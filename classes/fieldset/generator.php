<?php

namespace KrtekBase\Fieldset;

use Fuel\Core\Fieldset;
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
class Fieldset_Generator extends Fieldset_Holder {
	/** @var $instances Fieldset_Generator[] */
	static private $instances = array();

	/**
	 * Create a Fieldset based on the definition corresponding to
	 * the given name. If the fieldset was already created, return
	 * this particular instance.
	 *
	 * Add some custom hidden fields to the fieldset so we are latter
	 * able to process data automatically through the other method
	 * proposed by this class.
	 *
	 * @param string $definition Definition name
	 * @param string $class Model class name
	 * @param array $config The config for this fieldset (only used upon creation)
	 * @return Fieldset_Generator the new fieldset generator
	 */
	public static function forge($definition, $class, array $config = array()) {
		$name = $class.'_'.$definition;
		if(! isset(static::$instances[$name])) {
			static::$instances[$name] = new static($name, $definition, $class, $config);
		}
		return static::$instances[$name];
	}

	private $parsed = false;

	protected function __construct($name, $definition, $class, array $config) {
		parent::__construct(Fieldset::forge($name, $config), $definition, $class, '');
	}


	public function parse() {
		$this->parsed = true;

		$this->hidden('_fieldset_name', $this->definition());
		$this->hidden('_fieldset_model', $this->clazz());

		Fieldset_Parser::parse($this->fieldset(), $this->definition(), $this->clazz());
	}

	public function process() {
	}

	public function populate(Model_Base $instance, $with_reference = true) {
		if(! $this->parsed)
			$this->parse();

		Fieldset_Populator::populate($instance, $this->fieldset(), $this->definition(), $this->clazz());
	}
}