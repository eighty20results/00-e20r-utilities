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

use RecursiveCallbackFilterIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use UnexpectedValueException;

// Deny direct access to the file
if ( ! defined( 'ABSPATH' ) && function_exists( 'wp_die' ) ) {
	wp_die( 'Cannot access file directly' );
}

if ( ! defined( 'E20R_UTILITIES_BASE_FILE' ) ) {
	define( 'E20R_UTILITIES_BASE_FILE', __FILE__ );
}

if ( ! class_exists( 'E20R\Utilities\Loader' ) ) {

	/**
	 * Class Loader
	 * @package E20R\Utilities
	 */
	class Loader {

		// Load the default PSR-4 Autoloader
		public function __construct() {
			require_once __DIR__ . '/inc/autoload.php';
		}

		/**
		 * Add filter to indicate this plugin is active
		 */
		public function utilities_loaded() {
			add_filter( 'e20r_utilities_module_installed', '__return_true', -1, 1 );
		}
	}
}

$loader = new Loader();

if ( function_exists( '\add_action' ) && class_exists( '\E20R\Utilities\Utilities' ) ) {
	\add_action( 'plugins_loaded', array( $loader, 'utilities_loaded' ), -1 );
	\add_action( 'plugins_loaded', array( Utilities::get_instance(), 'load_text_domain' ), -1 );
}

if ( class_exists( '\E20R\Utilities\Utilities' ) && defined( 'WP_PLUGIN_DIR' ) ) {
	Utilities::configure_update( '00-e20r-utilities', __FILE__ );
}
