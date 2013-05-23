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

Fuel\Core\Autoloader::add_core_namespace('KrtekBase');

Fuel\Core\Autoloader::add_classes(array(
	'KrtekBase\\DBUtil' => __DIR__.'/classes/dbutil.php',
	'KrtekBase\\Migration' => __DIR__.'/classes/migration.php',
	'KrtekBase\\Model_Base' => __DIR__.'/classes/model.php',
	'KrtekBase\\Krtek_Cache' => __DIR__.'/classes/cache.php',
	'KrtekBase\\Controller_Base' => __DIR__.'/classes/controller.php',
	'KrtekBase\\ViewModel_Base' => __DIR__.'/classes/viewmodel.php',
	'KrtekBase\\Acl' => __DIR__.'/classes/acl.php',

	'KrtekBase\\Controller_Crud' => __DIR__.'/classes/controller_crud.php',
	'KrtekBase\\View_Crud' => __DIR__.'/classes/viewmodel_crud.php',
));
