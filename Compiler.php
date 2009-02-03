<?php

/**
 * Compiler class definition
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
 * @version      1.6
 * @link         http://php-stl.redtreesystems.com
 */

/**
 * Compiler
 *
 * This class provides the xml => php translation, modeled after the JSTL. An implementation
 * for PHPSavant and Smarty is included.
 */
class Compiler
{
    /**
     * A simple constant to define that we are using phpsavant
     *
     * @var int
     */
    const TYPE_PHPSAVANT = 1;

    /**
     * A simple constant to define that we are using smarty
     *
     * @var int
     */
    const TYPE_SMARTY = 2;

    /**
     * Use the builtin type
     *
     * @var int
     */
    const TYPE_BUILTIN = 3;

    /**
     * The directory where compiled templates should be stored
     */
    private static $compileDir = '/tmp';

    /**
     * The default compiler class. This has to be set as a static
     * item because there is no way in PHP to get the name of a static
     * superclass
     *
     * @var string the class name used for compilation
     */
    private static $compilerClass = 'Compiler';

    /**
     * The DOM object
     *
     * @var DOMDocument
     */
    private $dom;

    /**
     * Sets the compiler type
     *
     * @var TYPE_PHPSAVANT|TYPE_SMARTY
     */
    private $type;

    /**
     * The buffer to write to
     *
     * @var string
     */
    private $buffer;

    /**
     * The source file
     *
     * @var string
     */
    private $file;

    /**
     * The ouput php file
     *
     * @var string
     */
    private $compiledFile;

    /**
     * Our map of classes which are being handled by xmlns's
     *
     * @var array
     */
    private $handlerMap = array();

    /**
     * Set the directory to put the compiled templates
     *
     * @param string $dir
     * @return void
     */
    public static function setCompileDirectory($dir)
    {
        Compiler::$compileDir = $dir;
    }

    /**
     * Sets the class of the compiler to use
     *
     * @param string $to class name
     * @return void
     */
    public static function setCompilerClass($to)
    {
        Compiler::$compilerClass = $to;
    }

    /**
     * The builtin && PHPSavant implementation
     *
     * @param string $file the source file
     * @return string the location of the compiled file
     */
    public static function compile($file, $type=Compiler::TYPE_PHPSAVANT)
    {
        $compiler = new Compiler::$compilerClass($file);
        $compiler->type = $type;

        if ($compiler->notModified()) {
            return $compiler->getCompiledFile();
        }

        $compiler->parse(file_get_contents($file));

        $fh = fopen($compiler->getCompiledFile(), 'w');
        if (!$fh) {
            die("could not open $compiler->compiledFile for writing!");
        }

        fwrite($fh, $compiler->buffer);

        fclose($fh);

        return $compiler->getCompiledFile();
    }

    /**
     * Constructor
     *
     * @param string $file
     * @return Compiler
     */
    public function __construct($file='')
    {
        $this->file = $file;
        $file = preg_replace('|[^a-z0-9_]|i', '_', $file);
        $this->compiledFile = Compiler::$compileDir . "$file.php";
    }

    /**
     * The Smarty implementation
     *
     * sets $compiled_content to the compiled source
     * @param string $resource_name
     * @param string $source_content
     * @param string $compiled_content
     * @return true on success
     */
    public function _compile_file($resource_name, $source_content, &$compiled_content)
    {
        $this->type = Compiler::TYPE_SMARTY;

        $this->file = $resource_name;

        $this->parse($source_content);
        $compiled_content = $this->buffer;

        return true;
    }

    /**
     * Determines if the compiled file has been modified since the source file
     *
     * @return true if there is no need to re-compile
     */
    public function notModified()
    {
        if (!file_exists($this->compiledFile)) {
            return false;
        }

        return filemtime($this->compiledFile) >= filemtime($this->file);
    }

    /**
     * The main compilation function - called recursivley to process
     *
     * @param DOMNode $currentNode
     * @return void
     */
    public function process(DOMNode $currentNode)
    {
        if ($currentNode->nodeType == XML_COMMENT_NODE) {
            return;
        }

        if (($currentNode->nodeType == XML_TEXT_NODE) || ($currentNode->nodeType == XML_CDATA_SECTION_NODE)) {
            $this->write($currentNode->nodeValue);
            return;
        }

        /*
         * There's a handler for this node
         */
        if ($currentNode->namespaceURI) {
            if ($handler = $this->getClass($currentNode->namespaceURI)) {
                $method = preg_replace('|(\w+):(.+)|', '$2', $currentNode->nodeName);
                if (!method_exists($handler, $method)) {
                    $method = "_$method";
                }

                $handler->$method($currentNode);
                return;
            }
        }

        $this->write("<$currentNode->nodeName");

        if ($currentNode->hasAttributes()) {
            foreach ($currentNode->attributes as $attr) {
                $this->write(' ' . $attr->name . ' = "' . $attr->value . '"');
            }
        }

        /*
         * make some exceptions for weirdo tags...
         */
        if ($currentNode->hasChildNodes() || (
            ($currentNode->nodeName != 'meta')
            && ($currentNode->nodeName != 'link')
            && ($currentNode->nodeName != 'br')
            && ($currentNode->nodeName != 'hr')
            && ($currentNode->nodeName != 'img')
            && ($currentNode->nodeName != 'input')))
        {
            $this->write('>');

            foreach ($currentNode->childNodes as $child) {
                $this->process($child);
            }

            $this->write("</$currentNode->nodeName>");
        }
        else {
            $this->write(' />');
        }
    }

    /**
     * Writes data to the output
     *
     * @param string $out the data to write out
     * @return void
     */
    public function write($out)
    {
        $out = $this->replaceRules($out);
        $this->buffer .= $out;
    }

    /**
     * Returns the path of the compiled file
     *
     * @return string
     */
    public function getCompiledFile()
    {
        return $this->compiledFile;
    }

    /**
     * Gets the class that will be used to handle $uri
     *
     * @param string $uri the specified xmlns uri
     * @return Tag an instance of Tag to be used to process this tag
     */
    private function getClass($uri)
    {
        $matches = array();

        if (!preg_match('|^class[:]//(.+)|', $uri, $matches)) {
            return null;
        }

        $class = $matches[1];

        if (isset($this->handler[$class])) {
            return $this->handler[$class];
        }

        if (!class_exists($class)) {
            die("class $class of $uri does not exist, specified in $this->file");
        }

        $this->handler[$class] = new $class($this);
        if (!($this->handler[$class] instanceof Tag)) {
            die("$uri does not specify a class extending Tag");
        }

        return $this->handler[$class];
    }

    /**
     * The replacement rules for the "expression" syntax
     *
     * @param string $output the current output being written
     * @return string replaced output
     */
    private function replaceRules($output)
    {
        $output = preg_replace("/[$][{]([^=]+?)[}]/e", "'\$'.preg_replace('/(?<![.])[.](?![.])/','->','\\1')", $output);
        $output = preg_replace("/[@][{]([^=]+?)[}]/", '$1', $output);

        $output = preg_replace("/[$][{][=](.+?)[}]/", '<?php echo ${$1}; ?>', $output);
        $output = preg_replace("/[@][{][=](.+?)[}]/", '<?php echo $1; ?>', $output);

        /*
         * Yes, this is the same as the first set of regexs
         * Yes, this is slow
         * No, it's not a big deal
         */
        $output = preg_replace("/[$][{]([^=]+?)[}]/e", "'\$'.preg_replace('/(?<![.])[.](?![.])/','->','\\1')", $output);
        $output = preg_replace("/[@][{]([^=]+?)[}]/", '$1', $output);

        if ($this->type == Compiler::TYPE_SMARTY) {
            $output = preg_replace('/[$]this[-][>](\w+)/i', '$this->_tpl_vars[\'\\1\']', $output);
            $output = preg_replace('/[$]this[-][>]_tpl_vars[[]'."'(.+?)'[]][(]/i", '$this->$1(', $output);
        }

        return $output;
    }

    /**
     * Parses the current file into the compiled output
     *
     * @param string $contents the source content
     * @return void
     */
    private function parse($contents)
    {
        $this->dom = new DOMDocument();
        $this->dom->preserveWhiteSpace = true;

        if (!$this->dom->loadXML($contents)) {
            die("failed to parse $this->file");
        }

        $this->dom->normalizeDocument();

        foreach ($this->dom->documentElement->childNodes as $node) {
            $this->process($node);
        }

        /*
         * normalize php blocks
         */
        $this->buffer = preg_replace('/[?][>]\s*?[<][?]php/si', '', $this->buffer);
    }
}
?>
