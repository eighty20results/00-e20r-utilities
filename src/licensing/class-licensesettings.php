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

namespace E20R\Utilities\Licensing;

use E20R\Utilities\Licensing\Exceptions\NoLicenseKeyFoundException;
use E20R\Utilities\Utilities;

// Deny direct access to the file
if ( ! defined( 'ABSPATH' ) ) {
	die( 'Cannot access file directly' );
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
		private $all_settings = array();

		/**
		 * Current instance of the settings
		 *
		 * @var LicenseSettings|NewLicenseSettings|OldLicenseSettings|null
		 */
		private $settings = null;

		/**
		 * The handle to the License Page (in WordPress)
		 *
		 * @var null|mixed $page_handle
		 */
		private $page_handle = null;

		/**
		 * @var null|LicenseSettings
		 */
		private $utils = null;

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
		 * LicenseSettings constructor.
		 *
		 * @param string|null $product_sku
		 */
		public function __construct( $product_sku = 'e20r_default_license' ) {

			$this->utils       = Utilities::get_instance();
			$this->product_sku = $product_sku;
			$this->excluded    = array( 'excluded', 'utils', 'page_handle', 'settings', 'all_settings' );

			if ( ! defined( 'E20R_LICENSE_SERVER_URL' ) || ( defined( 'E20R_LICENSE_SERVER_URL' ) && ! E20R_LICENSE_SERVER_URL ) ) {
				$this->utils->log( "Error: Haven't added the 'E20R_LICENSE_SERVER_URL' constant to the wp-config file!" );
				$this->utils->add_message(
					__(
						'Error: The E20R_LICENSE_SERVER_URL definition is missing! Please add it to the wp-config.php file.',
						// phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralDomain
						'e20r-licensing'
					),
					'error',
					'backend'
				);

				return null;
			}

			if ( ! empty( $this->product_sku ) ) {
				$defaults           = $this->defaults( $product_sku );
				$this->all_settings = get_option( 'e20r_license_settings', $defaults );
				$this->settings     = $this->all_settings[ $product_sku ];
			}
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
			$licensing = new Licensing();

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
			$settings        = apply_filters( 'e20r-license-add-new-licenses', $this->all_settings(), array() );
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

				if ( ! in_array( $product_sku, array( 'example_gateway_addon', 'new_licenses' ), true ) &&
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
						$key         = ( ! $licensing->is_new_version() && isset( $license['license_key'] ) ?
							$license['license_key'] :
							( isset( $license['product_sku'] ) ? $license['product_sku'] : null )

						);

						$is_active = $licensing->is_active(
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

					if ( $licensing->is_new_version() ) {

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
							'value'            => ( $licensing->is_new_version() && isset( $license['the_key'] ) ? $license['the_key'] : isset( $license['key'] ) ) ? $license['key'] : null,
							'email_field'      => 'license_email',
							'product_sku'      => $licensing->is_new_version() && isset( $license['product_sku'] ) ?
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
				apply_filters( 'e20r-license-add-new-licenses', array() );

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

		/**
		 * Set the license setting property
		 *
		 * @param string      $key      License setting key (name of property)
		 * @param null|string $value    License setting value (value of property)
		 *
		 * @return bool
		 *
		 * @throws \Exception
		 */
		public function set( $key, $value = null ) {

			if ( ! isset( $this->{$key} ) ) {
				$this->utils->log( "Error: '${key}' does not exist!" );
				throw new \Exception(
					sprintf( 'Error: The %1$s setting does not exists', $key ),
					E20R_MISSING_SETTING
				);
			}

			$this->{$key} = $value;
			// phpcs:ignore
			$this->utils->log( "Set '${key}' to " . print_r( $value, true ) );

			return true;
		}

		public function get_settings( $sku ) {

			if ( ! isset( $this->settings[$sku] ) ) {
				throw new NoLicenseKeyFoundException( $sku );
			}
		}
		/**
		 *
		 * Load local settings for the specified product
		 *
		 * @param string $product_sku
		 *
		 * @return array
		 * @throws \Exception
		 */
		public function load_settings( $product_sku = null ) {

			$licensing = new Licensing();

			if ( is_null( $product_sku ) ) {
				if ( defined( 'E20R_LICENSING_DEBUG' ) && true === E20R_LICENSING_DEBUG ) {
					$this->utils->log( 'No product key provided. Using default key (e20r_default_license)!' );
				}
				$this->product_sku = 'e20r_default_license';
			}

			// $product_sku  = strtolower( $product_sku ); phpcs:ignore Squiz.PHP.CommentedOutCode.Found
			$defaults = $this->defaults( $product_sku );
			$settings = get_option( 'e20r_license_settings', $defaults );

			if ( empty( $settings ) || (
				1 <= count( $settings ) && 'e20r_default_license' === $product_sku )
			) {
				$this->utils->log( 'Overwriting license settings with defaults' );
				$settings = $defaults;
			}

			if ( $licensing->is_new_version() ) {
				$this->settings = new NewLicenseSettings( $product_sku );
			} else {
				$this->settings = new OldLicenseSettings( $product_sku );
			}

			foreach ( $settings as $setting_key => $value ) {

				$this->settings->set( $setting_key, $value );
			}

			if ( defined( 'E20R_LICENSING_DEBUG' ) && true === E20R_LICENSING_DEBUG ) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r
				$this->utils->log( 'All settings: ' . print_r( $settings, true ) );
			}

			if ( 'e20r_default_license' === $product_sku || empty( $product_sku ) ) {
				if ( defined( 'E20R_LICENSING_DEBUG' ) && true === E20R_LICENSING_DEBUG ) {
					$this->utils->log(
						"No product, or default product specified, so returning all settings: {$product_sku}"
					);
				}

				return $settings;
			}

			if ( defined( 'E20R_LICENSING_DEBUG' ) && true === E20R_LICENSING_DEBUG ) {
				$this->utils->log( "Requested and returning settings for {$product_sku}" );
			}

			if ( empty( $this->settings->get( 'product_sku' ) ) ) {
				return null;
			}

			return $this->settings->all_settings();
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

			$settings = array();

			foreach ( $this as $key => $value ) {
				if ( ! in_array( $key, $this->excluded, true ) ) {
					$settings[ $key ] = $value;
				}
			}

			return $settings;
		}
		/**
		 * Settings array for the License(s) on this system
		 *
		 * @param string $product_sku
		 *
		 * @return array
		 */
		public function defaults( $product_sku = 'e20r_default_license' ) {

			$this->product_sku = $product_sku;
			$this->settings->all_settings();
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
				if ( defined( 'E20R_LICENSING_DEBUG' ) && true === E20R_LICENSING_DEBUG ) {
					// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r
					$this->utils->log( 'Not being called by the E20R License settings page, so returning: ' . print_r( $input, true ) );
				}

				return $input;
			}

			/* phpcs:ignore Squiz.PHP.CommentedOutCode.Found
			if ( defined( 'E20R_LICENSING_DEBUG' ) && true === E20R_LICENSING_DEBUG ) {
				$this->utils->log( "Validation input (Add License on Purchase): " . print_r( $input, true ) );
			}
			*/
			$license_settings = $this->all_settings();

			// Save new license keys & activate the license
			if ( isset( $input['new_product'] ) && true === $this->utils->array_isnt_empty( $input['new_product'] ) ) {

				if ( defined( 'E20R_LICENSING_DEBUG' ) && true === E20R_LICENSING_DEBUG ) {
					// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r
					$this->utils->log( 'New product? ' . print_r( $input['new_product'], true ) );
					$this->utils->log( 'Processing a possible license activation' );
				}

				foreach ( $input['new_product'] as $nk => $product ) {

					if ( ! empty( $input['new_license'][ $nk ] ) ) {
						if ( defined( 'E20R_LICENSING_DEBUG' ) && true === E20R_LICENSING_DEBUG ) {
							$this->utils->log( "Processing license activation for {$input['new_license'][$nk]} " );
						}

						$license_key   = isset( $input['new_license'][ $nk ] ) ? $input['new_license'][ $nk ] : null;
						$license_email = isset( $input['new_email'][ $nk ] ) ? $input['new_email'][ $nk ] : null;
						$product       = isset( $input['new_product'][ $nk ] ) ? $input['new_product'][ $nk ] : null;

						$license_settings[ $product ]['first_name']    = isset( $current_user->first_name ) ? $current_user->first_name : null;
						$license_settings[ $product ]['last_name']     = isset( $current_user->last_name ) ? $current_user->last_name : null;
						$license_settings[ $product ]['fulltext_name'] = $input['fulltext_name'][ $nk ];
						$license_settings[ $product ]['product_sku']   = $input['product_sku'][ $nk ];

						if ( ! empty( $license_email ) && ! empty( $license_key ) ) {

							if ( defined( 'E20R_LICENSING_DEBUG' ) && true === E20R_LICENSING_DEBUG ) {
								$this->utils->log( 'Have a license key and email, so activate the new license' );
							}

							$license_settings[ $product ]['email'] = $license_email;
							$license_settings[ $product ]['key']   = $license_key;

							if ( defined( 'E20R_LICENSING_DEBUG' ) && true === E20R_LICENSING_DEBUG ) {
								$this->utils->log( "Attempting remote activation for {$product} " );
							}

							$result = Licensing::activate( $product, $license_settings[ $product ] );

							if ( defined( 'E20R_LICENSING_DEBUG' ) && true === E20R_LICENSING_DEBUG ) {
								// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r
								$this->utils->log( "Status from activation {$result['status']} vs " . Licensing::E20R_LICENSE_DOMAIN_ACTIVE . ' => ' . print_r( $result, true ) );
							}

							if ( Licensing::E20R_LICENSE_DOMAIN_ACTIVE === intval( $result['status'] ) ) {

								if ( defined( 'E20R_LICENSING_DEBUG' ) && true === E20R_LICENSING_DEBUG ) {
									$this->utils->log( 'This license & server combination is already active on the licensing server' );
								}

								if ( true === Licensing::deactivate( $product, $result['settings'] ) ) {

									if ( defined( 'E20R_LICENSING_DEBUG' ) && true === E20R_LICENSING_DEBUG ) {
										$this->utils->log( 'Was able to deactivate this license/host combination' );
									}

									$result = Licensing::activate( $product, $license_settings[ $product ] );

								}
							}

							if ( defined( 'E20R_LICENSING_DEBUG' ) && true === E20R_LICENSING_DEBUG ) {
								$this->utils->log( 'Loading updated settings from server' );
							}

							if ( true === LicenseServer::status( $product, $license_settings[ $product ], true ) ) {
								$result['settings'] = self::merge( $product, $license_settings[ $product ] );
							}

							if ( isset( $result['settings']['status'] ) && 'active' !== $result['settings']['status'] ) {
								if ( defined( 'E20R_LICENSING_DEBUG' ) && true === E20R_LICENSING_DEBUG ) {
									$this->utils->log( "Error: Unable to activate license for {$product}!!!" );
								}
							} else {
								if ( defined( 'E20R_LICENSING_DEBUG' ) && true === E20R_LICENSING_DEBUG ) {
									// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r
									$this->utils->log( "Updating license for {$product} to: " . print_r( $result['settings'], true ) );
								}

								$license_settings[ $product ] = $result['settings'];

								if ( defined( 'E20R_LICENSING_DEBUG' ) && true === E20R_LICENSING_DEBUG ) {
									$this->utils->log( "Need to save license settings for {$product}" );
								}

								$license_settings = self::update( $product, $license_settings[ $product ] );

								if ( false === $license_settings ) {
									if ( defined( 'E20R_LICENSING_DEBUG' ) && true === E20R_LICENSING_DEBUG ) {
										$this->utils->log( "Unable to save the {$product} settings!" );
									}
								}
							}
						}
					} else {
						if ( defined( 'E20R_LICENSING_DEBUG' ) && true === E20R_LICENSING_DEBUG ) {
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
					$product = $input['product'][ $lk ];

					$result = Licensing::deactivate( $product );

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
				if ( false === self::update( null, $license_settings ) ) {
					$this->utils->log( 'Unable to save the settings!' );
				}
			}

			if ( defined( 'E20R_LICENSING_DEBUG' ) && true === E20R_LICENSING_DEBUG ) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r
				$this->utils->log( 'Returning validated settings: ' . print_r( $license_settings, true ) );
			}

			foreach ( $input as $license => $settings ) {

				if ( isset( $license_settings[ $license ] ) && in_array( 'domain', array_keys( $settings ), true ) ) {
					if ( defined( 'E20R_LICENSING_DEBUG' ) && true === E20R_LICENSING_DEBUG ) {
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

			$old_settings = $this->all_settings( $product_sku );

			/* phpcs:ignore Squiz.PHP.CommentedOutCode.Found
			if ( defined( 'E20R_LICENSING_DEBUG' ) && true === E20R_LICENSING_DEBUG ) {
				$this->utils->log( "Previously saved settings for {$product_sku}: " . print_r( $old_settings, true ) );
				$this->utils->log( "New - requested - settings: " . print_r( $new_settings, true ) );
			}
			*/

			if ( empty( $old_settings ) ) {
				$old_settings = self::defaults();
			}

			foreach ( $new_settings as $key => $value ) {
				$old_settings[ $key ] = $value;
			}

			if ( defined( 'E20R_LICENSING_DEBUG' ) && true === E20R_LICENSING_DEBUG ) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r
				$this->utils->log( 'Updated settings (after merge...) ' . print_r( $old_settings, true ) );
			}

			return $old_settings;
		}

		/**
		 * Save the license settings
		 *
		 * @param string $product_sku
		 * @param array  $new_settings
		 *
		 * @return bool|array
		 */
		public function update( $product_sku = null, $new_settings ) {

			$license_settings = $this->all_settings();

			/* phpcs:ignore Squiz.PHP.CommentedOutCode.Found
			if ( defined( 'E20R_LICENSING_DEBUG' ) && true === E20R_LICENSING_DEBUG ) {
				$this->utils->log( "Settings before update: " . print_r( $license_settings, true ) );
				$this->utils->log( "NEW settings for {$product_sku}: " . print_r( $new_settings, true ) );
			}
			*/

			// Make sure the new settings make sense
			if ( is_array( $license_settings ) && in_array( 'fieldname', array_keys( $license_settings ), true ) ) {
				if ( defined( 'E20R_LICENSING_DEBUG' ) && true === E20R_LICENSING_DEBUG ) {
					$this->utils->log( "Unexpected settings layout while processing {$product_sku}!" );
				}
				$license_settings                 = self::defaults();
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
				if ( defined( 'E20R_LICENSING_DEBUG' ) && true === E20R_LICENSING_DEBUG ) {
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
				if ( defined( 'E20R_LICENSING_DEBUG' ) && true === E20R_LICENSING_DEBUG ) {
					$this->utils->log( "Removing license settings for {$product_sku}" );
				}
				unset( $license_settings[ $product_sku ] );

			} else {
				if ( defined( 'E20R_LICENSING_DEBUG' ) && true === E20R_LICENSING_DEBUG ) {
					$this->utils->log( 'Requested save of everything' );
				}
			}

			if ( defined( 'E20R_LICENSING_DEBUG' ) && true === E20R_LICENSING_DEBUG ) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r
				$this->utils->log( 'Saving: ' . print_r( $license_settings, true ) );
			}

			update_option( 'e20r_license_settings', $license_settings, 'yes' );

			return $license_settings;
		}

		/**
		 * Add the options section for the Licensing Options page
		 */
		public function add_options_page() {

			// Check whether the Licensing page is already loaded or not
			if ( false === $this->is_page_loaded( 'e20r-licensing', true ) ) {
				$this->load_page();
			}
		}

		/**
		 * Check whether the Licensing page is already loaded or not
		 *
		 * @param string $handle
		 * @param bool   $sub
		 *
		 * @return bool
		 */
		public function is_page_loaded( $handle, $sub = false ) {

			if ( ! is_admin() || ( defined( 'DOING_AJAX' ) && DOING_AJAX ) ) {
				if ( defined( 'E20R_LICENSING_DEBUG' ) && true === E20R_LICENSING_DEBUG ) {
					$this->utils->log( 'AJAX request or not in wp-admin' );
				}

				return false;
			}

			global $menu;
			global $submenu;

			$check_menu = $sub ? $submenu : $menu;

			if ( empty( $check_menu ) ) {
				if ( defined( 'E20R_LICENSING_DEBUG' ) && true === E20R_LICENSING_DEBUG ) {
					$this->utils->log( "No menu object found for {$handle}??" );
				}

				return false;
			}

			$item = isset( $check_menu['options-general.php'] ) ? $check_menu['options-general.php'] : array();

			if ( true === $sub ) {

				foreach ( $item as $subm ) {

					if ( $subm[2] === $handle ) {
						if ( defined( 'E20R_LICENSING_DEBUG' ) && true === E20R_LICENSING_DEBUG ) {
							$this->utils->log( 'Settings submenu already loaded: ' . urldecode( $subm[2] ) );
						}

						return true;
					}
				}
			} else {

				if ( $item[2] === $handle ) {
					if ( defined( 'E20R_LICENSING_DEBUG' ) && true === E20R_LICENSING_DEBUG ) {
						$this->utils->log( 'Menu already loaded: ' . urldecode( $item[2] ) );
					}

					return true;
				}
			}

			if ( defined( 'E20R_LICENSING_DEBUG' ) && true === E20R_LICENSING_DEBUG ) {
				$this->utils->log( 'Loading licensing page...' );
			}

			return false;
		}

		/**
		 * Verifies if the E20R Licenses option page is loaded by someone else
		 */
		public function load_page() {

			if ( defined( 'E20R_LICENSING_DEBUG' ) && true === E20R_LICENSING_DEBUG ) {
				$this->utils->log( 'Attempting to add options page for E20R Licenses' );
			}

			$this->page_handle = add_options_page(
				__( 'E20R Licenses', 'e20r-licensing-utility' ),
				__( 'E20R Licenses', 'e20r-licensing-utility' ),
				'manage_options',
				'e20r-licensing',
				array( LicensePage::get_instance(), 'page' )
			);
		}
	}
}
