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
				$this->set('data', $this->format_data($this->{$var_name}), false);
		}

		return parent::render();
	}

	public function form() {
		$model_name = $this->model;
		$var_name = $this->var_name;

		$this->form = $model_name::fieldset('admin')->repopulate();
		$this->{$var_name}->populate($this->form);
	}

	protected function format_data($data) {
		if(is_array($data))
			return $data;

		$model_name = $this->model;
		$ret = '<table class="model_listing">
			<thead>
				<tr><th>Nom</th><th>Valeur</th></tr>
			</thead>
			<tbody>';
		foreach($data as $name => $value) {
			$label = $model_name::_labels($name, 'admin') ?: $name;
			$attributes = $model_name::_attributes($name, 'default') ?: array('type' => 'text');

			switch($attributes['type']) {
				case 'date': $value = date('Y.m.d H:m', strtotime($value));
					break;
				case 'checkbox': $value = $value == 0 ? 'No' : 'Yes';
					break;
				case 'select':
				case 'radio':
					if(isset($attributes['options']) && isset($attributes['options'][$value]))
						$value = $attributes['options'][$value];
					break;
			}

			if(substr($name, -3) == '_id') {
				$name = substr($name, 0, -3);
				if(isset($attributes['callback'])) {
					$model = $attributes['callback'][0];
					$name = strtolower(str_replace('Model_', '', \Fuel\Core\Inflector::denamespace($model)));
				} else
					$model = 'Model_'.ucfirst($name);

				$title = $model::find_by_pk($value);
				if($title)
					$title = $title->select_name();
				else
					$title = $value;
				$value = '<a href="'.$this->base_url.'/../'.\Fuel\Core\Inflector::pluralize($name).'/view/'.$value.'">'.$title.'</a>';
			}

			$ret .= '<tr><td>'.$label.' :</td><td>'.$value.'</td></tr>';
		}
		return $ret.'</tbody></table>';
	}

}