<?php

namespace Base;

/**
 * Base class for each ViewModel.
 *
 * Automatically set the view name to the called method name (ie 'method.mustache').
 * Automatically set the view folder based on the class name.
 * Provide some calling magic to call the asked method only if it exists and do nothing otherwise.
 *
 * @package krtek-Base
 * @category BaseClasses
 * @author Gilles Meier <krtek4@gmail.com>
 * @version 1.0
 * @license Affero GPLv3 http://www.gnu.org/licenses/agpl-3.0-standalone.html
 * @copyright 2012 Gilles Meier <krtek4@gmail.com>
 * @link https://github.com/krtek4/fuel-krtek-base
 */
abstract class ViewModel_Base extends \ViewModel {
	/**
	 * @var string format of the template to use (by default mustache)
	 */
	static protected $_format = 'mustache';
	/**
	 * @var string folder inside the app "views" folder which contains the views for this ViewModel
	 */
	private $_view_folder = null;

	/**
	 * Compute the _view_folder based on the class name (class name without the leading View_ is used).
	 * Set the method sooner than the parent controller to correctly set the view location.
	 */
	protected function __construct($method, $auto_filter = null) {
		if(is_null($this->_view_folder))
			$this->_view_folder = strtolower(str_replace('View_', '', get_called_class()));

		// registring method before it's done in the parent constructor
		// because set_view is called before and we need the method name.
		$this->_method = $method;
		return parent::__construct($method, $auto_filter);
	}

	/**
	 * Set the view based on the _vied_folder and _method passed.
	 */
	protected function set_view() {
		$this->_view = $this->_view_folder.'/'.$this->_method.'.'.static::$_format;
		return parent::set_view();
	}

	/**
	 * If the called method is the same as the ViewModel asked method, then call it
	 * only if it exists, otherwise do nothing.
	 *
	 * If the called method isn't the ViewModel asked method, raise an exception.
	 *
	 * @param string $name called method
	 * @param array $arguments arguments
	 * @return mixed depends on the method
	 * @exception ErrorException if the called method isn't the method asked to the ViewModel.
	 */
	public function __call($name, $arguments) {
		// only all magic call of the method passed to the ViewModel
		if($name !== $this->_method)
			throw new ErrorException('Call to undefined method '.get_called_class().'::'.$name.'()');
		if(method_exists($this, $name))
				return call_user_func_array(array($this, $name), $arguments);
	}

	/**
	 * Set fields from the model on this ViewModel. If a definition is provided,
	 * only fields from this particular fieldset definition will be set.
	 *
	 * @param Model_Base $model The model to fetch data from
	 * @param string $definition a fieldset definition for filtering.
	 */
	protected function set_data_from_model($model, $definition = null) {
		// FIXME: correctly implements this.
		foreach($model as $key => $value)
			$this->{$key} = $value;
	}
}

?>