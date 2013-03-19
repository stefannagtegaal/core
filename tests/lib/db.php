<?php
/**
 * Copyright (c) 2012 Bart Visscher <bartv@thisnet.nl>
 * This file is licensed under the Affero General Public License version 3 or
 * later.
 * See the COPYING-README file.
 */

class Test_DB extends PHPUnit_Framework_TestCase {
	protected $backupGlobals = FALSE;

	protected static $schema_file = 'static://test_db_scheme';
	protected $test_prefix;

	public function setUp() {
		$dbfile = OC::$SERVERROOT.'/tests/data/db_structure.xml';

		$r = '_'.OC_Util::generate_random_bytes('4').'_';
		$content = file_get_contents( $dbfile );
		$content = str_replace( '*dbprefix*', '*dbprefix*'.$r, $content );
		file_put_contents( self::$schema_file, $content );
		OC_DB::createDbFromStructure(self::$schema_file);

		$this->test_prefix = $r;
		$this->table1 = $this->test_prefix.'contacts_addressbooks';
		$this->table2 = $this->test_prefix.'contacts_cards';
		$this->table3 = $this->test_prefix.'vcategory';
	}

	public function tearDown() {
		OC_DB::removeDBStructure(self::$schema_file);
		unlink(self::$schema_file);
	}

	public function testQuotes() {
		$query = OC_DB::prepare('SELECT `fullname` FROM *PREFIX*'.$this->table2.' WHERE `uri` = ?');
		$result = $query->execute(array('uri_1'));
		$this->assertTrue((bool)$result);
		$row = $result->fetchRow();
		$this->assertFalse($row);
		$query = OC_DB::prepare('INSERT INTO *PREFIX*'.$this->table2.' (`fullname`,`uri`) VALUES (?,?)');
		$result = $query->execute(array('fullname test', 'uri_1'));
		$this->assertTrue((bool)$result);
		$query = OC_DB::prepare('SELECT `fullname`,`uri` FROM *PREFIX*'.$this->table2.' WHERE `uri` = ?');
		$result = $query->execute(array('uri_1'));
		$this->assertTrue((bool)$result);
		$row = $result->fetchRow();
		$this->assertArrayHasKey('fullname', $row);
		$this->assertEquals($row['fullname'], 'fullname test');
		$row = $result->fetchRow();
		$this->assertFalse($row);
	}

	public function testNOW() {
		$query = OC_DB::prepare('INSERT INTO *PREFIX*'.$this->table2.' (`fullname`,`uri`) VALUES (NOW(),?)');
		$result = $query->execute(array('uri_2'));
		$this->assertTrue((bool)$result);
		$query = OC_DB::prepare('SELECT `fullname`,`uri` FROM *PREFIX*'.$this->table2.' WHERE `uri` = ?');
		$result = $query->execute(array('uri_2'));
		$this->assertTrue((bool)$result);
	}

	public function testUNIX_TIMESTAMP() {
		$query = OC_DB::prepare('INSERT INTO *PREFIX*'.$this->table2.' (`fullname`,`uri`) VALUES (UNIX_TIMESTAMP(),?)');
		$result = $query->execute(array('uri_3'));
		$this->assertTrue((bool)$result);
		$query = OC_DB::prepare('SELECT `fullname`,`uri` FROM *PREFIX*'.$this->table2.' WHERE `uri` = ?');
		$result = $query->execute(array('uri_3'));
		$this->assertTrue((bool)$result);
	}

	public function testinsertIfNotExist() {
		$categoryentries = array(
				array('user' => 'test', 'type' => 'contact', 'category' => 'Family'),
				array('user' => 'test', 'type' => 'contact', 'category' => 'Friends'),
				array('user' => 'test', 'type' => 'contact', 'category' => 'Coworkers'),
				array('user' => 'test', 'type' => 'contact', 'category' => 'Coworkers'),
				array('user' => 'test', 'type' => 'contact', 'category' => 'School'),
			);

		foreach($categoryentries as $entry) {
			$result = OC_DB::insertIfNotExist('*PREFIX*'.$this->table3,
				array(
					'uid' => $entry['user'],
					'type' => $entry['type'],
					'category' => $entry['category'],
				));
			$this->assertTrue((bool)$result);
		}

		$query = OC_DB::prepare('SELECT * FROM *PREFIX*'.$this->table3);
		$result = $query->execute();
		$this->assertTrue((bool)$result);
		$this->assertEquals('4', $result->numRows());
	}

	public function testinsertIfNotExistDontOverwrite() {
		$fullname = 'fullname test';
		$uri = 'uri_1';
		$carddata = 'This is a vCard';

		// Normal test to have same known data inserted.
		$query = OC_DB::prepare('INSERT INTO *PREFIX*'.$this->table2.' (`fullname`, `uri`, `carddata`) VALUES (?, ?, ?)');
		$result = $query->execute(array($fullname, $uri, $carddata));
		$this->assertTrue((bool)$result);
		$query = OC_DB::prepare('SELECT `fullname`, `uri`, `carddata` FROM *PREFIX*'.$this->table2.' WHERE `uri` = ?');
		$result = $query->execute(array($uri));
		$this->assertTrue((bool)$result);
		$row = $result->fetchRow();
		$this->assertArrayHasKey('carddata', $row);
		$this->assertEquals($carddata, $row['carddata']);
		$this->assertEquals('1', $result->numRows());

		// Try to insert a new row
		$result = OC_DB::insertIfNotExist('*PREFIX*'.$this->table2,
			array(
				'fullname' => $fullname,
				'uri' => $uri,
			));
		$this->assertTrue((bool)$result);

		$query = OC_DB::prepare('SELECT `fullname`, `uri`, `carddata` FROM *PREFIX*'.$this->table2.' WHERE `uri` = ?');
		$result = $query->execute(array($uri));
		$this->assertTrue((bool)$result);
		$row = $result->fetchRow();
		$this->assertArrayHasKey('carddata', $row);
		// Test that previously inserted data isn't overwritten
		$this->assertEquals($carddata, $row['carddata']);
		// And that a new row hasn't been inserted.
		$this->assertEquals('1', $result->numRows());

	}
}
