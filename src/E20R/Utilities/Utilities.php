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

namespace E20R\Utilities;

// Disallow direct access to the class definition

use E20R\Licensing\Settings\Defaults;
use Exception;
use Puc_v4_Factory;
use function apply_filters;
use function plugin_dir_path;
use function plugins_url;

if ( ! defined( 'ABSPATH' ) && function_exists( 'wp_die' ) ) {
	wp_die( 'Cannot access file directly' );
}

if ( ! class_exists( '\E20R\Utilities\Utilities' ) ) {

	/**
	 * Class Utilities
	 * @package E20R\Utilities
	 *
	 * @version 3.0 - GDPR opt-in, erasure and data access framework
	 */
	class Utilities {

		/**
		 * Version number for the Utilities class
		 */
		const VERSION = '3.0';

		/**
		 * URI to the library path (Utilities)
		 *
		 * @var string
		 */
		public static $library_url = '';

		/**
		 * Path to the Utilities library
		 *
		 * @var string
		 */
		public static $library_path = '';

		/**
		 * @var string Cache key
		 */
		private static $cache_key;

		/**
		 * @var null|string
		 */
		public $plugin_slug = '00-e20r-utilities';

		/**
		 * @var null|Utilities
		 */
		private static $instance = null;

		/**
		 * @var int $blog_id
		 */
		private $blog_id = null;

		/**
		 * @var null $msg
		 */
		private $msg = null;

		/**
		 * Utilities constructor.
		 */
		public function __construct( $messages = null ) {

			self::$library_url  = function_exists( 'plugins_url' ) ? plugins_url( '', __FILE__ ) : '';
			self::$library_path = function_exists( 'plugin_dir_path' ) ? plugin_dir_path( __FILE__ ) : __DIR__;
			$this->plugin_slug  = function_exists( 'apply_filters' ) ? apply_filters( 'e20r_licensing_text_domain', '00-e20r-utilities' ) : '00-e20r-utilities';

			$this->log( 'Plugin Slug: ' . $this->plugin_slug );

			$this->blog_id = function_exists( 'get_current_blog_id' ) ? get_current_blog_id() : 1;

			self::$cache_key = "e20r_pw_utils_{$this->blog_id}";

			if ( empty( $messages ) ) {
				$messages = new Message();
			}

			$this->log( 'Front or backend???' );

			if ( ! function_exists( 'add_action' ) ) {
				$this->log( 'Error: add_action() is undefined!' );
				return;
			}

			if ( ! function_exists( 'add_filter' ) ) {
				$this->log( 'Error: add_filter() is undefined!' );
				return;
			}

			if ( ! function_exists( 'has_action' ) ) {
				$this->log( 'Error: has_action() is undefined!' );
				return;
			}

			if ( self::is_admin() ) {

				// Clear cache when updating discount codes or membership level definitions
				add_action( 'pmpro_save_discount_code', array( $this, 'clear_delay_cache' ), 9999, 1 );
				add_action( 'pmpro_save_membership_level', array( $this, 'clear_delay_cache' ), 9999, 1 );

				/** Disable SSL validation for localhost request(s) */
				add_filter( 'http_request_args', array( $this, 'set_ssl_validation_for_updates' ), 9999, 2 );

				if ( ! has_action( 'admin_notices', array( $messages, 'display' ) ) ) {
					$this->log( 'Loading message(s) for backend' );
					add_action( 'admin_notices', array( $messages, 'display' ), 10 );
				}
			} else {

				$this->log( 'Loading message(s) for frontend' );
				add_filter( 'woocommerce_update_cart_action_cart_updated', array( $messages, 'clear_notices' ), 10, 1 );
				add_action( 'woocommerce_init', array( $messages, 'display' ), 1 );

				add_filter( 'pmpro_email_field_type', array( $messages, 'filter_passthrough' ), 1, 1 );
				add_filter( 'pmpro_get_membership_levels_for_user', array( $messages, 'filter_passthrough' ), 10, 2 );
			}
		}

		/**
		 * Mask the text if it's a valid email address
		 *
		 * @param string $email
		 * @param int    $min_length
		 * @param int    $max_length
		 * @param string $mask
		 *
		 * @return string
		 */
		public function maybe_mask_email( $email, $min_length = 3, $max_length = 10, $mask = '***' ) {

			if ( ! is_email( $email ) ) {
				return $email;
			}

			$at_pos   = strpos( $email, '@' );
			$username = substr( $email, 0, $at_pos );
			$length   = strlen( $email );
			$domain   = substr( $email, $at_pos );

			if ( ( $length / 2 ) < $max_length ) {
				$max_length = ( $length / 2 );
			}

			$shortened_email = ( ( $length > $min_length ) ? substr( $username, 0, $max_length ) : '' );

			return "{$shortened_email}{$mask}{$domain}";
		}

		/**
		 * Pattern recognize whether the data is a valid date format for strtotime() to process
		 * Expected format: YYYY-MM-DD
		 * Note: Does not check if the date makes sense! I.e. will return true for Feb 31st, 2020
		 *
		 * @param string $data -- Data to test
		 *
		 * @return bool -- true | false
		 *
		 * @access public
		 */
		public function is_valid_date( $data ) {
			// Returns true when strtotime($data) is for a valid date and false when it's not
			if ( false === strtotime( $data, time() ) ) {
				return false;
			}

			return true;
		}

		/**
		 * (Attempt to) Fetch and sanitize the IP address of the connecting client
		 *
		 * @return string|null
		 */
		public function get_client_ip() {

			$ip = null;

			if ( isset( $_SERVER['REMOTE_ADDR'] ) ) {
				return $_SERVER['REMOTE_ADDR'];
			}

			$ip_keys = array(
				'HTTP_CLIENT_IP',
				'HTTP_X_FORWARDED_FOR',
				'HTTP_X_FORWARDED',
				'HTTP_X_CLUSTER_CLIENT_IP',
				'HTTP_FORWARDED_FOR',
				'HTTP_FORWARDED',
				'REMOTE_ADDR',
			);

			foreach ( $ip_keys as $key ) {

				if ( array_key_exists( $key, $_SERVER ) === true ) {

					foreach ( explode( ',', $_SERVER[ $key ] ) as $ip ) {

						// trim for safety measures
						$ip = trim( $ip );

						// attempt to validate IP
						if ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 | FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) !== false ) {
							return $ip;
						}
					}
				}
			}

			return $ip;
		}

		/**
		 * Are we viewing the admin screen (WP Backend)
		 *
		 * @return bool
		 */
		public static function is_admin() {

			$is_admin = false;

			if ( isset( $GLOBALS['current_screen'] ) ) {
				$is_admin = $GLOBALS['current_screen']->in_admin();
			} elseif ( defined( 'WP_ADMIN' ) ) {
				$is_admin = WP_ADMIN;
			}

			return $is_admin;
		}

		/**
		 * Load and use L10N based text (if available)
		 */
		public function load_textdomain() {

			$this->log( 'Processing load_textdomain' );

			if ( empty( $this->plugin_slug ) ) {
				$this->log( 'Error attempting to load translation files!' );

				return;
			}

			$locale  = apply_filters( 'plugin_locale', get_locale(), '00-e20r-utilities' );
			$fs_base = new \WP_Filesystem_Base();

			$mofile        = "00-e20r-utilities-{$locale}.mo";
			$mofile_local  = plugin_dir_path( __FILE__ ) . 'languages/' . $mofile;
			$mofile_global = $fs_base->wp_lang_dir() . '/00-e20r-utilities/' . $mofile;

			load_textdomain( '00-e20r-utilities', $mofile_local );

			//Attempt to load the global translation first (if it exists)
			if ( file_exists( $mofile_global ) ) {
				load_textdomain( '00-e20r-utilities', $mofile_global );
			}

			//load local second
			load_textdomain( '00-e20r-utilities', $mofile_local );

			//load via plugin_textdomain/glotpress
			load_plugin_textdomain( '00-e20r-utilities', false, dirname( __FILE__ ) . '/../../languages/' );
		}

		/**
		 * Test whether the plugin is active on the system or in the network
		 *
		 * @param null|string $plugin_file
		 * @param null|string $function_name
		 *
		 * @return bool
		 */
		public function plugin_is_active( $plugin_file = null, $function_name = null ) {

			if ( ! is_admin() ) {

				if ( ! empty( $function_name ) ) {
					return function_exists( $function_name );
				}
			} else {

				if ( ! empty( $plugin_file ) ) {
					return ( is_plugin_active( $plugin_file ) || is_plugin_active_for_network( $plugin_file ) );
				}

				if ( ! empty( $function_name ) ) {
					return function_exists( $function_name );
				}
			}

			return false;
		}

		/**
		 * Return last message of a specific type
		 *
		 * @param string $type
		 *
		 * @return string[]
		 */
		public function get_message( $type = 'notice' ) {

			$messages = new Message();

			return $messages->get( $type );
		}

		/**
		 * Add error message to the list of messages to display on the back-end
		 *
		 * @param string $message    The message to save/add
		 * @param string $type       The type of message (notice, warning, error)
		 * @param string $msg_source The source of the error message
		 *
		 * @return bool
		 */
		public function add_message( $message, $type = 'notice', $msg_source = 'default' ) {

			$this->msg[] = new Message( $message, $type, $msg_source );

			return true;
		}

		/**
		 * Display the error message as HTML when called
		 *
		 * @param string $source - The error source to show.
		 */
		public function display_messages( $source = 'default' ) {

			$message = new Message();
			$message->display( $source );
		}

		/**
		 * Identify the calling function (used in debug logger
		 *
		 * @return array|string
		 *
		 * @access public
		 */
		public function who_called_me() {

			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_debug_backtrace
			$trace  = debug_backtrace();
			$caller = $trace[2];

			if ( isset( $caller['class'] ) ) {
				$trace = "{$caller['class']}::{$caller['function']}()";
			} else {
				$trace = "Called by {$caller['function']}()";
			}

			return $trace;
		}

		/**
		 * Return the cache key for the Utilities class
		 * @return string
		 */
		public function get_util_cache_key() {
			return self::$cache_key;
		}

		/**
		 * Return all delay values for a membership payment start
		 *
		 * @param \stdClass|array $level
		 *
		 * @return array|bool|mixed|null
		 */
		public static function get_membership_start_delays( $level ) {

			$delays = array();
			self::$instance->log( "Processing start delays for {$level->id}" );

			if ( ! function_exists( 'pmpro_isLevelRecurring' ) ) {
				self::$instance->log( 'PMPro is not an active plugin!' );
				return $delays;
			}

			if ( ! function_exists( 'pmprosd_daysUntilDate' ) ) {
				self::$instance->log( 'The PMPro Set Expiration Date add-on is not an active plugin!' );
				return $delays;
			}

			if ( true === pmpro_isLevelRecurring( $level ) ) {

				self::$instance->log( "Level {$level->id} is a recurring payments level" );
				$delays = Cache::get( "start_delay_{$level->id}", self::$cache_key );

				if ( null === $delays ) {

					self::$instance->log( 'Invalid cache... Loading from scratch' );

					// Calculate the trial period (may be smaller than a normal billing period
					if ( $level->cycle_number > 0 ) {

						self::$instance->log( 'Is a recurring billing level' );

						$trial_cycles       = $level->trial_limit;
						$period_days        = self::convert_period( $level->cycle_period );
						$billing_cycle_days = 0;

						if ( null !== $period_days ) {
							$billing_cycle_days = $level->cycle_number * $period_days;
						}

						if ( ! empty( $trial_cycles ) ) {
							$delays['trial'] = $trial_cycles * $billing_cycle_days;
						}

						$delays[ $level->id ] = ( $billing_cycle_days * $level->cycle_number );
						self::$instance->log( "Days used for delay value: {$delays[$level->id]} " );
					}

					// We have Subscription Delays add-on for PMPro installed and active
					if ( function_exists( 'pmprosd_getDelay' ) ) {

						self::$instance->log( "Processing Subscription Delay values for {$level->id}" );

						//Get the default delay value (days)
						$date_or_num = pmprosd_getDelay( $level->id, null ); // @phpstan-ignore-line
						self::$instance->log( "Received default delay value: {$date_or_num}" );

						if ( ! empty( $date_or_num ) ) {
							// @phpstan-ignore-next-line
							$val = ( is_numeric( $date_or_num ) ? $date_or_num : pmprosd_daysUntilDate( $date_or_num ) );

							if ( ! empty( $val ) ) {
								$delays['default'] = $val;
								self::$instance->log( "Configured default value {$delays[ 'default' ]}" );
							}
						} else {
							self::$instance->log( "No default value for level {$level->id} specified" );
						}

						// Fetch discount codes to locate delays for
						$active_codes = self::get_all_discount_codes();

						// Process active discount code delays
						if ( ! empty( $active_codes ) ) {
							foreach ( $active_codes as $code ) {

								// Get the delay value from the Subscription Delays plugin
								$d = pmprosd_getDelay( $level->id, $code->id ); // @phpstan-ignore-line

								if ( ! empty( $d ) ) {

									self::$instance->log( "Processing {$d}" );
									// @phpstan-ignore-next-line
									$val = ( is_numeric( $d ) ? $d : pmprosd_daysUntilDate( $d ) );

									if ( ! empty( $val ) ) {
										$delays[ $code->code ] = $val;
										self::$instance->log( "Configured {$code->code} value {$delays[ $code->code ]}" );
									}
								}
							}
						}
					}

					// Update the cache.
					if ( ! empty( $delays ) ) {

						// Save to cache and have cached for up to 7 days
						Cache::set( "start_delay_{$level->id}", $delays, WEEK_IN_SECONDS, self::$cache_key );
					}
				}
			}

			return $delays;
		}

		/**
		 * Convert a Cycle Period (from PMPro) string to an approximate day count
		 *
		 * @param string $period
		 *
		 * @return int|null
		 */
		public static function convert_period( $period ) {

			$days = null;

			switch ( strtolower( $period ) ) {

				case 'day':
					$days = 1;
					break;

				case 'week':
					$days = 7;
					break;

				case 'month':
					$days = 30;
					break;

				case 'year':
					$days = 365;
					break;
			}

			return $days;
		}

		/**
		 * Return all PMPro discount codes from the system
		 *
		 * @return array|null|object
		 */
		public static function get_all_discount_codes() {

			global $wpdb;

			return $wpdb->get_results( "SELECT id, code FROM {$wpdb->pmpro_discount_codes}" );
		}

		/**
		 * Remove the start delay cache for the level
		 *
		 * @param int $level_id
		 */
		public function clear_delay_cache( $level_id ) {

			$this->log( "Clearing delay cache for {$level_id}" );
			Cache::delete( "start_delay_{$level_id}", self::$cache_key );
		}

		/**
		 * Test whether the user is in Trial mode (i.e. the user's startdate is configured as 'after' the current date/time
		 *
		 * @param int $user_id
		 * @param int $level_id
		 *
		 * @return int|bool - Returns the Timestamp (seconds) of when the trial ends, or false if no trial was found
		 */
		public function is_in_trial( $user_id, $level_id ) {

			global $wpdb;
			$this->log( "Processing trial test for {$user_id} and {$level_id}" );

			// Get the most recent (active) membership level record for the specified user/membership level ID
			$start_ts = intval(
				$wpdb->get_var(
					$wpdb->prepare(
						"SELECT UNIX_TIMESTAMP( mu.startdate ) AS start_date
								FROM {$wpdb->pmpro_memberships_users} AS mu
								WHERE mu.user_id = %d AND mu.membership_id = %d
								ORDER BY mu.id DESC
								LIMIT 1",
						$user_id,
						$level_id
					)
				)
			);

			$this->log( "Found start Timestamp: {$start_ts}" );

			// No record found for specified user, so can't be in a trial...
			if ( empty( $start_ts ) ) {
				$this->log( "No start time found for {$user_id}, {$level_id}: {$wpdb->last_error}" );

				return false;
			}

			$now = time();

			if ( true === $this->plugin_is_active( null, 'pmprosd_daysUntilDate' ) ) {

				$this->log( 'The PMPro Subscription Delays add-on is active on this system' );

				// Is the user record in 'pre-start' mode (i.e. using Subscription Delay add-on)
				if ( $start_ts <= $now ) {

					$this->log( "User ({$user_id}) at membership level ({$level_id}) is currently in 'trial' mode: {$start_ts} <= {$now}" );

					return $start_ts;
				}
			} elseif ( true === $this->plugin_is_active( 'paid-memberships-pro/paid-memberships-pro.php', 'pmpro_getMembershipLevelForUser' ) ) {

				$this->log( "No trace of the 'Subscription Delays' add-on..." );

				$user_level = pmpro_getMembershipLevelForUser( $user_id );

				// Is there a trial period defined for this user?
				if ( ! empty( $user_level->cycle_number ) && ! empty( $user_level->trial_limit ) ) {

					$trial_duration = $user_level->cycle_number * $user_level->trial_limit;
					$start_date     = date_i18n( 'Y-m-d H:i:s', $start_ts );
					$trial_ends_ts  = strtotime(
						sprintf( '%1$s +%2$s %3$s', $start_date, $trial_duration, $user_level->cycle_period ) // @phpstan-ignore-line
					);

					if ( false !== $trial_ends_ts && $trial_ends_ts >= $now ) {
						$this->log( "User {$user_id} is in their current trial period for level {$level_id}: It ends at {$trial_ends_ts} which is >= {$now} " );

						return $trial_ends_ts;
					} else {
						$this->log( 'There was a problem converting the trial period info into a timestamp!' );
					}
				} else {
					$this->log( 'No Trial period defined for user...' );
				}
			} else {
				$this->log( 'Neither PMPro nor Subscription Delays add-on is installed and active!!' );
			}

			return false;
		}

		/**
		 * Return the correct Stripe amount formatting (based on currency setting)
		 *
		 * @param float|int $amount
		 * @param string    $currency
		 *
		 * @return float|string
		 */
		public function amount_by_currency( $amount, $currency ) {

			$def_currency = apply_filters( 'e20r_utilities_default_currency', 'USD' );

			if ( $def_currency !== $currency ) {
				$def_currency = strtoupper( $currency );
			}

			$decimals = 2;
			global $pmpro_currencies;

			if ( isset( $pmpro_currencies[ $def_currency ]['decimals'] ) ) {
				$decimals = intval( $pmpro_currencies[ $def_currency ]['decimals'] );
			}

			$divisor = intval( str_pad( '1', ( 1 + $decimals ), '0', STR_PAD_RIGHT ) );
			$this->log( "Divisor for calculation: {$divisor}" );

			$amount = number_format_i18n( ( $amount / $divisor ), $decimals );
			$this->log( "Using amount: {$amount} for {$currency} vs {$amount}" );

			return $amount;
		}

		/**
		 * Define and return the path name for debug logging file
		 *
		 * @return false|string
		 */
		public function get_debug_name() {

			if ( ! function_exists( 'wp_upload_dir' ) ) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				error_log( 'Error: Cannot find WP upload information (yet?)' );
				return false;
			}

			$log_file = sprintf(
				'debug_%1$s.log',
				date_i18n( 'Y_M_D', time() )
			);

			$upload_dir_info = wp_upload_dir();

			if ( empty( $upload_dir_info ) ) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				error_log( 'Error: Unable to define the upload directory information for WordPress!' );
				return false;
			}

			$log_directory = sprintf( '%1$s/e20r_debug', $upload_dir_info['basedir'] );
			$log_name      = sprintf( '%1$s/%2$s', $log_directory, $log_file );

			if ( ! file_exists( $log_directory ) ) {
				if ( ! mkdir( $log_directory, 0755, true ) ) {
					// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
					error_log( "Unable to create the E20R Debug logging location: {$log_directory}" );
					return false;
				}
			}
			return $log_name;
		}

		/**
		 * Print a message to the daily E20R Log file if WP_DEBUG is configured (Does not try to mask email addresses)
		 *
		 * @param string $message
		 * @return bool|null
		 */
		public function log( $message ) {

			if ( ! defined( 'WP_DEBUG' ) || defined( 'WP_DEBUG' ) && false === WP_DEBUG ) {
				return false;
			}

			/**
			 * Mask email addresses if applicable
			 */
			// phpcs:ignore Squiz.PHP.CommentedOutCode.Found
			/*
			if ( 1 === preg_match( '/\b[^\s]+@[^\s]+/i', $msg, $match ) ) {

				$masked_email = $this->maybe_mask_email( $match[0] );
				$msg          = preg_replace( '/\b[^\s]+@[^\s]+/i', $masked_email, $msg );
			}
			*/
			// Get timestamp, thread ID and function calling us
			$remote_addr      = isset( $_SERVER['REMOTE_ADDR'] ) ? $_SERVER['REMOTE_ADDR'] : '127.0.0.1';
			$req_time         = isset( $_SERVER['REQUEST_TIME'] ) ? $_SERVER['REQUEST_TIME'] : time();
			$thread_id        = sprintf( '%08x', abs( crc32( $remote_addr . $req_time ) ) );
			$tz_string        = get_option( 'timezone_string' );
			$timestamp        = gmdate( 'H:m:s', strtotime( $tz_string ) );
			$calling_function = $this->who_called_me();

			// Log the message to the custom E20R debug log
			$log_name = $this->get_debug_name();

			if ( ! empty( $log_name ) ) {
				// phpcs:ignore
				// $log_fh = fopen( $log_name, 'a+' );
				// phpcs:ignore
				// $log_fh,
				error_log( // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
					sprintf(
						'[%s](%s) %s - %s',
						$thread_id,
						$timestamp,
						$calling_function,
						$message
					)
				);
				// phpcs:ignore
				// fclose( $log_fh );
			}

			return true;
		}

		/**
		 * Case insensitive search/replace function (recursive)
		 *
		 * @param string $search
		 * @param string $replacer
		 * @param string $input
		 *
		 * @return mixed
		 */
		public function nc_replace( $search, $replacer, $input ) {

			return preg_replace_callback(
				"/\b{$search}\b/i",
				function ( $matches ) use ( $replacer ) {
					return ctype_lower( $matches[0][0] ) ? strtolower( $replacer ) : $replacer;
				},
				$input
			);
		}

		/**
		 * Process REQUEST variable: Check for presence and sanitize it before returning value or default
		 *
		 * @param string     $name    Name of the variable to return
		 * @param null|mixed $default The default value to return if the REQUEST variable doesn't exist or is empty.
		 *
		 * @return bool|float|int|null|string  Sanitized value from the front-end.
		 */
		public function get_variable( $name, $default = null ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return isset( $_REQUEST[ $name ] ) && ! empty( $_REQUEST[ $name ] ) ? $this->sanitize( $_REQUEST[ $name ] ) : $default;
		}

		/**
		 * Sanitizes the passed field/value.
		 *
		 * @param array|int|null|string|\stdClass $field The value to sanitize
		 *
		 * @return mixed     Sanitized value
		 */
		public function sanitize( $field ) {

			if ( ! is_numeric( $field ) ) {

				if ( is_array( $field ) ) {
					foreach ( $field as $key => $val ) {
						$field[ $key ] = $this->sanitize( $val );
					}
				}

				if ( is_object( $field ) ) {
					foreach ( (array) $field as $key => $val ) {
						$field->{$key} = $this->sanitize( $val );
					}
				}

				if ( ! is_email( $field ) && (
					( ! is_array( $field ) ) && ctype_alpha( $field ) ||
					( ( ! is_array( $field ) ) && strtotime( $field ) ) ||
					( ( ! is_array( $field ) ) && is_string( $field ) )
					)
				) {

					if ( strtolower( $field ) === 'yes' ) {
						$field = true;
					} elseif ( strtolower( $field ) === 'no' ) {
						$field = false;
					} elseif ( ! self::is_html( $field ) ) {
						$field = sanitize_text_field( $field );
					} else {
						$field = wp_kses_post( $field );
					}
				}

				if ( function_exists( 'is_email' ) && is_email( $field ) ) {
					$field = sanitize_email( $field );
				}
			} else {

				if ( is_float( $field + 1 ) ) {
					$field = sanitize_text_field( $field );
				}

				if ( is_int( $field + 1 ) ) {
					$field = intval( $field );
				}

				if ( self::is_bool( $field ) ) {
					$field = (bool) $field;
				}
			}

			return $field;
		}

		/**
		 * More consistent boolean tester
		 *
		 * @param mixed $variable
		 *
		 * @return false|mixed
		 */
		private static function is_bool( $variable ) {
			if ( ! isset( $variable ) ) {
				return false;
			}
			return filter_var( $variable, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE );
		}

		/**
		 * Test whether string contains HTML
		 *
		 * @param string $string
		 *
		 * @return bool
		 */
		final public static function is_html( $string ) {
			return preg_match( '/<[^<]+>/', $string, $m ) !== 0;
		}

		/**
		 * Test whether the value is an integer
		 *
		 * @param mixed $val
		 *
		 * @return bool|int
		 */
		final public static function is_integer( $val ) {
			if ( ! is_scalar( $val ) || self::is_bool( $val ) ) {
				return false;
			}

			if ( is_float( (float) $val + 0 ) && ( (int) $val + 0 ) > PHP_INT_MAX ) {
				return false;
			}

			return is_float( $val ) ? false : preg_match( '~^((?:\+|-)?[0-9]+)$~', $val );
		}

		/**
		 * Test if the value is a floating point number
		 *
		 * @param string|mixed $val
		 *
		 * @return bool
		 */
		final public static function is_float( $val ) {
			if ( ! is_scalar( $val ) ) {
				return false;
			}

			return is_float( $val + 0 );
		}

		/**
		 * Decode the JSON object we received
		 *
		 * @param string $response
		 *
		 * @return array|mixed|object
		 *
		 * @since 2.0.0
		 * @since 2.1 - Updated to handle UTF-8 BOM character
		 */
		public function decode_response( string $response ) {

			// UTF-8 BOM handling
			$bom  = pack( 'H*', 'EFBBBF' );
			$json = preg_replace( "/^$bom/", '', $response );
			$obj  = json_decode( $json );

			if ( null !== $obj ) {
				return $obj;
			}

			return false;
		}

		/**
		 * Encode data to JSON
		 *
		 * @param mixed $data
		 *
		 * @return bool|string
		 *
		 * @since 2.0.0
		 */
		public function encode( $data ) {

			return wp_json_encode( $data );
		}

		/**
		 * Clear the Output (browser) buffers (for erroneous error messages, etc)
		 *
		 * @return string
		 */
		public function clear_buffers() {

			ob_start();

			$buffers = ob_get_clean();

			return $buffers;

		}

		/**
		 * Return or print checked field for HTML Checkbox INPUT
		 *
		 * @param mixed $needle
		 * @param mixed $haystack
		 * @param bool  $echo
		 *
		 * @return null|string
		 */
		public function checked( $needle, $haystack, $echo = false ) {

			$text = null;

			if ( is_array( $haystack ) ) {
				if ( in_array( $needle, $haystack, true ) ) {
					$text = ' checked="checked" ';
				}
			}

			if ( is_object( $haystack ) && in_array( $needle, (array) $haystack, true ) ) {
				$text = ' checked="checked" ';
			}

			if ( false === is_array( $haystack ) && false === is_object( $haystack ) ) {
				if ( $needle === $haystack ) {
					$text = ' checked="checked" ';
				}
			}

			if ( true === $echo && ! empty( $text ) ) {
				echo esc_attr__( ' checked="checked" ' );

				return null;
			}

			return $text;
		}

		/**
		 * Return or print selected field for HTML Select input
		 *
		 * @param mixed $needle
		 * @param mixed $haystack
		 * @param bool  $echo
		 *
		 * @return null|string
		 */
		public function selected( $needle, $haystack, $echo = false ) {

			$html_string = null;

			if ( is_array( $haystack ) ) {
				if ( in_array( $needle, $haystack, true ) ) {
					$html_string = ' selected="selected" ';
				}
			}

			if ( is_object( $haystack ) && in_array( $needle, (array) $haystack, true ) ) {
				$html_string = ' selected="selected" ';
			}

			if ( ! is_array( $haystack ) && ! is_object( $haystack ) ) {
				if ( $needle === $haystack ) {
					$html_string = ' selected="selected" ';
				}
			}

			if ( true === $echo && ! empty( $html_string ) ) {
				print esc_attr__( ' selected="selected" ' );
				return null;
			}

			return $html_string;
		}

		/**
		 * Generates a true random alphanumeric string of $length characters
		 *
		 * @param int    $length   Size of the string to generate
		 * @param string $keyspace The characters to use to generate the string.
		 *
		 * @return string|false   True random string of $keyspace characters
		 *
		 * Credit:
		 * @url http://stackoverflow.com/questions/4356289/php-random-string-generator/31107425#31107425
		 */
		public function random_string( $length, $keyspace = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ' ) {

			$string  = '';
			$max_len = mb_strlen( $keyspace, '8bit' ) - 1;

			try {
				for ( $i = 0; $i < $length; ++ $i ) {
					$string .= $keyspace[ random_int( 0, $max_len ) ];
				}
			} catch ( Exception $e ) {
				$this->log( 'Error generating random string: ' . $e->getMessage() );
				return false;
			}

			return $string;
		}

		/**
		 * Search through array values to check whether there's anything there
		 *
		 * @param array $array
		 *
		 * @return bool
		 */
		public function array_isnt_empty( $array ) {

			$values = array_values( $array );

			return ( empty( $values ) ? false : true );
		}

		/**
		 * Substitute [IN] for proper SQL 'IN' statement containing array of like values
		 *
		 * @param string $sql
		 * @param array  $values
		 * @param string $type
		 *
		 * @return string
		 */
		public function prepare_in( $sql, $values, $type = '%d' ) {

			global $wpdb;

			$not_in_count = substr_count( $sql, '[IN]' );

			if ( $not_in_count > 0 ) {

				$args = array(
					str_replace(
						'[IN]',
						implode( ', ', array_fill( 0, count( $values ), ( '%d' === $type ? '%d' : '%s' ) ) ),
						str_replace( '%', '%%', $sql )
					),
				);

				$substr_count = substr_count( $sql, '[IN]' );

				for ( $i = 0; $i < $substr_count; $i ++ ) {
					$args = array_merge( $args, $values );
				}

				// Sanitize the SQL variables
				$sql = call_user_func_array(
					array( $wpdb, 'prepare' ),
					array_merge( $args )
				);

			}

			return $sql;
		}

		/**
		 * Get rid of PHP notice/warning messages from buffer
		 */
		public function safe_ajax() {

			// Intentionally deactivate debug output to display/client
			// phpcs:ignore WordPress.PHP.IniSet.display_errors_Blacklisted
			ini_set( 'display_errors', '0' );
			ob_start();
			$messages = ob_get_clean();
			$this->log( $messages );
		}

		/**
		 * Connect to the license server using TLS 1.2
		 *
		 * @param mixed $handle - File handle for the pipe to the CURL process
		 */
		public function force_tls_12( $handle ) {
			// phpcs:ignore -- Intentionally setting the CURL option we need
			curl_setopt( $handle, CURLOPT_SSLVERSION, 6 );
		}

		/**
		 * The current instance of the Utilities class
		 *
		 * @return Utilities|null
		 */
		public static function get_instance() {

			if ( is_null( self::$instance ) ) {
				self::$instance = new self();
			}

			return self::$instance;
		}

		/**
		 * Backwards compatible function to support prior version of the plugin
		 *
		 * @param string $plugin_slug
		 * @param string $plugin_path
		 *
		 * @return mixed|null
		 */
		// phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid
		public static function configureUpdateServerV4( $plugin_slug, $plugin_path ) {
			return self::configure_update( $plugin_slug, $plugin_path );
		}

		/**
		 * Configure and load the plugin_update_checker
		 *
		 * @param string      $plugin_slug
		 * @param string|null $plugin_path
		 *
		 * @return mixed
		 */
		public static function configure_update( $plugin_slug, $plugin_path ) {

			$plugin_updates = null;
			$plugin         = self::get_instance();

			if ( is_null( $plugin_path ) ) {
				$plugin_path = sprintf( '%1$s/%2$s/%2$s.php', WP_PLUGIN_DIR, $plugin_slug );
			}

			if ( ! file_exists( dirname( E20R_UTILITIES_BASE_FILE ) . '/inc/yahnis-elsts/plugin-update-checker/plugin-update-checker.php' ) ) {
				$plugin->add_message( 'File not found: Unable to load the plugin update checker!', 'warning' );
				return null;
			}

			/**
			 * One-click update handler & checker
			 */
			if ( ! class_exists( '\\Puc_v4_Factory' ) ) {
				require_once sprintf( '%1$s/inc/yahnis-elsts/plugin-update-checker/plugin-update-checker.php', dirname( E20R_UTILITIES_BASE_FILE ) );
			}

			$plugin_updates = Puc_v4_Factory::buildUpdateChecker(
				sprintf( 'https://eighty20results.com/protected-content/%1$s/metadata.json', $plugin_slug ),
				$plugin_path,
				$plugin_slug
			);

			return $plugin_updates;
		}

		/**
		 * Is the specified server is the same as the Licensing server
		 *
		 * @param string|null $url - The URL to check the license server name against
		 *
		 * @return bool
		 */
		public static function is_license_server( ?string $url = null ): bool {

			if ( empty( $url ) ) {
				$url = home_url();
			}

			return (
				false !== stripos( $url, Defaults::constant( 'E20R_LICENSE_SERVER' ) ) ||
				( defined( 'E20R_LICENSE_SERVER_URL' ) && false !== stripos( $url, E20R_LICENSE_SERVER_URL ) )
			);
		}

		/**
		 * Deactivate local SSL certificate validation for local server URL
		 *
		 * @param array  $request_args
		 * @param string $url
		 *
		 * @return array
		 * @uses 'http_request_args' -> Configure Request arguments (header)
		 *
		 */
		public function set_ssl_validation_for_updates( $request_args, $url ) {

			if ( ! self::is_license_server( $url ) ) {
				return $request_args;
			}

			$request_args['sslverify'] = false;

			return $request_args;
		}
	}
}
