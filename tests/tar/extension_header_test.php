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

require_once( dirname( __FILE__ ) . "/../archive_test_case.php" );
require_once( dirname( __FILE__ ) . "/tar_test_builder.php" );

/**
 * Tests reading extension headers (GNU long names, PAX extended headers)
 * from archives that are built block by block.
 *
 * @package Archive
 * @version //autogen//
 * @subpackage Tests
 */
class ezcArchiveTarExtensionHeaderTest extends ezcArchiveTestCase
{
    protected function setUp() : void
    {
        date_default_timezone_set( "UTC" );
        $this->createTempDir( "ezcArchive_" );
    }

    protected function tearDown() : void
    {
        $this->removeTempDir();
    }

    /**
     * Writes $blocks to a temporary archive, opens it with $type and returns
     * an array with path => contents (or link target) for every entry.
     */
    protected function readEntries( $blocks, $type = ezcArchive::TAR_GNU )
    {
        $dir = $this->getTempDir();
        $archive = ezcArchive::open( ezcArchiveTarTestBuilder::write( "$dir/archive.tar", $blocks ), $type );

        $result = array();
        foreach ( $archive as $entry )
        {
            if ( $entry->getType() == ezcArchiveEntry::IS_SYMBOLIC_LINK )
            {
                $result[$entry->getPath()] = "-> " . $entry->getLink();
                continue;
            }

            $archive->extractCurrent( "$dir/out" );
            $result[$entry->getPath()] = file_get_contents( "$dir/out/" . $entry->getPath() );
        }
        return $result;
    }

    public function testGnuReaderAppliesPaxExtendedHeader()
    {
        $blocks = ezcArchiveTarTestBuilder::extension( "x", ezcArchiveTarTestBuilder::paxRecord( "path", "dir/from-pax.txt" ) ) .
                  ezcArchiveTarTestBuilder::file( "from-header.txt", "pax" ) .
                  ezcArchiveTarTestBuilder::file( "second.txt", "second" );

        self::assertSame( array( "dir/from-pax.txt" => "pax", "second.txt" => "second" ), $this->readEntries( $blocks ) );
    }

    public function testGnuReaderSkipsPaxGlobalHeader()
    {
        $blocks = ezcArchiveTarTestBuilder::extension( "g", ezcArchiveTarTestBuilder::paxRecord( "comment", "global" ) ) .
                  ezcArchiveTarTestBuilder::file( "first.txt", "first" ) .
                  ezcArchiveTarTestBuilder::file( "second.txt", "second" );

        self::assertSame( array( "first.txt" => "first", "second.txt" => "second" ), $this->readEntries( $blocks ) );
    }

    public function testGnuReaderReadsNulTypeFlagAsFile()
    {
        $blocks = ezcArchiveTarTestBuilder::header( "nul.txt", "\0", 3 ) . ezcArchiveTarTestBuilder::data( "nul" ) .
                  ezcArchiveTarTestBuilder::file( "second.txt", "second" );

        self::assertSame( array( "nul.txt" => "nul", "second.txt" => "second" ), $this->readEntries( $blocks ) );
    }

    public function testGnuReaderReturnsUnknownTypeAsEntry()
    {
        $dir = $this->getTempDir();
        $blocks = ezcArchiveTarTestBuilder::header( "volume", "V", 0 ) .
                  ezcArchiveTarTestBuilder::file( "second.txt", "second" );
        $archive = ezcArchive::open( ezcArchiveTarTestBuilder::write( "$dir/archive.tar", $blocks ), ezcArchive::TAR_GNU );

        $paths = array();
        foreach ( $archive as $entry )
        {
            $paths[] = $entry->getPath();
        }
        self::assertSame( array( "volume", "second.txt" ), $paths );
    }

    public static function longNameLengths()
    {
        return array(
            array( 99 ),   // The name and the NUL fill one block partly.
            array( 511 ),  // The name and the NUL fill exactly one block.
            array( 1023 ), // The name and the NUL fill exactly two blocks.
        );
    }

    /**
     * @dataProvider longNameLengths
     */
    public function testLongFileName( $length )
    {
        // Directories of 50 characters keep every path component short.
        $name = substr( str_repeat( str_repeat( "n", 49 ) . "/", 30 ), 0, $length - 4 ) . ".txt";

        // The data of the first entry looks like a header. It must never be
        // parsed as one.
        $blocks = ezcArchiveTarTestBuilder::extension( "L", "$name\0" ) .
                  ezcArchiveTarTestBuilder::file( "short.txt", ezcArchiveTarTestBuilder::file( "forged.txt", "forged" ) ) .
                  ezcArchiveTarTestBuilder::file( "second.txt", "second" );

        // Only list the entries: the long names do not fit PATH_MAX everywhere.
        $dir = $this->getTempDir();
        $archive = ezcArchive::open( ezcArchiveTarTestBuilder::write( "$dir/archive.tar", $blocks ), ezcArchive::TAR_GNU );

        $entries = array();
        foreach ( $archive as $entry )
        {
            $entries[$entry->getPath()] = $entry->getSize();
        }
        self::assertSame( array( $name => 1024, "second.txt" => 6 ), $entries );
    }

    public function testEmptyLongFileNameIsIgnored()
    {
        $blocks = ezcArchiveTarTestBuilder::extension( "L", "" ) .
                  ezcArchiveTarTestBuilder::file( "first.txt", "first" ) .
                  ezcArchiveTarTestBuilder::file( "second.txt", "second" );

        $dir = $this->getTempDir();
        $archive = ezcArchive::open( ezcArchiveTarTestBuilder::write( "$dir/archive.tar", $blocks ), ezcArchive::TAR_GNU );

        $entries = array();
        foreach ( $archive as $entry )
        {
            $entries[$entry->getPath()] = $entry->getSize();
        }
        self::assertSame( array( "first.txt" => 5, "second.txt" => 6 ), $entries );
    }

    public function testLongLinkNameHasNoTrailingNul()
    {
        $blocks = ezcArchiveTarTestBuilder::extension( "K", "target.txt\0" ) .
                  ezcArchiveTarTestBuilder::header( "link", "2", 0, "ignored" );

        self::assertSame( array( "link" => "-> target.txt" ), $this->readEntries( $blocks ) );
    }

    public function testLongFileNameTooLarge()
    {
        $blocks = ezcArchiveTarTestBuilder::header( "././@LongLink", "L", 077777777777 ) .
                  ezcArchiveTarTestBuilder::file( "first.txt", "first" );

        try
        {
            $this->readEntries( $blocks );
            self::fail( "Expected an ezcArchiveValueException." );
        }
        catch ( ezcArchiveValueException $e )
        {
            self::assertStringContainsString( "8589934591", $e->getMessage() );
        }
    }

    public function testLongFileNameAtMaximumSize()
    {
        $name = str_repeat( "m", ezcArchiveUstarHeader::MAX_EXTENSION_SIZE - 1 );
        $blocks = ezcArchiveTarTestBuilder::extension( "L", "$name\0" ) .
                  ezcArchiveTarTestBuilder::header( "short", "5", 0 );

        $dir = $this->getTempDir();
        $archive = ezcArchive::open( ezcArchiveTarTestBuilder::write( "$dir/archive.tar", $blocks ), ezcArchive::TAR_GNU );
        self::assertSame( $name, $archive->current()->getPath() );
    }

    public function testArchiveEndsInsideLongFileName()
    {
        $dir = $this->getTempDir();
        file_put_contents( "$dir/archive.tar", ezcArchiveTarTestBuilder::header( "././@LongLink", "L", 2000 ) . str_repeat( "n", 1024 ) );

        $this->expectException( ezcArchiveBlockSizeException::class );
        ezcArchive::open( "$dir/archive.tar", ezcArchive::TAR_GNU )->current();
    }

    public function testArchiveEndsAfterLongFileName()
    {
        $dir = $this->getTempDir();
        file_put_contents( "$dir/archive.tar", ezcArchiveTarTestBuilder::extension( "L", "name\0" ) );

        $this->expectException( ezcArchiveBlockSizeException::class );
        ezcArchive::open( "$dir/archive.tar", ezcArchive::TAR_GNU )->current();
    }

    public static function readers()
    {
        return array(
            array( ezcArchive::TAR_GNU ),
            array( ezcArchive::TAR_PAX ),
        );
    }

    /**
     * @dataProvider readers
     */
    public function testPaxRecordsSpanningSeveralBlocks( $type )
    {
        $path = str_repeat( "p/", 400 ) . "file.txt";
        $records = ezcArchiveTarTestBuilder::paxRecord( "comment", str_repeat( "c", 600 ) ) .
                   ezcArchiveTarTestBuilder::paxRecord( "path", $path ) .
                   ezcArchiveTarTestBuilder::paxRecord( "linkpath", "unused" );
        $blocks = ezcArchiveTarTestBuilder::extension( "x", $records ) .
                  ezcArchiveTarTestBuilder::file( "short.txt", "long" ) .
                  ezcArchiveTarTestBuilder::file( "second.txt", "second" );

        self::assertSame( array( $path => "long", "second.txt" => "second" ), $this->readEntries( $blocks, $type ) );
    }

    /**
     * @dataProvider readers
     */
    public function testPaxSizeRecord( $type )
    {
        $blocks = ezcArchiveTarTestBuilder::extension( "x", ezcArchiveTarTestBuilder::paxRecord( "size", "5" ) ) .
                  ezcArchiveTarTestBuilder::header( "sized.txt", "0", 0 ) . ezcArchiveTarTestBuilder::data( "sized" ) .
                  ezcArchiveTarTestBuilder::file( "second.txt", "second" );

        self::assertSame( array( "sized.txt" => "sized", "second.txt" => "second" ), $this->readEntries( $blocks, $type ) );
    }

    /**
     * @dataProvider readers
     */
    public function testEmptyPaxRecordsAreIgnored( $type )
    {
        $records = ezcArchiveTarTestBuilder::paxRecord( "path", "" ) .
                   ezcArchiveTarTestBuilder::paxRecord( "linkpath", "" ) .
                   ezcArchiveTarTestBuilder::paxRecord( "size", "" );
        $blocks = ezcArchiveTarTestBuilder::extension( "x", $records ) .
                  ezcArchiveTarTestBuilder::file( "from-header.txt", "header" ) .
                  ezcArchiveTarTestBuilder::extension( "x", ezcArchiveTarTestBuilder::paxRecord( "linkpath", "" ) ) .
                  ezcArchiveTarTestBuilder::header( "link", "2", 0, "target.txt" );

        self::assertSame( array( "from-header.txt" => "header", "link" => "-> target.txt" ), $this->readEntries( $blocks, $type ) );
    }

    public static function malformedPaxRecords()
    {
        return array(
            array( "0 path=x\n" ),       // A zero length never advances.
            array( "path=x\n" ),         // No length.
            array( "x path=y\n" ),       // Not a number.
            array( "4 path=x\n" ),       // Shorter than its contents.
            array( "99 path=x\n" ),      // Longer than the header.
            array( "10 pathxyz\n" ),     // No "=".
            array( "9 path=xy" ),        // No newline.
        );
    }

    /**
     * @dataProvider malformedPaxRecords
     */
    public function testMalformedPaxRecordsAreRejected( $records )
    {
        foreach ( array( ezcArchive::TAR_GNU, ezcArchive::TAR_PAX ) as $type )
        {
            $blocks = ezcArchiveTarTestBuilder::extension( "x", $records ) .
                      ezcArchiveTarTestBuilder::file( "file.txt", "file" );
            try
            {
                $this->readEntries( $blocks, $type );
                self::fail( "Expected an ezcArchiveException." );
            }
            catch ( ezcArchiveException $e )
            {
                self::assertStringContainsString( "PAX extended header", $e->getMessage() );
            }
        }
    }

    public static function suite()
    {
        return new \PHPUnit\Framework\TestSuite( __CLASS__ );
    }
}
?>
