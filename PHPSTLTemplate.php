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
     * The compiler class to use for compilation. Defaults to 'Compiler'
     *
     * @var string
     */
    private $compiler = 'Compiler';

    /**
     * Holds a list of paths to search for templates
     *
     * @var array
     */
    private $paths = array();

    public $template;

    /**
     * Constructor
     *
     * @param template string (optional) the template this instance is compiled from
     */
    public function __construct($template=null)
    {
        if (isset($template)) {
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
            $this->template = $template;
        }
    }

    /**
     * Sets the compiler class
     *
     * @param string $className the compiler class name
     * @return void
     */
    public function setCompiler($className)
    {
        $this->compiler = $className;
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
     * Assign $val to this.$name
     *
     * @param string $name the name to assign to
     * @param mixed $val the value to assign to $name
     * @return void
     */
    public function assign($name, $val)
    {
        if (!$name) {
            throw new InvalidArgumentException('name can not be empty');
        }

        $this->$name = $val;
    }

    /**
     * Looks up the given template in the path list
     *
     * @param string $template a path to a template
     * @return string
     */
    public function pathLookup($template)
    {
        foreach ($this->paths as &$path) {
            $foundTemplate = "$path/$template";
            if (file_exists($foundTemplate)) {
                return $foundTemplate;
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
        $compiler = new $this->compiler();

        $compiled = $compiler->compile($template, Compiler::TYPE_BUILTIN);

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
     * Only works if the $template property is set
     *
     * @return string
     */
    public function render()
    {
        if (! isset($this->template)) {
            throw new RuntimeException('template property not set');
        }
        return $this->fetchTemplate($this->template);
    }
}

?>
