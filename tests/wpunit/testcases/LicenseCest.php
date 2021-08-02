<?php
/*
 * Copyright (c) 2016 - 2021 - Eighty / 20 Results by Wicked Strong Chicks.
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
 */

namespace E20R\Test\Functional;

use E20R\Utilities\Licensing\Exceptions\InvalidSettingKeyException;
use E20R\Utilities\Licensing\Exceptions\MissingServerURL;
use E20R\Utilities\Licensing\License;
use E20R\Utilities\Licensing\LicenseSettings;
use E20R\Utilities\Licensing\Settings\Defaults;

class LicenseCest {

	private $server = null;

	private $page = null;

	public function testIs_active( FunctionalTester $I ) {

	}

	public function testActivate( FunctionalTester $I ) {

	}

	public function testIs_licensed( FunctionalTester $I ) {

	}

	public function testIs_expiring( FunctionalTester $I ) {

	}

	public function testIs_new_version( FunctionalTester $I ) {

	}

	/**
	 * Test whether we'll use SSL verify or not
	 *
	 * @param string $sku
	 * @param bool $expected
	 * @param LicenseSettings $settings
	 *
	 * @dataProvider fixture_ssl_verify
	 */
	public function test_get_ssl_verify( string $sku, LicenseSettings $settings, bool $expected ) {
		try {
			$license = new License( $sku, $settings );
		} catch ( \Exception $e ) {
			assertFalse( true, $e->getMessage() );
		}

		assertSame( $expected, $license->get_ssl_verify() );
	}

	/**
	 * Generate fixture for the get_ssl_verify() tests
	 *
	 * @return array
	 */
	public function fixture_ssl_verify() {

		$possible_values = array(
			0 => true,
			1 => false,
		);
		$fixture_values  = array();

		foreach ( $possible_values as $ssl_value ) {
			try {
				$settings = new LicenseSettings( 'e20r_default_license' );
				$settings->set( 'ssl_verify', $ssl_value );
			} catch ( InvalidSettingKeyException | MissingServerURL $e ) {
				assertFalse( true, $e->getMessage() );
			}

			$fixture_values[] = array( 'e20r_default_license', $settings, $ssl_value );
		}

		return $fixture_values;
	}

	public function testGet_ssl_verify( FunctionalTester $I ) {


	}

	public function testDeactivate( FunctionalTester $I ) {

	}

	public function testRegister( FunctionalTester $I ) {

	}
}
