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
		 * License Identifier (old style licenses)
		 *
		 * @var string $license_key
		 */
		private $license_key = null;

		/**
		 * License Identifier (new style licenses)
		 *
		 * @var string $product_sku
		 */
		private $product_sku = null;

		/**
		 * The name of the product (license)
		 *
		 * @var string $product_name
		 */
		private $product_name = null;

		/**
		 * License Identifier we'll use
		 *
		 * @var string $key_to_check
		 */
		private $key_to_check = null;

		/**
		 * Constructor for the AjaxHandler() class
		 *
		 * @throws \Exception
		 */
		public function __construct() {

			$this->utils        = Utilities::get_instance();
			$this->license_key  = $this->utils->get_variable( 'license_key', '' );
			$this->product_sku  = $this->utils->get_variable( 'product_sku', '' );
			$this->product_name = $this->utils->get_variable( 'product_name', '' );

			if ( empty( $this->license_key ) && ! empty( $this->product_sku ) ) {
				$this->key_to_check = $this->product_sku;
			}

			if ( empty( $this->product_sku ) && ! empty( $this->license_key ) ) {
				$this->key_to_check = $this->license_key;
			}

			if ( empty( $this->key_to_check ) ) {
				throw new \Exception( 'Error: Neither the license key nor product sku was received!' );
			}

			$this->settings = new LicenseSettings( $this->key_to_check );
			$this->server   = new LicenseServer( $this->settings->get( 'new_version' ), $this->settings->get( 'ssl_verify' ) );
		}

		/**
		 * Verify the specified license (AJAX call)
		 */
		public function ajax_handler_verify_license() {

			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r, WordPress.Security.NonceVerification.Recommended
			$this->utils->log( 'Received variables from request: ' . print_r( $_REQUEST, true ) );

			if ( empty( $product_name ) ) {
				wp_send_json_error(
					sprintf(
						// translators: %s - SKU for licensed product
						__(
							'No product name found for the "%s" SKU',
							'e20r-utilities-licensing'
						),
						$this->key_to_check
					)
				);
			}

			if ( empty( $license_key ) ) {
				wp_send_json_error(
					sprintf(
						// translators: %s - Product name for the license
						__(
							'Error: Invalid/non-existent key specified for the "%s" license',
							'e20r-utilities-licensing'
						),
						$this->product_name
					)
				);
			}

			if ( empty( $product_sku ) ) {
				wp_send_json_error(
					sprintf(
						// translators: %s - Product name for the license
						__(
							'Invalid SKU given for the "%s" license',
							'e20r-utilities-licensing'
						),
						$this->product_name
					)
				);
			}

			$this->utils->log( 'Forcing verification/check against upstream license server' );
			try {
				$this->settings->set( 'product_sku', $this->key_to_check );
			} catch ( Exception $e ) {
				$this->utils->add_message(
					__( 'Error: Unable to configure SKU for license', 'e20r-utilities-licensing' ),
					'error',
					'backend'
				);
			}

			// Check the license status upstream
			$status = $this->server->status( $this->key_to_check, $this->settings->all_settings(), true );

			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r
			$this->utils->log( 'License status: ' . print_r( $status, true ) );

			if ( empty( $status ) ) {
				wp_send_json_error(
					sprintf(
						// translators: %s - Product name for the license
						__(
							'Error: Invalid license key for "%s". It is not an active/available license',
							// phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralDomain
							'e20r-utilities-licensing'
						),
						$this->product_name
					)
				);
			}
			wp_send_json_success();
		}

	}
}
