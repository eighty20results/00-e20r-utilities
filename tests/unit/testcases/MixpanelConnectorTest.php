<?php
/**
 * Copyright (c) 2016 - 2022 - Eighty / 20 Results by Wicked Strong Chicks.
 * ALL RIGHTS RESERVED
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 * @package E20R\Tests\Unit\MixpanelConnectorTest
 */

namespace E20R\Tests\Unit;

use Brain\Monkey;
use Brain\Monkey\Functions;
use Codeception\Test\Unit;
use E20R\Metrics\Exceptions\HostNotDefined;
use E20R\Metrics\Exceptions\InvalidMixpanelKey;
use E20R\Metrics\Exceptions\UniqueIDException;
use E20R\Metrics\MixpanelConnector;
use E20R\Exceptions\InvalidSettingsKey;
use E20R\Utilities\Utilities;
use Exception;
use Mixpanel;
use Mockery;

/**
 * Unit tests for Mixpanel connector
 */
class MixpanelConnectorTest extends Unit {

	/**
	 * Mock of the Utilities class
	 *
	 * @var null|Mockery|Utilities
	 */
	private $mock_utils = null;

	/**
	 * Mock of the MixpanelConnector class
	 *
	 * @var null|MixpanelConnector|Mockery
	 */
	private $mock_mp = null;

	/**
	 * The ID of the User as implemented by MixPanel()
	 *
	 * @var string|null $user_id
	 */
	private $user_id = null;

	/**
	 * The setup function for this Unit Test suite
	 */
	protected function setUp(): void {
		if ( ! defined( 'ABSPATH' ) ) {
			define( 'ABSPATH', __DIR__ );
		}
		if ( ! defined( 'PLUGIN_PHPUNIT' ) ) {
			define( 'PLUGIN_PHPUNIT', true );
		}
		parent::setUp();
		Monkey\setUp();

		$this->loadFiles();
		$this->loadStubbedFunctions();

		$this->user_id = uniqid( 'e20rutil', true );
	}

	/**
	 * Teardown function for the Unit Tests
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		Monkey\tearDown();
		parent::tearDown();
	}

	/**
	 * Create function stubs for testing purposes
	 */
	public function loadStubbedFunctions() {
		if ( ! defined( 'DAY_IN_SECONDS' ) ) {
			define( 'DAY_IN_SECONDS', 60 * 60 * 24 );
		}
		if ( ! defined( 'PLUGIN_PHPUNIT' ) ) {
			define( 'PLUGIN_PHPUNIT', true );
		}
		Functions\stubs(
			array(
				'plugins_url'         => 'https://localhost:7254/wp-content/plugins/00-e20r-utilities/',
				'get_current_blog_id' => 0,
				'date_i18n'           => function( $date_string, $time ) {
					return date( $date_string, $time ); // phpcs:ignore WordPress.DateTime.RestrictedFunctions.date_date
				},
				'esc_attr'            => null,
				'esc_html__'          => null,
				'esc_attr__'          => null,
				'__'                  => null,
				'_e'                  => null,
			)
		);

		Functions\when( 'wp_die' )
			->justReturn( null );

		Functions\when( 'add_action' )
			->returnArg( 3 );

		Functions\expect( 'dirname' )
			->with( Mockery::contains( 'src/E20R/Metrics/MixpanelConnector.php' ) )
			->zeroOrMoreTimes()
			->andReturn( '../../../src/E20R/Metrics/' );

		Functions\expect( 'get_filesystem_method' )
			->zeroOrMoreTimes()
			->andReturn( 'direct' );

		Functions\expect( 'plugin_dir_path' )
			->zeroOrMoreTimes()
			->with( Mockery::contains( 'src/E20R/Metrics/MixpanelConnector.php' ) )
			->andReturn(
				function() {
					return __DIR__ . '/../../../src/E20R/Licensing/';
				}
			);

		Functions\expect( 'get_option' )
			->zeroOrMoreTimes()
			->with( Mockery::contains( 'e20r_mp_userid' ) )
			->andReturn(
				function( $option_name, $option_default ) {
					return $this->user_id;
				}
			);

		Functions\expect( 'wp_get_current_user' )
			->zeroOrMoreTimes()
			->andReturn( 10024 );

		try {
			$this->mock_mp = $this->makeEmpty(
				Mixpanel::class
			);
		} catch ( Exception $e ) {
			self::assertFalse( true, 'Error mocking Mixpanel() class: ' . $e->getMessage() );
		}

		$this->mock_utils = $this->makeEmpty(
			Utilities::class,
			array(
				'log'         => function( $text ) {
					// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
					error_log( "Mocked log: {$text}" );
					return null;
				},
				'add_message' => null,
			)
		);

		$this->mock_mp = $this->makeEmpty(
			Mixpanel::class,
			array()
		);
	}

	/**
	 * Load source files for the Unit Test to execute
	 */
	public function loadFiles() {
		require_once __DIR__ . '/../../../inc/autoload.php';
	}

	/**
	 * Test: Is the MixpanelConnector instantiatable
	 *
	 * @param string $token The token ID for the MixPanel API (mocked)
	 * @param array  $server The server name for hte MixPanel server to use
	 * @param string $class_name The expected class name
	 *
	 * @return void
	 *
	 * @test
	 * @dataProvider fixture_instantiated
	 *
	 * @throws HostNotDefined Thrown if the Mixpanel API host configuration is not defined
	 * @throws InvalidMixpanelKey Thrown if the mixpanel key is missing/not defined
	 */
	public function it_instantiated_the_mixpanel_connector( $token, $server, $class_name ) {
		Functions\expect( 'update_option' )
			->zeroOrMoreTimes()
			->with( 'e20r_mp_userid', null )
			->andReturn( true );

		$mp_class = new MixpanelConnector( $token, $server, $this->mock_mp, $this->mock_utils );
		self::assertSame( $class_name, get_class( $mp_class ), sprintf( '%1$s is not an instance of %2$s', get_class( $mp_class ), $class_name ) );
	}

	/**
	 * Fixture: Test instantiation of MixpanelConnector class
	 *
	 * @return array[]
	 */
	public function fixture_instantiated() {
		return array(
			array( 'a14f11781866c21172e4ebfd', array( 'host' => 'api-eu.mixpanel.com' ), MixpanelConnector::class ),
			array( 'anything', array( 'host' => 'api-eu.mixpanel.com' ), MixpanelConnector::class ),
			array( '10', array( 'host' => 'whocares' ), MixpanelConnector::class ),
		);
	}


	/**
	 * Test: The token is empty and we expect an exception to be raised
	 *
	 * @param null|string|int $bad_token The token value we expect will trigger the exception
	 *
	 * @return void
	 *
	 * @test
	 * @dataProvider fixture_bad_config
	 *
	 * @throws HostNotDefined Thrown if the Mixpanel API host configuration is not defined
	 * @throws InvalidMixpanelKey Thrown if the mixpanel key is missing/not defined
	 */
	public function it_raises_key_exception( $bad_token ) {

		Functions\expect( 'update_option' )
			->zeroOrMoreTimes()
			->with( 'e20r_mp_userid' )
			->andReturn( true );

		$this->expectException( InvalidMixpanelKey::class );
		new MixpanelConnector( $bad_token, null, $this->mock_mp, $this->mock_utils );
	}

	/**
	 * Fixture: Test bad token/API key value(s) for MixpanelConnector
	 *
	 * @return array
	 */
	public function fixture_bad_config() {
		return array(
			array( null, null ),
			array( '', null ),
			array( 0, array() ),
			array( 10, array() ),
			array( (int) '10', array() ),
			array( [], [] ), // phpcs:ignore Generic.Arrays.DisallowShortArraySyntax.Found
		);
	}

	/**
	 * Test: The bad host configuration and we expect a HostNotDefined exception to be raised
	 *
	 * @param string          $token The token value to use (skipping the InvalidMixpanelKey exception)
	 * @param null|string|int $bad_host_config The host config to trigger the HostNotDefined exception
	 *
	 * @return void
	 *
	 * @test
	 * @dataProvider fixture_bad_host_config
	 *
	 * @throws HostNotDefined Thrown if the Mixpanel API host configuration is not defined
	 */
	public function it_raises_host_exception( $token, $bad_host_config ) {

		Functions\expect( 'update_option' )
			->zeroOrMoreTimes()
			->with( 'e20r_mp_userid' )
			->andReturn( true );

		$this->expectException( HostNotDefined::class );
		new MixpanelConnector( $token, $bad_host_config, $this->mock_mp, $this->mock_utils );
	}

	/**
	 * Fixture: Test bad token/API key value(s) for MixpanelConnector
	 *
	 * @return array
	 */
	public function fixture_bad_host_config() {
		return array(
			array( 'invalid_valid_key', null ),
			array( 'invalid_valid_key', '' ),
			array( 'invalid_valid_key', 'anyhost.example.com' ),
			array( 'invalid_valid_key', '' ),
			array( 'invalid_valid_key', array() ),
			array( 'invalid_valid_key', [] ), // phpcs:ignore Generic.Arrays.DisallowShortArraySyntax.Found
		);
	}

	/**
	 * Test the get() method
	 *
	 * @param string $parameter The parameter name to attempt fetching data for
	 * @param mixed  $expected  The expected return value
	 * @param string $token     The MixPanel API server token
	 * @param array  $config    Host configuration for API server
	 *
	 * @return void
	 * @dataProvider fixture_successful_get
	 *
	 * @test
	 */
	public function it_successfully_gets_class_parameter_values(
		$parameter,
		$expected,
		$token = null,
		$config = array( 'host' => 'api-eu.mixpanel.com' )
	) {
		$result = null;
		Functions\expect( 'update_option' )
			->zeroOrMoreTimes()
			->with( 'e20r_mp_userid' )
			->andReturn( true );

		Functions\expect( 'gethostname' )
			->atLeast()
			->once()
			->andReturn( '7e131628a169' );

		Functions\expect( 'bin2hex' )
			->atLeast()
			->once()
			->andReturn( '69f3f1' );

		if ( function_exists( 'random_bytes' ) ) {
			Functions\expect( 'random_bytes' )
				->atLeast()
				->once()
				->andReturn( '69f3f1' );
		}

		if ( ! function_exists( 'random_bytes' ) && function_exists( 'openssl_random_pseudo_bytes' ) ) {
			Functions\expect( 'openssl_random_pseudo_bytes' )
				->atLeast()
				->once()
				->andReturn( '69f3f1' );
		}

		try {
			$mp     = new MixpanelConnector( $token, $config, $this->mock_mp, $this->mock_utils );
			$result = $mp->get( $parameter );
		} catch ( InvalidMixpanelKey $e ) {
			self::assertFalse(
				true,
				'Error instantiating MixpanelConnector() class: ' . $e->getMessage()
			);
		} catch ( InvalidSettingsKey $e ) {
			self::assertFalse(
				true,
				sprintf(
					'%1$s is not a valid parameter in the MixpanelConnector() class: %2$s',
					$parameter,
					$e->getMessage()
				)
			);
		} catch ( HostNotDefined $e ) {
			self::assertFalse(
				true,
				sprintf(
					'Error instantiating MixpanelConnector() class: %1$s',
					$e->getMessage()
				)
			);
		}
		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r
		self::assertSame( $expected, $result, "Error: {$expected} is not " . print_r( $result, true ) );
	}

	/**
	 * Fixture: Test successful get() method calls for MixpanelConnector()
	 *
	 * @return array[]
	 */
	public function fixture_successful_get() {
		return array(
			array( 'user_id', 'e20rutl69f3f1', 'a14f11781866c2117ab6487792e4ebfd', array( 'host' => 'api-eu.mixpanel.com' ) ),
			array( 'hostid', '7e131628a169 -> e20rutl69f3f1', 'a14f11781866c2117ab6487792e4ebfd', array( 'host' => 'api-eu.mixpanel.com' ) ),
		);
	}

	/**
	 * Test the get() method (errors)
	 *
	 * @param string $parameter The parameter name to attempt fetching data for
	 * @param mixed  $expected The expected return value
	 * @param mixed  $expected_exception The anticipated exception returned
	 * @param string $user_id The ID of the un-identified User (Mixpanel)
	 * @param string $token The Mixpanel server API token (mocked)
	 * @param array  $config The Mixpanel server API configuration (mocked)
	 *
	 * @return void
	 *
	 * @dataProvider fixture_unsuccessful_get
	 * @test
	 */
	public function it_requests_non_existing_parameters_and_raises_exception(
		$parameter,
		$expected,
		$expected_exception,
		$user_id,
		$token = null,
		$config = array( 'host' => 'api-eu.mixpanel.com' )
	) {
		if ( null !== $expected_exception ) {
			$this->expectException( $expected_exception );
		}

		Functions\expect( 'update_option' )
			->zeroOrMoreTimes()
			->with( 'e20r_mp_userid' )
			->andReturn( true );

		try {
			$this->mock_mp = $this->constructEmpty(
				Mixpanel::class,
				array( $token, $config ),
				array(
					'get_user_id' => $user_id,
				)
			);
		} catch ( Exception $e ) {
			self::assertFalse( true, 'Error mocking Mixpanel() class: ' . $e->getMessage() );
		}

		$mp_class = new MixpanelConnector( $token, $config, $this->mock_mp, $this->mock_utils );
		$returned = $mp_class->get( $parameter );
		self::assertSame( $expected, $returned, "Error: The returned value is expected to be '{$expected}' but is {$returned}" );
	}

	/**
	 * The unsuccessful get fixture
	 *
	 * @return array[]
	 */
	public function fixture_unsuccessful_get() {
		return array(
			// parameter, expected, expected_exception, user_id, token, config_array
			array( 'not_a_valid_class_property', null, InvalidSettingsKey::class, null, 'a14f11781866c2117792e4ebfd', array( 'host' => 'api-eu.mixpanel.com' ) ),
			array( 'not_a_valid_class_property', null, InvalidMixpanelKey::class, 'e20rutl69f3f1', null, array( 'host' => 'api-eu.mixpanel.com' ) ),
		);
	}

	/**
	 * Test the uniq_real_id() method
	 *
	 * @param string      $prefix string prefix to use for
	 * @param int         $length The total length of the ID we're allowed to generate
	 * @param mixed       $bytes "Random" bytes returned by random_bytes() or openssl_random_pseudo_bytes()
	 * @param string      $expected The resulting string
	 * @param string|null $exception If applicable, the exception we expect the method to throw
	 *
	 * @return void
	 *
	 * @dataProvider fixture_generate_random_bytes
	 *
	 * @test
	 */
	public function it_returns_unique_ids( $prefix, $length, $bytes, $expected, $exception ) {
		$this->markTestSkipped( 'Problem with generating standard/random-ish IDs' );

		Functions\when( 'random_bytes' )
			->justReturn( $bytes );
		Functions\when( 'openssl_random_pseudo_bytes' )
			->justReturn( $bytes );
		Functions\expect( 'update_option' )
			->zeroOrMoreTimes()
			->with( 'e20r_mp_userid' )
			->andReturn( true );

		if ( null !== $exception ) {
			$this->mock_utils->log( "We're expecting an exception!" );
			$this->expectException( $exception );
		}
		$mp_class = new MixpanelConnector( 'dummytoken', array( 'host' => 'localhost.example.com' ), $this->mock_mp, $this->mock_utils );
		$result   = $mp_class->uniq_real_id( $prefix, $length );

		self::assertSame( $expected, $result );
	}

	/**
	 * Fixture: Generate the bytes/expected result for the 2 random function in uniq_real_id() method
	 *
	 * @return array Array of fixtures to use in test
	 * @throws Exception Raised if something goes wrong with random_bytes()
	 */
	public function fixture_generate_random_bytes() {
		$retval = array();
		foreach ( range( 0, 10 ) as $index ) {
			$prefix     = "e{$index}rml";
			$str_length = strlen( $prefix );
			// phpcs:ignore WordPress.WP.AlternativeFunctions.rand_mt_rand
			$length = (int) mt_rand( 10, 15 );
			$bytes  = random_bytes( (int) ceil( $length / 2 ) );
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( "Selected length: {$length}" );
			$length = $length - $str_length;
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( "Length after prefix '{$prefix}' ({$str_length}) has been deducted: {$length}" );
			$exception = null;
			if ( $length <= 0 || ( $str_length > ( $length - 2 ) ) ) {
				$exception = UniqueIDException::class;
			}
			$expected = $prefix . substr( bin2hex( $bytes ), 0, $length );
			$retval[] = array( $prefix, $length, $bytes, $expected, $exception );
		}
		return $retval;
	}
	/**
	 * Tests the get_user_id() method
	 *
	 * @param string|null $get_option_value The user_id string we're pretending to have saved in the WP Options table
	 * @param string      $uniq_real_id The unique ID generated
	 *
	 * @return void
	 *
	 * @throws InvalidSettingsKey Raised if the supplied 'get()' method parameter is missing in the MixpanelConnector() class
	 *
	 * @dataProvider fixture_user_ids
	 * @test
	 */
	public function it_configured_a_user_id( $get_option_value, $uniq_real_id ) {
		$this->markTestSkipped( 'Problem with generating user IDs' );

		Functions\expect( 'get_option' )
			->zeroOrMoreTimes()
			->with( 'e20r_mp_userid' )
			->andReturn( $get_option_value );

		Functions\expect( 'update_option' )
			->zeroOrMoreTimes()
			->with( 'e20r_mp_userid' )
			->andReturn( true );

		$mp_class = $this->construct(
			MixpanelConnector::class,
			array( 'dummytoken', array( 'host' => 'localhost.example.com' ), $this->mock_mp, $this->mock_utils ),
			array(
				'uniq_real_id' => function ( $prefix, $len ) use ( $uniq_real_id, $get_option_value ) {
					$value = $get_option_value;
					if ( empty( $get_option_value ) ) {
						$value = $uniq_real_id;
					}

					return $value;
				},
			)
		);

		$mp_class->get_user_id();
		$result = $mp_class->get( 'user_id' );
		if ( null === $get_option_value ) {
			self::assertSame( $uniq_real_id, $result, "Error: Unique ID value ({$uniq_real_id}) is not equal to the class member parameter setting '{$result}'" );
		} else {
			self::assertSame( $get_option_value, $result, "Error: Specified option value ({$get_option_value}) is not equal to the class member parameter setting {$result}" );
		}
	}
	/**
	 * Fixture for the get_user_id() method test(s)
	 *
	 * @return array[]
	 */
	public function fixture_user_ids() {
		return array(
			// get_option_value, uniq_id, expected exception
			array( null, 'e20rutl69f3f1' ),
		);
	}
}
