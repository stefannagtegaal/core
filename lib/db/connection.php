<?php
/**
 * Copyright (c) 2013 Bart Visscher <bartv@thisnet.nl>
 * This file is licensed under the Affero General Public License version 3 or
 * later.
 * See the COPYING-README file.
 */

namespace OC\DB;
use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Configuration;
use Doctrine\Common\EventManager;

class Connection extends \Doctrine\DBAL\Connection {
	protected $table_prefix;
	protected $sequence_suffix;

	protected $preparedQueries = array();
	protected $cachingQueryStatementEnabled = true;

	/**
	 * Initializes a new instance of the Connection class.
	 *
	 * @param array $params  The connection parameters.
	 * @param Driver $driver
	 * @param Configuration $config
	 * @param EventManager $eventManager
	 */
	public function __construct(array $params, Driver $driver, Configuration $config = null,
		EventManager $eventManager = null)
	{
		if (!isset($params['table_prefix'])) {
			throw new Exception('table_prefix not set');
		}
		if (!isset($params['sequence_suffix'])) {
			throw new Exception('sequence_suffix not set');
		}
		parent::__construct($params, $driver, $config, $eventManager);
		$this->table_prefix = $params['table_prefix'];
		$this->sequence_suffix = $params['sequence_suffix'];
	}

	/**
	 * Prepares an SQL statement.
	 *
	 * @param string $statement The SQL statement to prepare.
	 * @return \Doctrine\DBAL\Driver\Statement The prepared statement.
	 */
	public function prepare( $statement, $limit=null, $offset=null ) {
		$statement = $this->replaceTablePrefix($statement);
		if ($limit === -1) {
			$limit = null;
		}
		if (!is_null($limit)) {
			$platform = $this->getDatabasePlatform();
			$statement = $platform->modifyLimitQuery($statement, $limit, $offset);
		} else {
			if (isset($this->preparedQueries[$statement]) && $this->cachingQueryStatementEnabled) {
				return $this->preparedQueries[$statement];
			}
		}
		$rawQuery = $statement;
		$result = parent::prepare($statement);
		if (is_null($limit) && $this->cachingQueryStatementEnabled) {
			$this->preparedQueries[$rawQuery] = $result;
		}
		return $result;
	}

	/**
	 * Returns the ID of the last inserted row, or the last value from a sequence object,
	 * depending on the underlying driver.
	 *
	 * Note: This method may not return a meaningful or consistent result across different drivers,
	 * because the underlying database may not even support the notion of AUTO_INCREMENT/IDENTITY
	 * columns or sequences.
	 *
	 * @param string $seqName Name of the sequence object from which the ID should be returned.
	 * @return string A string representation of the last inserted ID.
	 */
	public function lastInsertId($seqName = null)
	{
		if ($seqName) {
			$seqName = $this->replaceTablePrefix($seqName) . $this->sequence_suffix;
		}
		return parent::lastInsertId($seqName);
	}

	// internal use
	public function replaceTablePrefix($statement) {
		return str_replace( '*PREFIX*', $this->table_prefix, $statement );
	}

	public function enableQueryStatementCaching() {
		$this->cachingQueryStatementEnabled = true;
	}

	public function disableQueryStatementCaching() {
		$this->cachingQueryStatementEnabled = false;
		$this->preparedQueries = array();
	}
}
