<?php
/**
 *  Copyright (c) 2019-2021. - Eighty / 20 Results by Wicked Strong Chicks.
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

namespace E20R\Utilities\Licensing;

use E20R\Utilities\Cache;
use E20R\Utilities\Utilities;
# For the 10quality license client handling
use LicenseKeys\Utility\Api;
use LicenseKeys\Utility\Client;
use LicenseKeys\Utility\LicenseRequest;

if ( file_exists( plugin_dir_path( __FILE__ ) . 'inc/autoload.php' ) ) {
	require_once plugin_dir_path( __FILE__ ) . 'inc/autoload.php';
}

// Deny direct access to the file
if ( ! defined( 'ABSPATH' ) && function_exists( 'wp_die' ) ) {
	wp_die( 'Cannot access file directly' );
}
if ( ! class_exists( '\E20R\Utilities\Licensing\LicenseServer' ) ) {
	class LicenseServer {

		const E20R_LICENSE_SECRET_KEY = '5687dc27b50520.33717427';
		const E20R_LICENSE_STORE_CODE = 'L4EGy6Y91a15ozt';

		/**
		 * Transmit Request to the Licensing server
		 *
		 * @param array $api_params
		 *
		 * @return \stdClass|false
		 */
		public static function send( $api_params ) {

			$utils     = Utilities::get_instance();
			$licensing = new Licensing();

			if ( defined( 'E20R_LICENSING_DEBUG' ) && true === E20R_LICENSING_DEBUG ) {
				$utils->log( 'Attempting remote connection to ' . E20R_LICENSE_SERVER_URL );
			}

			if ( ! $licensing->is_new_version() ) {
				$response = wp_remote_post(
					E20R_LICENSE_SERVER_URL,
					array(
						// phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores
						'timeout'     => apply_filters( 'e20r-license-remote-server-timeout', 30 ),
						'sslverify'   => $licensing->get_ssl_verify(),
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
							$utils = Utilities::get_instance();
							// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r
							$utils->log( 'Need to save API settings? ' . print_r( $api_params, true ) );
							// TODO: Save settings
						}
					);
				} catch ( \Exception $e ) {
					$utils->log( "Error activating the ${$api_params['sku']} license! - {$e->getMessage()}" );
					$utils->log( $e->getTraceAsString() );
					return false;
				}
				/** FIXME: Remove when testing of the new API module works as expected
				$action = isset( $api_params['action'] ) ? $api_params['action'] : null;

				if ( empty( $action ) ) {
					$utils->log( 'Error: Using NEW Licensing version, but no action set!!!' );

					return false;
				} else {
					$utils->log( "Action: {$action}" );
				}

				unset( $api_params['action'] );

				if ( defined( 'E20R_LICENSING_DEBUG' ) && true === E20R_LICENSING_DEBUG ) {
					$utils->log( "Expensive: Connecting to upstream license server!" );
				}

				$response = wp_remote_post(
					add_query_arg( 'action', $action, E20R_LICENSE_SERVER_URL . '/wp-admin/admin-ajax.php' ),
					array(
						'timeout'     => apply_filters( 'e20r-license-remote-server-timeout', 30 ),
						'sslverify'   => $licensing->get_ssl_verify(),
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
				$msg = sprintf( esc_attr__( 'E20R Licensing: %s', 'e20r-licensing-utility' ), $response->get_error_message() );
				if ( defined( 'E20R_LICENSING_DEBUG' ) && true === E20R_LICENSING_DEBUG ) {
					$utils->log( $msg );
				}
				$utils->add_message( $msg, 'error' );

				return false;
			}

			$license_data = stripslashes( wp_remote_retrieve_body( $response ) );

			$bom          = pack( 'H*', 'EFBBBF' );
			$license_data = preg_replace( "/^$bom/", '', $license_data );
			$decoded      = json_decode( $license_data );

			if ( null === $decoded && json_last_error() !== JSON_ERROR_NONE ) {

				switch ( json_last_error() ) {
					case JSON_ERROR_DEPTH:
						$error = esc_attr__( 'Maximum stack depth exceeded', 'e20r-licensing-utility' );
						break;
					case JSON_ERROR_STATE_MISMATCH:
						$error = esc_attr__( 'Underflow or the modes mismatch', 'e20r-licensing-utility' );
						break;
					case JSON_ERROR_CTRL_CHAR:
						$error = esc_attr__( 'Unexpected control character found', 'e20r-licensing-utility' );
						break;
					case JSON_ERROR_SYNTAX:
						$error = esc_attr__( 'Syntax error, malformed JSON', 'e20r-licensing-utility' );
						break;
					case JSON_ERROR_UTF8:
						$error = esc_attr__( 'Malformed UTF-8 characters, possibly incorrectly encoded', 'e20r-licensing-utility' );
						break;
					default:
						$error = sprintf(
							// translators: The message is returned from the json parser (if it exists)
							__( 'No error, supposedly? %s', 'e20r-licensing-utility' ),
							// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r
							print_r( json_last_error(), true )
						);
				}

				if ( defined( 'E20R_LICENSING_DEBUG' ) && true === E20R_LICENSING_DEBUG ) {
					$utils->log( 'Response from remote server: <' . $license_data . '>' );
					$utils->log( 'JSON decode error: ' . $error );
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
		public static function status( $sku, $settings = null, $force = false ) {

			$utils     = Utilities::get_instance();
			$licensing = new Licensing();

			// Default value for the license (it's not active)
			$license_status = false;
			global $current_user;

			if ( is_null( $settings ) ) {
				$license_settings = new LicenseSettings( $sku );
				$settings         = $license_settings->all_settings();
			}

			if ( ! $licensing->is_new_version() && empty( $settings['key'] ) ) {
				if ( defined( 'E20R_LICENSING_DEBUG' ) && true === E20R_LICENSING_DEBUG ) {
					$utils->log( "Old store: {$sku} has no key stored. Returning false" );
				}

				return $license_status;
			}

			if ( $licensing->is_new_version() && empty( $settings['the_key'] ) ) {
				if ( defined( 'E20R_LICENSING_DEBUG' ) && true === E20R_LICENSING_DEBUG ) {
					$utils->log( "New store: {$sku} has no key stored. Returning false" );
				}

				return $license_status;
			}

			if ( defined( 'E20R_LICENSING_DEBUG' ) && true === E20R_LICENSING_DEBUG && $licensing->is_new_version() ) {
				$utils->log( 'Using the new store plugin' );
			}

			if ( defined( 'E20R_LICENSING_DEBUG' ) && true === E20R_LICENSING_DEBUG ) {
				$utils->log( "Local license settings for {$sku}" /* . print_r( $settings, true ) phpcs:ignore */ );
			}

			$utils->log( 'Force check against upstream server? ' . ( $force ? 'Yes' : 'No' ) );
			$license_status = (bool) Cache::get( "{$sku}_status", 'e20r_licensing' );
			$utils->log( "Cached license status is ({$license_status}): " . ( $license_status ? 'Active' : 'empty' ) );

			if ( true === $force || false === $force && true !== $license_status ) {

				if ( defined( 'E20R_LICENSING_DEBUG' ) && true === E20R_LICENSING_DEBUG ) {
					$utils->log( "Connecting to license server to validate license for {$sku}" );
				}

				$product_name = $settings['fulltext_name'];

				if ( ! $licensing->is_new_version() ) {
					// Configure request for license check
					$api_params = array(
						'slm_action'  => 'slm_check',
						'secret_key'  => self::E20R_LICENSE_SECRET_KEY,
						'license_key' => $settings['key'],
						// 'registered_domain' => $_SERVER['SERVER_NAME'] phpcs:ignore Squiz.PHP.CommentedOutCode.Found
					);
				} else {

					// Use the 10quality License Key management client
					// Returns a boolean if successful
					return Api::validate(
						Client::instance(),
						function() use ( $settings ) {
							return new LicenseRequest( $settings['key'] );
						},
						function() use ( $sku, $settings ) {
							$utils = Utilities::get_instance();

							if ( defined( 'E20R_LICENSING_DEBUG' ) && true === E20R_LICENSING_DEBUG ) {
								// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r
								$utils->log( "Saving settings for {$sku}: " . print_r( $settings, true ) );
							}

							return LicenseSettings::update( $sku, $settings );
						}
					);
					/** FIXME: Remove this when the new client API works as expected
					if ( ! isset( $settings['activation_id'] ) ) {
						$utils->log( "Assume the license is inactive and return" );

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
				if ( defined( 'E20R_LICENSING_DEBUG' ) && true === E20R_LICENSING_DEBUG ) {
					$utils->log( "Transmitting request to License server for {$sku}" );
				}

				$decoded = self::send( $api_params );

				if ( ! $licensing->is_new_version() ) {
					// License not validated
					if ( ! isset( $decoded->result ) || 'success' !== $decoded->result ) {

						if ( isset( $settings['fulltext_name'] ) ) {
							$name = $settings['fulltext_name'];
						} else {
							$name = $product_name;
						}

						$msg = sprintf(
							// translators: License name is provided by the calling plugin
							__( 'Sorry, no valid license found for: %s', 'e20r-licensing-utility' ),
							$name
						);
						if ( defined( 'E20R_LICENSING_DEBUG' ) && true === E20R_LICENSING_DEBUG ) {
							$utils->log( $msg );
						}
						$utils->add_message( $msg, 'error', 'backend' );

						Cache::set( "{$sku}_status", $license_status, DAY_IN_SECONDS, 'e20r_licensing' );
						return $license_status;
					}

					if ( is_array( $decoded->registered_domains ) ) {
						if ( defined( 'E20R_LICENSING_DEBUG' ) && true === E20R_LICENSING_DEBUG ) {
							$utils->log( 'Processing license data for (count: ' . count( $decoded->registered_domains ) . ' domains )' );
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

								if ( defined( 'E20R_LICENSING_DEBUG' ) && true === E20R_LICENSING_DEBUG ) {
									// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r
									$utils->log( "Saving license data for {$domain->registered_domain}: " . print_r( $settings, true ) );
								}
								if ( false === LicenseSettings::update( $sku, $settings ) ) {

									$msg = sprintf(
										// translators: The license name is received from the plugin being licensed
										__( 'Unable to save the %s license settings', 'e20r-licensing-utility' ),
										$settings['fulltext_name']
									);
									if ( defined( 'E20R_LICENSING_DEBUG' ) && true === E20R_LICENSING_DEBUG ) {
										$utils->log( $msg );
									}
									$utils->add_message( $msg, 'error', 'backend' );
								}

								$license_status = ( 'active' === $settings['status'] );
								if ( defined( 'E20R_LICENSING_DEBUG' ) && true === E20R_LICENSING_DEBUG ) {
									$utils->log( "Current status for {$sku} license: " . ( $license_status ? 'active' : 'inactive/deactivated/blocked' ) );
								}
							} else {
								if ( defined( 'E20R_LICENSING_DEBUG' ) && true === E20R_LICENSING_DEBUG ) {
									$utils->log( 'Wrong domain, or domain info not found' );
								}
							}
						}
					} else {

						// 'activation_id' => $settings['activation_id'] phpcs:ignore Squiz.PHP.CommentedOutCode.Found

						if ( defined( 'E20R_LICENSING_DEBUG' ) && true === E20R_LICENSING_DEBUG ) {
							$utils->log( "The {$sku} license is on the server, but not active for this domain" );
						}
						$license_status = false;
					}

					if ( isset( $settings['expires'] ) && $settings['expires'] < time() || ( isset( $settings['active'] ) && 'active' !== $settings['status'] ) ) {

						$msg = sprintf(
							// translators: The license name is set by the plugin being licensed
							__( 'Your %s license has expired!', 'e20r-licensing-utility' ),
							$settings['fulltext_name']
						);

						if ( defined( 'E20R_LICENSING_DEBUG' ) && true === E20R_LICENSING_DEBUG ) {
							$utils->log( $msg );
						}
						$utils->add_message( $msg, 'error' );
						$license_status = false;
					}

					$utils->log( "Save the value for '{$sku}_status' to cache: {$license_status}" );
					Cache::set( "{$sku}_status", $license_status, DAY_IN_SECONDS, 'e20r_licensing' );
				} else {

					if ( defined( 'E20R_LICENSING_DEBUG' ) && true === E20R_LICENSING_DEBUG ) {
						// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r
						$utils->log( 'Returned data from (new) validation check: ' . print_r( $decoded, true ) );
					}

					if ( empty( $decoded ) ) {
						// translators: License name is provided by the calling plugin
						$msg = esc_attr__( 'No data received from license server for %1$s. Please contact the store owner!', 'e20r-utilities-licensing' );

						$utils->add_message(
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
						$msg = esc_attr__( 'Error validating the %1$s license: %2$s -> %3$s', 'e20r-utilities-licensing' );

						foreach ( (array) $decoded->errors as $error_key => $error_info ) {

							$utils->add_message(
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

						$utils->log( 'License is valid, so should be returning success' );

						if ( isset( $decoded->data->status ) && 'active' === $decoded->data->status ) {
							$license_status = true;
						}

						$utils->log( 'License status: ' . ( $license_status ? 'True' : 'False' ) );
					}

					Cache::set( "{$sku}_status", $license_status, DAY_IN_SECONDS, 'e20r_licensing' );
				}
			} else {
				if ( defined( 'E20R_LICENSING_DEBUG' ) && true === E20R_LICENSING_DEBUG ) {
					$utils->log( "Loaded the cached (local) license status ({$license_status}) info for {$sku}" );
				}
			}

			return $license_status;
		}
	}
}
