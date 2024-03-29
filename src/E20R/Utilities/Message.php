<?php
/**
 * Copyright (c) 2016 - 2022 - Eighty / 20 Results by Wicked Strong Chicks.
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
 * @package E20R\Utilities\Message
 */

namespace E20R\Utilities;

if ( ! defined( 'ABSPATH' ) && ( ! defined( 'PLUGIN_PATH' ) ) ) {
	die( 'Cannot access source file directly!' );
}

if ( ! class_exists( '\\E20R\\Utilities\\Message' ) ) {

	/**
	 * Stores message(s) for use by the WP Backend alert notices
	 */
	class Message {

		/**
		 * Constant indicating the WP Front-end page(s)
		 */
		const FRONTEND_LOCATION = 1000;

		/**
		 * Constant indicating the WP back-end pages (wp-admin dashboard, etc)
		 */
		const BACKEND_LOCATION = 2000;

		/**
		 * Constant indicating the default location (wp-admin, I presume)
		 */
		const DEFAULT_LOCATION = 3000;

		/**
		 * List of front or backend messages
		 *
		 * @var string[]
		 */
		private $msg = array();

		/**
		 * List of front/backend message types (valid: 'error', 'warning', 'info'
		 *
		 * @var string[]
		 */
		private $msgt = array();

		/**
		 * List of location to display the messages/message types
		 *
		 * @var int[]
		 */
		private $location = array();

		/**
		 * Message constructor.
		 *
		 * @param string $message The message text
		 * @param string $type The type of message (notice|warning|error)
		 * @param string $location Where to display the message (backend|frontend)
		 */
		public function __construct( $message = null, $type = 'notice', $location = 'backend' ) {

			// Not adding a message
			if ( empty( $message ) ) {
				return;
			}

			$utils     = Utilities::get_instance();
			$cache_key = $utils->get_util_cache_key();
			$tmp       = Cache::get( 'err_info', $cache_key );

			if ( null !== $tmp ) {

				$utils->log( 'Loading cached messages (' . count( $tmp['msg'] ) . ')' );

				$this->msg      = isset( $tmp['msg'] ) ? $tmp['msg'] : array();
				$this->msgt     = $tmp['msgt'] ? $tmp['msgt'] : array();
				$this->location = isset( $tmp['location'] ) ? $tmp['location'] : ( isset( $tmp['msgt_source'] ) ? $tmp['msgt_source'] : array() );
			}

			$location = $this->convert_destination( $location );

			$msg_found = array();

			// Look for duplicate messages
			foreach ( $this->msg as $key => $msg ) {

				if ( ! empty( $msg ) && false !== strpos( $message, $msg ) ) {
					$msg_found[] = $key;
				}

				// Fix bad location values
				if ( ! empty( $location ) ) {
					$this->location[ $key ] = $this->convert_destination( $location );
				}
			}

			// No duplicates found, so add the new one
			if ( empty( $msg_found ) ) {
				// Save new message
				$utils->log( "Adding a message to the list: {$message}" );

				$this->msg[]      = $message;
				$this->msgt[]     = $type;
				$this->location[] = $location;
			} else {

				// Potentially clean up duplicate messages
				$total = count( $msg_found );

				// Remove extra instances of the message
				for ( $i = 1; ( $total - 1 ) >= $i; $i ++ ) {
					$utils->log( 'Removing duplicate message' );
					unset( $this->msg[ $i ] );
				}
			}

			// Update the cached values
			if ( ! empty( $this->msg ) ) {
				$this->update_cache();
			}
		}

		/**
		 * Return the correct Destination constant value
		 *
		 * @param string|int $destination The location where we intend to show the message(s)
		 *
		 * @return int
		 */
		public function convert_destination( $destination ) {

			if ( is_numeric( $destination ) ) {
				return (int) $destination;
			}

			switch ( trim( strtolower( $destination ) ) ) {

				case 'backend':
					$destination = self::BACKEND_LOCATION;
					break;
				case 'frontend':
					$destination = self::FRONTEND_LOCATION;
					break;

				default:
					$destination = self::BACKEND_LOCATION;
			}

			return $destination;
		}

		/**
		 * Update the cached error/warning/notice messages
		 */
		private function update_cache() {

			$utils     = Utilities::get_instance();
			$cache_key = $utils->get_util_cache_key();

			$values = array(
				'msg'      => $this->msg,
				'msgt'     => $this->msgt,
				'location' => $this->location,
			);

			Cache::set( 'err_info', $values, DAY_IN_SECONDS, $cache_key );
		}

		/**
		 * Minimize duplication of WooCommerce alert messages
		 *
		 * @param null|bool $passthrough Ignored parameter
		 *
		 * @return bool
		 */
		public function clear_notices( $passthrough = null ) {

			wc_clear_notices();
			return $passthrough;
		}

		/**
		 * Display the error/warning/notice messages in the appropriate destination
		 *
		 * @param string|null|int $destination Where we intend to display the message(s)
		 */
		public function display( $destination = null ) {

			if ( ! is_string( $destination ) ) {
				$destination = null;
			}

			$utils     = Utilities::get_instance();
			$cache_key = $utils->get_util_cache_key();

			global $pmpro_pages;

			// Load from cache if there are no messages found
			if ( empty( $this->msg ) ) {

				$msgs = Cache::get( 'err_info', $cache_key );

				if ( ! empty( $msgs ) ) {
					$this->msg      = $msgs['msg'];
					$this->msgt     = $msgs['msgt'];
					$this->location = $msgs['location'];
				}
			}

			if ( empty( $this->msg ) ) {
				return;
			}

			if ( empty( $destination ) && Utilities::is_admin() ) {
				$destination = self::BACKEND_LOCATION;
			}

			if ( empty( $destination ) && (
					false === Utilities::is_admin() ||
					is_page( $pmpro_pages ) ||
					( function_exists( 'is_account_page' ) && is_account_page() ) ||
					( function_exists( 'is_cart' ) && is_cart() ) ||
					( function_exists( 'is_checkout' ) && is_checkout() )
				)
			) {
				$destination = self::FRONTEND_LOCATION;
			}

			$found_keys = $this->extract_by_destination( $this->convert_destination( $destination ) );

			$utils->log( 'Have a total of ' . count( $this->msg ) . ' message(s). Found ' . count( $found_keys ) . " messages for location {$destination}: " );

			foreach ( $found_keys as $key ) {

				if ( empty( $this->location ) || ! isset( $this->location[ $key ] ) ) {
					$location = self::BACKEND_LOCATION;
				} else {
					$location = $this->location[ $key ];
					unset( $this->location[ $key ] );
				}

				if ( ! empty( $this->msg[ $key ] ) ) {

					switch ( intval( $location ) ) {

						case self::FRONTEND_LOCATION:
							$utils->log( 'Showing on front-end of site' );
							$this->display_frontend( $this->msg[ $key ], $this->msgt[ $key ] );
							break;

						case self::BACKEND_LOCATION:
							$utils->log( 'Showing on back-end of site' );
							$this->display_backend( $this->msg[ $key ], $this->msgt[ $key ] );
							break;

						default:
							global $msg;
							global $msgt;

							$msg  = $this->msg[ $key ];
							$msgt = $this->msgt[ $key ];
					}

					unset( $this->msg[ $key ] );
					unset( $this->msgt[ $key ] );
				}
			}

			if ( ! empty( $this->msg ) ) {
				$this->update_cache();
			} else {
				Cache::delete( 'err_info', $cache_key );
			}
		}

		/**
		 * Return list of message keys that match the specified destination
		 *
		 * @param int $destination Where we intend to display the message(s)
		 *
		 * @return array
		 */
		private function extract_by_destination( $destination ) {

			$keys = array();

			foreach ( $this->location as $msg_key => $location ) {

				if ( $location === $destination ) {
					$keys[] = $msg_key;
				}
			}

			return $keys;
		}

		/**
		 * Display on the front-end of the site (if using WooCommerce or PMPro)
		 *
		 * @param string $message The message to display on the front-end of the website (if it supports this)
		 * @param string $type The type of message to display
		 */
		private function display_frontend( $message, $type ) {

			if ( $this->has_woocommerce() ) {

				Utilities::get_instance()->log( 'Attempting to show on WooCommerce front-end' );
				wc_add_notice( $message, $type );
			}

			if ( $this->has_pmpro() ) {

				Utilities::get_instance()->log( "Attempting to show {$message} on PMPro front-end" );

				global $pmpro_msg;
				global $pmpro_msgt;
				global $msg;
				global $msgt;

				$pmpro_msg  = $message;
				$pmpro_msgt = "pmpro_{$type}";
				$msg        = $pmpro_msg;
				$msgt       = $pmpro_msgt;

				pmpro_setMessage( $pmpro_msg, $pmpro_msgt, true );
				$this->add_pmpro_message( $pmpro_msg, $pmpro_msgt );
			}
		}

		/**
		 * Passthrough for some of the PMPro filters so we can display error message(s) on the
		 *
		 * @param mixed $arg1 Argument to pass through (ignored)
		 * @param mixed $arg2 Argument to pass through (ignored)
		 * @param mixed $arg3 Argument to pass through (ignored)
		 * @param mixed $arg4 Argument to pass through (ignored)
		 * @param mixed $arg5 Argument to pass through (ignored)
		 * @param mixed $arg6 Argument to pass through (ignored)
		 *
		 * @return mixed
		 */
		public function filter_passthrough( $arg1 = null, $arg2 = null, $arg3 = null, $arg4 = null, $arg5 = null, $arg6 = null ) {

			$utils = Utilities::get_instance();

			global $pmpro_pages;
			global $post;

			$page_list = array(
				( $pmpro_pages['billing'] ?? null ),
				( $pmpro_pages['account'] ?? null ),
			);

			if ( ! isset( $post->post_content ) || ( isset( $post->post_content ) && ! is_page( $page_list ) ) ) {
				$utils->log( 'Not on billing or account shortcode/page' );
				return $arg1;
			}

			// @phpstan-ignore-next-line
			$utils->log( "Loading error messages for account/billing page: {$post->ID}" );
			$this->display( self::FRONTEND_LOCATION );

			return $arg1;
		}

		/**
		 * WooCommerce is installed and active
		 *
		 * @return bool
		 */
		private function has_woocommerce() {
			return function_exists( 'wc_add_notice' );
		}

		/**
		 * PMPro is installed and active
		 *
		 * @return bool
		 */
		private function has_pmpro() {
			return function_exists( 'pmpro_getAllLevels' );
		}

		/**
		 * Display the PMPro error message(s)
		 *
		 * @param string $message The message to add to the PMPro page(s)
		 * @param string $message_type The type of message to add to the PMPro page(s)
		 */
		public function add_pmpro_message( $message, $message_type ) {

			Utilities::get_instance()->log( 'Adding for PMPro page' );

			if ( ! empty( $message ) ) {
				// translators: Message and type of message is configured when the HTML is called
				echo esc_html__(
					// phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralText
					sprintf(
						'<div id="pmpro_message" class="pmpro_message %1$s">%2$s</div>',
						$message_type,
						$message
					)
				);
			}
		}

		/**
		 * Display in WP Admin (the backend)
		 *
		 * @param string $msg The message to add to the WP backend (/wp-admin/)
		 * @param string $type The type of message to add to the WP backend (/wp-admin/)
		 */
		private function display_backend( $msg, $type ) {

			if ( ! Utilities::is_admin() ) {
				return;
			}

			// phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralText, WordPress.WP.I18n.MissingTranslatorsComment
			echo esc_html__( sprintf( '<div class="notice notice-%1$s is-dismissible backend">', $type ) );
			// phpcs:ignore WordPress.WP.I18n.NonSingularStringLiteralText, WordPress.WP.I18n.MissingTranslatorsComment
			echo esc_html__( sprintf( '<p>%1$s</p>', $msg ) );
			echo esc_html__( '</div>' );
		}

		/**
		 * Return all messages of a specific type
		 *
		 * @param string $type The message type to retrieve
		 *
		 * @return string[]
		 */
		public function get( $type ) {

			$messages  = array();
			$utils     = Utilities::get_instance();
			$cache_key = $utils->get_util_cache_key();
			$tmp       = Cache::get( 'err_info', $cache_key );

			// Grab from the cache (if it exists)
			if ( null !== $tmp ) {

				$this->msg      = $tmp['msg'];
				$this->msgt     = $tmp['msgt'];
				$this->location = $tmp['location'];
			}

			foreach ( $this->msgt as $message_key => $message_type ) {

				if ( $message_type === $type ) {
					$messages[] = $this->msg[ $message_key ];
				}
			}

			return $messages;

		}
	}
}
