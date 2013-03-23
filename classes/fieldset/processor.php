<?php

namespace KrtekBase\Fieldset;

use Fuel\Core\Fieldset;

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
		if(! Input::method('POST') && empty($data))
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

		/** @var $instances Model_Base[] */
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




}