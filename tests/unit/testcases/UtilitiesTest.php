<?php
/**
 *
 * Copyright (c) 2021 - 2022. - Eighty / 20 Results by Wicked Strong Chicks.
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
 * @package E20R\Tests\Unit\UtilitiesTest
 */

namespace E20R\Tests\Unit;

use Codeception\AssertThrows;
use Codeception\Test\Unit;
use E20R\Licensing\Settings\Defaults;
use E20R\Utilities\Message;
use E20R\Utilities\Utilities;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Mockery;
use Brain\Monkey;
use Brain\Monkey\Filters;
use Brain\Monkey\Actions;
use Brain\Monkey\Functions;
use function E20R\Tests\Unit\Fixtures\e20r_unittest_stubs;
use function E20R\Tests\Unit\Fixtures\fixture_upload_dir;

if ( ! defined( 'PLUGIN_PHPUNIT' ) ) {
	define( 'PLUGIN_PHPUNIT', true );
}

/**
 * Test class for the Utilities module
 */
class UtilitiesTest extends Unit {

	use MockeryPHPUnitIntegration;
	use AssertThrows;

	/**
	 * The mocked Message() class
	 *
	 * @var Mockery|Message
	 */
	private $m_message;

	/**
	 * The setup function for this Unit Test suite
	 */
	protected function setUp(): void {

		parent::setUp();
		Monkey\setUp();
		$this->loadFiles();
		e20r_unittest_stubs();

		$this->loadStubbedClasses();
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
		require_once __DIR__ . '/../inc/unittest_stubs.php';
		require_once __DIR__ . '/../../../inc/autoload.php';
	}

	/**
	 * Define Mocked classes
	 *
	 * @throws \Exception Raised when unable to create the mocked class
	 */
	public function loadStubbedClasses() {
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
	 * @param bool $is_admin          Is the user supposed to be an admin
	 * @param bool $use_debug_logging Did we want to enable debug logging
	 * @param bool $has_action        Action to test for
	 *
	 * @dataProvider fixtures_constructor
	 * @throws \Exception Raised if a mocked class can't be created
	 */
	public function test_class_is_instantiated( $is_admin, $use_debug_logging, $has_action ) {

		Functions\when( 'has_action' )
			->justReturn( $has_action );

		if ( ! defined( 'WP_DEBUG' ) && $use_debug_logging ) {
			define( 'WP_DEBUG', true );
		}

		$utils = $this->construct(
			Utilities::class,
			array( $this->m_message ),
			array(
				'log'         => function( $msg ) {
					// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
					error_log( "Stubbed log(): {$msg}" );
				},
				'add_message' => function( $msg, $type, $location ) {
					// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
					error_log( "Stubbed WP notice: {$msg}, type: {$type}, location: {$location}" );
					self::assertSame( 'backend', $location );
				},
				'is_admin'    => $is_admin,
			)
		);

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
	 *
	 * @return array
	 */
	public function fixtures_constructor() {
		// is_admin, use_debug_logging, has_action
		return array(
			array( true, false, true ),
			array( true, false, false ),
			array( false, true, false ),
			array( false, true, true ),
		);
	}

	/**
	 * Tests the is_valid_date() function
	 *
	 * @param string $date     Date string to test validity of
	 * @param bool   $expected The expected result
	 *
	 * @dataProvider fixture_test_dates
	 * @covers       \E20R\Utilities\Utilities::is_valid_date
	 * @test
	 *
	 * @throws \Exception Raised when class can't be mocked
	 */
	public function test_is_valid_date( $date, $expected ) {

		$utils = $this->construct(
			Utilities::class,
			array( $this->m_message ),
			array(
				'log'         => function( $msg ) {
					// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
					error_log( "Stubbed log(): {$msg}" );
				},
				'add_message' => function( $msg, $type, $location ) {
					// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
					error_log( "Stubbed WP notice: {$msg}, type: {$type}, location: {$location}" );
					self::assertSame( 'backend', $location );
				},
			)
		);

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
	 * @param string|null $plugin_name Name of plugin to check for the presence/activation of
	 * @param string|null $function_name Function name to use when testing for plugin presence/activation
	 * @param bool        $is_admin Test environment supposed to be in admin state
	 * @param bool        $expected Expected return value
	 *
	 * @dataProvider pluginListData
	 * @covers \E20R\Utilities\Utilities::plugin_is_active
	 * @throws \Exception Raised when mocked class cannot be created
	 *
	 * @test
	 */
	public function test_plugin_is_active( ?string $plugin_name, ?string $function_name, bool $is_admin, bool $expected ) {

		Functions\expect( 'is_admin' )
			->andReturn( $is_admin );

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

		$utils = $this->construct(
			Utilities::class,
			array( $this->m_message ),
			array(
				'log'         => function( $msg ) {
					// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
					error_log( "Stubbed log(): {$msg}" );
				},
				'add_message' => function( $msg, $type, $location ) {
					// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
					error_log( "notice: {$msg}, type: {$type}, location: {$location}" );
					self::assertSame( 'backend', $location );
				},
			)
		);

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
	 * @param string $url URL string to test
	 * @param string $home_url Expected WP home() URL
	 * @param string $license_server License server hostname
	 * @param bool   $expected Expected return value
	 *
	 * @throws \Exception Raised when class cannot be mocked
	 * @covers \E20R\Utilities\Utilities::is_license_server
	 * @dataProvider fixture_is_license_server
	 *
	 * @test
	 */
	public function test_is_license_server( $url, $home_url, $license_server, $expected ) {

		if ( empty( $url ) ) {
			Functions\expect( 'home_url' )
				->zeroOrMoreTimes()
				->andReturn( $home_url );
		}

		$license_server_url = sprintf( 'https://%1$s', $license_server );

		$m_defaults = $this->makeEmpty(
			Defaults::class,
			array(
				'constant' => function( $name, $operation, $value ) use ( $license_server, $license_server_url ) {
					if ( empty( $operation ) || Defaults::READ_CONSTANT === $operation ) {
						if ( 'E20R_LICENSE_SERVER_URL' === $name ) {
							return $license_server_url;
						}

						if ( 'E20R_LICENSE_SERVER' === $name ) {
							return $license_server;
						}
					}
				},
			)
		);

		$utils = $this->construct(
			Utilities::class,
			array( $this->m_message ),
			array(
				'log'         => function( $msg ) {
					// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
					error_log( "Stubbed log(): {$msg}" );
				},
				'add_message' => function( $msg, $type, $location ) {
					// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
					error_log( "Stubbed WP notice: {$msg}, type: {$type}, location: {$location}" );
					self::assertSame( 'backend', $location );
				},
			)
		);

		$result = $utils->is_license_server( $url, $m_defaults );
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
			array( 'https://eighty20results.com', 'https://eighty20results.com', 'eighty20results.com', true ), // # 0
			array( 'https://bitbetter.coach', 'https://eighty20results.com', 'eighty20results.com', false ), // # 1
			array( null, 'https://eighty20results.com', 'eighty20results.com', true ), // # 2
			array( null, 'https://example.com', 'eighty20results.com', false ), // # 3
		);
	}
}
