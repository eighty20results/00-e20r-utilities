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
		 * Class auto-loader for the Utilities Module
		 *
		 * @param string $class_name Name of the class to auto-load
		 *
		 * @since  1.0
		 * @access public static
		 */
		public function auto_load( string $class_name ): bool {

			if ( false === stripos( $class_name, 'e20r' ) ) {
				return false;
			}

			if ( function_exists( 'plugin_dir_path' ) ) {
				$base_path = \plugin_dir_path( __FILE__ );
				$src_path  = \plugin_dir_path( __FILE__ ) . 'src/';
			} else {
				$base_path = __DIR__;
				$src_path  = __DIR__ . '/src/E20R/';
			}

			if ( file_exists( $src_path ) ) {
				$base_path = $src_path;
			}

			$filename = "{$class_name}.php";

			try {
				$iterator = new RecursiveDirectoryIterator(
					$base_path,
					RecursiveDirectoryIterator::SKIP_DOTS |
					RecursiveIteratorIterator::SELF_FIRST |
					RecursiveIteratorIterator::CATCH_GET_CHILD |
					RecursiveDirectoryIterator::FOLLOW_SYMLINKS
				);
			} catch ( \Exception $e ) {
				error_log( 'Error: ' . $e->getMessage() ); // phpcs:ignore
				return false;
			}

			try {
				// Locate class member files, recursively
				$filter = new RecursiveCallbackFilterIterator(
					$iterator,
					function ( $current, $key, $iterator ) use ( $filename ) {

						// Skip hidden files and directories.
						if ( '.' === $current->getFilename()[0] || '..' === $current->getFilename() ) {
							return false;
						}

						if ( $current->isDir() ) {
							// Only recurse into intended subdirectories.
							return $current->getFilename() === $filename;
						} else {
							// Only consume files of interest.
							return str_starts_with( $current->getFilename(), $filename );
						}
					}
				);
			} catch ( \Exception $e ) {
				error_log( 'Autoloader error: ' . $e->getMessage() ); // phpcs:ignore
				return false;
			}

			try {
				/** @SuppressWarnings("unused") */
				$rec_iterator = new RecursiveIteratorIterator(
					$iterator,
					RecursiveIteratorIterator::LEAVES_ONLY,
					RecursiveIteratorIterator::CATCH_GET_CHILD
				);
			} catch ( UnexpectedValueException $uvexception ) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				error_log(
					sprintf(
						"Error: %s.\nState: %s",
						$uvexception->getMessage(),
						// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r
						print_r( $iterator, true )
					)
				);
				return false;
			}

			// Walk through filesystem looking for our class file
			foreach ( $rec_iterator as $f_filename => $f_file ) {

				$class_path = sprintf( '%s/%s', $f_file->getPath(), basename( $f_filename ) );

				if ( $f_file->isFile() && false !== strpos( $class_path, $filename ) ) {
					/** @noinspection PhpIncludeInspection */
					require_once $class_path;
				}
			}

			return true;
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
//
//try {
//	spl_autoload_register( array( $loader, 'auto_load' ) );
//} catch ( \Exception $exception ) {
//	// phpcs:ignore
//	error_log( 'Unable to register autoloader: ' . $exception->getMessage(), E_USER_ERROR );
//	return false;
//}

if ( function_exists( '\add_action' ) ) {
	\add_action( 'plugins_loaded', array( $loader, 'utilities_loaded' ), -1 );
	\add_action( 'plugins_loaded', array( Utilities::get_instance(), 'load_text_domain' ), -1 );
}

if ( class_exists( '\E20R\Utilities\Utilities' ) && defined( 'WP_PLUGIN_DIR' ) ) {
	Utilities::configure_update( '00-e20r-utilities', __FILE__ );
}
