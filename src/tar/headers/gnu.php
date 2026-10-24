<?php
/**
 * File contains the ezcArchiveGnuHeader class.
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
 * @package Archive
 * @version //autogentag//
 * @license http://www.apache.org/licenses/LICENSE-2.0 Apache License, Version 2.0
 * @access private
 */

/**
 * The ezcArchiveGnuHeader class represents the Tar Gnu header.
 *
 * ezcArchiveGnuHeader can read the header from an ezcArchiveBlockFile or ezcArchiveEntry.
 *
 * The values from the headers are directly accessible via the class properties, and allows
 * reading and writing to specific header values.
 *
 * The entire header can be appended to an ezcArchiveBlockFile again or written to an ezcArchiveFileStructure.
 * Information may get lost, though.
 *
 * The header is the {@link ezcArchiveUstarHeader} with the extension that the type can be set to "L" or "K".
 * Respectively, long filename and long link. If the type is set to either "L" or "K", the next block contains the
 * filename or link. The block thereafter repeats the original header but the type is then set to a digit.
 * (No long filename or link).
 *
 * @package Archive
 * @version //autogentag//
 * @access private
 */
class ezcArchiveGnuHeader extends ezcArchiveUstarHeader
{
    /**
     * Creates and initializes a new header.
     *
     * If the ezcArchiveBlockFile $file is null then the header will be empty.
     * When an ezcArchiveBlockFile is given, the block position should point to the header block.
     * This header block will be read from the file and initialized in this class.
     *
     * Extension headers that precede the actual header are consumed: "L" (long file name),
     * "K" (long link name), "x" (PAX extended header, of which path, linkpath, size, uid and
     * gid are used) and "g" (PAX global header, which is skipped). Every other type flag,
     * including NUL, is the header of an entry.
     *
     * @throws ezcArchiveValueException
     *         if an extension header is larger than {@link MAX_EXTENSION_SIZE}.
     * @throws ezcArchiveBlockSizeException
     *         if the archive ends inside or directly after an extension header.
     * @throws ezcArchiveException
     *         if a PAX extended header is malformed.
     * @param ezcArchiveBlockFile $file
     */
    public function __construct( ezcArchiveBlockFile $file = null )
    {
        if ( !is_null( $file ) )
        {
            $fileName = null;
            $linkName = null;
            $paxRecords = array();

            // Every extension header moves the block position forward by at
            // least one block, so this loop ends at the latest at the end of
            // the archive.
            parent::__construct( $file );
            while ( in_array( $this->type, array( "L", "K", "x", "g" ), true ) )
            {
                switch ( $this->type )
                {
                    case "L":
                        // GNU tar stores the name with a terminating NUL.
                        $fileName = rtrim( $this->readExtension( $file ), "\0" );
                        break;

                    case "K":
                        $linkName = rtrim( $this->readExtension( $file ), "\0" );
                        break;

                    case "x":
                        $paxRecords = array_merge( $paxRecords, $this->decodePaxRecords( $this->readExtension( $file ) ) );
                        break;

                    case "g":
                        // Global PAX headers are not supported; skip the payload.
                        $this->readExtension( $file );
                        break;
                }

                parent::__construct( $file );
            }

            // An empty extension does not replace the name from the header.
            if ( $fileName !== null && $fileName !== "" )
            {
                $this->fileName = $fileName;
                $this->filePrefix = "";
            }

            if ( $linkName !== null && $linkName !== "" )
            {
                $this->linkName = $linkName;
            }

            $this->applyPaxRecords( $paxRecords );
        }
    }
}
?>
