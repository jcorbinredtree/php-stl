<?php

/**
 * PHPSTLDirectoryProvider class definition
 *
 * PHP version 5
 *
 * LICENSE: The contents of this file are subject to the Mozilla Public License Version 1.1
 * (the "License"); you may not use this file except in compliance with the
 * License. You may obtain a copy of the License at http://www.mozilla.org/MPL/
 *
 * Software distributed under the License is distributed on an "AS IS" basis,
 * WITHOUT WARRANTY OF ANY KIND, either express or implied. See the License for
 * the specific language governing rights and limitations under the License.
 *
 * The Original Code is Red Tree Systems Code.
 *
 * The Initial Developer of the Original Code is
 * Brandon Prudent <php-stl@redtreesystems.com>. All Rights Reserved.
 *
 * @author       Red Tree Systems, LLC <support@redtreesystems.com>
 * @copyright    2007 Red Tree Systems, LLC
 * @license      MPL 1.1
 * @version      1.0
 * @link         http://php-stl.redtreesystems.com
 */

/**
 * Implements a basic filesystem-based template provider
 */
class PHPSTLDirectoryProvider extends PHPSTLFileBackedProvider
{
    /**
     * The path exposed by this provider
     */
    protected $path;

    /**
     * Constructor
     *
     * @param pstl PHPSTL
     * @param path string trailing slashes are not needed and will be stripped
     */
    public function __construct(PHPSTL $pstl, $path)
    {
        $path = realpath($path);
        if ($path === false || ! is_dir($path)) {
            throw new RuntimeException("no such directory $path");
        }

        parent::__construct($pstl);
        $this->path = $path;
    }

    /**
     * Subclasses implement this to do basic file based resolution
     *
     * @param resource string
     * @return string the file path
     * @see PHPSTLFileBackedProvider::getResourceFile
     */
    protected function getResourceFile($resource)
    {
        $path = realpath("$this->path/$resource");
        if ($path == false || ! is_file($path)) {
            return null;
        } else {
            return $path;
        }
    }

    /**
     * @return string
     * @see PHPSTLTemplateProvider::__tostring
     */
    public function __tostring()
    {
        return "file://$this->path";
    }
}

?>
