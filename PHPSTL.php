<?php

/**
 * PHPSTL class definition
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

require_once(dirname(__FILE__).'/PHPSTLTemplate.php');
require_once(dirname(__FILE__).'/PHPSTLTemplateCache.php');
require_once(dirname(__FILE__).'/PHPSTLDiskCache.php');
require_once(dirname(__FILE__).'/PHPSTLCompiler.php');
require_once(dirname(__FILE__).'/PHPSTLTemplateProvider.php');
require_once(dirname(__FILE__).'/PHPSTLFileBackedProvider.php');
require_once(dirname(__FILE__).'/PHPSTLDirectoryProvider.php');

/**
 * The primary interface to PHP-STL, manages all the other parts
 */
class PHPSTL
{
    /**
     * The compiler used to compile templates
     *
     * @var PHPSTLCompiler
     */
    protected $compiler;

    /**
     * The cache used to cache compilation
     *
     * @var PHPSTLTemplateCache
     */
    protected $cache;

    /**
     * List of PHPSTLTemplateProviders
     */
    protected $providers = array();

    /**
     * Arbitrary named options array, set to the values passed in the
     * constructor primarily for other parts of PHP-STL to poke at
     */
    protected $options = array();

    /**
     * Sets up a new PHP-STL templating system
     *
     * @param options arary optinal named array of options
     *   Options (all are optional):
     *     include_path    can be either a comma-separated string list of directories or
     *                     an array of directory strings; a PHPSTLDirectoryProvider is
     *                     created for each item in the list
     *     allow_abs_path  whether to allow absolute file paths, default false
     *     cache_clas      a class derived from PHPSTLTemplateCache
     *     compiler_class  PHPSTLCompiler or a subclass of it
     *     template_class  PHPSTLTemplate or a subclass of it, this is an advisory to the
     *                     providers; the builtin php-stl providers will honor this, but
     *                     it's conceivable that a specific subclass may have a better
     *                     notion of what class it should use for the templates it provides
     */
    public function __construct($options=array())
    {
        $this->options = array_merge($this->options, $options);

        $cacheClass = $this->getCacheClass();
        if (! is_subclass_of($cacheClass, 'PHPSTLTemplateCache')) {
            throw new InvalidArgumentException(
                "cache_class $cacheClass isn't derived from PHPSTLTemplateCache"
            );
        }

        $compilerClass = $this->getComplilerClass();
        if (
            $compilerClass != 'PHPSTLCompiler' &&
            ! is_subclass_of($compilerClass, 'PHPSTLCompiler')
        ) {
            throw new InvalidArgumentException(
                "compiler_class $compilerClass isn't derived from PHPSTLCompiler"
            );
        }

        $templateClass = $this->getTemplateClass();
        if (
            $templateClass != 'PHPSTLTemplate' &&
            ! is_subclass_of($templateClass, 'PHPSTLTemplate')
        ) {
            throw new InvalidArgumentException(
                "template_class $templateClass isn't derived from PHPSTLTemplate"
            );
        }

        $this->cache = new $cacheClass($this);
        $this->compiler = new $compilerClass($this);

        $inc = $this->getIncludePath();
        if (isset($inc)) {
            foreach ($inc as $incDir) {
                if (is_dir($incDir)) {
                    $provider = new PHPSTLDirectoryProvider($this, $incDir);
                    $this->addProvider($provider);
                }
            }
        }
    }

    public function getCacheClass()
    {
        return $this->getOption('cache_class', 'PHPSTLDiskCache');
    }

    public function getComplilerClass()
    {
        return $this->getOption('compiler_class', 'PHPSTLCompiler');
    }

    public function getTemplateClass()
    {
        return $this->getOption('template_class', 'PHPSTLTemplate');
    }

    /**
     * Returns the compiler
     */
    public function getCompiler()
    {
        return $this->compiler;
    }

    /**
     * Returns the cache
     */
    public function getCache()
    {
        return $this->cache;
    }

    /**
     * Gets the named option
     *
     * @param name string the option name
     * @param default the default option value, defaults to null
     * @return mixed the value
     */
    public function getOption($name, $default=null)
    {
        if (
            array_key_exists($name, $this->options) &&
            isset($this->options[$name])
        ) {
            return $this->options[$name];
        } else {
            return $default;
        }
    }

    /**
     * Returns the include_path option as an array if it is set, null otherwise
     *
     * @return mixed null or array
     */
    public function getIncludePath() {
        $inc = $this->getOption('include_path');
        if (isset($inc)) {
            if (is_string($inc)) {
                $inc = explode(',', $inc);
            } elseif (! is_array($inc)) {
                throw InvalidArgumentException(
                    'include_path not a string or array'
                );
            }
        }
        return $inc;
    }


    /**
     * Adds a provider to this PHP-STL system, the default behavior is to append
     * this provider onto the end of the list of providers
     *
     * @param provider PHPSTLTemplateProvider the template provider
     * @param prepend boolean if true the provider will be prepended to the head
     *   of the provider list rather than appended to the end if false.
     *   optional, defaults to false
     * @return PHPSTLTemplateProvider the provider added for convenience
     */
    public function addProvider(PHPSTLTemplateProvider $provider, $prepend=false)
    {
        if ($prepend) {
            array_unshift($this->providers, $provider);
        } else {
            array_push($this->providers, $provider);
        }
        return $provider;
    }

    /**
     * Removes the provider from the list of providers
     *
     * Essentially the converse of addProvider
     *
     * @param provider PHPSTLTemplateProvider the template provider
     * @return PHPSTLTemplateProvider the provider added for convenience
     */
    public function removeProvider(PHPSTLTemplateProvider $provider)
    {
        $new = array();
        foreach ($this->providers as $p) {
            if ($p !== $provider) {
                array_push($new, $p);
            }
        }
        $this->providers = $new;
        return $provider;
    }

    /**
     * Returns the list of providers
     *
     * @return array of PHPSTLTemplateProvider
     */
    public function getProviders()
    {
        return $this->providers;
    }

    /**
     * Loads the template named by resource
     *
     * If no provider can load the resource, a PHPSTLNoSuchResource exception
     * is thrown
     *
     * @param resource string the resource to load
     * @return PHPSTLTemplate the loaded template
     */
    public function load($resource)
    {
        $r = PHPSTLTemplateProvider::provide($this->providers, $resource);
        if (isset($r)) {
            return $r;
        } else {
            throw new PHPSTLNoSuchResource($this, $resource);
        }
    }

    /**
     * Convenience method, equivalent to:
     *   $template = $pstl->load($resource);
     *   $return = $template->render($args);
     *
     * @param resource string
     * @param args mixed array or null
     * @return string
     * @see load, PHPSTLTemplate::render
     */
    public function process($resource, $args=null)
    {
        $template = $this->load($resource);
        return $template->render($args);
    }
}

class PHPSTLNoSuchResource extends RuntimeException
{
    private $pstl;
    public function getPHPSTL()
    {
        return $this->pstl;
    }

    private $resource;
    public function getResource()
    {
        return $this->resource;
    }

    public function __construct(PHPSTL $pstl, $resource)
    {
        $this->pstl = $pstl;
        $this->resource = $resource;
        parent::__construct("No such template $resource");
    }
}

?>
