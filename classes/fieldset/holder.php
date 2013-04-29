<?php

namespace KrtekBase\Fieldset;

use Fuel\Core\Fieldset;
use Fuel\Core\FuelException;

class Fieldset_Exception extends FuelException { }

/**
 * Hold a Fieldset and the various related information (model class,
 * definition, current hierarchy). Provides various utility methods
 * to get information on the class name or the fieldset.
 *
 * @package krtek-Base
 * @category BaseClasses
 * @author Gilles Meier <krtek4@gmail.com>
 * @version 1.0
 * @license Affero GPLv3 http://www.gnu.org/licenses/agpl-3.0-standalone.html
 * @copyright 2012 Gilles Meier <krtek4@gmail.com>
 * @link https://github.com/krtek4/fuel-krtek-base
 */
class Fieldset_Holder {
	/** @var $fieldset Fieldset */
	private $fieldset;
	/** @var $class string the related model class name */
	private $class;
	/** @var $definition string the definition to use */
	private $definition;
	/** @var $hierarchy string current hierarchy */
	private $hierarchy;

	protected function __construct($fieldset, $definition, $class, $hierarchy) {
		if(! $fieldset instanceof Fieldset)
			throw new Fieldset_Exception("Must be an instance of Fieldset.");

		if(strlen($definition) == 0)
			throw new Fieldset_Exception("Must give a definition name.");

		if(strlen($class) == 0)
			throw new Fieldset_Exception("Must give a model class name.");

		$this->fieldset = $fieldset;
		$this->definition = $definition;
		$this->class = $class;
		$this->hierarchy = $hierarchy;
	}

	/**
	 * @return Fieldset
	 */
	public function fieldset() { return $this->fieldset; }

	/**
	 * @return string
	 */
	public function clazz() { return $this->class; }

	/**
	 * @return string
	 */
	public function definition() { return $this->definition; }

	/**
	 * @return string
	 */
	public function hierarchy() { return $this->hierarchy;
	}

	/**
	 * Compute the name to use for a field in a fieldset.
	 *
	 * @param string $name name of the field
	 * @return string the name of the field for the fieldset
	 */
	protected function field_name($name) {
		return $this->hierarchy().$this->static_variable('_table_name').'-'.$name;
	}

	/**
	 * @param $name
	 * @throws Fieldset_Exception
	 * @return string
	 */
	protected function label($name) {
		$label = call_user_func_array(array($this->clazz(), '_labels'), array($name, $this->definition()));
		if(! $label)
			throw new Fieldset_Exception ('No label found for '.$name);
		return $label;
	}

	/**
	 * @param $name
	 * @return array
	 */
	protected function rules($name) {
		return call_user_func_array(array($this->clazz(), '_rules'), array($name, $this->definition()));
	}

	/**
	 * @param $name
	 * @return array
	 */
	protected function attributes($name) {
		return call_user_func_array(array($this->clazz(), '_attributes'), array($name, $this->definition()));
	}

	/**
	 * @param $name string
	 * @return mixed
	 */
	protected function static_variable($name) {
		return call_user_func_array(array($this->clazz(), 'get_meta'), array($name));
	}

	/**
	 * Add the current table_name to the actual hierarchy and
	 * return the new value. Must be called each time a processing class
	 * pass the relay to a child Model.
	 *
	 * @return string new hierarchy string to pass a child.
	 */
	public function updated_hierarchy() {
		return $this->hierarchy().$this->static_variable('_table_name').'-';
	}

	/**
	 * Add a hidden field to the fieldset.
	 *
	 * @param $name string
	 * @param $value string
	 * @return \Fuel\Core\Fieldset_Field
	 */
	protected function hidden($name, $value) {
		return $this->fieldset->add(array('name' => $name, 'value' => $value, 'type' => 'hidden'));
	}

	/**
	 * @param $name
	 * @param $label
	 * @param $rules
	 * @return \Fuel\Core\Fieldset_Field
	 */
	protected function field($name, $label, $rules) {
		return $this->fieldset()->validation()->add_field($name, $label, $rules);
	}
}
