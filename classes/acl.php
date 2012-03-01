<?php

namespace Base;

/**
 * Thrown when a 403 forbidden error is needed.
 */
class HttpForbiddenException extends \Fuel\Core\HttpException {
	public function response() {
		return new \Response(\View::forge('403'), 403);
	}
}

/**
 * Thrown when an user hasn't access to some ressource.
 */
class AclException extends \Auth\AuthException { }

/**
 * Helper class used to query the auth driver about access to various
 * part of the application.
 *
 * @package krtek-Base
 * @category BaseInterfaces
 * @author Gilles Meier <krtek4@gmail.com>
 * @version 1.0
 * @license Affero GPLv3 http://www.gnu.org/licenses/agpl-3.0-standalone.html
 * @copyright 2012 Gilles Meier <krtek4@gmail.com>
 * @link https://github.com/krtek4/fuel-krtek-base
 */
class Acl {
	/**
	 * Check for access right on a model_instance or class.
	 *
	 * @param Model_Base|string $instance either a model class instance or a class name
	 * @param string $action the action to test for (save|update|delete|find)
	 * @return bool Wheter the user has access or not
	 */
	public static function model_access($instance, $action) {
		return true;
		// FIXME: implement this method
	}

	/**
	 * Check for access right on a particular action of a controller
	 *
	 * @param string $controller the controller name
	 * @param string $action the controller action to test for
	 * @param string $domain the domain (public|admin) if it is impossible to infer from controller name
	 * @return bool Wheter the user has access or not
	 */
	public static function controller_access($controller, $action = 'index', $domain = null) {
		return true;
		// FIXME: implement this method
	}
}

?>