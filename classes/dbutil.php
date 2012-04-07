<?php

namespace Base;

/**
 * Extends the DBUtil class from FuelPHP to add some trigger management and
 * MySQL specific function to use uuid_short as primary key.
 *
 * @package krtek-Base
 * @category BaseDatabase
 * @author Gilles Meier <krtek4@gmail.com>
 * @version 1.0
 * @license Affero GPLv3 http://www.gnu.org/licenses/agpl-3.0-standalone.html
 * @copyright 2012 Gilles Meier <krtek4@gmail.com>
 * @link https://github.com/krtek4/fuel-krtek-base
 */
class DBUtil extends \Fuel\Core\DBUtil {
	/**
	 * @var array Needed information for storing MySQL's uuid_short() information.
	 */
	static private $uuid_short_type = array('type' => 'bigint', 'unsigned' => true, 'default' => 0);

	/**
	 * Compute a name for a foreign key based on the table and the key.
	 * @param string $table Table name
	 * @param string $key The column used as a key
	 * @return string The name to use for the FK
	 */
	static public function foreign_key_name($table, $key) {
		return 'fk_'.$table.'_'.$key;
	}

	/**
	 * Create the array needed by create_table() to create FK constraint on the creation.
	 * If $dest_table and $column are not provided, the destination table is computed by addind an 's' to the key name
	 * and the column is 'id'.
	 * If $key is an array, destination table and column must be null and the function will be called on each element
	 * and an array of array will be returned.
	 * @param string|array $key key name without the' _id' at the end
	 * @param string $source_table table name
	 * @param string $dest_table destination table name (reference)
	 * @param string $column column name in the destination table.
	 * @return array information for create_table()
	 */
	static protected function foreign_key_array($key, $source_table, $dest_table = null, $column = null) {
		if(is_array($key)) {
			if(! (is_null($dest_table) && is_null($column)))
				throw new Exception("If an array of keys is provided, destination table and columns must be null.");

			$fks = array();
			foreach($key as $k)
				$fks[] = static::foreign_key_array($k, $source_table, $dest_table, $column);
			return $fks;
		}

		$dest_table = is_null($dest_table) ? \Inflector::pluralize($key) : $dest_table;
		$column = is_null($column) ? 'id' : $column;

		return array(
			'constraint' => static::foreign_key_name($source_table, $key),
			'key' => $key.'_id',
			'reference' => array('table' => $dest_table, 'column' => $column),
			'on_delete' => 'CASCADE'
		);
	}

	/**
	 * Create a FK constraint in the database. The name of the constraint will be computed with
	 * the foreign_key_name() function.
	 * @staticvar array $accepted_action List of accepted action for On_Delete and On_Update
	 * @param string $table The table name
	 * @param string $key The key name
	 * @param string $ref_table The referenced table name
	 * @param string $column The references column
	 * @param string $on_delete What to do on delete
	 * @param string $on_update What to do on update
	 * @return bool always true
	 */
	static public function create_foreign_key($table, $key, $ref_table, $column = 'id', $on_delete = 'CASCADE', $on_update = 'CASCADE') {
		static $accepted_action = array('CASCADE', 'SET NULL', 'RESTRICT', 'NO ACTION', 'SET DEFAULT');

		if(! in_array($on_delete, $accepted_action))
			throw new \Database_Exception('Invalid action for On Delete : ',$on_delete);
		if(! in_array($on_update, $accepted_action))
			throw new \Database_Exception('Invalid action for On Update : ',$on_update);

		\DB::query('ALTER TABLE '.\DB::quote_identifier(\DB::table_prefix($table)).
			' ADD CONSTRAINT '.static::foreign_key_name($table, $key).' FOREIGN KEY'.
			' ('.$key.')'.
			' REFERENCES '.\DB::quote_identifier(\DB::table_prefix($ref_table)).' ('.$column.')'.
			' ON DELETE '.$on_delete.' ON UPDATE '.$on_update)->execute();
		return true;
	}

	/**
	 * Drop a FK constraint in the database. The name of the constraint will be computed with
	 * the foreign_key_name() function OR if $key is null, $table will be used as the FK name.
	 * @param string $table The table name OR the constraint name if $key is null
	 * @param string $key The key name
	 * @return bool always true
	 */
	static public function drop_foreign_key($table, $key) {
		\DB::query('ALTER TABLE '.\DB::quote_identifier(\DB::table_prefix($table)).
			' DROP FOREIGN KEY '.static::foreign_key_name($table, $key))->execute();
		return true;
	}

	/**
	 * Create a new table which use the result of MySQL's uuid_short function as primary key. The primary
	 * key field is automatically added by the function and a trigger is created to populate it.
	 * The FKs are created based on the key name provided in the last paramater. An '_id' is added at the end for
	 * the field name, the references table is the key name + 's' and the column is 'id'.
	 * @param string $name Table name
	 * @param array $fields List of fields without primary field
	 * @param array $fks List of keys to automatically create FKs.
	 * @return bool true if succeeded
	 */
	static public function uuid_table($name, array $fields, array $fks = array()) {
		$fields['id'] = static::$uuid_short_type;

		foreach($fks as $f)
			$fields[$f.'_id'] = static::$uuid_short_type;

		$complete_fk = static::foreign_key_array($fks, $name);

		return \DBUtil::create_table($name, $fields, array('id'), false, 'InnoDB', null, $complete_fk) &&
				static::create_trigger('uuid_short_'.$name, $name, 'BEGIN SET NEW.id = uuid_short(); SET @last_uuid = NEW.id; END');
	}

	/**
	 * Drop a table and trigger created by uuid_table().
	 * @param string $name Table name
	 * @return bool always true
	 */
	static public function drop_uuid_table($name) {
		static::drop_trigger('uuid_short_'.$name);
		\DBUtil::drop_table($name);
		return  true;
	}

	/**
	 * Create a trigger in the database
	 * @staticvar array $accepted_time List of accepted time for the trigger
	 * @staticvar array $accepted_event List of accepted event for the trigger
	 * @param string $name Name of the trigger
	 * @param string $table Table on which the trigger operates
	 * @param string $body Body of the trigger
	 * @param string $time Time for the trigger
	 * @param string $event Event on which the trigger will be triggered
	 * @return bool true if succeeded
	 */
	static public function create_trigger($name, $table, $body, $time = 'BEFORE', $event = 'INSERT') {
		static $accepted_time = array('BEFORE', 'AFTER');
		static $accepted_event = array('INSERT', 'UPDATE', 'DELETE');

		$time = strtoupper($time);
		$event = strtoupper($event);

		if(! in_array($time, $accepted_time))
			throw new \Database_Exception('Invalid time for trigger : ',$time);

		if(! in_array($event, $accepted_event))
			throw new \Database_Exception('Invalid event for trigger : ',$event);

		$sql = 'CREATE TRIGGER '.\DB::quote_identifier($name).' '.$time.' '.$event.' ON ';
		$sql .= \DB::quote_identifier(\DB::table_prefix($table));
		$sql .= ' FOR EACH ROW '.$body.';';

		return \DB::query($sql)->execute();
	}

	/**
	 * Drop a trigger in the database
	 * @param string $name Trigger name
	 * @param bool $if_exists Only if exists ? default to false
	 * @return bool true if succeeded
	 */
	static public function drop_trigger($name, $if_exists = false) {
		\DB::query('DROP TRIGGER '.($if_exists ? 'IF EXISTS ' : '').\DB::quote_identifier($name))->execute();
		return true;
	}
}

?>
