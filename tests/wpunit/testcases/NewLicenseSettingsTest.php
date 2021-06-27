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

namespace E20R\Utilities\Licensing\Test;

use E20R\Utilities\Licensing\NewLicenseSettings;
use Brain\Monkey;
use Brain\Monkey\Functions;

class NewLicenseSettingsTest extends \Codeception\Test\Unit {

	/**
	 * The setup function for this Unit Test suite
	 */
	protected function setUp(): void {
		parent::setUp();
		Monkey\setUp();

		Functions\expect( 'plugins_url' )
			->andReturn( sprintf( 'https://localhost:7253/wp-content/plugins/' ) );

		Functions\expect( 'admin_url' )
			->with( 'options-general.php' )
			->andReturn( 'https://localhost:7253/wp-admin/options-general.php' );

		Functions\expect( 'plugin_dir_path' )
			->andReturn( sprintf( '/var/www/html/wp-content/plugins/00-e20r-utilities/src/licensing' ) );

		Functions\expect( 'get_current_blog_id' )
			->andReturn( 1 );
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
	 * Test create license settings class (new API)
	 *
	 * @dataProvider fixture_license_settings
	 */
	public function test_create_license_settings( $sku, $expected ) {

		$settings = new NewLicenseSettings( $sku );

		Functions\expect( 'get_option' )
			->with( 'e20r_license_settings' )
			->andReturnUsing( function() use ($settings, $sku ) {
				return $settings->defaults( $sku );
			});


		self::assertEquals( $expected, $settings->get( 'product_sku' ) );
	}

	/**
	 * Fixture for new License settings
	 * @return array[]
	 */
	public function fixture_license_settings() {
		return array(
			array( 'PRODUCT_1', 'PRODUCT_1' ),
		);
	}
}
