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

use E20R\Licensing\Exceptions\InvalidMixpanelKey;
use E20R\Licensing\Exceptions\InvalidSettingsKey;
use E20R\Licensing\Exceptions\UserNotDefined;
use Mixpanel;
use function esc_attr__;

if ( ! class_exists( 'E20R\Metrics\MixpanelConnector' ) ) {

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
		 * @param null|string   $token       Mixpanel token.
		 * @param string[]      $host        Mixpanel host.
		 * @param null|Mixpanel $mp_instance Mixpanel class instance (for testing purposes).
		 *
		 * @throws UserNotDefined - No user logged in when instantiating the class.
		 * @throws InvalidMixpanelKey - The Mixpanel key supplied is invalid.
		 */
		public function __construct( $token = null, $host = array( 'host' => 'api-eu.mixpanel.com' ), $mp_instance = null ) {
			if ( is_null( $token ) ) {
				throw new InvalidMixpanelKey(
					esc_attr__(
						'No API key found for the Mixpanel connector',
						'00-e20r-utilities'
					)
				);
			}

			$this->user_id = get_option( 'e20r_mp_userid', uniqid( 'e20rutil', true ) );

			if ( empty( $host ) ) {
				$host = array( 'host' => 'api-eu.mixpanel.com' );
			}

			if ( empty( $mp_instance ) ) {
				$this->instance = Mixpanel::getInstance( $token, $host );
			} else {
				$this->instance = $mp_instance;
			}

			$user_info = wp_get_current_user();

			if ( empty( $user_info ) ) {
				throw new UserNotDefined(
					esc_attr__( 'Current user not yet identified', '00-e20r-utilities' )
				);
			}

			$this->hostid = sprintf( '%1$s -> %2$s', gethostname(), $this->user_id );
			$this->instance->people->set(
				$this->user_id,
				array(
					'host_id' => $this->hostid,
				),
				null,
				true
			);
		}

		/**
		 * Return a User ID (numeric or the '' string) to use for Mixpanel data
		 *
		 * @return int|string
		 */
		private function get_user_id() {
			$user_id   = 'unknown_user';
			$user_info = function_exists( 'wp_get_current_user' ) ? wp_get_current_user() : $user_id;
			if ( 'unknown_user' !== $user_info ) {
				$user_id = $user_info->ID;
			}
			return $user_id;
		}

		/**
		 * Installed and activated plugin
		 */
		public function increment_activations() {
			$this->instance->people->increment( $this->get_user_id(), 'utilities_activated', 1 );
		}

		/**
		 * Deactivated plugin
		 */
		public function decrement_activations() {
			$this->instance->people->increment( $this->get_user_id(), 'utilities_deactivated', 1 );
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
