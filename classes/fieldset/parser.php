<?php

namespace KrtekBase\Fieldset;

use Fuel\Core\Fieldset;
use KrtekBase\Model_Base;

/**
 * Parse a fieldset definition on a model class and add the various fields
 * to the Fieldset
 *
 * @package krtek-Base
 * @category BaseClasses
 * @author Gilles Meier <krtek4@gmail.com>
 * @version 1.0
 * @license Affero GPLv3 http://www.gnu.org/licenses/agpl-3.0-standalone.html
 * @copyright 2012 Gilles Meier <krtek4@gmail.com>
 * @link https://github.com/krtek4/fuel-krtek-base
 */
class Fieldset_Parser extends Fieldset_Holder {
	/**
	 * Parse a fieldset definition and add the fields
	 * to the given Fieldset.
	 *
	 * @param $fieldset Fieldset
	 * @param $definition string
	 * @param $class string
	 * @param $hierarchy string
	 */
	public static function parse($fieldset, $definition, $class, $hierarchy = null) {
		$parser = new Fieldset_Parser($fieldset, $definition, $class, $hierarchy);
		$parser->parse_definition();
	}

	/**
	 * Process a fieldset definition by adding each found field in the
	 * specified definition to the Fieldset instance. Each individual
	 * field is processed by the _process_field method.
	 */
	protected function parse_definition() {
		foreach($this->fields() as $field)
			$this->process_field($field);
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
	 * @param string $field Field definition
	 */
	protected function process_field($field) {
		$info = explode(':', $field, 2);
		if(count($info) == 1) {
			$this->add_field($field);
			return;
		}

		switch($info[0]) {
			case 'extend':
				// add the fields from this other definition (name is second "parameter")
				Fieldset_Parser::parse($this->fieldset(), $info[1], $this->clazz(), $this->hierarchy());
				break;
			case 'special':
				// add this special field
				$this->add_special_field($info[1]);
				break;
			case 'many':
				// add a field for ids in a many to many relation
				$references = $this->static_variable('_reference_many');
				$field_name = $references[$info[1]]['fk'];
				$this->add_field($field_name);
				break;
			default:
				// first "parameter" is considered like a model class name, second "parameter" is the
				// definition name in this other model class.
				Fieldset_Parser::parse($this->fieldset(), $info[1], $info[0], $this->updated_hierarchy());
				break;
		}
	}

	/**
	 * Add the field with the given name to the fieldset.
	 *
	 * @param $name
	 * @return void
	 */
	protected function add_field($name) {
		$attributes = $this->attributes($name);
		switch($attributes['type']) {
			case 'file':
				$config = $this->fieldset()->get_config('form_attributes', array());
				$config += array('enctype' => 'multipart/form-data');
				$this->fieldset()->set_config('form_attributes', $config);
				break;
			case 'select':
			case 'checkbox':
			case 'radio':
				$callback = null;
				$callback_params = array();

				if(isset($attributes['callback'])) {
					$callback = $attributes['callback'];
					$callback_params = isset($attributes['callback_params']) ? $attributes['callback_params'] : array();
					unset($attributes['callback']);
					unset($attributes['callback_params']);
				} else if(substr($name, - 3) == '_id') {
					$callback = array('Model_'.ucfirst(substr($name, 0, - 3)), 'find_all');
				}

				if(! is_null($callback)) {
					$attributes['options'] = array();
					$rows = call_user_func_array($callback, $callback_params);
					if($rows)
						foreach($rows as $k => $row)
							if(is_object($row))
								$attributes['options'][$row['id']] = $row->select_name();
							else
								$attributes['options'][$k] = $row;
				}
				break;
		}

		$field = $this->field($this->field_name($name), $this->label($name), $this->rules($name));

		// Set certain types through specific setter
		foreach($attributes as $attr => $val)
			if(method_exists($field, $method = 'set_'.$attr)) {
				$field->{$method}($val);
				unset($attributes[$attr]);
			}
		$field->set_attribute($attributes);
	}

	/**
	 * Add the special field with the given name to the fieldset.
	 * There exists three particular field for now : 'submit', 'reset',
	 * 'cancel'. Which add the related button type to the fieldset.
	 *
	 * @param string $name Field name
	 */
	protected function add_special_field($name) {
		$label = $this->label($name);
		$attributes = $this->attributes($name);
		switch($name) {
			case 'cancel':
				$attributes['class'] = isset($attributes['class']) ? $attributes['class'] : '';
				$attributes['class'] .= ' cancel';
				$attributes['type'] = 'reset';
				$attributes['value'] = $label;
				$label = '';
				break;
			case 'submit':
			case 'reset':
				$attributes['type'] = $name;
				$attributes['value'] = $label;
				$label = '';
				break;
		}
		$this->fieldset()->add($this->field_name($name), $label, $attributes);
	}}