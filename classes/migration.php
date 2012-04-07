<?php

namespace Base;

/**
 * Base class for migrations
 *
 * @package krtek-Base
 * @category BaseDatabase
 * @author Gilles Meier <krtek4@gmail.com>
 * @version 1.0
 * @license Affero GPLv3 http://www.gnu.org/licenses/agpl-3.0-standalone.html
 * @copyright 2012 Gilles Meier <krtek4@gmail.com>
 * @link https://github.com/krtek4/fuel-krtek-base
 */
abstract class Migration {
	/**
	 * Enclose the migration in a transaction.
	 * @param string $direction either 'up' or 'down'
	 * @return bool true if the migration was successful
	 */
	private function run($direction) {
		\Fuel\Core\DB::start_transaction();

		$status = true;
		try {
			$status = call_user_func(array($this, 'do_'.$direction));
		} catch(Exception $e) {
			\Fuel\Core\Cli::error($e);
			$status = false;
		}

		if(! $status) {
			\Fuel\Core\Cli::error("Something went wrong during the migration, rollback !");
			\Fuel\Core\DB::rollback_transaction();
			throw new \Database_Exception("Migration rolled back due to error.");
		}
		\Fuel\Core\DB::commit_transaction();
		return true;
	}

	/**
	 * Migrate up to the next version. Operations are enclosed in a transaction.
	 * Warning: MySQL cannot rollback table creation / deletion.
	 */
	public function up() { return $this->run('up'); }
	/**
	 * Migrate down to the previous version. Operations are enclosed in a transaction.
	 * Warning: MySQL cannot rollback table creation / deletion.
	 */
	public function down() { return $this->run('down'); }

	/**
	 * Actual work for the up migration.
	 */
	protected abstract function do_up();
	/**
	 * Actual work for the down migration.
	 */
	protected abstract function do_down();
}

?>