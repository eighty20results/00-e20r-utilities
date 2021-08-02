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

use E20R\Licensing\License;
use E20R\Licensing\Settings\LicenseSettings;
use E20R\Utilities\Utilities;

// Deny direct access to the file
if ( ! defined( 'ABSPATH' ) && function_exists( 'wp_die' ) ) {
	wp_die( 'Cannot access file directly' );
}

if ( ! class_exists( '\E20R\Licensing\LicensePage' ) ) {
	class LicensePage {

		/**
		 * Instance of the Utilities class
		 *
		 * @var null|Utilities $this->utils
		 */
		private $utils = null;

		/**
		 * Handle to page (license settings)
		 *
		 * @var string|false Handle for WP admin page
		 */
		private $page_handle = null;

		/**
		 * Whether to add Licensing specific debug logging
		 *
		 * @var bool $log_debug
		 */
		private $log_debug = false;

		/**
		 * LicensePage constructor.
		 */
		public function __construct() {
			$this->utils     = Utilities::get_instance();
			$this->log_debug = defined( 'E20R_LICENSING_DEBUG' ) && E20R_LICENSING_DEBUG;
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
				if ( $this->log_debug ) {
					$this->utils->log( 'AJAX request or not in wp-admin' );
				}

				return false;
			}

			global $menu;
			global $submenu;

			$check_menu = $sub ? $submenu : $menu;

			if ( empty( $check_menu ) ) {
				if ( $this->log_debug ) {
					$this->utils->log( "No menu object found for {$handle}??" );
				}

				return false;
			}

			$item = isset( $check_menu['options-general.php'] ) ? $check_menu['options-general.php'] : array();

			if ( true === $sub ) {

				foreach ( $item as $subm ) {

					if ( $subm[2] === $handle ) {
						if ( $this->log_debug ) {
							$this->utils->log( 'Settings submenu already loaded: ' . urldecode( $subm[2] ) );
						}

						return true;
					}
				}
			} else {

				if ( $item[2] === $handle ) {
					if ( $this->log_debug ) {
						$this->utils->log( 'Menu already loaded: ' . urldecode( $item[2] ) );
					}

					return true;
				}
			}

			if ( $this->log_debug ) {
				$this->utils->log( 'Loading licensing page...' );
			}

			return false;
		}

		/**
		 * Verifies if the E20R Licenses option page is loaded by someone else
		 */
		public function load_page() {

			if ( $this->log_debug ) {
				$this->utils->log( 'Attempting to add options page for E20R Licenses' );
			}

			$this->page_handle = add_options_page(
				__( 'E20R Licenses', '00-e20r-utilities' ),
				__( 'E20R Licenses', '00-e20r-utilities' ),
				'manage_options',
				'e20r-licensing',
				array( $this, 'page' )
			);
		}

		/**
		 * Show the Licensing section on the options page
		 */
		public function show_section() {

			if ( $this->log_debug ) {
				$this->utils->log( 'Loading section HTML for License Settings' );
			}
			// phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores
			$pricing_page = apply_filters( 'e20r-license-pricing-page-url', 'https://eighty20results.com/shop/' );

			// phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores
			$button_text = apply_filters( 'e20r-license-save-btn-text', esc_attr__( 'Activate/Deactivate & Save license(s)', '00-e20r-utilities' ) );
			?>
			<p class="e20r-licensing-section">
			<?php
			echo esc_html__(
				'This add-on is distributed under version 2 of the GNU Public License (GPLv2). One of the things the GPLv2 license grants is the right to use this software on your site, free of charge.',
				'00-e20r-utilities'
			);
			?>
			</p>
			<p class="e20r-licensing-section">
			<?php
			// translators: The button text is defined in the 'e20r-license-save-btn-text' filter
			echo esc_html__(
					// phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralText
				sprintf(
					"To verify and activate the license, add the license key and email address to the appropriate field(s), then click the '%s' button.",
					$button_text
				),
				'00-e20r-utilities'
			);
			?>
			</p>
			<p class="e20r-licensing-section">
			<?php
			// translators: The button text is translated as it's applied to the filter
			echo esc_attr__(
					// phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralText
				sprintf( 'To deactivate the license and clear the license settings from this system, check the check-box in the \"Deactivate\" column and then click the \'%1$s\' button.', $button_text ),
				'00-e20r-utilities'
			);
			?>
			</p>
			<p class="e20r-licensing-section">
				<a href="<?php echo esc_url_raw( $pricing_page ); ?>" target="_blank">
					<?php echo esc_attr__( 'Purchase Licenses/Add-ons &raquo;', '00-e20r-utilities' ); ?>
				</a>
			</p>
			<div class="form-table">
				<div class="e20r-license-settings-row">
					<div class="e20r-license-settings-column e20r-license-settings-header e20r-license-name-column">
						<?php echo esc_attr__( 'Name', '00-e20r-utilities' ); ?>
					</div>
					<div class="e20r-license-settings-column e20r-license-settings-header e20r-license-key-column">
						<?php echo esc_attr__( 'Key', '00-e20r-utilities' ); ?>
					</div>
					<div class="e20r-license-settings-column e20r-license-settings-header e20r-license-email-column">
						<?php echo esc_attr__( 'Email', '00-e20r-utilities' ); ?>
					</div>
					<div
						class="e20r-license-settings-column e20r-license-settings-header e20r-license-deactivate-column">
						<?php echo esc_attr__( 'Deactivate', '00-e20r-utilities' ); ?>
					</div>
				</div>
			</div>
			<?php
		}

		/**
		 * Show input row for License page
		 *
		 * @param array $args
		 *
		 * @since 1.6 - BUG FIX: Used incorrect product label for new licenses
		 */
		public function show_input( $args ) {

			global $current_user;

			if ( $this->log_debug ) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r
				$this->utils->log( 'Loading input HTML for: ' . print_r( $args, true ) );
			}

			$is_active       = isset( $args['is_active'] ) && 1 === (int) $args['is_active'];
			$product_sku     = isset( $args['product_sku'] ) ? $args['product_sku'] : '';
			$status_color    = $is_active ? 'e20r-license-active' : 'e20r-license-inactive';
			$product         = esc_attr__( 'Unknown', '00-e20r-utilities' );
			$var_name        = "{$args['option_name']}[product][0]";
			$is_subscription = ( isset( $args['has_subscription'] ) && ! empty( $args['has_subscription'] ) && 1 === $args['has_subscription'] );

			if ( isset( $args['product'] ) ) {

				$product  = $args['product'];
				$var_name = "{$args['option_name']}[product][{$args['index']}]";

			} elseif ( isset( $args['new_product'] ) ) {

				$product             = $args['new_product'];
				$var_name            = "{$args['option_name']}[new_product][{$args['index']}]";
				$args['email_value'] = $current_user->user_email;
			}
			?>
			<div class="e20r-license-data-row">
				<div class="e20r-license-settings-column e20r-license-key-column">
				<?php
					// translators: Generates dynamic HTML for the Licensing page. Nothing to see here
					echo esc_html__(
							// phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralText
						sprintf(
							'<input type="hidden" name="%1$s" value="%2$s" />',
							"{$args['option_name']}[fieldname][{$args['index']}]",
							$args['value']
						)
					);
					// translators: Generates dynamic HTML for the Licensing page. Nothing to see here
					echo esc_html__(
						// phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralText
						sprintf(
							'<input type="hidden" name="%1$s" value="%2$s" />',
							"{$args['option_name']}[fulltext_name][{$args['index']}]",
							$args['fulltext_name']
						)
					);
					// translators: Generates dynamic HTML for the Licensing page. Nothing to see here
					echo esc_html__(
						// phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralText
						sprintf(
							'<input type="hidden" name="%1$s" value="%2$s" />',
							$var_name,
							$product
						)
					);
					// translators: Generates dynamic HTML for the Licensing page. Nothing to see here
					echo esc_html__(
						// phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralText
						sprintf(
							'<input type="hidden" name="%1$s" value="%2$s" />',
							"{$args['option_name']}[product_sku][{$args['index']}]",
							$product_sku
						)
					);
					// translators: Generates dynamic HTML for the Licensing page. Nothing to see here
					echo esc_html__(
						// phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralText
						sprintf(
							'<input name="%1$s[%2$s][%3$d]" type="%4$s" id="%5$s" value="%6$s" placeholder="%7$s" class="regular_text %8$s" />',
							$args['option_name'],
							$args['name'],
							$args['index'],
							$args['input_type'],
							$args['label_for'],
							$args['value'],
							$args['placeholder'],
							$status_color
						)
					);
				?>
				</div>
				<div class="e20r-license-settings-column e20r-license-email-column">
					<?php
					// translators: Dynamic creation of HTML to be escaped & placed on the page
					echo esc_html__(
							// phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralText
						sprintf(
							'<input name="%1$s[%2$s][%3$d]" type="email" id=%4$s_email value="%5$s" placeholder="%6$s" class="email_address %7$s" />',
							$args['option_name'],
							$args['email_field'],
							$args['index'],
							$args['label_for'],
							$args['email_value'],
							__( 'Email used to buy license', '20r-Licensing-utility' ),
							$status_color
						)
					);
					?>
				</div>
				<div class="e20r-license-settings-column e20r-license-deactivate-column">
					<?php if ( 'new_key' !== $args['name'] ) { ?>
						<?php
						// translators: Dynamic creation of HTML to be escaped & placed on the page
						echo esc_html__(
						// phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralText
							sprintf(
								'<input type="checkbox" name="%1$s[delete][%2$d]" class="clear_license" value="%3$s" />',
								$args['option_name'],
								$args['index'],
								$args['value']
							)
						);
					}
					?>
				</div>
				<div class="e20r-license-settings-column e20r-license-check-license-column">
					<?php if ( 'new_key' !== $args['name'] ) { ?>
						<?php
						// translators: Dynamic creation of HTML to be escaped & placed on the page
						echo esc_html__(
						// phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralText
							sprintf(
								'<input type="button" name="%1$s[check_license][%2$d]" class="e20r-check-license button button-secondary" value="%3$s" />',
								$args['option_name'],
								$args['index'],
								__( 'Verify license', '00-e20r-utilities' )
							)
						);
					}
					?>
				</div>
				<div class="e20r-license-settings-row">
					<p class="e20r-license-settings-status">
						<?php

						$has_expiration  = ( ! $is_subscription && isset( $args['expiration_ts'] ) && ! empty( $args['expiration_ts'] ) );
						$expiration_date = '';

						if ( $is_active ) {
							$expiration_message = esc_attr__( 'This license does not expire', '00-e20r-utilities' );
						} else {
							$expiration_message = esc_attr__( 'This license is not activated', '00-e20r-utilities' );
						}

						if ( $has_expiration || $is_subscription ) {
							// translators: Creates the appropriately formatted date information for the license expiration
							$expiration_date = esc_attr__(
								// phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralText
								sprintf(
									'on or before: %1$s',
									date_i18n(
										get_option( 'date_format' ),
										$args['expiration_ts']
									)
								),
								'00-e20r-utilities'
							);
						}

						$body_msg = $is_subscription && ! $has_expiration ?
							__( 'will renew automatically (unless cancelled)', '00-e20r-utilities' ) :
							__( 'needs to be renewed manually', '00-e20r-utilities' );

						if ( ! $is_subscription && ! $has_expiration ) {
							$body_msg = esc_attr__( 'does not need to be renewed', '00-e20r-utilities' );
						}

						if ( $is_subscription || $has_expiration ) {
							$expiration_message = sprintf(
									// translators: Message to show when the license is expiring
								__( 'This license %1$s %2$s', '00-e20r-utilities' ),
								$body_msg,
								$expiration_date
							);
						}

						$this->utils->log( "License expiration info: {$expiration_message}" );
						// phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralText
						echo esc_html__( $expiration_message, '00-e20r-utilities' );
						?>
					</p>
				</div>
			</div>
			<?php
		}

		/**
		 * The page content for the E20R Licensing section
		 *
		 * @since 1.6.1 - BUG FIX: Would sometimes show the wrong license status on the Licensing page
		 */
		public function page() {

			if ( $this->log_debug ) {
				$this->utils->log( 'Testing access for Licensing page' );
			}

			if ( ! function_exists( 'current_user_can' ) ||
				(
						! current_user_can( 'manage_options' ) &&
						! current_user_can( 'e20r_license_admin' )
				)
			) {
				wp_die(
					esc_html__(
						'You are not permitted to perform this action.',
						'00-e20r-utilities'
					)
				);
			}

			// phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores
			$button_text = apply_filters(
				'e20r_license_save_btn_text',
				esc_attr__(
					'Activate/Deactivate & Save license(s)',
					'00-e20r-utilities'
				)
			);
			?>
			<?php $this->utils->display_messages(); ?>
			<br/>
			<h2>
			<?php
				// phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralText
				echo esc_html__( $GLOBALS['title'] );
			?>
			</h2>
			<form action="options.php" method="POST">
				<?php
				settings_fields( 'e20r_license_settings' );
				do_settings_sections( 'e20r-licensing-settings' );
				?>
			</>
			<?php
			submit_button( $button_text );
			?>
			</form>
			<?php
			$l_settings = new LicenseSettings();
			$settings   = array();

			try {
				$settings = $l_settings->get_settings();
			} catch ( \Exception $e ) {
				$this->utils->add_message( $e->getMessage(), 'error', 'backend' );
			}

			// phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores
			$settings            = apply_filters( 'e20r-license-add-new-licenses', $settings, array() );
			$support_account_url = apply_filters(
				// phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores
				'e20r-license-support-account-url',
				sprintf(
					'%1$s?redirect_to=%2$s',
					E20R_LICENSE_SERVER_URL . '/wp-login.php',
					E20R_LICENSE_SERVER_URL . '/account/'
				)
			);

			if ( $this->log_debug ) {
				$this->utils->log( 'Have ' . count( $settings ) . ' new license(s) to add info for' );
			}

			foreach ( $settings as $product_sku => $license ) {
				try {
					$licensing = new License( $product_sku );
				} catch ( Exceptions\InvalidSettingKeyException | Exceptions\MissingServerURL $e ) {
					$this->utils->add_message( 'Error: ' . $e->getMessage(), 'error', 'backend' );
				}

				if ( count( $settings ) > 1 && in_array(
					$product_sku,
					array(
						'e20r_default_license',
						'new_licenses',
						'example_gateway_addon',
					),
					true
				) ) {
					// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r
					$this->utils->log( "Skipping settings for ${product_sku}: " . print_r( $license, true ) );
					continue;
				}
				$this->utils->log( "Checking license status for {$product_sku}" );

				$license_valid =
						$licensing->is_licensed( $product_sku, false ) &&
						( isset( $license['status'] ) && 'active' === $license['status'] );
				?>

				<div class="wrap">
				<?php
					$license_expired = false;
				if ( isset( $license['expires'] ) ) {
					$this->utils->log( 'Have old Licensing config, so...' );
					$license_expired =
						! empty( $license['expires'] ) && $license['expires'] <= time();
				}

				if ( isset( $license['expire'] ) ) {
					$this->utils->log( 'Have new Licensing config, so...' );
					$license_expired =
						! empty( $license['expire'] ) && $license['expire'] <= time();
				}

				if ( false === $license_valid && true === $license_expired ) {
					?>
						<div class="notice notice-error inline">
						<p>
							<strong>
							<?php
							// translators: The plugin name (license name) is supplied by the plugin itself, not this framework
							echo esc_html__(
									// phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralText
								sprintf(
									'Your <em>%s</em> license is either not configured, invalid or has expired.',
									$license['fulltext_name']
								),
								'00-e20r-utilities'
							);
							?>
									</strong>
							<?php
							// translators: The substituted text is an URL to the license server account page for that user
							echo esc_html__(
								// phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralText
								sprintf(
									'Visit your Eighty / 20 Results <a href="%1$s" target="_blank">Support Account</a> page to confirm that your account is active and to locate your license key.',
									$support_account_url
								),
								'00-e20r-utilities'
							);
							?>
						</p>
						</div>
						<?php
				}

				if ( $license_valid ) {
					?>
						<div class="notice notice-info inline">
						<p>
							<strong><?php esc_attr_e( 'Thank you!', '00-e20r-utilities' ); ?></strong>
						<?php
						// translators: The name of the licensed product is supplied from the product
						echo esc_attr__(
							// phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralText
							sprintf(
								'A valid %1$s license key is being used on this site.',
								$license['fulltext_name']
							),
							'00-e20r-utilities'
						);
						?>
						</p>
						</div>
						<?php

				}
				?>
				</div> <!-- end wrap -->
				<?php
			}

		}
	}
}
