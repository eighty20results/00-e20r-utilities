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

namespace E20R\Licensing;

use E20R\Licensing\Exceptions\InvalidSettingsKey;
use E20R\Licensing\Exceptions\MissingServerURL;
use E20R\Licensing\Exceptions\NoLicenseKeyFound;
use E20R\Licensing\Settings\LicenseSettings;
use E20R\Licensing\License;
use E20R\Utilities\Utilities;

if ( ! class_exists( '\E20R\Licensing\Licensing' ) ) {
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
		 */
		public static function is_active( string $product_sku, array $settings, bool $is_active ): bool {

			try {
				$new_settings = new LicenseSettings( $product_sku );
			} catch ( InvalidSettingsKey | MissingServerURL $e ) {
				Utilities::get_instance()->add_message(
					$e->getMessage(),
					'error',
					'backend'
				);
				return false;
			}
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
		 * @throws Exceptions\InvalidSettingsKey
		 * @throws Exceptions\MissingServerURL
		 */
		public static function is_expiring( $product_sku ) {
			$license = new License( $product_sku );
			return $license->is_expiring( $product_sku );
		}

		/**
		 * Compatibility function replacing the old Licensing::is_licensed()
		 *
		 * @param null|string $product_sku
		 * @param false       $force
		 *
		 * @return bool
		 * @throws Exceptions\InvalidSettingsKey
		 * @throws Exceptions\MissingServerURL
		 */
		public static function is_licensed( ?string $product_sku = null, bool $force = false ): bool {
			$license = new License( $product_sku );

			return $license->is_licensed( $product_sku, $force );
		}

		/**
		 * Compatibility function replacing the old Licensing::activate()
		 *
		 * @param string $product_sku
		 *
		 * @return array
		 * @throws Exceptions\InvalidSettingsKey
		 * @throws Exceptions\MissingServerURL
		 */
		public static function activate( $product_sku ): array {
			$license = new License( $product_sku );

			return $license->activate( $product_sku );
		}

		/**
		 * Compatibility function replacing the old Licensing::deactivate()
		 *
		 * @param string     $product_sku
		 * @param null|array $settings
		 *
		 * @return bool
		 * @throws NoLicenseKeyFound
		 */
		public static function deactivate( $product_sku, $settings = null ): bool {

			try {
				$new_settings = new LicenseSettings( $product_sku );

				if ( ! empty( $settings ) ) {
					$new_settings = $new_settings->merge( $product_sku, $settings );
				}
			} catch ( InvalidSettingsKey $ike ) {
				Utilities::get_instance()->add_message(
					$ike->getMessage(),
					'error',
					'backend'
				);
				return false;
			} catch ( MissingServerURL $se ) {
				Utilities::get_instance()->add_message(
					$se->getMessage(),
					'error',
					'backend'
				);
				return false;
			}

			try {
				$license = new License( $product_sku, $new_settings );
			} catch ( Exceptions\InvalidSettingsKey | MissingServerURL $e ) {
				Utilities::get_instance()->add_message(
					$e->getMessage(),
					'error',
					'backend'
				);
				return false;
			}

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
}
