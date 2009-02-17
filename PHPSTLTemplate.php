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

require_once(dirname(__FILE__).'/Compiler.php');

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
     * @var Compiler
     */
    private $compiler = null;

    /**
     * Holds a list of paths to search for templates
     *
     * @var array
     */
    private $paths = array();

    private $file;

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
    public function setCompiler(Compiler &$compiler)
    {
        $this->compiler = $compiler;
    }

    /**
     * Returns the compiler object to use for compilation.
     *
     * If not set yet, will call setupCompiler to initialize the compiler
     *
     * @return Compiler
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
     * creates and returns a new instance of Compiler every time.
     *
     * @return Compiler
     */
    protected function setupCompiler()
    {
        return new Compiler();
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
     * Assign $val to this->$name
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

        if (in_array($name, array('compiler', 'file', 'paths'))) {
            throw new InvalidArgumentException(
                "Won't squash proticted or private member '$name'"
            );
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
     * Gets a template's output
     *
     * DEPRECATED
     *   Instead of creating a generic template and telling it to fetch things,
     *   you should just create a template from the file and render it
     *
     * @param string $template a path to a template
     * @return string
     */
    public function fetch($template)
    {
        if (! self::isFileAbsolute($template)) {
            $foundTemplate = $this->pathLookup($template);
            if (isset($foundTemplate)) {
                $template = $foundTemplate;
            } else {
                throw new RuntimeException(
                    "Unable to find template $template, ".
                    "search path contains: ".
                    implode(', ', $this->paths)
                );
            }
        }

        return $this->fetchTemplate($template);
    }

    /**
     * Loads the template in the given file
     *
     * @param template string path to a template file, no checking is done on
     * the argument, it's the caller's responsibility to make sure it exists, if
     * not Compiler::compile will throw an exception
     *
     * @return string template content
     */
    protected function fetchTemplate($template)
    {
        $compiler = $this->getCompiler();
        $compiled = $compiler->compile($template);

        ob_start();
        include $compiled;
        return ob_get_clean();
    }

    /**
     * Displays a template
     *
     * DEPRECATED
     *   see the note on fetch
     *
     * @param string $template a path to a template
     * @return void
     */
    public function display($template)
    {
        print $this->fetch($template);
    }

    /**
     * Renders the template
     *
     * @return string
     */
    public function render()
    {
        return $this->fetchTemplate($this->file);
    }
}

?>
