<?php

/**
 * Tag base class definition
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
 * @category     Tag
 * @author       Red Tree Systems, LLC <php-stl@redtreesystems.com>
 * @copyright    2007 Red Tree Systems, LLC
 * @license      MPL 1.1
 * @version      1.4
 * @link         http://php-stl.redtreesystems.com
 */

/**
 * Tag
 *
 * This class provides a tag handler base class
 *
 * @category     Tag
 */
abstract class Tag
{
    /**
     * The compiler to write to
     *
     * @var Compiler
     */
    protected $compiler;

    /**
     * Constructor
     *
     * @param Compiler $compiler
     */
    public function __construct(Compiler &$compiler)
    {
        $this->compiler = $compiler;
    }

    /**
     * Dispatches a DOMElement to be handled by this Tag subclass instance
     *
     * Given an element named <ns:method />, this will look for a method
     * "method" first, then "_method", if neither is found, if method begins
     * with '__' or if the method is one defined direectly by the Tag class, a
     * CompilerException is thrown.
     *
     * The return value of the handler method is passed through, this is
     * typically void and doesn't matter.
     *
     * @param element DOMElement the element to handle
     * @return mixed usually void
     * @see Compiler::process
     */
    public function __dispatch(DOMElement &$element)
    {
        $method = substr(strstr($element->nodeName, ':'), 1);

        if (! method_exists($this, $method)) {
            if (! method_exists($this, "_$method")) {
                throw new CompilerException($this->compiler,
                    'Tag class '.get_class($this).
                    ' unable to handle element '.$element->nodeName
                );
            }
            $method = "_$method";
        }

        if (
            substr($method, 0, 2) == '__' ||
            in_array($method, get_class_methods('Tag'))
        ) {
            throw new CompilerException($this->compiler,
                "Won't call internal ".get_class($this).
                " method for element ".$element->nodeNode
            );
        }

        return $this->$method($element);
    }

    /**
     * Requires the attribute to be on $element
     *
     * @param DOMElement $element the target element
     * @param string $attr the attribute key
     * @param boolean $quote true if the value should be quoted [default]
     * @return the attribute value for key $attr
     */
    protected function requiredAttr(DOMElement &$element, $attr, $quote=true)
    {
        if (!$element->hasAttribute($attr)) {
            throw new InvalidArgumentException(
                "required attribute $attr missing from element $element->nodeName"
            );
        }

        $value = $element->getAttribute($attr);
        if ($quote) {
            $value = $this->quote($value);
        }
        return $value;
    }

    /**
     * Get an attribute from $element
     *
     * @param DOMElement $element the target element
     * @param string $attr the attribute key
     * @param mixed $default the default value
     * @return the attribute value for key $attr
     */
    protected function getAttr(DOMElement &$element, $attr, $default=null)
    {
        if ($element->hasAttribute($attr)) {
            return $this->quote($element->getAttribute($attr));
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
     * @return the attribute value for key $attr
     */
    protected function getUnquotedAttr(DOMElement &$element, $attr, $default=null)
    {
        if ($element->hasAttribute($attr)) {
            return $element->getAttribute($attr);
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
    protected function getBooleanAttr(DOMElement &$element, $attr, $default=false)
    {
        if (!$element->hasAttribute($attr)) {
            return $default;
        }

        switch ($element->getAttribute($attr)) {
            case 'true':
            case 'yes':
                return true;
            case 'false':
            case 'no':
                return false;
        }

        throw new InvalidArgumentException(
            "Invalid boolean attribute $attr specified for $element->nodeName"
        );
    }

    /**
     * Processes child elements
     *
     * @param DOMElement $element
     * @return void
     */
    protected function process(DOMElement &$element)
    {
        if ($element->hasChildNodes()) {
            foreach($element->childNodes as $node) {
                $this->compiler->process($node);
            }
        }
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
            return "'$val'";
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
