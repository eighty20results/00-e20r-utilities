<?php
/*
Plugin Name: E20R Utilities Module
Plugin URI: https://eighty20results.com/
Description: Provides functionality required by some of the Eighty/20 Results developed plugins
Version: 2.0.6
Requires PHP: 7.3
Author: Thomas Sjolshagen <thomas@eighty20results.com>
Author URI: https://eighty20results.com/thomas-sjolshagen/
License: GPLv2
Text Domain: 00-e20r-utilities
Domain Path: languages/

 * Copyright (c) 2014 - 2021. - Eighty / 20 Results by Wicked Strong Chicks.
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
 */

namespace E20R\Utilities;

use function \add_action;
use function \add_filter;

// Deny direct access to the file
if ( ! defined( 'ABSPATH' ) && function_exists( 'wp_die' ) ) {
	wp_die( 'Cannot access file directly' );
}

if ( ! defined( 'E20R_UTILITIES_BASE_FILE' ) ) {
	define( 'E20R_UTILITIES_BASE_FILE', __FILE__ );
}

// Load the PSR-4 Autoloader
require_once __DIR__ . '/inc/autoload.php';

if ( ! class_exists( 'E20R\Utilities\Loader' ) ) {

	/**
	 * Class Loader
	 * @package E20R\Utilities
	 */
	class Loader {

		/**
		 * @var Utilities|null $utils
		 */
		private $utils = null;

		/**
		 * The priority for the 'e20r_utilities_module_installed' filter handler
		 *
		 * @var int $default_priority
		 */
		private $default_priority = 99999;
		/**
		 * Loader constructor.
		 * Loads the default PSR-4 Autoloader and configures a couple of required action handlers
		 */
		public function __construct( $utils = null ) {
			if ( ! class_exists( '\E20R\Utilities\Utilities' ) ) {
				wp_die(
					esc_attr__(
						"Error: Couldn't load the Utilities class included in this module. Please deactivate the E20R Utilities Module plugin!",
						'00-e20r-utilities'
					)
				);
			}

			// The loader uses the Utilities class to load action handlers,
			// so we need it for testing purposes
			if ( empty( $utils ) ) {
				$message = new Message();
				$utils   = new Utilities( $message );
			}
			$this->utils = $utils;

			// Add required action for language modules (I18N)
			add_action( 'plugins_loaded', array( $this->utils, 'load_text_domain' ), 11 );
		}

		/**
		 * Add filter to indicate this plugin is active
		 */
		public function utilities_loaded() {
			$this->utils->log( 'Confirms we loaded this E20R Utilities module' );
			// (try to) make sure this executes last
			add_filter( 'e20r_utilities_module_installed', array( $this, 'making_sure_we_win' ), $this->default_priority, 1 );
		}

		/**
		 * Function to make sure the last filter hook executed for
		 * 'e20r_utilities_module_installed' returns true (since this plugin is active)
		 *
		 * @param bool $is_installed
		 *
		 * @return bool
		 */
		public function making_sure_we_win( $is_installed ): bool {
			global $wp_filter;

			$max_priority    = $this->get_max_hook_priority();
			$bump_priority   = false;
			$default_handler = has_filter( 'e20r_utilities_module_installed', array( $this, 'making_sure_we_win' ) );
			$filter_count    = isset( $wp_filter['e20r_utilities_module_installed']->callbacks[ $this->default_priority ] ) ?
				count( $wp_filter['e20r_utilities_module_installed']->callbacks[ $this->default_priority ] ) :
				0;

			// Remove unnecessary executions of extra instance of the 'making_sure_we_win' hook handler (in case it's executed more than once)
			if ( $filter_count > 1 ) {
				$hook_handlers = array_keys( $wp_filter['e20r_utilities_module_installed']->callbacks[ $this->default_priority ] );
				$same_hook     = array();

				foreach ( $hook_handlers as $key_id => $hook_id ) {
					if ( 1 === preg_match( '/making_sure_we_win/', $hook_id ) && 0 !== $key_id ) {
						$same_hook[] = $key_id;
					}
				}

				// Clean up so we don't go bananas with adding extra insurance handlers
				if ( count( $same_hook ) >= 1 ) {
					foreach ( $same_hook as $hook_key ) {
						unset( $wp_filter['e20r_utilities_module_installed']->callbacks[ $this->default_priority ][ $hook_handlers[ $hook_key ] ] );
					}
					$filter_count = 1;
				}
			}

			// Latest (highest) priority hook is above the default value
			// and the handler has a hook priority less or same as latest hook handler
			if ( ( $this->default_priority < $max_priority ) && ( $default_handler <= $max_priority ) ) {
				// Need to bump priority and make sure we always return true;
				$bump_priority = true;
			}

			if ( false === $bump_priority && 1 < $filter_count ) {
				// Have more than a single hook at the default (high) priority, so need make sure we always return true.
				$bump_priority = true;
			}

			if ( true === $bump_priority ) {
				$this->default_priority = ( (int) $max_priority + 10 );
				$this->utils->log( "Bumping filter priority to {$this->default_priority} so we make sure we always return true since this plugin _is_ activated!" );
				add_filter( 'e20r_utilities_module_installed', '__return_true', $this->default_priority, 1 );
				ksort( $wp_filter );
				$this->default_priority = 99999;
			}

			return true;
		}

		/**
		 * Returns the (current) default priority for the final 'e20r_utilities_module_installed' hook
		 *
		 * @return int
		 */
		public function get_default_priority() : int {
			return $this->default_priority;
		}

		/**
		 * Set the default priority for the default 'e20r_utilities_module_installed' hook to 99999
		 */
		public function reset_priority() {
			$this->default_priority = 99999;
		}

		/**
		 * Returns the highest priority value for the 'e20r_utilities_module_installed' filter hooks
		 *
		 * @return int
		 */
		public function get_max_hook_priority(): int {
			global $wp_filter;
			$filter_priority_list = array_keys( $wp_filter['e20r_utilities_module_installed']->callbacks );
			rsort( $filter_priority_list );
			return (int) $filter_priority_list[0];
		}
	}
}

if ( function_exists( '\add_action' ) ) {
	$loader = new Loader();
	\add_action( 'plugins_loaded', array( $loader, 'utilities_loaded' ), 10 );
}

// One-click update support for the plugin
if ( class_exists( '\E20R\Utilities\Utilities' ) && defined( 'WP_PLUGIN_DIR' ) ) {
	Utilities::configure_update( '00-e20r-utilities', __FILE__ );
}
