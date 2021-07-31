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

namespace E20R\Utilities\Licensing\Settings;

use E20R\Utilities\Licensing\Exceptions\InvalidSettingKeyException;
use E20R\Utilities\Licensing\Exceptions\ConfigFileNotFound;
use E20R\Utilities\Utilities;
use Exception;

use WP_Filesystem_Base;

class Defaults {

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
	 * @throws ConfigFileNotFound
	 */
	public function __construct( bool $use_rest = true, Utilities $utils = null ) {

		if ( empty( $utils ) ) {
			$utils = Utilities::get_instance();
		}

		$this->utils = $utils;

		// Load the config settings from the locally installed config file
		try {
			if ( false === $this->read_config() ) {
				throw new \Exception( esc_attr__( 'Cannot read the configuration', '00-e20r-utilities' ) );
			}
		} catch ( ConfigFileNotFound $exp ) {
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
	 */
	public function read_config() {
		global $wp_filesystem;

		// Init the global if it's currently empty
		if ( empty( $wp_filesystem ) ) {
			\WP_Filesystem();
		}

		// Get the path to the
		$config_file = plugin_dir_path( __FILE__ ) . '/.info.json';

		if ( ! file_exists( $config_file ) ) {
			throw new ConfigFileNotFound(
				esc_attr__( 'Error: Could not find the plugin configuration file!', '00-e20r-utilities' )
			);
		}

		if ( empty( $wp_filesystem ) ) {
			return false;
		}

		$json_blob = $wp_filesystem->get_contents( $config_file );
		$settings  = json_decode( $json_blob, true );

		if ( null === $settings ) {
			throw new \Exception( esc_attr__( 'Unable to decode the configuration file', '00-e20r-utilities' ) );
		}

		if ( ! is_array( $settings ) ) {
			throw new \Exception( esc_attr__( 'Invalid configuration file format', '00-e20r-utilities' ) );
		}

		$settings = array_map( 'trim', $settings );
		foreach ( $settings as $key => $value ) {
			try {
				$this->set( $key, $value );
			} catch ( \Exception $e ) {
				$this->utils->log( 'Error: ' . esc_attr( $e->getMessage() ) );
				return false;
			}
		}

		return true;
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
	 * @param mixed $value
	 *
	 * @return bool
	 * @throws InvalidSettingKeyException
	 * @throws Exception
	 */
	public function set( $name, $value ) {

		if ( 'server_url' !== $name && ! defined( 'PLUGIN_PHPUNIT' ) || ( defined( 'PLUGIN_PHPUNIT' ) && ! PLUGIN_PHPUNIT ) ) {
			throw new Exception( esc_attr__( 'Error: Cannot change the default plugin settings', '00-e20r-utilities' ) );
		}

		try {
			$this->param_exists( $name );
		} catch ( InvalidSettingKeyException $e ) {
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
	 * @param string $name
	 *
	 * @return string|bool|null
	 */
	protected function get_default( string $name ) {
		$default = array(
			'version'        => '3.2',
			'server_url'     => 'https://eighty20results.com',
			'rest_url'       => '/wp-json/woo-license-server/v1',
			'ajax_url'       => '/wp-admin/wp-ajax.php',
			'use_rest'       => true,
			'debug_logging'  => false,
			'connection_uri' => null,
			'store_code'     => null,
		);

		return $default[ $name ];
	}

	/**
	 * Return the value for the specified parameter (if it exists)
	 *
	 * @param string $name
	 *
	 * @return mixed
	 * @throws InvalidSettingKeyException
	 */
	public function get( $name ) {
		try {
			$this->param_exists( $name );
		} catch ( InvalidSettingKeyException $e ) {
			throw $e;
		}

		return $this->{$name};
	}

	/**
	 * Make sure the parameter exists.
	 *
	 * @param string $name
	 *
	 * @throws InvalidSettingKeyException
	 */
	protected function param_exists( $name ) {
		$reflection = new \ReflectionClass( self::class );
		$params     = array_keys( $reflection->getDefaultProperties() );

		if ( ! in_array( $name, $params, true ) ) {
			throw new InvalidSettingKeyException(
				sprintf(
					// translators: %s - The parameter given by the calling function
					esc_attr__( 'Error: %s is not a valid parameter', '00-e20r-utilities' ),
					$name
				)
			);
		}

		return true;
	}
}
