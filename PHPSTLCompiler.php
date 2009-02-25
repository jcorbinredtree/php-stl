<?php

/**
 * PHPSTLCompiler class definition
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

require_once(dirname(__FILE__).'/Tag.php');
require_once(dirname(__FILE__).'/CoreTag.php');

/**
 * PHPSTLCompiler
 *
 * This class provides the xml => php translation, modeled after the JSTL.
 */
class PHPSTLCompiler
{
    /**
     * @var PHPSTL
     */
    protected $pstl;

    /**
     * Returns the PHP-STL instance that this compiler is a part of
     */
    public function getPHPSTL()
    {
        return $this->pstl;
    }

    /**
     * The DOM object of the template currently being parsed
     *
     * @var DOMDocument
     */
    protected $dom;

    /**
     * The buffer to write to
     *
     * @var string
     */
    private $buffer;
    private $footerBuffer;

    /**
     * The current template
     *
     * @var string
     */
    protected $template=null;

    /**
     * Our map of classes which are being handled by xmlns's
     *
     * @var array
     */
    private $handlerMap = array();

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
     * Returns the template currently being compiled
     *
     * @return string or null
     */
    public function currentTemplate()
    {
        return $this->template;
    }

    /**
     * Returns the current parsing position in the template being complied if
     * known
     *
     * @return string or null
     */
    public function currentPosition()
    {
        return null; // TODO
    }

    /**
     * Compiles a template
     *
     * @param template PHPSTLTemplate template to compile
     * @return string the location of the cache file
     */
    public function compile(PHPSTLTemplate &$template)
    {
        $cache = $this->pstl->getCache();
        try {
            $this->template = $template;
            if (
                $cache->isCached($template) &&
                ! $this->pstl->getOption('always_compile', false)
            ) {
                $ret = $cache->fetch($template);
            } else {
                $content = $this->template->getContent();
                $content = $this->parse($content);
                $ret = $cache->store($template, $content);
            }
            $this->template = null;
            return $ret;
        } catch (Exception $ex) {
            $this->template = null;
            throw $ex;
        }
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

        if (
            $currentNode->nodeType == XML_TEXT_NODE ||
            $currentNode->nodeType == XML_CDATA_SECTION_NODE
        ) {
            $this->write($currentNode->nodeValue);
            return;
        }

        // There's a handler for this node
        if ($currentNode->namespaceURI) {
            if ($handler = $this->getHandler($currentNode->namespaceURI)) {
                $handler->__dispatch($currentNode);
                return;
            }
        }

        $this->write("<$currentNode->nodeName");

        if ($currentNode->hasAttributes()) {
            foreach ($currentNode->attributes as $attr) {
                $this->write(' ' . $attr->name . '="' . $attr->value . '"');
            }
        }

        // make some exceptions for weirdo tags...
        if (
            $currentNode->hasChildNodes() ||
            in_array($currentNode->nodeName, array(
                'meta', 'link', 'br', 'hr', 'img', 'input'
            ))
        ) {
            $this->write('>');

            foreach ($currentNode->childNodes as $child) {
                $this->process($child);
            }

            $this->write("</$currentNode->nodeName>");
        } else {
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
     * Writes data to the footer, this will go after all other normal output
     * @param string $out
     * @return void
     */
    public function writeFooter($out)
    {
        $this->footerBuffer .= $out;
    }

    /**
     * Gets the class that will be used to handle $uri
     *
     * @param string $uri the specified xmlns uri
     * @return Tag an instance of Tag to be used to process this tag
     */
    private function getHandler($uri)
    {
        $matches = array();

        if (! preg_match('|^class://(.+)|', $uri, $matches)) {
            return null;
        }
        $class = $matches[1];

        if (! isset($this->handler[$class])) {
            if (! class_exists($class) && function_exists('__autoload')) {
                __autoload($class);
            }
            if (! class_exists($class)) {
                throw new PHPSTLCompilerException($this,
                    "No such Tag class $class for $uri"
                );
            }

            if (! is_subclass_of($class, 'Tag')) {
                throw new PHPSTLCompilerException($this,
                    "$class is not a subclass of Tag for $uri"
                );
            }

            $this->handler[$class] = new $class($this);
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

        return $output;
    }

    /**
     * Called by parse after the buffer is setup to write an preamble
     *
     * @return void
     */
    protected function writeTemplateHeader($subStats=null)
    {
        $stats = array();
        $stats['Template'] = (string) $this->template;
        $stats['Last Modified'] = strftime('%F %T %Z',
            $this->template->getLastModified()
        );
        $stats['Compile Time'] = strftime('%F %T %Z');

        if (isset($subStats)) {
            $stats = array_merge($stats, $subStats);
        }

        $len = max(array_map('strlen', array_keys($stats)));

        $this->write("<?php\n/**\n");
        foreach ($stats as $title => $value) {
            $this->write(
                " * $title".
                str_repeat(' ', $len-strlen($title)).
                " : $value\n"
            );
        }
        $this->write(" */ ?>\n");
    }

    /**
     * Called by parse after all elements have been processed
     */
    protected function writeTemplateFooter()
    {
        $this->write($this->footerBuffer);
    }

    /**
     * Parses the given template content
     *
     * Calls cleanupParse on failure or success
     *
     * @param string $contents the source content
     *
     * @return string the compiled form
     */
    protected function parse($contents)
    {
        if (isset($this->buffer)) {
            throw new RuntimeException('PHPSTLCompiler->parse called recursivley');
        }
        try {
            $this->buffer = '';
            $this->footerBuffer = '';
            $this->dom = new DOMDocument();
            $this->dom->preserveWhiteSpace = true;

            if (!$this->dom->loadXML($contents)) {
                die("failed to parse $this->template");
            }

            $this->dom->normalizeDocument();

            $this->writeTemplateHeader();

            foreach ($this->dom->documentElement->childNodes as $node) {
                $this->process($node);
            }
            $this->writeTemplateFooter();

            // normalize php blocks
            $this->buffer = preg_replace('/\s*\?>\s*?<\?php\s*/si', "\n", $this->buffer);

            // Collapse whitespace around php directives
            $this->buffer = preg_replace('/\s+<\?php/s', ' <?php', $this->buffer);
            $this->buffer = preg_replace('/\?>\s+/s', '?> ', $this->buffer);

            // Leading whitespace
            $this->buffer = preg_replace('/^\s*(?:(<\?php.*?\?>)\s*)?/si', '$1', $this->buffer);

            // Trailing whitespace
            $this->buffer = preg_replace('/(?:\s*(<\?php.*?\?>))?\s*$/si', '$1', $this->buffer);

            $ret = $this->buffer;
            $this->cleanupParse();
            return $ret;
        } catch (Exception $ex) {
            $this->cleanupParse();
            throw $ex;
        }
    }

    /**
     * Destroys any state built up by a parse()
     *
     * @return void
     */
    protected function cleanupParse()
    {
        $this->buffer = null;
        $this->footerBuffer = null;
        $this->dom = null;
    }
}

class PHPSTLCompilerException extends RuntimeException
{
    public function __construct(PHPSTLCompiler $compiler, $mess)
    {
        $template = $compiler->currentTemplate();
        $mess .= ", in $template";
        $pos = $compiler->currentPosition();
        if (isset($pos)) {
            $mess .= " at $pos";
        }

        parent::__construct($mess);
    }
}

?>
