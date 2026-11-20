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

/**
 * Builds tar archives block by block, so that tests can create archives
 * with headers that the system tar would never write.
 *
 * @package Archive
 * @version //autogen//
 * @subpackage Tests
 */
class ezcArchiveTarTestBuilder
{
    const MAGIC_GNU   = "ustar  \0";
    const MAGIC_USTAR = "ustar\00000";

    /**
     * Returns a 512 byte header block with a valid checksum.
     *
     * @param string $name
     * @param string $type   The type flag, a single character.
     * @param int    $size
     * @param string $link
     * @param string $mode   The file mode as an octal string.
     * @param string $magic
     * @return string
     */
    public static function header( $name, $type, $size, $link = "", $mode = "0644", $magic = self::MAGIC_GNU )
    {
        $header = str_pad( $name, 100, "\0" ) .
                  sprintf( "%07s\0", $mode ) .
                  sprintf( "%07o\0", 1000 ) .
                  sprintf( "%07o\0", 1000 ) .
                  sprintf( "%011o\0", $size ) .
                  sprintf( "%011o\0", 1200000000 ) .
                  "        " .
                  $type .
                  str_pad( $link, 100, "\0" ) .
                  $magic .
                  str_pad( "user", 32, "\0" ) .
                  str_pad( "group", 32, "\0" ) .
                  "0000000\0" .
                  "0000000\0" .
                  str_repeat( "\0", 167 );

        $checksum = 0;
        for ( $i = 0; $i < 512; $i++ )
        {
            $checksum += ord( $header[$i] );
        }

        return substr_replace( $header, sprintf( "%06o\0 ", $checksum ), 148, 8 );
    }

    /**
     * Returns $data padded with NUL characters to a multiple of 512 bytes.
     *
     * @param string $data
     * @return string
     */
    public static function data( $data )
    {
        $rest = strlen( $data ) % 512;
        return $rest === 0 ? $data : $data . str_repeat( "\0", 512 - $rest );
    }

    /**
     * Returns a regular file entry: header and data blocks.
     *
     * @param string $name
     * @param string $contents
     * @param string $mode
     * @return string
     */
    public static function file( $name, $contents, $mode = "0644" )
    {
        return self::header( $name, "0", strlen( $contents ), "", $mode ) . self::data( $contents );
    }

    /**
     * Returns an extension header (for example "L", "K", "x" or "g") with its payload.
     *
     * @param string $type
     * @param string $payload
     * @return string
     */
    public static function extension( $type, $payload )
    {
        return self::header( "././@LongLink", $type, strlen( $payload ) ) . self::data( $payload );
    }

    /**
     * Returns a PAX record "<length> <keyword>=<value>\n".
     *
     * @param string $keyword
     * @param string $value
     * @return string
     */
    public static function paxRecord( $keyword, $value )
    {
        $record = " {$keyword}={$value}\n";
        $length = strlen( $record );
        while ( strlen( $length . $record ) != $length )
        {
            $length = strlen( $length . $record );
        }
        return $length . $record;
    }

    /**
     * Writes $blocks followed by the two end-of-archive blocks to $fileName.
     *
     * @param string $fileName
     * @param string $blocks
     * @return string  The file name.
     */
    public static function write( $fileName, $blocks )
    {
        file_put_contents( $fileName, $blocks . str_repeat( "\0", 1024 ) );
        return $fileName;
    }
}
?>
