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
 *
 * @version 3.0
 * @package E20R\Utilities\Licensing\License
 */

namespace E20R\Licensing;

use E20R\Licensing\Exceptions\BadOperation;
use E20R\Licensing\Exceptions\ConfigDataNotFound;
use E20R\Licensing\Exceptions\ErrorSavingSettings;
use E20R\Licensing\Exceptions\InvalidSettingsKey;
use E20R\Licensing\Exceptions\InvalidSettingsVersion;
use E20R\Licensing\Exceptions\MissingServerURL;
use E20R\Licensing\Exceptions\ServerConnectionError;
use E20R\Licensing\Settings\Defaults;
use E20R\Licensing\Settings\LicenseSettings;
use E20R\Utilities\Message;
use E20R\Utilities\Utilities;
use Exception;
use ReflectionException;

// Deny direct access to the file
if ( ! defined( 'ABSPATH' ) && function_exists( 'wp_die' ) ) {
	wp_die( 'Cannot access file directly' );
}

if ( ! class_exists( '\E20R\Licensing\License' ) ) {

	/**
	 * Class used to handle operations from the licensing client
	 */
	class License {

		/**
		 * License cache keys
		 */
		const CACHE_KEY   = 'active_licenses';
		const CACHE_GROUP = 'e20r_licensing';

		/**
		 * I18N domain name (translation)
		 *
		 * @var string|null
		 */
		private $text_domain = '00-e20r-utilities';

		/**
		 * Array of LicenseSettings
		 *
		 * @var LicenseSettings|null $settings
		 */
		private $settings = null;

		/**
		 * HTML page for managing Licenses
		 *
		 * @var LicensePage $page
		 */
		private $page = null;

		/**
		 * AJAX handler for license handling
		 *
		 * @var AjaxHandler $ajax
		 */
		private $ajax = null; // phpcs:ignore

		/**
		 * Instance of the connection object to the license server
		 *
		 * @var LicenseServer|null $server
		 */
		private $server = null;
		/**
		 * Utilities class
		 *
		 * @var Utilities|null $utils
		 */
		private $utils = null;

		/**
		 * The SKU for the product license
		 *
		 * @var null|string $product_sku
		 */
		private $product_sku = null;

		/**
		 * Whether to log License specific (extra) debug information
		 *
		 * @var bool $log_debug
		 */
		private $log_debug = false;

		/**
		 * Configure the License class (load settings, etc)
		 *
		 * @param null|string          $product_sku - The licensed product's SKU
		 * @param LicenseSettings|null $settings - The settings for the license
		 * @param LicenseServer|null   $server - The license server connection class
		 * @param LicensePage|null     $page - The viewer for the licensing HTML
		 * @param Utilities|null       $utils - The Utilities class
		 * @param AjaxHandler|null     $ajax_handler - The AJAX handler class
		 *
		 * @throws Exceptions\InvalidSettingsKey - When a LicenseSetting doesn't exist
		 * @throws MissingServerURL - Raised when the URL to the license server isn't defined
		 * @throws ConfigDataNotFound - Raised if the configuration JSON blob is missing
		 * @throws BadOperation - Raised if somebody tries an invalid operation against a constant or settings class parameter
		 * @throws InvalidSettingsVersion - Raised when the version of the licensing code is unsupported
		 * @throws Exception - Default exception being raised
		 */
		public function __construct(
			?string $product_sku = null,
			?LicenseSettings $settings = null,
			?LicenseServer $server = null,
			?LicensePage $page = null,
			?Utilities $utils = null,
			?AjaxHandler $ajax_handler = null
		) {

			// Define the Utilities class
			if ( empty( $utils ) ) {
				$message = new Message();
				$utils   = new Utilities( $message );
			}

			$this->utils       = $utils;
			$this->product_sku = $product_sku;
			$this->text_domain = apply_filters( 'e20r_licensing_text_domain', $this->text_domain );

			if ( empty( $settings ) ) {
				try {
					$defaults = new Defaults();
					$settings = new LicenseSettings( $this->product_sku, $defaults, $this->utils );
				} catch ( Exceptions\InvalidSettingsKey | MissingServerURL | BadOperation | ConfigDataNotFound | InvalidSettingsVersion $e ) {
					$this->utils->log( 'Error: Invalid setting key used when instantiating the LicenseSettings() class: ' . esc_attr( $e->getMessage() ) );
					throw $e;
				}
			} else {
				$this->utils->log( 'Using supplied LicenseSettings class' );
			}

			// Save the LicenseSettings object
			$this->settings = $settings;

			if ( empty( $server ) ) {
				try {
					$server = new LicenseServer( $this->settings, $this->utils );
				} catch ( Exception $e ) {
					$this->utils->log( 'License Server configuration: ' . esc_attr( $e->getMessage() ) );
					throw $e;
				}
			} else {
				$this->utils->log( 'Using supplied LicenseServer class instance' );
			}

			// Save the LicenseServer object
			$this->server = $server;

			if ( empty( $page ) ) {
				try {
					$page = new LicensePage( $this->settings, $this->utils );
				} catch ( Exception $e ) {
					$this->utils->log( 'License Page: ' . esc_attr( $e->getMessage() ) );
					throw $e;
				}
			} else {
				$this->utils->log( 'Using supplied LicensePage class' );
			}

			// Save the LicensePage object
			$this->page      = $page;
			$this->log_debug = $this->settings->get( 'plugin_defaults' )->get( 'debug_logging' );

			if ( empty( $ajax_handler ) ) {
				try {
					$ajax_handler = new AjaxHandler( $product_sku, $this->settings, $this->server, $this->utils );
				} catch ( Exception $e ) {
					$this->utils->log( 'Warning: Not loading the AJAX handler. No SKU or Key found.' );
				}
			} else {
				$this->utils->log( 'Using supplied AjaxHandler class' );
			}

			$this->ajax = $ajax_handler;

			if ( $this->log_debug ) {
				$this->utils->log( 'Loaded the License class...' );
			}
		}

		/**
		 * Return the License class requested
		 *
		 * @param string $class_name - Name of the class to return the name of
		 *
		 * @return LicenseSettings|LicenseServer|LicensePage|License|null
		 */
		public function get_class( $class_name = 'license' ) {

			// Return error if the license class (name) isn't found
			if (
				! in_array(
					$class_name,
					array( 'license', 'settings', 'server', 'page', 'ajax' ),
					true
				)
			) {
				return null;
			}

			return 'license' === $class_name ? $this : $this->{$class_name};
		}

		/**
		 * Set the SKU and reload setting(s)
		 *
		 * @param string $sku - The product SKU for the licensed bit of code/product
		 *
		 * @throws Exceptions\InvalidSettingsKey - Raised if the key specified doesn't exist for the settings class
		 * @throws MissingServerURL - Raised if the URL to the License server is missing/wrong
		 * @throws BadOperation - Raised if a constant operation isn't defined
		 * @throws ConfigDataNotFound - Raised if the cofiguration data JSON is missing
		 * @throws InvalidSettingsVersion - Raised if we're attemting to use an unsupported Licensing plugin or plugin version
		 */
		public function set_sku( $sku ) {
			$this->product_sku = $sku;

			// Update the SKU dependent classes/objects
			$this->settings = null;
			$defaults       = new Defaults();
			try {
				$this->settings = new LicenseSettings( $this->product_sku, $defaults, $this->utils );
			} catch ( Exceptions\InvalidSettingsKey | MissingServerURL | BadOperation | ConfigDataNotFound | InvalidSettingsVersion $e ) {
				$this->utils->log( $e->getMessage() );
				throw $e;
			}
		}

		/**
		 * Load action hooks for the E20R License Utilities module
		 */
		public function load_hooks() {
			add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ), 10 );

			if ( ! empty( $this->ajax ) ) {
				add_action(
					'wp_ajax_e20r_license_verify',
					array( $this->ajax, 'ajax_handler_verify_license' ),
					10
				);
			}
		}

		/**
		 * Load License page specific scripts and style(s)
		 */
		public function enqueue() {

			wp_enqueue_style(
				'00-e20r-utilities',
				plugins_url( 'css/e20r-utilities-licensing.css', __FILE__ ),
				array(),
				$this->settings->get( 'plugin_defaults' )->get( 'version' )
			);

			$this->utils->log( 'Loading the License javascript?' );

			if ( $this->utils::is_admin() ) {

				wp_enqueue_script(
					'00-e20r-utilities',
					plugins_url( '../Licensing/javascript/e20r-Licensing.js', __FILE__ ),
					array( 'jquery' ),
					$this->settings->get( 'plugin_defaults' )->get( 'version' ),
					true
				);

				$this->utils->log( '00-e20r-utilities script(s) loaded' );
			}
		}

		/**
		 * Return the value of ssl_verify
		 *
		 * @return bool
		 */
		public function get_ssl_verify() : bool {
			return (bool) $this->settings->get( 'ssl_verify' );
		}

		/**
		 * Return the value of new_version
		 *
		 * @return bool|null
		 */
		public function is_new_version(): ?bool {
			return $this->settings->get( 'new_version' );
		}

		/**
		 * Is the specified product licensed for use/updates (check against cached value, if possible)
		 * The Cache is configured to time out every 24 hours (or so)
		 *
		 * @param string $product_sku Name of the product/component to test the license for (aka the SKU)
		 * @param bool   $force       Whether to force the plugin to connect with the license server, regardless of cache value(s)
		 *
		 * @return bool
		 * @test E20R\Tests\WPUnit\License_WPUnitTest::test_is_licensed()
		 */
		public function is_licensed( $product_sku = null, $force = false ) : bool {

			if ( empty( $product_sku ) ) {
				if ( $this->log_debug ) {
					$this->utils->log( 'No Product Stub supplied!' );
				}
				return false;
			}

			try {
				if (
					$this->utils->is_license_server(
						$this->settings->get( 'plugin_defaults' )->get( 'server_url' ),
						$this->settings->get( 'plugin_defaults' )
					)
				) {
					$this->utils->log( 'Running on the server issuing licenses. Skipping check (treating as licensed)' );
					return true;
				}
			} catch ( BadOperation | InvalidSettingsKey $e ) {
				$this->utils->log( $e->getMessage() );
				return false;
			}

			$this->utils->log( "Checking license for {$product_sku}" );
			$is_licensed = $this->server->status( $product_sku, $force );
			try {
				return $this->is_active( $product_sku, $is_licensed );
			} catch ( InvalidSettingsKey $e ) {
				$this->utils->log( 'Invalid setting found: ' . $e->getMessage() );
				return false;
			}
		}

		/**
		 * Check if a licensed product has an active license
		 *
		 * @param string $product_sku - The SKU for the product that is licensed
		 * @param bool   $is_licensed - Is the product/SKU licensed (as we test for whether the license is active still)
		 *
		 * @return bool
		 * @throws InvalidSettingsKey - Raised when the provided key isn't a valid setting in the LicenseSettings class
		 */
		public function is_active( $product_sku, $is_licensed = false ) : bool {

			if ( 'e20r_default_license' === $product_sku ) {
				$this->utils->log( 'Processing the Default (non-existent) license. Returning false' );
				return false;
			}

			$new_version = $this->is_new_version();
			$this->utils->log( 'Use new or old License logic? ' . ( $new_version ? 'New' : 'Old' ) );

			if ( true === $new_version ) {
				$this->utils->log( 'Status of license under new Licensing model... Is licensed? ' . ( $is_licensed ? 'True' : 'False' ) );

				$the_key = $this->settings->get( 'the_key' );
				$status  = $this->settings->get( 'status' );

				$is_active = (
					! empty( $the_key ) &&
					! empty( $status ) &&
					'active' === $status
				);
				$this->utils->log( "Active: '{$is_active}', key: {$the_key}, status: {$status}" );
			} elseif ( false === $new_version ) {

				$the_key = $this->settings->get( 'key' );
				$status  = $this->settings->get( 'status' );
				$domain  = $this->settings->get( 'domain' );

				$is_active = (
					! empty( $the_key ) &&
					! empty( $status ) &&
					'active' === $status && isset( $_SERVER['SERVER_NAME'] ) &&
					filter_var( wp_unslash( $_SERVER['SERVER_NAME'] ) ) === $domain
				);
				$this->utils->log( "Active: '{$is_active}', key: {$the_key}, status: {$status}, domain: {$domain}" );
			} else {
				$this->utils->log( 'Neither old nor new Licensing plugin selected!!!' );
				return false;
			}

			$this->utils->log( "License status for {$product_sku}: " . ( $is_active ? 'Active' : 'Inactive' ) );
			return $is_licensed && $is_active;
		}

		/**
		 * Is the license scheduled to expire within the specified interval(s)
		 *
		 * @param string $product_sku - The SKU for the licensed product/software
		 *
		 * @return int|bool
		 */
		public function is_expiring( $product_sku ) {

			try {
				$settings = $this->settings->get_settings( $product_sku );
			} catch ( Exception $e ) {
				$this->utils->log( "Warning: Cannot find license for ${product_sku}: " . $e->getMessage() );
				return true;
			}

			if ( $this->log_debug ) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r
				$this->utils->log( 'Received settings for expiration check: ' . print_r( $settings, true ) );
			}

			if ( $this->is_new_version() && isset( $settings['expire'] ) ) {
				$expires = (int) $settings['expire'];
			} elseif ( isset( $settings['expires'] ) ) {
				$expires = (int) $settings['expires'];
			} else {
				// phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp.Requested
				$expires = (int) time();
			}

			if ( empty( $expires ) ) {

				if ( $this->log_debug ) {
					$this->utils->log( "NOTICE: The {$product_sku} license does not expire" );
				}

				return false;
			}

			if ( $this->log_debug ) {
				$this->utils->log(
					"Expiration date for {$product_sku}: " .
					wp_date( 'Y-m-d H:i:s', $expires )
				);
			}

			$expiration_interval     = apply_filters( 'e20r_license_expiration_warning_interval_days', 30 );
			$calculated_warning_time = strtotime( "+ {$expiration_interval} day", time() );
			$diff                    = $expires - time();

			if ( $this->log_debug ) {
				$this->utils->log( "{$product_sku} scheduled to expire on {$expires} vs {$calculated_warning_time}" );
			}

			if ( $expires <= $calculated_warning_time && $expires >= time() && $diff > 0 ) {
				return true;
			} elseif ( $expires <= time() && $diff <= 0 ) {
				return - 1;
			}

			return false;
		}

		/**
		 * Activate the license key on the remote server
		 *
		 * @param string             $product_sku - The Product SKU in WooCommerce store from whence the product was licensed
		 * @param LicenseServer|null $server - The License server connection class
		 * @return array|bool
		 *
		 * @throws ServerConnectionError - Raised when the license Server isn't available
		 * @throws Exceptions\InvalidSettingsKey - Raised if the specified setting doesn't exist in the settings class
		 * @throws Exceptions\BadOperation - Raised when attempting an unsupported action against a constant
		 * @throws Exceptions\InvalidSettingsVersion - Raised when an unsupported version of the settings class is used
		 * @throws MissingServerURL - Raised if the License server URL is missing
		 * @throws ConfigDataNotFound | ReflectionException - Raised when the config data is missing
		 *
		 * @since 1.8.4 - BUG FIX: Didn't save the license settings
		 * @since 3.2 - BUG FIX: Use LicenseServer class as part of status and throw exceptions when things go sideways
		 * @since 6.0 - ENHANCEMENT: Updated to support new licensing classes and unit/integration test framework
		 */
		public function activate( string $product_sku, $server = null ) {
			$state           = null;
			$plugin_defaults = $this->settings->get( 'plugin_defaults' );
			$new_version     = $this->is_new_version();

			if ( ! $new_version ) {
				$msg = esc_attr__( 'Error: Unable to connect to license server. Please upgrade the E20R Utilities plugin!', '00-e20r-utilities' );
				$this->utils->add_message( $msg, 'error', 'backend' );
				throw new ServerConnectionError( $msg );
			}

			$this->utils->log( 'Using new license server plugin for activation...' );

			if ( $this->log_debug ) {
				$this->utils->log( "Attempting to activate {$product_sku} on remote server" );
			}

			if ( empty( $this->settings ) ) {
				try {
					$this->settings = new LicenseSettings( $product_sku, $plugin_defaults, $this->utils );
				} catch (
					Exceptions\InvalidSettingsKey |
				MissingServerURL |
				BadOperation |
				ConfigDataNotFound |
				InvalidSettingsVersion $e
				) {
					$this->utils->add_message( $e->getMessage(), 'error', 'backend' );
					throw $e;
				}
			}

			$api_params = array(
				'action'      => 'license_key_activate',
				'store_code'  => $plugin_defaults->get( 'store_code' ),
				'sku'         => $this->settings->get( 'product_sku' ),
				'license_key' => ( true === $new_version ? $this->settings->get( 'the_key' ) : $this->settings->get( 'key' ) ),
				'domain'      => $this->settings->get( 'domain_name' ),
			);

			if ( null !== $server ) {
				$this->server = $server;
			}

			// Send query to the license manager server
			$decoded = $this->server->send( $api_params );

			if ( empty( $decoded ) ) {

				$msg = esc_attr__( 'Error transmitting to the remote Licensing server', '00-e20r-utilities' );
				$this->utils->add_message( $msg, 'error', 'backend' );

				if ( $this->log_debug ) {
					$this->utils->log( $msg );
				}

				return array(
					'status'   => $plugin_defaults->constant( 'E20R_LICENSE_BLOCKED' ),
					'settings' => null,
				);
			}

			if ( $this->log_debug ) {
				$this->utils->log( 'Processing payload from new Licensing server...' );
			}

			if ( true === $decoded->error ) {

				$this->utils->log( 'New Licensing server returned error...' );
				$state = $plugin_defaults->constant( 'E20R_LICENSE_ERROR' );

				// translators: The substitution values come from the error object
				$msg = esc_attr__( 'Activation error: %1$s -> %2$s', '00-e20r-utilities' );

				foreach ( (array) $decoded->errors as $error_key => $error_message ) {
					$msg = sprintf( $msg, $error_key, array_pop( $error_message ) );
					$this->utils->add_message(
						$msg,
						'error',
						'backend'
					);
					$this->utils->log( $msg );
				}

				try {
					$this->settings->update();
				} catch ( Exception $exception ) {
					$this->utils->add_message(
						$exception->getMessage(),
						'error',
						'backend'
					);
					$state = $plugin_defaults->constant( 'E20R_LICENSE_ERROR' );
				}

				return array(
					'status'   => $state,
					'settings' => $this->settings,
				);
			}

			if ( isset( $decoded->status ) && 200 === (int) $decoded->status ) {

				// $settings + $decoded->data == Merged settings

				if ( isset( $settings[ $product_sku ] ) && ! empty( $settings[ $product_sku ] ) ) {
					$existing_settings = $settings[ $product_sku ];
				} elseif ( ! isset( $settings[ $product_sku ] ) && ! empty( $settings ) ) {
					$existing_settings = $settings;
				} else {
					$existing_settings = $this->settings->defaults();
				}
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r
				$this->utils->log( 'FIXME: Need to do something with existing settings: ' . print_r( $existing_settings, true ) );
				$new_settings = (array) $decoded->data;
				try {
					$this->settings->merge( $new_settings );
				} catch ( ErrorSavingSettings $e ) {
					$this->utils->add_message(
						sprintf(
						// translators: %1$s - Error message from the LicenseSettings::merge() operation
							esc_attr__( 'Error merging settings: %1$s', '00-e20r-utilities' ),
							$e->getMessage()
						),
						'error',
						'background'
					);
					return array(
						'status'   => $plugin_defaults->constant( 'E20R_LICENSE_BLOCKED' ),
						'settings' => $this->settings,
					);
				}

				$this->utils->add_message( $decoded->message, 'notice', 'backend' );
				$state = $plugin_defaults->constant( 'E20R_LICENSE_DOMAIN_ACTIVE' );
			}

			$this->settings->save();

			return array(
				'status'   => $state,
				'settings' => $this->settings,
			);
		}

		/**
		 * Deactivate the specified license (product/license key)
		 *
		 * @param string     $product_sku - The SKU for the licensed product/software
		 * @param array|null $settings - List of settings to use
		 *
		 * @return bool
		 */
		public function deactivate( $product_sku, $settings = null ) : bool {

			$license_key = $$this->settings->get( 'key' );

			if ( empty( $license_key ) ) {
				if ( $this->log_debug ) {
					$this->utils->log( 'No license key, so nothing to deactivate' );
				}

				return false;
			}

			if ( $this->log_debug ) {
				$this->utils->log( "Attempting to deactivate {$product_sku} on remote server" );
			}

			if ( $this->settings->get( 'plugin_defaults' )->get( 'debug_logging' ) && $this->is_new_version() ) {
				$this->utils->log( 'Using new license server plugin for deactivation...' );
			}

			if ( ! $this->is_new_version() ) {
				$api_params = array(
					'slm_action'        => 'slm_deactivate',
					'license_key'       => $settings['key'],
					'secret_key'        => $this->settings->get( 'plugin_defaults' )->constant( 'E20R_LICENSE_SECRET_KEY' ),
					'registered_domain' => $_SERVER['SERVER_NAME'] ?? 'localhost.local', // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized, WordPress.Security.ValidatedSanitizedInput.MissingUnslash
					'status'            => 'pending',
				);
			} else {

				if ( ! isset( $settings['activation_id'] ) ) {
					$this->utils->log( 'Unable to deactivate since activation_id data is missing!' );
					$this->utils->log( 'Just clear the license...' );

					if ( false === $this->settings->update( $product_sku, $settings ) ) {
						if ( $this->log_debug ) {
							$this->utils->log( "Unable to save settings (after removal) for {$product_sku}" );
						}
					}

					return true;
				}

				$api_params = array(
					'action'        => 'license_key_deactivate',
					'store_code'    => $this->settings->get( 'plugin_defaults' )->get( 'store_code' ),
					'sku'           => $this->settings->get( 'product_sku' ),
					'license_key'   => $this->settings->get( 'license_key' ),
					'domain'        => filter_var( wp_unslash( $_SERVER['SERVER_NAME'] ), FILTER_SANITIZE_URL ) ?? 'localhost',
					'activation_id' => $this->settings->get( 'activation_id' ),
				);
			}

			if ( $this->is_new_version() ) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r
				$this->utils->log( 'Sending to server: ' . print_r( $api_params, true ) );
			}

			$decoded = $this->server->send( $api_params );

			if ( $this->is_new_version() ) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r
				$this->utils->log( 'Decoded: ' . print_r( $decoded, true ) );
			}

			if ( false === $decoded ) {
				return $decoded;
			}

			if ( ! $this->is_new_version() ) {
				/**
				 * Check if the result is the 'Already inactive' ( status: 80 )
				 */
				if ( 'error' === $decoded->result && (
						isset( $decoded->error_code ) &&
						80 === $decoded->error_code &&
						1 === preg_match( '/domain is already inactive/i', $decoded->message )
					) ) {

					// Then override the status.
					$decoded->result = 'success';
				}

				if ( 'success' !== $decoded->result ) {
					if ( $this->log_debug ) {
						$this->utils->log( 'Error deactivating the license!' );
					}

					return false;
				}

				if ( $this->log_debug ) {
					$this->utils->log( "Removing license {$product_sku}..." );
				}

				if ( false === $this->settings->update( $product_sku, $settings ) ) {
					if ( $this->log_debug ) {
						$this->utils->log( "Unable to save settings (after removal) for {$product_sku}" );
					}
				}

				return true;
			} elseif ( isset( $decoded->status ) ) {

				if ( isset( $decoded->status ) && 500 === (int) $decoded->status ) {
					// translators: Error message supplied from decoded request object
					$error_message = esc_attr__( 'Deactivation error: %s', '00-e20r-utilities' );

					if ( isset( $decoded->errors ) ) {

						$this->utils->log( 'Decoding error messages from the License server...' );
						// translators: Error message supplied from decoded request object
						$error_string = esc_attr__( '%1$s -> %2$s', '00-e20r-utilities' );

						foreach ( (array) $decoded->errors as $error_key => $error_info ) {
							$info = array_pop( $error_info );
							$this->utils->log(
								sprintf(
									$error_message,
									sprintf( $error_string, $error_key, $info )
								)
							);
						}
					}
				}

				/* phpcs:ignore Squiz.PHP.CommentedOutCode.Found
				if ( isset( $decoded->status ) && 200 === (int) $decoded->status ) {
					$License_info = isset( $decoded->data ) ?
						$decoded->data :
						LicenseSettings::defaults( $product );
				}
				*/

				return true;
			}

			return false;

		}

		/**
		 * Get the license page URL for the local admin/options page
		 *
		 * @param string $license_stub - the SKU/stub for the license
		 *
		 * @return string
		 */
		public function get_license_page_url( $license_stub ) : string {

			return esc_url_raw(
				add_query_arg(
					array(
						'page'         => 'e20r-Licensing',
						'license_stub' => rawurlencode( $license_stub ),
					),
					admin_url( 'options-general.php' )
				)
			);
		}

		/**
		 * Register all License settings
		 *
		 * @since 1.5 - BUG FIX: Incorrect namespace used in register_setting(), add_settings_section() and
		 *        add_settings_field() functions
		 * @since 1.6 - BUG FIX: Used wrong label for new licenses
		 */
		public function register() {

			$license_list = array();

			register_setting(
				'e20r_license_settings', // group, used for settings_fields()
				'e20r_license_settings',  // option name, used as key in database
				array( 'E20R\Utilities\License\LicenseSettings::validate' )
			);

			add_settings_section(
				'e20r_licensing_section',
				__( 'Configure Licenses', '00-e20r-utilities' ),
				'E20R\Utilities\License\LicensePage::show_section',
				'e20r-Licensing'
			);

			// phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores
			$settings        = apply_filters(
				'e20r_license_add_new_licenses',
				$this->settings->all_settings(),
				array()
			);
			$license_counter = 0;

			if ( $this->log_debug ) {
				$this->utils->log( 'Found ' . count( $settings ) . ' potential licenses' );
			}

			foreach ( $settings as $product_sku => $license ) {

				if ( $this->log_debug ) {
					$this->utils->log( "Processing license info for ${product_sku}" );
				}

				// Skip and clean up.
				if ( isset( $license['key'] ) && empty( $license['key'] ) ) {

					unset( $settings[ $product_sku ] );
					update_option( 'e20r_license_settings', $settings, 'yes' );

					if ( $this->log_debug ) {
						// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r
						$this->utils->log( "Skipping {$product_sku} with settings (doesn't have a product SKU): " . print_r( $license, true ) );
					}
					continue;
				}

				if ( $this->log_debug ) {
					$this->utils->log( "Loading settings fields for '{$product_sku}'?" );
				}

				if (
					! in_array( $product_sku, array( 'example_gateway_addon', 'new_licenses' ), true ) &&
					isset( $license['key'] ) &&
					( 'e20r_default_license' !== $license['key'] && 1 <= count( $settings ) ) &&
					! empty( $license['key'] )
				) {

					if ( $this->log_debug ) {
						$this->utils->log( "Previously activated license: {$product_sku}: adding {$license['fulltext_name']} fields" );
						// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r
						$this->utils->log( "Existing settings for {$product_sku}: " . print_r( $license, true ) );
					}

					if ( empty( $license['status'] ) ) {
						$license['status'] = 'inactive';
					}

					$is_active = ( 'active' === $license['status'] );

					if ( 'active' === $license['status'] ) {
						$is_licensed = true;
						$key         = ( ! $this->is_new_version() && isset( $license['license_key'] ) ?
							$license['license_key'] :
							( isset( $license['product_sku'] ) ? $license['product_sku'] : null )

						);

						$is_active = $this->is_active(
							$key,
							$is_licensed
						);
						$this->utils->log( "The {$key} license is " . ( $is_active ? 'Active' : 'Inactive' ) );
					}

					$status_class = 'e20r-license-inactive';

					if ( 'active' === $license['status'] ) {
						$status_class = 'e20r-license-active';
					}

					$license_name = sprintf(
						'<span class="%2$s">%1$s (%3$s)</span>',
						$license['fulltext_name'],
						$status_class,
						ucfirst( $license['status'] )
					);

					$expiration_ts = 0;

					if ( $this->is_new_version() ) {

						if ( ! empty( $license['expire'] ) ) {
							$expiration_ts = (int) $license['expire'];
						} else {
							// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r
							$this->utils->log( 'License info (new) w/o expiration info: ' . print_r( $license, true ) );
							$this->utils->add_message(
								sprintf(
								// translators: Name of license is supplied from the plugin being licensed
									__(
										'Error: No expiration info found for %s. Using default value (expired)',
										// phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralDomain
										'00-e20r-utilities'
									),
									$license_name
								),
								'warning',
								'backend'
							);
						}
					} else {
						if ( ! empty( $license['expires'] ) ) {
							$expiration_ts = (int) strtotime( $license['expires'] );
						} else {
							// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r
							$this->utils->log( 'License info (old) w/o expiration info: ' . print_r( $license, true ) );
							$this->utils->add_message(
								sprintf(
								// translators: Name of license is translated in the calling plugin
									__(
										'Warning: No expiration info found for %s. Using default value (expired)',
										// phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralDomain
										'00-e20r-utilities'
									),
									$license_name
								),
								'warning',
								'backend'
							);
						}
					}

					add_settings_field(
						"{$license['key']}",
						$license_name,
						'E20R\Utilities\License\LicensePage::show_input',
						'e20r-Licensing',
						'e20r_licensing_section',
						array(
							'index'            => $license_counter,
							'label_for'        => isset( $license['key'] ) ?
								$license['key'] :
								__( 'Unknown', '00-e20r-utilities' ),
							'product'          => $product_sku,
							'option_name'      => 'e20r_license_settings',
							'fulltext_name'    => isset( $license['fulltext_name'] ) ?
								$license['fulltext_name'] :
								__( 'Unknown', '00-e20r-utilities' ),
							'name'             => 'license_key',
							'input_type'       => 'password',
							'is_active'        => $is_active,
							'expiration_ts'    => $expiration_ts,
							'has_subscription' => ( isset( $license['subscription_status'] ) && 'active' === $license['subscription_status'] ),
							'value'            => ( $this->is_new_version() && isset( $license['the_key'] ) ? $license['the_key'] : isset( $license['key'] ) ) ? $license['key'] : null,
							'email_field'      => 'license_email',
							'product_sku'      => $this->is_new_version() && isset( $license['product_sku'] ) ?
								$license['product_sku'] :
								null,
							'email_value'      => isset( $license['email'] ) ? $license['email'] : null,
							'placeholder'      => esc_attr__( 'Paste the purchased key here', '00-e20r-utilities' ),
						)
					);

					$license_list[] = $product_sku;
					$license_counter ++;
				}
			}

			// To add new/previously existing settings
			$new_licenses = isset( $settings['new_licenses'] ) && ! empty( $settings['new_licenses'] ) ?
				$settings['new_licenses'] :
				// phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores
				apply_filters( 'e20r_license_add_new_licenses', array() );

			if ( $this->log_debug ) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r
				$this->utils->log( 'New license info found: ' . print_r( $new_licenses, true ) );
			}
			/* phpcs:ignore Squiz.PHP.CommentedOutCode.Found
			if ( empty( $new_licenses ) ) {
				$new_licenses = apply_filters( 'e20r-license-add-new-licenses', array() );
			}
			*/
			foreach ( $new_licenses as $new_product_sku => $new ) {

				if ( $this->log_debug ) {
					$this->utils->log( "Processing new license fields for new sku: {$new['new_product']}" );
				}

				// Skip if we've got this one in the list of licenses already.

				if ( ! in_array( $new['new_product'], $license_list, true ) && 'example_gateway_addon' !== $new_product_sku ) {
					if ( $this->log_debug ) {
						$this->utils->log( "Adding  license fields for new sku {$new['new_product']} (one of " . count( $new_licenses ) . ' unlicensed add-ons)' );
					}

					add_settings_field(
						"e20r_license_new_{$new_product_sku}",
						sprintf(
						// translators: The settings from the plugin being licensed will contain its name
							__( 'Add %s license', '00-e20r-utilities' ),
							$new['fulltext_name']
						),
						'E20R\Utilities\License\LicensePage::show_input',
						'e20r-Licensing',
						'e20r_licensing_section',
						array(
							'index'         => $license_counter,
							'label_for'     => $new['new_product'],
							'fulltext_name' => $new['fulltext_name'],
							'option_name'   => 'e20r_license_settings',
							'new_product'   => $new['new_product'],
							'name'          => 'new_license',
							'input_type'    => 'text',
							'value'         => null,
							'is_active'     => false,
							'email_field'   => 'new_email',
							'product_sku'   => $new['product_sku'],
							'email_value'   => null,
							'placeholder'   => $new['placeholder'],
							'classes'       => sprintf( 'e20r-Licensing-new-column e20r-Licensing-column-%1$d', $license_counter ),
						)
					);

					$license_counter ++;
					if ( $this->log_debug ) {
						$this->utils->log( "New license field(s) added for sku: {$new_product_sku}" );
					}
				}
			}
		}
	}
}
