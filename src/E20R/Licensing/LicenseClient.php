<?php
/**
 * Copyright (c) 2017 - 2021 - Eighty / 20 Results by Wicked Strong Chicks.
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

use E20R\Utilities\Utilities;

// Deny direct access to the file
if ( ! defined( 'ABSPATH' ) && function_exists( 'wp_die' ) ) {
	wp_die( 'Cannot access file directly' );
}

if ( ! class_exists( '\E20R\Licensing\LicenseClient' ) ) {

	/**
	 * Class LicenseClient
	 * @package E20R\Utilities\Licensing
	 */
	abstract class LicenseClient {

		/**
		 * @var null|LicenseClient
		 */
		private static $instance = null;

		/**
		 * LicenseClient constructor.
		 */
		private function __construct() {

			if ( is_null( self::$instance ) ) {
				self::$instance = $this;
			}
		}

		// Load hooks to add new license info to license page
		abstract public function load_hooks();

		/**
		 * The current instance of the LicenseClient class
		 *
		 * @return LicenseClient|null
		 */
		public static function get_instance() {

			if ( ! is_null( self::$instance ) ) {

				self::$instance->load_hooks();
			}

			return self::$instance;
		}

		abstract public function check_licenses();

		/**
		 * Filter Handler: Add the a new License settings entry
		 *
		 * @filter e20r-license-add-new-licenses
		 *
		 * @param array $license_settings
		 * @param array $plugin_settings
		 *
		 * @return array
		 */
		protected function add_new_license_info( $license_settings, $plugin_settings ) {

			$utils = Utilities::get_instance();

			if ( ! isset( $license_settings['new_licenses'] ) ) {
				$license_settings['new_licenses'] = array();
				$utils->log( 'Init array of licenses entry' );
			}

			$utils->log( 'Have ' . count( $license_settings['new_licenses'] ) . " new licenses to process already. Adding for sku {$plugin_settings['key_prefix']}/{$plugin_settings['stub']}... " );

			$license_settings['new_licenses'][ $plugin_settings['key_prefix'] ] = array(
				'label_for'     => $plugin_settings['key_prefix'],
				'fulltext_name' => $plugin_settings['label'],
				'new_product'   => $plugin_settings['key_prefix'],
				'option_name'   => 'e20r_license_settings',
				'name'          => 'license_key',
				'input_type'    => 'password',
				'value'         => null,
				'email_field'   => 'license_email',
				'email_value'   => null,
				'product_sku'   => strtoupper( $plugin_settings['key_prefix'] ),
				// translators: The label that describes the license is defined in the received settings
				'placeholder'   => sprintf(
					// translators: The licensed plugin will set its own label (name)
					esc_attr__(
						'Paste the received \'%1$s\' key here',
						'00-e20r-utilities'
					),
					$plugin_settings['label']
				),
			);

			return $license_settings;
		}
	}
}
