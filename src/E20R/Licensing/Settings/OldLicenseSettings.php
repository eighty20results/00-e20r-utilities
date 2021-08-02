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
	private $product = '';

	/**
	 * The license key
	 *
	 * @var null|string $key
	 */
	private $key = null;

	/**
	 * Timestamp (datetime) when the license was updated/renewed
	 *
	 * @var null|\DateTime
	 */
	private $renewed = null;

	/**
	 * License server domain name (FQDN)
	 *
	 * @var string $domain
	 */
	private $domain = '';


	/**
	 * @var null|string Date and time of expiration for the license
	 */
	private $expires = null;

	/**
	 * Current license status
	 *
	 * @var string $status
	 */
	private $status = '';

	/**
	 * The name of the license owner (first name)
	 *
	 * @var string $first_name
	 */
	private $first_name = '';

	/**
	 * The surname of the license owner
	 *
	 * @var string $last_name
	 */
	private $last_name = '';

	/**
	 * The email address of the license owner
	 *
	 * @var string $email
	 */
	private $email = '';

	/**
	 * The timestamp for the last update to the license (on the server?)
	 *
	 * @var int|string $timestamp
	 */
	private $timestamp = 0;


	/**
	 * oldLicenseSettings constructor.
	 *
	 * @param string|null $product_sku
	 */
	public function __construct( $product_sku = null ) {

		parent::__construct( $product_sku );

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

		$this->domain    = $_SERVER['HTTP_HOST'];
		$this->timestamp = time();

		try {
			$this->load_settings();
		} catch ( \Exception $e ) {
			Utilities::get_instance()->log( "Error: " . $e->getMessage() ); // phpcs:ignore
		}
	}
}
