<?php

/**
 * Fuel-krtek-Base
 *
 * Various base classes for FuelPHP
 *
 * @package krtek-Base
 * @category BaseClasses
 * @author Gilles Meier <krtek4@gmail.com>
 * @version 1.0
 * @license Affero GPLv3 http://www.gnu.org/licenses/agpl-3.0-standalone.html
 * @copyright 2012 Gilles Meier <krtek4@gmail.com>
 * @link https://github.com/krtek4/fuel-krtek-base
 */

Fuel\Core\Autoloader::add_core_namespace('Base');

Fuel\Core\Autoloader::add_classes(array(
	'Base\\DBUtil' => __DIR__.'/classes/dbutil.php',
	'Base\\Migration' => __DIR__.'/classes/migration.php',
	'Base\\Model_Base' => __DIR__.'/classes/model.php',
	'Base\\Cache' => __DIR__.'/classes/cache.php',
	'Base\\Controller_Base' => __DIR__.'/classes/controller.php',
	'Base\\ViewModel_Base' => __DIR__.'/classes/viewmodel.php',
	'Base\\Acl' => __DIR__.'/classes/acl.php',

	'Base\\Controller_Crud' => __DIR__.'/classes/controller_crud.php',
	'Base\\View_Crud' => __DIR__.'/classes/viewmodel_crud.php',
));
