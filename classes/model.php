<?php

namespace Base;

/**
 * Base exception for various Model related exceptions.
 */
class Model_Exception extends \Fuel\Core\Fuel_Exception { };

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
abstract class Model_Base extends \Model_Crud {
//	/**
//	 * @var array List of fieldset definition for this model
//	 */
//	protected static $_fieldsets = array();

//	/**
//	 * @var array reprensation of various parent of this class with the form 'Model_Name' => 'FK_column'
//	 */
//	protected static $_parent = null;

	/**
	 * Count all row in the table where the column has the provided value.
	 *
	 * @param string Column name
	 * @param mixed value to look for
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
	 * @return Model_*|array Instance of a model object, or array of instances.
	 */
	public static function e_find($id = null) {
		if(is_null($id)) {
			$object = static::find_all();
			if(is_null($object))
				$object = array();
		} else {
			$object = static::find_by_pk($id);
			if(is_null($object))
				throw new \Fuel\Core\HttpNotFoundException();
		}

		return $object;
	}

	/**
	 * Add magic methods to count by columns
	 *
	 * @param   string  $name  The method name
	 * @param   string  $args  The method args
	 * @return  mixed   Based on static::$return_type
	 * @throws  BadMethodCallException
	 */
	public static function __callStatic($name, $args)
	{
		if (strncmp($name, 'count_by_', 9) === 0)
			return static::count_by(substr($name, 9), reset($args));
		return parent::__callStatic($name, $args);
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
	 * @param string $name Definition name
	 * @param array $config The config for this fieldset (only used upon creation)
	 * @return Fieldset the generated fieldset
	 */
	public static function fieldset($name, array $config = array()) {
		$fieldset_name = get_called_class().'_'.$name;
		$fieldset = \Fieldset::instance($fieldset_name);
		if(! $fieldset) {
			$fieldset = \Fieldset::forge($fieldset_name, $config);
			self::_process_fieldset_definition($fieldset, $name);
			$fieldset->hidden('_fieldset_name', $name);
			$fieldset->hidden('_fieldset_model', get_called_class());
		}
		return $fieldset;
	}

	/**
	 * Process a fieldset defintion by adding each found field in the
	 * specified defintion to the given Fieldset instance. Each individual
	 * field is processed by the _process_field method.
	 *
	 * @param Fieldset $fieldset Fieldset instance to whom we must add the fields
	 * @param string $name The definition name
	 */
	protected static function _process_fieldset_definition($fieldset, $name) {
		if(! array_key_exists($name, static::$_fieldsets))
				throw new Model_Exception("Unknown fieldset name : ".get_called_class().'->'.$name);

		foreach(static::$_fieldsets[$name] as $field)
			self::_process_field($fieldset, $field);
	}

	/**
	 * Process a field definition from a fieldset definition. You can have
	 * three kind of fields
	 * 1° simple fields, which are added to the fieldset using information from
	 *    the various properties of the model by the _add_field method.
	 * 2° Other definition extension with the form 'extend:name', which add all
	 *    fields from the extended definition to the fieldset (the supplementary
	 *    definition is parsed by the _process_fieldset_definition method)
	 * 3° Definition from another model class with the form 'Model_Name:name', which
	 *    add all fields from the definition of the foreign class.
	 * 4° Special fields, like button, with the form 'special:name', which are added
	 *    through the _add_special_field method.
	 *
	 * @param Fieldset $fieldset Fieldset instance to whom we must add the fields
	 * @param string $field Field definition
	 * @return mixed irrelevant
	 */
	protected static function _process_field($fieldset, $field) {
		$info = explode(':', $field, 2);
		if(count($info) == 1)
			return self::_add_field($fieldset, $field);

		switch($info[0]) {
			case 'extend':
				// add the fields from this other definition (name is second "parameter")
				return self::_process_fieldset_definition($fieldset, $info[1]);
			case 'special':
				// add this special field
				return self::_add_special_field($fieldset, $info[1]);
			default:
				// first "parameter" is considered like a model classname, second "parameter" is the
				// definition name in this other model class.
				return call_user_func_array(array($info[0], '_process_fieldset_definition'), array($fieldset, $info[1]));
		}
	}

	/**
	 * Add the field with the given name to the fieldset.
	 *
	 * @param Fieldset $fieldset Fieldset instance to whom we must add the fields
	 * @param string $field Field definition
	 */
	protected static function _add_field($fieldset, $field) {
		$label = static::_labels($field);
		if(! $label)
			throw new Model_Exception ('No label found for '.$field);

		$rules = static::_rules($field);
		if($rules)
			$f = $fieldset->validation()->add_field(self::_field_name($field), $label, $rules);
		else
			$f = $fieldset->validation()->add(self::_field_name($field), $label);

		$attributes = static::_attributes($field);

		if(substr($field, -3) == '_id' && $attributes['type'] == 'select') {
			if(isset($attributes['callback'])) {
				$callback = $attributes['callback'];
				unset($attributes['callback']);
			} else
				$callback = array('Model_'.ucfirst(substr($field, 0, -3)), 'find_all');

			$attributes['options'] = array();
			foreach(call_user_func($callback) as $rows) {
				$attributes['options'][$rows['id']] = $rows->select_name();
			}
		}

		// Set certain types through specific setter
		foreach (array('label', 'type', 'value', 'options') as $prop)
			if (array_key_exists($prop, $attributes)) {
				$f->{'set_'.$prop}($attributes[$prop]);
				unset($attributes[$prop]);
			}
		$f->set_attribute($attributes);
	}

	/**
	 * Add the special field with the given name to the fieldset.
	 * There exists three particular field for now : 'submit', 'reset',
	 * 'cancel'. Which add the related button type to the fieldset.
	 *
	 * @param Fieldset $fieldset Fieldset instance to whom we must add the fields
	 * @param string $field Field definition
	 */
	protected static function _add_special_field($fieldset, $field) {
		$label = static::_labels($field);
		$fieldset->{$field}($label);
	}

	/**
	 * Compute the name to use for a field in a fieldset.
	 *
	 * @param string $field name of the field
	 * @param string $model name of the model or get_called_class() if null
	 * @return string the name of the field for the fieldset
	 */
	protected static function _field_name($field, $model = null) {
		$model = is_null($model) ? get_called_class() : $model;
		return $model::$_table_name.'_'.$field;
	}

	/**
	 * Return the rules for the given field name.
	 *
	 * @param string $name the field name
	 * @return array|bool rules for the field, false of there's no rules
	 */
	protected static function _rules($name) {
		if(isset(static::$_rules[$name]))
			return static::$_rules[$name];
		return false;
	}

	/**
	 * Return the label for the given field name.
	 *
	 * @param string $name the field name
	 * @return string|bool label for the field, false if there's no label
	 */
	protected static function _labels($name) {
		if(isset(static::$_labels[$name]))
			return static::$_labels[$name];
		return false;
	}

	/**
	 * Return the attributes for the given field name.
	 *
	 * @param string $name the field name
	 * @return array attributes for the field
	 */
	protected static function _attributes($name) {
		return static::$_attributes[$name];
	}

	/**
	 * Process data found in the post input array to create or update
	 * the model(s) referenced by the posted fieldset.
	 *
	 * Try as best as possible to return the instance of the called model if it exists.
	 * If the method is called trough Model_Base, the _fieldset_model instance will
	 * be returned.
	 *
	 * @param $data Default value
	 * @return Model_?|false The created / updated model or false if an error occured
	 */
	public static function process_fieldset_input(array $data = array()) {
		if (! \Input::method('POST') && empty($data))
			return false;

		$model = \Input::post('_fieldset_model', \Arr::get($data, '_fieldset_model', get_called_class()));
		$definition = \Input::post('_fieldset_name', \Arr::get($data, '_fieldset_name'));

		// replace values from $data with POST content if existant. This is needed
		// because these values takes precedance over POST for validation.
		foreach($data as $k => $v)
			$data[$k] = \Input::post($k, $data[$k]);

		if(! $model::fieldset($definition)->validation()->run($data))
			return false;

		if(empty($model))
			throw new Model_Exception('No model provided, impossible to process fieldset.');
		if(empty($definition))
			throw new Model_Exception('No definition provided, impossible to process fieldset.');

		$fields_per_class = array();
		self::_process_fieldset_input($model, $definition, $fields_per_class, $data);

		$instances = array();
		foreach($fields_per_class as $class => $fields) {
			$instances[$class] = null;
			$class_id = \Input::post(static::_field_name(static::primary_key(), $class));
			if(! is_null($class_id)) { // we're doing an update
				$instances[$class] = $class::find_by_pk($class_id);
				$instances[$class]->from_array($fields);
			} else { // it is a creation
				$instances[$class] = $class::forge($fields);
			}
			if(! $instances[$class]) {
				return false;
			}
		}

		try {
			$status = $instances[$model]->save($instances);
		} catch(Exception $e) {
			\Fuel\Core\Log::error($e);
			return false;
		}

		$called = get_called_class();
		if($model != $called && $called != get_class() && isset($instances[$called]))
			$model = $called; // if possible try to return an instance of the called class.
		return $status ? $instances[$model] : false;
	}

	/**
	 * Do the processing of fieldset defininition and store every found and
	 * relevant fields sorted by model names in the $fields array passed by
	 * reference.
	 *
	 * @param string $model Base model name
	 * @param string $definition Base definition name
	 * @param array $fields (by reference) list of processed fields
	 */
	private static function _process_fieldset_input($model, $definition, array &$fields, array $data) {
		$fields[$model] = array();
		foreach($model::$_fieldsets[$definition] as $field) {
			$info = explode(':', $field, 2);
			if(count($info) == 1) {
				$name = self::_field_name($field, $model);
				$default = isset($model::$_defaults) ? \Arr::get($model::$_defaults, $field, null) : null;
				$fields[$model][$field] = \Input::post($name, \Arr::get($data, $name, $default));

			} else {
				switch($info[0]) {
					case 'extend':
						$new_model = $model; // extend a definition from the same model
						break;
					case 'special':
						continue 2; // special field, nothing to do
					default:
						$new_model = $info[0]; // inclusion of a definition from another model
				}
				self::_process_fieldset_input($new_model, $info[1], $fields, $data);
			}
		}
	}

	/**
	 * Save the various parents of the model and then assign the ids to the foreign key column.
	 *
	 * @param array $instances Array of instances of various parents model with the form 'Model_Name' => instance
	 * @return array|int|bool
	 *		false if the validation failed
	 *		On UPDATE : number of affected rows
	 *		On INSERT : array(0 => autoincrement id, 1 => number of affected rows)
	 */
	private function _save_parents(array &$instances) {
		$result = 0;
		if(! isset(static::$_parent))
			return $result;

		foreach(static::$_parent as $class => $fk) {
			if(! isset($instances[$class]))
				continue;

			if(($r_temp = $instances[$class]->_do_save($instances)) === false)
				return false;

			$this->{$fk} = $instances[$class]->{$class::primary_key()};
			$result = self::_combine_save_results($result, $r_temp);
		}
		return $result;
	}

	/**
	 * Does the actual saving job (parents and current model).
	 *
	 * @param array $instances Array of instances of various parents model with the form 'Model_Name' => instance
	 * @return array|int|bool
	 *		false if the validation failed
	 *		On UPDATE : number of affected rows
	 *		On INSERT : array(0 => autoincrement id, 1 => number of affected rows)
	 */
	private function _do_save(array &$instances) {
		$r_parents = $this->_save_parents($instances);
		$r_this = parent::save();
		return self::_combine_save_results($r_this, $r_parents);
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
	 * Wraps the saving process in transaction is asked, the actual job is done by _do_save().
	 * The parents defined in the $_parent static variable are also saved.
	 *
	 * @param array $instances Array of instances of various parents model with the form 'Model_Name' => instance
	 * @param bool $transaction Do we use transaction ?
	 * @return array|int|bool
	 *		false if the validation failed
	 *		On UPDATE : number of affected rows
	 *		On INSERT : array(0 => autoincrement id, 1 => number of affected rows)
	 */
	public function save(array &$instances = array(), $transaction = true) {
		if($transaction)
			\Fuel\Core\DB::start_transaction();

		try {
			$status = self::_do_save($instances);
		} catch(Exception $e) {
			\Fuel\Core\DB::rollback_transaction();
			throw $e;
		}

		if($transaction)
			\Fuel\Core\DB::commit_transaction();
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
		$uuid = \Fuel\Core\DB::query('SELECT @last_uuid as id')->execute();
		$this->{static::primary_key()} = $uuid[0]['id'];
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
	 * Set this model data based on the given array. The key is the column name
	 * and the value the new value to assign to this column.
	 *
	 * @param array $data
	 */
	public function from_array(array $data) {
		foreach ($data as $key => $value)
			$this->{$key} = $value;
	}

	/**
	 * Populate known fields of the fieldset with value from this
	 * model instance.
	 *
	 * @param Fieldset $fieldset
	 * @param bool $with_parent Also get parents and populate their fields aswell
	 */
	public function populate($fieldset, $with_parent = true) {
		$fieldset->hidden(static::_field_name(static::primary_key()), $this->{static::primary_key()});
		foreach($this->to_array() as $name => $value) {
			$field_name = static::_field_name($name);
			$field = $fieldset->field($field_name);
			if($field)
				$field->set_value(\Input::post($field_name, $value), true);
		}

		if($with_parent && isset(static::$_parent))
			foreach(static::$_parent as $class => $fk) {
				$parent = $class::find_by_pk($this->{$fk});
				if($parent)
					$parent->populate($fieldset, $with_parent);
			}
	}

	/**
	 * Overridden to add access control based on action type
	 */
	protected function pre_update($query) {
		if(! Acl::model_access($this, 'update'))
			throw new HttpForbiddenException();
		return parent::pre_update($query);
	}

	/**
	 * Overridden to add access control based on action type
	 */
	protected function pre_delete($query) {
		if(! Acl::model_access($this, 'delete'))
			throw new HttpForbiddenException();
		return parent::pre_delete($query);
	}

	/**
	 * Overridden to add access control based on action type
	 */
	protected function pre_save($query) {
		if(! Acl::model_access($this, 'save'))
			throw new HttpForbiddenException();
		return parent::pre_save($query);
	}

	/**
	 * Overridden to add access control based on action type
	 */
	protected static function pre_find($query) {
		if(! Acl::model_access(get_called_class(), 'find'))
			throw new HttpForbiddenException();
		return parent::pre_find($query);
	}
}
