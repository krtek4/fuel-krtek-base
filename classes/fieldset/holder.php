<?php

namespace KrtekBase\Fieldset;

use Fuel\Core\Fieldset;

/**
 * Generate fieldsets based on meta-information setted on the
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
class Fieldset_Holder {
	/** @var $fieldset Fieldset */
	private $fieldset;

	protected function __construct($fieldset) { $this->fieldset = $fieldset; }

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


	/**
	 * @return Fieldset
	 */
	public function fieldset() { return $this->fieldset; }
}
