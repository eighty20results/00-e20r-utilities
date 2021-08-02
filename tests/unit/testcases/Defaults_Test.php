<?php
/*
 * *
 *   * Copyright (c) 2021. - Eighty / 20 Results by Wicked Strong Chicks.
 *   * ALL RIGHTS RESERVED
 *   *
 *   * This program is free software: you can redistribute it and/or modify
 *   * it under the terms of the GNU General Public License as published by
 *   * the Free Software Foundation, either version 3 of the License, or
 *   * (at your option) any later version.
 *   *
 *   * This program is distributed in the hope that it will be useful,
 *   * but WITHOUT ANY WARRANTY; without even the implied warranty of
 *   * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *   * GNU General Public License for more details.
 *   *
 *   * You should have received a copy of the GNU General Public License
 *   * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace E20R\Tests\Unit;

use Codeception\AssertThrows;
use Codeception\Test\Unit;
use Codeception\Stub\Expected;
use Brain\Monkey;
use Brain\Monkey\Functions;
use E20R\Licensing\Exceptions\ConfigDataNotFound;
use E20R\Licensing\Exceptions\InvalidSettingsKey;
use E20R\Licensing\Settings\Defaults;
use E20R\Utilities\Utilities;
use Exception;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Throwable;

class Defaults_Test extends Unit {

	use MockeryPHPUnitIntegration;
	use AssertThrows;

	/**
	 * @var Utilities|null $mock_utils
	 */
	private ?Utilities $mock_utils = null;

	/**
	 * The setup function for this Unit Test suite
	 *
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

		Functions\stubs(
			array(
				'plugins_url'         => 'https://localhost:7254/wp-content/plugins/00-e20r-utilities/',
				'plugin_dir_path'     => '/var/www/html/wp-content/plugins/00-e20r-utilities/',
				'get_current_blog_id' => 0,
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
			->andReturn( '/var/www/html/wp-content/plugins/00-e20r-utilities/src/E20R/Licensing/' );

		Functions\expect( 'get_filesystem_method' )
			->zeroOrMoreTimes()
			->andReturn( 'direct' );

		Functions\expect( 'plugin_dir_path' )
			->zeroOrMoreTimes()
			->with( Mockery::contains( 'src/E20R/Licensing/Defaults.php' ) )
			->andReturn(
				function() {
					return '../../../src/E20R/Licensing/';
				}
			);

		$this->mock_utils = $this->makeEmpty(
			Utilities::class,
			array(
				'log' => function( $text ) {
					// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
					error_log( $text );
					return null;
				},
			)
		);
	}

	/**
	 * Load source files for the Unit Test to execute
	 */
	public function loadFiles() {
		require_once __DIR__ . '/../inc/class-wp-filesystem-base.php';
		require_once __DIR__ . '/../inc/class-wp-filesystem-direct.php';
	}

	/**
	 * Testing instantiation of the Default() class
	 *
	 * @param bool        $use_rest
	 * @param bool        $var_debug
	 * @param string|null $version
	 * @param string|null $server_url ,
	 * @param null|bool   $const_for_debug_logging
	 * @param string|null $const_server_url
	 * @param bool        $use_phpunit_constant
	 * @param string|null $config
	 * @param array       $expected
	 *
	 * @throws ConfigDataNotFound
	 * @throws InvalidSettingsKey
	 * @throws Throwable
	 * @dataProvider fixture_instantiate_class
	 *
	 */
	public function test_instantiate_class( ?bool $use_rest, ?bool $var_debug, ?string $version, ?string $server_url, ?bool $const_for_debug_logging, ?string $const_server_url, ?bool $use_phpunit_constant, ?string $config, array $expected ) {

		// NOTE: Only trigger this as part of the second to thing (fixture) to execute
		if ( true === $const_for_debug_logging && ! defined( 'E20R_LICENSING_DEBUG' ) ) {
			define( 'E20R_LICENSING_DEBUG', $const_for_debug_logging );
		}

		// NOTE: Only trigger this as part of the last thing (fixture) to execute
		if ( ! empty( $const_server_url ) && ! defined( 'E20R_LICENSE_SERVER_URL' ) ) {
			define( 'E20R_LICENSE_SERVER_URL', $const_server_url );
		}

		// NOTE: Only trigger this as the third last thing (fixture) to execute
		if ( ! defined( 'PLUGIN_PHPUNIT' ) ) {
			define( 'PLUGIN_PHPUNIT', true );
		}

		global $wp_filesystem;

		// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		$wp_filesystem = $this->makeEmpty(
			\WP_Filesystem_Direct::class,
			array(
				'get_contents' => Expected::atLeastOnce( $config ),
			),
		);

		try {
			Functions\expect( 'file_exists' )
				->with( Mockery::contains( '/var/www/html/wp-content/plugins/00-e20r-utilities/src/Licensing/.info.json' ) )
				->zeroOrMoreTimes()
				->andReturn(
					function() {
						return true;
					}
				);
		} catch ( Exception $e ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( 'file_exists() mock error: ' . esc_attr( $e->getMessage() ) );
		}

		if ( ! $config ) {
			$this->assertThrowsWithMessage(
				Exception::class,
				'Unable to decode the configuration file',
				function() use ( $use_rest ) {
					$settings = new Defaults( $use_rest, $this->mock_utils );
				}
			);
			return;
		} else {
			$settings = new Defaults( $use_rest, $this->mock_utils );
		}

		$this->assertDoesNotThrow(
			Exception::class,
			function() use ( $settings, $var_debug ) {
				$settings->set( 'debug_logging', $var_debug );
			}
		);

		$this->assertDoesNotThrow(
			Exception::class,
			function() use ( $settings, $version ) {
				$settings->set( 'version', $version );
			}
		);

		$this->assertDoesNotThrow(
			Exception::class,
			function() use ( $settings, $server_url ) {
				$settings->set( 'server_url', $server_url );
			}
		);

		self::assertSame( $expected['use_rest'], $settings->get( 'use_rest' ), 'Could not select expected REST API or AJAX mode: ' . $use_rest );
		self::assertSame( $expected['debug_logging'], $settings->get( 'debug_logging' ), "Could not set debug_logging to {$var_debug}|{$const_for_debug_logging}" );
		self::assertSame( $expected['version'], $settings->get( 'version' ), "Could not set version to {$version} (expected: {$expected['version']})" );
		self::assertSame( $expected['server_url'], $settings->get( 'server_url' ), "Could not set server URL to {$server_url}" );
		self::assertSame( $expected['connection_uri'], $settings->get( 'connection_uri' ), "Could not set connection_uri to {$server_url}" . ( $use_rest ? $settings->get( 'rest_url' ) : $settings->get( 'ajax_url' ) ) );
		self::assertSame( $expected['store_code'], $settings->get( 'store_code' ), 'Didn\'t return the expected WooCommerce Store Code!' );

		// Test the inverted rest/ajax flag
		$settings->set( 'use_rest', ( ! $use_rest ) );
		self::assertSame( ( ! $expected['use_rest'] ), $settings->get( 'use_rest' ) );

		// And that should result in the connection URI changing
		$new_connection_uri = sprintf( '%1$s%2$s', $settings->get( 'server_url' ), ( $settings->get( 'use_rest' ) ? $settings->get( 'rest_url' ) : $settings->get( 'ajax_url' ) ) );
		self::assertSame( $new_connection_uri, $settings->get( 'connection_uri' ), "Could not set connection_uri to {$new_connection_uri}" );
	}

	/**
	 * Fixture for the LicenseSettings constructor test
	 *
	 * @return array[]
	 * @throws Exception
	 */
	public function fixture_instantiate_class(): array {
		return array(
			// use_rest, var_debug, version, server_url, const_for_debug_logging, const_server_url, use_phpunit_constant, config file contents, result array
			array( // # 0
				false,
				false,
				'2.0',
				$this->fixture_get_config( 1, 'server_url' ),
				null,
				null,
				false,
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
				null,
				null,
				false,
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
				null,
				null,
				false,
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
				null,
				null,
				false,
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
				true,
				'3.1',
				$this->fixture_get_config( 2, 'server_url' ),
				null,
				null,
				false,
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
				true,
				'3.1',
				'',
				null,
				null,
				false,
				$this->fixture_load_config_json( 1 ),
				array(
					'use_rest'       => true,
					'debug_logging'  => true,
					'version'        => '3.1',
					'server_url'     => $this->fixture_get_config( 1, 'server_url' ),
					'connection_uri' => $this->fixture_get_config( 1, 'server_url' ) . '/wp-json/woo-license-server/v1',
					'store_code'     => $this->fixture_get_config( 1, 'store_code' ),
				),
			),
			array( // # 6
				true,
				true,
				'3.1',
				null,
				null,
				null,
				false,
				$this->fixture_load_config_json( 1 ),
				array(
					'use_rest'       => true,
					'debug_logging'  => true,
					'version'        => '3.1',
					'server_url'     => $this->fixture_get_config( 1, 'server_url' ),
					'connection_uri' => $this->fixture_get_config( 1, 'server_url' ) . '/wp-json/woo-license-server/v1',
					'store_code'     => $this->fixture_get_config( 1, 'store_code' ),
				),
			),
			array( // # 7
				true,
				false, // Should get overridden by the E20R_LICENSING_DEBUG constant
				'3.1',
				$this->fixture_get_config( 2, 'server_url' ),
				true,
				null,
				false,
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
			array( // # 8
				false,
				false, // Should get overridden by the E20R_LICENSING_DEBUG constant
				'3.1',
				$this->fixture_get_config( 2, 'server_url' ),
				true,
				$this->fixture_get_config( 3, 'server_url' ), // Should get overridden by the E20R_LICENSE_SERVER_URL constant
				false,
				$this->fixture_load_config_json( 3 ),
				array(
					'use_rest'       => false,
					'debug_logging'  => true,
					'version'        => '3.1',
					'server_url'     => $this->fixture_get_config( 3, 'server_url' ),
					'connection_uri' => $this->fixture_get_config( 3, 'server_url' ) . '/wp-admin/wp-ajax.php',
					'store_code'     => $this->fixture_get_config( 3, 'store_code' ),
				),
			),
			array( // # 9
				false,
				false, // Should get overridden by the E20R_LICENSING_DEBUG constant
				null,
				$this->fixture_get_config( 2, 'server_url' ), // Should get overridden by the E20R_LICENSE_SERVER_URL constant
				true,
				$this->fixture_get_config( 3, 'server_url' ),
				false,
				$this->fixture_load_config_json( 3 ),
				array(
					'use_rest'       => false,
					'debug_logging'  => true,
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
				$this->fixture_get_config( 2, 'server_url' ), // Should get overridden by the E20R_LICENSE_SERVER_URL constant
				true,
				$this->fixture_get_config( 3, 'server_url' ),
				true, // Shouldn trigger
				$this->fixture_load_config_json( 3 ),
				array(
					'use_rest'       => false,
					'debug_logging'  => true,
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
				$this->fixture_get_config( 2, 'server_url' ), // Should get overridden by the E20R_LICENSE_SERVER_URL constant
				true,
				$this->fixture_get_config( 3, 'server_url' ),
				true, // Shouldn trigger
				null, // config file is missing and should raise an exception
				array(
					'use_rest'       => false,
					'debug_logging'  => true,
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
	 * @param int    $key
	 * @param string $field
	 *
	 * @return string
	 * @throws Exception
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
	 * @param int $key
	 *
	 * @return string
	 * @throws Exception
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
	 * @param null|string $json
	 * @param array  $expected
	 *
	 * @dataProvider fixture_read_config_success
	 * @throws InvalidSettingsKey|ConfigDataNotFound|Exception
	 */
	public function test_read_config_success( ?string $json, array $expected ) {

		$read_config_result = false;
		try {
			$plugin_defaults    = new Defaults( false, $this->mock_utils );
			$read_config_result = $plugin_defaults->read_config( $json );
		} catch ( ConfigDataNotFound | InvalidSettingsKey | Exception $e ) {
			self::assertFalse( true, $e->getMessage() );
		}

		self::assertSame( $expected['server_url'], $plugin_defaults->get( 'server_url' ) );
		self::assertSame( $expected['store_code'], $plugin_defaults->get( 'store_code' ) );
		self::assertSame( $expected['result'], $read_config_result );
	}

	/**
	 * Test fixture for the Defaults_Test::test_read_config_success()
	 *
	 * @return array
	 * @throws Exception
	 */
	public function fixture_read_config_success(): array {
		$fixture = array();

		foreach ( range( 1, 11 ) as $key ) {
			$json_key  = rand( 1, 3 ); // phpcs:ignore WordPress.WP.AlternativeFunctions.rand_rand
			$expected  = array(
				'result'     => true,
				'server_url' => $this->fixture_get_config( $json_key, 'server_url' ),
				'store_code' => $this->fixture_get_config( $json_key, 'store_code' ),
			);
			$fixture[] = array( $this->fixture_load_config_json( $json_key ), $expected );
		}

		// Testing with the build-in Defaults::E20R_STORE_CONFIG constant
		$fixture[] = array(
			null,
			array(
				'store_code' => 'L4EGy6Y91a15ozt',
				'server_url' => 'https://eighty20results.com',
				'result'     => true,
			),
		);

		return $fixture;
	}
}
