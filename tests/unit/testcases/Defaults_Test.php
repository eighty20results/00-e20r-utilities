<?php
/**
 *
 * Copyright (c) 2021. - Eighty / 20 Results by Wicked Strong Chicks.
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
 * @package E20R\Tests\Unit\Defaults_Tests
 */

namespace E20R\Tests\Unit;

use Codeception\AssertThrows;
use Codeception\Test\Unit;
use Brain\Monkey;
use Brain\Monkey\Functions;
use E20R\Utilities\Utilities;
use E20R\Licensing\Settings\Defaults;
use E20R\Licensing\Exceptions\BadOperation;
use E20R\Licensing\Exceptions\ConfigDataNotFound;
use E20R\Licensing\Exceptions\InvalidSettingsKey;
use Exception;
use Throwable;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;

/**
 * Unit tests for the Defaults class
 */
class Defaults_Test extends Unit {

	use MockeryPHPUnitIntegration;
	use AssertThrows;

	/**
	 * Utilities mock class
	 *
	 * @var Utilities|null $mock_utils
	 */
	private $mock_utils = null;

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
			->with( Mockery::contains( 'src/E20R/Licensing/Defaults.php' ) )
			->zeroOrMoreTimes()
			->andReturn( '../../../src/E20R/Licensing/' );

		Functions\expect( 'get_filesystem_method' )
			->zeroOrMoreTimes()
			->andReturn( 'direct' );

		Functions\expect( 'plugin_dir_path' )
			->zeroOrMoreTimes()
			->with( Mockery::contains( 'src/E20R/Licensing/Defaults.php' ) )
			->andReturn(
				function() {
					return __DIR__ . '/../../../src/E20R/Licensing/';
				}
			);

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
	 * Test the instantiation of the Defaults() class
	 *
	 * @param bool|null $rest_setting - Whether to use REST API endpoint or not
	 * @param bool      $expected - The expected test result
	 *
	 * @dataProvider fixture_rest_settings
	 */
	public function test_no_utilities( $rest_setting, $expected ) {
		$defaults = new Defaults( $rest_setting );
		self::assertSame( $expected, $defaults->get( 'use_rest' ) );
	}

	/**
	 * Fixture for test_no_utilities()
	 *
	 * @return array
	 */
	public function fixture_rest_settings() {
		return array(
			array( false, false ),
			array( true, true ),
		);
	}
	/**
	 * Testing instantiation of the Default() class
	 *
	 * @param bool             $use_rest - Whether to use the REST API endpoint or not
	 * @param bool             $var_debug - debug_log setting
	 * @param string|null      $version - Version info
	 * @param string|null      $server_url - The URL for the license server
	 * @param null|bool        $const_for_debug_logging - Use a Constant to set debug_logging
	 * @param string|null      $const_server_url - Use a constant to set the Server URL for the license server
	 * @param string|null|bool $config - JSON blob containing the default settings for this class
	 * @param array            $expected - Array of expected values to test against
	 *
	 * @throws ConfigDataNotFound - The config data wasn't found/is missing
	 * @throws InvalidSettingsKey - The parameter specified doesn't exist
	 * @throws Throwable - Can be re-thrown
	 *
	 * @dataProvider fixture_instantiate_class
	 */
	public function test_instantiate_class(
		?bool $use_rest,
		?bool $var_debug,
		?string $version,
		?string $server_url,
		?bool $const_for_debug_logging,
		?string $const_server_url,
		$config,
		array $expected
	) {

		if ( empty( $config ) ) {
			$this->assertThrowsWithMessage(
				ConfigDataNotFound::class,
				'No configuration data found',
				function() use ( $use_rest, $config ) {
					new Defaults( $use_rest, $this->mock_utils, $config );
				}
			);
			return;
		} else {
			$settings = new Defaults( $use_rest, $this->mock_utils, $config );

			if ( null !== $const_for_debug_logging ) {
				$this->mock_utils->log( "Using E20R_LICENSING_DEBUG constant to set debug logging to: {$const_for_debug_logging}" );
				$settings->constant( 'E20R_LICENSING_DEBUG', $settings::UPDATE_CONSTANT, $const_for_debug_logging );
			}

			if ( null !== $const_server_url ) {
				$settings->constant( 'E20R_LICENSE_SERVER_URL', $settings::UPDATE_CONSTANT, $const_server_url );
			}
		}

		$this->assertDoesNotThrow(
			InvalidSettingsKey::class,
			function() use ( $settings, $var_debug ) {
				$settings->unlock( 'debug_logging' );
				$settings->set( 'debug_logging', $var_debug );
				$settings->lock( 'debug_logging' );
			}
		);

		$this->assertDoesNotThrow(
			InvalidSettingsKey::class,
			function() use ( $settings, $version ) {
				$settings->unlock( 'version' );
				$settings->set( 'version', $version );
				$settings->lock( 'version' );
			}
		);

		$this->assertDoesNotThrow(
			InvalidSettingsKey::class,
			function() use ( $settings, $server_url ) {
				$settings->unlock( 'server_url' );
				$settings->set( 'server_url', $server_url );
				$settings->lock( 'server_url' );
			}
		);

		self::assertSame( $expected['use_rest'], $settings->get( 'use_rest' ), 'Could not select expected REST API or AJAX mode: ' . $use_rest );
		self::assertSame( $expected['debug_logging'], $settings->get( 'debug_logging' ), "Could not set debug_logging to {$var_debug}, is {$const_for_debug_logging}" );
		self::assertSame( $expected['version'], $settings->get( 'version' ), "Could not set version to {$version} (expected: {$expected['version']})" );
		self::assertSame( $expected['server_url'], $settings->get( 'server_url' ), "Could not set server URL to {$server_url}" );
		self::assertSame( $expected['connection_uri'], $settings->get( 'connection_uri' ), "Could not set connection_uri to {$server_url}" . ( $use_rest ? $settings->get( 'rest_url' ) : $settings->get( 'ajax_url' ) ) );
		self::assertSame( $expected['store_code'], $settings->get( 'store_code' ), 'Didn\'t return the expected WooCommerce Store Code!' );

		// Test the inverted rest/ajax flag
		$settings->unlock( 'use_rest' );
		$settings->set( 'use_rest', ( ! $use_rest ) );
		$settings->lock( 'use_rest' );
		self::assertSame( ( ! $expected['use_rest'] ), $settings->get( 'use_rest' ) );

		// And that should result in the connection URI changing
		$new_connection_uri = sprintf( '%1$s%2$s', $settings->get( 'server_url' ), ( $settings->get( 'use_rest' ) ? $settings->get( 'rest_url' ) : $settings->get( 'ajax_url' ) ) );
		self::assertSame( $new_connection_uri, $settings->get( 'connection_uri' ), "Could not set connection_uri to {$new_connection_uri}" );
	}

	/**
	 * Fixture for the LicenseSettings constructor test
	 *
	 * @return array[]
	 * @throws Exception - Default exception to catch
	 */
	public function fixture_instantiate_class(): array {
		return array(
			// use_rest, var_debug, version, server_url, const_for_debug_logging, const_server_url, use_phpunit_constant, config file contents, result array
			array( // # 0
				false,
				false,
				'2.0',
				$this->fixture_get_config( 1, 'server_url' ),
				null, // E20R_LICENSING_DEBUG
				null, // The E20R_LICENSE_SERVER_URL
				$this->fixture_load_config_json( 1 ),
				array(
					'use_rest'       => false,
					'debug_logging'  => false,
					'version'        => '2.0',
					'server_url'     => $this->fixture_get_config( 1, 'server_url' ),
					'connection_uri' => $this->fixture_get_config( 1, 'server_url' ) . '/wp-admin/wp-ajax.php',
					'store_code'     => $this->fixture_get_config( 1, 'store_code' ),
				),
			),
			array( // # 1
				false,
				false,
				'3.0',
				$this->fixture_get_config( 1, 'server_url' ),
				null, // E20R_LICENSING_DEBUG
				null, // The E20R_LICENSE_SERVER_URL
				$this->fixture_load_config_json( 1 ),
				array(
					'use_rest'       => false,
					'debug_logging'  => false,
					'version'        => '3.0',
					'server_url'     => $this->fixture_get_config( 1, 'server_url' ),
					'connection_uri' => $this->fixture_get_config( 1, 'server_url' ) . '/wp-admin/wp-ajax.php',
					'store_code'     => $this->fixture_get_config( 1, 'store_code' ),
				),
			),
			array( // # 2
				false,
				false,
				'3.1',
				$this->fixture_get_config( 1, 'server_url' ),
				null, // E20R_LICENSING_DEBUG
				null, // The E20R_LICENSE_SERVER_URL
				$this->fixture_load_config_json( 1 ),
				array(
					'use_rest'       => false,
					'debug_logging'  => false,
					'version'        => '3.1',
					'server_url'     => $this->fixture_get_config( 1, 'server_url' ),
					'connection_uri' => $this->fixture_get_config( 1, 'server_url' ) . '/wp-admin/wp-ajax.php',
					'store_code'     => $this->fixture_get_config( 1, 'store_code' ),
				),
			),
			array( // # 3
				true,
				false,
				'2.0',
				$this->fixture_get_config( 1, 'server_url' ),
				null, // E20R_LICENSING_DEBUG
				null, // The E20R_LICENSE_SERVER_URL
				$this->fixture_load_config_json( 1 ),
				array(
					'use_rest'       => true,
					'debug_logging'  => false,
					'version'        => '2.0',
					'server_url'     => $this->fixture_get_config( 1, 'server_url' ),
					'connection_uri' => $this->fixture_get_config( 1, 'server_url' ) . '/wp-json/woo-license-server/v1',
					'store_code'     => $this->fixture_get_config( 1, 'store_code' ),
				),
			),
			array( // # 4
				true,
				true, // var_debug
				'3.1',
				$this->fixture_get_config( 2, 'server_url' ),
				null, // E20R_LICENSING_DEBUG
				null, // The E20R_LICENSE_SERVER_URL
				$this->fixture_load_config_json( 2 ),
				array(
					'use_rest'       => true,
					'debug_logging'  => true,
					'version'        => '3.1',
					'server_url'     => $this->fixture_get_config( 2, 'server_url' ),
					'connection_uri' => $this->fixture_get_config( 2, 'server_url' ) . '/wp-json/woo-license-server/v1',
					'store_code'     => $this->fixture_get_config( 2, 'store_code' ),
				),
			),
			array( // # 5
				true,
				true, // var_debug
				'3.1',
				'',
				false, // E20R_LICENSING_DEBUG
				null, // The E20R_LICENSE_SERVER_URL
				$this->fixture_load_config_json( 1 ),
				array(
					'use_rest'       => true,
					'debug_logging'  => false, // Because the E20R_LICENSING_DEBUG constant is supposed to be set to false
					'version'        => '3.1',
					'server_url'     => '',
					'connection_uri' => '/wp-json/woo-license-server/v1',
					'store_code'     => $this->fixture_get_config( 1, 'store_code' ),
				),
			),
			array( // # 6
				true,
				true,
				'3.1',
				null,
				null, // E20R_LICENSING_DEBUG
				null, // The E20R_LICENSE_SERVER_URL
				$this->fixture_load_config_json( 1 ),
				array(
					'use_rest'       => true,
					'debug_logging'  => true,
					'version'        => '3.1',
					'server_url'     => null,
					'connection_uri' => '/wp-json/woo-license-server/v1',
					'store_code'     => $this->fixture_get_config( 1, 'store_code' ),
				),
			),
			array( // # 7
				true,
				false, // Should get overridden by the E20R_LICENSING_DEBUG constant
				'3.1',
				$this->fixture_get_config( 2, 'server_url' ),
				true, // E20R_LICENSING_DEBUG
				null, // The E20R_LICENSE_SERVER_URL
				$this->fixture_load_config_json( 2 ),
				array(
					'use_rest'       => true,
					'debug_logging'  => true, // Because the E20R_LICENSING_DEBUG constant is supposed to be set to true
					'version'        => '3.1',
					'server_url'     => $this->fixture_get_config( 2, 'server_url' ),
					'connection_uri' => $this->fixture_get_config( 2, 'server_url' ) . '/wp-json/woo-license-server/v1',
					'store_code'     => $this->fixture_get_config( 2, 'store_code' ),
				),
			),
			array( // # 8
				false,
				false, // Should not be overridden by the E20R_LICENSING_DEBUG constant
				'3.1',
				$this->fixture_get_config( 3, 'server_url' ),
				null, // E20R_LICENSING_DEBUG
				$this->fixture_get_config( 2, 'server_url' ), // The E20R_LICENSE_SERVER_URL
				$this->fixture_load_config_json( 3 ),
				array(
					'use_rest'       => false,
					'debug_logging'  => false,
					'version'        => '3.1',
					'server_url'     => $this->fixture_get_config( 2, 'server_url' ),
					'connection_uri' => $this->fixture_get_config( 2, 'server_url' ) . '/wp-admin/wp-ajax.php',
					'store_code'     => $this->fixture_get_config( 3, 'store_code' ),
				),
			),
			array( // # 9
				false,
				false, // Should get overridden by the E20R_LICENSING_DEBUG constant
				null,
				$this->fixture_get_config( 3, 'server_url' ), // Should get overridden by the E20R_LICENSE_SERVER_URL constant
				true, // E20R_LICENSING_DEBUG
				null, // The E20R_LICENSE_SERVER_URL
				$this->fixture_load_config_json( 3 ),
				array(
					'use_rest'       => false,
					'debug_logging'  => true, // Because the E20R_LICENSING_DEBUG constant is supposed to be set to true
					'version'        => '3.2',
					'server_url'     => $this->fixture_get_config( 3, 'server_url' ),
					'connection_uri' => $this->fixture_get_config( 3, 'server_url' ) . '/wp-admin/wp-ajax.php',
					'store_code'     => $this->fixture_get_config( 3, 'store_code' ),
				),
			),
			array( // # 10
				false,
				false, // Should get overridden by the E20R_LICENSING_DEBUG constant
				null,
				$this->fixture_get_config( 3, 'server_url' ), // Should get overridden by the E20R_LICENSE_SERVER_URL constant
				true, // E20R_LICENSING_DEBUG
				null, // The E20R_LICENSE_SERVER_URL
				$this->fixture_load_config_json( 3 ),
				array(
					'use_rest'       => false,
					'debug_logging'  => true, // Because the E20R_LICENSING_DEBUG constant is supposed to be set to false
					'version'        => '3.2',
					'server_url'     => $this->fixture_get_config( 3, 'server_url' ),
					'connection_uri' => $this->fixture_get_config( 3, 'server_url' ) . '/wp-admin/wp-ajax.php',
					'store_code'     => $this->fixture_get_config( 3, 'store_code' ),
				),
			),
			array( // # 11
				false,
				false, // Should get overridden by the E20R_LICENSING_DEBUG constant
				null,
				$this->fixture_get_config( 3, 'server_url' ), // Should get overridden by the E20R_LICENSE_SERVER_URL constant
				null, // E20R_LICENSING_DEBUG
				null, // The E20R_LICENSE_SERVER_URL
				false, // config file is missing and should raise an exception
				array(
					'use_rest'       => false,
					'debug_logging'  => false,
					'version'        => '3.2',
					'server_url'     => $this->fixture_get_config( 3, 'server_url' ),
					'connection_uri' => $this->fixture_get_config( 3, 'server_url' ) . '/wp-admin/wp-ajax.php',
					'store_code'     => $this->fixture_get_config( 3, 'store_code' ),
				),
			),
		);
	}

	/**
	 * Select the loaded fixture information to return
	 *
	 * @param int    $key - ID of the fixture info to fetch
	 * @param string $field - The field name to use
	 *
	 * @return string
	 * @throws Exception - Exception re-thrown
	 */
	public function fixture_get_config( int $key, string $field ): string {
		try {
			$json_blob = $this->fixture_load_config_json( $key );
		} catch ( Exception $e ) {
			throw $e;
		}
		$config = json_decode( $json_blob, true );
		return $config[ $field ];
	}

	/**
	 * Load one of the test fixtures as a JSON blob and parse it
	 *
	 * @param int $key - Key for JSON test file to use
	 *
	 * @return string
	 * @throws Exception - Exception to re-raise
	 */
	public function fixture_load_config_json( int $key ): string {
		$filename = sprintf( __DIR__ . '/../inc/mock_config_%d.json', $key );
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$content = file_get_contents( $filename );
		if ( false === $content ) {
			throw new Exception( 'Error: Unable to load ' . $filename );
		}
		return $content;
	}

	/**
	 * Happy path test for Defaults::read_config()
	 *
	 * @param null|string|bool $json - JSON blob containing default settings for this test
	 * @param array            $expected - The expected values set by the JSON blob
	 *
	 * @dataProvider fixture_read_config_success
	 * @throws InvalidSettingsKey - The specified key in the JSON blob is not present in the Defaults class
	 * @throws Exception - The default exception
	 * @throws Throwable - A throwable exception
	 */
	public function test_read_config_success( $json, array $expected ) {

		$plugin_defaults = new Defaults( false, $this->mock_utils, $json );

		if ( ! empty( $json ) ) {
			try {
				$plugin_defaults->read_config( $json );
				self::assertSame( $expected['server_url'], $plugin_defaults->get( 'server_url' ) );
				self::assertSame( $expected['store_code'], $plugin_defaults->get( 'store_code' ) );
			} catch ( ConfigDataNotFound | InvalidSettingsKey | Exception $e ) {
				self::assertFalse( true, $e->getMessage() );
			}
		} else {
			$plugin_defaults->constant( 'E20R_STORE_CONFIG', Defaults::UPDATE_CONSTANT, $json );
			$this->assertThrowsWithMessage(
				ConfigDataNotFound::class,
				'No configuration data found',
				function() use ( $json, $plugin_defaults ) {
					$plugin_defaults->read_config( $json );
				}
			);
			return;
		}
	}

	/**
	 * Test fixture for the Defaults_Test::test_read_config_success()
	 *
	 * @return array
	 * @throws Exception - Raises the default exception
	 */
	public function fixture_read_config_success(): array {
		$fixture = array();

		foreach ( range( 1, 11 ) as $key ) {
			$json_key        = rand( 1, 3 ); // phpcs:ignore WordPress.WP.AlternativeFunctions.rand_rand
			$expected        = array(
				'server_url' => $this->fixture_get_config( $json_key, 'server_url' ),
				'store_code' => $this->fixture_get_config( $json_key, 'store_code' ),
			);
			$fixture[ $key ] = array( $this->fixture_load_config_json( $json_key ), $expected );
		}

		// Testing with the build-in Defaults::$E20R_STORE_CONFIG
		$fixture[] = array(
			null, // Using FALSE to signify that there's no config_json present
			array(
				'store_code' => 'L4EGy6Y91a15ozt',
				'server_url' => 'https://eighty20results.com',
			),
		);

		return $fixture;
	}


	/**
	 * Test the failure (exceptions) in Defaults::read_config()
	 *
	 * @param string $passed_json - JSON to use when setting defaults for the Defaults() class
	 * @param string $json_config - The configuration data to use
	 * @param string $expected - The expected test results
	 *
	 * @throws BadOperation - Thrown when an invalid operation is attempted for the constant(s)
	 * @throws ConfigDataNotFound - Thrown when configuration data is empty
	 * @throws InvalidSettingsKey - Thrown when the named key doesn't exist as a class parameter
	 *
	 * @dataProvider fixture_bad_configs
	 * @covers \E20R\Licensing\Settings\Defaults::read_config()
	 */
	public function test_read_config_failure( $passed_json, $json_config, $expected ) {
		$plugin_defaults = new Defaults( false, $this->mock_utils );
		$plugin_defaults->constant( 'E20R_STORE_CONFIG', Defaults::UPDATE_CONSTANT, $json_config );

		if ( '{}' === $json_config ) {
			$this->mock_utils->log( 'Mocking json_decode()' );
			Functions\expect( 'json_decode' )
				->andReturn( null );
		}

		try {
			$plugin_defaults->read_config( $passed_json );
			self::assertSame( $expected, null );
		} catch ( Exception | ConfigDataNotFound | InvalidSettingsKey $exception ) {
			self::assertInstanceOf( $expected, $exception );
		}
	}

	/**
	 * Fixture for the test_read_config_failure()
	 *
	 * @return array[]
	 */
	public function fixture_bad_configs() {
		return array(
			array( null, null, ConfigDataNotFound::class ),
			array( '{}', '{}', ConfigDataNotFound::class ),
			array( '{"store_code":"123456789010","server_url":"https://eighty20results.com"}', null, null ),
		);
	}
	/**
	 * Test default constant settings
	 *
	 * @param string $constant_name - Name of constant to test
	 * @param string $expected - Expected value returned for that constant
	 *
	 * @dataProvider fixture_read_constant_defaults
	 * @covers \E20R\Licensing\Settings\Defaults::constant()
	 */
	public function test_constant_read_defaults( string $constant_name, $expected ) {
		try {
			$defaults = new Defaults( false, $this->mock_utils );
			$result   = $defaults->constant( $constant_name, Defaults::READ_CONSTANT );
			self::assertSame( $expected, $result );
		} catch ( ConfigDataNotFound | InvalidSettingsKey | Exception $e ) {
			error_log( $e->getMessage() . " for {$constant_name} " ); // phpcs:ignore
		}
	}

	/**
	 * Fixture for happy path Defaults::constant() testing
	 *
	 * @return \string[][]
	 */
	public function fixture_read_constant_defaults(): array {
		// constant_name, expected
		return array(
			array( 'E20R_STORE_CONFIG', '{"store_code":"L4EGy6Y91a15ozt","server_url":"https://eighty20results.com"}' ),
			array( 'E20R_LICENSE_SECRET_KEY', '5687dc27b50520.33717427' ),
			array( 'E20R_LICENSE_SERVER', 'eighty20results.com' ),
			array( 'E20R_LICENSING_DEBUG', false ),
			array( 'E20R_LICENSE_SERVER_URL', 'https://eighty20results.com' ),
			array( 'E20R_LICENSE_MAX_DOMAINS', 2048 ),
			array( 'E20R_LICENSE_REGISTERED ', 1024 ),
			array( 'E20R_LICENSE_DOMAIN_ACTIVE', 512 ),
			array( 'E20R_LICENSE_ERROR', 256 ),
			array( 'E20R_LICENSE_BLOCKED', 128 ),
		);
	}


	/**
	 * Tests updating "constants" in the Defaults() class
	 *
	 * @param int         $operation - Operation to perform for the constant_name specified
	 * @param string      $constant_name - Name of the constant to test
	 * @param mixed       $constant_value - Value to set the constant to
	 * @param mixed       $expected - expected value to be returned for the tested constant
	 * @param null|string $raise_exception - Exception we expect to see when testing
	 *
	 * @dataProvider fixture_update_constants
	 * @covers       \E20R\Licensing\Settings\Defaults::constant()
	 */
	public function test_constant_update_with_errors( $operation, $constant_name, $constant_value, $expected, $raise_exception ) {

		try {
			$defaults = new Defaults( true, $this->mock_utils );
		} catch ( ConfigDataNotFound | InvalidSettingsKey $e ) {
			$this->assertFalse(
				true,
				'Error: Unexpected exception when instantiating the Defaults() class! -> ' . $e->getMessage()
			);
		}

		if (
			null !== $raise_exception ||
			! in_array( $operation, array( $defaults::READ_CONSTANT, $defaults::UPDATE_CONSTANT ), true )
		) {
			$this->assertThrows(
				$raise_exception,
				function() use ( $defaults, $operation, $constant_name, $constant_value, $expected ) {
					$result = $defaults->constant( $constant_name, $operation, $constant_value );
					self::assertSame( $expected, $result );
				}
			);
			return;
		}

		try {
			$result = $defaults->constant( $constant_name, $operation, $constant_value );
			self::assertSame( $expected, $result );
		} catch ( InvalidSettingsKey $e ) {
			self::assertFalse( true, $e->getMessage() );
		}

	}

	/**
	 * Fixture to test various updates to the (private and 'sort of fake') Defaults() constants
	 *
	 * @return array[]
	 */
	public function fixture_update_constants() : array {
		// operation, constant_name, constant_value, expected, raises_exception
		return array(
			array( Defaults::UPDATE_CONSTANT, 'E20R_LICENSE_SECRET_KEY', 'some_secret_key_1', true, null ),
			array( Defaults::UPDATE_CONSTANT, 'E20R_LICENSING_DEBUG', true, true, null ),
			array( Defaults::UPDATE_CONSTANT, 'E20R_STORE_CONFIG', '{}', true, null ),
			array( Defaults::UPDATE_CONSTANT, 'E20R_LICENSE_SERVER', 'example.com', true, null ),
			array( Defaults::UPDATE_CONSTANT, 'E20R_LICENSE_SERVER_URL', 'https://example.com', true, null ),
			array( 10, 'E20R_LICENSE_SERVER', 'example.com', false, BadOperation::class ),
			array( Defaults::UPDATE_CONSTANT, 'E20R_SERVER_URL', 'https://example.com', false, InvalidSettingsKey::class ),
			array( Defaults::UPDATE_CONSTANT, 'E20R_LICENSE', true, false, InvalidSettingsKey::class ),
		);
	}

	/**
	 * Test that the unlock() logic works as expected (when setting unlocked parameters, no exception should be raised)
	 *
	 * @param string      $parameter_name - Parameter name to test
	 * @param mixed       $value - Parameter value to set
	 * @param mixed       $expected_value - Expected value being returned
	 * @param null|string $expected_exception - Expected exception being raised
	 *
	 * @covers \E20R\Licensing\Settings\Defaults::set
	 *
	 * @dataProvider fixture_parameter_value_unlocked
	 */
	public function test_update_for_valid_unlocked_parameter( $parameter_name, $value, $expected_value, $expected_exception ) {
		$defaults = new Defaults( true, $this->mock_utils );
		$defaults->unlock( $parameter_name );

		$this->assertDoesNotThrow(
			$expected_exception,
			function() use ( $defaults, $parameter_name, $value, $expected_exception, $expected_value ) {
				self::assertTrue( $defaults->set( $parameter_name, $value ) );
				self::assertSame( $expected_value, $defaults->get( $parameter_name ) );
			}
		);
	}

	/**
	 * Fixture for Defaults_Test::test_parameter_locking()
	 *
	 * @return array[]
	 */
	public function fixture_parameter_value_unlocked() {
		return array(
			array( 'version', '2.0', '2.0', BadOperation::class ),
			array( 'version', '2.0', '2.0', BadOperation::class ),
			array( 'server_url', 'https://example.com', 'https://example.com', BadOperation::class ),
			array( 'rest_url', '/rest-api/v1/do-something/', '/rest-api/v1/do-something/', BadOperation::class ),
			array( 'ajax_url', '/wp-admin/admin-ajax.php', '/wp-admin/admin-ajax.php', BadOperation::class ),
			array( 'use_rest', false, false, BadOperation::class ),
			array( 'debug_logging', true, true, BadOperation::class ),
			array( 'store_code', '12345678', '12345678', BadOperation::class ),
		);
	}

	/**
	 * Test(s) for the Defaults::get_default() values, including error testing
	 *
	 * @param string             $parameter - The parameter to obtain the default value of
	 * @param mixed              $expected_value - The returned default values
	 * @param InvalidSettingsKey $expected_exception - The exception to test against
	 *
	 * @dataProvider fixture_get_default_errors
	 * @covers \E20R\Licensing\Settings\Defaults::get_default()
	 */
	public function test_get_default_with_errors( $parameter, $expected_value, $expected_exception ) {
		$defaults = new Defaults( true );
		try {
			$result = $defaults->get_default( $parameter );
			self::assertSame( $expected_value, $result );
		} catch ( InvalidSettingsKey $e ) {
			self::assertInstanceOf( $expected_exception, $e );
		}
	}

	/**
	 * Fixture for the test_get_default_with_errors
	 *
	 * @return array[]
	 */
	public function fixture_get_default_errors() {
		return array(
			// key, value, exception
			array( 'version', '3.2', null ),
			array( 'dummy', null, InvalidSettingsKey::class ),
		);
	}

	/**
	 * Test the 'exists()' function used to validate that a given default parameter is defined
	 *
	 * @param string                  $parameter - The parameter name to test the existence of
	 * @param bool                    $expected - The expected return value from the exists() method
	 * @param InvalidSettingsKey|null $exception - The exception to test against
	 *
	 * @covers \E20R\Licensing\Settings\Defaults::exists()
	 * @dataProvider fixture_exists
	 */
	public function test_exists( $parameter, $expected, $exception ) {
		$defaults = new Defaults( true );
		try {
			$result = $defaults->exists( $parameter );
			self::assertSame( $expected, $result );
		} catch ( InvalidSettingsKey $ex ) {
			self::assertInstanceOf( $exception, $ex );
		}
	}

	/**
	 * Fixture for the Defaults::exists() tests
	 *
	 * @return array[]
	 */
	public function fixture_exists() {
		return array(
			// parameter name, expected value, expected exception
			array( 'dummy_param', false, InvalidSettingsKey::class ),
			array( 'sever_url', false, InvalidSettingsKey::class ),
			array( 'SERVER_URL', false, InvalidSettingsKey::class ),
			array( 'REST_URL', false, InvalidSettingsKey::class ),
			array( 'Ajax_URL', false, InvalidSettingsKey::class ),
			array( 'USE_REST', false, InvalidSettingsKey::class ),
			array( 'DEBUG_LOGGING', false, InvalidSettingsKey::class ),
			array( 'STORE_CODE', false, InvalidSettingsKey::class ),
			array( 'Connection_URI', false, InvalidSettingsKey::class ),
			array( 'server_url', true, null ),
			array( 'version', true, null ),
			array( 'rest_url', true, null ),
			array( 'ajax_url', true, null ),
			array( 'use_rest', true, null ),
			array( 'debug_logging', true, null ),
			array( 'store_code', true, null ),
			array( 'connection_uri', true, null ),
		);
	}

	/**
	 * Test the unlock() function
	 *
	 * @param string             $param_name - The parameter name to unlock
	 * @param bool               $expected The expected return value when unlocking the specified parameter name
	 * @param InvalidSettingsKey $exception - Parameter to test against
	 *
	 * @throws InvalidSettingsKey - The parameter didn't exist in the specified Defaults instance
	 * @dataProvider fixture_unlock_exceptions
	 * @covers \E20R\Licensing\Settings\Defaults::unlock()
	 */
	public function test_unlock_exceptions( $param_name, $expected, $exception ) {
		$defaults = new Defaults( true );
		try {
			$defaults->unlock( $param_name );
			$result = $defaults->get( "{$param_name}_locked" );
			self::assertSame( $expected, $result );
		} catch ( BadOperation $ex ) {
			self::assertInstanceOf( $exception, $ex );
		}
	}

	/**
	 * Fixture for testing Defaults::unlock()
	 *
	 * @return array[]
	 */
	public function fixture_unlock_exceptions() {
		return array(
			array( 'store_code', false, null ),
			array( 'STORE_CODE', false, BadOperation::class ),
		);
	}

	/**
	 * Test the unlock() function
	 *
	 * @param string             $param_name - The parameter name to unlock
	 * @param bool               $expected - The expected value(s) from the test
	 * @param InvalidSettingsKey $exception - The exception to pass in
	 *
	 * @throws InvalidSettingsKey - Raised when the specified parameter name doesn't exist
	 *
	 * @dataProvider fixture_lock_exceptions
	 * @covers \E20R\Licensing\Settings\Defaults::lock()
	 */
	public function test_lock_exceptions( $param_name, $expected, $exception ) {
		$defaults = new Defaults( true );
		try {
			$defaults->lock( $param_name );
			$result = $defaults->get( "{$param_name}_locked" );
			self::assertSame( $expected, $result );
		} catch ( BadOperation $ex ) {
			self::assertInstanceOf( $exception, $ex );
		}
	}

	/**
	 * Fixture for testing Defaults::unlock()
	 *
	 * @return array[]
	 */
	public function fixture_lock_exceptions() {
		return array(
			array( 'store_code', true, null ),
			array( 'STORE_CODE', false, BadOperation::class ),
		);
	}
}
