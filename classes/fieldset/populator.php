<?php

namespace KrtekBase\Fieldset;

use Fuel\Core\DB;
use Fuel\Core\Fieldset;
use Fuel\Core\Input;
use KrtekBase\Krtek_Cache;
use KrtekBase\Model_Base;

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
class Fieldset_Populator extends Fieldset_Holder {
	/**
	 * Process a fieldset definition and add the fields
	 * to the given Fieldset.
	 *
	 * @param $instance Model_Base
	 * @param $fieldset Fieldset
	 * @param $definition string
	 * @param $class string
	 * @param $hierarchy string
	 */
	public static function populate($instance, $fieldset, $definition, $class, $hierarchy = null, $with_reference = true) {
		$populator = new Fieldset_Populator($instance, $fieldset, $definition, $class, $hierarchy);
		$populator->do_populate($with_reference);
	}

	/** @var $instance Model_Base */
	private $instance;

	protected function __construct($instance, $fieldset, $definition, $class, $hierarchy) {
		parent::__construct($fieldset, $definition, $class, $hierarchy);
		$this->instance = $instance;
	}

	/**
	 * @return Model_Base
	 */
	protected function instance() { return $this->instance; }

	/**
	 * Populate known fields of the fieldset with value from this
	 * model instance.
	 *
	 * @param bool $with_references Also get references and populate their fields as well
	 * @return Fieldset return the $fieldset to allow chaining
	 */
	protected function do_populate($with_references) {
		if($this->instance()->pk()) {
			// TODO: find a proper way to get the column name for the PK
			$name = $this->field_name('id');
			$this->hidden($name, $this->instance()->pk());
		}

		foreach($this->instance()->to_array() as $name => $value) {
			$field_name = $this->field_name($name);
			$field = $this->fieldset()->field($field_name);
			if($field)
				$field->set_value(Input::post($field_name, $value), true);
		}

		if($with_references) {
			$this->populate_reference_one();
			$this->populate_referenced_by();
			$this->populate_reference_many();
		}

		return $this->fieldset();
	}

	/**
	 * Populate fields from referenced model (one)
	 */
	protected function populate_reference_one() {
		foreach($this->static_variable('_reference_one') as $class => $fk)
			if(isset($this->instance()->{$fk})) {
				$reference = call_user_func_array(array($class, 'find_by_pk'), array($this->instance()->{$fk}));
				if($reference)
					Fieldset_Populator::populate($reference, $this->fieldset(), $this->definition(), $class, $this->updated_hierarchy(), false);
			}
	}

	/**
	 * Populate fields from model that reference this one
	 */
	protected function populate_referenced_by() {
		foreach($this->static_variable('_referenced_by') as $class => $fk) {
			if($this->instance()->pk()) {
				$referenced = call_user_func_array(array($class, 'find_by'), array($fk, $this->instance()->pk()));
				if(! $referenced) {
					// if no referenced found, forge on so it will be saved with the fk set to this instance pk.
					$referenced = call_user_func_array(array($class, 'forge'), array(array($fk => $this->instance()->pk())));
				} else if(is_array($referenced)) {
					$referenced = current($referenced);
				}
				Fieldset_Populator::populate($referenced, $this->fieldset(), $this->definition(), $class, $this->updated_hierarchy(), false);
			}
		}
	}

	/**
	 * Populate fields from references models (many)
	 */
	protected function populate_reference_many() {
		foreach($this->static_variable('_reference_many') as $class => $data)
			if($this->instance()->pk()) {
				$instances = call_user_func_array(array($class, 'find_many_by'), array($data['lk'], $this->instance()->pk()));
				$ids = array_map(function(Model_Base $m) { return $m->pk(); }, $instances);

				$field_name = $this->field_name($data['fk']);
				$field = $this->fieldset()->field($field_name);
				if($field)
					$field->set_value(Input::post($field_name, $ids), true);
				}
	}
}