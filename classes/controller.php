<?php

namespace KrtekBase;

use Fuel\Core\Controller_Hybrid;
use Fuel\Core\Input;
use Fuel\Core\Package;

/**
 * Base class for each controller.
 *
 * @package krtek-Base
 * @category BaseClasses
 * @author Gilles Meier <krtek4@gmail.com>
 * @version 1.0
 * @license Affero GPLv3 http://www.gnu.org/licenses/agpl-3.0-standalone.html
 * @copyright 2012 Gilles Meier <krtek4@gmail.com>
 * @link https://github.com/krtek4/fuel-krtek-base
 */
abstract class Controller_Base extends Controller_Hybrid {
	/**
	 * @var bool if null no fieldset were processed, otherwise the result of the process.
	 */
	private $_fieldset_processed = null;

	/**
	 * @var bool Should the fieldset be auto processed
	 */
	protected $auto_process = true;

	/**
	 * Do some ACL checking based on whitelisting (see chauveauth in config)
	 * Process a fieldset if POST information were sent.
	 */
	public function before() {
		if(! Acl::controller_access($this->request->controller, $this->request->action))
			throw new HttpForbiddenException();

		parent::before();

		if(Input::method() == 'POST' && $this->auto_process)
			$this->process_fieldset();
	}

	/**
	 * Process the fieldset if one is present.
	 * @return Model_Base|bool result of the fieldset process.
	 */
	final protected function process_fieldset() {
		$this->_fieldset_processed = Model_Base::process_fieldset();
		if(! $this->_fieldset_processed && Package::loaded('messages'))
				\Messages\Messages::instance()->message('error', 'Veuillez vérifier les informations fournies.');
		return $this->fieldset_process_result();
	}

	/**
	 * @return bool Was a fieldset processed or not ?
	 */
	final protected function was_fieldset_processed() {
		return ! is_null($this->_fieldset_processed);
	}

	/**
	 * @return Model_Base|bool result of the fieldset process.
	 */
	final protected function fieldset_process_result() {
		return is_null($this->_fieldset_processed) ? false : $this->_fieldset_processed;
	}

	/**
	 * @return bool true if this is an AJAX request
	 */
	final protected function is_ajax() {
		return Input::is_ajax() && ! $this->is_pjax();
	}

	/**
	 * @return bool true if this is a PJAX request
	 */
	final protected function is_pjax() {
		return Input::server('HTTP_X_PJAX', 'false') === 'true';
	}

	/**
	 * @return bool true if this is an HMVC request
	 */
	final protected function is_hmvc() {
		return ! is_null($this->request->parent());
	}

	/**
	 * @return bool true if the template must be used
	 */
	final protected function is_templated() {
		return ! ($this->is_ajax() || $this->is_hmvc());
	}

	public function is_restful() {
		return $this->is_ajax();
	}

	/**
	 * Set the content and title of the response
	 * @param mixed $content The content
	 * @param mixed $title The title, not used if the response isn't templated
	 * @throws \RuntimeException when the title isn't set
	 */
	final protected function content($content, $title = null) {
		if(! $this->is_templated())
			$this->template = $content;
		else {
			if(is_null($title))
				throw new \RuntimeException("Title must be set !");

			$this->template->title = $title;
			$this->template->content = $content;
		}
	}
}

?>
