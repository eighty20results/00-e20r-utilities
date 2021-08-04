<?php
/**
 *  Copyright (c) 2017-2021. - Eighty / 20 Results by Wicked Strong Chicks.
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

namespace E20R\Licensing\Settings;

use E20R\Licensing\Exceptions\InvalidSettingsKey;
use E20R\Licensing\Exceptions\MissingServerURL;
use E20R\Licensing\Exceptions\NoLicenseKeyFound;
use E20R\Licensing\LicenseServer;
use E20R\Licensing\Settings\Defaults;
use E20R\Licensing\License;
use E20R\Utilities\Message;
use E20R\Utilities\Utilities;

// Deny direct access to the file
if ( ! defined( 'ABSPATH' ) && function_exists( 'wp_die' ) ) {
	wp_die( 'Cannot access file directly' );
}

if ( ! defined( 'E20R_MISSING_SETTING' ) ) {
	define( 'E20R_MISSING_SETTING', 1024 );
}

if ( ! class_exists( '\E20R\Utilities\Licensing\LicenseSettings' ) ) {
	class LicenseSettings {

		/**
		 * All settings for all processed licenses
		 *
		 * @var array|false|void $all_settings
		 */
		protected $all_settings = array();

		/**
		 * Current instance of the settings
		 *
		 * @var LicenseSettings|NewLicenseSettings|OldLicenseSettings|null
		 */
		protected $settings = null;

		/**
		 * @var null|Utilities
		 */
		protected $utils = null;

		/**
		 * The license key to use
		 *
		 * @var null|string $product_sku
		 */
		protected $product_sku = null;

		/**
		 * The description of the license
		 *
		 * @var string $fulltext_name
		 */
		protected $fulltext_name = '';

		/**
		 * List of excluded class variables (i.e. not settings)
		 *
		 * @var array|string[] $excluded
		 */
		protected $excluded = array();

		/**
		 * Should we verify SSL certificate(s)
		 *
		 * @var bool $ssl_verify
		 */
		protected $ssl_verify = true;

		/**
		 * New or old version of Licensing system
		 *
		 * @var bool $new_version
		 */
		protected $new_version;

		/**
		 * Whether to log Licensing specific (extra) debug information
		 *
		 * @var bool $to_debug
		 */
		protected $to_debug = false;

		/**
		 * The default settings for the plugin
		 *
		 * @var Defaults|null $plugin_defaults
		 */
		protected $plugin_defaults = null;

		/**
		 * LicenseSettings constructor.
		 *
		 * @param string|null $product_sku
		 *
		 * @throws InvalidSettingsKey|MissingServerURL
		 */
		public function __construct( $product_sku = 'e20r_default_license', $plugin_defaults = null, $utils = null ) {

			if ( empty( $product_sku ) ) {
				$product_sku = 'e20r_default_license';
			}

			if ( empty( $plugin_defaults ) ) {
				$plugin_defaults = new Defaults();
			}

			if ( empty( $utils ) ) {
				$messages = new Message();
				$utils    = new Utilities( $messages );
			}

			$this->product_sku     = $product_sku;
			$this->utils           = $utils;
			$this->plugin_defaults = $plugin_defaults;
			$this->update_plugin_defaults();

			$this->excluded = array(
				'excluded',
				'utils',
				'page_handle',
				'settings',
				'all_settings',
				'instance',
				'page',
				'to_debug',
// 				'plugin_defaults',
			);

			$server_url = $this->plugin_defaults->get( 'server_url' );

			if (
				empty( $server_url ) ||
				1 !== preg_match( '/^https?:\/\/([0-9a-zA-Z].*)\.([0-9a-zA-Z].*)\/?/', $server_url )
			) {
				$msg = "Error: Haven't configured the Eighty/20 Results server URL, or the URL is malformed. Can be configured in the wp-config.php file.";
				$this->utils->log( $msg );
				$this->utils->add_message(
					esc_html__(
						"Error: The license server URL is unknown, or the URL is malformed! Place a correct URL in your wp-config.php file. Example: define( 'E20R_LICENSE_SERVER_URL', 'https://eighty20results.com/' )",
						'00-e20r-utilities'
					),
					'error',
					'backend'
				);
				throw new MissingServerURL( $msg );
			}
		}

		/**
		 * Configure the plugin defaults (reset them if necessary)
		 *
		 * @throws InvalidSettingsKey
		 */
		public function update_plugin_defaults() {
			try {
				$this->to_debug = (bool) $this->plugin_defaults->get( 'debug_logging' );
			} catch ( \Exception $e ) {
				$this->utils->log( 'Error: Unable to save to_debug setting: ' . $e->getMessage() );
				throw $e;
			}

			if ( isset( $_SERVER['HTTP_HOST'] ) && 'eighty20results.com' === $_SERVER['HTTP_HOST'] ) {
				$this->utils->log( 'Running on Licensing server. Deactivating SSL Verification for loop-back connections' );
				$this->ssl_verify = false;
			}

			// Determine whether we're using the new or old Licensing version
			try {
				$this->new_version = version_compare( $this->plugin_defaults->get( 'version' ), '3.0', 'ge' );
			} catch ( \Exception $e ) {
				$this->utils->log( 'Error: Unable to fetch the version information for this plugin!' );
				$this->utils->add_message(
					__(
						'Error: Unable to fetch the version information for this plugin!',
						'00-e20r-utilities'
					),
					'error',
					'backend'
				);
				throw $e;
			}

			if ( $this->to_debug ) {
				$this->utils->log( 'Using new or old version of Licensing code..? ' . ( $this->new_version ? 'New' : 'Old' ) );
			}
		}
		/**
		 *
		 * Load local settings for the specified product
		 *
		 * @param string|null $product_sku
		 *
		 * @return array|null
		 *
		 * @throws \Exception
		 */
		public function load_settings( string $product_sku = null ) {

			if ( empty( $product_sku ) && empty( $this->product_sku ) ) {
				if ( $this->to_debug ) {
					$this->utils->log( 'No product key provided. Using default key (e20r_default_license)!' );
				}
				$product_sku = 'e20r_default_license';
			}

			if ( ! empty( $product_sku ) && $this->product_sku !== $product_sku ) {
				$this->product_sku = $product_sku;
			}

			$defaults           = $this->defaults();
			$this->all_settings = get_option( 'e20r_license_settings', $defaults );
			// $this->settings     = isset( $this->all_settings[ $product_sku ] ) ? $this->settings[ $product_sku ] : $defaults;

			// $product_sku  = strtolower( $product_sku ); phpcs:ignore Squiz.PHP.CommentedOutCode.Found
			$settings = get_option( 'e20r_license_settings', $defaults );

			if ( empty( $this->all_settings ) || (
					1 <= count( $this->all_settings ) && 'e20r_default_license' === $product_sku )
			) {
				$this->utils->log( 'Overwriting license settings with defaults' );
				$settings = $defaults;
			}

			if ( $this->new_version ) {
				$this->settings = new NewLicenseSettings( $product_sku );
			} else {
				$this->settings = new OldLicenseSettings( $product_sku );
			}

			foreach ( $settings as $setting_key => $value ) {
				$this->settings->set( $setting_key, $value );
			}

			if ( $this->to_debug ) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r
				$this->utils->log( 'All settings: ' . print_r( $settings, true ) );
			}

			if ( 'e20r_default_license' === $product_sku || empty( $product_sku ) ) {
				if ( $this->to_debug ) {
					$this->utils->log(
						"No product, or default product specified, so returning all settings: {$product_sku}"
					);
				}

				return $settings;
			}

			if ( $this->to_debug ) {
				$this->utils->log( "Requested and returning settings for {$product_sku}" );
			}

			if ( empty( $this->settings->get( 'product_sku' ) ) ) {
				return null;
			}

			return $this->settings->all_settings();
		}

		/**
		 * Set the license setting property
		 *
		 * @param string     $key   License setting key (name of property)
		 * @param null|mixed $value License setting value (value of property)
		 *
		 * @return bool
		 *
		 * @throws \Exception
		 */
		public function set( string $key, $value = null ): bool {

			if ( 'plugin_defaults' === $key && defined( 'PLUGIN_PHPUNIT' ) && PLUGIN_PHPUNIT ) {
				$this->utils->log( 'Warning: Intentionally overriding the plugin default settings' );
				$this->plugin_defaults = $value;
				$this->update_plugin_defaults();
				return true;
			}

			if ( ! isset( $this->{$key} ) ) {
				$this->utils->log( "Error: '{$key}' does not exist!" );
				throw new InvalidSettingsKey(
					sprintf(
						// translators: %1$s - Key name for the failed setting update
						esc_attr__( 'Error: The %1$s property is not valid', '00-e20r-utilities' ),
						$key
					)
				);
			}

			$this->{$key} = $value;

			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r
			$this->utils->log( "Set '{$key}' to " . print_r( $value, true ) );

			// (Re)Load the settings for the specified sku
			if ( 'product_sku' === $key ) {
				$this->load_settings( $value );
			}

			return true;
		}

		/**
		 * Return all settings for a given license (SKU/Key)
		 *
		 * @param string $sku
		 *
		 * @throws NoLicenseKeyFound
		 * @return string[]
		 */
		public function get_settings( $sku = null ) {

			$excluded = apply_filters(
				'e20r_licensing_excluded',
				array(
					'e20r_default_license',
					'example_gateway_addon',
					'new_licenses',
				)
			);

			if ( null === $sku || in_array( $sku, $excluded, true ) ) {
				return array();
			}

			if ( ! isset( $this->settings[ $sku ] ) ) {
				throw new NoLicenseKeyFound( $sku );
			}

			return $this->settings[ $sku ];
		}

		/**
		 * Return the setting value for the key
		 *
		 * @param string $key
		 *
		 * @return null|mixed
		 */
		public function get( $key ) {

			if ( ! isset( $this->{$key} ) ) {
				$this->utils->log( "{$key} does not exist. Returning null!" );
				return null;
			}

			return $this->{$key};
		}
		/**
		 * Create a list (array) of settings with values for use by other function(s)
		 *
		 * @return array
		 */
		public function all_settings() {
			$reflection   = new \ReflectionClass( $this );
			$child_props  = $reflection->getProperties(
				\ReflectionProperty::IS_PUBLIC |
				\ReflectionProperty::IS_PROTECTED |
				\ReflectionProperty::IS_PRIVATE
			);
			$parent_props = $reflection->getParentClass()->getProperties(
				\ReflectionProperty::IS_PUBLIC |
				\ReflectionProperty::IS_PROTECTED
			);

			$settings = array();

			foreach ( $parent_props as $prop ) {
				$key = $prop->getName();
				if ( ! in_array( $key, $this->excluded, true ) ) {
					$settings[ $key ] = $this->{$key} ?? null;
				}
			}

			foreach ( $child_props as $prop ) {
				$key = $prop->getName();
				if ( ! in_array( $key, $this->excluded, true ) ) {
					$settings[ $key ] = $this->{$key} ?? null;
				}
			}

			return $settings;
		}

		/**
		 * Settings array for the License(s) on this system
		 *
		 * @param null|string $product_sku
		 *
		 * @return array
		 */
		public function defaults( ?string $product_sku = 'e20r_default_license' ): array {
			return $this->all_settings();
		}

		/**
		 * Prepare license settings for save operation
		 *
		 * @param array $input
		 *
		 * @return array
		 */
		public function validate( $input ) {

			global $current_user;

			if ( empty( $input['new_product'] ) && empty( $input['product'] ) && empty( $input['delete'] ) ) {
				if ( $this->to_debug ) {
					// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r
					$this->utils->log( 'Not being called by the E20R License settings page, so returning: ' . print_r( $input, true ) );
				}

				return $input;
			}

			/* phpcs:ignore Squiz.PHP.CommentedOutCode.Found
			if ( $this->to_debug ) {
				$this->utils->log( "Validation input (Add License on Purchase): " . print_r( $input, true ) );
			}
			*/
			$license_settings = $this->all_settings();

			// Save new license keys & activate the license
			if ( isset( $input['new_product'] ) && true === $this->utils->array_isnt_empty( $input['new_product'] ) ) {

				if ( $this->to_debug ) {
					// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r
					$this->utils->log( 'New product? ' . print_r( $input['new_product'], true ) );
					$this->utils->log( 'Processing a possible license activation' );
				}

				foreach ( $input['new_product'] as $nk => $product ) {

					if ( ! empty( $input['new_license'][ $nk ] ) ) {
						if ( $this->to_debug ) {
							$this->utils->log( "Processing license activation for {$input['new_license'][$nk]} " );
						}

						$license_key   = isset( $input['new_license'][ $nk ] ) ? $input['new_license'][ $nk ] : null;
						$license_email = isset( $input['new_email'][ $nk ] ) ? $input['new_email'][ $nk ] : null;
						$product       = isset( $input['new_product'][ $nk ] ) ? $input['new_product'][ $nk ] : null;

						$license_settings[ $product ]['first_name']    = isset( $current_user->first_name ) ? $current_user->first_name : null;
						$license_settings[ $product ]['last_name']     = isset( $current_user->last_name ) ? $current_user->last_name : null;
						$license_settings[ $product ]['fulltext_name'] = $input['fulltext_name'][ $nk ];
						$license_settings[ $product ]['product_sku']   = $input['product_sku'][ $nk ];

						$licensing = new License( $license_settings[ $product ]['product_sku'] );

						if ( ! empty( $license_email ) && ! empty( $license_key ) ) {

							if ( $this->to_debug ) {
								$this->utils->log( 'Have a license key and email, so activate the new license' );
							}

							$license_settings[ $product ]['email'] = $license_email;
							$license_settings[ $product ]['key']   = $license_key;

							if ( $this->to_debug ) {
								$this->utils->log( "Attempting remote activation for {$product} " );
							}

							$result = $licensing->activate( $product, $license_settings[ $product ] );

							if ( $this->to_debug ) {
								// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r
								$this->utils->log( "Status from activation {$result['status']} vs " . License::E20R_LICENSE_DOMAIN_ACTIVE . ' => ' . print_r( $result, true ) );
							}

							if ( License::E20R_LICENSE_DOMAIN_ACTIVE === intval( $result['status'] ) ) {

								if ( $this->to_debug ) {
									$this->utils->log( 'This license & server combination is already active on the Licensing server' );
								}

								if ( true === $licensing->deactivate( $product, $result['settings'] ) ) {

									if ( $this->to_debug ) {
										$this->utils->log( 'Was able to deactivate this license/host combination' );
									}

									$result = $licensing->activate( $product, $license_settings[ $product ] );

								}
							}

							if ( $this->to_debug ) {
								$this->utils->log( 'Loading updated settings from server' );
							}

							$server = new LicenseServer( $this->new_version, $this->ssl_verify );

							if ( true === $server->status( $product, $license_settings[ $product ], true ) ) {
								$result['settings'] = $this->merge( $product, $license_settings[ $product ] );
							}

							if ( isset( $result['settings']['status'] ) && 'active' !== $result['settings']['status'] ) {
								if ( $this->to_debug ) {
									$this->utils->log( "Error: Unable to activate license for {$product}!!!" );
								}
							} else {
								if ( $this->to_debug ) {
									// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r
									$this->utils->log( "Updating license for {$product} to: " . print_r( $result['settings'], true ) );
								}

								$license_settings[ $product ] = $result['settings'];

								if ( $this->to_debug ) {
									$this->utils->log( "Need to save license settings for {$product}" );
								}

								$license_settings = $this->update( $product, $license_settings[ $product ] );

								if ( false === $license_settings ) {
									if ( $this->to_debug ) {
										$this->utils->log( "Unable to save the {$product} settings!" );
									}
								}
							}
						}
					} else {
						if ( $this->to_debug ) {
							$this->utils->log( "No new license key specified for {$product}, nothing to save" );
						}
					}
				}
			}

			// Process licenses to deactivate/delete
			if ( isset( $input['delete'] ) && true === $this->utils->array_isnt_empty( $input['delete'] ) ) {

				foreach ( $input['delete'] as $dk => $l ) {

					$lk = array_search( $l, $input['license_key'], true );

					$this->utils->log( "License to deactivate: {$input['product'][$lk]}" );

					$product   = $input['product'][ $lk ];
					$licensing = new License( $product );
					$result    = $licensing->deactivate( $product );

					if ( false !== $result ) {

						$this->utils->log( "Successfully deactivated {$input['product'][ $lk ]} on remote server" );

						unset( $input['license_key'][ $lk ] );
						unset( $input['license_email'][ $lk ] );
						unset( $input['fieldname'][ $lk ] );
						unset( $input['fulltext_name'][ $lk ] );
						unset( $license_settings[ $product ] );
						unset( $input['product'][ $lk ] );
						unset( $input['product_sku'][ $lk ] );
					}
				}

				// Save cleared license updates
				if ( false === $this->update( null, $license_settings ) ) {
					$this->utils->log( 'Unable to save the settings!' );
				}
			}

			if ( $this->to_debug ) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r
				$this->utils->log( 'Returning validated settings: ' . print_r( $license_settings, true ) );
			}

			foreach ( $input as $license => $settings ) {

				if ( isset( $license_settings[ $license ] ) && in_array( 'domain', array_keys( $settings ), true ) ) {
					if ( $this->to_debug ) {
						$this->utils->log( 'Grabbing data from input and assigning it to license' );
					}

					$license_settings[ $license ] = $input[ $license ];
				}
			}

			return $license_settings;
		}

		/**
		 * Merge existing (or default) settings for the product with the new settings
		 *
		 * @param string $product_sku
		 * @param array  $new_settings
		 *
		 * @return array
		 */
		public function merge( $product_sku, $new_settings ) {

			$old_settings = $this->all_settings();

			/* phpcs:ignore Squiz.PHP.CommentedOutCode.Found
			if ( $this->to_debug ) {
				$this->utils->log( "Previously saved settings for {$product_sku}: " . print_r( $old_settings, true ) );
				$this->utils->log( "New - requested - settings: " . print_r( $new_settings, true ) );
			}
			*/

			if ( empty( $old_settings ) ) {
				$old_settings = $this->defaults();
			}

			foreach ( $new_settings as $key => $value ) {
				$old_settings[ $key ] = $value;
			}

			if ( $this->to_debug ) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r
				$this->utils->log( 'Updated settings (after merge...) ' . print_r( $old_settings, true ) );
			}

			return $old_settings;
		}

		/**
		 * Save the license settings
		 *
		 * @param string|null $product_sku
		 * @param array  $new_settings
		 *
		 * @return bool|array
		 */
		public function update( $product_sku, $new_settings ) {

			$license_settings = $this->all_settings();

			/* phpcs:ignore Squiz.PHP.CommentedOutCode.Found
			if ( $this->to_debug ) {
				$this->utils->log( "Settings before update: " . print_r( $license_settings, true ) );
				$this->utils->log( "NEW settings for {$product_sku}: " . print_r( $new_settings, true ) );
			}
			*/

			// Make sure the new settings make sense
			if ( is_array( $license_settings ) && in_array( 'fieldname', array_keys( $license_settings ), true ) ) {
				if ( $this->to_debug ) {
					$this->utils->log( "Unexpected settings layout while processing {$product_sku}!" );
				}
				$license_settings                 = $this->defaults();
				$license_settings[ $product_sku ] = $new_settings;
			}

			// Need to update the settings for a (possibly) pre-existing product
			if ( ! is_null( $product_sku ) && ! empty( $new_settings ) && ! in_array(
				$product_sku,
				array(
					'e20r_default_license',
					'example_gateway_addon',
				),
				true
			) && ! empty( $product_sku )
			) {

				$license_settings[ $product_sku ] = $new_settings;
				if ( $this->to_debug ) {
					$this->utils->log( "Updating license settings for {$product_sku}" );
				}
			} elseif ( ! is_null( $product_sku ) && empty( $new_settings ) && ( ! in_array(
				$product_sku,
				array(
					'e20r_default_license',
					'example_gateway_addon',
				),
				true
			) && ! empty( $product_sku ) )
			) {
				if ( $this->to_debug ) {
					$this->utils->log( "Removing license settings for {$product_sku}" );
				}
				unset( $license_settings[ $product_sku ] );

			} else {
				if ( $this->to_debug ) {
					$this->utils->log( 'Requested save of everything' );
				}
			}

			if ( $this->to_debug ) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r
				$this->utils->log( 'Saving: ' . print_r( $license_settings, true ) );
			}

			update_option( 'e20r_license_settings', $license_settings, 'yes' );

			return $license_settings;
		}
	}
}
