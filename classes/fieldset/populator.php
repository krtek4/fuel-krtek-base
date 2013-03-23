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
class Fieldset_Populator extends Fieldset_Holder {
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

			foreach(static::$_referenced_by as $class => $fk)
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

}