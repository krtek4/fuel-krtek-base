<?php

namespace KrtekBase\Fieldset;

use Fuel\Core\Fieldset;
use Fuel\Core\Input;
use KrtekBase\Model_Base;

/**
 * Generate fieldsets based on meta-information defined on the
 * model class.
 *
 * Also provides various utility methods to access other Fieldset
 * related classes without having to call them directly.
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

	/**
	 * Return a fieldset generator based on data present in the Input.
	 *
	 * @param array $config
	 * @return Fieldset_Generator
	 * @throws Fieldset_Exception
	 */
	public static function from_fields(array $config = array()) {
		$definition = Input::post('_fieldset_name', null);
		$class = Input::post('_fieldset_model', null);

		if(is_null($definition) || is_null($class)) {
			throw new Fieldset_Exception("Unable to gather sufficient data from input.");
		}

		return static::forge($definition, $class, $config);
	}

	private $parsed = false;

	protected function __construct($name, $definition, $class, array $config) {
		parent::__construct(Fieldset::forge($name, $config), $definition, $class, '');
	}

	/**
	 * Generate the Fieldset using the Fieldset_Parser
	 */
	public function parse() {
		if($this->parsed)
			return;

		$this->parsed = true;

		$this->hidden('_fieldset_name', $this->definition());
		$this->hidden('_fieldset_model', $this->clazz());

		Fieldset_Parser::parse($this->fieldset(), $this->definition(), $this->clazz());
	}

	/**
	 * Process (ie retrieve information in the input and save it) the fieldset using
	 * Fieldset_Processor
	 *
	 * @param array $data default data to use if nothings found in the input or the object
	 * @return Model_Base|bool The created / updated model or false if an error occurred
	 */
	public function process($data = array()) {
		if(! $this->parsed)
			$this->parse();

		return Fieldset_Processor::process($this->fieldset(), $this->definition(), $this->clazz(), null, $data);
	}

	/**
	 * Populate the fieldset using Fieldset_Populator
	 *
	 * @param Model_Base $instance
	 * @param bool $with_reference
	 */
	public function populate(Model_Base $instance, $with_reference = true) {
		if(! $this->parsed)
			$this->parse();

		Fieldset_Populator::populate($instance, $this->fieldset(), $this->definition(), $this->clazz());
	}
}