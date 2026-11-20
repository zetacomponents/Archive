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
     * "K" (long link name), and "x" and "g" (PAX extended and global headers, which are
     * skipped). Every other type flag, including NUL, is the header of an entry.
     *
     * @throws ezcArchiveValueException
     *         if an extension header is larger than {@link MAX_EXTENSION_SIZE}.
     * @throws ezcArchiveBlockSizeException
     *         if the archive ends inside or directly after an extension header.
     * @param ezcArchiveBlockFile $file
     */
    public function __construct( ezcArchiveBlockFile $file = null )
    {
        if ( !is_null( $file ) )
        {
            $fileName = null;
            $linkName = null;

            // Every extension header moves the block position forward by at
            // least one block, so this loop ends at the latest at the end of
            // the archive.
            do
            {
                parent::__construct( $file );

                $isExtension = true;
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
                    case "g":
                        // PAX extended and global headers are not supported; skip the payload.
                        $this->readExtension( $file );
                        break;

                    default:
                        $isExtension = false;
                        break;
                }
            } while ( $isExtension );

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
        }
    }

    /**
     * Reads an extended set of data from the Block file and returns it as a string.
     *
     * Some filenames or link names do not fit in the Ustar header, and are therefor placed in a new block.
     * This method read the block(s) and returns the data as a string. After this method returns, the
     * block position of $file points to the header that follows the payload.
     *
     * @throws ezcArchiveValueException
     *         if the payload is larger than {@link MAX_EXTENSION_SIZE}.
     * @throws ezcArchiveBlockSizeException
     *         if the archive ends before the payload or the next header.
     * @param ezcArchiveBlockFile $file
     * @return string
     */
    protected function readExtension( ezcArchiveBlockFile $file )
    {
        if ( $this->fileSize > self::MAX_EXTENSION_SIZE )
        {
            throw new ezcArchiveValueException( $this->fileSize, "an extension header size of at most " . self::MAX_EXTENSION_SIZE . " bytes" );
        }

        $blocks = $file->getBlocksFromBytes( $this->fileSize );

        $data = "";
        for ( $i = 0; $i < $blocks; $i++ )
        {
            $block = $file->next();
            if ( $block === false )
            {
                throw new ezcArchiveBlockSizeException( $file->getFileName(), "The archive ends inside an extension header." );
            }
            $data .= $block;
        }

        // Move to the header the extension applies to.
        if ( $file->next() === false )
        {
            throw new ezcArchiveBlockSizeException( $file->getFileName(), "The archive ends after an extension header." );
        }

        return substr( $data, 0, $this->fileSize );
    }
}
?>
