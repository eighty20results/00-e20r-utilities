<?php
/**
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
 *
 * @package E20R\Licensing\Licensing
 */

namespace E20R\Licensing;

use E20R\Licensing\Exceptions\BadOperation;
use E20R\Licensing\Exceptions\ConfigDataNotFound;
use E20R\Licensing\Exceptions\ErrorSavingSettings;
use E20R\Licensing\Exceptions\InvalidSettingsKey;
use E20R\Licensing\Exceptions\InvalidSettingsVersion;
use E20R\Licensing\Exceptions\MissingServerURL;
use E20R\Licensing\Exceptions\NoLicenseKeyFound;
use E20R\Licensing\Exceptions\ServerConnectionError;
use E20R\Licensing\Settings\LicenseSettings;
use E20R\Utilities\Utilities;
use Exception;
use ReflectionException;

if ( ! class_exists( '\E20R\Licensing\Licensing' ) ) {
	/**
	 * Compatibility class for old license management approach
	 */
	class Licensing {

		/**
		 * Compatibility function replacing the old Licensing::is_active()
		 *
		 * @param string $product_sku The product sku to use (from the E20R License Store)
		 * @param array  $settings    The settings to use
		 * @param bool   $is_active   Defines whether the license key is active or not (assumes 'is_licensed()' has been executed)
		 *
		 * @return bool
		 */
		public static function is_active( string $product_sku, array $settings, bool $is_active ): bool {

			_deprecated_function( 'Licensing::is_active()', '5.8', 'License::is_active()' );
			try {
				$new_settings = new LicenseSettings( $product_sku );
			} catch ( InvalidSettingsKey | MissingServerURL | ConfigDataNotFound | BadOperation | InvalidSettingsVersion | Exception $e ) {
				Utilities::get_instance()->add_message(
					$e->getMessage(),
					'error',
					'backend'
				);
				return false;
			}
			try {
				$new_settings->merge( $settings );
			} catch ( ErrorSavingSettings | ReflectionException $e ) {
				Utilities::get_instance()->add_message(
					$e->getMessage(),
					'error',
					'backend'
				);
				return false;
			}

			try {
				$license = new License( $product_sku, $new_settings );
			} catch ( BadOperation | ConfigDataNotFound | InvalidSettingsKey | InvalidSettingsVersion | MissingServerURL | Exception $e ) {
				Utilities::get_instance()->add_message(
					$e->getMessage(),
					'error',
					'backend'
				);
				return false;
			}

			try {
				return $license->is_active( $product_sku, $is_active );
			} catch ( InvalidSettingsKey $e ) {
				Utilities::get_instance()->add_message(
					$e->getMessage(),
					'error',
					'backend'
				);
				return false;
			}

		}

		/**
		 * Compatibility function replacing the old Licensing::is_expiring()
		 *
		 * @param string $product_sku The product sku to use (from the E20R License Store)
		 *
		 * @return bool|int
		 */
		public static function is_expiring( $product_sku ) {
			_deprecated_function( 'Licensing::is_expiring()', '5.8', 'License::is_expiring()' );
			try {
				$license = new License( $product_sku );
			} catch ( BadOperation | ConfigDataNotFound | InvalidSettingsKey | InvalidSettingsVersion | MissingServerURL | Exception $e ) {
				Utilities::get_instance()->add_message(
					$e->getMessage(),
					'error',
					'backend'
				);
				return false;
			}

			return $license->is_expiring( $product_sku );
		}

		/**
		 * Compatibility function replacing the old Licensing::is_licensed()
		 *
		 * @param null|string $product_sku The product sku to use (from the E20R License Store)
		 * @param bool        $force Whether to skip using the cached value
		 *
		 * @return bool
		 */
		public static function is_licensed( ?string $product_sku = null, bool $force = false ): bool {
			_deprecated_function( 'Licensing::is_licensed()', '5.8', 'License::is_licensed()' );
			try {
				$license = new License( $product_sku );
			} catch ( BadOperation | ConfigDataNotFound | InvalidSettingsKey | InvalidSettingsVersion | MissingServerURL | Exception $e ) {
				Utilities::get_instance()->add_message(
					$e->getMessage(),
					'error',
					'backend'
				);
				return false;
			}

			return $license->is_licensed( $product_sku, $force );
		}

		/**
		 * Compatibility function replacing the old Licensing::activate()
		 *
		 * @param string $product_sku The product sku to use (from the E20R License Store)
		 *
		 * @return array
		 */
		public static function activate( $product_sku ): array {
			_deprecated_function( 'Licensing::activate()', '5.8', 'License::activate()' );
			try {
				$license = new License( $product_sku );
			} catch ( BadOperation | ConfigDataNotFound | InvalidSettingsKey | InvalidSettingsVersion | MissingServerURL | Exception $e ) {
				Utilities::get_instance()->add_message(
					$e->getMessage(),
					'error',
					'backend'
				);
				return array();
			}

			try {
				return $license->activate( $product_sku );
			} catch ( BadOperation | ConfigDataNotFound | InvalidSettingsKey | InvalidSettingsVersion | MissingServerURL | ServerConnectionError | ReflectionException $e ) {
				Utilities::get_instance()->add_message(
					$e->getMessage(),
					'error',
					'backend'
				);
				return array();
			}
		}

		/**
		 * Compatibility function replacing the old Licensing::deactivate()
		 *
		 * @param string     $product_sku The product sku to use (from the E20R License Store)
		 * @param null|array $settings    The license settings being used for this license SKU
		 *
		 * @return bool
		 * @throws NoLicenseKeyFound Raised if the specified license key was not found
		 */
		public static function deactivate( $product_sku, $settings = null ): bool {
			_deprecated_function( 'Licensing::deactivate()', '5.8', 'License::deactivate()' );
			try {
				$new_settings = new LicenseSettings( $product_sku );
			} catch ( BadOperation | ConfigDataNotFound | InvalidSettingsKey | InvalidSettingsVersion | MissingServerURL $e ) {
				Utilities::get_instance()->add_message(
					$e->getMessage(),
					'error',
					'backend'
				);
				return false;
			}

			if ( ! empty( $settings ) ) {
				try {
						$new_settings = $new_settings->merge( $settings );
				} catch ( ErrorSavingSettings | ReflectionException $ike ) {
					Utilities::get_instance()->add_message(
						$ike->getMessage(),
						'error',
						'backend'
					);
					return false;
				}
			}

			try {
				$license = new License( $product_sku, $new_settings );
			} catch ( InvalidSettingsKey | MissingServerURL | BadOperation | ConfigDataNotFound | InvalidSettingsVersion | Exception $e ) {
				Utilities::get_instance()->add_message(
					$e->getMessage(),
					'error',
					'backend'
				);
				return false;
			}

			try {
				return $license->deactivate( $product_sku, $new_settings->get_settings( $product_sku ) );
			} catch ( InvalidSettingsKey $e ) {
				Utilities::get_instance()->add_message(
					$e->getMessage(),
					'error',
					'backend'
				);
				return false;
			}
		}

		/**
		 * Compatibility function replacing the old Licensing::register()
		 *
		 * @return bool
		 */
		public static function register() {
			_deprecated_function( 'Licensing::register()', '5.8', 'License::register()' );

			try {
				$license = new License();
			} catch ( Exception $e ) {
				Utilities::get_instance()->add_message(
					$e->getMessage(),
					'error',
					'backend'
				);
				return false;
			}

			$license->register();
			return true;
		}
	}
}
