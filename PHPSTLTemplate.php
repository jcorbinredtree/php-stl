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

require_once(dirname(__FILE__).'/PHPSTLCompiler.php');

// Borrowed from Pear File_Util module
if (! defined('FILE_WIN32')) {
    define('FILE_WIN32', defined('OS_WINDOWS') ? OS_WINDOWS : !strncasecmp(PHP_OS, 'win', 3));
}

/**
 * PHPSTLTemplate
 *
 * This class is a simple template, used for those who don't want the PHPSavant or Smarty
 * dependency
 */
class PHPSTLTemplate
{
    // Borrowed from Pear File_Util::isAbsolute
    /**
     * Tests if a path is absolute
     *
     * @param $path string the path
     *
     * @return boolean
     */
    protected static function isFileAbsolute($path)
    {
        if (preg_match('/(?:\/|\\\)\.\.(?=\/|$)/', $path)) {
            return false;
        }
        if (FILE_WIN32) {
            return preg_match('/^[a-zA-Z]:(\\\|\/)/', $path);
        }
        return ($path{0} == '/') || ($path{0} == '~');
    }

    /**
     * The compiler object to use for compiling templates.
     *
     * @var PHPSTLCompiler
     */
    private $compiler = null;

    /**
     * The compiled form, currently a path to a php file for include()ing.
     */
    private $compiled = null;

    /**
     * Holds a list of paths to search for templates
     *
     * @var array
     */
    private $paths = array();

    /**
     * The file that defines this template
     */
    private $file = null;

    /**
     * Constructor
     *
     * @param template string (optional) the template this instance is compiled from
     */
    public function __construct($file)
    {
       if (! self::isFileAbsolute($file)) {
            $foundFile = $this->pathLookup($file);
            if (isset($foundFile)) {
                $file = $foundFile;
            } else {
                throw new RuntimeException(
                    "Unable to find template $file, ".
                    "search path contains: ".
                    implode(', ', $this->paths)
                );
            }
        }
        $this->file = $file;
    }

    /**
     * Gets the file that defines this template
     *
     * @return string path
     */
    public function getFile()
    {
        return $this->file;
    }

    /**
     * Sets the compiler class
     *
     * @param string $className the compiler class name
     * @return void
     */
    public function setCompiler(PHPSTLCompiler &$compiler)
    {
        $this->compiler = $compiler;
    }

    /**
     * Returns the compiler object to use for compilation.
     *
     * If not set yet, will call setupPHPSTLCompiler to initialize the compiler
     *
     * @return PHPSTLCompiler
     */
    public function getCompiler()
    {
        if (! isset($this->compiler)) {
            $this->compiler = $this->setupCompiler();
        }
        return $this->compiler;
    }

    /**
     * Called by getCompiler to setup the compiler, the default implementation
     * creates and returns a new instance of PHPSTLCompiler every time.
     *
     * @return PHPSTLCompiler
     */
    protected function setupCompiler()
    {
        return new PHPSTLCompiler();
    }

    /**
     * Add path $path to the list of paths to search for templates
     *
     * @param string $path the path you wish to add
     * @return void
     */
    public function addPath($path)
    {
        array_push($this->paths, $path);
    }

    /**
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
     * Looks up the given file in the path list
     *
     * @param string $file a path to a template
     * @return string
     */
    public function pathLookup($file)
    {
        foreach ($this->paths as &$path) {
            $foundFile = "$path/$file";
            if (file_exists($foundFile)) {
                return $foundFile;
            }
        }

        return null;
    }

    /**
     * Compiles this template
     *
     * @see $compiled, PHPSTLCompiler::compile
     */
    public function compile()
    {
        if (isset($this->compiled)) {
            return;
        }
        $compiler = $this->getCompiler();
        $this->compiled = $compiler->compile($this);
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

            if (! isset($this->__compiled)) {
                $this->compile();
            }

            ob_start();
            include $this->__compiled;
            $ret = ob_get_clean();
        } catch (Exception $ex) {
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
}

?>
