<?php

/**
 * PHPSTLTemplate class definition
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
 * Represents a template
 */
class PHPSTLTemplate
{
    /**
     * The PHPSTLTemplateProvider that created this template
     */
    private $provider;

    /**
     * The resource string represented by this template
     *
     * Resource strings only have meaning with respect to a
     * PHPSTLTemplateProvider, providers create PHPSTLTemplates from resource
     * strings.
     */
    private $resource;

    /**
     * The compiled form, currently a path to a php file for include()ing.
     */
    private $compiled = null;

    /**
     * Holds the string representation returned by __toString
     *
     * This is determined in the constructor since the provider might change
     * its mind about itself between template instantiation and a call to our
     * __tostring; this would be undesirable since a template's __tostring needs
     * to be stable for caching.
     */
    private $stringRepr;

    /**
     * Constructor
     *
     * @param provider PHPSTLTemplateProvider the provider that created this
     * template
     * @param resource string the resource that this template was created from
     * @param identifier string override the normal identifier string returned
     * by __tostring, normally bulit as $provider.'/'.$resource
     */
    public function __construct(PHPSTLTemplateProvider $provider, $resource, $identifier=null)
    {
        $this->provider = $provider;
        $this->resource = $resource;

        if (isset($identifier)) {
            $this->stringRepr = $identifier;
        } else {
            $this->stringRepr = ((string) $this->provider).'/'.$this->resource;
        }
    }

    /**
     * @see $resource
     * @return string
     */
    public function getResource()
    {
        return $this->resource;
    }

    /**
     * @see $provider
     * @return PHPSTLTemplateProvider
     */
    public function getProvider()
    {
        return $this->provider;
    }

    /**
     * Returns a unix timestamp indicating when the template resource was last
     * modified.
     *
     * Equivalent to:
     *   $template->getProvider()->getLastModified($template)
     *
     * @return int timestamp
     */
    public function getLastModified()
    {
        return $this->provider->getLastModified($this);
    }

    /**
     * Returns raw template content
     *
     * Equivalent to:
     *   $template->getProvider()->getContent($template)
     */
    public function getContent()
    {
        return $this->provider->getContent($this);
    }

    /**
     * Assigns asingle template argument
     *
     * Template arguments are simply object properties on the PHPSTLTemplate
     * object itself
     *
     * @param string $name the name to assign to
     * @param mixed $val the value to assign to $name
     * @return mixed the old value
     */
    public function assign($name, $val)
    {
        if (! $name) {
            throw new InvalidArgumentException('name can not be empty');
        }

        $cls = new ReflectionClass(get_class($this));
        try {
            $prop = $cls->getProperty($name);
        } catch (ReflectionException $e) {
            $prop = null;
        }
        if (isset($prop)) {
            if (! $prop->isPublic()) {
                throw new InvalidArgumentException(
                    "attempt to assign to non-public property $name"
                );
            }
        }

        if (property_exists($this, $name)) {
            $old = $this->$name;
        } else {
            $old = null;
        }

        if (isset($val)) {
            $this->$name = $val;
        } elseif (property_exists($this, $name)) {
            unset($this->$name);
        }

        return $old;
    }

    /**
     * Assigns multipe template arguments
     *
     * Returns a named array of old values such that calling setArguments again
     * with it will undo the prior call.
     *
     * If setting any one of the arguments raises an exception, the entire
     * change set is undone and the exception propogated.
     *
     * @param args array
     * @return array
     * @see assign
     */
    public function setArguments($args)
    {
        if (! is_array($args)) {
            throw new InvalidArgumentException('not an array');
        }
        if (! count($args)) {
            return array();
        }
        $old = array();
        try {
            foreach ($args as $name => &$value) {
                $old[$name] = $this->assign($name, $value);
            }
        } catch (Exception $ex) {
            try {
                $this->setArguments($old);
            } catch (Exception $swallow) {}
            throw $ex;
        }
        return $old;
    }

    /**
     * Gets the compiled form of this template
     *
     * @see $compiled, PHPSTLCompiler::compile
     * @return string
     */
    public function getCompiled()
    {
        if (! isset($this->compiled)) {
            $pstl = $this->provider->getPHPSTL();
            $this->compiled = $pstl->getCompiler()->compile($this);
        }
        return $this->compiled;
    }

    /**
     * Convenience method
     *   Equivalent to:
     *     $template->getProvider()->getPHPSTL()->getCache()->cacheName($template)
     *
     * @return string the cacheName for this template
     * @see PHPSTLTemplateCache::cacheName
     */
    public function cacheName()
    {
        return $this->provider->getPHPSTL()->getCache()->cacheName($this);
    }

    /**
     * Renders the template
     *
     * @param ars array optional, if non-null, setArguments will be called
     * befor rendering with this paramater, then called again after rendering
     * to restore.
     *
     * @return string
     */
    public final function render($args=null)
    {
        try {
            $this->renderSetup($args);

            $compiled = $this->getCompiled();
            ob_start();
            include $compiled;
            $ret = ob_get_clean();
        } catch (Exception $ex) {
            ob_end_clean();
            $this->renderCleanup();
            throw $ex;
        }
        $this->renderCleanup();
        return $ret;
    }

    private $oldArgs = null;

    /**
     * Sets up any needed state to render the template
     *
     * Subclasses should override this and the following renderCleanup method
     * rather than render.
     *
     * @param args array as in render
     * @return void
     * @see render, renderCleanup
     */
    protected function renderSetup($args)
    {
        if (isset($args)) {
            $this->oldArgs = $this->setArguments($args);
        }
    }

    /**
     * Essentially the inverse of renderSetup
     *
     * @return void
     * @see render, renderSetup
     */
    protected function renderCleanup()
    {
        if (isset($this->oldArgs)) {
            $this->setArguments($this->oldArgs);
            $this->oldArgs = null;
        }
    }

    /**
     * Returns string representation of this template like:
     *   "{provider}/{resource}"
     * where "{provider}" and "{resource}" are the provider string
     * representation and the resource string respectively
     *
     * Example:
     *   $pstl = new PHPSTL(array(
     *     'include_path' => '/some/template/dir'
     *   ));
     *   print (string) $pstl->load('task/template.xml');
     *   // prints "file:///some/template/dir/task/template.xml"
     *
     * @return string
     */
    public function __tostring()
    {
        return $this->stringRepr;
    }

    /**
     * Data needed by the template provider, this property shouldn't be accessed
     * by anything other than the provider, doing so can result in undefined
     * results.
     *
     * @var mixed
     */
    public $providerData;
}

?>
