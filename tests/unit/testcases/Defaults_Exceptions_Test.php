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

namespace E20R\Test\Unit;

use Codeception\AssertThrows;
use Codeception\Test\Unit;
use Brain\Monkey;
use Brain\Monkey\Functions;
use E20R\Utilities\Licensing\Exceptions\InvalidSettingKeyException;
use E20R\Utilities\Licensing\Settings\Defaults;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;

class Defaults_Exceptions_Test extends Unit {

	use MockeryPHPUnitIntegration;
	use AssertThrows;

	/**
	 * The setup function for this Unit Test suite
	 *
	 */
	protected function setUp(): void {
		if ( ! defined( 'ABSPATH' ) ) {
			define( 'ABSPATH', __DIR__ );
		}
		parent::setUp();
		Monkey\setUp();

		$this->loadFiles();
		$this->loadMockedFunctions();
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

	public function loadMockedFunctions() {
		if ( ! defined( 'DAY_IN_SECONDS' ) ) {
			define( 'DAY_IN_SECONDS', 60 * 60 * 24 );
		}
		Functions\stubs(
			array(
				'plugins_url'         => 'https://localhost:7254/wp-content/plugins/00-e20r-utilities/',
				'plugin_dir_path'     => '/var/www/html/wp-content/plugins/00-e20r-utilities/',
				'get_current_blog_id' => 0,
				'esc_html__'          => null,
				'esc_attr__'          => null,
				'__'                  => null,
				'_e'                  => null,
			)
		);
	}

	/**
	 * Load source files for the Unit Test to execute
	 */
	public function loadFiles() {
		require_once __DIR__ . '/../../../inc/autoload.php';
		require_once __DIR__ . '/../../../src/licensing/exceptions/class-invalidsettingkeyexception.php';
		require_once __DIR__ . '/../../../src/licensing/class-defaults.php';
	}

	/**
	 * Testing instantiation of the Default() class
	 *
	 * @param bool   $use_rest
	 * @param bool   $var_debug
	 * @param string $version
	 * @param string $server_url ,
	 * @param bool   $const_for_debug_logging
	 * @param string $const_server_url
	 * @param bool   $use_phpunit_constant
	 * @param array  $expected
	 *
	 * @dataProvider fixture_instantiate_class
	 * @throws \E20R\Utilities\Licensing\Exceptions\InvalidSettingKeyException|\Throwable
	 */
	public function test_instantiate_class( $use_rest, $var_debug, $version, $server_url, $const_for_debug_logging, $const_server_url, $use_phpunit_constant, $expected ) {

		$settings = new Defaults( $use_rest );

		if ( defined( 'PLUGIN_PHPUNIT' ) ) {
			self::assertFalse(
				PLUGIN_PHPUNIT,
				'The PLUGIN_PHPUNIT constant was defined so we won\'t throw an exception when we try to set variables (except for the server_url parameter)'
			);
		}

		// NOTE: Only trigger this as part of the second to thing (fixture) to execute
		if ( true === $const_for_debug_logging && ! defined( 'E20R_LICENSING_DEBUG' ) ) {
			error_log( "Using value in E20R_LICENSING_DEBUG constant\n" );
			define( 'E20R_LICENSING_DEBUG', $const_for_debug_logging );
		}

		// NOTE: Only trigger this as part of the last thing (fixture) to execute
		if ( ! empty( $const_server_url ) && ! defined( 'E20R_LICENSE_SERVER_URL' ) ) {
			error_log( "Setting E20R_LICENSE_SERVER_URL constant to {$const_server_url}" );
			define( 'E20R_LICENSE_SERVER_URL', $const_server_url );
		}

		// When testing, these two variables should not be allowed to be changed
		$this->assertThrows(
			\Exception::class,
			function() use ( $settings, $version ) {
				$settings->set( 'version', $version );
			}
		);
		// When testing, these two variables should not be allowed to be changed
		$this->assertThrows(
			\Exception::class,
			function() use ( $settings, $var_debug ) {
				$settings->set( 'debug_logging', $var_debug );
			}
		);

		// We do allow changing the server_url in the cases where we're not testing
		$this->assertDoesNotThrow(
			\Exception::class,
			function() use ( $settings, $server_url ) {
				try {
					$settings->set( 'server_url', $server_url );
				} catch ( \Exception $e ) {
					error_log( "Error setting server_url: " . $e->getMessage() );
					throw $e;
				}
			}
		);

		error_log( "server_url is now {$settings->get( 'server_url' )}");

		self::assertSame( $expected['use_rest'], $settings->get( 'use_rest' ), 'Could not select expected REST API or AJAX mode: ' . $use_rest );
		self::assertSame( $expected['debug_logging'], $settings->get( 'debug_logging' ), "Error: Changed debug_logging to {$var_debug}|{$const_for_debug_logging}" );
		self::assertSame( $expected['version'], $settings->get( 'version' ), "Error: Changed version to {$version} (expected: {$expected['version']})" );
		self::assertSame( $expected['server_url'], $settings->get( 'server_url' ), "Error: Should have been allowed to set server URL to {$server_url}" );
		self::assertSame( $expected['connection_uri'], $settings->get( 'connection_uri' ), "Error: Wrong connection_uri ({$server_url}" . ( $use_rest ? $settings->get( 'rest_url' ) : $settings->get( 'ajax_url' ) ) . ')' );

		// Test the inverted rest/ajax flag
		$this->assertThrows(
			\Exception::class,
			function() use ( $settings, $use_rest ) {
				$settings->set( 'use_rest', ( ! $use_rest ) );
			}
		);

		self::assertSame( $expected['use_rest'], $settings->get( 'use_rest' ) );

		$new_connection_uri = sprintf( '%1$s%2$s', $settings->get( 'server_url' ), ( $settings->get( 'use_rest' ) ? $settings->get( 'rest_url' ) : $settings->get( 'ajax_url' ) ) );
		self::assertSame( $new_connection_uri, $settings->get( 'connection_uri' ), "Could not set connection_uri to {$new_connection_uri}" );
	}

	/**
	 * Fixture for the LicenseSettings constructor test
	 *
	 * @return array[]
	 */
	public function fixture_instantiate_class() {
		return array(
			// use_rest, var_debug, version, server_url, const_for_debug_logging, const_server_url, use_phpunit_constant, result array
			array( // # 0
				false,
				false,
				'2.0',
				'https://eighty20results.com',
				null,
				null,
				false,
				array(
					'use_rest'       => false,
					'debug_logging'  => false,
					'version'        => '3.2',
					'server_url'     => 'https://eighty20results.com',
					'connection_uri' => 'https://eighty20results.com/wp-admin/wp-ajax.php',
				),
			),
			array( // # 1
				false,
				false,
				'3.0',
				'https://eighty20results.com',
				null,
				null,
				false,
				array(
					'use_rest'       => false,
					'debug_logging'  => false,
					'version'        => '3.2',
					'server_url'     => 'https://eighty20results.com',
					'connection_uri' => 'https://eighty20results.com/wp-admin/wp-ajax.php',
				),
			),
			array( // # 2
				false,
				false,
				'3.1',
				'https://eighty20results.com',
				null,
				null,
				false,
				array(
					'use_rest'       => false,
					'debug_logging'  => false,
					'version'        => '3.2',
					'server_url'     => 'https://eighty20results.com',
					'connection_uri' => 'https://eighty20results.com/wp-admin/wp-ajax.php',
				),
			),
			array( // # 3
				true,
				false,
				'2.0',
				'https://eighty20results.com',
				null,
				null,
				false,
				array(
					'use_rest'       => true,
					'debug_logging'  => false,
					'version'        => '3.2',
					'server_url'     => 'https://eighty20results.com',
					'connection_uri' => 'https://eighty20results.com/wp-json/woo-license-server/v1',
				),
			),
			array( // # 4
				true,
				true,
				'3.1',
				'https://example.com',
				null,
				null,
				false,
				array(
					'use_rest'       => true,
					'debug_logging'  => false,
					'version'        => '3.2',
					'server_url'     => 'https://example.com',
					'connection_uri' => 'https://example.com/wp-json/woo-license-server/v1',
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
				array(
					'use_rest'       => true,
					'debug_logging'  => false,
					'version'        => '3.2',
					'server_url'     => 'https://eighty20results.com',
					'connection_uri' => 'https://eighty20results.com/wp-json/woo-license-server/v1',
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
				array(
					'use_rest'       => true,
					'debug_logging'  => false,
					'version'        => '3.2',
					'server_url'     => 'https://eighty20results.com',
					'connection_uri' => 'https://eighty20results.com/wp-json/woo-license-server/v1',
				),
			),
			array( // # 7
				true,
				false, // Should get overridden by the E20R_LICENSING_DEBUG constant
				'3.1',
				'https://example.com',
				true,
				null,
				false,
				array(
					'use_rest'       => true,
					'debug_logging'  => false,
					'version'        => '3.2',
					'server_url'     => 'https://example.com',
					'connection_uri' => 'https://example.com/wp-json/woo-license-server/v1',
				),
			),
			array( // # 8
				false,
				false, // Should get overridden by the E20R_LICENSING_DEBUG constant
				'3.1',
				'https://example.com',
				true,
				'https://another.example.com', // Should get overridden by the E20R_LICENSE_SERVER_URL constant
				false,
				array(
					'use_rest'       => false,
					'debug_logging'  => true,
					'version'        => '3.2',
					'server_url'     => 'https://another.example.com',
					'connection_uri' => 'https://another.example.com/wp-admin/wp-ajax.php',
				),
			),
			array( // # 9
				false,
				false, // Should get overridden by the E20R_LICENSING_DEBUG constant
				null,
				'https://example.com', // Should get overridden by the E20R_LICENSE_SERVER_URL constant
				true,
				'https://another.example.com',
				false,
				array(
					'use_rest'       => false,
					'debug_logging'  => true,
					'version'        => '3.2',
					'server_url'     => 'https://another.example.com',
					'connection_uri' => 'https://another.example.com/wp-admin/wp-ajax.php',
				),
			),
			array( // # 10
				false,
				false, // Should get overridden by the E20R_LICENSING_DEBUG constant
				null,
				'https://example.com', // Should get overridden by the E20R_LICENSE_SERVER_URL constant
				true,
				'https://another.example.com',
				true, // Shouldn't trigger
				array(
					'use_rest'       => false,
					'debug_logging'  => true,
					'version'        => '3.2',
					'server_url'     => 'https://another.example.com',
					'connection_uri' => 'https://another.example.com/wp-admin/wp-ajax.php',
				),
			),
		);
	}
}
