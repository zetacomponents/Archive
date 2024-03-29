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

require_once( dirname( __FILE__ ) . "/../testdata.php" );
require_once(dirname(__FILE__) . "/../archive_test_case.php");

/**
 * @package Archive
 * @version //autogen//
 * @subpackage Tests
 */
class ezcArchiveBzip2Test extends ezcArchiveTestCase
{
    protected function setUp() : void
    {
        $this->createTempDir( "ezcArchive_" );
        date_default_timezone_set( "UTC" ); 
    }

    protected function tearDown() : void
    {
        $this->removeTempDir();
    }

    /*
    public function testCreateTar()
    {
        $dir = $this->getTempDir();
        $archive = ezcArchive::open( "$dir/mytar.tar", ezcArchive::TAR_USTAR );
        file_put_contents( "$dir/a.txt", "Hello world!" );
        $archive->append( "$dir/a.txt", $dir );
        $archive->close();

        exec( "tar -cf $dir/gnutar.tar --format=ustar -C $dir a.txt" );

        $this->assertEquals( file_get_contents( "$dir/gnutar.tar" ), file_get_contents( "$dir/mytar.tar" ) );
    }
    */

    public function testCreateBzip2Tar()
    {
        if ( !ezcBaseFeatures::hasExtensionSupport( 'bz2' ) )
        {
            $this->markTestSkipped();
        }

        $dir = $this->getTempDir();
        $archive = ezcArchive::open( "compress.bzip2://$dir/mytar.tar.bz2", ezcArchive::TAR_USTAR );
        file_put_contents( "$dir/a.txt", "Hello world!" );
        $archive->append( "$dir/a.txt", $dir );
        $archive->close();

        exec( "tar -cf $dir/gnutar.tar --format=ustar -C $dir a.txt" );
        exec( "bunzip2 $dir/mytar.tar.bz2" );

        $this->assertEquals( file_get_contents( "$dir/gnutar.tar" ), file_get_contents( "$dir/mytar.tar" ) );
    }

    public function testCreateBzip2TarWithTwoFiles()
    {
        if ( !ezcBaseFeatures::hasExtensionSupport( 'bz2' ) )
        {
            $this->markTestSkipped();
        }

        $dir = $this->getTempDir();
        $archive = ezcArchive::open( "compress.bzip2://$dir/mytar.tar.bz2", ezcArchive::TAR_USTAR );
        file_put_contents( "$dir/a.txt", "Hello world!" );
        file_put_contents( "$dir/b.txt", "BBBBBBBBBBBB" );
        $archive->append( "$dir/a.txt", $dir );
        $archive->append( "$dir/b.txt", $dir );
        $archive->close();

        exec( "tar -cf $dir/gnutar.tar --format=ustar -C $dir a.txt b.txt" );
        exec( "bunzip2 $dir/mytar.tar.bz2" );

        $this->assertEquals( file_get_contents( "$dir/gnutar.tar" ), file_get_contents( "$dir/mytar.tar" ) );
    }

    /*
    public function testWriteToExistingGzippedTar()
    {
        if ( !ezcBaseFeatures::hasExtensionSupport( 'zlib' ) )
        {
            $this->markTestSkipped();
        }

        // Create an archive with one file.
        $dir = $this->getTempDir();
        $archive = ezcArchive::open( "compress.zlib://$dir/mytar.tar.gz", ezcArchive::TAR_USTAR );
        file_put_contents( "$dir/a.txt", "Hello world!" );
        $archive->append( "$dir/a.txt", $dir );
        $archive->close();

        // Reopen it and append another file.
        $archive = ezcArchive::open( "compress.zlib://$dir/mytar.tar.gz", ezcArchive::TAR_USTAR );

        try
        {
            $archive->append( "$dir/a.txt", $dir);
            $this->fail( "Expected a 'cannot-append' exception");
        }
        catch ( ezcArchiveException $e )
        {

        }
        $archive->close();
    }

    public function testCreateNewGzippedTarArchiveTogetherWithReadingEntries()
    {
        if ( !ezcBaseFeatures::hasExtensionSupport( 'zlib' ) )
        {
            $this->markTestSkipped();
        }

        $dir = $this->getTempDir();

        // Create some test data.
        file_put_contents( "$dir/a.txt", "AAAAAAAAAAA" );
        file_put_contents( "$dir/b.txt", "BBBBBBBBBBB" );
        file_put_contents( "$dir/c.txt", "CCCCCCCCCCC" );

        // Create a new archive
        $archive = ezcArchive::open( "compress.zlib://$dir/mytar.tar.gz", ezcArchive::TAR_USTAR );
        $archive->append( "$dir/a.txt", $dir );
        $archive->rewind();
        $a = $archive->current();
        $this->assertEquals( "a.txt", $a->getPath( false ) );

        $archive->rewind();
        $archive->append( "$dir/b.txt", $dir );

        $a = $archive->current();
        $this->assertEquals( "a.txt", $a->getPath( false ) );

        $a = $archive->next();
        $this->assertEquals( "b.txt", $a->getPath( false ) );

        $archive->rewind();
        $archive->close();

        // Reopen it and append another file.
        $archive = ezcArchive::open( "compress.zlib://$dir/mytar.tar.gz", ezcArchive::TAR_USTAR );

        try
        {
            $archive->append( "$dir/a.txt", $dir );
            $this->fail( "Expected a 'cannot-append' exception" );
        }
        catch ( ezcArchiveException $e )
        {

        }
        $archive->close();
    }

    public function testAppendToCurrentException()
    {
        if ( !ezcBaseFeatures::hasExtensionSupport( 'zlib' ) )
        {
            $this->markTestSkipped();
        }

        $dir = $this->getTempDir();
        $archive = ezcArchive::open( "compress.zlib://$dir/mytar.tar.gz", ezcArchive::TAR_USTAR );
        file_put_contents( "$dir/a.txt", "AAAAAAAAAAA" );

        try
        {
            $archive->appendToCurrent( "$dir/a.txt", $dir);
            $this->fail( "Expected an exception that the file couldn't be appended." );
        }
        catch ( ezcArchiveException $e )
        {
        }
    }

    public function testCloseException()
    {
        if ( !ezcBaseFeatures::hasExtensionSupport( 'zlib' ) )
        {
            $this->markTestSkipped();
        }

        $dir = $this->getTempDir();
        $archive = ezcArchive::open( "compress.zlib://$dir/mytar.tar.gz", ezcArchive::TAR_USTAR );
        file_put_contents( "$dir/a.txt", "AAAAAAAAAAA" );
        $archive->append( "$dir/a.txt", $dir );
        $archive->close();

        try
        {
            $archive->append( "$dir/a.txt", $dir );
            $this->fail( "Expected an exception that the file couldn't be appended." );
        }
        catch ( ezcArchiveException $e )
        {
        }
    }

    public function testListing()
    {
        if ( !ezcBaseFeatures::hasExtensionSupport( 'zlib' ) )
        {
            $this->markTestSkipped();
        }

        $dir = $this->getTempDir();
        $archive = ezcArchive::open( "compress.zlib://$dir/mytar.tar.gz", ezcArchive::TAR_USTAR );
        file_put_contents( "$dir/a.txt", "AAAAAAAAAAA" );
        $archive->append( "$dir/a.txt", $dir );
        file_put_contents( "$dir/a.txt", "AAAAAAAAAAA" );
        $archive->append( "$dir/a.txt", $dir );

        $a = $archive->getListing();
        $this->assertEquals( 2, sizeof( $a ) );
    }
    */

    public static function suite()
    {
        return new \PHPUnit\Framework\TestSuite( __CLASS__  );
    }
}
?>
