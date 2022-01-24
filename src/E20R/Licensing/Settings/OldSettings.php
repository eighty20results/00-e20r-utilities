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
 * @package E20R\Utilities\Licensing
 */

namespace E20R\Licensing\Settings;

use E20R\Licensing\Exceptions\InvalidSettingsVersion;
use function wp_unslash;

if ( ! defined( 'ABSPATH' ) && ( ! defined( 'PLUGIN_PATH' ) ) ) {
	die( 'Cannot access source file directly!' );
}

/**
 * Class OldSettings
 *
 * @deprecated
 */
class OldSettings extends BaseSettings {

	/**
	 * The product key (SKU) for the license
	 *
	 * @var string $product
	 */
	protected $product = '';

	/**
	 * The license key
	 *
	 * @var null|string $key
	 */
	protected $key = null;

	/**
	 * Timestamp (datetime) when the license was updated/renewed
	 *
	 * @var null|\DateTime
	 */
	protected $renewed = null;

	/**
	 * License server domain name (FQDN)
	 *
	 * @var string $domain
	 */
	protected $domain = '';


	/**
	 * Date and time of expiration for the license
	 *
	 * @var null|string
	 */
	protected $expires = null;

	/**
	 * Current license status
	 *
	 * @var string $status
	 */
	protected $status = 'cancelled';

	/**
	 * The name of the license owner (first name)
	 *
	 * @var string $first_name
	 */
	protected $first_name = '';

	/**
	 * The surname of the license owner
	 *
	 * @var string $last_name
	 */
	protected $last_name = '';

	/**
	 * The email address of the license owner
	 *
	 * @var string $email
	 */
	protected $email = '';

	/**
	 * The timestamp for the last update to the license (on the server?)
	 *
	 * @var int|string|null $timestamp
	 */
	protected $timestamp = null;

	/**
	 * The properties to exclude (not included in the REST API request)
	 *
	 * @var array
	 */
	protected $excluded = array( 'excluded', 'all_settings', 'defaults', 'product_sku' );

	/**
	 * OldSettings constructor.
	 *
	 * @param string|null $product_sku - The WooCommerce store SKU for the license product being processed
	 * @param null|array  $settings - Default settings to use for this class
	 *
	 * @throws InvalidSettingsVersion - Raised when we get a property that doesn't match this version of the class
	 */
	public function __construct( ?string $product_sku = 'e20r_default_license', $settings = null ) {
		$this->product_sku = ( ! empty( $product_sku ) ? $product_sku : 'e20r_default_license' );
		if ( empty( $settings ) ) {
			$settings = $this->defaults();
		}
		$this->all_settings[ $product_sku ] = $settings;

		// Loading settings from the supplied array
		foreach ( $this->all_settings[ $product_sku ] as $key => $value ) {
			if ( ! property_exists( $this, $key ) ) {
				throw new InvalidSettingsVersion(
					esc_attr__(
						'The supplied settings are not the correct settings for the current license management version',
						'00-e20r-utilities'
					)
				);
			}
			$this->{$key} = $value;
		}
		parent::__construct( $product_sku, $this->all_settings[ $product_sku ] );
		global $current_user;

		if ( ! empty( $current_user ) ) {
			$this->first_name = ! empty( $current_user->user_firstname ) ?
				$current_user->user_firstname :
				$current_user->first_name;
			$this->last_name  = ! empty( $current_user->user_lastname ) ?
				$current_user->user_lastname :
				$current_user->last_name;
			$this->email      = $current_user->user_email;
		}
		$this->product = $this->product_sku;
	}

	/**
	 * Return all properties from the class with its default values
	 *
	 * @return array
	 */
	public function defaults(): array {
		$domain = 'localhost.local';
		if ( isset( $_SERVER['HTTP_HOST'] ) ) {
			$domain = filter_var( wp_unslash( $_SERVER['HTTP_HOST'] ), FILTER_SANITIZE_URL );
		}

		return array(
			'product'    => '',
			'key'        => null,
			'renewed'    => null,
			'domain'     => $domain,
			'expires'    => null,
			'status'     => 'cancelled',
			'first_name' => '',
			'last_name'  => '',
			'email'      => '',
			'timestamp'  => null,
		);
	}
}
