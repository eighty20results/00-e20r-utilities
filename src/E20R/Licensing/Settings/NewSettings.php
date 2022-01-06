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
 * @package E20R\Licensing\Settings
 */

namespace E20R\Licensing\Settings;

use E20R\Licensing\Exceptions\InvalidSettingsVersion;

/**
 * Class NewSettings
 *
 * @package E20R\Licensing\Settings
 */
class NewSettings extends BaseSettings {

	/**
	 * The timestamp for when the license expires
	 *
	 * @var int $expire
	 */
	protected $expire = -1; // Timestamp

	/**
	 * The License activation ID string
	 *
	 * @var null|string $activation_id
	 */
	protected $activation_id = null;

	/**
	 * Date when the license expires
	 *
	 * @var null|string
	 */
	protected $expire_date = null;

	/**
	 * Timezone for the expire date information
	 *
	 * @var string $timezone
	 */
	protected $timezone = 'UTC';

	/**
	 * The license key
	 *
	 * @var string $the_key
	 */
	protected $the_key = '';

	/**
	 * URL to the license server
	 *
	 * @var string $url
	 */
	protected $url = '';

	/**
	 * Whether this license is active or has expired
	 *
	 * @var bool $has_expired
	 */
	protected $has_expired = true;

	/**
	 * The status of the license (on the server)
	 *
	 * @var string $status
	 */
	protected $status = 'cancelled';

	/**
	 * Do we require connectivity to the license server
	 *
	 * @var bool $allow_offline
	 */
	protected $allow_offline = false;

	/**
	 * If we allow being disconnected, what's the interval type before we require access
	 *
	 * @var string $offline_interval
	 */
	protected $offline_interval = 'days';

	/**
	 * The number of $offline_interval periods we allow before we need connectivity to keep
	 * the license active
	 *
	 * @var int $offline_value
	 */
	protected $offline_value = 0;

	/**
	 * The properties to exclude in a REST API request for this class
	 *
	 * @var string[]
	 */
	protected $excluded = array( 'excluded', 'all_settings', 'defaults', 'product_sku' );

	/**
	 * NewSettings constructor.
	 *
	 * @param null|string $product_sku - The WooCommerce store SKU for the License product in question
	 * @param array|null  $settings - Settings to apply for this class
	 *
	 * @throws InvalidSettingsVersion - Raised when we get a property that doesn't match this version of the class
	 */
	public function __construct( ?string $product_sku = 'e20r_default_license', $settings = null ) {
		$this->product_sku = ( ! empty( $product_sku ) ? $product_sku : 'e20r_default_license' );
		if ( empty( $settings ) ) {
			$settings = $this->defaults();
		}
		$this->all_settings[ $product_sku ] = $settings;
		parent::__construct( $product_sku, $this->all_settings[ $product_sku ] );

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
			// Save the supplied setting(s) for this class
			$this->{$key} = $value;
		}
	}

	/**
	 * Return all properties from the class with its default values
	 *
	 * @return array
	 */
	public function defaults(): array {
		return array(
			'expire'           => -1,
			'activation_id'    => null,
			'expire_date'      => '',
			'timezone'         => 'UTC',
			'the_key'          => '',
			'url'              => '',
			'has_expired'      => true,
			'status'           => 'cancelled',
			'allow_offline'    => false,
			'offline_interval' => 'days',
			'offline_value'    => 0,
		);
	}
}
