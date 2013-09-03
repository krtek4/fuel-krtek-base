<?php

namespace KrtekBase;

use Fuel\Core\DB;
use \Fuel\Core\Lang;
use \Fuel\Core\Inflector;
use \Fuel\Core\ViewModel;
use \Fuel\Core\Response;

/**
 * Base CRUD controller class which implements various method to help creating
 * CRUD controllers.
 *
 * @package krtek-Base
 * @category Crud
 * @author Gilles Meier <krtek4@gmail.com>
 * @version 1.0
 * @license Affero GPLv3 http://www.gnu.org/licenses/agpl-3.0-standalone.html
 * @copyright 2012 Gilles Meier <krtek4@gmail.com>
 * @link https://github.com/krtek4/fuel-krtek-base
 */
abstract class Controller_Crud extends Controller_Base {
	/**
	 * @var string Name of the model this class refers to
	 */
	protected static $_model = null;

	/**
	 * @var array list of model classes to preload to the cache
	 */
	protected static $_preload = array();

	/**
	 * @var string Name to use for instance variable, page title (pluralized), view name
	 */
	protected static $_friendly_name = null;

	/**
	 * @var string default action for this controller
	 */
	public $default_action = 'listing';

	/**
	 * @var array controller's contextual navigation
	 */
	private static $nav = array();

	/**
	 * Load the lang data and try to set $_model and $_friendly_name based on
	 * the controller's name if they are not set.
	 */
	public static function _init() {
		Lang::load('controller_crud', 'controller_crud');

		if(is_null(static::$_friendly_name))
			static::$_friendly_name = static::_class_to_name();
		if(is_null(static::$_model))
			static::$_model = 'Model_'.  ucfirst(static::$_friendly_name);

		Krtek_Cache::preload(static::$_preload);
	}

	/**
	 * Transform the class name to a usable friendly name for this class.
	 *
	 * @return string the friendly name to use if none is set.
	 */
	protected static function _class_to_name() {
		return Inflector::singularize(strtolower(str_replace('Controller_', '', get_called_class())));
	}

	/**
	 * Return a text from the lang file.
	 *
	 * @param string $name name of the key
	 * @return string the text corresponding to the key
	 */
	private static function get_message($name) {
		return Lang::get('controller_crud.'.$name);
	}

	/**
	 * @return string name of the view to use based on the friendly_name
	 */
	private static function _view() {
		return Inflector::pluralize(static::$_friendly_name);
	}

	/**
	 * @return string title of the page based on the friendly_name
	 */
	private static function _title() {
		return self::_view();
	}

	/**
	 * @param bool $plural do we have to pluralize the name ?
	 * @return string name of the instance variable based on the friendly_name
	 */
	private static function _var_name($plural = false) {
		$name = static::$_friendly_name;
		if($plural)
			$name = Inflector::pluralize($name);
		return $name;
	}

	/**
	 * @return string The base url for all internal urls
	 */
	private function _base_url() {
		return static::_url_prefix().'/'.static::_view();
	}

	/**
	 * @return string The prefix to add before all internal urls.
	 */
	protected function _url_prefix() { return ''; }

	/**
	 * @param string $action the action to point to
	 * @param array $param parameters to add to the url
	 * @return string url internal to the class linking to the given action
	 */
	protected function _internal_url($action = null, $param = array()) {
		if(is_null($action))
			$action = $this->default_action;
		return static::_base_url().'/'.$action.'/'.implode('/', $param);
	}

	/**
	 * Add an entry to the page contextual navigation
	 *
	 * @param string $url url to direct to
	 * @param string $title title of the link
	 * @param array $attr HTML attributes to set on the link
	 */
	protected static function nav($url, $title, $attr = array()) {
		self::$nav[] = array('url' => $url, 'title' => $title, 'attr' => $attr);
	}

	/**
	 * Navigation to show every time
	 */
	protected function global_nav() {
		static::nav($this->_internal_url('listing'), static::get_message('list'), array('class' => 'text_icon list'));
		static::nav($this->_internal_url('search'), static::get_message('search'), array('class' => 'text_icon search'));
		static::nav($this->_internal_url('add'), static::get_message('add'), array('class' => 'text_icon add'));
	}

	/**
	 * Navigation specific to a particular instance of the related model.
	 *
	 * @param int $id the id of the specific instance
	 */
	protected function specific_nav($id) {
		static::nav($this->_internal_url('view', array($id)), static::get_message('view'), array('class' => 'text_icon detail'));
		static::nav($this->_internal_url('edit', array($id)), static::get_message('edit'), array('class' => 'text_icon edit'));
	}

	/**
	 * @return array the contextual navigation
	 */
	public static function get_nav() { return self::$nav; }

	/**
	 * Helper to create the view model and set the content of this controller.
	 *
	 * @param string $action The name of the action to pass to the view model
	 * @param string $instance The variable to set on the view model (with _var_name)
	 * @param bool $plural whether we have one more more instances
	 * @return mixed content
	 */
	protected function _content($action, $instance = null, $plural = false) {
		try {
			$vm = ViewModel::forge(self::_view(), $action);
		} catch(\OutOfBoundsException $e) {
			$vm = ViewModel::forge('Crud', $action);
		}
		if(! is_null($instance)) {
			$vm->set(self::_var_name($plural), $instance, false);
		}
		$vm->set('controller', static::_view());
		$vm->set('model', static::$_model);
		$vm->set('var_name', static::_var_name());
		$vm->set('base_url', static::_base_url());
		return $this->content($vm, self::_title());
	}

	/**
	 * Return a specific instance of the model associated with this controller.
	 * A 404 error is raised if the instance isn't found.
	 *
	 * @param int $id primary key to retrieve
	 * @return Model_Base an instance of the model.
	 */
	protected function _instance($id) {
		$model_name = static::$_model;
		return $model_name::e_find($id);
	}

	/**
	 * Retrieve all the rows from the database corresponding to this controller
	 * model.
	 *
	 * @return array all the instances of the model.
	 */
	protected function _instances() {
		$model_name = static::$_model;
		return $model_name::find_all();
	}

	/**
	 * Create a new empty instance of the model.
	 *
	 * @return Model_Base an instance of the model.
	 */
	protected function _new_instance() {
		$model_name = static::$_model;
		return $model_name::forge();
	}

	/**
	 * Get the numth parameter of the action or null if not set
	 *
	 * @param int $num
	 * @return mixed
	 */
	protected function get_param($num) {
		if(count($this->request->method_params) == 2 && count($this->request->method_params[1]) > $num)
			return $this->request->method_params[1][$num];
		return null;
	}

	protected function message($class, $msg) {
		if(\Fuel\Core\Package::loaded('messages'))
			\Messages\Messages::instance()->message($class, $msg);
	}

	/**
	 * Set the contextual navigation of this controller
	 */
	public function before() {
		$this->global_nav();
		parent::before();
	}

	/**
	 * List all the instances of the related model
	 */
	public function action_listing() {
		$this->_content('listing', $this->_instances(), true);
	}

	/**
	 * Search interface (not implemented)
	 */
	public function action_search() {
		$this->_content('search');
	}

	/**
	 * Show the details of a specific instance of the related model.
	 *
	 * @param int $id the specific instance id
	 */
	public function action_view($id = -1) {
		$this->specific_nav($id);
		$this->_content('view', $this->_instance($id));
	}

	/**
	 * Show a form for adding a new instance of the related model
	 * Redirect to the listing if successful addition.
	 */
	public function action_add() {
		if($this->fieldset_process_result()) {
			$this->message('success', self::get_message('add_success'));
			Response::redirect($this->_internal_url());
		}

		$this->_content('form', $this->_new_instance());
	}

	/**
	 * Show a form for editing a particular instance of the related model
	 * Redirect to the listing if successful addition.
	 *
	 * @param int $id the specific instance id
	 */
	public function action_edit($id = -1) {
		if($this->fieldset_process_result()) {
			$this->message('success', self::get_message('edit_success'));
			Response::redirect($this->_internal_url());
		}

		$this->specific_nav($id);
		$this->_content('form', $this->_instance($id));
	}

	/**
	 * Delete a particular instance of the related model.
	 * Redirect to the listing afterward.
	 *
	 * @param int $id the specific instance id
	 */
	public function action_delete($id = -1) {
		try {
			$status = $this->_instance($id)->delete();
		} catch(\Exception $e) {
			$info = DB::error_info();
			$this->message('error', self::get_message('mysql_error_'.$info[1]));
			$status = false;
		}

		if($status)
			$this->message('success', self::get_message('delete_success'));
		else
			$this->message('error', self::get_message('delete_error'));

		Response::redirect($this->_internal_url());
	}
}

?>
