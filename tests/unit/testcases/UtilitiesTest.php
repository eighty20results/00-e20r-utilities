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

use Codeception\Test\Unit;
use E20R\Utilities\Message;
use E20R\Utilities\Utilities;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;
use Mockery;
use Brain\Monkey;
use Brain\Monkey\Filters;
use Brain\Monkey\Actions;
use Brain\Monkey\Functions;

class UtilitiesTest extends Unit {

	use MockeryPHPUnitIntegration;

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
				'plugin_dir_path'     => '/var/www/html/wp-content/plugins/00-e20r-utilities',
				'get_current_blog_id' => 1,
			)
		);

		Functions\expect( 'get_option' )
			->with( Mockery::contains( 'e20r_license_settings' ) )
			->andReturn( array() );

		Functions\expect( 'get_option' )
			->with( Mockery::contains( 'timezone_string' ) )
			->andReturn( 'Europe/Oslo' );
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
	 * @param bool $has_action
	 *
	 * @dataProvider fixtures_constructor
	 */
	public function test_class_is_instantiated( $is_admin, $has_action ) {

		Functions\expect( 'plugins_url' )
			->andReturn( 'https://localhost:7254/wp-content/plugins/00-e20r-utilities' );

		Functions\expect( 'plugin_dir_path' )
			->andReturn( '/var/www/html/wp-content/plugins/00-e20r-utilities' );

		Functions\expect( 'get_current_blog_id' )
			->andReturn( 1 );

		$util_mock = $this->getMockBuilder( Utilities::class )->onlyMethods( array( 'is_admin', 'log' ) )->getMock();
		$util_mock->method( 'is_admin' )->willReturn( $is_admin );
		$util_mock->method( 'log' )->willReturn( null );

		Functions\when( 'has_action' )
			->justReturn( $has_action );

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
		return array(
			array( true, true ),
			array( true, false ),
			array( false, false ),
			array( false, true ),
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
	public function test_is_date( $date, $expected ) {
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
		return array(
			array( '2021-10-11', true ),
			array( '10-11-2020', true ),
			array( '31-12-2020', true ),
			array( '31-02-2020', true ),
			array( '30th Feb, 2020', true ),
			array( '29-Nov-2020', true ),
			array( '1st Jan, 2020', true ),
			array( 'nothing', false ),
			array( null, false ),
			array( false, false ),
		);
	}
	/**
	 * Test if the specified plugin is considered "active" by WordPress
	 *
	 * @param string $plugin_name
	 * @param string $function_name
	 * @param bool $expected
	 *
	 * @dataProvider pluginListData
	 */
	public function test_plugin_is_active( $plugin_name, $function_name, $is_admin, $expected ) {
		Functions\expect( 'get_option' )
			->with( Mockery::contains( 'timezone_string' ) )
			->andReturn( 'Europe/Oslo' );

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

		// TODO: Add support for the E20R_LICENSE_SERVER_URL constant
		// TODO: Can we also mock the Defaults::E20R_LICENSE_SERVER constant

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
