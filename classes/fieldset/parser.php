<?php

namespace KrtekBase\Fieldset;

use Fuel\Core\Fieldset;
use KrtekBase\Model_Exception;

class Fieldset_Parser extends Fieldset_Holder {
	/**
	 * Process a fieldset definition and add the fields
	 * to the given Fieldset.
	 *
	 * @param $fieldset Fieldset
	 * @param $definition string
	 * @param $class string
	 * @param $hierarchy string
	 */
	public static function process($fieldset, $definition, $class, $hierarchy = null) {
		$parser = new Fieldset_Parser($fieldset, $definition, $class, $hierarchy);
		$parser->process_definition();
	}

	/** @var $class string the related model class name */
	private $class;
	/** @var $definition string the definition to use */
	private $definition;
	/** @var $hierarchy string current hierarchy */
	private $hierarchy;

	protected function __construct($fieldset, $definition, $class, $hierarchy) {
		parent::__construct($fieldset);
		$this->definition = $definition;
		$this->class = $class;
		$this->hierarchy = $hierarchy;
	}

	/**
	 * Return the fieldset definition or throw
	 * exception if not found or invalid.
	 *
	 * @throws Model_Exception when fieldset not found or invalid
	 * @return array fieldset definition
	 */
	protected function definition() {
		$fieldsets = $this->static_variable('_fieldsets');
		if(! array_key_exists($this->definition, $fieldsets))
			throw new Model_Exception("Unknown fieldset name : ".$this->class.'->'.$this->definition);

		if(! is_array($fieldsets[$this->definition]))
			throw new Model_Exception("Invalid fieldset definition : ".$this->class.'->'.$this->definition);

		return $fieldsets[$this->definition];
	}

	/**
	 * @param $name
	 * @throws Model_Exception
	 * @return string
	 */
	protected function label($name) {
		$label = call_user_func_array(array($this->class, '_labels'), array($name, $this->definition));
		if(! $label)
			throw new Model_Exception ('No label found for '.$name);
		return $label;
	}
	/**
	 * @param $name
	 * @return array
	 */
	protected function rules($name) { return call_user_func_array(array($this->class, '_rules'), array($name, $this->definition)); }
	/**
	 * @param $name
	 * @return array
	 */
	protected function attributes($name) { return call_user_func_array(array($this->class, '_attributes'), array($name, $this->definition));
	}

	/**
	 * @param $name string
	 * @return mixed
	 */
	protected function static_variable($name) {
		$class = $this->class;
		return $class::$name;
	}

	/**
	 * Compute the name to use for a field in a fieldset.
	 *
	 * @param string $name name of the field
	 * @return string the name of the field for the fieldset
	 */
	protected function field_name($name) {
		return $this->hierarchy.$this->static_variable('_table_name').'-'.$name;
	}

	/**
	 * Add the current table_name to the actual hierarchy and
	 * return the new value. Must be called each time a processing class
	 * pass the relay to a child Model.
	 *
	 * @return string new hierarchy string to pass a child.
	 */
	public function update_hierarchy() {
		return $this->hierarchy.$this->static_variable('_table_name').'-';
	}

	/**
	 * Process a fieldset definition by adding each found field in the
	 * specified definition to the Fieldset instance. Each individual
	 * field is processed by the _process_field method.
	 */
	protected function process_definition() {
		foreach($this->definition() as $field)
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
				Fieldset_Parser::process($this->fieldset(), $info[1], $this->class, $this->hierarchy);
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
				Fieldset_Parser::process($this->fieldset(), $info[1], $info[0], $this->update_hierarchy());
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
		$field = $this->field($this->field_name($name), $this->label($name), $this->rules($name));

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
				} else if(substr($field, - 3) == '_id') {
					$callback = array('Model_'.ucfirst(substr($field, 0, - 3)), 'find_all');
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