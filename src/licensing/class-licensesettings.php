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

use E20R\Utilities\Licensing\Exceptions\InvalidSettingKeyException;
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
		 * @var null|Utilities
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
		 * Should we verify SSL certificate(s)
		 *
		 * @var bool $ssl_verify
		 */
		protected $ssl_verify = false;

		/**
		 * New or old version of licensing system
		 *
		 * @var bool $new_version
		 *
		 */
		protected $new_version = false;

		/**
		 * LicenseSettings constructor.
		 *
		 * @param string|null $product_sku
		 */
		public function __construct( $product_sku = 'e20r_default_license' ) {

			$this->utils       = Utilities::get_instance();
			$this->product_sku = $product_sku;
			$this->excluded    = array( 'excluded', 'utils', 'page_handle', 'settings', 'all_settings', 'instance', 'page' );

			if ( isset( $_SERVER['HTTP_HOST'] ) && 'eighty20results.com' === $_SERVER['HTTP_HOST'] ) {
				$this->utils->log( 'Running on own server. Deactivating SSL Verification' );
				$this->ssl_verify = false;
			}

			// Determine whether we're using the new or old Licensing version
			$this->new_version = (
				defined( 'E20R_LICENSING_VERSION' ) &&
				version_compare( E20R_LICENSING_VERSION, '3.0', 'ge' )
			);

			if ( defined( 'E20R_LICENSING_DEBUG' ) && true === E20R_LICENSING_DEBUG ) {
				$this->utils->log( 'Using new or old version of licensing code..? ' . ( $this->new_version ? 'New' : 'Old' ) );
			}

			if (
				! defined( 'E20R_LICENSE_SERVER_URL' ) ||
				( defined( 'E20R_LICENSE_SERVER_URL' ) && ! E20R_LICENSE_SERVER_URL )
			) {
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
				throw new InvalidSettingKeyException(
					sprintf(
						// translators: %1$s - Key name for the failed setting update
						__( 'Error: The %1$s setting does not exists', 'e20r-utilities-licensing' ),
						$key
					),
					E20R_MISSING_SETTING
				);
			}

			$this->{$key} = $value;
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r
			$this->utils->log( "Set '${key}' to " . print_r( $value, true ) );

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
		 * @throws NoLicenseKeyFoundException
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
				throw new NoLicenseKeyFoundException( $sku );
			}

			return $this->settings[ $sku ];
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

			if ( ! empty( $this->product_sku ) ) {
				$defaults           = $this->defaults( $product_sku );
				$this->all_settings = get_option( 'e20r_license_settings', $defaults );
				$this->settings     = $this->all_settings[ $product_sku ];
			}

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

			if ( $this->new_version ) {
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

						$licensing = new Licensing( $license_settings[ $product ]['product_sku'] );

						if ( ! empty( $license_email ) && ! empty( $license_key ) ) {

							if ( defined( 'E20R_LICENSING_DEBUG' ) && true === E20R_LICENSING_DEBUG ) {
								$this->utils->log( 'Have a license key and email, so activate the new license' );
							}

							$license_settings[ $product ]['email'] = $license_email;
							$license_settings[ $product ]['key']   = $license_key;

							if ( defined( 'E20R_LICENSING_DEBUG' ) && true === E20R_LICENSING_DEBUG ) {
								$this->utils->log( "Attempting remote activation for {$product} " );
							}

							$result = $licensing->activate( $product, $license_settings[ $product ] );

							if ( defined( 'E20R_LICENSING_DEBUG' ) && true === E20R_LICENSING_DEBUG ) {
								// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r
								$this->utils->log( "Status from activation {$result['status']} vs " . Licensing::E20R_LICENSE_DOMAIN_ACTIVE . ' => ' . print_r( $result, true ) );
							}

							if ( Licensing::E20R_LICENSE_DOMAIN_ACTIVE === intval( $result['status'] ) ) {

								if ( defined( 'E20R_LICENSING_DEBUG' ) && true === E20R_LICENSING_DEBUG ) {
									$this->utils->log( 'This license & server combination is already active on the licensing server' );
								}

								if ( true === $licensing->deactivate( $product, $result['settings'] ) ) {

									if ( defined( 'E20R_LICENSING_DEBUG' ) && true === E20R_LICENSING_DEBUG ) {
										$this->utils->log( 'Was able to deactivate this license/host combination' );
									}

									$result = $licensing->activate( $product, $license_settings[ $product ] );

								}
							}

							if ( defined( 'E20R_LICENSING_DEBUG' ) && true === E20R_LICENSING_DEBUG ) {
								$this->utils->log( 'Loading updated settings from server' );
							}

							$server = new LicenseServer( $this->new_version, $this->ssl_verify );

							if ( true === $server->status( $product, $license_settings[ $product ], true ) ) {
								$result['settings'] = $this->merge( $product, $license_settings[ $product ] );
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

								$license_settings = $this->update( $product, $license_settings[ $product ] );

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

					$product   = $input['product'][ $lk ];
					$licensing = new Licensing( $product );
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
				$old_settings = $this->defaults();
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
		 * @param string|null $product_sku
		 * @param array  $new_settings
		 *
		 * @return bool|array
		 */
		public function update( $product_sku, $new_settings ) {

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
	}
}
