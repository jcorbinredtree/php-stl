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
class PHPSTLDirectoryProvider extends PHPSTLTemplateProvider
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
     * Loads a template resource
     *
     * @param resource string
     * @return mixed PHPSTLTemplate, PHPSTLTemplateProvider::DECLINE, or
     * PHPSTLTemplateProvider::FAIL
     * @see PHPSTL::load
     */
    public function load($resource)
    {
        $path = realpath("$this->path/$resource");
        if ($path !== false && is_file($path)) {
            return $this->createTemplate($resource, $path);
        }
        return PHPSTLTemplateProvider::DECLINE;
    }

    /**
     * Returns a unix timestamp indicating when the template resource was last
     * modified.
     *
     * @param template PHPSTLTemplate
     * @return int timestamp
     */
    public function getLastModified(PHPSTLTemplate $template)
    {
        assert(is_a($template->getProvider(), 'PHPSTLDirectoryProvider'));
        $file = $template->providerData;
        assert(file_exists($file));
        return filemtime($file);
    }

    /**
     * Gets raw template content
     *
     * @param template PHPSTLTemplate
     * @return string
     */
    public function getContent(PHPSTLTemplate $template)
    {
        assert(is_a($template->getProvider(), 'PHPSTLDirectoryProvider'));
        $file = $template->providerData;
        assert(file_exists($file));
        ob_start();
        $content = file_get_contents($file);
        if ($content === false) {
            $mess = ob_get_clean();
            throw new RuntimeException("Failed to read $file: $mess");
        }
        ob_end_flush();
        return $content;
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
