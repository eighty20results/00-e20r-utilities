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
		 * Loader constructor.
		 * Loads the default PSR-4 Autoloader and configures a couple of required action handlers
		 */
		public function __construct( $utils = null ) {
			if ( ! class_exists( '\E20R\Utilities\Utilities' ) ) {
				wp_die(
					esc_attr__(
						"Error: Couldn't load the Utilities class included in this module!",
						'00-e20r-utilities'
					)
				);
			}

			// The loader uses the Utilities class to load action handlers,
			// so we need it for testing purposes
			if ( empty( $utils ) ) {
				$utils = new Utilities();
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
			// (try to) Make sure this executes last
			add_filter( 'e20r_utilities_module_installed', '__return_true', 99999, 1 );
		}
	}
}

if ( function_exists( '\add_action' ) ) {
	\add_action( 'plugins_loaded', array( new Loader(), 'utilities_loaded' ), 10 );
}

// One-click update support for the plugin
if ( class_exists( '\E20R\Utilities\Utilities' ) && defined( 'WP_PLUGIN_DIR' ) ) {
	Utilities::configure_update( '00-e20r-utilities', __FILE__ );
}
