<?php
/**
 * Copyright (c) 2013 Bart Visscher <bartv@thisnet.nl>
 * This file is licensed under the Affero General Public License version 3 or
 * later.
 * See the COPYING-README file.
 */


namespace OC\DB;

class AdapterSqlite extends Adapter {
	public function insertIfNotExist($table, $input) {
		// NOTE: For SQLite we have to use this clumsy approach
		// otherwise all fieldnames used must have a unique key.
		$query = 'SELECT COUNT(*) FROM "' . $table . '" WHERE ';
		foreach($input as $key => $value) {
			$query .= $key . " = '" . $value . '\' AND ';
		}
		$query = substr($query, 0, strlen($query) - 5);
		try {
			$stmt = $this->conn->prepare($query);
			$result = $stmt->execute();
		} catch(\Doctrine\DBAL\DBALException $e) {
			$entry = 'DB Error: "'.$e->getMessage() . '"<br />';
			$entry .= 'Offending command was: ' . $query . '<br />';
			OC_Log::write('core', $entry, OC_Log::FATAL);
			error_log('DB error: '.$entry);
			OC_Template::printErrorPage( $entry );
		}

		if ($stmt->fetchColumn() == 0) {
			$query = 'INSERT INTO "' . $table . '" ("'
				. implode('","', array_keys($input)) . '") VALUES("'
				. implode('","', array_values($input)) . '")';
		} else {
			return true;
		}

		try {
			$result = $this->conn->prepare($query);
		} catch(\Doctrine\DBAL\DBALException $e) {
			$entry = 'DB Error: "'.$e->getMessage() . '"<br />';
			$entry .= 'Offending command was: ' . $query.'<br />';
			OC_Log::write('core', $entry, OC_Log::FATAL);
			error_log('DB error: ' . $entry);
			OC_Template::printErrorPage( $entry );
		}

		return $result->execute();
	}
}
