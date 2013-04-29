<?php

namespace KrtekBase;

/**
 * Base exception for various Cache related exceptions.
 */
class Cache_Exception extends \Fuel\Core\FuelException { }

class Cache_NotFound_Exception extends Cache_Exception { }
class Cache_WrongType_Exception extends Cache_Exception { }
class Cache_NoMapping_Exception extends Cache_Exception { }

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
class Krtek_Cache {
	protected static $cache = array();
	protected static $mapping = array();

	/**
	 * Save the model object for this uuid
	 *
	 * @param $uuid string
	 * @param $value Model_Base
	 * @param $column string
	 * @return Model_Base
	 */
	public static function save($uuid, $value, $column = null) {
		if(! is_null($column) && $column != $value->primary_column()) {
			if(! isset(static::$mapping[$column]))
				static::$mapping[$column] = array();

			static::$mapping[$column][$uuid] = $value->pk();
			$uuid = $value->pk();
		}
		static::$cache[$uuid] = $value;
		return static::$cache[$uuid];
	}

	/**
	 * Do we have something for this uuid ?
	 *
	 * @param $uuid
	 * @param $column string
	 * @return bool
	 */
	public static function has($uuid, $column = null) {

		if(is_null($column) || $column == 'id') {
			return isset(static::$cache[$uuid]) && ! empty(static::$cache[$uuid]);
		} else if(isset(static::$mapping[$column][$uuid]))
			return static::has(static::$mapping[$column][$uuid]);
		return false;
	}

	/**
	 * Return the model object for this uuid.
	 *
	 * @param $uuid string
	 * @param $class string
	 * @param $column string
	 * @throws Cache_NotFound_Exception
	 * @throws Cache_NoMapping_Exception
	 * @throws Cache_WrongType_Exception
	 * @return Model_Base
	 */
	public static function get($uuid, $class = null, $column = null) {
		if(is_null($column) || $column == 'id') {
			if(static::has($uuid)) {
				$instance = static::$cache[$uuid];
				if(! is_null($class) && ! is_a($instance, $class))
					throw new Cache_WrongType_Exception("Found object with the wrong instance type : ".$uuid);
				return $instance;
			}
			throw new Cache_NotFound_Exception("Nothing for this uuid : ".$uuid);
		} else if(isset(static::$mapping[$column][$uuid]))
			return static::get(static::$mapping[$column][$uuid], $class);
		throw new Cache_NoMapping_Exception("No mapping found for : $column and uuid $uuid.");
	}

	/**
	 * Clear the given uuid from the cache
	 *
	 * @param $uuid
	 * @param $column string
	 */
	public static function clear($uuid, $column = null) {
		if(is_null($column) || $column == 'id') {
			if(static::has($uuid))
				unset(static::$cache[$uuid]);
		} else if(isset(static::$mapping[$column][$uuid]))
			static::clear(static::$mapping[$column][$uuid]);
	}

	/**
	 * Clear the whole cache
	 */
	public static function clear_all() {
		static::$cache = array();
		static::$mapping = array();
	}

	/**
	 * Preload one or more models into the cache
	 *
	 * @param $model Model_Base|array
	 * @param $column
	 */
	public static function preload($model, $column = null) {
		if(is_array($model)) {
			foreach($model as $c => $m)
				static::preload($m, $c);
			return;
		}

		if(is_string($column)) {
			$data = $model::find_by($column, '%', 'LIKE');
			if(! empty($data))
				foreach($data as $d)
					static::$mapping[$column][$d->$column] = $d->id;
		} else
			$model::find_all();
	}

	protected static $results_cache = array();

	/**
	 * Cache a complete result for a query on a table for a particular column.
	 * SHOULD NOT BE USED OUTSIDE OF MODEL_BASE
	 *
	 * @param $table string The table
	 * @param $column string The column
	 * @param $data Model_Base[] Results of the whole query with the format id => result()
	 */
	public static function results_cache_save($table, $column, $data) {
		if(! isset(static::$results_cache[$table]))
			static::$results_cache[$table] = array();

		if(! isset(static::$results_cache[$table][$column]))
			static::$results_cache[$table][$column] = array();

		foreach($data as $id => $result)
			static::$results_cache[$table][$column][$id] = $result;
	}

	/**
	 * Get the cached results for a query on a table for a particular column
	 * SHOULD NOT BE USED OUTSIDE OF MODEL_BASE
	 *
	 * @param $table string The table
	 * @param $column string The column
	 * @param $id int The id we want the result for
	 * @return mixed the result if cached, null if we have data for $table / $column but not for this id, false if nothing found.
	 */
	public static function results_cache_get($table, $column, $id) {
		if(isset(static::$results_cache[$table][$column][$id]))
			return static::$results_cache[$table][$column][$id];
		if(isset(static::$results_cache[$table][$column]))
			return null;
		return false;
	}
}