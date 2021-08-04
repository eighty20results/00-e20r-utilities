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

namespace E20R\Licensing;

use E20R\Utilities\Utilities;
use E20R\Licensing\Settings\LicenseSettings;
use Exception;

if ( ! class_exists( 'E20R\Licensing\AjaxHandler' ) ) {

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
		public function __construct( ?string $product_sku = null, ?LicenseSettings $settings = null, ?LicenseServer $server = null, ?Utilities $utils = null ) {

			if ( empty( $utils ) ) {
				$utils = Utilities::get_instance();
			}

			$this->utils = $utils;

			if ( empty( $product_sku ) ) {
				$product_sku = $this->utils->get_variable( 'product_sku', '' );
			}

			$this->license_key  = $this->utils->get_variable( 'license_key', '' );
			$this->product_sku  = $product_sku;
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

			if ( empty( $settings ) ) {
				$settings = new LicenseSettings( $this->key_to_check );
			}

			$this->settings = $settings;

			if ( empty( $server ) ) {
				$server = new LicenseServer( $this->settings );
			}

			$this->server = $server;
		}

		/**
		 * Verify the specified license (AJAX call)
		 */
		public function ajax_handler_verify_license() {

			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r, WordPress.Security.NonceVerification.Recommended
			$this->utils->log( 'Received variables from request: ' . print_r( $_REQUEST, true ) );

			if ( empty( $this->product_name ) ) {
				wp_send_json_error(
					sprintf(
						// translators: %s - SKU for licensed product
						esc_attr__(
							'No product name found for the "%s" SKU',
							'00-e20r-utilities'
						),
						$this->key_to_check
					)
				);
			}

			if ( empty( $this->key_to_check ) ) {
				wp_send_json_error(
					sprintf(
						// translators: %s - Product name for the license
						esc_attr__(
							'Error: Invalid/non-existent key specified for the "%s" license',
							'00-e20r-utilities'
						),
						$this->product_name
					)
				);
			}

			if ( empty( $this->product_sku ) ) {
				wp_send_json_error(
					sprintf(
						// translators: %s - Product name for the license
						esc_attr__(
							'Invalid SKU given for the "%s" license',
							'00-e20r-utilities'
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
					esc_attr__( 'Error: Unable to configure SKU for license', '00-e20r-utilities' ),
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
						esc_attr__(
							'Error: Invalid license key for "%s". It is not an active/available license',
							'00-e20r-utilities'
						),
						$this->product_name
					)
				);
			}
			wp_send_json_success();
		}

	}
}
