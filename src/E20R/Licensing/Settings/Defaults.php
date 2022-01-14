<?php
/**
 * Copyright (c) 2021 - 2022. - Eighty / 20 Results by Wicked Strong Chicks.
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
 * @package E20R\Licensing\Settings\Defaults
 */

namespace E20R\Licensing\Settings;

use E20R\Licensing\Exceptions\BadOperation;
use E20R\Licensing\Exceptions\ConfigDataNotFound;
use E20R\Licensing\Exceptions\DefinedByConstant;
use E20R\Exceptions\InvalidSettingsKey;
use E20R\Utilities\Message;
use E20R\Utilities\Utilities;
use Exception;
use ReflectionClass;

if ( ! class_exists( '\E20R\Licensing\Settings\Defaults' ) ) {
	/**
	 * Class Defaults
	 *
	 * @package E20R\Licensing\Settings
	 */
	class Defaults {

		const READ_CONSTANT   = 1;
		const UPDATE_CONSTANT = 0;

		// @codingStandardsIgnoreStart
		// Ignoring the case as we're using this to fake const values (simplify unit testing, for instance)
		private $E20R_LICENSE_SECRET_KEY = '5687dc27b50520.33717427';
		private $E20R_STORE_CONFIG       = '{"store_code":"L4EGy6Y91a15ozt","server_url":"https://eighty20results.com"}';
		private $E20R_LICENSE_SERVER     = 'eighty20results.com';
		private $E20R_LICENSE_SERVER_URL = 'https://eighty20results.com';
		private $E20R_LICENSING_DEBUG    = null;
		/**
		 * License status constants
		 */
		private $E20R_LICENSE_MAX_DOMAINS   = 2048;
		private $E20R_LICENSE_REGISTERED    = 1024;
		private $E20R_LICENSE_DOMAIN_ACTIVE = 512;
		private $E20R_LICENSE_ERROR         = 256;
		private $E20R_LICENSE_BLOCKED       = 128;
		// @codingStandardsIgnoreEnd

		/**
		 * Instance of this class
		 *
		 * @var null|Defaults
		 */
		protected static $instance = null;

		/**
		 * The version number for this plugin (E20R Licensing module)
		 *
		 * @var string $version
		 */
		protected $version = '3.2';

		/**
		 * Should the version number set be locked
		 *
		 * @var bool $version_locked
		 */
		protected $version_locked = true;

		/**
		 * Default server URL for this plugin
		 *
		 * @var string $server_url
		 */
		protected $server_url = 'https://eighty20results.com';

		/**
		 * Should the E20R_LICENSE_SERVER_URL be locked
		 *
		 * @var bool $server_url_locked
		 */
		protected $server_url_locked = false;

		/**
		 * Rest end-point for the license server plugin
		 *
		 * @var string $rest_url
		 */
		protected $rest_url = '/wp-json/woo-license-server/v1';

		/**
		 * Lock the "default" rest_url setting
		 *
		 * @var bool $rest_url_locked
		 */
		protected $rest_url_locked = true;
		/**
		 * AJAX end-point for the license server plugin
		 *
		 * @var string $ajax_url
		 */
		protected $ajax_url = '/wp-admin/wp-ajax.php';
		/**
		 * Lock the "default" ajax_url setting
		 *
		 * @var bool $ajax_url_locked
		 */
		protected $ajax_url_locked = true;
		/**
		 * Whether to use the REST or AJAX API
		 *
		 * @var bool $use_rest
		 */
		protected $use_rest = true;
		/**
		 * Lock the "default" use_rest setting
		 *
		 * @var bool $use_rest_locked
		 */
		protected $use_rest_locked = true;

		/**
		 * Whether to add verbose license operations debug logging to the error_log() destination
		 *
		 * @var bool $debug_logging
		 */
		protected $debug_logging = false;

		/**
		 * Whether to lock the E20R_LICENSING_DEBUG constant for updates
		 *
		 * @var bool $debug_logging_locked
		 */
		protected $debug_logging_locked = false;

		/**
		 * Is the debug_logging variable already defined because the E20R_LICENSING_DEBUG constant is set to 'true'
		 *
		 * @var bool $debug_logging_defined_by_constant
		 */
		protected $debug_logging_defined_by_constant = false;

		/**
		 * Is the server_url variable already defined because the 'E20R_LICENSE_SERVER' constant is set to 'true'
		 *
		 * @var bool $server_url_defined_by_constant
		 */
		protected $server_url_defined_by_constant = false;

		/**
		 * The WooCommerce store code to use
		 *
		 * @var string|null
		 */
		protected $store_code = null;
		/**
		 * Lock the "default" use_rest setting
		 *
		 * @var bool $store_code_locked
		 */
		protected $store_code_locked = true;

		/**
		 * The connection URI for the license client
		 *
		 * @var string|null $connection_uri
		 */
		protected $connection_uri = null;

		/**
		 * Default settings for the plugin
		 *
		 * @var array $default
		 */
		private $default = array();

		/**
		 * The Utilities class instance
		 *
		 * @var Utilities|null $utils
		 */
		private $utils = null;

		/**
		 * Defaults constructor.
		 *
		 * @param bool           $use_rest - Use REST endpoint or not
		 * @param null|Utilities $utils - Instance of the Utilities class
		 * @param null|string    $config_json - JSON blob containing the required settings (mostly used for testing purposes)
		 * @param null|bool      $ignore_constants - Don't use defined constants to set values in this class
		 *
		 * @throws ConfigDataNotFound - Thrown when specifying JSON blob to use but it's empty or no config is found
		 * @throws InvalidSettingsKey - Invalid parameter (setting) specified for this class
		 * @throws Exception - Generic catch-all exception
		 */
		public function __construct( bool $use_rest = true, ?Utilities $utils = null, ?string $config_json = null, ?bool $ignore_constants = false ) {

			if ( empty( $utils ) ) {
				$message = new Message();
				$utils   = new Utilities( $message );
			}

			$this->utils   = $utils;
			$this->default = array(
				'version'       => '3.2',
				'server_url'    => 'https://eighty20results.com',
				'rest_url'      => '/wp-json/woo-license-server/v1',
				'ajax_url'      => '/wp-admin/wp-ajax.php',
				'use_rest'      => true,
				'debug_logging' => false,
				'store_code'    => null,
			);

			foreach ( $this->default as $default_key => $default_value ) {
				$this->unlock( $default_key );
				$this->set( $default_key, $default_value );
				$this->lock( $default_key );
			}

			// Set the config for the store (as supplied by the caller)
			if ( defined( 'PLUGIN_PHPUNIT' ) && true === PLUGIN_PHPUNIT && null !== $config_json ) {
				$this->constant( 'E20R_STORE_CONFIG', self::UPDATE_CONSTANT, $config_json );
			}

			// Load the config settings from the locally installed config file
			try {
				$this->read_config();
			} catch ( ConfigDataNotFound | Exception | InvalidSettingsKey $exp ) {
				$this->utils->log( 'Error: ' . $exp->getMessage() );
				throw $exp;
			}

			// @codeCoverageIgnoreStart
			// @codingStandardsIgnoreStart
			// Remove the constant if possible and use the Defaults::constant() approach instead (code coverage not needed)
			if ( false === $ignore_constants && defined( 'E20R_LICENSING_DEBUG' ) ) {
				$this->constant( 'E20R_LICENSING_DEBUG', self::UPDATE_CONSTANT, E20R_LICENSING_DEBUG );
				if ( extension_loaded( 'runkit' ) && function_exists( 'runkit_constant_remove' ) ) {
					runkit_constant_remove( 'E20R_LICENSE_SERVER_URL' ); // phpcs:ignore
				}
			}

			if ( false === $ignore_constants && defined( 'E20R_LICENSE_SERVER_URL' ) && ! empty( E20R_LICENSE_SERVER_URL ) ) {
				// Update the server URL and set lock it for others
				$this->constant( 'E20R_LICENSE_SERVER_URL', self::UPDATE_CONSTANT, E20R_LICENSE_SERVER_URL );

				// Attempt to remove the constant (if possible)
				if ( extension_loaded( 'runkit' ) && function_exists( 'runkit_constant_remove' ) ) {
					runkit_constant_remove( 'E20R_LICENSE_SERVER_URL' ); // phpcs:ignore
				}
			}
			// @codeCoverageIgnoreEnd
			// @codingStandardsIgnoreEnd

			$this->use_rest      = $use_rest;
			$this->debug_logging = $this->constant( 'E20R_LICENSING_DEBUG' );
			$this->build_connection_uri();
		}

		/**
		 * Create the connection_uri setting for the plugin
		 */
		private function build_connection_uri() {
			$default_path = $this->rest_url;

			if ( ! $this->use_rest ) {
				$default_path = $this->ajax_url;
			}

			$this->connection_uri = sprintf( '%1$s%2$s', $this->server_url, $default_path );
		}

		/**
		 * Loads the configuration from the current directory.
		 *
		 * @param string|null|bool $json_blob - A user supplied JSON blob containing the base settings for this class
		 *
		 * @throws ConfigDataNotFound - Thrown when there's missing data in the JSON blob
		 * @throws InvalidSettingsKey - Thrown when the JSON blob specifies a class parameter that doesn't exist
		 * @throws Exception - Catch-all exception with logging
		 */
		public function read_config( $json_blob = null ) {

			if ( empty( $json_blob ) ) {
				$json_blob = $this->constant( 'E20R_STORE_CONFIG' );
			}

			if ( empty( $json_blob ) ) {
				throw new ConfigDataNotFound(
					esc_attr__( 'No configuration data found', '00-e20r-utilities' )
				);
			}

			$settings = json_decode( $json_blob, true );

			if ( null === $settings || empty( $settings ) ) {
				throw new ConfigDataNotFound(
					esc_attr__( 'Unable to decode the configuration data', '00-e20r-utilities' )
				);
			}

			$settings = array_map( 'trim', $settings );
			foreach ( $settings as $key => $value ) {
				try {
					// As part of the configuration update, we need to be able to unlock default variables
					// so they can be updated per the requested configuration
					$is_locked_param = "{$key}_locked";
					$was_unlocked    = false;
					// Unlock pre-locked variables so we can read and set our configuration
					if ( true === $this->{$is_locked_param} ) {
						$this->unlock( $key );
						$was_unlocked = true;
					}
					// We update the default value for the specified key
					$this->set( $key, $value );
					if ( $was_unlocked ) {
						$this->unlock( $key );
					}
				} catch ( InvalidSettingsKey | Exception $e ) {
					$this->utils->log( 'Error: ' . esc_attr( $e->getMessage() ) );
					throw $e;
				}
			}
		}

		/**
		 * Lock the default setting (so it cannot be updated)
		 *
		 * @param string $setting_name - Name of the class parameter to "lock"
		 *
		 * @throws BadOperation - Raised when the specified class parameter doesn't exist
		 */
		public function lock( string $setting_name = 'server_url' ) {
			$parameter = "{$setting_name}_locked";
			if ( ! isset( $this->{$parameter} ) ) {
				throw new BadOperation(
					sprintf(
						// translators: %1$s - the parameter name provided by the caller
						esc_attr__( 'Error: Cannot lock "%1$s". Invalid parameter name', '00-e20r-utilities' ),
						$setting_name
					)
				);
			}

			if ( $this->{$parameter} ) {
				return;
			}
			$this->{$parameter} = true;
		}

		/**
		 * Unlock the default setting (so it can be updated)
		 *
		 * @param string $setting_name - Class parameter (name) to unlock
		 *
		 * @throws BadOperation - Raised when the specified parameter isn't defined by this class
		 */
		public function unlock( string $setting_name = 'server_url' ) {
			$parameter = "{$setting_name}_locked";
			if ( ! isset( $this->{$parameter} ) ) {
				throw new BadOperation(
					sprintf(
						// translators: %1$s - the parameter name provided by the caller
						esc_attr__( 'Error: Cannot unlock "%1$s". Invalid parameter name', '00-e20r-utilities' ),
						$setting_name
					)
				);
			}

			if ( false === $this->{$parameter} ) {
				return;
			}
			$this->{$parameter} = false;
		}

		/**
		 * Set/Read the class constant(s)
		 *
		 * @param string $name - Name of constant to process
		 * @param int    $operation - The operation to apply (read/update)
		 * @param mixed  $value - The value to set the named constant to
		 *
		 * @return bool|string - Return true if successful, false or raise exception if not
		 * @throws InvalidSettingsKey - Raised if the specified parameter name doesn't exist in this class
		 * @throws BadOperation - Attempted to perform an unsupported operation on a constant
		 */
		public function constant( string $name, int $operation = self::READ_CONSTANT, $value = null ) {

			if ( ! property_exists( self::class, $name ) ) {
				throw new InvalidSettingsKey(
					sprintf(
					// translators: %1$s - Name of requested constant
						esc_attr__( '"%1$s" is not a valid Defaults() constant!', '00-e20r-utilities' ),
						$name
					)
				);
			}

			if ( ! in_array( $operation, array( self::READ_CONSTANT, self::UPDATE_CONSTANT ), true ) ) {
				throw new BadOperation(
					sprintf(
						// translators: %d - the operation constant to perform
						esc_attr__( '%d is an invalid operation', '00-e20r-utilities' ),
						$operation
					)
				);
			}

			switch ( $operation ) {
				case self::READ_CONSTANT:
					return $this->{$name};
				case self::UPDATE_CONSTANT:
					$this->{$name} = $value;
					// Need to make sure the parameters are
					// the same as the constant value(s)
					switch ( $name ) {
						case 'E20R_LICENSE_SERVER_URL':
							$this->server_url                     = $value;
							$this->server_url_locked              = true;
							$this->server_url_defined_by_constant = true;
							break;
						case 'E20R_LICENSING_DEBUG':
							$this->debug_logging                     = $value;
							$this->debug_logging_defined_by_constant = true;
							$this->debug_logging_locked              = true;
							break;
					}
					return true;
			}
			return false;
		}

		/**
		 * Set the specified Default variable value
		 *
		 * @param string $name - Name of parameter to set
		 * @param mixed  $value - Value to assign to the named parameter
		 *
		 * @return bool - True if successfully updated
		 *
		 * @throws BadOperation - Raised when the specified parameter is locked
		 * @throws InvalidSettingsKey - Raised when attempting to set a parameter that doesn't exist
		 * @throws DefinedByConstant - Raised when attempting to set a parameter that was previously defined by a constant
		 */
		public function set( string $name, $value ) : bool {

			try {
				$this->exists( $name );
			} catch ( InvalidSettingsKey $exception ) {
				throw $exception;
			}
			$lock_param   = "{$name}_locked";
			$is_constant  = "{$name}_defined_by_constant";
			$constant_map = array(
				'debug_logging_defined_by_constant' => 'E20R_LICENSING_DEBUG',
				'server_url_defined_by_constant'    => 'E20R_LICENSE_SERVER',
			);

			if ( property_exists( $this, $is_constant ) && true === $this->{$is_constant} ) {
				throw new DefinedByConstant(
					sprintf(
					// translators: %1$s - the supplied setting name, %2$s - constant name
						esc_attr__(
							'%1$s is already defined by the "%2$s" constant',
							'00-e20r-utilities'
						),
						esc_attr( $name ),
						$constant_map[ $is_constant ]
					)
				);
			}

			// Exit if the constant has been set for DEBUG
			if ( 'debug_logging' === $name && false === $this->debug_logging_locked ) {
				$this->debug_logging = $value;
				$this->lock( 'debug_logging' );
				return true;
			}

			// Set and exit if we're looking for the server_url
			if ( 'server_url' === $name && false === $this->server_url_locked ) {
				$this->server_url = $value;
				$this->build_connection_uri();
				$this->lock( 'server_url' );
				return true;
			}

			if ( true === $this->{$lock_param} ) {
				throw new BadOperation(
					sprintf(
					// translators: %1$s - the supplied setting name
						esc_attr__(
							'"%1$s" is a default setting and cannot be updated',
							'00-e20r-utilities'
						),
						esc_attr( $name )
					)
				);
			}

			// Do we need to change the value?
			$default = $this->get_default( $name );

			if ( $this->{$name} !== $default && $this->{$name} !== $value ) {
				$this->{$name} = $value;
			}

			if ( $this->{$name} === $default && $value !== $default ) {
				$this->{$name} = $value;
			}

			if ( empty( $value ) && false !== $value && ! is_bool( $this->{$name} ) ) {
				$this->{$name} = $default;
			}

			$this->build_connection_uri();
			return true;
		}

		/**
		 * Return the default value for the parameter
		 *
		 * @param string $name - Name of parameter to get default value for
		 *
		 * @return string|bool|null
		 * @throws InvalidSettingsKey - The parameter specified does not exist
		 */
		public function get_default( string $name ) {

			if ( ! property_exists( $this, $name ) ) {
				throw new InvalidSettingsKey(
					sprintf(
					// translators: %1$s - Supplied parameter name
						esc_attr__( '"%1$s" is not a valid default setting', '00-e20r-utilities' ),
						$name
					)
				);
			}

			return $this->default[ $name ];
		}

		/**
		 * Return the value for the specified parameter (if it exists)
		 *
		 * @param string $name - Name of the parameter to get the current value of
		 *
		 * @return mixed
		 * @throws InvalidSettingsKey - The specified parameter does not exist in this class
		 */
		public function get( $name ) {
			$this->exists( $name );
			return $this->{$name};
		}

		/**
		 * Make sure the parameter exists.
		 *
		 * @param string $param_name - Parameter name to check the existence of (for this class)
		 *
		 * @throws InvalidSettingsKey - Raised if the parameter is not defined for this class
		 */
		public function exists( string $param_name ): bool {
			$reflection = new ReflectionClass( self::class );
			$params     = array_keys( $reflection->getDefaultProperties() );

			if ( ! in_array( $param_name, $params, true ) ) {
				throw new InvalidSettingsKey(
					sprintf(
					// translators: %s - The parameter given by the calling function
						esc_attr__( 'Error: "%1$s" is not a valid parameter', '00-e20r-utilities' ),
						$param_name
					)
				);
			}

			return true;
		}
	}
}
