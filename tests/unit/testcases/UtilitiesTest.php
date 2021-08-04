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
use E20R\Utilities\Message;
use E20R\Utilities\Utilities;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Mockery;
use Brain\Monkey;
use Brain\Monkey\Filters;
use Brain\Monkey\Actions;
use Brain\Monkey\Functions;

if ( ! defined( 'PLUGIN_PHPUNIT' ) ) {
	define( 'PLUGIN_PHPUNIT', true );
}

class UtilitiesTest extends Unit {

	use MockeryPHPUnitIntegration;
	use AssertThrows;

	private $m_message;

	/**
	 * The setup function for this Unit Test suite
	 *
	 */
	protected function setUp(): void {

		parent::setUp();
		Monkey\setUp();
		$this->loadFiles();
		$this->loadStubs();
		$this->loadDefaultMocks();
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
	 * Load source files for the Unit Test to execute
	 */
	public function loadFiles() {
		require_once __DIR__ . '/../../../inc/autoload.php';
	}

	public function loadStubs() {

		Functions\stubs(
			array(
				'plugins_url'         => 'https://localhost/wp-content/plugins/00-e20r-utilities',
				'plugin_dir_path'     => __DIR__ . '/../../../',
				'get_current_blog_id' => 1,
				'date_i18n'           => function( $format, $time ) {
					return date( $format, $time ); // phpcs:ignore WordPress.DateTime.RestrictedFunctions.date_date
				},
			)
		);

	}
	/**
	 * Define Mocked classes
	 *
	 * @throws \Exception
	 */
	public function loadDefaultMocks() {
		$this->m_message = $this->makeEmpty(
			Message::class,
			array(
				'display'            => false,
				'clear_notices'      => null,
				'filter_passthrough' => null,
			)
		);
	}
	/**
	 * Test the instantiation of the Utilities class
	 *
	 * @param bool $is_admin
	 * @param bool $use_debug_logging
	 * @param array $wp_upload_dir
	 * @param bool $file_exists
	 * @param bool $mkdir
	 * @param bool $has_action
	 *
	 * @dataProvider fixtures_constructor
	 */
	public function test_class_is_instantiated( $is_admin, $use_debug_logging, $wp_upload_dir, $file_exists, $mkdir, $has_action ) {

		Functions\expect( 'plugins_url' )
			->andReturn( 'https://localhost:7254/wp-content/plugins/00-e20r-utilities' );

		Functions\expect( 'plugin_dir_path' )
			->andReturn( __DIR__ . '/../../../' );

		Functions\expect( 'get_current_blog_id' )
			->andReturn( 1 );

		Functions\when( 'has_action' )
			->justReturn( $has_action );

		Functions\expect( 'get_option' )
			->with( 'timezone_string' )
			->andReturn( 'Europe/Oslo' );

		Functions\expect( 'file_exists' )
			->zeroOrMoreTimes()
			->andReturn( $file_exists );

		Functions\expect( 'mkdir' )
			->zeroOrMoreTimes()
			->andReturn( $mkdir );

		try {
			Functions\expect( 'wp_upload_dir' )
				->zeroOrMoreTimes()
				->andReturn( $wp_upload_dir );
		} catch ( \Exception $e ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( 'wp_upload_dir() mock error: ' . esc_attr( $e->getMessage() ) );
		}

		if ( ! defined( 'WP_DEBUG' ) && $use_debug_logging ) {
			define( 'WP_DEBUG', true );
		}

		$utils = new Utilities( $this->m_message );

		if ( $is_admin ) {
			Filters\has( 'pmpro_save_discount_code', array( $utils, 'clear_delay_cache' ) );
			Actions\has( 'pmpro_save_membership_level', array( $utils, 'clear_delay_cache' ) );
			Filters\has( 'http_request_args', array( $utils, 'set_ssl_validation_for_updates' ) );

			if ( ! has_action( 'admin_notices', array( $this->m_message, 'display' ) ) ) {
				Actions\has( 'admin_notices', array( $this->m_message, 'display' ) );
			}
		} else {
			// Filters should be set/defined if we think we're in the wp-admin backend
			Filters\has( 'woocommerce_update_cart_action_cart_updated', array( $this->m_message, 'clear_notices' ) );
			Filters\has( 'pmpro_email_field_type', array( $this->m_message, 'filter_passthrough' ) );
			Filters\has( 'pmpro_get_membership_levels_for_user', array( $this->m_message, 'filter_passthrough' ) );
			Actions\has( 'woocommerce_init', array( $this->m_message, 'display' ) );
		}

	}

	/**
	 * Fixture for testing the Utilities constructor (filter/action checks)
	 * @return array
	 */
	public function fixtures_constructor() {
		// is_admin, use_debug_logging, wp_upload_dir, file_exists, mkdir, has_action
		return array(
			array( true, false, $this->get_upload_dir(), true, true, true ),
			array( true, false, $this->get_upload_dir(), true, true, false ),
			array( false, true, $this->get_upload_dir(), true, true, false ),
			array( false, true, null, false, true, true ),
		);
	}

	/**
	 * Returns the WP_UPLOAD_DIR structure
	 *
	 * @return array
	 */
	private function get_upload_dir() {
		return array(
			'path'    => __DIR__ . '/../../_output/2021/08/',
			'url'     => 'https://localhost:7254/wp-content/uploads/2021/08/',
			'subdir'  => '2021/08/',
			'basedir' => __DIR__ . '/../../_output/',
			'error'   => false,
		);
	}

	/**
	 * Tests the is_valid_date() function
	 *
	 * @param string $date
	 * @param bool $expected
	 *
	 * @dataProvider fixture_test_dates
	 */
	public function test_is_date( $date, $upload_dir, $expected ) {

		Functions\expect( 'get_option' )
			->with( 'timezone_string' )
			->andReturn( 'Europe/Oslo' );

		try {
			Functions\expect( 'wp_upload_dir' )
				->zeroOrMoreTimes()
				->andReturn( $upload_dir );
		} catch ( \Exception $e ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( 'wp_upload_dir() mock error: ' . esc_attr( $e->getMessage() ) );
		}

		$utils  = new Utilities( $this->m_message );
		$result = $utils->is_valid_date( $date );

		self::assertEquals( $expected, $result );
	}

	/**
	 * Date provider for the is_valid_date() unit tests
	 *
	 * @return array[]
	 */
	public function fixture_test_dates(): array {
		$upload_dir = $this->get_upload_dir();

		return array(
			array( '2021-10-11', $upload_dir, true ),
			array( '10-11-2020', $upload_dir, true ),
			array( '31-12-2020', $upload_dir, true ),
			array( '31-02-2020', $upload_dir, true ),
			array( '30th Feb, 2020', $upload_dir, true ),
			array( '29-Nov-2020', $upload_dir, true ),
			array( '1st Jan, 2020', $upload_dir, true ),
			array( 'nothing', $upload_dir, false ),
			array( null, $upload_dir, false ),
			array( false, $upload_dir, false ),
		);
	}
	/**
	 * Test if the specified plugin is considered "active" by WordPress
	 *
	 * @param string|null $plugin_name
	 * @param string|null $function_name
	 * @param bool   $is_admin
	 * @param bool   $expected
	 *
	 * @dataProvider pluginListData
	 */
	public function test_plugin_is_active( ?string $plugin_name, ?string $function_name, bool $is_admin, bool $expected ) {

		Functions\expect( 'is_admin' )
			->andReturn( $is_admin );

		Functions\expect( 'plugins_url' )
			->andReturn(
				sprintf( 'https://development.local:7254/wp-content/plugins/' )
			);

		try {
			Functions\expect( 'is_plugin_active' )
				->with( Mockery::contains( $plugin_name ) )
				->andReturn( $expected );
		} catch ( \Exception $e ) {
			self::assertFalse( true, $e->getMessage() );
		}

		try {
			Functions\expect( 'is_plugin_active_for_network' )
				->with( Mockery::contains( $plugin_name ) )
				->andReturn( $expected );
		} catch ( \Exception $e ) {
			self::assertFalse( true, $e->getMessage() );
		}

		Functions\expect( 'get_option' )
			->with( 'timezone_string' )
			->andReturn( 'Europe/Oslo' );

		try {
			Functions\expect( 'wp_upload_dir' )
				->zeroOrMoreTimes()
				->andReturn( $this->get_upload_dir() );
		} catch ( \Exception $e ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( 'wp_upload_dir() mock error: ' . esc_attr( $e->getMessage() ) );
		}

		$utils  = new Utilities( $this->m_message );
		$result = $utils->plugin_is_active( $plugin_name, $function_name );

		self::assertEquals( $expected, $result );
	}

	/**
	 * Data Provider for the plugin_is_active test function
	 *
	 * @return array[]
	 */
	public function pluginListData() {
		return array(
			// plugin_name, function_name, is_admin, expected
			array( 'plugin_file/something.php', 'my_function', false, false ),
			array( '00-e20r-utilities/class-loader.php', null, false, false ),
			array( '00-e20r-utilities/class-loader.php', null, true, true ),
			array( null, 'pmpro_getOption', false, false ),
			array( null, 'pmpro_getOption', true, false ),
			array( null, 'pmpro_not_a_function', false, false ),
			array( null, 'pmpro_not_a_function', true, false ),
			array( 'paid-memberships-pro/paid-memberships-pro.php', null, true, false ),
			array( 'paid-memberships-pro/paid-memberships-pro.php', null, false, false ),
		);
	}

	/**
	 * Test the Utilities::is_license_server() function
	 *
	 * @param string $url
	 * @param string $home_url
	 * @param string $license_server
	 * @param bool $expected
	 *
	 * @throws \Exception
	 *
	 * @dataProvider fixture_is_license_server
	 */
	public function test_is_license_server( $url, $home_url, $license_server, $expected ) {

		if ( empty( $url ) ) {
			Functions\expect( 'home_url' )
				->atLeast()
				->once()
				->andReturn( $home_url );
		}

		try {
			Functions\expect( 'wp_upload_dir' )
				->zeroOrMoreTimes()
				->andReturn( $this->get_upload_dir() );
		} catch ( \Exception $e ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( 'wp_upload_dir() mock error: ' . esc_attr( $e->getMessage() ) );
		}

		Functions\expect( 'get_option' )
			->with( 'timezone_string' )
			->andReturn( 'Europe/Oslo' );

		// TODO: Add support for the E20R_LICENSE_SERVER_URL constant
		// TODO: Can we also mock the Defaults::constant( 'E20R_LICENSE_SERVER' ) constant

		$utils  = new Utilities( $this->m_message );
		$result = $utils::is_license_server( $url );

		self::assertSame( $expected, $result );
	}

	/**
	 * Test fixture for test_is_license_server()
	 *
	 * @return array[]
	 */
	public function fixture_is_license_server() : array {
		return array(
			// url, home_url, license_server, expected
			array( 'https://eighty20results.com', 'https://eighty20results.com', 'eighty20results.com', true ),
			array( 'https://bitbetter.coach', 'https://eighty20results.com', 'eighty20results.com', false ),
			array( null, 'https://eighty20results.com', 'eighty20results.com', true ),
			array( null, 'https://example.com', 'eighty20results.com', false ),
		);
	}
}
