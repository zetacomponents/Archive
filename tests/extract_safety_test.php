<?php
/**
 *
 * Licensed to the Apache Software Foundation (ASF) under one
 * or more contributor license agreements.  See the NOTICE file
 * distributed with this work for additional information
 * regarding copyright ownership.  The ASF licenses this file
 * to you under the Apache License, Version 2.0 (the
 * "License"); you may not use this file except in compliance
 * with the License.  You may obtain a copy of the License at
 * 
 *   http://www.apache.org/licenses/LICENSE-2.0
 * 
 * Unless required by applicable law or agreed to in writing,
 * software distributed under the License is distributed on an
 * "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY
 * KIND, either express or implied.  See the License for the
 * specific language governing permissions and limitations
 * under the License.
 *
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache License, Version 2.0
 * @filesource
 * @package Archive
 * @version //autogen//
 * @subpackage Tests
 */

require_once( dirname( __FILE__ ) . "/archive_test_case.php" );
require_once( dirname( __FILE__ ) . "/tar/tar_test_builder.php" );

/**
 * Tests that extracting never writes outside of the target directory and
 * does not apply setuid, setgid or sticky bits by default.
 *
 * @package Archive
 * @version //autogen//
 * @subpackage Tests
 */
class ezcArchiveExtractSafetyTest extends ezcArchiveTestCase
{
    protected $dir;
    protected $target;

    protected function setUp() : void
    {
        date_default_timezone_set( "UTC" );
        $this->createTempDir( "ezcArchive_" );
        $this->dir = realpath( $this->getTempDir() );
        $this->target = $this->dir . "/target";
        mkdir( $this->target );
        mkdir( $this->dir . "/outside" );
        file_put_contents( $this->dir . "/outside/secret.txt", "secret" );
    }

    protected function tearDown() : void
    {
        $this->removeTempDir();
    }

    protected function skipWithoutSymlinks()
    {
        if ( substr( php_uname( 's' ), 0, 7 ) == 'Windows' )
        {
            self::markTestSkipped( "Symbolic links are copied on Windows." );
        }
    }

    /**
     * Extracts the tar archive made of $blocks into the target directory.
     */
    protected function extractTar( $blocks, ezcArchiveOptions $options = null )
    {
        $archive = ezcArchive::open( ezcArchiveTarTestBuilder::write( $this->dir . "/archive.tar", $blocks ), ezcArchive::TAR_USTAR, $options );
        $archive->extract( $this->target );
    }

    /**
     * Asserts that extracting $blocks throws an ezcArchiveValueException that
     * names $value.
     */
    protected function assertRefused( $blocks, $value )
    {
        try
        {
            $this->extractTar( $blocks );
            self::fail( "Expected an ezcArchiveValueException." );
        }
        catch ( ezcArchiveValueException $e )
        {
            self::assertStringContainsString( "'$value'", $e->getMessage() );
        }
    }

    public static function escapingPaths()
    {
        return array(
            array( "../escaped.txt" ),
            array( "a/../../escaped.txt" ),
            array( "./../escaped.txt" ),
            array( "a/b/../../../escaped.txt" ),
            array( ".." ),
        );
    }

    /**
     * @dataProvider escapingPaths
     */
    public function testPathOutsideTargetIsRefused( $path )
    {
        $this->assertRefused( ezcArchiveTarTestBuilder::file( $path, "escaped" ), $path );
        self::assertFileDoesNotExist( $this->dir . "/escaped.txt" );
    }

    public function testDotDotInsideTargetIsRefused()
    {
        // Like GNU tar, refuse every ".." component, even if the path would
        // stay inside the target.
        $this->assertRefused( ezcArchiveTarTestBuilder::file( "a/../inside.txt", "inside" ), "a/../inside.txt" );
        self::assertFileDoesNotExist( $this->target . "/inside.txt" );
    }

    public function testCurrentDirectoryComponentsAreAllowed()
    {
        $this->extractTar( ezcArchiveTarTestBuilder::file( "./a/./inside.txt", "inside" ) );
        self::assertSame( "inside", file_get_contents( $this->target . "/a/inside.txt" ) );
    }

    public function testAbsolutePathIsRefused()
    {
        // The name field of a tar header holds 100 characters.
        $path = "/tmp/ezcArchive-absolute-" . getmypid() . ".txt";
        try
        {
            $this->assertRefused( ezcArchiveTarTestBuilder::file( $path, "absolute" ), $path );
            self::assertFileDoesNotExist( $path );
        }
        finally
        {
            @unlink( $path );
        }
    }

    public static function pathsThroughSymlink()
    {
        return array(
            array( "lnk/secret.txt" ),
            array( "lnk/new.txt" ),
            array( "lnk/sub/new.txt" ),
            array( "lnk/./new.txt" ),
        );
    }

    /**
     * @dataProvider pathsThroughSymlink
     */
    public function testWriteThroughSymlinkFromArchiveIsRefused( $path )
    {
        $this->skipWithoutSymlinks();

        $blocks = ezcArchiveTarTestBuilder::header( "lnk", "2", 0, "../outside", "0777", ezcArchiveTarTestBuilder::MAGIC_USTAR ) .
                  ezcArchiveTarTestBuilder::file( $path, "overwritten" );

        $this->assertRefused( $blocks, $path );
        self::assertSame( array( ".", "..", "secret.txt" ), scandir( $this->dir . "/outside" ) );
        self::assertSame( "secret", file_get_contents( $this->dir . "/outside/secret.txt" ) );
    }

    public function testSymlinkInsideTargetIsFollowed()
    {
        $this->skipWithoutSymlinks();

        $blocks = ezcArchiveTarTestBuilder::header( "dir/", "5", 0, "", "0755", ezcArchiveTarTestBuilder::MAGIC_USTAR ) .
                  ezcArchiveTarTestBuilder::header( "lnk", "2", 0, "dir", "0777", ezcArchiveTarTestBuilder::MAGIC_USTAR ) .
                  ezcArchiveTarTestBuilder::file( "lnk/file.txt", "inside" );

        $this->extractTar( $blocks );
        self::assertSame( "inside", file_get_contents( $this->target . "/dir/file.txt" ) );
    }

    public function testSymlinkPointingOutsideIsCreated()
    {
        $this->skipWithoutSymlinks();

        // Creating the link is fine, as long as nothing is written through it.
        $this->extractTar( ezcArchiveTarTestBuilder::header( "lnk", "2", 0, "../outside", "0777", ezcArchiveTarTestBuilder::MAGIC_USTAR ) );
        self::assertSame( "../outside", readlink( $this->target . "/lnk" ) );
    }

    public function testHardLinkToFileOutsideTargetIsRefused()
    {
        $blocks = ezcArchiveTarTestBuilder::header( "hardlink", "1", 0, "../outside/secret.txt", "0644", ezcArchiveTarTestBuilder::MAGIC_USTAR );

        $this->assertRefused( $blocks, "../outside/secret.txt" );
        self::assertFileDoesNotExist( $this->target . "/hardlink" );
    }

    public function testHardLinkThroughSymlinkIsRefused()
    {
        $this->skipWithoutSymlinks();

        $blocks = ezcArchiveTarTestBuilder::header( "lnk", "2", 0, "../outside", "0777", ezcArchiveTarTestBuilder::MAGIC_USTAR ) .
                  ezcArchiveTarTestBuilder::header( "hardlink", "1", 0, "lnk/secret.txt", "0644", ezcArchiveTarTestBuilder::MAGIC_USTAR );

        $this->assertRefused( $blocks, "lnk/secret.txt" );
        self::assertFileDoesNotExist( $this->target . "/hardlink" );
    }

    public function testHardLinkInsideTargetIsCreated()
    {
        $blocks = ezcArchiveTarTestBuilder::file( "file.txt", "inside" ) .
                  ezcArchiveTarTestBuilder::header( "hardlink", "1", 0, "file.txt", "0644", ezcArchiveTarTestBuilder::MAGIC_USTAR );

        $this->extractTar( $blocks );
        self::assertSame( "inside", file_get_contents( $this->target . "/hardlink" ) );
    }

    public function testZipPathOutsideTargetIsRefused()
    {
        if ( !class_exists( 'ZipArchive' ) )
        {
            self::markTestSkipped( "The zip extension is needed to create the archive." );
        }

        $zip = new ZipArchive();
        $zip->open( $this->dir . "/archive.zip", ZipArchive::CREATE );
        $zip->addFromString( "../escaped.txt", "escaped" );
        $zip->close();

        $archive = ezcArchive::open( $this->dir . "/archive.zip" );
        try
        {
            $archive->extract( $this->target );
            self::fail( "Expected an ezcArchiveValueException." );
        }
        catch ( ezcArchiveValueException $e )
        {
            self::assertStringContainsString( "'../escaped.txt'", $e->getMessage() );
        }
        self::assertFileDoesNotExist( $this->dir . "/escaped.txt" );
    }

    public function testSpecialPermissionBitsAreRemoved()
    {
        $blocks = ezcArchiveTarTestBuilder::header( "dir/", "5", 0, "", "3775", ezcArchiveTarTestBuilder::MAGIC_USTAR ) .
                  ezcArchiveTarTestBuilder::file( "setuid", "x", "4755" ) .
                  ezcArchiveTarTestBuilder::file( "setgid", "x", "2755" );

        $this->extractTar( $blocks );
        clearstatcache();
        self::assertSame( "755", sprintf( "%o", fileperms( $this->target . "/setuid" ) & 07777 ) );
        self::assertSame( "755", sprintf( "%o", fileperms( $this->target . "/setgid" ) & 07777 ) );
        self::assertSame( "775", sprintf( "%o", fileperms( $this->target . "/dir" ) & 07777 ) );
    }

    public function testSpecialPermissionBitsArePreservedOnRequest()
    {
        if ( substr( php_uname( 's' ), 0, 7 ) == 'Windows' )
        {
            self::markTestSkipped( "Windows has no setuid bit." );
        }

        $probe = $this->dir . "/probe";
        touch( $probe );
        @chmod( $probe, 04755 );
        clearstatcache();
        if ( ( fileperms( $probe ) & 07777 ) !== 04755 )
        {
            self::markTestSkipped( "The file system does not keep the setuid bit." );
        }

        $options = new ezcArchiveOptions( array( "preserveSpecialPermissions" => true ) );
        $this->extractTar( ezcArchiveTarTestBuilder::file( "setuid", "x", "4755" ), $options );
        clearstatcache();
        self::assertSame( "4755", sprintf( "%o", fileperms( $this->target . "/setuid" ) & 07777 ) );
    }

    public function testUnsafeEntriesAreSkippedOnRequest()
    {
        $path = "/tmp/ezcArchive-absolute-" . getmypid() . ".txt";
        $blocks = ezcArchiveTarTestBuilder::file( "../escaped.txt", "escaped" ) .
                  ezcArchiveTarTestBuilder::file( "a/../inside.txt", "inside" ) .
                  ezcArchiveTarTestBuilder::file( $path, "absolute" ) .
                  ezcArchiveTarTestBuilder::header( "hardlink", "1", 0, "../outside/secret.txt", "0644", ezcArchiveTarTestBuilder::MAGIC_USTAR ) .
                  ezcArchiveTarTestBuilder::file( "good.txt", "good" );

        try
        {
            $this->extractTar( $blocks, new ezcArchiveOptions( array( "skipUnsafeEntries" => true ) ) );
            self::assertFileDoesNotExist( $path );
        }
        finally
        {
            @unlink( $path );
        }

        self::assertSame( "good", file_get_contents( $this->target . "/good.txt" ) );
        self::assertSame( array( ".", "..", "good.txt" ), scandir( $this->target ) );
        self::assertFileDoesNotExist( $this->dir . "/escaped.txt" );
    }

    public function testWriteThroughSymlinkIsSkippedOnRequest()
    {
        $this->skipWithoutSymlinks();

        $blocks = ezcArchiveTarTestBuilder::header( "lnk", "2", 0, "../outside", "0777", ezcArchiveTarTestBuilder::MAGIC_USTAR ) .
                  ezcArchiveTarTestBuilder::file( "lnk/new.txt", "overwritten" ) .
                  ezcArchiveTarTestBuilder::header( "hardlink", "1", 0, "lnk/secret.txt", "0644", ezcArchiveTarTestBuilder::MAGIC_USTAR ) .
                  ezcArchiveTarTestBuilder::file( "good.txt", "good" );

        $this->extractTar( $blocks, new ezcArchiveOptions( array( "skipUnsafeEntries" => true ) ) );
        self::assertSame( array( ".", "..", "secret.txt" ), scandir( $this->dir . "/outside" ) );
        self::assertSame( array( ".", "..", "good.txt", "lnk" ), scandir( $this->target ) );
    }

    public function testExtractCurrentReturnsFalseForSkippedEntry()
    {
        $blocks = ezcArchiveTarTestBuilder::file( "../escaped.txt", "escaped" );
        $options = new ezcArchiveOptions( array( "skipUnsafeEntries" => true ) );
        $archive = ezcArchive::open( ezcArchiveTarTestBuilder::write( $this->dir . "/archive.tar", $blocks ), ezcArchive::TAR_USTAR, $options );

        self::assertFalse( $archive->extractCurrent( $this->target ) );
        self::assertFileDoesNotExist( $this->dir . "/escaped.txt" );
    }

    public function testPermissionsWithOnlySpecialBitsAreApplied()
    {
        if ( substr( php_uname( 's' ), 0, 7 ) == 'Windows' )
        {
            self::markTestSkipped( "Windows has no Unix permissions." );
        }

        // Mode 4000 is 0 without the setuid bit. The file must not keep the
        // default permissions instead.
        $this->extractTar( ezcArchiveTarTestBuilder::file( "setuid-only", "x", "4000" ) );
        clearstatcache();
        self::assertSame( "0", sprintf( "%o", fileperms( $this->target . "/setuid-only" ) & 07777 ) );
    }

    public function testEntryWithoutPermissionsKeepsDefaultPermissions()
    {
        // ZIP archives made on Windows store no Unix permissions at all.
        $this->extractTar( ezcArchiveTarTestBuilder::file( "no-mode", "x", "0000" ) );
        clearstatcache();
        self::assertNotSame( 0, fileperms( $this->target . "/no-mode" ) & 07777 );
        self::assertSame( "x", file_get_contents( $this->target . "/no-mode" ) );
    }

    public static function pathChecks()
    {
        return array(
            // path, Windows, refused
            array( "a/b.txt", false, false ),
            array( "a/b.txt", true, false ),
            array( "./a.txt", true, false ),
            array( "a..b/c...txt", true, false ),
            array( "...", false, false ),
            array( "a/.. /b.txt", false, false ),
            array( "a/b:c.txt", false, false ),
            array( "../a.txt", false, true ),
            array( "a/../b.txt", false, true ),
            array( "/a.txt", false, true ),
            array( "a\\..\\..\\b.txt", true, true ),
            array( "\\a.txt", true, true ),
            array( "C:a.txt", true, true ),
            array( "a/.. /b.txt", true, true ),
            array( "a/... /b.txt", true, true ),
            array( "a/.../b.txt", true, true ),
            array( "a/ ./b.txt", true, true ),
            array( "a/b.txt:stream", true, true ),
            array( "a/b:c/d.txt", true, true ),
        );
    }

    /**
     * @dataProvider pathChecks
     */
    public function testCheckRelativePath( $path, $isWindows, $refused )
    {
        $archive = ezcArchive::open( ezcArchiveTarTestBuilder::write( $this->dir . "/archive.tar", ezcArchiveTarTestBuilder::file( "a.txt", "a" ) ), ezcArchive::TAR_USTAR );
        $check = Closure::bind( function ( $path, $isWindows )
        {
            $this->checkRelativePath( $path, $isWindows );
        }, $archive, get_class( $archive ) );

        try
        {
            $check( $path, $isWindows );
            self::assertFalse( $refused, "'$path' was accepted." );
        }
        catch ( ezcArchiveValueException $e )
        {
            self::assertTrue( $refused, "'$path' was refused: " . $e->getMessage() );
        }
    }

    public static function suite()
    {
        return new \PHPUnit\Framework\TestSuite( __CLASS__ );
    }
}
?>
