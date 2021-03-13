<?php
/*
Plugin Name: E20R Utilities Module
Plugin URI: https://eighty20results.com/
Description: Provides functionality required by some of the Eighty/20 Results developed plugins
Version: 2.0
Author: Thomas Sjolshagen <thomas@eighty20results.com>
Author URI: https://eighty20results.com/thomas-sjolshagen/
License: GPLv2

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

if ( ! class_exists( 'E20R\Utilities\Loader' ) ) {

	class Loader {

		public function __construct() {
			if ( function_exists( 'plugin_dir_path' ) ) {
				require_once \plugin_dir_path( __FILE__ ) . 'inc/autoload.php';
			} else {
				require_once __DIR__ . '/inc/autoload.php';
			}
		}
		/**
		 * Class auto-loader for the Utilities Module
		 *
		 * @param string $class_name Name of the class to auto-load
		 *
		 * @return bool
		 * @since  1.0
		 * @access public static
		 */
		public static function auto_load( $class_name ) {

			if ( false === stripos( $class_name, 'e20r' ) ) {
				return false;
			}

			$parts  = explode( '\\', $class_name );
			$c_name = preg_replace( '/_/', '-', $parts[ ( count( $parts ) - 1 ) ] );
			$c_name = strtolower( $c_name );

			if ( function_exists( 'plugin_dir_path' ) ) {
				$base_path = \plugin_dir_path( __FILE__ );
				$src_path  = \plugin_dir_path( __FILE__ ) . 'src/';
			} else {
				$base_path = __DIR__;
				$src_path  = __DIR__ . '/src/';
			}

			if ( file_exists( $src_path ) ) {
				$base_path = $src_path;
			}

			$filename = "class-{$c_name}.php";

			$iterator = new RecursiveDirectoryIterator(
				$base_path,
				RecursiveDirectoryIterator::SKIP_DOTS |
				RecursiveIteratorIterator::SELF_FIRST |
				RecursiveIteratorIterator::CATCH_GET_CHILD |
				RecursiveDirectoryIterator::FOLLOW_SYMLINKS
			);

			// Locate class member files, recursively
			$filter = new RecursiveCallbackFilterIterator(
				$iterator,
				/** @SuppressWarnings("unused") */
				function ( $current, $key, $iterator ) use ( $filename ) {
					$file_name = $current->getFilename();

					// Skip hidden files and directories.
					if ( '.' === $file_name[0] || '..' === $file_name ) {
						return false;
					}

					if ( $current->isDir() ) {
						// Only recurse into intended subdirectories.
						return $file_name() === $filename;
					} else {
						// Only consume files of interest.
						return strpos( $file_name, $filename ) === 0;
					}
				}
			);

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
	}
}

# Load the composer autoloader for the 10quality utilities
if ( file_exists( __DIR__ . '/inc/autoload.php' ) ) {
	require_once __DIR__ . '/inc/autoload.php';
}

try {
	spl_autoload_register( 'E20R\Utilities\Loader::auto_load' );
} catch ( \Exception $exception ) {
	// phpcs:ignore
	error_log( 'Unable to register autoloader: ' . $exception->getMessage(), E_USER_ERROR );
	return false;
}

if ( function_exists( 'add_filter' ) ) {
	\add_filter( 'e20r_utilities_module_installed', '__return_true', -1, 1 );
}

if ( class_exists( '\E20R\Utilities\Utilities' ) ) {
	Utilities::configure_update( '00-e20r-utilities', __FILE__ );
}
