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
				continue;
			}

			$references = $this->static_variable('_reference_many');
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
		$references = call_user_func_array(array($this->clazz(), 'get_meta'), array('_reference_many'));
		foreach($this->get_classes() as $class) {
			// only consider class that are a many to many reference
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
			$pk = call_user_func(array($class, 'primary_key'));

			if(empty($values[$pk])) { // no id -> creation
				unset($values[$pk]);
				$instances[$class] = call_user_func_array(array($class, 'forge'), array($values));
			} else { // or update
				$instances[$class] = call_user_func_array(array($class, 'find_by_pk'), array($values[$pk]));
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
	 * @param string $current_class current class
	 * @param Model_Base[] $instances all instances remaining to save
	 * @param array $references many to many references
	 * @throws Fieldset_Exception
	 * @return Model_Base
	 */
	private function do_save($current_class, array &$instances, array &$references) {
		$current = $instances[$current_class];
		unset($instances[$current_class]);

		// reference one
		$ref = call_user_func_array(array($current, 'get_meta'), array('_reference_one'));
		foreach($ref as $class => $fk) {
			if(! isset($instances[$class]))
				continue;

			$saved = $this->do_save($class, $instances, $references);
			$current->{$fk} = $saved->pk();
		}

		// current
		$current->save();

		// referenced by
		$ref = call_user_func_array(array($current, 'get_meta'), array('_referenced_by'));
		foreach($ref as $class => $fk) {
			if(! isset($instances[$class]))
				continue;

			$instances[$class]->{$fk} = $current->pk();
			$this->do_save($class, $instances, $references);
		}

		// many to many
		$sql = '';
		$ref = call_user_func_array(array($current, 'get_meta'), array('_reference_many'));
		foreach($ref as $model => $data) {
			if(! isset($references[$model]))
				continue;

			$values = array();
			foreach($references[$model] as $id)
				$values[] = '('.$current->pk().' , '.$id.')';

			$sql .= 'DELETE FROM '.$data['table'].' WHERE '.$data['lk'].' = '.$current->pk().'; ';
			$sql .= 'INSERT INTO '.$data['table'].' ('.$data['lk'].', '.$data['fk'].') VALUES '.implode(', ', $values).'; ';
		}
		if(! empty($sql))
			DB::query($sql)->execute();

		return $current;
	}

	/**
	 * Save the given instances
	 *
	 * @param Model_Base[] $instances Array of instances of various referenced models with the form 'Model_Name' => instance
	 * @param array $references list of ids for the many to many references
	 * @throws Fieldset_Exception
	 * @throws \Exception
	 * @return bool
	 */
	public function save(array $instances, array $references) {
		if(! isset($instances[$this->clazz()]))
			throw new Fieldset_Exception("Unable to find instance for current class : ".$this->clazz().".");

		DB::start_transaction();
		try {
			$this->do_save($this->clazz(), $instances, $references);
		} catch(\Exception $e) {
			Log::error('['.get_called_class().'] Rollback transaction');
			DB::rollback_transaction();
			throw $e;
		}
		DB::commit_transaction();

		return true;
	}
}