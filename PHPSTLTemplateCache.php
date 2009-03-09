<?php

/**
 * PHPSTLTemplateCache class definition
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
 * @author       Red Tree Systems, LLC <support@redtreesystems.com>
 * @copyright    2007 Red Tree Systems, LLC
 * @license      MPL 1.1
 * @version      1.6
 * @link         http://php-stl.redtreesystems.com
 */

/**
 * PHPSTLTemplateCache
 *
 * Abstract base class for caching compiled templates
 *
 * For a sensible default implementation see PHPSTLDiskCashe
 *
 * TODO this may make more sense as an abstract base class with a default
 *      implementation in something like PHPSTLDiskCache
 *
 * Caches compiled templates
 */
abstract class PHPSTLTemplateCache
{
    /**
     * @var PHPSTL
     */
    protected $pstl;

    /**
     * Returns the PHP-STL instance that this cache is a part of
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
     * Returns the cache name of the template, this is a unique string that
     * only makes sense to the cache implementation, don't rely on it for
     * anything else.
     *
     * Implementation should make use of the string representation of the
     * template's provider and the template's resource string when building this
     * string so that every template has a repeatably unique cache name.
     *
     * @param template PHPSTLTemplate
     * @return string
     * @see PHPSTLTemplateProvider::__tostring, PHPSTLTemplate::$resource
     */
    abstract public function cacheName(PHPSTLTemplate $template);

    /**
     * Checks if the template is currently cached, whether it's out of date or
     * not
     *
     * @param template PHPSTLTemplate
     * @return boolean
     */
    abstract public function exists(PHPSTLTemplate $template);

    /**
     * Returns whether the cached template is up to date
     *
     * This should be false if exists would return false
     *
     * @param template PHPSTLTemplate
     * @return boolean
     */
    abstract public function isCached(PHPSTLTemplate $template);

    /**
     * Fetches a template from the cache
     *
     * If exists would return fales for this template, this should throw a
     * RuntimeException
     *
     * @param PHPSTLTemplate $template
     * @return array as from store
     * @see store
     */
    abstract public function fetch(PHPSTLTemplate $template);

    /**
     * Stores a template in the cache
     *
     * @param PHPSTLTemplate $template
     * @param array $meta
     * @param string $compiled the compiled content to cache
     * @return array containing two paths:
     * array($metaCache, $contentCache)
     * - $metaCache: path to a serialized associative array containing meta data
     * - $contentCache: path to the compiled php
     */
    abstract public function store(PHPSTLTemplate $template, $meta, $compiled);

    /**
     * Clears the cache completely
     *
     * @return void
     */
    abstract public function clear();
}

?>
