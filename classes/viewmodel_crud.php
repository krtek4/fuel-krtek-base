<?php

namespace Base;

/**
 * Base CRUD ViewModel class
 *
 * @package krtek-Base
 * @category Crud
 * @author Gilles Meier <krtek4@gmail.com>
 * @version 1.0
 * @license Affero GPLv3 http://www.gnu.org/licenses/agpl-3.0-standalone.html
 * @copyright 2012 Gilles Meier <krtek4@gmail.com>
 * @link https://github.com/krtek4/fuel-krtek-base
 */
class View_Crud extends ViewModel_Base {
	public function render() {
		if(get_called_class() == 'Base\\View_Crud') {
			$var_name = $this->var_name;
			if(! isset($this->{$var_name}))
				$var_name = \Fuel\Core\Inflector::pluralize($var_name);
			if(isset($this->{$var_name}))
				$this->data = $this->{$var_name};
		}

		return parent::render();
	}

	public function form() {
		$model_name = $this->model;
		$var_name = $this->var_name;

		$this->form = $model_name::fieldset('admin')->repopulate();
		$this->{$var_name}->populate($this->form);
	}
}