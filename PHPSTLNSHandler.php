<?php

/**
 * PHPSTLNSHandler base class definition
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
 * @author       Red Tree Systems, LLC <php-stl@redtreesystems.com>
 * @copyright    2007 Red Tree Systems, LLC
 * @license      MPL 1.1
 * @version      1.4
 * @link         http://php-stl.redtreesystems.com
 */

/**
 * Handles custom namespaces for PHPSTL
 */
abstract class PHPSTLNSHandler
{
    /**
     * @var PHPSTLCompiler
     */
    protected $compiler;

    /**
     * @var string
     */
    protected $namespace;

    /**
     * @param PHPSTLCompiler $compiler
     * @param string $namespace
     */
    public function __construct(PHPSTLCompiler $compiler, $namespace) {
        assert(is_string($namespace));
        $this->compiler = $compiler;
        $this->namespace = $namespace;
    }

    public function __tostring()
    {
        return "[$this->namespace handler]";
    }

    /**
     * @return PHPSTLCompiler
     */
    public function getCompiler()
    {
        return $this->compiler;
    }

    /**
     * @see $namespace
     * @return string
     */
    public function getNamespace()
    {
        return $this->namespace;
    }

    /**
     * Primary entry point from PHPSTLCompiler
     *
     * @param DOMNode $node
     */
    final public function handle(DOMNode $node)
    {
        assert(isset($node->namespaceURI));
        assert($node->namespaceURI == $this->namespace);

        switch ($node->nodeType) {
        case XML_ATTRIBUTE_NODE:
            if ($node->ownerElement === $node->ownerDocument->documentElement) {
                return $this->handleDocumentAttribute($node);
            } else {
                return $this->handleAttribute($node);
            }
            break;
        case XML_ELEMENT_NODE:
            return $this->handleElement($node);
            break;
        default:
            new PHPSTLCompilerException($this->compiler,
                "Unsupported nodeType $node->nodeType ".
                "for $node->prefix:$node->nodeName"
            );
        }
    }

    /**
     * Calls a handler method for a node
     *
     * @param string method
     * @param DOMNode $node
     * @return mixed whatever the handling method returns, if the caller cares
     */
    final protected function callHandleMethod($method, DOMNode $node)
    {
        if (! method_exists($this, $method)) {
            throw new PHPSTLCompilerException($this->compiler,
                'Cannot handle '.$this->pathString($node).
                ', tried '.get_class($this)."->$method"
            );
        }
        return $this->$method($node);
    }

    /**
     * Returns a representative path string for the given DOMNode like:
     *   /root/el/el/@attr
     *   /root/el/text()
     *
     * @param DOMNode $node
     * @return string
     */
    protected function pathString(DOMNode $node, $suffix=null)
    {
        switch ($node->nodeType) {
        case XML_TEXT_NODE:
        case XML_CDATA_SECTION_NODE:
            $what = 'text()';
            break;
        case XML_ATTRIBUTE_NODE:
            $what = '@'.$node->nodeName;
            break;
        default:
            $what = $node->nodeName;
            break;
        }
        if (isset($suffix)) {
            $what .= $suffix;
        }
        $what = sprintf('%s, xmlns:%s="%s"',
            $what, $node->prefix, $this->namespace
        );
        while ($node->parentNode !== $node->ownerDocument) {
            $node = $node->parentNode;
            $what = $node->nodeName.'/'.$what;
        }
        return '/'.$what;
    }

    /**
     * Handles an attribute on the document element
     *
     * Trys to call a method named handleDocumentAttrName
     *
     * @param DOMAttr $attr
     * @return mixed whatever the handling method returns, if the caller cares
     * @see callHandleMethod
     */
    protected function handleDocumentAttribute(DOMAttr $attr)
    {
        return $this->callHandleMethod(
            'handleDocumentAttr'.ucfirst($attr->name), $attr
        );
    }

    /**
     * Handles an attribute
     *
     * Trys to call a method named handleAttrName
     *
     * @param DOMAttr $attr
     * @return mixed whatever the handling method returns, if the caller cares
     * @see callHandleMethod
     */
    protected function handleAttribute(DOMAttr $attr)
    {
        return $this->callHandleMethod(
            'handleAttr'.ucfirst($attr->name), $attr
        );
    }

    /**
     * Handles an element
     *
     * Trys to call a method named handleElementName
     *
     * @param DOMElement $element
     * @return mixed whatever the handling method returns, if the caller cares
     * @see callHandleMethod
     */
    protected function handleElement(DOMElement $element)
    {
        return $this->callHandleMethod(
            'handleElement'.ucfirst($element->localName), $element
        );
    }

    /**
     * Requires the attribute to be on $element
     *
     * @param DOMElement $element the target element
     * @param string $attr the attribute key
     * @param boolean $quote true if the value should be quoted [default]
     * @param boolean $expand whether to expand expressions in the value
     * @return the attribute value for key $attr
     */
    protected function requiredAttr(DOMElement $element, $attr, $quote=true, $expand=true)
    {
        if (! $element->hasAttribute($attr)) {
            throw new InvalidArgumentException(
                'missing required attribute '.
                $this->pathString($element, "/@$attr")
            );
        }
        $value = $element->getAttribute($attr);
        if ($quote) {
            $value = $this->quote($value);
        }
        if ($expand) {
            $value = $this->expand($value);
        }
        return $value;
    }

    /**
     * Get an attribute from $element
     *
     * @param DOMElement $element the target element
     * @param string $attr the attribute key
     * @param mixed $default the default value
     * @param boolean $expand whether to expand expressions in the value
     * @return the attribute value for key $attr
     */
    protected function getAttr(DOMElement $element, $attr, $default=null, $expand=true)
    {
        if ($element->hasAttribute($attr)) {
            $value = $element->getAttribute($attr);
            $value = $this->quote($value);
            if ($expand) {
                $value = $this->expand($value);
            }
            return $value;
        } else {
            return $this->quote($default);
        }
    }

    /**
     * Get a raw attribute from $element
     *
     * @param DOMElement $element the target element
     * @param string $attr the attribute key
     * @param mixed $default the default value
     * @param boolean $expand whether to expand expressions in the value
     * @return the attribute value for key $attr
     */
    protected function getUnquotedAttr(DOMElement $element, $attr, $default=null, $expand=true)
    {
        if ($element->hasAttribute($attr)) {
            $value = $element->getAttribute($attr);
            if ($expand) {
                return $this->expand($value);
            } else {
                return $value;
            }
        } else {
            return $default;
        }
    }

    /**
     * Get a boolean attribute from $element
     *
     * @param DOMElement $element the target element
     * @param string $attr the attribute key
     * @param mixed $default the default value
     * @return boolean a value matching the users intent
     */
    protected function getBooleanAttr(DOMElement $element, $attr, $default=false)
    {
        if (!$element->hasAttribute($attr)) {
            return $default;
        }

        $bool = $this->booleanValue($element->getAttribute($attr));

        if (! isset($bool)) {
            throw new InvalidArgumentException(
                'invalid boolean attribute '.
                $this->pathString($element)."/@$attr"
            );
        }

        return $bool;
    }

    /**
     * Tests whether the given value is boolean
     *
     * @param mixed value
     * @return mixed
     *   true if value is 'true', or 'yes'
     *   false if value is 'false', or 'no'
     *   null otherwise
     */
    protected function booleanValue($value)
    {
        switch ($value) {
            case 'true':
            case 'yes':
                return true;
            case 'false':
            case 'no':
                return false;
            default:
                return null;
        }
    }

    /**
     * Processes child elements
     *
     * @param DOMElement $element
     * @return void
     */
    protected function process(DOMElement $element)
    {
        if ($element->hasChildNodes()) {
            foreach($element->childNodes as $node) {
                $this->compiler->process($node);
            }
        }
    }

    /**
     * Formats an array of elements as a comma-separated argument list suitable
     * for building a function call string.
     *
     * Example:
     *   $this->argList(array("'a'", null, "'b'", null))
     *   == "'a', null, 'b'"
     *
     *   $this->argList(array("'a'", null, "'b'", null), false)
     *   == "'a', null, 'b', null"
     *
     * Note, this function does NOT quote any arguments, since it is presumed
     * the most likely use case is something like:
     *   $this->argList(array(
     *     $this->getAttr(...),
     *     ...
     *   );
     * Where most arguments come from attribute parsing methods which already
     * quote things.
     *
     * @param args array the array list
     *
     * @param pruneTail boolean default true, if true drops trailing nulls
     * from the arg list
     *
     * @return string
     */
    protected function argList($args, $pruneTail=true)
    {
        if ($pruneTail) {
            while (! isset($args[count($args)-1])) {
                array_pop($args);
            }
        }
        $a = array();
        foreach ($args as &$arg) {
            array_push($a, isset($arg) ? $arg : 'null');

        }
        return implode(', ', $a);
    }

    /**
     * Collects attributes from an element and return them
     *
     * HTML-style boolean handling is on by default, see getAttributeString for
     * what this means. The $attrs array argument may define a special named
     * member '-no-html-boolean', if set to a true value, will cause boolean
     * attributes to be emitted "normally" rather than in the html way.
     *
     * Expression expansion is performed on attribute values by default, if you
     * don't want this, then you can specify the special named member
     * '-no-expand' to true.
     *
     * @param element DOMElement the element
     *
     * @param attrs array array of attribute names to process; can
     * also contain named key => value pairs specifying default values in
     * case the element lacks an attribute; ordinal elements are equivalent to
     * specifying name => null
     *
     * @param asArray boolean optional, default false, if true return an
     * associative array of collected values, otherwise returns a string
     * like ' attr="val" attr="val"'
     *
     * @return string or array
     * @see getAttributeString
     */
    protected function getAttributes(DOMElement $element, $attrs, $asArray=false)
    {
        assert(is_array($attrs));

        $htmlBoolean = true;
        if (
            array_key_exists('-no-html-boolean', $attrs) &&
            $attrs['-no-html-boolean']
        ) {
            $htmlBoolean = false;
        }

        $expand = true;
        if (
            array_key_exists('-no-expand', $attrs) &&
            $attrs['-no-expand']
        ) {
            $expand = false;
        }

        $opts = array();
        foreach ($attrs as $attr => $default) {
            if (is_int($attr)) {
                $attr = $default;
                $default = null;
            }
            $value = $this->getUnquotedAttr($element, $attr, $default, false);
            if (isset($value)) {
                $bool = $this->booleanValue($value);
                if (isset($bool)) {
                    $opts[$attr] = $bool;
                } else {
                    $opts[$attr] = $value;
                }
            }
        }
        if ($asArray) {
            if ($expand) {
                foreach ($opts as $name => $val) {
                    $opts[$name] = $this->expand($val);
                }
            }
            return $opts;
        } else {
            return $this->getAttributeString($opts, $htmlBoolean, $expand);
        }
    }

    /**
     * Returns a tag attribute string like ' name="val" name="val"' from an
     * associative array.
     *
     * @param array $attrs
     *
     * @param boolean $htmlBoolean if true, boolean values will be output as
     * ' name="name"' if true or '' if false
     *
     * @param boolean $expand whether to expand attribute values, defaults true
     *
     * @return string
     */
    protected function getAttributeString($attrs, $htmlBoolean=true, $expand=true)
    {
        assert(is_array($attrs));
        $r = '';
        foreach ($attrs as $attr => $value) {
            if ($value === false) {
                if (! $htmlBoolean) {
                    $r .= " $attr=\"false\"";
                }
                continue;
            } elseif ($value === true) {
                if ($htmlBoolean) {
                    $r .= " $attr=\"$attr\"";
                } else {
                    $r .= " $attr=\"true\"";
                }
                continue;
            } elseif ($expand) {
                $value = $this->expand($value, true);
            }
            $r .= " $attr=\"$value\"";
        }
        return $r;
    }

    /**
     * Expands variable expressions in the given value
     *
     * This is used by attribute retreival methos to expand ${...} and @{...}
     * expressions into real php
     *
     * @param string $value
     *
     * @param boolean $full defaults false, if true then the expansion will be
     * done to full <?php ... ?> directives, this usually isn't what you want
     * since most consumers of attribute values want a value for use in
     * building a <?php ... ?> block
     *
     * @return $value
     */
    protected function expand($value, $full=false)
    {
        return PHPSTLExpressionParser::expand($value, $full, $full);
    }

    /**
     * Quotes a subject if it's found to require one
     *
     * @param string $val The subject to quote (or not)
     * @return string The quoted (or not) value
     */
    protected function quote($val)
    {
        if (! isset($val)) {
            return null;
        }

        if ($this->needsQuote($val)) {
            return "'".addslashes($val)."'";
        }

        return $val;
    }

    /**
     * Returns true if the value requires quoting
     *
     * @return boolean
     */
    protected function needsQuote($val)
    {
        $char = strlen($val) ? $val[0] : '';

        return $char != '$' && $char != '@';
    }
}

?>
