<?php

namespace KrtekBase;

use Fuel\Core\DB;
use Fuel\Core\Fieldset;
use Fuel\Core\Input;
use Fuel\Core\Arr;
use Fuel\Core\Database_Connection;
use Fuel\Core\Database_Result;
use Fuel\Core\Inflector;


/**
 * Base exception for various Model related exceptions.
 */
class Model_Exception extends \Fuel\Core\FuelException { }

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
abstract class Model_Base extends \Fuel\Core\Model_Crud {
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
	 * @throws \Fuel\Core\HttpNotFoundException
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
				throw new \Fuel\Core\HttpNotFoundException();
		}

		return $object;
	}

	/**
	 * Specify the table for the column to avoid problems when joining.
	 *
	 * @param   mixed  $value  The primary key value to find
	 * @return  null|Model_Base  Either null or a new Model object
	 */
	public static function find_by_pk($value)
	{
		if(Cache::has($value))
			return Cache::get($value);

		return static::find_one_by(static::$_table_name.'.'.static::primary_key(), $value);
	}

	/**
	 * Specify the table for the column to avoid problems when joining. If a table
	 * is already specified, nothing's done.
	 *
	 * @param   mixed  $column  The column to search
	 * @param   mixed  $value   The value to find
	 * @param   string $operator The operator to use for the comparison
	 * @return  null|Model_Base  Either null or a new Model object
	 */
	public static function find_one_by($column, $value = null, $operator = '=') {
		if(strpos($column, '.') === false)
			$column = static::$_table_name.'.'.$column;

		return parent::find_one_by($column, $value, $operator);
	}

	/**
	 * Retrieve a list of id for the current model which reference the the foreign
	 * model through the association table defined in $_reference_many.
	 *
	 * @param string $model referenced model name
	 * @param int $id id of the foreign key in the association table
	 * @return array id to retrieve from the database
	 */
	protected static function ids_for_find_many($model, $id) {
		$data = static::$_reference_many[$model];

		$sql = 'SELECT '.$data['lk'].' FROM '.$data['table'].' WHERE '.$data['fk'].' = '.$id;
		$result = DB::query($sql)->execute()->as_array();

		$ids = array();
		foreach($result as $r) {
			$ids[] = $r[$data['lk']];
		}
		return $ids;
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
		$name = 'Model_'.ucfirst(substr($column, 0, -3));

		$ids = static::ids_for_find_many($name, $id);

		$instances = array();
		foreach($ids as $id)
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
	 * Add the current table_name to the actual hierarchy and
	 * return the new value. Must be called each time a processing class
	 * pass the relay to a child Model.
	 *
	 * @param string $actual actual hierarchy string
	 * @return string new hierarchy string to pass a child.
	 */
	public static function update_hierarchy($actual) {
		return $actual.static::$_table_name.'-';
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
		$fieldset = Fieldset::instance($fieldset_name);
		if(! $fieldset) {
			$fieldset = Fieldset::forge($fieldset_name, $config);
			self::_process_fieldset_definition($fieldset, $name);
			$fieldset->add(array('name' => '_fieldset_name', 'value' => $name, 'type' => 'hidden'));
			$fieldset->add(array('name' => '_fieldset_model', 'value' => get_called_class(), 'type' => 'hidden'));
		}
		return $fieldset;
	}

	/**
	 * Process a fieldset definition by adding each found field in the
	 * specified definition to the given Fieldset instance. Each individual
	 * field is processed by the _process_field method.
	 *
	 * @param Fieldset $fieldset Fieldset instance to whom we must add the fields
	 * @param string $name The definition name
	 * @param null $hierarchy
	 */
	protected static function _process_fieldset_definition($fieldset, $name, $hierarchy = null) {
		foreach(static::_fieldset($name) as $field)
			self::_process_field($fieldset, $field, $name, $hierarchy);
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
	 * @param string $definition_name the fieldset definition name
	 * @param $hierarchy
	 */
	protected static function _process_field($fieldset, $field, $definition_name, $hierarchy) {
		$info = explode(':', $field, 2);
		if(count($info) == 1) {
			self::_add_field($fieldset, $field, $definition_name, $hierarchy);
			return;
		}

		switch($info[0]) {
			case 'extend':
				// add the fields from this other definition (name is second "parameter")
				self::_process_fieldset_definition($fieldset, $info[1], $hierarchy);
				break;
			case 'special':
				// add this special field
				self::_add_special_field($fieldset, $info[1], $definition_name);
				break;
			case 'many':
				// add a field for ids in a many to many relation
				$field_name = static::$_reference_many[$info[1]]['fk'];
				self::_add_field($fieldset, $field_name, $definition_name, $hierarchy);
				break;
			default:
				// first "parameter" is considered like a model class name, second "parameter" is the
				// definition name in this other model class.
				$args = array($fieldset, $info[1], static::update_hierarchy($hierarchy));
				call_user_func_array(array($info[0], '_process_fieldset_definition'), $args);
				break;
		}
	}

	/**
	 * Add the field with the given name to the fieldset.
	 *
	 * @param Fieldset $fieldset Fieldset instance to whom we must add the fields
	 * @param string $field Field definition
	 * @param string $definition_name the fieldset definition name
	 * @param $hierarchy
	 * @throws Model_Exception
	 * @return void
	 */
	protected static function _add_field($fieldset, $field, $definition_name, $hierarchy) {
		$label = static::_labels($field, $definition_name);
		if(! $label)
			throw new Model_Exception ('No label found for '.$field);

		$rules = static::_rules($field, $definition_name);
		if($rules)
			$f = $fieldset->validation()->add_field(self::_field_name($field, $hierarchy), $label, $rules);
		else
			$f = $fieldset->validation()->add(self::_field_name($field, $hierarchy), $label);

		$attributes = static::_attributes($field, $definition_name);

		if($attributes['type'] == 'file') {
			$config = $fieldset->get_config('form_attributes', array()) + array('enctype' => 'multipart/form-data');
			$fieldset->set_config('form_attributes', $config);
		} else if(substr($field, -3) == '_id' && in_array($attributes['type'], array('select', 'checkbox', 'radio'))) {
			if(isset($attributes['callback'])) {
				$callback = $attributes['callback'];
				$callback_params = isset($attributes['callback_params']) ? $attributes['callback_params'] : array();
				unset($attributes['callback']);
				unset($attributes['callback_params']);
			} else {
				$callback = array('Model_' . ucfirst(substr($field, 0, -3)), 'find_all');
				$callback_params = array();
			}

			$attributes['options'] = array();
			$rows = call_user_func_array($callback, $callback_params);
			if($rows)
				foreach($rows as $row)
					$attributes['options'][$row['id']] = $row->select_name();
		}

		// Set certain types through specific setter
		foreach ($attributes as $attr => $val)
			if (method_exists($f, $method = 'set_'.$attr)) {
				$f->{$method}($val);
				unset($attributes[$attr]);
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
	 * @param string $definition_name the fieldset definition name
	 */
	protected static function _add_special_field($fieldset, $field, $definition_name) {
		$label = static::_labels($field, $definition_name);
		$attributes = array();
		switch($field) {
			case 'cancel':
				$attributes['class'] = 'cancel';
				$attributes['type'] = 'reset';
				$attributes['value'] = $label;
				$label = '';
				break;
			case 'submit':
			case 'reset':
				$attributes['type'] = $field;
				$attributes['value'] = $label;
				$label = '';
				break;
		}
		$fieldset->add($field, $label, $attributes);
	}

	/**
	 * Compute the name to use for a field in a fieldset.
	 *
	 * @param string $field name of the field
	 * @param $hierarchy
	 * @param string $model name of the model or get_called_class() if null
	 * @return string the name of the field for the fieldset
	 */
	protected static function _field_name($field, $hierarchy, $model = null) {
		$model = is_null($model) ? get_called_class() : $model;
		return $hierarchy.$model::$_table_name.'-'.$field;
	}

	/**
	 * Return the fieldset definition corresponding to $name or throw
	 * exception if not found or invalid.
	 *
	 * @throws Model_Exception when fieldset not found or invalid
	 * @param string $name the definition name
	 * @return array fieldset definition
	 */
	protected static function _fieldset($name) {
		if(! array_key_exists($name, static::$_fieldsets))
				throw new Model_Exception("Unknown fieldset name : ".get_called_class().'->'.$name);

		if(! is_array(static::$_fieldsets[$name]))
				throw new Model_Exception("Invalid fieldset definition : ".get_called_class().'->'.$name);

		return static::$_fieldsets[$name];
	}

	/**
	 * Return the rules for the given field name.
	 *
	 * @param string $name the field name
	 * @param string $definition_name the fieldset definition name
	 * @return string|bool rules for the field, false of there's no rules
	 */
	protected static function _rules($name, $definition_name) {
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
	 * @return array attributes for the field
	 */
	public static function _attributes($name, $definition_name) {
		if(isset(static::$_attributes[$name]))
			return static::$_attributes[$name];
		return array();
	}

	/**
	 * Process data found in the post input array to create or update
	 * the model(s) referenced by the posted fieldset.
	 *
	 * Try as best as possible to return the instance of the called model if it exists.
	 * If the method is called trough Model_Base, the _fieldset_model instance will
	 * be returned.
	 *
	 * @param array $data Default value
	 * @param null $hierarchy
	 * @throws Model_Exception
	 * @return Model_Base|bool The created / updated model or false if an error occurred
	 */
	public static function process_fieldset_input(array $data = array(), $hierarchy = null) {
		if (! Input::method('POST') && empty($data))
			return false;

		$model = Input::post('_fieldset_model', Arr::get($data, '_fieldset_model', get_called_class()));
		$definition = Input::post('_fieldset_name', Arr::get($data, '_fieldset_name'));

		// replace values from $data with POST content if existent. This is needed
		// because these values takes precedence over POST for validation.
		foreach($data as $k => $v)
			$data[$k] = Input::post($k, $data[$k]);


		if(! empty($_FILES)) {
			\Fuel\Core\Upload::process($model::$_upload_config);
			if(\Fuel\Core\Upload::is_valid()) {
				\Fuel\Core\Upload::save();

				foreach(\Fuel\Core\Upload::get_files() as $file) {
					$data[$file['field']] = '/'.$file['saved_to'].$file['saved_as'];
				}
			}
			$upload_errors = \Fuel\Core\Upload::get_errors();
			if(! empty($upload_errors)) {
				foreach($upload_errors as $errors) {
					foreach($errors['errors'] as $error) {
						\Messages\Messages::instance()->message('error', $error['message']);
					}
				}
				return false;
			}
		}

		if(! $model::fieldset($definition)->validation()->run($data))
			return false;

		if(empty($model))
			throw new Model_Exception('No model provided, impossible to process fieldset.');
		if(empty($definition))
			throw new Model_Exception('No definition provided, impossible to process fieldset.');

		$fields_per_class = array();
		self::_process_fieldset_input($model, $definition, $fields_per_class, $data, $hierarchy);

		$references = array();
		foreach($fields_per_class as $class => $data) {
			if(! isset($model::$_reference_many[$class]))
				continue;

			$ids = $data[$model::$_reference_many[$class]['fk']];

			if(! is_array($ids))
				$ids = array($ids);
			$references[$class] = $ids;
			unset($fields_per_class[$class]);
		}

		/** @var $instances Model_Base[]  */
		$instances = array();
		foreach($fields_per_class as $class => $fields) {
			$instances[$class] = null;
			$class_id = $fields[$class::primary_key()];
			if(! empty($class_id)) { // we're doing an update
				$instances[$class] = $class::find_by_pk($class_id);
				$instances[$class]->from_array($fields);
			} else { // it is a creation
				unset($fields[$class::primary_key()]);
				$instances[$class] = $class::forge($fields);
			}
			if(! $instances[$class]) {
				\Log\Log::error('Unable to find '.$class.' in instances.');
				return false;
			}
		}

		try {
			$status = $instances[$model]->save(true, $instances);

			$status_tmp = $instances[$model]->_save_reference_many($references);
			$status = self::_combine_save_results($status, $status_tmp);
		} catch(\Exception $e) {
			\Log\Log::error($e);
			return false;
		}

		$called = get_called_class();
		if($model != $called && $called != get_class() && isset($instances[$called]))
			$model = $called; // if possible try to return an instance of the called class.
		return $status ? $instances[$model] : false;
	}

	/**
	 * Do the processing of fieldset definition and store every found and
	 * relevant fields sorted by model names in the $fields array passed by
	 * reference.
	 *
	 * @param string $model Base model name
	 * @param string $definition Base definition name
	 * @param array $fields (by reference) list of processed fields
	 * @param array $data default values
	 * @param $hierarchy
	 * @return void
	 */
	private static function _process_fieldset_input($model, $definition, array &$fields, array $data, $hierarchy) {
		if(! isset($fields[$model]))
				$fields[$model] = array();

		$fields[$model][static::primary_key()] = Input::post(static::_field_name(static::primary_key(), $hierarchy, $model));

		foreach($model::_fieldset($definition) as $field) {
			$info = explode(':', $field, 2);
			if(count($info) == 1) {
				$fields[$model][$field] = self::_get_field_value($model, $field, $data, $hierarchy);
			} else {
				switch($info[0]) {
					case 'extend':
						$new_model = $model; // extend a definition from the same model
						$new_hierarchy = $hierarchy;
						break;
					case 'special':
						continue 2; // special field, nothing to do
					case 'many':
						$fieldname = $model::$_reference_many[$info[1]]['fk'];
						$fields[$info[1]][$fieldname] = self::_get_field_value($model, $fieldname, $data, $hierarchy);
						continue 2;
					default:
						$new_model = $info[0]; // inclusion of a definition from another model
						$new_hierarchy = $model::update_hierarchy($hierarchy);
				}
				self::_process_fieldset_input($new_model, $info[1], $fields, $data, $new_hierarchy);
			}
		}
	}

	/**
	 * Retrieve the value for the given field from the POST data, the default value passed as a parameter
	 * or the default values set on the class as a last resort.
	 *
	 * @param string $model Base model name
	 * @param string $field field name
	 * @param array $data default values
	 * @param $hierarchy
	 * @return string the value
	 */
	private static function _get_field_value($model, $field, $data, $hierarchy) {
		$name = self::_field_name($field, $hierarchy, $model);
		$default = Arr::get($model::$_defaults, $field, null);
		$value = Input::post($name, Arr::get($data, $name, $default));
		return $value;
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
				$values[] = '('.$this->{static::primary_key()}.', '.$id.') ';

			$sql .= 'DELETE FROM '.$data['table'].' WHERE '.$data['lk'].' = '.$this->{static::primary_key()}.'; ';
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
		$result = self::_combine_save_results($result, $result_tmp);

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
			if(! $status)
				\Log\Log::error('Validation failed : '.$this->validation()->show_errors());
		} catch(\Exception $e) {
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
		$uuid = DB::query('SELECT @last_uuid as id')->execute();
		$this->{static::primary_key()} = $uuid[0]['id'];
		Cache::save($this->{static::primary_key()}, $this);
		return $result;
	}

	protected function post_update($result) {
		Cache::save($this->{static::primary_key()}, $this);
		return $result;
	}

	/**
	 * Save the retrieved objects to the cache.
	 *
	 * @inheritDoc
	 */
	protected static function post_find($result) {
		if(is_array($result))
			foreach($result as $r)
				Cache::save($r->{static::primary_key()}, $r);
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
	 * @param Fieldset $fieldset
	 * @param bool $with_references Also get references and populate their fields as well
	 * @param null $hierarchy
	 * @return Fieldset return the $fieldset to allow chaining
	 */
	public function populate($fieldset, $with_references = true, $hierarchy = null) {
		if(isset($this->{static::primary_key()})) {
			$name = static::_field_name(static::primary_key(), $hierarchy);
			$fieldset->add(array('name' => $name, 'value' => $this->{static::primary_key()}, 'type' => 'hidden'));
		}

		foreach($this->to_array() as $name => $value) {
			$field_name = static::_field_name($name, $hierarchy);
			$field = $fieldset->field($field_name);
			if($field)
				$field->set_value(Input::post($field_name, $value), true);
		}

		if($with_references) {
			foreach(static::$_reference_one as $class => $fk)
				if(isset($this->{$fk})) {
					/** @var $reference Model_Base */
					$reference = $class::find_by_pk($this->{$fk});
					if($reference)
						$reference->populate($fieldset, $with_references, static::update_hierarchy($hierarchy));
				}

			foreach (static::$_referenced_by as $class => $fk)
				if(isset($this->{static::primary_key()})) {
					/** @var $referenced Model_Base|Model_Base[] */
					$referenced = $class::find_by($fk, $this->{static::primary_key()});
					if($referenced) {
						if(is_array($referenced))
							$referenced = current($referenced);
					} else
						$referenced = $class::forge(array($fk => $this->{static::primary_key()}));
					$referenced->populate($fieldset, false, static::update_hierarchy($hierarchy));
				}

			foreach(static::$_reference_many as $class => $data)
				if(isset($this->{static::primary_key()})) {
					$ids = $class::ids_for_find_many(get_called_class(), $this->{static::primary_key()});

					$field_name = static::_field_name($data['fk'], $hierarchy);
					$field = $fieldset->field($field_name);
					if($field)
						$field->set_value(Input::post($field_name, $ids), true);
				}
		}

		return $fieldset;
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
}
