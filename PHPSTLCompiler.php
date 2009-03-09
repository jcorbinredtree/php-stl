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
 * @author       Red Tree Systems, LLC <support@redtreesystems.com>
 * @copyright    2007 Red Tree Systems, LLC
 * @license      MPL 1.1
 * @version      1.6
 * @link         http://php-stl.redtreesystems.com
 */

require_once dirname(__FILE__).'/PHPSTLExpressionParser.php';
require_once dirname(__FILE__).'/PHPSTLNSHandler.php';

/**
 * PHPSTLCompiler
 *
 * This class provides the xml => php translation, modeled after the JSTL.
 */
class PHPSTLCompiler
{
    static public $HTMLSingleTags = array(
        'meta', 'link', 'br', 'hr', 'img', 'input'
    );

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
     * Map of namespaceURIs to PHPSTLNSHandler objects
     *
     * @var array
     */
    private $handlers=null;

    const WHITESPACE_PRESERVE = 1;
    const WHITESPACE_COLLAPSE = 2;
    const WHITESPACE_TRIM     = 3;

    protected $whitespace = self::WHITESPACE_COLLAPSE;

    private $stash;

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
     * Returns the DOMDocument currently being compiled
     *
     * @return DOMDocument or null
     */
    public function currentDOM()
    {
        return $this->dom;
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
     * @return array containing two paths:
     * array($metaCache, $contentCache)
     * - $metaCache: path to a serialized associative array containing meta data
     * - $contentCache: path to the compiled php
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
                list($meta, $content) = $this->parse($content);
                $meta['cacheName'] = $cache->cacheName($template);
                $ret = $cache->store($template, $meta, $content);
            }
            assert(is_array($ret));
            assert(count($ret) == 2);
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
     * @param DOMNode $node
     * @return void
     */
    public function process(DOMNode $node)
    {
        $end = null;
        $processChildren = true;

        switch ($node->nodeType) {
        case XML_DOCUMENT_NODE:
            break;
        case XML_COMMENT_NODE:
            $body = trim($node->data);
            if (strpos($body, "\n") === false) {
                $this->write("<?php\n// $body\n?>");
            } else {
                $lines = explode("\n", $body);
                for ($i=0; $i<count($lines); $i++) {
                    if ($i == 0) {
                        $buf = '/* '.$lines[$i]."\n";
                    } else {
                        $buf .= ' * '.$lines[$i]."\n";
                    }
                }
                $this->write("<?php\n$buf */\n?>");
            }
            break;
        case XML_TEXT_NODE:
        case XML_CDATA_SECTION_NODE:
            $text = PHPSTLExpressionParser::expand($node->nodeValue);
            $this->write($text);
            break;
        case XML_PI_NODE:
            switch ($node->target) {
            case 'php':
                $data = trim($node->data);
                if (! preg_match('/[:;}]$/', $data)) {
                    $data .= ';';
                }
                $this->write("<?php $data ?>");
                break;
            case 'whitespace':
                switch (trim($node->data)) {
                case 'preserve':
                    $this->whitespace = self::WHITESPACE_PRESERVE;
                    break;
                case 'collapse':
                    $this->whitespace = self::WHITESPACE_COLLAPSE;
                    break;
                case 'trim':
                    $this->whitespace = self::WHITESPACE_TRIM;
                    break;
                default:
                    throw new PHPSTLCompilerException($this,
                        "invalid <?whitespace $node->data ?>"
                    );
                    break;
                }
                break;
            default:
                throw new PHPSTLCompilerException($this,
                    "unknown processing instruction $node->target"
                );
            }
            break;
        case XML_ELEMENT_NODE:
            $attrs = array();
            foreach ($node->attributes as $name => $attr) {
                if (isset($attr->namespaceURI)) {
                    $handler = $this->handleNamespace($attr->namespaceURI);
                    $val = $handler->handle($attr);
                    $node->removeAttributeNode($attr);
                    if (isset($val)) {
                        $attrs[$attr->name] = $val;
                    }
                } else {
                    $attrs[$attr->name] = $attr->value;
                }
            }
            if ($node === $this->dom->documentElement) {
                foreach ($attrs as $name => $value) {
                    $this->meta[$name] = $value;
                }
            } elseif (isset($node->namespaceURI)) {
                $processChildren = false;
                $handler = $this->handleNamespace($node->namespaceURI);
                $handler->handle($node);
            } else {
                $start = "<$node->nodeName";
                foreach ($attrs as $name => $value) {
                    $start .= " $name=\"$value\"";
                }
                if (
                    $node->hasChildNodes() && (
                        $this->meta['type'] != 'text/html' ||
                        ! in_array($node->nodeName, self::$HTMLSingleTags)
                    )
                ) {
                    $start .= '>';
                    $end = "</$node->nodeName>";
                } else {
                    $processChildren = false;
                    $start .= ' />';
                }
                $this->write($start);
            }
            break;
        }

        if ($processChildren && $node->hasChildNodes()) {
            foreach ($node->childNodes as $child) {
                $this->process($child);
            }
        }

        if (isset($end)) {
            $this->write($end);
        }
    }

    private function unstashPHP($buffer)
    {
        return preg_replace_callback(
            '/\[\[([0-9a-f]{40})\]\]/s',
            array($this, 'unstashPHPBlock'),
            $buffer
        );
    }

    private function unstashPHPBlock($matches)
    {
        $id = $matches[1];
        if (isset($id) && array_key_exists($id, $this->stash)) {
            return $this->stash[$id];
        } else {
            return $matches[0];
        }
    }

    private function stashPHP($buffer)
    {
        return preg_replace_callback(
            '/<\?php(.+)\?>/s',
            array($this, 'stashPHPBlock'),
            $buffer
        );
    }

    private function stashPHPBlock($matches)
    {
        $buffer = trim($matches[1]);
        if (! $buffer) {
            return '';
        }
        if (
            ! preg_match('/[:;}\/]$/', $buffer) &&
            ! preg_match('/\/\/[^\n]*$/', $buffer)
        ) {
            $buffer .= ';';
        }
        $key = sha1(uniqid(__CLASS__.'stashPHPBlock'));
        $this->stash[$key] = $buffer;
        return "<?php\n[[$key]]\n?>";
    }

    /**
     * Writes data to the output
     *
     * @param string $out the data to write out
     * @return void
     */
    public function write($out)
    {
        $out = $this->stashPHP($out);

        switch ($this->whitespace) {
        case self::WHITESPACE_COLLAPSE:
            $out = preg_replace('/\s+<\?php/s', ' <?php', $out);
            $out = preg_replace('/\?>\s+/s', '?> ', $out);
            break;
        case self::WHITESPACE_TRIM:
            $out = preg_replace('/\s+</s', '<', $out);
            $out = preg_replace('/>\s+/s', '>', $out);
            $out = trim($out);
            break;
        }
        $this->buffer .= $out;
        $this->buffer = preg_replace(
            '/\s*\?>\s*<\?php\s*/s', "\n", $this->buffer
        );
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
     * Returns a handler for a namspace, or throws a PHPSTLCompilerException
     * if we can't handle it
     *
     * @param DOMNode $node
     * @return PHPSTLNSHandler an instance of PHPSTLNSHandler that should handle
     * the given namespace
     */
    protected function handleNamespace($namespace)
    {
        if (! isset($this->handlers)) {
            throw new RuntimeException('not compiling any document right now');
        }

        if (! isset($this->handlers[$namespace])) {
            try {
                $class = PHPSTL::getNamespaceHandler($namespace);
            } catch (InvalidArgumentException $e) {
                throw new PHPSTLCompilerException($this, $e->getMessage());
            }
            $this->handlers[$namespace] = new $class($this, $namespace);
        }
        return $this->handlers[$namespace];
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

        $head = "<?php\n/**\n";
        foreach ($stats as $title => $value) {
            $head .=
                " * $title".
                str_repeat(' ', $len-strlen($title)).
                " : $value\n";
        }
        $head .= " */ ?>\n";

        $this->write($head);
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
            $this->whitespace = self::WHITESPACE_COLLAPSE;
            $this->stash = array();
            $this->meta = array();
            $this->buffer = '';
            $this->footerBuffer = '';
            $this->handlers = array();
            $this->dom = new DOMDocument();
            $this->dom->preserveWhiteSpace = true;

            $this->meta['uri'] = (string) $this->template;
            $this->meta['type'] = 'text/html';

            if (!$this->dom->loadXML($contents)) {
                die("failed to parse $this->template");
            }

            $this->dom->normalizeDocument();

            $this->writeTemplateHeader();
            $this->process($this->dom);
            $this->writeTemplateFooter();

            $meta = $this->meta;
            $content = $this->unstashPHP(trim($this->buffer));

            $this->cleanupParse();
            return array($meta, $content);
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
        $this->whitespace = self::WHITESPACE_COLLAPSE;
        $this->stash = null;
        $this->meta = null;
        $this->buffer = null;
        $this->footerBuffer = null;
        $this->dom = null;
        $this->handlers = null;
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
