<?php
/**
 * Copyright (c) 2017-2021 - Eighty / 20 Results by Wicked Strong Chicks.
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
 *
 */

namespace E20R\Utilities\Licensing;

use E20R\Utilities\Utilities;
use LicenseKeys\Utility\Api;
use LicenseKeys\Utility\Client;
use LicenseKeys\Utility\LicenseRequest;

// Deny direct access to the file
if ( ! defined( 'ABSPATH' ) && function_exists( 'wp_die' ) ) {
	wp_die( 'Cannot access file directly' );
}

if ( ! class_exists( '\E20R\Utilities\Licensing\Licensing' ) ) {

	if ( ! defined( 'E20R_LICENSING_DEBUG' ) ) {
		define( 'E20R_LICENSING_DEBUG', false );
	}
	if ( ! defined( 'E20R_LICENSING_VERSION' ) ) {
		define( 'E20R_LICENSING_VERSION', '3.1' );
	}

	if ( ! defined( 'E20R_LICENSE_SERVER_URL' ) ) {
		define( 'E20R_LICENSE_SERVER_URL', 'https://eighty20results.com/' );
	}

	/**
	 * Class Licensing
	 * @package E20R\Utilities\Licensing
	 */
	class Licensing {

		/**
		 * License cache keys
		 */
		const CACHE_KEY   = 'active_licenses';
		const CACHE_GROUP = 'e20r_licensing';

		/**
		 * License status constants
		 */
		const E20R_LICENSE_MAX_DOMAINS   = 2048;
		const E20R_LICENSE_REGISTERED    = 1024;
		const E20R_LICENSE_DOMAIN_ACTIVE = 512;
		const E20R_LICENSE_ERROR         = 256;


		/**
		 * I18N domain name (translation)
		 *
		 * @var string|null
		 */
		private $text_domain = '00-e20r-utilities';

		/**
		 * New or old Licensing plugin in store
		 *
		 * @var bool
		 */
		private $new_version = false;

		/**
		 * Use SSL certificate validation when checking license
		 *
		 * @var bool
		 */
		private $ssl_verify = true;

		/**
		 * Array of LicenseSettings
		 *
		 * @var LicenseSettings $settings
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
		private $ajax = null;

		/**
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
		 * Configure the Licensing class (actions and settings)
		 * Licensing constructor.
		 */
		public function __construct( $product_sku = null ) {

			$this->utils       = Utilities::get_instance();
			$this->product_sku = $product_sku;

			if ( defined( 'E20R_LICENSING_DEBUG' ) && true === E20R_LICENSING_DEBUG ) {
				$this->utils->log( 'Loading the Licensing class...' );
			}

			// phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores
			$this->text_domain = apply_filters( 'e20r_licensing_text_domain', $this->text_domain );
			$this->ssl_verify  = $this->utils::is_local_server();

			if ( defined( 'E20R_LICENSING_DEBUG' ) && true === E20R_LICENSING_DEBUG ) {
				$this->utils->log( 'Do we verify the SSL certificate (no if local = home_url())? ' . ( $this->ssl_verify ? 'Yes' : 'No' ) );
			}

			$this->settings = new LicenseSettings( $this->product_sku );
			$this->server   = new LicenseServer( $this->new_version, $this->ssl_verify );
			$this->page     = new LicensePage();
			$this->ajax     = new AjaxHandler();
		}

		/**
		 * Return the Licensing class requested
		 *
		 * @param string $class_name
		 *
		 * @return LicenseSettings|LicenseServer|LicensePage|Licensing|null
		 */
		public function get_class( $class_name = 'licensing' ) {

			// Return error if the license class (name) isn't found
			if (
				! in_array(
					$class_name,
					array( 'licensing', 'settings', 'server', 'page', 'ajax' ),
					true
				)
			) {
				return null;
			}

			return 'licensing' === $class_name ? $this : $this->{$class_name};
		}

		/**
		 * Set the SKU and reload setting(s)
		 *
		 * @param string $sku
		 */
		public function set_sku( $sku ) {
			$this->product_sku = $sku;

			// Update the SKU dependent classes/objects
			$this->settings = null;
			$this->settings = new LicenseSettings( $this->product_sku );
		}

		/**
		 * Load action hooks for the E20R Licensing utilities module
		 */
		public function load_hooks() {
			add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ), 10 );
			add_action(
				'wp_ajax_e20r_license_verify',
				array( $this->ajax, 'ajax_handler_verify_license' ),
				10
			);
		}

		/**
		 * Load License page specific scripts and style(s)
		 */
		public function enqueue() {

			wp_enqueue_style(
				'e20r-utilities-licensing',
				plugins_url( 'css/e20r-utilities-licensing.css', __FILE__ ),
				null,
				E20R_LICENSING_VERSION
			);

			$this->utils->log( 'Loading the License javascript?' );

			if ( $this->utils::is_admin() ) {

				wp_enqueue_script(
					'e20r-licensing',
					plugins_url( '../licensing/javascript/e20r-licensing.js', __FILE__ ),
					'jquery',
					E20R_LICENSING_VERSION,
					true
				);

				$this->utils->log( 'e20r-licensing script(s) loaded' );
			}
		}

		/**
		 * Return the value of ssl_verify
		 *
		 * @return bool
		 */
		public function get_ssl_verify() {
			return (bool) $this->settings->get( 'ssl_verify' );
		}

		/**
		 * Return the value of new_version
		 *
		 * @return bool
		 */
		public function is_new_version() {
			return (bool) $this->settings->get( 'new_version' );
		}

		/**
		 * Is the specified product licensed for use/updates (check against cached value, if possible)
		 * The Ccache is configured to time out every 24 hours (or so)
		 *
		 * @param string $product_sku Name of the product/component to test the license for (aka the SKU)
		 * @param bool   $force       Whether to force the plugin to connect with the license server, regardless of cache value(s)
		 *
		 * @return bool
		 */
		public function is_licensed( $product_sku = null, $force = false ) {

			if ( empty( $product_sku ) ) {
				if ( defined( 'E20R_LICENSING_DEBUG' ) && true === E20R_LICENSING_DEBUG ) {
					$this->utils->log( 'No Product Stub supplied!' );
				}

				return false;
			}

			if ( $this->utils::is_local_server() ) {
				$this->utils->log( 'Running on the server issuing licenses. Skipping check (treating as licensed)' );

				return true;
			}

			// Make sure the SKU/product stub is upper-cased
			// $product_sku = strtoupper( $product_sku );

			if ( defined( 'E20R_LICENSING_DEBUG' ) && true === E20R_LICENSING_DEBUG ) {
				$this->utils->log( "Checking license for {$product_sku}" );
			}

			$excluded = apply_filters(
				'e20r_licensing_excluded',
				array(
					'e20r_default_license',
					'example_gateway_addon',
					'new_licenses',
				)
			);

			$license_settings = $this->settings->all_settings();

			if ( ! isset( $license_settings[ $product_sku ] ) ) {

				if ( defined( 'E20R_LICENSING_DEBUG' ) && true === E20R_LICENSING_DEBUG ) {
					$this->utils->log( "Creating default license settings for {$product_sku}" );
				}

				if ( ! is_array( $license_settings ) ) {
					if ( defined( 'E20R_LICENSING_DEBUG' ) && true === E20R_LICENSING_DEBUG ) {
						$this->utils->log( 'Existing license settings need to be initialized' );
					}
					$license_settings                 = array();
					$license_settings[ $product_sku ] = array();
				}

				if ( defined( 'E20R_LICENSING_DEBUG' ) && true === E20R_LICENSING_DEBUG ) {
					$this->utils->log( "Adding {$product_sku} license settings" );
				}

				$license_settings[ $product_sku ] = $this->settings->defaults( $product_sku );
			}

			$l_settings  = $this->settings->all_settings();
			$is_licensed = $this->server->status( $product_sku, $l_settings, $force );

			/* phpcs:ignore Squiz.PHP.CommentedOutCode.Found
			$license_settings = Cache::get( self::CACHE_KEY, self::CACHE_GROUP )

			if ( ! in_array( $product_sku, $excluded ) && ( null === $license_settings || ( true === $force ) ) && false === $is_licensed ) {
				if ( E20R_LICENSING_DEBUG && $force ) {
					$this->utils->log( "Ignoring cached license status for {$product_sku}" );
				}

				// Get new/existing settings
				$license_settings = LicenseSettings::get_settings();

				if ( ! isset( $license_settings[ $product_sku ] ) ) {
					$license_settings[ $product_sku ] = array();
				}

				$license_settings[ $product_sku ] = isset( $license_settings[ $product_sku ] ) ?
					$license_settings[ $product_sku ] :
					LicenseSettings::defaults( $product_sku );

				if ( defined( 'E20R_LICENSING_DEBUG' ) && true === E20R_LICENSING_DEBUG ) {
					$this->utils->log( "Using license settings for {$product_sku}: " . print_r( $license_settings[ $product_sku ], true ) );
				}
				// Update the local cache for the license
				Cache::set( self::CACHE_KEY, $license_settings, DAY_IN_SECONDS, self::CACHE_GROUP );
			}
			*/
			if ( defined( 'E20R_LICENSING_DEBUG' ) && true === E20R_LICENSING_DEBUG ) {
				$this->utils->log( "Found license settings for {$product_sku}? " . ( ! empty( $l_settings ) ? 'Yes' : 'No' ) );
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r
				$this->utils->log( 'Settings are: ' . print_r( $l_settings, true ) );
			}

			return $this->is_active( $product_sku, $l_settings, $is_licensed );
		}

		/**
		 * Check if a licensed product has an active license
		 *
		 * @param string $product_sku
		 * @param array  $license_settings
		 * @param bool   $is_licensed
		 *
		 * @return bool
		 */
		public function is_active( $product_sku, $license_settings, $is_licensed = false ) {

			$is_active = false;

			if ( 'e20r_default_license' === $product_sku ) {
				$this->utils->log( 'Processing the Default (non-existent) license. Returning false' );
				return false;
			}

			if ( defined( 'E20R_LICENSING_DEBUG' ) && true === E20R_LICENSING_DEBUG ) {
				$this->utils->log( 'New or old licensing plugin? ' . ( $this->is_new_version() ? 'New' : 'Old' ) );
			}

			if ( empty( $license_settings ) ) {
				if ( defined( 'E20R_LICENSING_DEBUG' ) && true === E20R_LICENSING_DEBUG ) {
					$this->utils->log( "Didn't get settings from caller so have to load them" );
				}
				$license_settings = $this->settings->all_settings();
			}

			if ( isset( $license_settings[ $product_sku ] ) ) {
				$this->utils->log( 'SKU specific settings only!' );
				$l_settings = $license_settings[ $product_sku ];
			} else {
				$this->utils->log( "Have to load the settings for {$product_sku} directly..." );
				$l_settings = $license_settings;
			}

			if ( true === $this->is_new_version() ) {

				if ( defined( 'E20R_LICENSING_DEBUG' ) && true === E20R_LICENSING_DEBUG ) {
					$this->utils->log( 'Status of license under new licensing plugin... Is licensed? ' . ( $is_licensed ? 'True' : 'False' ) );
				}

				$is_active = (
					! empty( $l_settings['the_key'] ) &&
					! empty( $l_settings['status'] ) &&
					'active' === $l_settings['status'] &&
					true === $is_licensed
				);
			} elseif ( false === $this->is_new_version() ) {

				if ( defined( 'E20R_LICENSING_DEBUG' ) && true === E20R_LICENSING_DEBUG ) {
					$this->utils->log( 'Status of license under old licensing plugin... Is licensed? ' . ( $is_licensed ? 'True' : 'False' ) );
				}

				$is_active = (
					! empty( $l_settings['key'] ) &&
					! empty( $l_settings['status'] ) &&
					'active' === $l_settings['status'] &&
					$l_settings['domain'] === $_SERVER['SERVER_NAME'] &&
					true === $is_licensed
				);
			} else {
				$this->utils->log( 'Neither old nor new licensing plugin selected!!!' );

				return false;
			}

			if ( defined( 'E20R_LICENSING_DEBUG' ) && true === E20R_LICENSING_DEBUG ) {
				$this->utils->log( "License status for {$product_sku}: " . ( $is_active ? 'Active' : 'Inactive' ) );
			}

			return $is_active;
		}

		/**
		 * Is the license scheduled to expire within the specified interval(s)
		 *
		 * @param string $product_sku
		 *
		 * @return bool
		 */
		public function is_expiring( $product_sku ) {

			// phpcs:ignore
			// $product_sku = strtolower( $product_sku );

			try {

				$settings = $this->settings->get_settings( $product_sku );
				$expires  = null;
			} catch ( \Exception $e ) {
				$this->utils->log( "Warning: Cannot find license for ${product_sku}: " . $e->getMessage() );
				return true;
			}

			if ( defined( 'E20R_LICENSING_DEBUG' ) && true === E20R_LICENSING_DEBUG ) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r
				$this->utils->log( 'Received settings for expiration check: ' . print_r( $settings, true ) );
			}

			if ( $this->is_new_version() && isset( $settings['expire'] ) ) {
				$expires = (int) $settings['expire'];
			} elseif ( isset( $settings['expires'] ) ) {
				$expires = (int) $settings['expires'];
			} else {
				// phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp.Requested
				$expires = (int) current_time( 'timestamp' );
			}

			if ( empty( $expires ) ) {

				if ( defined( 'E20R_LICENSING_DEBUG' ) && true === E20R_LICENSING_DEBUG ) {
					$this->utils->log( "NOTICE: The {$product_sku} license does not expire" );
				}

				return false;
			}

			if ( defined( 'E20R_LICENSING_DEBUG' ) && true === E20R_LICENSING_DEBUG ) {
				$this->utils->log(
					"Expiration date for {$product_sku}: " .
					( ! empty( $expires ) ?
						date_i18n( 'Y-m-d H:i:s', $expires ) :
						'Never'
					)
				);
			}

			$expiration_interval = apply_filters( 'e20r_licensing_expiration_warning_intervals', 30 );

			// phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp.Requested
			$calculated_warning_time = strtotime( "+ {$expiration_interval} day", current_time( 'timestamp' ) );

			// phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp.Requested
			$diff = $expires - current_time( 'timestamp' );

			if ( defined( 'E20R_LICENSING_DEBUG' ) && true === E20R_LICENSING_DEBUG ) {
				$this->utils->log( "{$product_sku} scheduled to expire on {$expires} vs {$calculated_warning_time}" );
			}

			// phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp.Requested
			if ( $expires <= $calculated_warning_time && $expires >= current_time( 'timestamp' ) && $diff > 0 ) {
				return true;
			// phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp.Requested
			} elseif ( $expires <= current_time( 'timestamp' ) && $diff <= 0 ) {
				return - 1;
			}

			return false;
		}

		/**
		 * Activate the license key on the remote server
		 *
		 * @param string $product_sku
		 * @param array  $settings
		 *
		 * @return array
		 *
		 * @since 1.8.4 - BUG FIX: Didn't save the license settings
		 */
		public function activate( $product_sku, $settings ) {

			$state = null;
			// phpcs:ignore
			// $product_sku = strtolower( $product_sku );

			if ( defined( 'E20R_LICENSING_DEBUG' ) && true === E20R_LICENSING_DEBUG ) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r
				$this->utils->log( "Attempting to activate {$product_sku} on remote server using: " . print_r( $settings, true ) );
			}

			if ( ( defined( 'E20R_LICENSING_DEBUG' ) && true === E20R_LICENSING_DEBUG ) && $this->is_new_version() ) {
				$this->utils->log( 'Using new license server plugin for activation...' );
			}

			if ( empty( $settings ) ) {
				$settings        = $this->settings->defaults( $product_sku );
				$settings['key'] = $product_sku;
			}

			if ( ! $this->is_new_version() ) {
				$api_params = array(
					'slm_action'        => 'slm_activate',
					'license_key'       => $settings['key'],
					'secret_key'        => LicenseServer::E20R_LICENSE_SECRET_KEY,
					'registered_domain' => isset( $_SERVER['SERVER_NAME'] ) ? $_SERVER['SERVER_NAME'] : 'Unknown',
					'item_reference'    => rawurlencode( $product_sku ),
					'first_name'        => $settings['first_name'],
					'last_name'         => $settings['last_name'],
					'email'             => $settings['email'],
				);
			} else {

				try {
					$response           = Api::activate(
						Client::instance(),
						function () use ( $settings, $product_sku ) {
							return LicenseRequest::create(
								E20R_LICENSE_SERVER_URL . '',
								'fake007',
								$settings['product_sku'],
								$settings['key'],
								LicenseRequest::DAILY_FREQUENCY
							);
						},
						function ( $license ) use ( $settings, $product_sku ) {
							$settings['status'] = 'active';
							$settings['key']    = $license;
							// phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp.Requested
							$settings['timestamp'] = current_time( 'timestamp' );

						}
					);
					$settings['status'] = 'active';

					// phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp.Requested
					$settings['timestamp'] = current_time( 'timestamp' );
					$state                 = ( false === $response->error );
					return array( $state, $settings );
				} catch ( \Exception $e ) {

					$settings           = $this->settings->defaults( $product_sku );
					$settings['status'] = 'inactive';
					$state              = false;
					$this->utils->log( 'Error: ' . $e->getMessage() );
					$this->utils->log( $e->getTraceAsString() );
					return array( $state, $settings );
				}

				/* phpcs:ignore Squiz.PHP.CommentedOutCode.Found
				$api_params = array(
					'action'      => 'license_key_activate',
					'store_code'  => LicenseServer::E20R_LICENSE_STORE_CODE,
					'sku'         => $settings['product_sku'],
					'license_key' => $settings['key'],
					'domain'      => isset( $_SERVER['SERVER_NAME'] ) ? $_SERVER['SERVER_NAME'] : 'Unknown',
				);
				*/
			}

			// Send query to the license manager server
			$decoded = $this->server->send( $api_params );

			if ( false === $decoded ) {

				// phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralDomain
				$msg = esc_attr__( 'Error transmitting to the remote licensing server', '00-e20r-utilities' );

				if ( defined( 'E20R_LICENSING_DEBUG' ) && true === E20R_LICENSING_DEBUG ) {
					$this->utils->log( $msg );
				}

				return array(
					'status'   => 'blocked',
					'settings' => null,
				);
			}

			if ( ! $this->is_new_version() ) {

				$this->utils->log( 'Using old licensing infrastructure' );

				if ( isset( $decoded->result ) ) {

					if ( defined( 'E20R_LICENSING_DEBUG' ) && true === E20R_LICENSING_DEBUG ) {
						$this->utils->log( "Decoded JSON and received a status... ({$decoded->result})" );
					}

					switch ( $decoded->result ) {

						case 'success':
							$settings['status'] = 'active';

							if ( defined( 'E20R_LICENSING_DEBUG' ) && true === E20R_LICENSING_DEBUG ) {
								$this->utils->log( "Added {$product_sku} to license list" );
								$this->utils->log( "Activated {$product_sku} on the remote server." );
							}

							$state = true;
							break;

						case 'error':
							$msg = $decoded->message;

							if ( false !== stripos( $msg, 'maximum' ) ) {
								$state = self::E20R_LICENSE_MAX_DOMAINS;
							} else {
								$state = self::E20R_LICENSE_ERROR;
							}

							$settings['status'] = 'blocked';

							if ( isset( $decoded->error_code ) ) {
								switch ( intval( $decoded->error_code ) ) {

									case 40:
										// Key/domain combo is already an active license
										if ( defined( 'E20R_LICENSING_DEBUG' ) && true === E20R_LICENSING_DEBUG ) {
											$this->utils->log( "Flagging {$settings['key']} as already active for this server" );
										}
										$state              = self::E20R_LICENSE_DOMAIN_ACTIVE;
										$settings['status'] = 'active';
										break;
								}
							}

							$this->utils->add_message(
								sprintf(
									// translators: The values are added from the request error object
									__(
										'For %1$s: %2$s',
										// phpcs:ignore
										'e20r-utilities-licensing'
									),
									$settings['key'],
									$decoded->message
								),
								$decoded->result,
								'backend'
							);
							if ( defined( 'E20R_LICENSING_DEBUG' ) && true === E20R_LICENSING_DEBUG ) {
								$this->utils->log( "{$decoded->message}" );
							}

							break;
					}
					 // phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp.Requested
					$settings['timestamp'] = current_time( 'timestamp' );
				}

				return array(
					'status'   => $state,
					'settings' => $settings,
				);
			}

			if ( defined( 'E20R_LICENSING_DEBUG' ) && true === E20R_LICENSING_DEBUG ) {
				$this->utils->log( 'Processing from new licensing server...' );
			}

			if ( true === $decoded->error ) {

				$this->utils->log( 'New licensing server returned error...' );

				$state    = self::E20R_LICENSE_ERROR;
				$settings = $this->settings->defaults( $product_sku );

				// translators: The substitution values come from the error object
				$msg = esc_attr__( 'Activation error: %1$s -> %2$s', 'e20r-utilities-licensing' );

				foreach ( (array) $decoded->errors as $error_key => $error_message ) {
					$this->utils->add_message(
						sprintf( $msg, $error_key, array_pop( $error_message ) ),
						'error',
						'backend'
					);
				}
			}

			if ( isset( $decoded->status ) && 200 === (int) $decoded->status ) {

				// $settings + $decoded->data == Merged settings

				if ( isset( $settings[ $product_sku ] ) && ! empty( $settings[ $product_sku ] ) ) {
					$existing_settings = $settings[ $product_sku ];
				} elseif ( ! isset( $settings[ $product_sku ] ) && ! empty( $settings ) ) {
					$existing_settings = $settings;
				} else {
					$existing_settings = $this->settings->defaults( $product_sku );
				}

				$new_settings = (array) $decoded->data;

				foreach ( $new_settings as $key => $value ) {
					$existing_settings[ $key ] = $value;
				}

				$this->utils->add_message( $decoded->message, 'notice', 'backend' );

				$settings = $existing_settings;
				$state    = self::E20R_LICENSE_DOMAIN_ACTIVE;
			}

			return array(
				'status'   => $state,
				'settings' => $settings,
			);
		}

		/**
		 * Deactivate the specified license (product/license key)
		 *
		 * @param string     $product_sku
		 * @param array|null $settings
		 *
		 * @return bool
		 */
		public function deactivate( $product_sku, $settings = null ) {

			if ( is_null( $settings ) ) {
				$settings = $this->settings->get_settings( $product_sku );
			}

			if ( empty( $settings['key'] ) ) {
				if ( defined( 'E20R_LICENSING_DEBUG' ) && true === E20R_LICENSING_DEBUG ) {
					$this->utils->log( 'No license key, so nothing to deactivate' );
				}

				return false;
			}

			if ( defined( 'E20R_LICENSING_DEBUG' ) && true === E20R_LICENSING_DEBUG ) {
				$this->utils->log( "Attempting to deactivate {$product_sku} on remote server" );
			}

			if ( E20R_LICENSING_DEBUG && $this->is_new_version() ) {
				$this->utils->log( 'Using new license server plugin for deactivation...' );
			}

			if ( ! $this->is_new_version() ) {
				$api_params = array(
					'slm_action'        => 'slm_deactivate',
					'license_key'       => $settings['key'],
					'secret_key'        => LicenseServer::E20R_LICENSE_SECRET_KEY,
					'registered_domain' => $_SERVER['SERVER_NAME'],
					'status'            => 'pending',
				);
			} else {

				if ( ! isset( $settings['activation_id'] ) ) {
					$this->utils->log( 'Unable to deactivate since activation_id data is missing!' );
					$this->utils->log( 'Just clear the license...' );

					if ( false === $this->settings->update( $product_sku, null ) ) {
						if ( defined( 'E20R_LICENSING_DEBUG' ) && true === E20R_LICENSING_DEBUG ) {
							$this->utils->log( "Unable to save settings (after removal) for {$product_sku}" );
						}
					}

					return true;
				}

				$api_params = array(
					'action'        => 'license_key_deactivate',
					'store_code'    => LicenseServer::E20R_LICENSE_STORE_CODE,
					'sku'           => $settings['product_sku'],
					'license_key'   => $settings['key'],
					'domain'        => isset( $_SERVER['SERVER_NAME'] ) ? $_SERVER['SERVER_NAME'] : 'Unknown',
					'activation_id' => $settings['activation_id'],
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
					if ( defined( 'E20R_LICENSING_DEBUG' ) && true === E20R_LICENSING_DEBUG ) {
						$this->utils->log( 'Error deactivating the license!' );
					}

					return false;
				}

				if ( defined( 'E20R_LICENSING_DEBUG' ) && true === E20R_LICENSING_DEBUG ) {
					$this->utils->log( "Removing license {$product_sku}..." );
				}

				if ( false === $this->settings->update( $product_sku, null ) ) {
					if ( defined( 'E20R_LICENSING_DEBUG' ) && true === E20R_LICENSING_DEBUG ) {
						$this->utils->log( "Unable to save settings (after removal) for {$product_sku}" );
					}
				}

				return true;
			} elseif ( isset( $decoded->status ) ) {

				if ( isset( $decoded->status ) && 500 === (int) $decoded->status ) {
					// translators: Error message supplied from decoded request object
					$error_message = esc_attr__( 'Deactivation error: %s', 'e20r-utilities-licensing' );

					if ( isset( $decoded->errors ) ) {

						$this->utils->log( 'Decoding error messages from the License server...' );
						// translators: Error message supplied from decoded request object
						$error_string = esc_attr__( '%1$s -> %2$s', 'e20r-utilities-licensing' );

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
		 * @param string $license_stub
		 *
		 * @return string
		 */
		public function get_license_page_url( $license_stub ) {

			return esc_url_raw(
				add_query_arg(
					array(
						'page'         => 'e20r-licensing',
						'license_stub' => rawurlencode( $license_stub ),
					),
					admin_url( 'options-general.php' )
				)
			);
		}

		/**
		 * Register all Licensing settings
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
				'E20R\Utilities\Licensing\LicenseSettings::validate'     // validation callback
			);

			add_settings_section(
				'e20r_licensing_section',
				__( 'Configure Licenses', 'e20r-licensing-utility' ),
				'E20R\Utilities\Licensing\LicensePage::show_section',
				'e20r-licensing'
			);

			// phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores
			$settings        = apply_filters(
				'e20r_license_add_new_licenses',
				$this->settings->all_settings(),
				array()
			);
			$license_counter = 0;

			if ( defined( 'E20R_LICENSING_DEBUG' ) && true === E20R_LICENSING_DEBUG ) {
				$this->utils->log( 'Found ' . count( $settings ) . ' potential licenses' );
			}

			foreach ( $settings as $product_sku => $license ) {

				$is_active = false;

				if ( defined( 'E20R_LICENSING_DEBUG' ) && true === E20R_LICENSING_DEBUG ) {
					$this->utils->log( "Processing license info for ${product_sku}" );
				}

				// Skip and clean up.
				if ( isset( $license['key'] ) && empty( $license['key'] ) ) {

					unset( $settings[ $product_sku ] );
					update_option( 'e20r_license_settings', $settings, 'yes' );

					if ( defined( 'E20R_LICENSING_DEBUG' ) && true === E20R_LICENSING_DEBUG ) {
						// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r
						$this->utils->log( "Skipping {$product_sku} with settings (doesn't have a product SKU): " . print_r( $license, true ) );
					}
					continue;
				}

				if ( defined( 'E20R_LICENSING_DEBUG' ) && true === E20R_LICENSING_DEBUG ) {
					$this->utils->log( "Loading settings fields for '{$product_sku}'?" );
				}

				if (
					! in_array( $product_sku, array( 'example_gateway_addon', 'new_licenses' ), true ) &&
					isset( $license['key'] ) &&
					( 'e20r_default_license' !== $license['key'] && 1 <= count( $settings ) ) &&
					! empty( $license['key'] )
				) {

					if ( defined( 'E20R_LICENSING_DEBUG' ) && true === E20R_LICENSING_DEBUG ) {
						$this->utils->log( "Previously activated license: {$product_sku}: adding {$license['fulltext_name']} fields" );
						// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r
						$this->utils->log( "Existing settings for {$product_sku}: " . print_r( $license, true ) );
					}

					if ( empty( $license['status'] ) ) {
						$license['status'] = 'inactive';
					}

					if ( 'active' === $license['status'] ) {
						$is_licensed = true;
						$key         = ( ! $this->is_new_version() && isset( $license['license_key'] ) ?
							$license['license_key'] :
							( isset( $license['product_sku'] ) ? $license['product_sku'] : null )

						);

						$is_active = $this->is_active(
							$key,
							$license,
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
										'e20r-utilities-licensing'
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
										'e20r-utilities-licensing'
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
						'E20R\Utilities\Licensing\LicensePage::show_input',
						'e20r-licensing',
						'e20r_licensing_section',
						array(
							'index'            => $license_counter,
							'label_for'        => isset( $license['key'] ) ?
								$license['key'] :
								__( 'Unknown', 'e20r-licensing-utility' ),
							'product'          => $product_sku,
							'option_name'      => 'e20r_license_settings',
							'fulltext_name'    => isset( $license['fulltext_name'] ) ?
								$license['fulltext_name'] :
								__( 'Unknown', 'e20r-licensing-utility' ),
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
							'placeholder'      => esc_attr__( 'Paste the purchased key here', 'e20r-licensing-utility' ),
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

			if ( defined( 'E20R_LICENSING_DEBUG' ) && true === E20R_LICENSING_DEBUG ) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r
				$this->utils->log( 'New license info found: ' . print_r( $new_licenses, true ) );
			}
			/* phpcs:ignore Squiz.PHP.CommentedOutCode.Found
			if ( empty( $new_licenses ) ) {
				$new_licenses = apply_filters( 'e20r-license-add-new-licenses', array() );
			}
			*/
			foreach ( $new_licenses as $new_product_sku => $new ) {

				if ( defined( 'E20R_LICENSING_DEBUG' ) && true === E20R_LICENSING_DEBUG ) {
					$this->utils->log( "Processing new license fields for new sku: {$new['new_product']}" );
				}

				// Skip if we've got this one in the list of licenses already.

				if ( ! in_array( $new['new_product'], $license_list, true ) && 'example_gateway_addon' !== $new_product_sku ) {
					if ( defined( 'E20R_LICENSING_DEBUG' ) && true === E20R_LICENSING_DEBUG ) {
						$this->utils->log( "Adding  license fields for new sku {$new['new_product']} (one of " . count( $new_licenses ) . ' unlicensed add-ons)' );
					}

					add_settings_field(
						"e20r_license_new_{$new_product_sku}",
						sprintf(
						// translators: The settings from the plugin being licensed will contain its name
							__( 'Add %s license', 'e20r-licensing-utility' ),
							$new['fulltext_name']
						),
						'E20R\Utilities\Licensing\LicensePage::show_input',
						'e20r-licensing',
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
							'is_active'     => $is_active,
							'email_field'   => 'new_email',
							'product_sku'   => $new['product_sku'],
							'email_value'   => null,
							'placeholder'   => $new['placeholder'],
							'classes'       => sprintf( 'e20r-licensing-new-column e20r-licensing-column-%1$d', $license_counter ),
						)
					);

					$license_counter ++;
					if ( defined( 'E20R_LICENSING_DEBUG' ) && true === E20R_LICENSING_DEBUG ) {
						$this->utils->log( "New license field(s) added for sku: {$new_product_sku}" );
					}
				}
			}
		}
	}
}
