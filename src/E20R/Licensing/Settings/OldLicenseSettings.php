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
use E20R\Licensing\Exceptions\MissingServerURL;
use E20R\Utilities\Utilities;

/**
 * Class OldLicenseSettings
 * @package E20R\Utilities\Licensing
 *
 * @deprecated
 */
class OldLicenseSettings extends LicenseSettings {

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
	 * @var null|string Date and time of expiration for the license
	 */
	protected $expires = null;

	/**
	 * Current license status
	 *
	 * @var string $status
	 */
	protected $status = '';

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
	 * @var int|string $timestamp
	 */
	protected $timestamp = 0;


	/**
	 * oldLicenseSettings constructor.
	 *
	 * @param string|null $product_sku
	 * @param Defaults|null $plugin_defaults
	 * @param Utilities|null $utils
	 *
	 * @throws InvalidSettingsKey|MissingServerURL
	 */
	public function __construct( $product_sku = 'e20r_default_license', $plugin_defaults = null, $utils = null ) {
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
		$this->expires = gmdate( 'dd-mm-yy\Th:i:s' );
		$this->status  = 'expired';

		$this->product     = $product_sku;
		$this->product_sku = $product_sku;

		// Add the product_sku member variable since we use 'product'
		$this->excluded[] = 'product_sku';

		$this->domain    = $_SERVER['HTTP_HOST'] ?? 'localhost.local';
		$this->timestamp = time();
	}
}
