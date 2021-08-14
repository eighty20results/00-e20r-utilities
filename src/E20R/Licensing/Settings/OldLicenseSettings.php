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

use E20R\Utilities\Message;
use E20R\Utilities\Utilities;

/**
 * Class OldLicenseSettings
 * @package E20R\Utilities\Licensing
 *
 * @deprecated
 */
class OldLicenseSettings {

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
	 * Utilities class
	 *
	 * @var Utilities $utils
	 */
	protected $utils;

	/**
	 * The default settings for the plugin
	 *
	 * @var Defaults|null $plugin_defaults
	 */
	protected $plugin_defaults = null;

	/**
	 * OldLicenseSettings constructor.
	 *
	 * @param string|null    $product_sku
	 * @param Defaults|null  $plugin_defaults
	 * @param Utilities|null $utils
	 */
	public function __construct( ?string $product_sku = 'e20r_default_license', ?Defaults $plugin_defaults = null, ?Utilities $utils = null ) {

		if ( empty( $plugin_defaults ) ) {
			$plugin_defaults = new Defaults();
		}

		if ( empty( $utils ) ) {
			$message = new Message();
			$utils   = new Utilities( $message );
		}

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
		$this->utils           = $utils;
		$this->plugin_defaults = $plugin_defaults;
		$this->expires         = gmdate( 'D-M-Y\Th:i:s' );
		$this->status          = 'expired';
		$this->product         = $product_sku;
		$this->domain          = $_SERVER['HTTP_HOST'] ?? 'localhost.local';
		$this->timestamp       = time();
	}
}
