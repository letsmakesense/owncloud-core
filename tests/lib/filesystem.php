<?php
/**
 * ownCloud
 *
 * @author Robin Appelman
 * @copyright 2012 Robin Appelman icewind@owncloud.com
 *
 * This library is free software; you can redistribute it and/or
 * modify it under the terms of the GNU AFFERO GENERAL PUBLIC LICENSE
 * License as published by the Free Software Foundation; either
 * version 3 of the License, or any later version.
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU AFFERO GENERAL PUBLIC LICENSE for more details.
 *
 * You should have received a copy of the GNU Affero General Public
 * License along with this library.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

class Test_Filesystem extends PHPUnit_Framework_TestCase {
	/**
	 * @var array tmpDirs
	 */
	private $tmpDirs = array();

	/**
	 * @return array
	 */
	private function getStorageData() {
		$dir = OC_Helper::tmpFolder();
		$this->tmpDirs[] = $dir;
		return array('datadir' => $dir);
	}

	public function tearDown() {
		foreach ($this->tmpDirs as $dir) {
			OC_Helper::rmdirr($dir);
		}
	}

	public function setUp() {
		OC_Filesystem::clearMounts();
	}

	public function testMount() {
		OC_Filesystem::mount('OC_Filestorage_Local', self::getStorageData(), '/');
		$this->assertEquals('/', OC_Filesystem::getMountPoint('/'));
		$this->assertEquals('/', OC_Filesystem::getMountPoint('/some/folder'));
		$this->assertEquals('', OC_Filesystem::getInternalPath('/'));
		$this->assertEquals('some/folder', OC_Filesystem::getInternalPath('/some/folder'));

		OC_Filesystem::mount('OC_Filestorage_Local', self::getStorageData(), '/some');
		$this->assertEquals('/', OC_Filesystem::getMountPoint('/'));
		$this->assertEquals('/some/', OC_Filesystem::getMountPoint('/some/folder'));
		$this->assertEquals('/some/', OC_Filesystem::getMountPoint('/some/'));
		$this->assertEquals('/', OC_Filesystem::getMountPoint('/some'));
		$this->assertEquals('folder', OC_Filesystem::getInternalPath('/some/folder'));
	}

	public function testNormalize() {
		$this->assertEquals('/path', OC_Filesystem::normalizePath('/path/'));
		$this->assertEquals('/path/', OC_Filesystem::normalizePath('/path/', false));
		$this->assertEquals('/path', OC_Filesystem::normalizePath('path'));
		$this->assertEquals('/path', OC_Filesystem::normalizePath('\path'));
		$this->assertEquals('/foo/bar', OC_Filesystem::normalizePath('/foo//bar/'));
		$this->assertEquals('/foo/bar', OC_Filesystem::normalizePath('/foo////bar'));
		if (class_exists('Normalizer')) {
			$this->assertEquals("/foo/bar\xC3\xBC", OC_Filesystem::normalizePath("/foo/baru\xCC\x88"));
		}
	}

	public function testBlacklist() {
		OC_Hook::clear('OC_Filesystem');
		OC::registerFilesystemHooks();

		$run = true;
		OC_Hook::emit(
			OC_Filesystem::CLASSNAME,
			OC_Filesystem::signal_write,
			array(
				OC_Filesystem::signal_param_path => '/test/.htaccess',
				OC_Filesystem::signal_param_run => &$run
			)
		);
		$this->assertFalse($run);

		if (OC_Filesystem::getView()) {
			$user = OC_User::getUser();
		} else {
			$user = uniqid();
			OC_Filesystem::init('/' . $user . '/files');
		}

		OC_Filesystem::mount('OC_Filestorage_Temporary', array(), '/');

		$rootView = new OC_FilesystemView('');
		$rootView->mkdir('/' . $user);
		$rootView->mkdir('/' . $user . '/files');

		$this->assertFalse((bool)$rootView->file_put_contents('/.htaccess', 'foo'));
		$this->assertFalse((bool)OC_Filesystem::file_put_contents('/.htaccess', 'foo'));
		$fh = fopen(__FILE__, 'r');
		$this->assertFalse((bool)OC_Filesystem::file_put_contents('/.htaccess', $fh));
	}

	public function testHooks() {
		if (OC_Filesystem::getView()) {
			$user = OC_User::getUser();
		} else {
			$user = uniqid();
			OC_Filesystem::init('/' . $user . '/files');
		}
		OC_Hook::clear('OC_Filesystem');
		OC_Hook::connect('OC_Filesystem', 'post_write', $this, 'dummyHook');

		OC_Filesystem::mount('OC_Filestorage_Temporary', array(), '/');

		$rootView = new OC_FilesystemView('');
		$rootView->mkdir('/' . $user);
		$rootView->mkdir('/' . $user . '/files');

		OC_Filesystem::file_put_contents('/foo', 'foo');
		OC_Filesystem::mkdir('/bar');
		OC_Filesystem::file_put_contents('/bar//foo', 'foo');

		$tmpFile = OC_Helper::tmpFile();
		file_put_contents($tmpFile, 'foo');
		$fh = fopen($tmpFile, 'r');
		OC_Filesystem::file_put_contents('/bar//foo', $fh);
	}

	public function dummyHook($arguments) {
		$path = $arguments['path'];
		$this->assertEquals($path, OC_Filesystem::normalizePath($path)); //the path passed to the hook should already be normalized
	}
}
