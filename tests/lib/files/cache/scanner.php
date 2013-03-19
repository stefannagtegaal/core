<?php
/**
 * Copyright (c) 2012 Robin Appelman <icewind@owncloud.com>
 * This file is licensed under the Affero General Public License version 3 or
 * later.
 * See the COPYING-README file.
 */

namespace Test\Files\Cache;

class Scanner extends \PHPUnit_Framework_TestCase {
	/**
	 * @var \OC\Files\Storage\Storage $storage
	 */
	private $storage;

	/**
	 * @var \OC\Files\Cache\Scanner $scanner
	 */
	private $scanner;

	/**
	 * @var \OC\Files\Cache\Cache $cache
	 */
	private $cache;

	function testFile() {
		$data = "dummy file data\n";
		$this->storage->file_put_contents('foo.txt', $data);
		$this->scanner->scanFile('foo.txt');

		$this->assertEquals($this->cache->inCache('foo.txt'), true);
		$cachedData = $this->cache->get('foo.txt');
		$this->assertEquals($cachedData['size'], strlen($data));
		$this->assertEquals($cachedData['mimetype'], 'text/plain');
		$this->assertNotEquals($cachedData['parent'], -1); //parent folders should be scanned automatically

		$data = file_get_contents(\OC::$SERVERROOT . '/core/img/logo.png');
		$this->storage->file_put_contents('foo.png', $data);
		$this->scanner->scanFile('foo.png');

		$this->assertEquals($this->cache->inCache('foo.png'), true);
		$cachedData = $this->cache->get('foo.png');
		$this->assertEquals($cachedData['size'], strlen($data));
		$this->assertEquals($cachedData['mimetype'], 'image/png');
	}

	private function fillTestFolders() {
		$textData = "dummy file data\n";
		$imgData = file_get_contents(\OC::$SERVERROOT . '/core/img/logo.png');
		$this->storage->mkdir('folder');
		$this->storage->file_put_contents('foo.txt', $textData);
		$this->storage->file_put_contents('foo.png', $imgData);
		$this->storage->file_put_contents('folder/bar.txt', $textData);
	}

	function testFolder() {
		$this->fillTestFolders();

		$this->scanner->scan('');
		$this->assertEquals($this->cache->inCache(''), true);
		$this->assertEquals($this->cache->inCache('foo.txt'), true);
		$this->assertEquals($this->cache->inCache('foo.png'), true);
		$this->assertEquals($this->cache->inCache('folder'), true);
		$this->assertEquals($this->cache->inCache('folder/bar.txt'), true);

		$cachedDataText = $this->cache->get('foo.txt');
		$cachedDataText2 = $this->cache->get('foo.txt');
		$cachedDataImage = $this->cache->get('foo.png');
		$cachedDataFolder = $this->cache->get('');
		$cachedDataFolder2 = $this->cache->get('folder');

		$this->assertEquals($cachedDataImage['parent'], $cachedDataText['parent']);
		$this->assertEquals($cachedDataFolder['fileid'], $cachedDataImage['parent']);
		$this->assertEquals($cachedDataFolder['size'], $cachedDataImage['size'] + $cachedDataText['size'] + $cachedDataText2['size']);
		$this->assertEquals($cachedDataFolder2['size'], $cachedDataText2['size']);
	}

	function testShallow() {
		$this->fillTestFolders();

		$this->scanner->scan('', \OC\Files\Cache\Scanner::SCAN_SHALLOW);
		$this->assertEquals($this->cache->inCache(''), true);
		$this->assertEquals($this->cache->inCache('foo.txt'), true);
		$this->assertEquals($this->cache->inCache('foo.png'), true);
		$this->assertEquals($this->cache->inCache('folder'), true);
		$this->assertEquals($this->cache->inCache('folder/bar.txt'), false);

		$cachedDataFolder = $this->cache->get('');
		$cachedDataFolder2 = $this->cache->get('folder');

		$this->assertEquals(-1, $cachedDataFolder['size']);
		$this->assertEquals(-1, $cachedDataFolder2['size']);

		$this->scanner->scan('folder', \OC\Files\Cache\Scanner::SCAN_SHALLOW);

		$cachedDataFolder2 = $this->cache->get('folder');

		$this->assertNotEquals($cachedDataFolder2['size'], -1);

		$this->cache->correctFolderSize('folder');

		$cachedDataFolder = $this->cache->get('');
		$this->assertNotEquals($cachedDataFolder['size'], -1);
	}

	function testBackgroundScan(){
		$this->fillTestFolders();
		$this->storage->mkdir('folder2');
		$this->storage->file_put_contents('folder2/bar.txt', 'foobar');

		$this->scanner->scan('', \OC\Files\Cache\Scanner::SCAN_SHALLOW);
		$this->assertFalse($this->cache->inCache('folder/bar.txt'));
		$this->assertFalse($this->cache->inCache('folder/2bar.txt'));
		$cachedData = $this->cache->get('');
		$this->assertEquals(-1, $cachedData['size']);

		$this->scanner->backgroundScan();

		$this->assertTrue($this->cache->inCache('folder/bar.txt'));
		$this->assertTrue($this->cache->inCache('folder/bar.txt'));

		$cachedData = $this->cache->get('');
		$this->assertnotEquals(-1, $cachedData['size']);

		$this->assertFalse($this->cache->getIncomplete());
	}

	function setUp() {
		$this->storage = new \OC\Files\Storage\Temporary(array());
		$this->scanner = new \OC\Files\Cache\Scanner($this->storage);
		$this->cache = new \OC\Files\Cache\Cache($this->storage);
	}

	function tearDown() {
		$ids = $this->cache->getAll();
		$permissionsCache = $this->storage->getPermissionsCache();
		$permissionsCache->removeMultiple($ids, \OC_User::getUser());
		$this->cache->clear();
	}
}
