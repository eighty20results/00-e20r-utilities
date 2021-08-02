<?php
/**
 *  Copyright (c) 2019 - 2021. - Eighty / 20 Results by Wicked Strong Chicks.
 *  ALL RIGHTS RESERVED
 *
 *  This program is free software: you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation, either version 3 of the License, or
 *  (at your option) any later version.
 *
 *  This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  You should have received a copy of the GNU General Public License
 *  along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 *  You can contact us at mailto:info@eighty20results.com
 */

namespace E20R\Licensing;

use E20R\Utilities\Utilities;
use E20R\Utilities\Cache;
use E20R\Licensing\Settings\LicenseSettings;
use E20R\Licensing\Settings\Defaults;
# For the 10quality license client handling
use Exception;
use LicenseKeys\Utility\Api;
use LicenseKeys\Utility\Client;
use LicenseKeys\Utility\LicenseRequest;

// Deny direct access to the file
if ( ! defined( 'ABSPATH' ) && function_exists( 'wp_die' ) ) {
	wp_die( 'Cannot access file directly' );
}

if ( ! class_exists( '\E20R\Licensing\LicenseServer' ) ) {
	/**
	 * Class LicenseServer
	 */
	class LicenseServer {

		/**
		 * Utilities class (logging, etc)
		 *
		 * @var Utilities|null $utils
		 */
		private $utils = null;

		/**
		 * Should we use the new License Key functionality
		 *
		 * @var bool $is_new_version
		 */
		private $is_new_version = false;

		/**
		 * Should the License Server connection use SSL?
		 * @var bool $use_ssl
		 */
		private $use_ssl = true;

		/**
		 * Whether to log Licensing specific (extra) debug information
		 *
		 * @var bool $log_debug
		 */
		private $log_debug = false;

		/**
		 * The License settings class
		 *
		 * @var null|LicenseSettings $license_settings
		 */
		private $license_settings = null;

		/**
		 * LicenseServer constructor.
		 *
		 * @param bool $is_new
		 * @param bool $use_ssl
		 */
		public function __construct( bool $is_new = false, bool $use_ssl = false ) {
			$this->log_debug      = defined( 'E20R_LICENSING_DEBUG' ) && E20R_LICENSING_DEBUG;
			$this->utils          = Utilities::get_instance();
			$this->is_new_version = $is_new;
			$this->use_ssl        = $use_ssl;
		}


		/**
		 * Transmit Request to the Licensing server
		 *
		 * @param array $api_params
		 *
		 * @return \stdClass|bool
		 */
		public function send( $api_params ) {

			if ( $this->log_debug ) {
				$this->utils->log( 'Attempting remote connection to ' . E20R_LICENSE_SERVER_URL );
			}

			if ( ! $this->is_new_version ) {
				$response = wp_remote_post(
					E20R_LICENSE_SERVER_URL,
					array(
						// phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores
						'timeout'     => apply_filters( 'e20r-license-remote-server-timeout', 30 ),
						'sslverify'   => $this->use_ssl,
						'httpversion' => '1.1',
						'decompress'  => true,
						'body'        => $api_params,
					)
				);
			} else {
				// Using the (new) WooCommerce License plugin
				// Send query to the license manager server
				try {
					return Api::validate(
						Client::instance(),
						function() use ( $api_params ) {
							return new LicenseRequest( $api_params['license_key'] );
						},
						function( $api_params ) {
							$this->utils = Utilities::get_instance();
							// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r
							$this->utils->log( 'Need to save API settings? ' . print_r( $api_params, true ) );
							// TODO: Save settings
						}
					);
				} catch ( Exception $e ) {
					$this->utils->log( "Error activating the ${$api_params['sku']} license! - {$e->getMessage()}" );
					$this->utils->log( $e->getTraceAsString() );
					return false;
				}
				/** FIXME: Remove when testing of the new API module works as expected
				$action = isset( $api_params['action'] ) ? $api_params['action'] : null;

				if ( empty( $action ) ) {
					$this->utils->log( 'Error: Using NEW Licensing version, but no action set!!!' );

					return false;
				} else {
					$this->utils->log( "Action: {$action}" );
				}

				unset( $api_params['action'] );

				if ( $this->log_debug ) {
					$this->utils->log( "Expensive: Connecting to upstream license server!" );
				}

				$response = wp_remote_post(
					add_query_arg( 'action', $action, E20R_LICENSE_SERVER_URL . '/wp-admin/admin-ajax.php' ),
					array(
						'timeout'     => apply_filters( 'e20r-license-remote-server-timeout', 30 ),
						'sslverify'   => $license->get_ssl_verify(),
						'httpversion' => '1.1',
						'decompress'  => true,
						'body'        => $api_params,
					)
				);
				 */
			}

			// Check for error in the response
			if ( is_wp_error( $response ) ) {
				// translators: The error message is supplied from the repsponse object
				$msg = sprintf( esc_attr__( 'E20R Licensing: %s', '00-e20r-utilities' ), $response->get_error_message() );
				if ( $this->log_debug ) {
					$this->utils->log( $msg );
				}
				$this->utils->add_message( $msg, 'error' );

				return false;
			}

			$license_data = stripslashes( wp_remote_retrieve_body( $response ) );

			$bom          = pack( 'H*', 'EFBBBF' );
			$license_data = preg_replace( "/^$bom/", '', $license_data );
			$decoded      = json_decode( $license_data );

			if ( null === $decoded && json_last_error() !== JSON_ERROR_NONE ) {

				switch ( json_last_error() ) {
					case JSON_ERROR_DEPTH:
						$error = esc_attr__( 'Maximum stack depth exceeded', '00-e20r-utilities' );
						break;
					case JSON_ERROR_STATE_MISMATCH:
						$error = esc_attr__( 'Underflow or the modes mismatch', '00-e20r-utilities' );
						break;
					case JSON_ERROR_CTRL_CHAR:
						$error = esc_attr__( 'Unexpected control character found', '00-e20r-utilities' );
						break;
					case JSON_ERROR_SYNTAX:
						$error = esc_attr__( 'Syntax error, malformed JSON', '00-e20r-utilities' );
						break;
					case JSON_ERROR_UTF8:
						$error = esc_attr__( 'Malformed UTF-8 characters, possibly incorrectly encoded', '00-e20r-utilities' );
						break;
					default:
						$error = sprintf(
							// translators: The message is returned from the json parser (if it exists)
							__( 'No error, supposedly? %s', '00-e20r-utilities' ),
							// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r
							print_r( json_last_error(), true )
						);
				}

				if ( $this->log_debug ) {
					$this->utils->log( 'Response from remote server: <' . $license_data . '>' );
					$this->utils->log( 'JSON decode error: ' . $error );
				}

				return false;
			}

			return $decoded;
		}

		/**
		 * Connect to license server and check status for the current product/server
		 *
		 * @param string     $sku - The product/SKU to get the license status for
		 * @param null|array $settings - Optional array of parameters for the SKU
		 * @param bool       $force - Whether to force a check against the license server
		 *
		 * @return bool
		 */
		public function status( $sku, $settings = null, $force = false ) : bool {

			// Default value for the license (it's not active)
			$license_status = null;

			$this->license_settings = new LicenseSettings( $sku );
			$use_new                = (bool) $this->license_settings->get( 'new_version' );

			if ( is_null( $settings ) ) {
				$settings = $this->license_settings->all_settings();
			}

			if ( ! $use_new && empty( $settings['key'] ) ) {
				if ( $this->log_debug ) {
					$this->utils->log( "Old store: {$sku} has no key stored. Returning false" );
				}
				return false;
			}

			if ( $use_new && empty( $settings['the_key'] ) ) {
				if ( $this->log_debug ) {
					$this->utils->log( "New store: {$sku} has no key stored. Returning false" );
				}
				return false;
			}

			if ( $this->log_debug && $use_new ) {
				$this->utils->log( 'Using the new store plugin' );
			}

			$license_status = (bool) Cache::get( "{$sku}_status", 'e20r_licensing' );

			$this->utils->log( 'Force check against upstream server? ' . ( $force ? 'Yes' : 'No' ) );
			$this->utils->log( "Cached license status is ({$license_status}): " . ( $license_status ? 'Active' : 'empty' ) );

			if ( false === $force && null !== $license_status ) {
				if ( $this->log_debug ) {
					$this->utils->log( "Using the cached (local) license status ({$license_status}) info for {$sku}" );
				}
				return $license_status;
			}

			if ( $this->log_debug ) {
				$this->utils->log( "Connecting to license server to validate license for {$sku}" );
			}

			if ( false === $use_new ) {
				// Configure request for license check
				$api_params = array(
					'slm_action'  => 'slm_check',
					'secret_key'  => Defaults::E20R_LICENSE_SECRET_KEY,
					'license_key' => $settings['key'],
					// 'registered_domain' => $_SERVER['SERVER_NAME'] phpcs:ignore Squiz.PHP.CommentedOutCode.Found
				);
			} else {

				$license_settings = $this->license_settings;

				// Use the 10quality License Key management client
				// Returns a boolean if successful
				try {
					return Api::validate(
						Client::instance(),
						function() use ( $settings ) {
							return new LicenseRequest( $settings['key'] );
						},
						function() use ( $sku, $settings, $license_settings ) {
							if ( $this->log_debug ) {
								// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r
								$this->utils->log( "Saving settings for {$sku}: " . print_r( $settings, true ) );
							}

							// Update the license settings
							return $license_settings->update( $sku, $settings );
						}
					);
				} catch ( \Exception $e ) {
					$this->utils->log( "Validation error: " . $e->getMessage() ); //phpcs:ignore
					return false;
				}

				/** FIXME: Remove this when the new client API works as expected
				if ( ! isset( $settings['activation_id'] ) ) {
					$this->utils->log( "Assume the license is inactive and return" );

					return false;
				}

				$api_params = array(
					'action'        => 'license_key_validate',
					'store_code'    => self::E20R_LICENSE_STORE_CODE,
					'license_key'   => $settings['the_key'],
					'domain'        => isset( $_SERVER['SERVER_NAME'] ) ? $_SERVER['SERVER_NAME'] : 'Unknown',
					'sku'           => $settings['product_sku'],
					'activation_id' => $settings['activation_id'],
				);
				*/
			}

			if ( $this->log_debug ) {
				$this->utils->log( "Transmitting request to License server for {$sku}" );
			}

			$decoded = $this->send( $api_params );

			if ( true === (bool) $this->license_settings->get( 'new_version' ) ) {
				$license_status = $this->process_new_license_info( $sku, $decoded, $settings );
			} else {
				$license_status = $this->process_old_license_info( $sku, $decoded, $settings );
			}

			return $license_status;
		}

		/**
		 * Using the new Licensing plugin for WooCommerce (different format)
		 *
		 * @param string $sku
		 * @param \stdClass $decoded
		 * @param array $settings
		 *
		 * @return bool
		 */
		private function process_new_license_info( $sku, $decoded, $settings ) : bool {

			global $current_user;

			$product_name   = $settings['fulltext_name'];
			$license_status = (bool) Cache::get( "{$sku}_status", 'e20r_licensing' );

			// License not validated
			if ( ! isset( $decoded->result ) || 'success' !== $decoded->result ) {
				$msg = sprintf(
					// translators: License name is provided by the calling plugin
					__( 'Sorry, no valid license found for: %s', '00-e20r-utilities' ),
					$product_name
				);
				if ( $this->log_debug ) {
					$this->utils->log( $msg );
				}
				$this->utils->add_message( $msg, 'error', 'backend' );

				Cache::set( "{$sku}_status", $license_status, DAY_IN_SECONDS, 'e20r_licensing' );
				return $license_status;
			}

			if ( is_array( $decoded->registered_domains ) ) {
				if ( $this->log_debug ) {
					$this->utils->log( 'Processing license data for (count: ' . count( $decoded->registered_domains ) . ' domains )' );
				}

				foreach ( $decoded->registered_domains as $domain ) {

					if ( isset( $domain->registered_domain ) && $domain->registered_domain === $_SERVER['SERVER_NAME'] ) {

						if ( '0000-00-00' !== $decoded->date_renewed ) {
							$settings['renewed'] = strtotime( $decoded->date_renewed, time() );
						} else {
							$settings['renewed'] = time();
						}
						$settings['domain']        = $domain->registered_domain;
						$settings['fulltext_name'] = $product_name;
						$settings['expires']       = isset( $decoded->date_expiry ) ? strtotime( $decoded->date_expiry, time() ) : null;
						$settings['status']        = $decoded->status;
						$settings['first_name']    = isset( $current_user->first_name ) ? $current_user->first_name : null;
						$settings['last_name']     = isset( $current_user->last_name ) ? $current_user->last_name : null;
						$settings['email']         = $decoded->email;
						$settings['timestamp']     = time();

						if ( $this->log_debug ) {
							// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r
							$this->utils->log( "Saving license data for {$domain->registered_domain}: " . print_r( $settings, true ) );
						}
						if ( false === $this->license_settings->update( $sku, $settings ) ) {

							$msg = sprintf(
							// translators: The license name is received from the plugin being licensed
								__( 'Unable to save the %s license settings', '00-e20r-utilities' ),
								$settings['fulltext_name']
							);
							if ( $this->log_debug ) {
								$this->utils->log( $msg );
							}
							$this->utils->add_message( $msg, 'error', 'backend' );
						}

						$license_status = ( 'active' === $settings['status'] );
						if ( $this->log_debug ) {
							$this->utils->log( "Current status for {$sku} license: " . ( $license_status ? 'active' : 'inactive/deactivated/blocked' ) );
						}
					} else {
						if ( $this->log_debug ) {
							$this->utils->log( 'Wrong domain, or domain info not found' );
						}
					}
				}
			} else {
				// 'activation_id' => $settings['activation_id'] phpcs:ignore Squiz.PHP.CommentedOutCode.Found
				if ( $this->log_debug ) {
					$this->utils->log( "The {$sku} license is on the server, but not active for this domain" );
				}
				$license_status = false;
			}

			if ( isset( $settings['expires'] ) && $settings['expires'] < time() || ( isset( $settings['active'] ) && 'active' !== $settings['status'] ) ) {
				$msg = sprintf(
				// translators: The license name is set by the plugin being licensed
					__( 'Your %s license has expired!', '00-e20r-utilities' ),
					$settings['fulltext_name']
				);

				if ( $this->log_debug ) {
					$this->utils->log( $msg );
				}
				$this->utils->add_message( $msg, 'error' );
				$license_status = false;
			}

			$this->utils->log( "Save the value for '{$sku}_status' to cache: {$license_status}" );
			Cache::set( "{$sku}_status", $license_status, DAY_IN_SECONDS, 'e20r_licensing' );

			return $license_status;
		}

		/**
		 * Process the original type of license information
		 *
		 * @param string $sku
		 * @param \stdClass $decoded
		 * @param array $settings
		 *
		 * @return bool
		 */
		private function process_old_license_info( $sku, $decoded, $settings ) : bool {

			$license_status = (bool) Cache::get( "{$sku}_status", 'e20r_licensing' );

			if ( $this->log_debug ) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r
				$this->utils->log( 'Returned data from (new) validation check: ' . print_r( $decoded, true ) );
			}

			if ( empty( $decoded ) ) {
				// translators: License name is provided by the calling plugin
				$msg = esc_attr__( 'No data received from license server for %1$s. Please contact the store owner!', '00-e20r-utilities' );

				$this->utils->add_message(
					sprintf( $msg, $settings['fulltext_name'] ),
					'error',
					'backend'
				);

				$license_status = false;
				Cache::set( "{$sku}_status", $license_status, DAY_IN_SECONDS, 'e20r_licensing' );
				return $license_status;
			}

			if ( 1 === (int) $decoded->error && ( isset( $decoded->status ) && 500 === (int) $decoded->status ) ) {
				// translators: License name is provided by the calling plugin and the error from the decoded request
				$msg = esc_attr__( 'Error validating the %1$s license: %2$s -> %3$s', '00-e20r-utilities' );

				foreach ( (array) $decoded->errors as $error_key => $error_info ) {

					$this->utils->add_message(
						sprintf( $msg, $settings['fulltext_name'], $error_key, array_pop( $error_info ) ),
						'error',
						'backend'
					);
				}

				$license_status = false;
				Cache::set( "{$sku}_status", $license_status, DAY_IN_SECONDS, 'e20r_licensing' );
				return $license_status;
			}

			if ( isset( $decoded->status ) && 200 === (int) $decoded->status ) {

				$this->utils->log( 'License is valid, so should be returning success' );

				if ( isset( $decoded->data->status ) && 'active' === $decoded->data->status ) {
					$license_status = true;
				}

				$this->utils->log( 'License status: ' . ( $license_status ? 'True' : 'False' ) );
			}

			Cache::set( "{$sku}_status", $license_status, DAY_IN_SECONDS, 'e20r_licensing' );

			return $license_status;
		}
	}
}
