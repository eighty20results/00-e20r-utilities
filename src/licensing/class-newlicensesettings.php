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

namespace E20R\Utilities\Licensing;

/**
 * Class NewLicenseSettings
 * @package E20R\Utilities\Licensing
 */
class NewLicenseSettings extends LicenseSettings {

	/**
	 * The timestamp for when the license expires
	 *
	 * @var int $expire
	 */
	private $expire = 0; // Timestamp

	/**
	 * The License activation ID string
	 *
	 * @var null|string $activation_id
	 */
	private $activation_id = null;

	/**
	 * Date when the license expires
	 *
	 * @var null|string
	 */
	private $expire_date = null;

	/**
	 * Timezone for the expire date information
	 *
	 * @var string $timezone
	 */
	private $timezone = 'UTC';

	/**
	 * The license key
	 *
	 * @var string $the_key
	 */
	private $the_key = '';

	/**
	 * URL to the license server
	 *
	 * @var string $url
	 */
	private $url = '';

	/**
	 * Whether this license is active or has expired
	 *
	 * @var bool $has_expired
	 */
	private $has_expired = true;

	/**
	 * The status of the license (on the server)
	 *
	 * @var string $status
	 */
	private $status = 'cancelled';

	/**
	 * Do we require connectivity to the license server
	 *
	 * @var bool $allow_offline
	 */
	private $allow_offline = false;

	/**
	 * If we allow being disconnected, what's the interval type before we require access
	 *
	 * @var string $offline_interval
	 */
	private $offline_interval = 'days';

	/**
	 * The number of $offline_interval periods we before we need connectivity to keep
	 * the license active
	 *
	 * @var int $offline_value
	 */
	private $offline_value = 0;

	/**
	 * newLicenseSettings constructor.
	 *
	 * @param null|string $product_sku
	 */
	public function __construct( $product_sku = null ) {
		parent::__construct( $product_sku );

		$this->product_sku = $product_sku;
		$this->load_settings();
	}
}
