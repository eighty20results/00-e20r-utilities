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

use Codeception\Test\Unit;
use E20R\Licensing\Exceptions\InvalidMixpanelKey;
use E20R\Licensing\Exceptions\InvalidSettingsKey;
use E20R\Licensing\Exceptions\UserNotDefined;
use E20R\Metrics\MixpanelConnector;
use E20R\Utilities\Utilities;
use Brain\Monkey;
use Brain\Monkey\Functions;
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
					return uniqid( 'e20rutil', true );
				}
			);

		Functions\expect( 'wp_get_current_user' )
			->zeroOrMoreTimes()
			->andReturn( 10024 );

		$this->mock_utils = $this->makeEmpty(
			Utilities::class,
			array(
				'log' => function( $text ) {
					// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
					error_log( "Mocked log: {$text}" );
					return null;
				},
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
		require_once __DIR__ . '/../inc/class-wp-filesystem-base.php';
		require_once __DIR__ . '/../inc/class-wp-filesystem-direct.php';
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
	 */
	public function test_instantiate_class( $token, $server, $class_name ) {
		try {
			$this->mock_mp = $this->construct(
				Mixpanel::class,
				array( $token, $server )
			);
		} catch ( Exception $e ) {
			self::assertFalse( true, 'Error mocking Mixpanel() class: ' . $e->getMessage() );
		}

		try {
			$mp_class = new MixpanelConnector( $token, $server, $this->mock_mp );
			self::assertInstanceOf(
				$class_name,
				$mp_class,
				sprintf(
					'%1$s is not an instance of %2$s',
					get_class( $mp_class ),
					$class_name
				)
			);
		} catch ( InvalidMixpanelKey | UserNotDefined $e ) {
			self::assertFalse( true, 'Error instantiating MixpanelConnector() class: ' . $e->getMessage() );
		}
	}

	/**
	 * Fixture: Test instantiation of MixpanelConnector class
	 *
	 * @return array[]
	 */
	public function fixture_instantiated() {
		return array(
			array( 'a14f11781866c2117ab6487792e4ebfd', array( 'host' => 'api-eu.mixpanel.com' ), 'E20R\Metrics\MixpanelConnector' ),
		);
	}

	/**
	 * Test the get() method
	 *
	 * @param string $parameter The parameter name to attempt fetching data for
	 * @param mixed  $expected The expected return value
	 * @param string $token The MixPanel API server token
	 * @param array  $config Host configuration for API server
	 *
	 * @return void
	 * @dataProvider fixture_successful_get
	 *
	 * @test
	 */
	public function test_successful_get_operations( $parameter, $expected, $token = null, $config = array( 'host' => 'api-eu.mixpanel.com' ) ) {
		$result = null;

		try {
			$this->mock_mp = $this->construct(
				Mixpanel::class,
				array( $token, $config )
			);
		} catch ( Exception $e ) {
			self::assertFalse( true, 'Error mocking Mixpanel() class: ' . $e->getMessage() );
		}

		try {
			$mp     = new MixpanelConnector( $token, $config, $this->mock_mp );
			$result = $mp->get( $parameter );
		} catch ( InvalidMixpanelKey | UserNotDefined $e ) {
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
		}

		self::assertSame( $expected, $result, "Error: {$expected} is not {$result}" );
	}

	/**
	 * Fixture: Test successful get() method calls for MixpanelConnector()
	 *
	 * @return array[]
	 */
	public function fixture_successful_get() {
		return array(
			array( 'user_id', null, 'a14f11781866c2117ab6487792e4ebfd', array( 'host' => 'api-eu.mixpanel.com' ) ),
		);
	}
	/**
	 * Test the get() method (errors)
	 *
	 * @param string $parameter The parameter name to attempt fetching data for
	 * @param mixed  $expected The expected return value
	 * @param mixed  $expected_exception The anticipated exception returned
	 * @param string $token The Mixpanel server API token (mocked)
	 * @param array  $config The Mixpanel server API configuration (mocked)
	 *
	 * @return void
	 *
	 * @dataProvider fixture_unsuccessful_get
	 * @test
	 */
	public function test_unsuccessful_get_operations(
		$parameter,
		$expected,
		$expected_exception,
		$token = null,
		$config = array( 'host' => 'api-eu.mixpanel.com' )
	) {
		if ( null !== $expected_exception ) {
			$this->expectExceptionMessageRegExp( "/No API key found for the Mixpanel connector|Invalid parameter \({$parameter}\) for the MixpanelConnector\(\) class/" );
		}

		try {
			$this->mock_mp = $this->construct(
				Mixpanel::class,
				array( $token, $config ),
				array(
					'get_user_id' => 10024,
				)
			);
		} catch ( Exception $e ) {
			self::assertFalse( true, 'Error mocking Mixpanel() class: ' . $e->getMessage() );
		}

		$mp_class = new MixpanelConnector( $token, $config, $this->mock_mp );
		$returned = $mp_class->get( $parameter );
		self::assertSame( $expected, $returned );
	}

	/**
	 * The unsuccessful get fixture
	 *
	 * @return array[]
	 */
	public function fixture_unsuccessful_get() {
		return array(
			array( 'not_a_valid_class_property', null, InvalidSettingsKey::class, 'a14f11781866c2117ab6487792e4ebfd', array( 'host' => 'api-eu.mixpanel.com' ) ),
		);
	}
}
