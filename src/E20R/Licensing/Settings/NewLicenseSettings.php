<?php
/*
 * Copyright (c) 2016 - 2021 - Eighty / 20 Results by Wicked Strong Chicks.
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
 */

namespace E20R\Licensing\Settings;

use E20R\Utilities\Utilities;

/**
 * Class NewLicenseSettings
 * @package E20R\Licensing\Settings
 */
class NewLicenseSettings extends OldLicenseSettings {

	/**
	 * The timestamp for when the license expires
	 *
	 * @var int $expire
	 */
	protected $expire = 0; // Timestamp

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
	 * The license SKU in the shop
	 *
	 * @var null|string $product_sku
	 */
	protected $product_sku = null;

	/**
	 * newLicenseSettings constructor.
	 *
	 * @param null|string    $product_sku
	 * @param Defaults|null  $plugin_defaults
	 * @param Utilities|null $utils
	 */
	public function __construct( ?string $product_sku = 'e20r_default_license', ?Defaults $plugin_defaults = null, ?Utilities $utils = null ) {
		$this->product_sku = $product_sku;
		parent::__construct( $product_sku, $plugin_defaults, $utils );
	}
}
