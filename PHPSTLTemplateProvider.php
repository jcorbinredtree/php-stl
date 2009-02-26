<?php

/**
 * PHPSTLTemplateProvider class definition
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
 * Abstract template provider
 *
 * A template provider maps string resource names to PHPSTLTemplate objects
 *
 * PHPSTLDirectoryProvider provides traditional file-to-template mapping
 */
abstract class PHPSTLTemplateProvider
{
    /**
     * Iterates a list of providers in an attemp to load a resource
     *
     * @param providers array of PHPSTLTemplateProvider
     * @param resource string
     * @return PHPSTLTemplate on success, null otherwise
     * @see PHPSTLTemplateProvider::load
     */
    public static function provide($providers, $resource)
    {
        assert(is_array($providers));
        assert(is_string($resource));
        foreach ($providers as $provider) {
            $r = $provider->load($resource);
            if (is_object($r) && is_a($r, 'PHPSTLTemplate')) {
                return $r;
            } elseif ($r === PHPSTLTemplateProvider::DECLINE) {
                continue;
            } elseif ($r === PHPSTLTemplateProvider::FAIL) {
                // A provider can explicitly fail a resource
                break;
            }
        }
        // Either every provider declined, or one of them failed
        return null;
    }

    /**
     * Return constants
     */
    const DECLINE = 1;
    const FAIL    = 2;

    /**
     * The PHP-STL system
     *
     * @var PHPSTL
     */
    protected $pstl;

    /**
     * Returns the PHP-STL instance that this provider is a part of
     */
    public function getPHPSTL()
    {
        return $this->pstl;
    }

    /**
     * Constructor
     *
     * @param pstl PHPSTL
     */
    public function __construct(PHPSTL $pstl)
    {
        $this->pstl = $pstl;
    }

    /**
     * Creates a template object
     *
     * Subclasses should override this if they're not okay with using templates
     * of the class specified in the template_class option to PHPSTL
     *
     * @param resource string the string resource to pass to the template constructor
     * @param data mixed the value to set as $template->providerData
     * @param identified string identifier string override as in PHPSTLTemplate::__construct
     */
    protected function createTemplate($resource, $data, $identifier=null)
    {
        $class = $this->pstl->getOption('template_class', 'PHPSTLTemplate');
        $template = new $class($this, $resource, $identifier);
        $template->providerData = $data;
        return $template;
    }

    /**
     * Loads a template resource
     *
     * @param resource string
     * @return mixed PHPSTLTemplate, PHPSTLTemplateProvider::DECLINE, or
     * PHPSTLTemplateProvider::FAIL
     * @see PHPSTL::load
     */
    abstract public function load($resource);

    /**
     * Returns a unix timestamp indicating when the template resource was last
     * modified.
     *
     * @param template PHPSTLTemplate
     * @return int timestamp
     */
    abstract public function getLastModified(PHPSTLTemplate $template);

    /**
     * Gets raw template content
     *
     * @param template PHPSTLTemplate
     * @return string
     */
    abstract public function getContent(PHPSTLTemplate $template);

    /**
     * Returns a unique identifier for this template provider
     *
     * Subclasses must define this so that the caching mechanism can uniquely
     * identify templates
     *
     * For example, a directory based template provider would simply return
     * the full path to the directory it maps
     *
     * @return string
     */
    abstract public function __tostring();
}

?>
