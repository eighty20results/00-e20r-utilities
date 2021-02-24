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

namespace phpunit\testcases;

use E20R\Utilities\Message;
use E20R\Utilities\Utilities;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use PHPUnit\Framework\TestCase;
use Mockery;
use Brain\Monkey;
use Brain\Monkey\Filters;
use Brain\Monkey\Actions;
use Brain\Monkey\Functions;

class UtilitiesTest extends TestCase {

	use MockeryPHPUnitIntegration;

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

		$utils = Utilities::get_instance();
		self::assertEquals( $expected, $utils->is_valid_date( $data ) );

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
			echo sprintf( 'Error catching applied filter: %s', $exception->getMessage() );
			self::assertTrue( false );
		}
	}

	/**
	 * Data provider for the is_valid_date() unittests
	 *
	 * @return array[]
	 */
	public function dateTestData(): array {
		return array(
			array( '10-21-2021', true ),
			array( 'nothing', false ),
		);
	}
}

