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

namespace E20R\Tests\WPUnit;

use Codeception\Test\Test;
use Codeception\TestCase\WPTestCase;
use E20R\Licensing\Exceptions\InvalidSettingsKey;
use E20R\Licensing\Exceptions\MissingServerURL;
use E20R\Licensing\License;
use E20R\Licensing\Settings\LicenseSettings;
use E20R\Licensing\Settings\Defaults;

class License_WPUnitTest extends WPTestCase {

	private $server = null;

	private $page = null;

	public function test_is_active() {

	}

	public function test_activate() {

	}

	public function test_is_licensed() {

	}

	/**
	 * Test whether we'll use SSL verify or not
	 *
	 * @param string|null          $sku
	 * @param LicenseSettings|null $settings
	 * @param bool                 $expected
	 *
	 * @dataProvider fixture_ssl_verify
	 */
	public function test_get_ssl_verify( ?string $sku, ?LicenseSettings $settings, bool $expected ) {
		try {
			$license = new License( $sku, $settings );
			self::assertSame( $expected, $license->get_ssl_verify() );
		} catch ( \Exception $e ) {
			self::assertFalse( true, $e->getMessage() );
		}
	}

	/**
	 * Generate fixture for the get_ssl_verify() tests
	 *
	 * @return array
	 * @throws \Exception
	 */
	public function fixture_ssl_verify() {

		$possible_values = array(
			0 => true,
			1 => false,
		);
		$fixture_values  = array();

		foreach ( $possible_values as $ssl_value ) {
			try {
				$defaults = new Defaults();
				$settings = new LicenseSettings( 'e20r_default_license', $defaults );
				$settings->set( 'ssl_verify', $ssl_value );
				$fixture_values[] = array( 'e20r_default_license', $settings, $ssl_value );
			} catch ( InvalidSettingsKey | MissingServerURL $e ) {
				self::assertFalse( true, $e->getMessage() );
			}
		}
		return $fixture_values;
	}

	public function test_is_expiring() {

	}

	public function test_is_new_version() {

	}

	public function test_deactivate() {

	}

	public function test_register() {

	}
}
