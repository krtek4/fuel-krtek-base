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
class Fieldset_Generator extends Fieldset_Holder {
	/** @var $instances Fieldset_Generator[] */
	static private $instances = array();

	protected function __construct($definition, $class, array $config) {
		parent::__construct(Fieldset::forge($class.'_'.$definition, $config));

		$this->hidden('_fieldset_name', $definition);
		$this->hidden('_fieldset_model', $class);

		Fieldset_Parser::process($this->fieldset(), $definition, $class);
	}

	/**
	 * Create a Fieldset based on the definition corresponding to
	 * the given name. If the fieldset was already created, return
	 * this particular instance.
	 *
	 * Add some custom hidden fields to the fieldset so we are latter
	 * able to process data automatically through the other method
	 * proposed by this class.
	 *
	 * @param string $name Definition name
	 * @param string $class Model class name
	 * @param array $config The config for this fieldset (only used upon creation)
	 * @return Fieldset the generated fieldset
	 */
	public static function forge($name, $class, array $config = array()) {
		if(! isset(static::$instances[$name])) {
			static::$instances[$name] = new static($name, $class, $config);
		}
		return static::$instances[$name]->fieldset();
	}
}