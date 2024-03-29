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
 * @package E20R\Utilities\E20R_Async_Request
 */

namespace E20R\Utilities;

if ( ! defined( 'ABSPATH' ) && ( ! defined( 'PLUGIN_PATH' ) ) ) {
	die( 'Cannot access source file directly!' );
}

/**
 * WP Async Request
 *
 * @package WP-Background-Processing
 *
 * @credit https://github.com/A5hleyRich/wp-background-processing
 * @since   1.9.6 - ENHANCEMENT: Added fixes and updates from EWWW Image Optimizer code
 */

if ( ! class_exists( '\\E20R\\Utilities\\E20R_Async_Request' ) ) {
	/**
	 * Abstract E20R_Async_Request class.
	 *
	 * @abstract
	 */
	abstract class E20R_Async_Request {
		/**
		 * Prefix
		 *
		 * (default value: 'wp')
		 *
		 * @var string
		 * @access protected
		 */
		protected $prefix = 'e20r';
		/**
		 * Action
		 *
		 * (default value: 'async_request')
		 *
		 * @var string
		 * @access protected
		 */
		protected $action = 'async_request';
		/**
		 * Identifier
		 *
		 * @var mixed
		 * @access protected
		 */
		protected $identifier;
		/**
		 * Data
		 *
		 * (default value: array())
		 *
		 * @var array
		 * @access protected
		 */
		protected $data = array();

		/**
		 * The request arguments used when processing asynchronous requests
		 *
		 * @var mixed $query_args
		 */
		protected $query_args;

		/**
		 * URL for the query. Empty by default
		 *
		 * @var string $query_url
		 */
		protected $query_url;

		/**
		 * The arguments supplied using the POST HTTP action
		 *
		 * @var array $post_args
		 */
		protected $post_args;

		/**
		 * Initiate new async request
		 */
		public function __construct() {
			$this->identifier = $this->prefix . '_' . $this->action;
			add_action( 'wp_ajax_' . $this->identifier, array( $this, 'maybe_handle' ) );
			add_action( 'wp_ajax_nopriv_' . $this->identifier, array( $this, 'maybe_handle' ) );
		}

		/**
		 * Set data used during the request
		 *
		 * @param array $data Data.
		 *
		 * @return $this
		 */
		public function data( $data ) {
			$this->data = $data;

			return $this;
		}

		/**
		 * Dispatch the async request
		 *
		 * @return array|\WP_Error
		 */
		public function dispatch() {

			$url   = esc_url( add_query_arg( $this->get_query_args(), $this->get_query_url() ) );
			$utils = Utilities::get_instance();
			$utils->log( "Using URL: {$url} to submit request" );
			$args = $this->get_post_args();

			return wp_remote_post( esc_url_raw( $url ), $args );
		}

		/**
		 * Get query args
		 *
		 * @return array
		 */
		protected function get_query_args() {

			if ( property_exists( $this, 'query_args' ) && ! empty( $this->query_args ) ) {
				return $this->query_args;
			}

			return array(
				'action' => $this->identifier,
				'nonce'  => wp_create_nonce( $this->identifier ),
			);
		}

		/**
		 * Get query URL
		 *
		 * @return string
		 */
		protected function get_query_url() {

			$utils = Utilities::get_instance();
			$utils->log( "Is the query_url param set? {$this->query_url}" );

			if ( property_exists( $this, 'query_url' ) && ! empty( $this->query_url ) ) {
				return $this->query_url;
			}

			return admin_url( 'admin-ajax.php' );
		}

		/**
		 * Get post args
		 *
		 * @return array
		 */
		protected function get_post_args() {
			if ( property_exists( $this, 'post_args' ) && ! empty( $this->post_args ) ) {
				return $this->post_args;
			}

			return array(
				'timeout'   => 0.01,
				'blocking'  => false,
				'body'      => $this->data,
				'cookies'   => $_COOKIE,
				'sslverify' => apply_filters( 'https_local_ssl_verify', false ),
			);
		}

		/**
		 * Maybe handle
		 *
		 * Check for correct nonce and pass to handler.
		 */
		public function maybe_handle() {

			$utils = Utilities::get_instance();

			// Don't lock up other requests while processing
			session_write_close();
			check_ajax_referer( $this->identifier, 'nonce' );

			$this->handle();

			$utils->log( 'Terminating for single request' );
			wp_die();
		}

		/**
		 * Handle
		 *
		 * Override this method to perform any actions required
		 * during the async request.
		 */
		abstract protected function handle();
	}
}
