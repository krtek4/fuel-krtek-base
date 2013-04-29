<?php

namespace KrtekBase;

use Fuel\Core\DB;
use Fuel\Core\Fieldset;
use Fuel\Core\FuelException;
use Fuel\Core\HttpNotFoundException;
use Fuel\Core\Database_Connection;
use Fuel\Core\Database_Result;
use Fuel\Core\Inflector;
use Fuel\Core\Model_Crud;
use KrtekBase\Fieldset\Fieldset_Generator;
use Log\Log;


/**
 * Base exception for various Model related exceptions.
 */
class Model_Exception extends FuelException { }

/**
 * Base class for each model.
 *
 * Add a new method for counting element based on a column value criteria.
 * Add a post insertion hook to retrieve the uuid short id of the instance.
 * Add various method to ease Fieldset creation.
 * Add a simple string representation of the model instance.
 *
 * @package krtek-Base
 * @category BaseClasses
 * @author Gilles Meier <krtek4@gmail.com>
 * @version 1.0
 * @license Affero GPLv3 http://www.gnu.org/licenses/agpl-3.0-standalone.html
 * @copyright 2012 Gilles Meier <krtek4@gmail.com>
 * @link https://github.com/krtek4/fuel-krtek-base
 */
abstract class Model_Base extends Model_Crud {
	/**
	 * @var string the name of the table representing this object
	 */
	protected static $_table_name = null;

	/**
	 * @var array rules for the fields
	 */
	protected static $_rules = array();

	/**
	 * @var array attributes for the fields
	 */
	protected static $_attributes = array();

	/**
	 * @var array labels for the fields
	 */
	protected static $_labels = array();

	/**
	 * @var array default values for the fields
	 */
	protected static $_defaults = array();

	/**
	 * @var array List of fieldset definition for this model
	 */
	protected static $_fieldsets = array();

	/**
	 * @var array Models this class references with the form 'Model_Name' => 'FK column'
	 */
	protected static $_reference_one = array();

	/**
	 * @var array Models this class is linked to by an association table 'Model_Name' => 'association table'
	 */
	protected static $_reference_many = array();

	/**
	 * @var array Models referencing this class with the form 'Model_Name' => 'FK column on the other table'
	 */
	protected static $_referenced_by = array();

	/**
	 * @var array configuration for file uploading, merge with the default config
	 */
	protected static $_upload_config = array();

	/**
	 * Count all row in the table where the column has the provided value.
	 *
	 * @param string $column
	 * @param mixed $value to look for
	 * @return int the count.
	 */
	public static function count_by($column, $value) {
		return static::count($column, false, array($column => $value));
	}

	/**
	 * Get a particular row if an $id is set, or all rows if it's null.
	 *
	 * If the given id returns no row, a 404 error is raised. If all rows
	 * were asked and their's none, an empty array will be returned.
	 *
	 * @param int $id id for one row, null for all
	 * @throws HttpNotFoundException
	 * @return Model_Base|array Instance of a model object, or array of instances.
	 */
	public static function e_find($id = null) {
		if(is_null($id)) {
			$object = static::find_all();
			if(is_null($object))
				$object = array();
		} else {
			$object = static::find_by_pk($id);
			if(is_null($object))
				throw new HttpNotFoundException();
		}

		return $object;
	}

	/**
	 * Specify the table for the column to avoid problems when joining. If a table
	 * is already specified, nothing's done.
	 *
	 * @param   mixed  $column  The column to search
	 * @param   mixed  $value   The value to find
	 * @param   string $operator The operator to use for the comparison
	 * @param   bool $refresh bypass the cache ?
	 * @return  null|Model_Base  Either null or a new Model object
	 */
	public static function find_one_by($column, $value = null, $operator = '=', $refresh = false) {
		if(Krtek_Cache::has($value, $column) && ! $refresh)
			return Krtek_Cache::get($value, get_called_class(), $column);

		if(strpos($column, '.') === false)
			$column = static::$_table_name.'.'.$column;

		return parent::find_one_by($column, $value, $operator);
	}

	/**
	 * Retrieve a list of the current model instances which are related to the foreign
	 * model through an association table and the given column.
	 *
	 * @param string $column foreign column in the association table
	 * @param int $id foreign id
	 * @return array Model instances
	 */
	public static function find_many_by($column, $id) {
		$info = static::$_reference_many['Model_'.ucfirst(substr($column, 0, - 3))];
		$data = Krtek_Cache::results_cache_get($info['table'], $info['fk'], $id);
		if(is_null($data)) {
			$data = array();
		} else if($data === false) {
			$sql = 'SELECT * FROM '.$info['table'];
			$result = DB::query($sql)->execute()->as_array();

			$data = array();
			foreach($result as $r) {
				if(! isset($data[$r[$info['fk']]]))
					$data[$r[$info['fk']]] = array();
				$data[$r[$info['fk']]][] = $r[$info['lk']];
			}
			Krtek_Cache::results_cache_save($info['table'], $info['fk'], $data);
			$data = isset($data[$id]) ? $data[$id] : array();
		}

		$instances = array();
		foreach($data as $id)
			$instances[] = static::find_by_pk($id);
		return $instances;
	}

	/**
	 * Add magic methods to count by columns
	 *
	 * @param   string  $name  The method name
	 * @param   string  $args  The method args
	 * @return  mixed   Based on static::$return_type
	 * @throws  \BadMethodCallException
	 */
	public static function __callStatic($name, $args)
	{
		if (strncmp($name, 'count_by_', 9) === 0)
			return static::count_by(substr($name, 9), reset($args));
		if (strncmp($name, 'find_many_by_', 13) === 0)
			return static::find_many_by(substr($name, 13), reset($args));
		return parent::__callStatic($name, $args);
	}

	/**
	 * @param $definition
	 * @param array $config
	 * @return Fieldset_Generator
	 */
	protected static function fieldset_generator($definition, array $config = array()) {
		return Fieldset_Generator::forge($definition, get_called_class(), $config);
	}

	/**
	 * Create a Fieldset based on the definition corresponding to
	 * the given name. If the fieldset was already created, return
	 * this particular instance.
	 *
	 * Add some custom hidden fields to the fieldset so we are latter
	 * able to process data automatically through the other method
	 * proposed by this class.
	 *
	 * @param string $definition Definition name
	 * @param array $config The config for this fieldset (only used upon creation)
	 * @return Fieldset the generated fieldset
	 */
	public static function fieldset($definition, array $config = array()) {
		$generator = static::fieldset_generator($definition, $config);
		$generator->parse();
		return $generator->fieldset();
	}

	/**
	 * Get a meta information from this model class
	 *
	 * @param $name string
	 * @throws Model_Exception
	 * @return mixed
	 */
	public static function get_meta($name) {
		if(! isset(static::$$name))
			throw new Model_Exception("Trying to access a non-existing meta information : $name.");
		return static::$$name;
	}


	/**
	 * Return the rules for the given field name.
	 *
	 * @param string $name the field name
	 * @param string $definition_name the fieldset definition name
	 * @return string|bool rules for the field, false of there's no rules
	 */
	public static function _rules($name, $definition_name) {
		if(isset(static::$_rules[$name]))
			return static::$_rules[$name];
		return false;
	}

	/**
	 * Return the label for the given field name.
	 *
	 * @param string $name the field name
	 * @param string $definition_name the fieldset definition name
	 * @return string|bool label for the field, false if there's no label
	 */
	public static function _labels($name, $definition_name) {
		if(isset(static::$_labels[$name]))
			return static::$_labels[$name];
		return false;
	}

	/**
	 * Return the attributes for the given field name.
	 *
	 * @param string $name the field name
	 * @param string $definition_name the fieldset definition name
	 * @return string attributes for the field
	 */
	public static function _attributes($name, $definition_name) {
		if(isset(static::$_attributes[$name]))
			return static::$_attributes[$name];
		return array();
	}

	/**
	 * Save the references to other models specified in $_reference_many
	 *
	 * @param array $references
	 * @return boolean
	 */
	private function _save_reference_many(array $references) {
		$sql = '';
		foreach(static::$_reference_many as $model => $data) {
			if(! isset($references[$model]) || ! is_array($references[$model]))
				continue;

			$values = array();
			foreach($references[$model] as $id)
				$values[] = '('.$this->pk().', '.$id.') ';

			$sql .= 'DELETE FROM '.$data['table'].' WHERE '.$data['lk'].' = '.$this->pk().'; ';
			$sql .= 'INSERT INTO '.$data['table'].' ('.$data['lk'].', '.$data['fk'].') VALUES '.implode(', ', $values).'; ';
		}

		if(! empty($sql))
			return DB::query($sql)->execute();
		else
			return true;
	}

	/**
	 * Save the various references of the model and then assign the ids to the foreign key column.
	 *
	 * @param bool $validate do we have to validate
	 * @param Model_Base[] $instances Array of instances of various references model with the form 'Model_Name' => instance
	 * @return array|int|bool
	 *        false if the validation failed
	 *        On UPDATE : number of affected rows
	 *        On INSERT : array(0 => autoincrement id, 1 => number of affected rows)
	 */
	private function _save_reference_one($validate, array &$instances) {
		$result = 0;

		foreach(static::$_reference_one as $class => $fk) {
			if(! isset($instances[$class]))
				continue;

			if(($r_temp = $instances[$class]->_do_save($validate, $instances)) === false)
				return false;

			$this->{$fk} = $instances[$class]->{$class::primary_key()};
			$result = self::_combine_save_results($result, $r_temp);
		}

		return $result;
	}

	/**
	 * Save the various references of the model and then assign the ids to the foreign key column.
	 *
	 * @param bool $validate do we have to validate
	 * @param Model_Base[] $instances Array of instances of various references model with the form 'Model_Name' => instance
	 * @return array|int|bool
	 *        false if the validation failed
	 *        On UPDATE : number of affected rows
	 *        On INSERT : array(0 => autoincrement id, 1 => number of affected rows)
	 */
	private function _save_reference_by($validate, array &$instances)
	{
		$result = 0;

		if (! isset(static::$_referenced_by))
			return $result;

		foreach (static::$_referenced_by as $class => $fk) {
			if (!isset($instances[$class]))
				continue;

			$instances[$class]->{$fk} = $this->{$this->primary_key()};
			if (($r_temp = $instances[$class]->_do_save($validate, $instances, false)) === false)
				return false;

			$result = self::_combine_save_results($result, $r_temp);
		}
		return $result;
	}

	/**
	 * Does the actual saving job (_reference_one and current model).
	 *
	 * @param bool $validate  whether to validate the input
	 * @param Model_Base[] $instances Array of instances of various _reference_one model with the form 'Model_Name' => instance
	 * @param bool $with_reference_one
	 * @return array|int|bool
	 *        false if the validation failed
	 *        On UPDATE : number of affected rows
	 *        On INSERT : array(0 => autoincrement id, 1 => number of affected rows)
	 */
	private function _do_save($validate, array &$instances, $with_reference_one = true) {
		if($with_reference_one) {
			$result = $this->_save_reference_one($validate, $instances);
			if ($result === false)
				return false;
		} else
			$result = 0;

		$result_tmp = parent::save($validate);
		if ($result_tmp === false)
			return false;
		$result = self::_combine_save_results($result, $result_tmp);

		// TODO: find how to have references filled !
		$status_tmp = $this->_save_reference_many($references);
		$result = self::_combine_save_results($result, $status_tmp);

		$result_tmp = $this->_save_reference_by($validate, $instances);
		return self::_combine_save_results($result, $result_tmp);
	}

	/**
	 * Combine the results of two various save().
	 *
	 * If at least one of the parameter is an array, the result will be an array,
	 * otherwise we only return the number of affected rows. In case of an INSERT
	 * the returned id will be the one from the primary result if it exists,
	 * otherwise the secondary one.
	 *
	 * @param array|int $one Primary result
	 * @param array|int $two Secondary result
	 * @return array|int|bool
	 *		false if one of the value is false
	 *		On UPDATE : number of affected rows
	 *		On INSERT : array(0 => autoincrement id, 1 => number of affected rows)
	 */
	private static function _combine_save_results($one, $two) {
		if($one === false || $two === false)
			return false;
		elseif(is_array($one) && is_array($two)) // both are an array
			return array($one[0], $one[1] + $two[1]);
		elseif(! is_array($two)) // only $one is an array
			return array($one[0], $one[1] + $two);
		elseif(! is_array($one)) // only $two is an array
			return array($two[0], $one + $two[1]);
		else
			return $one + $two;
	}

	/**
	 * Wraps the saving process in transaction if asked, the actual job is done by _do_save().
	 * The references defined in the $_reference_one static variable are also saved.
	 *
	 * @param bool $validate  whether to validate the input
	 * @param Model_Base[] $instances Array of instances of various referenced models with the form 'Model_Name' => instance
	 * @param bool $transaction Do we use transaction ?
	 * @throws \Exception
	 * @return array|int|bool
	 *        false if the validation failed
	 *        On UPDATE : number of affected rows
	 *        On INSERT : array(0 => autoincrement id, 1 => number of affected rows)
	 */
	public function save($validate = true, array &$instances = array(), $transaction = true) {
		// disable transaction mode if we are already in one
		$transaction = $transaction && ! Database_Connection::instance(null)->in_transaction();

		if($transaction)
			DB::start_transaction();

		try {
			$status = self::_do_save($validate, $instances);
		} catch(\Exception $e) {
			Log::error('[' . get_called_class() . '] Rollback transaction');
			DB::rollback_transaction();
			throw $e;
		}

		if($transaction)
			DB::commit_transaction();
		return $status;
	}

	/**
	 * Retrieve the uuid_short generated for the insertion and
	 * set the corresponding field on the model class (id).
	 *
	 * @param Database_Result $result
	 * @return Database_Result unchanged database result
	 */
	protected function post_save($result) {
		$uuid = DB::query('SELECT @last_uuid AS id')->execute();
		$this->pk($uuid[0]['id']);
		Krtek_Cache::save($this->pk(), $this);
		return $result;
	}

	protected function post_update($result) {
		Krtek_Cache::save($this->pk(), $this);
		return $result;
	}

	/**
	 * Save the retrieved objects to the cache.
	 *
	 * @param Model_Base[] $result the result array or null when there was no result
	 * @return Model_Base[]
	 */
	protected static function post_find($result) {
		$result = parent::post_find($result);
		if(is_array($result))
			foreach($result as $r)
				Krtek_Cache::save($r->pk(), $r);
		return $result;
	}

	/**
	 * @return string Simple string representation of the model class
	 * (table with each element (name : value)
	 */
	public function __toString() {
		$ret = '<table>';
		foreach($this as $name => $value) {
			$ret .= '<tr><td>'.$name.'</td><td>:</td><td>'.$value.'</td></tr>';
		}
		return $ret.'</table>';
	}

	/**
	 * @param int|null $value int
	 * @return int|bool
	 */
	public function pk($value = null) {
		if(! is_null($value)) {
			$this->{static::primary_key()} = $value;
		}

		if(isset($this->{static::primary_key()}))
			return $this->{static::primary_key()};
		return false;
	}

	/**
	 * Non static version of primery_key()
	 * @return string the column used as primary key
	 */
	public function primary_column() {
		return static::primary_key();
	}

	/**
	 * Set this model data based on the given array. The key is the column name
	 * and the value the new value to assign to this column.
	 *
	 * @param array $data
	 */
	public function from_array(array $data) {
		foreach ($data as $key => $value)
			$this->{$key} = $value;
	}

	/*
	 * Retrieve a reference to a model or an array of referenced models.
	 * The name is transformed to a model name and then we try to retrieve
	 * instances based on the reference declared on the class.
	 *
	 * @param   string  $name  the name of the model we want to retrieve
	 * @return  mixed   Based on static::$return_type
	 * @throws Model_Exception
	 */
	public function magic_relation($name) {
		if(substr($name, 0, 6) == 'Model_')
			$model_name = $name;
		else
			$model_name = 'Model_'.ucfirst(str_replace('_', '', Inflector::classify($name)));

		$found = 0;
		$field = static::primary_key();
		$method = null;
		if(isset(static::$_reference_one[$model_name])) {
			$field = static::$_reference_one[$model_name];
			$method = 'find_by_pk';
			++$found;
		}
		if(isset(static::$_referenced_by[$model_name])) {
			$method = 'find_by_'.static::$_referenced_by[$model_name];
			++$found;
		}
		if(isset(static::$_reference_many[$model_name])) {
			$method = 'find_many_by_'.static::$_reference_many[$model_name]['lk'];
			++$found;
		}

		switch($found) {
			case 0:
				throw new Model_Exception('No relation found for this name : '.$name.' '.$model_name);
			case 1:
				if(! isset($this->{$field}))
					throw new Model_Exception('The needed field was not found : '.$field.' on '.get_called_class());
				$id = $this->{$field};
				return call_user_func_array($model_name.'::'.$method, array($id));
			default:
				throw new Model_Exception('Ambiguous relation found for this name : '.$name.' '.$model_name);
		}
	}

	/**
	 * Add methods to retrieve a reference model or an array of referenced models.
	 *
	 * @param   string  $name  The method name
	 * @param   string  $args  The method args
	 * @throws \BadMethodCallException
	 * @return  mixed   Based on static::$return_type
	 */
	public function __call($name, $args) {
		try {
			return $this->magic_relation($name);
		} catch(Model_Exception $e) {
			throw new \BadMethodCallException($e->getMessage());
		}
	}

	/**
	 * Populate known fields of the fieldset with value from this
	 * model instance.
	 *
	 * @param string|Fieldset $definition
	 * @param bool $with_references Also get references and populate their fields as well
	 * @param null $hierarchy
	 * @return Fieldset return the $fieldset to allow chaining
	 */
	public function populate($definition, $with_references = true, $hierarchy = null) {
		if($definition instanceof Fieldset) {
			$definition = $definition->field('_fieldset_name')->get_attribute('value');
		}
		$generator = static::fieldset_generator($definition);
		$generator->populate($this);
		return $generator->fieldset();
	}

	/**
	 * Overridden to add access control based on action type
	 */
	protected function pre_update(&$query) {
		if(! Acl::model_access($this, 'update'))
			throw new HttpForbiddenException();
	}

	/**
	 * Overridden to add access control based on action type
	 */
	protected function pre_delete(&$query) {
		if(! Acl::model_access($this, 'delete'))
			throw new HttpForbiddenException();
	}

	/**
	 * Overridden to add access control based on action type
	 */
	protected function pre_save(&$query) {
		if(! Acl::model_access($this, 'save'))
			throw new HttpForbiddenException();
	}

	/**
	 * Overridden to add access control based on action type
	 */
	protected static function pre_find(&$query) {
		if(! Acl::model_access(get_called_class(), 'find'))
			throw new HttpForbiddenException();
	}

	/**
	 * Log any eventual errors to ease debugging.
	 */
	protected function post_validate($result) {
		if (! $result) {
			$errors = array();
			foreach($this->validation()->error() as $name => $error)
				$errors[] = $name.'('. $error->rule.')';
			Log::error('[' . get_called_class() . '] Validation failed : ' . implode(' / ', $errors));
		}
		return $result;
	}

}
