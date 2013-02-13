<?php

namespace KrtekBase;

/**
 * Base exception for various Cache related exceptions.
 */
class Cache_Exception extends \Fuel\Core\FuelException { }

/**
 * Class to help cache results from a database query.
 *
 * The prerequisite is that all stored objects have an unique id.
 *
 * @package krtek-Base
 * @category BaseInterfaces
 * @author Gilles Meier <krtek4@gmail.com>
 * @version 1.0
 * @license Affero GPLv3 http://www.gnu.org/licenses/agpl-3.0-standalone.html
 * @copyright 2013 Gilles Meier <krtek4@gmail.com>
 * @link https://github.com/krtek4/fuel-krtek-base
 */
class Cache {
	protected static $cache = array();

	/**
	 * Save the model object for this uuid
	 *
	 * @param $uuid string
	 * @param $value Model_Base
	 * @return Model_Base
	 */
	public static function save($uuid, $value) {
		static::$cache[$uuid] = $value;
	}

	/**
	 * Do we have something for this uuid ?
	 *
	 * @param $uuid
	 * @return bool
	 */
	public static function has($uuid) {
		return isset(static::$cache[$uuid]) && ! empty(static::$cache[$uuid]);
	}

	/**
	 * Return the model object for this uuid.
	 *
	 * @param $uuid string
	 * @throws Cache_Exception
	 * @return Model_Base
	 */
	public static function get($uuid) {
		if(static::has($uuid))
			return static::$cache[$uuid];
		throw new Cache_Exception("Nothing for this uuid : ".$uuid);
	}

	/**
	 * Clear the given uuid from the cache
	 *
	 * @param $uuid
	 */
	public static function clear($uuid) {
		if(static::has($uuid))
			unset(static::$cache[$uuid]);
	}

	/**
	 * Clear the whole cache
	 */
	public static function clear_all() {
		static::$cache = array();
	}

	/**
	 * Preload one or more models into the cache
	 *
	 * @param $model Model_Base|array
	 */
	public static function preload($model) {
		if(is_array($model)) {
			foreach($model as $m)
				static::preload($m);
			return;
		}
		$model::find_all();
	}
}