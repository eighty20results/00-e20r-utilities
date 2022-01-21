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
 * @package E20R\Metrics\MixpanelConnector
 */

namespace E20R\Metrics;

use E20R\Exceptions\InvalidSettingsKey;
use E20R\Metrics\Exceptions\HostNotDefined;
use E20R\Metrics\Exceptions\InvalidMixpanelKey;
use E20R\Metrics\Exceptions\InvalidPluginInfo;
use E20R\Metrics\Exceptions\MissingDependencies;
use E20R\Metrics\Exceptions\UniqueIDException;
use E20R\Utilities\Message;
use E20R\Utilities\Utilities;
use Mixpanel;
use Exception;
use function esc_attr__;

if ( ! defined( 'ABSPATH' ) && ( ! defined( 'PLUGIN_PATH' ) ) ) {
	die( 'Cannot access source file directly!' );
}

if ( ! class_exists( 'E20R\\Metrics\\MixpanelConnector' ) ) {

	/**
	 * Custom Mixpanel connector
	 */
	class MixpanelConnector {

		/**
		 * Instance of the Mixpanel class
		 *
		 * @var Mixpanel|null $instance
		 */
		private $instance = null;

		/**
		 * An instance of the Utilities class
		 *
		 * @var Utilities|null $utils
		 */
		private $utils = null;

		/**
		 * Identifying string for host
		 *
		 * @var string|null
		 */
		private $hostid = null;

		/**
		 * The unique (hopefully) user ID we'll use for mixpanel. Cannot identify user info based on this ID
		 * It is only used to for Mixpanel metrics tracking
		 *
		 * @var string|null $user_id
		 */
		private $user_id = null;

		/**
		 * Instantiate our own edition of the Mixpanel Connector
		 *
		 * @param null|string    $token       Mixpanel token.
		 * @param string[]       $host        Mixpanel host.
		 * @param null|Mixpanel  $mp_instance Mixpanel class instance (for testing purposes).
		 * @param null|Utilities $utils       E20R Utilities Module class instance (for testing purposes)
		 *
		 * @throws HostNotDefined - No user logged in when instantiating the class.
		 * @throws InvalidMixpanelKey - The Mixpanel key supplied is invalid.
		 */
		public function __construct( $token = null, $host = array( 'host' => 'api-eu.mixpanel.com' ), $mp_instance = null, $utils = null ) {
			if ( is_null( $token ) ) {
				throw new InvalidMixpanelKey(
					esc_attr__(
						'No API key found for the Mixpanel connector',
						'00-e20r-utilities'
					)
				);
			}

			if ( empty( $this->utils ) && empty( $utils ) ) {
				$message = new Message();
				$utils   = new Utilities( $message );
			}

			$this->utils   = $utils;
			$this->user_id = $this->get_user_id();
			$this->utils->log( "Loading MixpanelConnector class for user ID {$this->user_id}" );

			if ( empty( $host ) ) {
				$host = array( 'host' => 'api-eu.mixpanel.com' );
			}

			if ( empty( $mp_instance ) ) {
				$this->instance = Mixpanel::getInstance( $token, $host );
				$this->utils->log( 'Added the Mixpanel() class instance!' );
			} else {
				$this->instance = $mp_instance;
			}

			$hostid = gethostname();

			if ( empty( $hostid ) ) {
				throw new HostNotDefined(
					esc_attr__( 'Unable to locate host ID!', '00-e20r-utilities' )
				);
			}

			$this->hostid = sprintf( '%1$s -> %2$s', $hostid, $this->user_id );
			$this->utils->log( "Host ID for Mixpanel will be: {$this->hostid}" );
			$this->instance->people->set(
				$this->hostid,
				array(
					'user_id' => $this->user_id,
					'host_id' => $this->hostid,
				),
				null,
				true
			);
		}

		/**
		 * Return a User ID (string) to use for Mixpanel data
		 *
		 * @return string|null
		 */
		private function get_user_id() {

			if ( empty( $this->user_id ) ) {
				$this->user_id = get_option( 'e20r_mp_userid', null );
			}

			if ( null === $this->user_id ) {
				try {
					$this->user_id = $this->uniq_real_id( 'e20rutl' );
				} catch ( UniqueIDException $e ) {
					// translators: %1$s the error message from the UniqueIDException()
					$message = sprintf( esc_attr__( 'Error: %1$s', '00-e20r-utilities' ), $e->getMessage() );
					$this->utils->log( $message );
					$this->utils->add_message( $message, 'error', 'backend' );
					return null;
				}
				update_option( 'e20r_mp_userid', $this->user_id );
			}

			return $this->user_id;
		}

		/**
		 * Installed and activated plugin
		 *
		 * @param string|null $plugin_slug The slug of the plugin
		 *
		 * @throws MissingDependencies Thrown when the Mixpanel Composer module is missing
		 * @throws InvalidPluginInfo Thrown if the user didn't supply a plugin slug to register
		 */
		public function increment_activations( $plugin_slug = null ) {

			if ( empty( $plugin_slug ) ) {
				throw new InvalidPluginInfo( 'Error: No plugin slug supplied!' );
			}

			if ( ! class_exists( Mixpanel::class ) ) {
				$msg = sprintf(
					// translators: %1$s - Class in the composer module we're raising the dependency exception for
					esc_attr__(
						'Error: E20R Utilities Module is missing a required composer module (%1$s). Please report this error at https://github.com/eighty20results/Utilities/issues',
						'00-e20r-utilities'
					),
					Mixpanel::class
				);

				$this->utils->add_message( $msg, 'error', 'backend' );

				throw new MissingDependencies( $msg );
			}
			$this->utils->log( "Incrementing the {$plugin_slug} activation metric" );
			$this->instance->people->increment( $this->hostid, "{$plugin_slug}_activated", 1 );
		}

		/**
		 * Generates a random unique ID (default 13 characters long, but can be adjusted)
		 *
		 * @param null|string $prefix Optionally use a prefix which can't be bigger than 50% of the length of the ID
		 * @param int         $length Length of the randomized Unique ID to generate (default is 13 characters)
		 *
		 * @return false|string
		 *
		 * @throws UniqueIDException Thrown if there's a problem with generating a securely unique ID
		 * @access private
		 * @credit https://www.php.net/manual/en/function.uniqid.php#120123
		 */
		private function uniq_real_id( $prefix = null, $length = 13 ) {

			if ( ! empty( $prefix ) ) {
				$str_length = strlen( $prefix );
				$new_length = $length - $str_length;

				if ( $new_length <= 0 || ( $str_length > ( $length - 2 ) ) ) {
					throw new UniqueIDException(
						esc_attr__(
							'Specified prefix is longer than the allowed half length of the ID',
							'00-e20r-utilities'
						)
					);
				}

				$length = $new_length;
			}

			if ( function_exists( 'random_bytes' ) ) {
				try {
					$bytes = random_bytes( ceil( $length / 2 ) );
				} catch ( Exception $e ) {
					throw new UniqueIDException(
						sprintf(
							// translators: %1$s the message from the random_bytes() exception thrown
							esc_attr__(
								'Error: %1$s',
								'00-e20r-utilities'
							),
							$e->getMessage()
						)
					);
				}
			} elseif ( function_exists( 'openssl_random_pseudo_bytes' ) ) {
				$bytes = openssl_random_pseudo_bytes( ceil( $length / 2 ) );
			} else {
				throw new UniqueIDException(
					esc_attr__(
						'Error: No cryptographically secure random function available',
						'00-e20r-utilities'
					)
				);
			}

			return $prefix . substr( bin2hex( $bytes ), 0, $length );
		}

		/**
		 * Deactivated plugin
		 *
		 * @param string|null $plugin_slug The name of the plugin we're deactivating in Mixpanel
		 *
		 * @throws MissingDependencies Thrown when the Mixpanel Composer module is missing
		 * @throws InvalidPluginInfo Thrown if the user didn't supply a plugin slug to register
		 */
		public function decrement_activations( $plugin_slug = null ) {

			if ( empty( $plugin_slug ) ) {
				throw new InvalidPluginInfo( 'Error: No plugin slug supplied!' );
			}

			if ( ! class_exists( Mixpanel::class ) ) {
				$msg = sprintf(
				// translators: %1$s - Class in the composer module we're raising the dependency exception for
					esc_attr__(
						'Error: E20R Utilities Module is missing a required composer module (%1$s). Please report this error at https://github.com/eighty20results/Utilities/issues',
						'00-e20r-utilities'
					),
					Mixpanel::class
				);

				$this->utils->add_message( $msg, 'error', 'backend' );

				throw new MissingDependencies( $msg );
			}
			$this->utils->log( "Decrementing the {$plugin_slug} activation metric for {$this->hostid}" );
			$this->instance->people->increment( $this->hostid, "{$plugin_slug}_deactivated", 1 );
		}

		/**
		 * Return the property value
		 *
		 * @param string $parameter - the class parameter to return the value of.
		 *
		 * @return Mixpanel|null
		 * @throws InvalidSettingsKey - Incorrect/unexpected variable to get for this class.
		 */
		public function get( $parameter = 'instance' ) {
			if ( ! property_exists( $this, $parameter ) ) {
				throw new InvalidSettingsKey(
					sprintf(
						// translators: %1$s The parameter name
						esc_attr__(
							'Invalid parameter (%1$s) for the MixpanelConnector() class',
							'00-e20r-utilities'
						),
						$parameter
					)
				);
			}

			return $this->{$parameter};
		}
	}
}
