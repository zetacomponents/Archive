<?php
/**
 * Entry Path Test
 *
 * @author Andreas Demetriou <androsland@gmail.com>
 */
class EntryPathTest extends ezcArchiveTestCase {

	/**
	 *
	 * Test function to test if the extract function of ezcArchive works on files
	 * with long name.
	 *
	 * Due to '\0' at the end of the filename, file_exists fatal error is being caught.
	 *
	 */
	public function test_entry_path() {
		$this->createTempDir( "entryPath-", sys_get_temp_dir ());
		$dir = $this->getTempDir();
		$archive = ezcArchive::open(dirname(__FILE__) . "/data/images.tar.gz");
		try {
			$archive->extract($dir);
		} catch (Exception $e) {// ignore any type of exception, including fatal exception file_exists
		}
		clearstatcache();
		$this->assertTrue(file_exists("$dir/testing/naming/including/inner/module/files/purpose/helper/jquery-ui-bootstrap/images/ui-bg_flat_0_aaaaaa_40x100.png"));
		$this->assertTrue(file_exists("$dir/testing/naming/including/inner/module/files/purpose/helper/jquery-ui-bootstrap/images/ui-bg_highlight-soft_75_cccccc_1x100.png"));
		$this->assertTrue(file_exists("$dir/testing/naming/including/inner/module/files/purpose/helper/jquery-ui-bootstrap/images/ui-bg_inset-soft_95_fef1ec_1x100.png"));
		$this->removeTempDir();
	}
}
?>
