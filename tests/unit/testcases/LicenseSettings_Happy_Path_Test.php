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
use E20R\Utilities\Cache;
use E20R\Utilities\Licensing\LicenseSettings;
use E20R\Utilities\Licensing\Settings\Defaults;
use E20R\Utilities\Message;
use E20R\Utilities\Utilities;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;

class LicenseSettings_Happy_Path_Test extends Unit {

	use MockeryPHPUnitIntegration;
	use AssertThrows;

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
			Functions\expect( 'get_option' )
				->with( \Mockery::contains( 'timezone_string' ) )
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
		require_once __DIR__ . '/../../../src/licensing/class-licensesettings.php';
		require_once __DIR__ . '/../../../src/utilities/class-utilities.php';
		require_once __DIR__ . '/../../../src/utilities/class-cache.php';
		require_once __DIR__ . '/../../../src/utilities/class-cache-object.php';
		require_once __DIR__ . '/../../../src/utilities/class-message.php';
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

		$util_mock = $this->getMockBuilder( Utilities::class )
						->onlyMethods( array( 'add_message', 'log', 'get_util_cache_key', 'get_instance' ) )
						->getMock();
		$util_mock->method( 'log' )
					->willReturn( null );
		$util_mock->method( 'add_message' )
					->willReturn( null );
		$util_mock->method( 'get_util_cache_key' )
					->willReturn( 'e20r_pw_utils_0' );

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

		Functions\when( 'get_transient' )
			->justReturn( '' );

		Functions\when( 'set_transient' )
			->justReturn( true );

		$plugin_defaults = new Defaults();
		try {
			$plugin_defaults->set( 'version', $version );
		} catch ( \Exception $e ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( "Error: Unable to update version to {$version}" . $e->getMessage() );
		}

		try {
			$plugin_defaults->set( 'debug_logging', $with_debug );
		} catch ( \Exception $e ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( "Error: Unable to update debug_logging to {$with_debug}" . $e->getMessage() );
		}

		$_SERVER['HTTP_HOST'] = $_SERVER['HTTP_HOST'] ?? $domain;

		try {
			$settings = new LicenseSettings( $sku );
		} catch ( \Exception $e ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( 'Error: Unable to instantiate the LicenseSettings class: ' . $e->getMessage() );
			throw $e;
		}

		// For testing purposes, we override the default plugin settings
		try {
			$settings->set( 'plugin_defaults', $plugin_defaults );
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
	 * Fixture for the LicenseSettings constructor test
	 *
	 * @return array[]
	 */
	public function fixture_instantiate_class(): array {
		return array(
			// SKU, domain, with_debug_logging, licensing version, result array
			array(
				'E20R_TEST_LICENSE',
				'example.net',
				false, // to_debug
				'3.1',
				array(
					'product_sku'     => 'E20R_TEST_LICENSE',
					'store_code'      => 'L4EGy6Y91a15ozt',
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
					'store_code'      => 'L4EGy6Y91a15ozt',
					'ssl_verify'      => false,
					'to_debug'        => true,
					'license_version' => '2.0',
					'new_version'     => false,
				),
			),
		);
	}
}
