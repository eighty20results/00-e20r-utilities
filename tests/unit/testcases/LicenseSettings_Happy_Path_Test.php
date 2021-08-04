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
use Brain\Monkey;
use Brain\Monkey\Functions;
use E20R\Utilities\Cache;
use E20R\Licensing\Exceptions\MissingServerURL;
use E20R\Licensing\Settings\LicenseSettings;
use E20R\Utilities\Message;
use E20R\Utilities\Utilities;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;

class LicenseSettings_Happy_Path_Test extends Unit {

	use MockeryPHPUnitIntegration;
	use AssertThrows;

	private $m_utils;

	/**
	 * The setup function for this Unit Test suite
	 *
	 */
	protected function setUp(): void {
		// So we can update the default settings for the License component of the E20R Utilities module
		// For testing purposes
		if ( ! defined( 'PLUGIN_PHPUNIT' ) ) {
			define( 'PLUGIN_PHPUNIT', true );
		}

		if ( ! defined( 'ABSPATH' ) ) {
			define( 'ABSPATH', __DIR__ );
		}

		if ( ! defined( 'WP_DEBUG' ) ) {
			define( 'WP_DEBUG', true );
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

	/**
	 * Define mocked functions we need
	 */
	public function loadMockedFunctions() {
		if ( ! defined( 'DAY_IN_SECONDS' ) ) {
			define( 'DAY_IN_SECONDS', 60 * 60 * 24 );
		}

		try {
			$this->m_utils = $this->makeEmpty(
				Utilities::class,
				array(
					'add_message'        => null,
					'log'                => null,
					'get_util_cache_key' => 'e20r_pw_utils_0',
				)
			);
		} catch ( \ Exception $exception ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( 'Utilities() mocker: ' . $exception->getMessage() );
		}

		try {
			Functions\expect( 'get_option' )
				->with( 'timezone_string' )
				->zeroOrMoreTimes()
				->andReturn( 'Europe/Oslo' );
		} catch ( \Exception $e ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( 'get_options() mock error: ' . esc_attr( $e->getMessage() ) );
		}

		try {
			Functions\expect( 'wp_upload_dir' )
				->zeroOrMoreTimes()
				->andReturn(
					array(
						'path'    => '/var/www/html/wp-content/uploads/2021/08/',
						'url'     => 'https://localhost:7254/wp-content/uploads',
						'subdir'  => '2021/08',
						'basedir' => '/var/www/html/wp-content/uploads',
						'error'   => false,
					)
				);
		} catch ( \Exception $e ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( 'wp_upload_dir() mock error: ' . esc_attr( $e->getMessage() ) );
		}

		try {
			Functions\expect( 'date_i18n' )
				->with( \Mockery::contains( 'Y_M_D' ) )
				->zeroOrMoreTimes()
				->andReturn( '2021_07_28' );
		} catch ( \Exception $e ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( 'date_i18n() mock error: ' . esc_attr( $e->getMessage() ) );
		}

		try {
			Functions\expect( 'file_exists' )
				->with( \Mockery::contains( 'e20r_debug/debug_2021_07_28.log' ) )
				->zeroOrMoreTimes()
				->andReturn( true );
		} catch ( \Exception $e ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( 'file_exists() mock error: ' . esc_attr( $e->getMessage() ) );
		}

		Functions\stubs(
			array(
				'plugins_url'         => 'https://localhost:7254/wp-content/plugins/00-e20r-utilities/',
				'plugin_dir_path'     => __DIR__ . '/../../../',
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
	}

	/**
	 * Test the instantiation of the LicenseSettings() class (happy path)
	 *
	 * @param string $sku
	 * @param string $domain
	 * @param bool $with_debug
	 * @param string $version
	 * @param array $expected
	 *
	 * @dataProvider fixture_instantiate_class
	 */
	public function test_instantiate_class( $sku, $domain, $with_debug, $version, $expected ) {

		$message_mock = $this->getMockBuilder( Message::class )
							->onlyMethods( array( 'convert_destination' ) )
							->getMock();
		$message_mock->method( 'convert_destination' )
						->willReturn( 2000 );

		$cache_mock = $this->getMockBuilder( Cache::class )
			->onlyMethods( array( 'get' ) )
			->getMock();

		$cache_mock->method( 'get' )
			->willReturn( '' );

		Functions\expect( 'dirname' )
			->zeroOrMoreTimes()
			->with( \Mockery::contains( '/.info.json' ) )
			->andReturn( __DIR__ . '/../../../src/E20R/Licensing/.info.json' );

		Functions\when( 'get_transient' )
			->justReturn( '' );

		Functions\when( 'set_transient' )
			->justReturn( true );

		$config = $this->fixture_config_file( $sku );

		try {
			$mocked_plugin_defaults = $this->makeEmpty(
				'\E20R\Utilities\Licensing\Settings\Defaults',
				array(
					'read_config' => true,
					'set'         => true,
					'get'         => function ( $param_name ) use ( $with_debug, $version, $config ) {

						$retval = null;
						switch ( $param_name ) {
							case 'debug_logging':
								$retval = $with_debug;
								break;
							case 'version':
								$retval = $version;
								break;
							case 'store_code':
								$retval = $config['store_code'];
								break;
							case 'server_url':
								$retval = $config['server_url'];
								break;
						}
						return $retval;
					},
				),
			);
		} catch ( \Exception $e ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( $e->getMessage() );
			return false;
		}

		$_SERVER['HTTP_HOST'] = $_SERVER['HTTP_HOST'] ?? $domain;

		if ( empty( $config['server_url'] ) ) {
			$this->assertThrowsWithMessage(
				MissingServerURL::class,
				"Error: Haven't configured the Eighty/20 Results server URL, or the URL is malformed. Can be configured in the wp-config.php file.",
				function() use ( $sku, $mocked_plugin_defaults ) {
					$settings = new LicenseSettings( $sku, $mocked_plugin_defaults, $this->m_utils );
				}
			);
			return;
		} else {
			try {
				$settings = new LicenseSettings( $sku, $mocked_plugin_defaults, $this->m_utils );
			} catch ( \Exception $e ) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				error_log( 'Error: Unable to instantiate the LicenseSettings class: ' . $e->getMessage() );
				throw $e;
			}
		}

		// For testing purposes, we override the default plugin settings
		try {
			$settings->set( 'plugin_defaults', $mocked_plugin_defaults );
		} catch ( \Exception $e ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( 'Error: Unable to set new plugin_defaults: ' . $e->getMessage() );
		}

		self::assertSame( $expected['to_debug'], $settings->get( 'to_debug' ), "Error: The to_debug variable should have been set to {$expected['to_debug']}, it is {$settings->get( 'to_debug' )}" );
		self::assertSame( $expected['ssl_verify'], $settings->get( 'ssl_verify' ), "Error: The ssl_verify variable should have been set to {$expected['ssl_verify']}, it is {$settings->get( 'ssl_verify' )}" );
		self::assertSame( $expected['product_sku'], $settings->get( 'product_sku' ), "Error: The product_sku variable should have been set to {$expected['product_sku']}" );
		self::assertSame( $expected['new_version'], $settings->get( 'new_version' ), "Error: The new_version variable should have been set to {$expected['new_version']}" );
		self::assertSame( $expected['license_version'], $settings->get( 'plugin_defaults' )->get( 'version' ), "Error: The license_version variable should have been set to {$expected['license_version']}" );
		self::assertSame( $expected['store_code'], $settings->get( 'plugin_defaults' )->get( 'store_code' ), "Error: The store code variable should have been {$expected['store_code']}!" );
	}

	/**
	 * The mocked contents for the fake `.info.json` file
	 *
	 * @param string $sku
	 *
	 * @return string[]
	 */
	public function fixture_config_file( ?string $sku ): array {
		if ( empty( $sku ) ) {
			$sku = 'e20r_default_license';
		}
		$config_content = array(
			'E20R_TEST_LICENSE'    => array(
				'store_code' => 'dummy_store_code_1',
				'server_url' => 'https://eighty20results.com',
			),
			'e20r_default_license' => array(
				'store_code' => 'dummy_store_code_2',
				'server_url' => 'https://eighty20results.com/',
			),
			'e20r_no_server_url'   => array(
				'store_code' => 'dummy_store_code_4',
				'server_url' => null,
			),
		);

		return $config_content[ $sku ];
	}

	/**
	 * Fixture for the LicenseSettings constructor test
	 *
	 * @return array[]
	 */
	public function fixture_instantiate_class(): array {
		return array(
			// SKU, domain, with_debug_logging, Licensing version, result array
			array(
				'E20R_TEST_LICENSE',
				'example.net',
				false, // to_debug
				'3.1',
				array(
					'product_sku'     => 'E20R_TEST_LICENSE',
					'store_code'      => $this->fixture_config_file( 'E20R_TEST_LICENSE' )['store_code'],
					'ssl_verify'      => true,
					'to_debug'        => false,
					'license_version' => '3.1',
					'new_version'     => true,
				),
			),
			array(
				null,
				'eighty20results.com',
				true, // to_debug
				'2.0',
				array(
					'product_sku'     => 'e20r_default_license',
					'store_code'      => $this->fixture_config_file( 'e20r_default_license' )['store_code'],
					'ssl_verify'      => false,
					'to_debug'        => true,
					'license_version' => '2.0',
					'new_version'     => false,
				),
			),
			array(
				'e20r_no_server_url',
				'example.net',
				false, // to_debug
				'3.1',
				array(
					'product_sku'     => 'E20R_TEST_LICENSE',
					'store_code'      => $this->fixture_config_file( 'e20r_no_server_url' )['store_code'],
					'ssl_verify'      => true,
					'to_debug'        => false,
					'license_version' => '3.1',
					'new_version'     => true,
				),
			),
		);
	}
}
