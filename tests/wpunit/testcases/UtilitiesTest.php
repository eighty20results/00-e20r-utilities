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

namespace E20R\Test;

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

	// use MockeryPHPUnitIntegration;

	/**
	 * The setup function for this Unit Test suite
	 *
	 */
	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();

		Monkey\Functions\when( 'plugins_url' )
			->justReturn( sprintf( 'https://development.local/wp-content/plugins/' ) );
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
	 * Tests the is_valid_date() function
	 *
	 * @param $data
	 * @param $expected
	 * @dataProvider dateTestData
	 */
	public function test_is_date( $data, $expected ) {

		$utils  = Utilities::get_instance();
		$result = $utils->is_valid_date( $data );

		self::assertEquals( $expected, $result );

		try {
			Filters\expectApplied( 'e20r-licensing-text-domain' )
				->with( '00-e20r-utilities', Mockery::type( Utilities::class ) );

			Filters\expectAdded( 'woocommerce_update_cart_action_cart_updated' )
				->with( Mockery::type( Message::class ) );

			Filters\expectAdded( 'pmpro_email_field_type' )
				->with( Mockery::type( Message::class ) );

			Filters\expectAdded( 'pmpro_get_membership_levels_for_user' )
				->with( Mockery::type( Message::class ) );

			Actions\expectAdded( 'woocommerce_init' )
				->with( Mockery::type( Message::class ) );

		} catch ( \Exception $exception ) {
			self::assertTrue( false, 'Error: ' . $exception->getMessage() );
		}
	}

	/**
	 * @param $plugin_name
	 * @param $function_name
	 * @param $expected
	 *
	 * @dataProvider pluginListData
	 */
	public function test_plugin_is_active( $plugin_name, $function_name, $is_admin, $expected ) {
		$utils  = Utilities::get_instance();
		$result = null;

		Functions\expect( 'is_admin' )
			->andReturn( $is_admin );

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
			// $plugin_name, $function_name, $is_admin, $expected
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
	 * Data provider for the is_valid_date() unittests
	 *
	 * @return array[]
	 */
	public function dateTestData(): array {
		return array(
			array( '2021-10-11', true ),
			array( '10-11-2020', true ),
			array( '31-12-2020', true ),
			array( '31-02-2020', true ),
			array( '30th Feb, 2020', true ),
			array( '29-Nov-2020', true ),
			array( '1st Jan, 2020', true ),
			array( 'nothing', false ),
		);
	}

}
