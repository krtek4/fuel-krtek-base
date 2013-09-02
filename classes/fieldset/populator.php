<?php

namespace KrtekBase\Fieldset;

use Fuel\Core\DB;
use Fuel\Core\Fieldset;
use Fuel\Core\Fieldset_Field;
use Fuel\Core\Input;
use KrtekBase\Krtek_Cache;
use KrtekBase\Model_Base;

/**
 * This class is responsible for the population of the various
 * fields present on a Fieldset.
 *
 * The population is made respecting the following priorities :
 *
 * 1° Input data
 * 2° Actual value of the instance
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
	/** @var array Hold informations about which id / class combination has already been populated */
	static private $alreadyPopulated = array();

	/**
	 * Populate the given fieldset with values from the Input data
	 * and the instance
	 *
	 * @param $instance Model_Base
	 * @param $fieldset Fieldset
	 * @param $definition string
	 * @param $class string
	 * @param $hierarchy string
	 * @param bool $with_reference
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
	 * @param Fieldset_Field $field
	 * @param $value
	 */
	protected function set_value(Fieldset_Field $field, $value) {
		switch($field->get_attribute('type')) {
			case 'date':
				$value = date('Y-m-d', strtotime($value));
				break;
			case 'datetime':
				$value = date('Y-m-d H:m:s', strtotime($value));
				break;
		}
		$field->set_value($value, true);
	}

	/**
	 * Populate known fields of the fieldset with value from this
	 * model instance.
	 *
	 * @param bool $with_references Also get references and populate their fields as well
	 * @return Fieldset return the $fieldset to allow chaining
	 */
	protected function do_populate($with_references) {
		if($this->instance()->pk()) {
			$name = $this->field_name($this->instance()->primary_column());
			$this->hidden($name, $this->instance()->pk());
		}

		foreach($this->instance()->to_array() as $name => $value) {
			$field_name = $this->field_name($name);
			$field = $this->fieldset()->field($field_name);
			if($field)
				$this->set_value($field, Input::post($field_name, $value));
		}

		if($with_references) {
			$this->populate_reference_one();
			$this->populate_referenced_by();
			$this->populate_reference_many();
		}

		return $this->fieldset();
	}

	/**
	 * @param string $class
	 * @param string $pk
	 * @return bool was this id / class combination already populated ?
	 */
	protected function alreadyPopulated($class, $pk) {
		return isset(self::$alreadyPopulated[$class][$pk]);
	}

	/**
	 * Set this particular id / class combination as populated
	 * @param string $class
	 * @param string $pk
	 */
	protected function setPopulated($class, $pk) {
		self::$alreadyPopulated[$class][$pk] = true;
	}

	/**
	 * Populate fields from referenced model (one)
	 */
	protected function populate_reference_one() {
		foreach($this->static_variable('_reference_one') as $class => $fk)
			if(isset($this->instance()->{$fk})) {
				$reference = call_user_func_array(array($class, 'find_by_pk'), array($this->instance()->{$fk}));
				if($reference && ! $this->alreadyPopulated($class, $reference->pk())) {
					$this->setPopulated($class, $reference->pk());
					Fieldset_Populator::populate($reference, $this->fieldset(), $this->definition(), $class, $this->updated_hierarchy());
				}
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
				if(! $this->alreadyPopulated($class, $referenced->pk())) {
					$this->setPopulated($class, $referenced->pk());
					Fieldset_Populator::populate($referenced, $this->fieldset(), $this->definition(), $class, $this->updated_hierarchy());
				}
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
					$this->set_value($field, Input::post($field_name, $ids));
				}
	}
}