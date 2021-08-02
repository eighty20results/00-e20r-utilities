<?php
/*
 * *
 *   * Copyright (c) 2021. - Eighty / 20 Results by Wicked Strong Chicks.
 *   * ALL RIGHTS RESERVED
 *   *
 *   * This program is free software: you can redistribute it and/or modify
 *   * it under the terms of the GNU General Public License as published by
 *   * the Free Software Foundation, either version 3 of the License, or
 *   * (at your option) any later version.
 *   *
 *   * This program is distributed in the hope that it will be useful,
 *   * but WITHOUT ANY WARRANTY; without even the implied warranty of
 *   * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *   * GNU General Public License for more details.
 *   *
 *   * You should have received a copy of the GNU General Public License
 *   * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace E20R\Licensing\Settings;

use E20R\Licensing\Exceptions\InvalidSettingsKey;
use E20R\Licensing\Exceptions\ConfigDataNotFound;
use E20R\Utilities\Message;
use E20R\Utilities\Utilities;
use Exception;

if ( ! class_exists( '\E20R\Licensing\Settings\Defaults' ) ) {
	/**
	 * Class Defaults
	 * @package E20R\Licensing\Settings
	 */
	class Defaults {

		const E20R_LICENSE_SECRET_KEY = '5687dc27b50520.33717427';
		const E20R_STORE_CONFIG       = '{"store_code":"L4EGy6Y91a15ozt","server_url":"https://eighty20results.com"}';
		const E20R_LICENSE_SERVER     = 'eighty20results.com';

		/**
		 * The version number for this plugin (E20R Licensing module)
		 * @var string $version
		 */
		protected string $version = '3.2';

		/**
		 * Default server URL for this plugin
		 * @var string $server_url
		 */
		protected string $server_url = 'https://eighty20results.com';

		/**
		 * Rest end-point for the license server plugin
		 * @var string $rest_url
		 */
		protected string $rest_url = '/wp-json/woo-license-server/v1';

		/**
		 * AJAX end-point for the license server plugin
		 * @var string $ajax_url
		 */
		protected string $ajax_url = '/wp-admin/wp-ajax.php';

		/**
		 * Whether to use the REST or AJAX API
		 * @var bool $use_rest
		 */
		protected bool $use_rest = true;

		/**
		 * Whether to add verbose license operations debug logging to the error_log() destination
		 * @var bool $debug_logging
		 */
		protected bool $debug_logging = false;

		/**
		 * The WooCommerce store code to use
		 *
		 * @var string|null
		 */
		protected ?string $store_code = null;

		/**
		 * The connection URI for the license client
		 * @var string|null $connection_uri
		 */
		protected ?string $connection_uri = null;

		/**
		 * Default settings for the plugin
		 *
		 * @var array $default
		 */
		private array $default = array();

		/**
		 * The Utilities class instance
		 * @var Utilities|null $utils
		 */
		private ?Utilities $utils = null;

		/**
		 * Defaults constructor.
		 *
		 * @param bool           $use_rest
		 * @param Utilities|null $utils
		 *
		 * @throws ConfigDataNotFound
		 * @throws InvalidSettingsKey
		 * @throws Exception
		 */
		public function __construct( bool $use_rest = true, Utilities $utils = null ) {

			$this->default = array(
				'version'        => '3.2',
				'server_url'     => 'https://eighty20results.com',
				'rest_url'       => '/wp-json/woo-license-server/v1',
				'ajax_url'       => '/wp-admin/wp-ajax.php',
				'use_rest'       => true,
				'debug_logging'  => false,
				'connection_uri' => null,
				'store_code'     => null,
			);

			if ( empty( $utils ) ) {
				$message = new Message();
				$utils   = new Utilities( $message );
			}

			$this->utils = $utils;

			// Load the config settings from the locally installed config file
			try {
				$this->read_config();
			} catch ( ConfigDataNotFound | Exception | InvalidSettingsKey $exp ) {
				$this->utils->log( 'Error: ' . $exp->getMessage() );
				throw $exp;
			}

			$this->use_rest = $use_rest;

			if ( defined( 'E20R_LICENSING_DEBUG' ) ) {
				$this->debug_logging = E20R_LICENSING_DEBUG;
			}

			if ( defined( 'E20R_LICENSE_SERVER_URL' ) ) {
				$this->server_url = E20R_LICENSE_SERVER_URL;
			}

			$this->build_connection_uri();
		}

		/**
		 * Loads the configuration from the current directory.
		 *
		 * @param string|null $json_blob
		 *
		 * @throws ConfigDataNotFound
		 * @throws InvalidSettingsKey
		 */
		public function read_config( ?string $json_blob = null ) {

			if ( empty( $json_blob ) ) {
				$json_blob = self::E20R_STORE_CONFIG;
			}

			if ( empty( $json_blob ) ) {
				throw new ConfigDataNotFound(
					esc_attr__( 'No configuration data found', '00-e20r-utilities' )
				);
			}

			$settings = json_decode( $json_blob, true );

			if ( null === $settings ) {
				throw new ConfigDataNotFound(
					esc_attr__( 'Unable to decode the configuration data', '00-e20r-utilities' )
				);
			}

			if ( ! is_array( $settings ) ) {
				throw new ConfigDataNotFound(
					esc_attr__( 'Invalid configuration file format', '00-e20r-utilities' )
				);
			}

			$settings = array_map( 'trim', $settings );
			foreach ( $settings as $key => $value ) {
				try {
					$status = $this->set( $key, $value );
				} catch ( InvalidSettingsKey | \Exception $e ) {
					$this->utils->log( 'Error: ' . esc_attr( $e->getMessage() ) );
					throw $e;
				}
			}
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
		 * Set the specified Default variable value
		 *
		 * @param string $name
		 * @param mixed  $value
		 *
		 * @return bool
		 * @throws InvalidSettingsKey
		 * @throws Exception
		 */
		public function set( $name, $value ) {

			if ( 'server_url' !== $name && ! defined( 'PLUGIN_PHPUNIT' ) || ( defined( 'PLUGIN_PHPUNIT' ) && ! PLUGIN_PHPUNIT ) ) {
				throw new Exception( esc_attr__( 'Error: Cannot change the default plugin settings', '00-e20r-utilities' ) );
			}

			try {
				$this->exists( $name );
			} catch ( InvalidSettingsKey $e ) {
				throw $e;
			}

			// Exit if the constant has been set for DEBUG
			if ( 'debug_logging' === $name && defined( 'E20R_LICENSING_DEBUG' ) && E20R_LICENSING_DEBUG !== $value ) {
				$this->debug_logging = E20R_LICENSING_DEBUG;

				return true;
			}

			// Exit if the constant has been set for E20R_LICENSE_SERVER_URL
			if ( 'server_url' === $name && defined( 'E20R_LICENSE_SERVER_URL' ) && ! empty( E20R_LICENSE_SERVER_URL ) ) {
				$this->server_url = E20R_LICENSE_SERVER_URL;
				$this->build_connection_uri();

				return true;
			}

			// Do we need to change the value?
			try {
				$default = $this->get_default( $name );
			} catch ( InvalidSettingsKey $e ) {
				throw $e;
			}

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
		 * @param string $name
		 *
		 * @return string|bool|null
		 * @throws InvalidSettingsKey
		 */
		public function get_default( string $name ) {

			if ( ! property_exists( $this, $name ) ) {
				throw new InvalidSettingsKey(
					sprintf(
					// translators: %1$s - Supplied parameter name
						esc_attr__( '%1$s is not a valid default setting', '00-e20r-utilities' ),
						$name
					)
				);
			}

			return $this->default[ $name ];
		}

		/**
		 * Return the value for the specified parameter (if it exists)
		 *
		 * @param string $name
		 *
		 * @return mixed
		 * @throws InvalidSettingsKey
		 */
		public function get( $name ) {
			try {
				$this->exists( $name );
			} catch ( InvalidSettingsKey $e ) {
				throw $e;
			}

			return $this->{$name};
		}

		/**
		 * Make sure the parameter exists.
		 *
		 * @param string $param_name
		 *
		 * @throws InvalidSettingsKey
		 */
		protected function exists( string $param_name ): bool {
			$reflection = new \ReflectionClass( self::class );
			$params     = array_keys( $reflection->getDefaultProperties() );

			if ( ! in_array( $param_name, $params, true ) ) {
				throw new InvalidSettingsKey(
					sprintf(
					// translators: %s - The parameter given by the calling function
						esc_attr__( 'Error: %s is not a valid parameter', '00-e20r-utilities' ),
						$param_name
					)
				);
			}

			return true;
		}
	}
}
