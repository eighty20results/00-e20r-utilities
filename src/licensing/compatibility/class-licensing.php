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

namespace E20R\Utilities\Licensing;

use E20R\Utilities\Licensing\Exceptions\InvalidSettingKeyException;
use E20R\Utilities\Licensing\Exceptions\MissingServerURL;
use E20R\Utilities\Licensing\Exceptions\NoLicenseKeyFoundException;
use E20R\Utilities\Utilities;

/**
 * Compatibility class for old license management approach
 *
 * Class Licensing
 * @package E20R\Utilities\Licensing
 */
class Licensing {

	/**
	 * Compatibility function replacing the old Licensing::is_active()
	 *
	 * @param string $product_sku
	 * @param array  $settings
	 * @param bool   $is_active
	 *
	 * @return bool
	 * @throws Exceptions\InvalidSettingKeyException
	 * @throws Exceptions\MissingServerURL
	 */
	public static function is_active( string $product_sku, array $settings, bool $is_active ): bool {

		$new_settings = new LicenseSettings( $product_sku );
		$new_settings->merge( $product_sku, $settings );

		$license = new License( $product_sku, $new_settings );

		return $license->is_active( $product_sku, $is_active );
	}

	/**
	 * Compatibility function replacing the old Licensing::is_expiring()
	 *
	 * @param string $product_sku
	 *
	 * @return bool|int
	 * @throws Exceptions\InvalidSettingKeyException
	 * @throws Exceptions\MissingServerURL
	 */
	public static function is_expiring( $product_sku ) {
		$license = new License( $product_sku );
		return $license->is_expiring( $product_sku );
	}

	/**
	 * Compatibility function replacing the old Licensing::is_licensed()
	 *
	 * @param null|string  $product_sku
	 * @param false $force
	 *
	 * @return bool
	 * @throws Exceptions\InvalidSettingKeyException
	 * @throws Exceptions\MissingServerURL
	 */
	public static function is_licensed( ?string $product_sku = null, bool $force = false ) : bool {
		$license = new License( $product_sku );
		return $license->is_licensed( $product_sku, $force );
	}

	/**
	 * Compatibility function replacing the old Licensing::activate()
	 *
	 * @param string $product_sku
	 *
	 * @return array
	 * @throws Exceptions\InvalidSettingKeyException
	 * @throws Exceptions\MissingServerURL
	 */
	public static function activate( $product_sku ) : array {
		$license = new License( $product_sku );
		return $license->activate( $product_sku );
	}


	/**
	 * Compatibility function replacing the old Licensing::deactivate()
	 *
	 * @param string $product_sku
	 * @param null|array $settings
	 *
	 * @return bool
	 * @throws Exceptions\InvalidSettingKeyException
	 * @throws Exceptions\MissingServerURL
	 * @throws Exceptions\NoLicenseKeyFoundException
	 */
	public static function deactivate( $product_sku, $settings = null ) : bool {

		try {
			$new_settings = new LicenseSettings( $product_sku );

			if ( ! empty( $settings ) ) {
				$new_settings = $new_settings->merge( $product_sku, $settings );
			}
		} catch ( InvalidSettingKeyException $ike ) {
			Utilities::get_instance()->add_message( 'Error: ' . $ike->getMessage(), 'error', 'backend' );
			return false;
		} catch ( MissingServerURL $se ) {
			Utilities::get_instance()->add_message( 'Error: ' . $se->getMessage(), 'error', 'backend' );
			return false;
		} catch ( NoLicenseKeyFoundException $lke ) {
			Utilities::get_instance()->add_message( 'Error: ' . $lke->getMessage(), 'error', 'backend' );
			return false;
		}

		$license = new License( $product_sku, $new_settings );
		return $license->deactivate( $product_sku, $new_settings->get_settings( $product_sku ) );
	}

	/**
	 * Compatibility function replacing the old Licensing::register()
	 */
	public static function register() {
		$license = new License();
		$license->register();
	}
}
