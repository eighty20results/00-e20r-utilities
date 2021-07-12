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

namespace E20R\Utilities\Licensing;

use E20R\Utilities\Utilities;
use Exception;

if ( ! class_exists( 'E20R\Utilities\Licensing\AjaxHandler' ) ) {

	class AjaxHandler {

		/**
		 * Instance of the E20R Utilities class
		 *
		 * @var Utilities|null $utils
		 */
		private $utils = null;

		/**
		 * @var LicenseSettings|null $settings
		 */
		private $settings = null;

		/**
		 * The server instance
		 *
		 * @var LicenseServer|null $server
		 */
		private $server = null;

		/**
		 * Constructor for the AjaxHandler() class
		 */
		public function __construct() {
			$this->utils  = Utilities::get_instance();
			$this->server = new LicenseServer();
		}

		/**
		 * Verify the specified license (AJAX call)
		 */
		public function ajax_handler_verify_license() {

			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r, WordPress.Security.NonceVerification.Recommended
			$this->utils->log( 'Received variables from request: ' . print_r( $_REQUEST, true ) );

			$license_key  = $this->utils->get_variable( 'license_key', '' );
			$product_sku  = $this->utils->get_variable( 'product_sku', '' );
			$product_name = $this->utils->get_variable( 'product_name', '' );

			if ( empty( $product_name ) ) {
				wp_send_json_error(
					sprintf(
					// translators: The SKU value is provided from the calling function
						__(
							'No product name found for the "%s" SKU',
							'e20r-utilities-licensing'
						),
						$product_sku
					)
				);
			}

			$this->settings = new LicenseSettings( $product_sku );

			if ( empty( $license_key ) ) {
				wp_send_json_error(
					sprintf(
					// translators: The product name for the license is a filter provided value
						__(
							'Error: Invalid/non-existent key specified for the "%s" license',
							'e20r-utilities-licensing'
						),
						$product_name
					)
				);
			}

			if ( empty( $product_sku ) ) {
				wp_send_json_error(
					sprintf(
					// translators: The product name for the license is a filter provided value
						__(
							'Invalid SKU given for the "%s" license',
							'e20r-utilities-licensing'
						),
						$product_name
					)
				);
			}

			$this->utils->log( 'Forcing verification/check against upstream license server' );
			try {
				$this->settings->set( 'product_sku', $product_sku );
			} catch ( Exception $e ) {
				$this->utils->add_message(
					__( 'Error: Unable to configure SKU for license', 'e20r-utilities-licensing' ),
					'error',
					'backend'
				);
			}

			// TODO: Use $license_key if this is for an old-style license
			// TODO: Use $product_sku if it's for a new-style license
			$status = $this->server->status(
				$product_sku,
				$this->settings->all_settings(),
				true
			);

			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r
			$this->utils->log( 'License status: ' . print_r( $status, true ) );

			if ( empty( $status ) ) {
				wp_send_json_error(
					sprintf(
					// translators: The product name for the license is a filter provided value
						__(
							'Error: Invalid license key for "%s". It is not an active/available license',
							// phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralDomain
							'e20r-utilities-licensing'
						),
						$product_name
					)
				);
			}

			wp_send_json_success();
		}

	}
}
