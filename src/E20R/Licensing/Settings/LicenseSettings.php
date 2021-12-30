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
 *
 * @package E20R\Licensing\Settings\LicensSettings
 */

namespace E20R\Licensing\Settings;

use E20R\Licensing\Exceptions\ConfigDataNotFound;
use E20R\Licensing\Exceptions\DefinedByConstant;
use E20R\Licensing\Exceptions\ErrorSavingSettings;
use E20R\Licensing\Exceptions\InvalidSettingsKey;
use E20R\Licensing\Exceptions\InvalidSettingsVersion;
use E20R\Licensing\Exceptions\MissingServerURL;
use E20R\Licensing\Exceptions\NoLicenseKeyFound;
use E20R\Licensing\Exceptions\ServerConnectionError;
use E20R\Licensing\Exceptions\BadOperation;
use E20R\Licensing\LicenseServer;
use E20R\Licensing\License;
use E20R\Utilities\Message;
use E20R\Utilities\Utilities;
use ReflectionException;

// Deny direct access to the file
if ( ! defined( 'ABSPATH' ) && function_exists( 'wp_die' ) ) {
	wp_die( 'Cannot access file directly' );
}

if ( ! defined( 'E20R_MISSING_SETTING' ) ) {
	define( 'E20R_MISSING_SETTING', 1024 );
}

if ( ! class_exists( '\E20R\Utilities\Licensing\LicenseSettings' ) ) {
	/**
	 * Settings class for the Licensing logic
	 */
	class LicenseSettings {

		/**
		 * All settings for all processed licenses
		 *
		 * @var array|false|void $all_settings
		 */
		protected $all_settings = array();

		/**
		 * Utilities class instance
		 *
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
		 * The Domain name of the server where we're running
		 *
		 * @var string $domain_name
		 */
		protected $domain_name = '';

		/**
		 * The chosen license settings class (old or new style)
		 *
		 * @var null|NewSettings|OldSettings
		 */
		private $license_request_settings = null;

		/**
		 * The default settings for Licensing
		 *
		 * @var Defaults|mixed|null
		 */
		private $plugin_defaults;

		/**
		 * LicenseSettings constructor.
		 *
		 * @param string|null                  $product_sku     The SKU in the WooCommerce store for the product
		 * @param Defaults|null                $plugin_defaults The default settings for this plugin
		 * @param Utilities|null               $utils           The utilities class
		 * @param NewSettings|OldSettings|null $settings_class  The actual settings class
		 *
		 * @throws MissingServerURL Raised when the URL for a License server is missing
		 * @throws InvalidSettingsKey Raised when an invalid key for the settings version is being used
		 * @throws ConfigDataNotFound Raised when the config data (JSON blob) is missing/unused
		 * @throws BadOperation Raised if the lock operation fails for a default setting
		 * @throws InvalidSettingsVersion Raised if we attempt to instantiate the wrong settings class for the licensing code
		 */
		public function __construct( $product_sku = 'e20r_default_license', $plugin_defaults = null, $utils = null, $settings_class = null ) {

			if ( empty( $product_sku ) ) {
				$product_sku = 'e20r_default_license';
			}

			if ( empty( $utils ) ) {
				$messages = new Message();
				$utils    = new Utilities( $messages );
			}
			$this->utils = $utils;

			if ( empty( $plugin_defaults ) ) {
				try {
					$plugin_defaults = new Defaults( true, $this->utils );
				} catch ( ConfigDataNotFound | InvalidSettingsKey $e ) {
					$this->utils->log( 'Cannot load settings: ' . $e->getMessage() );
					throw $e;
				}
			}

			$this->domain_name     = apply_filters(
				'e20r_license_domain_to_license',
				filter_var( wp_unslash( $_SERVER['SERVER_NAME'] ), FILTER_SANITIZE_URL ) ?? 'localhost' // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotValidated
			);
			$this->product_sku     = $product_sku;
			$this->plugin_defaults = $plugin_defaults;
			$this->update_plugin_defaults();

			// By locking the defaults here we make sure it's possible to test with different default settings
			try {
				$this->plugin_defaults->lock( 'debug_logging' );
				$this->plugin_defaults->lock( 'server_url' );
				$this->plugin_defaults->lock( 'version' );
			} catch ( BadOperation $e ) {
				$this->utils->log( 'Error locking settings: ' . $e->getMessage() );
				throw $e;
			}
			$this->excluded = array(
				'excluded',
				'utils',
				'page_handle',
				'settings',
				'all_settings',
				'instance',
				'page',
				'plugin_defaults',
				'new_version',
			);

			if ( empty( $settings_class ) ) {
				try {
					if ( $this->new_version ) {
						$this->license_request_settings = new NewSettings( $this->product_sku );
					} else {
						$this->license_request_settings = new OldSettings( $this->product_sku );
					}
				} catch ( InvalidSettingsVersion $e ) {
					$this->utils->log( 'Attempted to use an unexpected settings version: ' . $e->getMessage() );
					throw $e;
				}
			} else {
				$this->license_request_settings = $settings_class;
			}

			// Create setting(s) objets for all saved setting(s)
			$defaults           = $this->license_request_settings->defaults();
			$this->all_settings = get_option( 'e20r_license_settings', array() );
			$server_url         = $this->plugin_defaults->get( 'server_url' );

			// This could happen if the settings are saved incorrectly
			if ( ! is_array( $this->all_settings ) ) {
				$this->all_settings = array();
			}

			if ( ! isset( $this->all_settings[ $this->product_sku ] ) ) {
				$this->utils->log( "Warning: No {$this->product_sku} specific settings were loaded. Using default values" );
				$this->all_settings[ $this->product_sku ] = $defaults;
			}

			$this->utils->log( "Loading settings for {$this->product_sku}" );
			foreach ( $this->all_settings[ $this->product_sku ] as $key => $value ) {
				$this->license_request_settings->set( $key, $value );
			}

			if (
				empty( $server_url ) ||
				1 !== preg_match( '/^https?:\/\/([0-9a-zA-Z].*)(?:..)?([0-9a-zA-Z].*)(?:..)?/', $server_url )
			) {
				$msg = "Error: Haven't configured the license server URL, or the URL is malformed. Can be configured in the wp-config.php file.";
				$this->utils->log( $msg );
				$this->utils->add_message(
					esc_attr__(
						'Error: The license server URL is unknown, or the URL is malformed! Add a correct URL in your wp-config.php file. Example: define( "E20R_LICENSE_SERVER_URL", "https://eighty20results.com/" )',
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
		 * @throws InvalidSettingsKey - Raised when attempting to use an invalid settings key for the configured version
		 */
		public function update_plugin_defaults() {
			try {
				$this->to_debug = (bool) $this->plugin_defaults->get( 'debug_logging' );
			} catch ( InvalidSettingsKey $e ) {
				$this->utils->log( 'Error: Unable to save to_debug setting: ' . $e->getMessage() );
				throw $e;
			}

			if ( isset( $_SERVER['HTTP_HOST'] ) && 'eighty20results.com' === filter_var( wp_unslash( $_SERVER['HTTP_HOST'] ), FILTER_SANITIZE_URL ) ) {
				$this->utils->log( 'Running on Licensing server. Deactivating SSL Verification for loop-back connections' );
				$this->ssl_verify = false;
			}

			// Determine whether we're using the new or old Licensing version
			try {
				$licensing_version = $this->plugin_defaults->get( 'version' );
				$this->new_version = version_compare( $licensing_version, '3.0', 'ge' );
			} catch ( InvalidSettingsKey $e ) {
				$this->utils->log( 'Error: Unable to fetch the version information for this plugin!' );
				$this->utils->add_message(
					esc_attr__(
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
		 * @param string|null $product_sku WooCommerce SKU for the licensed product to process
		 * @param array|null  $settings An array of settings to use for the licensing code
		 * @return array|null
		 *
		 * @throws InvalidSettingsKey Raised when an invalid settings key is attempted used for the version of the settings
		 * @throws ReflectionException Generic exception raised when interrogating an existing class' properties and methods
		 */
		public function load_settings( ?string $product_sku = null, ?array $settings = null ) {

			if ( empty( $product_sku ) ) {
				if ( $this->to_debug ) {
					$this->utils->log( 'No product key provided. Using default key (e20r_default_license)!' );
				}
				$product_sku = 'e20r_default_license';
			}

			$this->product_sku  = $product_sku;
			$defaults           = $this->license_request_settings->defaults();
			$this->all_settings = get_option( 'e20r_license_settings', array() );

			// $product_sku  = strtolower( $product_sku ); phpcs:ignore Squiz.PHP.CommentedOutCode.Found

			if ( empty( $this->all_settings[ $this->product_sku ] ) || (
				( 1 <= count( array_keys( $this->all_settings[ $this->product_sku ] ) ) && 'e20r_default_license' === $product_sku ) )
			) {
				$this->utils->log( 'Overwriting license settings with defaults' );
				$this->all_settings[ $this->product_sku ] = $defaults;
				$this->save();
			}

			if ( ! empty( $settings ) && is_array( $settings ) ) {
				foreach ( $settings as $key => $value ) {
					$this->license_request_settings->set( $key, $value );
				}
				$this->all_settings[ $this->product_sku ] = $this->license_request_settings->all();
			}

			if ( $this->to_debug ) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r
				$this->utils->log( 'All settings: ' . print_r( $this->all_settings, true ) );
			}

			if ( 'e20r_default_license' === $product_sku || empty( $product_sku ) ) {
				if ( $this->to_debug ) {
					$this->utils->log(
						"No product, or default product specified, so returning all settings: {$product_sku}"
					);
				}

				return $this->all_settings;
			}

			if ( $this->to_debug ) {
				$this->utils->log( "Requested and returning settings for {$product_sku}" );
			}

			return $this->all_settings[ $this->product_sku ];
		}

		/**
		 * Set the license setting property
		 *
		 * @param string     $key   License setting key (name of property)
		 * @param null|mixed $value License setting value (value of property)
		 *
		 * @return bool
		 *
		 * @throws InvalidSettingsKey Raised when an invalid settings key is used for a given license plugin version
		 * @throws BadOperation Raised if an unsupported operation is attempted against a constant
		 * @throws DefinedByConstant Raised when trying to change a constant using the parameter settings method
		 * @throws ReflectionException Default exception while processing the settings class by version
		 * phpcs:ignore Squiz.Commenting.FunctionCommentThrowTag.WrongNumber
		 */
		public function set( string $key, $value = null ): bool {

			if ( 'plugin_defaults' === $key && defined( 'PLUGIN_PHPUNIT' ) && PLUGIN_PHPUNIT ) {
				$this->utils->log( 'Warning: Intentionally overriding the plugin default settings' );
				$this->plugin_defaults = $value;
				$this->update_plugin_defaults();
				return true;
			}

			if ( $this->is_request_setting( $key ) ) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r
				$this->utils->log( "Set the request setting '{$key}' to " . print_r( $value, true ) );
				$this->license_request_settings->set( $key, $value );
			}

			if ( $this->is_setting( $key ) ) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r
				$this->utils->log( "Set '{$key}' to " . print_r( $value, true ) );
				$this->{$key} = $value;
			}

			if ( $this->is_default_setting( $key ) ) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r
				$this->utils->log( "Attempt to set Defaults->{$key} to " . print_r( $value, true ) );
				try {
					$this->plugin_defaults->set( $key, $value );
				} catch ( InvalidSettingsKey | BadOperation | DefinedByConstant $e ) {
					$this->utils->log( "Error setting {$key}: " . $e->getMessage() );
					throw $e;
				}
			}

			if ( in_array( $key, $this->excluded, true ) ) {
				throw new InvalidSettingsKey(
					sprintf(
					// translators: %1$s - Key name for the failed setting update, %2$s - Class name
						esc_attr__( '"%1$s" is not a valid property for %2$s', '00-e20r-utilities' ),
						$key,
						__CLASS__
					)
				);
			}

			// (Re)Load the settings for the specified sku
			if ( 'product_sku' === $key ) {
				$this->load_settings( $value );
			}

			return true;
		}

		/**
		 * Does the specified property belong to the NewSettings|OldSettings class
		 *
		 * @param string $property The settings property to test
		 *
		 * @return bool
		 */
		private function is_request_setting( $property ) {
			return property_exists( $this->license_request_settings, $property );
		}

		/**
		 * Does the specified property belong to the Defaults class
		 *
		 * @param string $property The settings property to check whether is a default setting or not
		 *
		 * @return bool
		 */
		private function is_default_setting( $property ) {
			return property_exists( $this->plugin_defaults, $property );
		}

		/**
		 * Does the specified property belong to this class
		 *
		 * @param string $property The key to test whether it is a settings property or not
		 *
		 * @return bool
		 */
		private function is_setting( $property ) {
			return property_exists( $this, $property ) || 'license_key' === $property;
		}

		/**
		 * Return all settings for a given license (SKU/Key)
		 *
		 * @param string $sku WooCommerce store SKU for the licensed product we're processing
		 *
		 * @throws NoLicenseKeyFound The SKU doesn't have a license key we can use
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

			if ( ! isset( $this->all_settings[ $sku ] ) ) {
				throw new NoLicenseKeyFound( $sku );
			}

			return $this->all_settings[ $sku ];
		}

		/**
		 * Return the setting value for the key
		 *
		 * @param string $key The license key we're returning settings for
		 *
		 * @return null|mixed
		 * @throws InvalidSettingsKey - The specified key doesn't exist
		 */
		public function get( $key ) {
			$value = null;
			if (
				'license_key' !== $key &&
				! property_exists( $this, $key ) &&
				! property_exists( $this->license_request_settings, $key ) &&
				! property_exists( $this->plugin_defaults, $key )
			) {
				$this->utils->log( "{$key} does not exist. Returning null!" );
				throw new InvalidSettingsKey(
					sprintf(
						// translators: %1$s - The parameter name
						esc_attr__( 'Cannot find a setting named "%1$s"', '00-e20r-utilities' ),
						$key
					)
				);
			}

			if ( $this->is_setting( $key ) ) {
				if ( 'license_key' === $key ) {
					if ( $this->new_version ) {
						$value = $this->license_request_settings->get( 'the_key' );
						$this->utils->log( 'Using "the_key" to obtain a license key and returning ' . $value );
					} else {
						$value = $this->license_request_settings->get( 'key' );
						$this->utils->log( 'Using "key" to obtain a license key and returning ' . $value );
					}
				} else {
					$value = $this->{$key};
				}
			}

			if ( $this->is_request_setting( $key ) ) {
				$this->utils->log( "Loading '{$key}' from the version specific settings class" );
				$value = $this->license_request_settings->get( $key );
			}

			if ( $this->is_default_setting( $key ) ) {
				$this->utils->log( "Loading '{$key}' from the default settings" );
				$value = $this->plugin_defaults->get( $key );
			}

			return $value;
		}

		/**
		 * Create a list (array) of settings with values for use by other function(s)
		 *
		 * @return array
		 * @throws InvalidSettingsKey | ReflectionException Raised when the user attempts to fetch an invalid settings property
		 */
		public function all_settings() {
			$properties = $this->license_request_settings->get_properties();

			if ( empty( $this->all_settings[ $this->product_sku ] ) ) {
				$this->all_settings[ $this->product_sku ] = array();
			}

			foreach ( $properties as $property ) {
				try {
					$this->all_settings[ $this->product_sku ][ $property ] = $this->license_request_settings->get( $property );
				} catch ( InvalidSettingsKey $e ) {
					throw $e;
				}
			}

			return $this->all_settings;
		}

		/**
		 * Defaults settings for the License(s) on this system
		 *
		 * @return array
		 */
		public function defaults(): array {
			return $this->license_request_settings->defaults();
		}

		/**
		 * Prepare license settings for save operation
		 *
		 * @param array $input Settings from the WP Settings API validation hook
		 *
		 * @return array
		 *
		 * @throws NoLicenseKeyFound Thrown when the license key is not found on the Licensing server
		 * @throws ErrorSavingSettings Thrown when we're unable to save the product specific license settings
		 * @throws ConfigDataNotFound Thrown when we cannot find the config data for the store/url/etc to the licensing server
		 * @throws MissingServerURL Thrown when the URL to connect to the Licensing Server plugin is incorrect
		 * @throws InvalidSettingsKey Thrown when a settings key is specified for the wrong version of the Settings class
		 * @throws InvalidSettingsVersion Thrown when the old version of the licensing plugin is being assumed and the new is needed, and vice versa.
		 * @throws ServerConnectionError Thrown when the server with the Licensing plugin is unreachable
		 * @throws BadOperation | ReflectionException Thrown when attempting a disallowed operation on a setting/constant
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

						try {
							$licensing = new License( $license_settings[ $product ]['product_sku'] );
						} catch ( InvalidSettingsKey | InvalidSettingsVersion | MissingServerURL | ConfigDataNotFound | BadOperation $e ) {
							$this->utils->log( $e->getMessage() );
							throw $e;
						}

						if ( ! empty( $license_email ) && ! empty( $license_key ) ) {

							if ( $this->to_debug ) {
								$this->utils->log( 'Have a license key and email, so activate the new license' );
							}

							$license_settings[ $product ]['email'] = $license_email;
							$license_settings[ $product ]['key']   = $license_key;

							if ( $this->to_debug ) {
								$this->utils->log( "Attempting remote activation for {$product} " );
							}

							$result          = $licensing->activate( $product );
							$active_constant = $this->plugin_defaults->constant( 'E20R_LICENSE_DOMAIN_ACTIVE' );

							if ( $this->to_debug ) {
								// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r
								$this->utils->log( "Status from activation {$result['status']} vs " . $active_constant . ' => ' . print_r( $result, true ) );
							}

							if ( intval( $result['status'] ) === $active_constant ) {

								if ( $this->to_debug ) {
									$this->utils->log( 'This license & server combination is already active on the Licensing server' );
								}

								if ( true === $licensing->deactivate( $product, $result['settings'] ) ) {

									if ( $this->to_debug ) {
										$this->utils->log( 'Was able to deactivate this license/host combination' );
									}
									try {
										$result = $licensing->activate( $product );
									} catch ( InvalidSettingsKey | ServerConnectionError | InvalidSettingsVersion | BadOperation | MissingServerURL | ConfigDataNotFound $e ) {
										$this->utils->log( $e->getMessage() );
										throw $e;
									}
								}
							}

							if ( $this->to_debug ) {
								$this->utils->log( 'Loading updated settings from server' );
							}

							try {
								$server = new LicenseServer( $this );
							} catch ( InvalidSettingsKey $e ) {
								$this->utils->log( $e->getMessage() );
								throw $e;
							}

							if ( true === $server->status( $product, true ) ) {
								try {
									$settings_obj = $this->merge( $license_settings[ $product ] );
								} catch ( ErrorSavingSettings $e ) {
									$this->utils->log( $e->getMessage() );
									throw $e;
								}

								try {
									$results['settings'] = $settings_obj->get_settings( $product );
								} catch ( NoLicenseKeyFound $e ) {
									$this->utils->log( $e->getMessage() );
									throw $e;
								}
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

					$this->utils->log( "License to deactivate (key: ${$dk}): {$input['product'][$lk]}" );

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
		 * @param array $new_settings The settings we'd like to add/update
		 *
		 * @return LicenseSettings
		 *
		 * @throws ErrorSavingSettings Thrown when we cannot save the License specific settings for the product
		 * @throws ReflectionException Thrown when there are problems identifying parameters for the New or Old Settings class
		 */
		public function merge( $new_settings ) {

			try {
				$old_settings = $this->license_request_settings->all();
			} catch ( ReflectionException $e ) {
				$this->utils->log( $e->getMessage() );
				throw $e;
			}

			/* phpcs:ignore Squiz.PHP.CommentedOutCode.Found
			if ( $this->to_debug ) {
				$this->utils->log( "Previously saved settings for {$product_sku}: " . print_r( $old_settings, true ) );
				$this->utils->log( "New - requested - settings: " . print_r( $new_settings, true ) );
			}
			*/

			if ( empty( $old_settings ) ) {
				$old_settings = $this->license_request_settings->defaults();
			}

			// Assign new settings to
			if ( is_array( $new_settings ) ) {
				$this->utils->log( 'Updating previous settings with new ones' );
				foreach ( $new_settings as $key => $value ) {
					$old_settings[ $key ] = $value;
				}
			}

			if ( $this->to_debug ) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r
				$this->utils->log( 'Updated settings (after merge...) ' . print_r( $old_settings, true ) );
			}

			// Update settings in class
			foreach ( $old_settings as $key => $value ) {
				try {
					$this->set( $key, $value );
				} catch ( InvalidSettingsKey | BadOperation | DefinedByConstant | ReflectionException $e ) {
					$this->utils->log( $e->getMessage() );
					throw new ErrorSavingSettings( $e->getMessage() );
				}
			}
			return $this; // FIXME: Don't return self!
		}

		/**
		 * Keep for backwards compatibility reasons
		 *
		 * @param string|null $sku The WooCommerce product SKU for the licensed software
		 * @param array       $settings The License settings to update
		 *
		 * @return bool
		 */
		public function update( $sku = null, $settings = null ) {
			_deprecated_function( 'License::update()', '2.2', 'License::save()' );
			if ( ! empty( $settings ) ) {
				try {
					$this->merge( $settings );
				} catch ( ErrorSavingSettings | ReflectionException $e ) {
					$this->utils->log( sprintf( 'Error for %1$s: %2$s', $sku, $e->getMessage() ) );
					$this->utils->add_message( 'Error: ' . $e->getMessage(), 'error', 'backend' );
					return false;
				}
			}
			return $this->save();
		}

		/**
		 * Save the license settings
		 *
		 * @return bool
		 */
		public function save() {
			// TODO: This function needs to load the existing settings from the DB and then and saves the current setting

			try {
				$license_settings = $this->all_settings();
			} catch ( InvalidSettingsKey | ReflectionException $e ) {
				$this->utils->add_message(
					sprintf(
						// translators: %1$s - The exception error message
						esc_attr__( 'Error saving license settings: %1$s', '00-e20r-utilities' ),
						$e->getMessage()
					),
					'error',
					'backend'
				);
				$license_settings = array();
			}

			/* phpcs:ignore Squiz.PHP.CommentedOutCode.Found
			if ( $this->to_debug ) {
				$this->utils->log( "Settings before update: " . print_r( $license_settings, true ) );
				$this->utils->log( "NEW settings for {$product_sku}: " . print_r( $new_settings, true ) );
			}
			*/

			// Make sure the new settings make sense
			if ( is_array( $license_settings ) && in_array( 'fieldname', array_keys( $license_settings ), true ) ) {
				if ( $this->to_debug ) {
					$this->utils->log( "Unexpected settings layout while processing {$this->product_sku}!" );
				}
				$license_settings[ $this->product_sku ] = $this->license_request_settings->defaults();
			}

			// Need to update the settings for a (possibly) pre-existing product
			if ( ! is_null( $this->product_sku ) && ! empty( $new_settings ) && ! in_array(
				$this->product_sku,
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
			} elseif ( ! is_null( $this->product_sku ) && empty( $new_settings ) && ( ! in_array(
				$this->product_sku,
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

			return update_option( 'e20r_license_settings', $license_settings, 'yes' );
		}
	}
}
