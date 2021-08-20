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

use E20R\Licensing\Exceptions\InvalidSettingsKey;
use E20R\Licensing\Exceptions\InvalidSettingsVersion;
use E20R\Utilities\Utilities;

/**
 * Class NewSettings
 * @package E20R\Licensing\Settings
 */
abstract class BaseSettings {

	/**
	 * Settings & values
	 *
	 * @var array
	 */
	protected $all_settings = array();

	/**
	 * The license SKU in the shop
	 *
	 * @var null|string $product_sku
	 */
	protected $product_sku = null;

	/**
	 * The default values for the settings class
	 *
	 * @var array $defaults
	 */
	protected $defaults = array();

	/**
	 * BaseSettings constructor.
	 *
	 * @param null|string $product_sku
	 * @param array|null  $settings
	 *
	 * @throws InvalidSettingsVersion
	 */
	public function __construct( ?string $product_sku = 'e20r_default_license', $settings = null ) {
		$this->product_sku = $product_sku;
	}

	/**
	 * Getter for the BaseSettings() class
	 *
	 * @param string $key
	 *
	 * @return mixed
	 * @throws InvalidSettingsKey
	 */
	public function get( $key ) {
		if ( ! isset( $this->{$key} ) ) {
			throw new InvalidSettingsKey(
				esc_attr__(
					'Not a valid setting for this version of the licensing solution',
					'00-e20r-utilities'
				)
			);
		}
		return $this->{$key};
	}

	/**
	 * Getter for the BaseSettings() class
	 *
	 * @param string $key
	 * @param mixed $value
	 *
	 * @throws InvalidSettingsKey
	 */
	public function set( $key, $value ) {
		if ( ! property_exists( $this, $key ) ) {
			throw new InvalidSettingsKey(
				sprintf(
					// translators: %1$s - Class property
					esc_attr__(
						'"%1$s" Not a valid setting for this version of the licensing solution',
						'00-e20r-utilities'
					),
					$key
				)
			);
		}
		$this->{$key} = $value;
	}

	/**
	 * Return the class properties
	 *
	 * @param BaseSettings|NewSettings|OldSettings $class_name
	 *
	 * @return array
	 * @throws \ReflectionException
	 */
	public function get_properties( $class_name = null ): array {
		$properties = array();
		if ( empty( $class_name ) ) {
			$class_name = $this;
		}
		$reflection = new \ReflectionClass( $class_name );
		$props      = $reflection->getProperties(
			\ReflectionProperty::IS_PROTECTED |
			\ReflectionProperty::IS_PRIVATE
		);

		foreach ( $props as $prop ) {
			$key = $prop->getName();
			if ( ! in_array( $key, $this->excluded, true ) ) {
				$properties[] = $key;
			}
		}

		return $properties;
	}

	/**
	 * Return all properties from the class with its default values
	 *
	 * @param NewSettings|OldSettings|BaseSettings
	 *
	 * @return array
	 */
	abstract public function defaults();
}
