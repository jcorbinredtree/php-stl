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
 * PHPSTLTemplate
 *
 * This class is a simple template, used for those who don't want the PHPSavant or Smarty
 * dependency
 */
class PHPSTLTemplate
{
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
            throw new IllegalArgumentException('name can not be empty');
        }

        $this->$name = $val;
    }

    /**
     * Gets a template's output
     *
     * @param string $template a path to a template
     * @return string
     */
    public function fetch($template)
    {
        $compiler = new $this->compiler();

        $foundTemplate = $template;
        if (!file_exists($template)) {
            foreach ($this->paths as $path) {
                $foundTemplate = "$path/$template";
                if (file_exists($foundTemplate)) {
                    break;
                }
            }
        }

        $compiled = $compiler->compile($foundTemplate, Compiler::TYPE_BUILTIN);

        ob_start();
        include $compiled;
        return ob_get_clean();
    }

    /**
     * Displays a template
     *
     * @param string $template a path to a template
     * @return void
     */
    public function display($template)
    {
        print $this->fetch($template);
    }
}

?>
