<?php

namespace KrtekBase\Fieldset;

use Fuel\Core\Arr;
use Fuel\Core\DB;
use Fuel\Core\Database_Connection;
use Fuel\Core\Fieldset;
use Fuel\Core\Input;
use Fuel\Core\Upload;
use KrtekBase\Model_Base;
use Log\Log;
use Messages\Messages;

/**
 * Generate fieldsets based on meta-information defined on the
 * model class
 *
 * @package krtek-Base
 * @category BaseClasses
 * @author Gilles Meier <krtek4@gmail.com>
 * @version 1.0
 * @license Affero GPLv3 http://www.gnu.org/licenses/agpl-3.0-standalone.html
 * @copyright 2012 Gilles Meier <krtek4@gmail.com>
 * @link https://github.com/krtek4/fuel-krtek-base
 */
class Fieldset_Processor extends Fieldset_Holder {
	protected static $fields_per_class;

	/**
	 * Process a fieldset definition and set the value provided
	 * in the input to an instance of the related model object
	 *
	 * @param $fieldset Fieldset
	 * @param $definition string
	 * @param $class string
	 * @param $hierarchy string
	 * @param array $data default data to use if nothings found in the input or the object
	 * @return Model_Base|bool The created / updated model or false if an error occurred
	 */
	public static function process($fieldset, $definition, $class, $hierarchy = null, array $data = array()) {
		self::$fields_per_class = array();
		$processor = new Fieldset_Processor($fieldset, $definition, $class, $hierarchy, $data);
		return $processor->process_fieldset();
	}

	/**
	 * Call the process_input method on a newly created processor. Should be used internally to
	 * parse "child" definition of the "main" one.
	 *
	 * @param $fieldset Fieldset
	 * @param $definition string
	 * @param $class string
	 * @param $hierarchy string
	 * @param array $data default data to use if nothings found in the input or the object
	 */
	protected static function internal_process($fieldset, $definition, $class, $hierarchy, $data) {
		$processor = new Fieldset_Processor($fieldset, $definition, $class, $hierarchy, $data);
		$processor->process_input();
	}

	/** @var array default data given on the class creation */
	private $data = array();


	protected function __construct($fieldset, $definition, $class, $hierarchy, $data) {
		parent::__construct($fieldset, $definition, $class, $hierarchy);
		$this->data = $data;
	}

	/**
	 * Process a fieldset definition and set the value provided
	 * in the input to an instance of the related model object
	 *
	 * @throws Fieldset_Exception if no input found
	 * @return Model_Base|bool The created / updated model or false if an error occurred
	 */
	protected function process_fieldset() {
		if(! Input::method('POST') && empty($this->data))
			throw new Fieldset_Exception("No input or default data found");

		// replace values from $this->data with POST content if existent. This is needed
		// because these values takes precedence over POST for validation.
		foreach($this->data as $k => $v)
			$this->data[$k] = Input::post($k, $this->data[$k]);

		if(! empty($_FILES)) {
			$this->process_uploads();
		}

		// run validation with given data, missing values will be
		// directly taken from the POST data.
		if(! $this->fieldset()->validation()->run($this->data)) {
			Log::info("Pre-processing validation failed");
			return false;
		}

		$this->process_input();
		$references = $this->extract_references();
		$instances = $this->create_instances();

		try {
			if($this->save($instances, $references)) {
				$model = $this->clazz();
				$called = get_called_class();
				if($model != $called && $called != get_class() && isset($instances[$called]))
					$model = $called; // if possible try to return an instance of the called class.
				return $instances[$model];
			}
		} catch(\Exception $e) {
			Log::error($e);
		}
		return false;
	}

	/**
	 * Process uploaded files :
	 * 1° Move them according to upload config
	 * 2° Set the right filename in the data array
	 * 3° Set error messages if there is any
	 *
	 * @return bool
	 */
	protected function process_uploads() {
		Upload::process($this->static_variable('_upload_config'));
		if(Upload::is_valid()) {
			Upload::save();

			foreach(Upload::get_files() as $file) {
				$this->data[$file['field']] = '/'.$file['saved_to'].$file['saved_as'];
			}
		}
		$upload_errors = Upload::get_errors();
		if(! empty($upload_errors)) {
			foreach($upload_errors as $errors) {
				foreach($errors['errors'] as $error) {
					Messages::instance()->message('error', $error['message']);
				}
			}
			return false;
		}
		return true;
	}

	/**
	 * Process the various fields of the definition and get their value
	 * in the Input. The implementation of the result storing is left to
	 * the save_field_value method.
	 *
	 * @throws Fieldset_Exception
	 */
	protected function process_input() {
		$this->save_field_value($this->model_pk());

		foreach($this->fields() as $field) {
			$info = explode(':', $field, 2);
			if(count($info) == 1) {
				$this->save_field_value($field);
				return;
			}

			$references = $this->static_variable('_references_many');
			switch($info[0]) {
				case 'extend':
					// add the fields from this other definition (name is second "parameter")
					Fieldset_Processor::internal_process($this->fieldset(), $info[1], $this->clazz(), $this->hierarchy(), $this->data);
					break;
				case 'special':
					break;
				case 'many':
					// add a field for ids in a many to many relation
					$fieldname = $references[$info[1]]['fk'];
					$this->save_field_value($fieldname, $info[1]);
					break;
				default:
					// first "parameter" is considered like a model class name, second "parameter" is the
					// definition name in this other model class.
					Fieldset_Processor::internal_process($this->fieldset(), $info[1], $info[0], $this->updated_hierarchy(), $this->data);
				break;
			}
		}
	}

	private function extract_references() {
		$return = array();
		foreach($this->get_classes() as $class) {
			// only consider class that are a many to many reference
			$references = call_user_func_array(array($class, 'get_meta'), array('_reference_many'));
			if(! isset($references[$class]))
				continue;

			$values = $this->remove_class($class);
			$ids = $values[$references[$class]['fk']];
			$return[$class] = is_array($ids) ? $ids : array($ids);
		}
		return $return;
	}

	private function create_instances() {
		/** @var $instances Model_Base[] */
		$instances = array();
		foreach($this->get_classes() as $class) {
			$instances[$class] = null;
			$values = $this->get_values($class);

			if(empty($values[$this->model_pk()])) { // no id -> creation
				unset($values[$this->model_pk()]);
				$instances[$class] = call_user_func_array(array($this->clazz(), 'forge'), array($values));
			} else { // or update
				$instances[$class] = call_user_func_array(array($this->clazz(), 'find_by_pk'), array($values[$this->model_pk()]));
				$instances[$class]->from_array($values);
			}
			if(is_null($instances[$class]) || ! $instances[$class]) {
				Log::error('Unable to find '.$class.' in instances.');
				return false;
			}
		}
		return $instances;
	}

	private function get_classes() {
		return array_keys(self::$fields_per_class);
	}

	private function remove_class($class) {
		$values = $this->get_values($class);
		unset(self::$fields_per_class[$class]);
		return $values;
	}

	private function get_values($class) {
		return self::$fields_per_class[$class];
	}

	/**
	 * Save a field value to a persistent storage between call to
	 * the class so the parent can then save everything in the
	 * database.
	 * The value is retrieved with the get_field_value method.
	 *
	 * @param string $field the field name
	 * @param null|string $class the class to save the value for, if not set, the current ont
	 */
	private function save_field_value($field, $class = null) {
		if(is_null($class))
			$class = $this->clazz();

		if(! isset(self::$fields_per_class[$class]))
			self::$fields_per_class[$class] = array();

		self::$fields_per_class[$class][$field] = $this->get_field_value($field);
	}

	/**
	 * Retrieve the value for the given field from the POST data, the default value passed as a parameter
	 * or the default values set on the class as a last resort.
	 *
	 * @param string $field field name
	 * @return string the value
	 */
	private function get_field_value($field) {
		$name = $this->field_name($field);
		$default = Arr::get($this->static_variable('_defaults'), $field, null);
		$value = Input::post($name, Arr::get($this->data, $name, $default));
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
		foreach(static::$_reference_many as $model => $this->data) {
			if(! isset($references[$model]) || ! is_array($references[$model]))
				continue;

			$values = array();
			foreach($references[$model] as $id)
				$values[] = '('.$this->pk().', '.$id.') ';

			$sql .= 'DELETE FROM '.$this->data['table'].' WHERE '.$this->data['lk'].' = '.$this->pk().'; ';
			$sql .= 'INSERT INTO '.$this->data['table'].' ('.$this->data['lk'].', '.$this->data['fk'].') VALUES '.implode(', ', $values).'; ';
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
	private function _save_reference_by($validate, array &$instances) {
		$result = 0;

		if(! isset(static::$_referenced_by))
			return $result;

		foreach(static::$_referenced_by as $class => $fk) {
			if(! isset($instances[$class]))
				continue;

			$instances[$class]->{$fk} = $this->{$this->primary_key()};
			if(($r_temp = $instances[$class]->_do_save($validate, $instances, false)) === false)
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
			if($result === false)
				return false;
		} else
			$result = 0;

		$result_tmp = parent::save($validate);
		if($result_tmp === false)
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
	 *        false if one of the value is false
	 *        On UPDATE : number of affected rows
	 *        On INSERT : array(0 => autoincrement id, 1 => number of affected rows)
	 */
	private static function _combine_save_results($one, $two) {
		if($one === false || $two === false)
			return false;
		elseif(is_array($one) && is_array($two)) // both are an array
			return array($one[0], $one[1] + $two[1]); elseif(! is_array($two)) // only $one is an array
			return array($one[0], $one[1] + $two); elseif(! is_array($one)) // only $two is an array
			return array($two[0], $one + $two[1]); else
			return $one + $two;
	}

	/**
	 * Save the given instances
	 *
	 * @param Model_Base[] $instances Array of instances of various referenced models with the form 'Model_Name' => instance
	 * @param array $references list of ids for the many to many references
	 * @throws \Exception
	 * @return array|int|bool
	 *        false if the validation failed
	 *        On UPDATE : number of affected rows
	 *        On INSERT : array(0 => autoincrement id, 1 => number of affected rows)
	 */
	public function save(array $instances, array $references) {
		// disable transaction mode if we are already in one
		$transaction = ! Database_Connection::instance(null)->in_transaction();

		if($transaction)
			DB::start_transaction();

		try {
			throw new \RuntimeException('Implement this !');
			$status = self::_do_save($validate, $instances);
		} catch(\Exception $e) {
			Log::error('['.get_called_class().'] Rollback transaction');
			DB::rollback_transaction();
			throw $e;
		}

		if($transaction)
			DB::commit_transaction();
		return $status;
	}
}