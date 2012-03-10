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
 * The conditions passed to the ACL driver respect the standard format for the
 * most part (ie array(area, rights)). When needed, a third argument is added :
 * array(area, rights, additional contextual information).
 *
 * One specific area exists and is named 'Model', the right is either 'find',
 * 'save', 'update' or 'delete'. All other areas are supposed to be the
 * controller name and the right is the action.
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
	 * Helper method to call \Auth\Auth::instance()->has_access()
	 *
	 * @param string $condition
	 * @return bool Wheter the user has access or not
	 */
	private static function _check_access($condition) {
		$user = \Auth\Auth::instance()->has_access($condition);
		$group = \Auth\Auth::group()->has_access($condition);
		return $user || $group;
	}

	/**
	 * Check for access right on a model_instance or class.
	 *
	 * @param Model_Base|string $instance either a model class instance or a class name
	 * @param string $action the action to test for (save|update|delete|find)
	 * @return bool Wheter the user has access or not
	 */
	public static function model_access($instance, $action) {
		$condition = array('Model', $action, $instance);
		return self::_check_access($condition);
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
		$condition = array($controller, $action);
		return self::_check_access($condition);
	}
}

?>