<?php

/**
 * CoreTag class definition
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
 * @version      1.6
 * @link         http://php-stl.redtreesystems.com
 */

require_once(dirname(__FILE__).'/PHPSTLLoopIterator.php');

/**
 * CoreTag
 *
 * This class provides core tag functionality
 *
 * @category Tag
 */
class CoreTag extends Tag
{
    /**
     * Static function call setup by CoreTag::xmlHeader
     */
    public static function BuildXMLHeader($ver='1.0', $enc='utf-8')
    {
        return "<?xml version=\"$ver\" encoding=\"$enc\"?>\n";
    }

    /**
     * Opens a try { ... } catch { ... } block
     */
    public function _try(DOMElement &$element)
    {
        $this->compiler->write('<?php try { ?>');
        $this->process($element);
        $this->compiler->write('<?php } ?>');
    }

    /**
     * Catches an error type
     *
     * @param type the type to catch, defaults to RuntimeException
     * @param var the variable to put the exception into, defaults to exception
     */
    public function _catch(DOMElement &$element)
    {
        $class = $this->getUnquotedAttr($element, 'type', 'RuntimeException');
        $var = $this->getUnquotedAttr($element, 'var', 'exception');
        $this->compiler->write("<?php } catch ($class \$$var) { ?>");
    }

    /**
     * Throws an exception
     *
     * @param type the type of exception to throw, defaults to RuntimeException
     * @param message the message
     */
    public function _throw(DOMElement &$element)
    {
        $class = $this->getUnquotedAttr($element, 'type', 'RuntimeException');
        $message = $this->requiredAttr($element, 'message');
        $this->compiler->write("<?php throw new $class($message); ?>");
    }

    /**
     * Represents an if condition
     *
     * @param string test required - the condition to test
     * @param string var optional - if present, will store a boolean value to this variable
     */
    public function _if(DOMElement &$element)
    {
        $test = $this->requiredAttr($element, 'test', false);
        $var = $this->getUnquotedAttr($element, 'var', false);

        if ($var) {
            $this->compiler->write("<?php $var = $test ? true : false; ?>");
        } else {
            $this->compiler->write("<?php if($test) { ?>");
            $this->process($element);
            $this->compiler->write('<?php } ?>');
        }
    }

    /**
     * Represents an elseif condition, only works after a <core:if>
     */
    public function _elseif(DOMElement &$element)
    {
        $test = $this->requiredAttr($element, 'test', false);
        $this->compiler->write("<?php } elseif ($test) { ?>");
        $this->process($element);
    }

    /**
     * Represents an else condition - obviously only useful after a <core:if>
     */
    public function _else(DOMElement &$element)
    {
        $this->compiler->write('<?php } else { ?>');
        $this->process($element);
    }

    /**
     * Represents a switch block
     *
     * @param string test required - the variable to test
     */
    public function _switch(DOMElement &$element)
    {
        $test = $this->requiredAttr($element, 'test');

        $this->compiler->write("<?php switch ($test) { ?>");
        $this->process($element);
        $this->compiler->write('<?php } ?>');
    }

    /**
     * Represents a case statement - only useful (and valid) inside a switch
     *
     * @param string when required - the condition that must be met to reach the following code
     * @param string fallThrough optional - If true, will not add a break
     */
    public function _case(DOMElement &$element)
    {
        $test = $this->requiredAttr($element, 'when');
        $this->compiler->write("<?php case $test: ?>");
        $this->process($element);
        if (! $this->getBooleanAttr($element, 'fallThrough', false)) {
            $this->compiler->write('<?php break; ?>');
        }
    }

    /**
     * Represents the default in a case statement - only useful (and valid) inside a switch
     *
     * @param string fallThrough optional - If true, will not add a break
     */
    public function _default(DOMElement &$element)
    {
        $this->compiler->write('<?php default: ?>');
        $this->process($element);
        if (! $this->getBooleanAttr($element, 'fallThrough', false)) {
            $this->compiler->write('<?php break; ?>');
        }
    }

    /**
     * Performs a for each loop
     *
     * @param string list required - a list to process
     * @param string var required - the name of the locally scoped variable
     */
    public function _forEach(DOMElement &$element)
    {
        $list = $this->requiredAttr($element, 'list');
        $var = $this->requiredAttr($element, 'var', false);
        $varStatus = $this->getUnquotedAttr($element, 'varStatus', '__loop'.uniqid());

        if (preg_match('/,/', $var)) {
            $desc = explode(',', $var);
            $this->compiler->write(
                "<?php foreach($list as \$$desc[0] => \$$desc[1]) { ?>"
            );
        } else {
            $this->compiler->write(
                "<?php \$$varStatus = new PHPSTLLoopIterator($list); ".
                "for(; \$${varStatus}->index < \$${varStatus}->count; \$${varStatus}->index++) { ".
                "\$${varStatus}->current=\$$var=\$${varStatus}->list[\$${varStatus}->index]; ?>"
            );
        }

        $this->process($element);

        $this->compiler->write("<?php } ?>");
    }

    /**
     * Writes a continue statement for loop processing
     */
    public function _continue(DOMElement &$element)
    {
        $this->compiler->write('<?php continue; ?>');
    }

    /**
     * Writes a break statement for loop processing
     */
    public function _break(DOMElement &$element)
    {
        $this->compiler->write('<?php break; ?>');
    }

    /**
     * Performs a for loop
     *
     * @param int from required - the integer to start from
     * @param int to required - the integer to end on (the condition)
     * @param string var optional - the index variable to assign. default is i
     */
    public function _for(DOMElement &$element)
    {
        $from = $this->requiredAttr($element, 'from', false);
        $to = $this->requiredAttr($element, 'to', false);
        $var = $this->getUnquotedAttr($element, 'var', 'i');

        $this->compiler->write("<?php for(\$$var=$from; \$$var<$to; \$$var++){ ?>");
        $this->process($element);
        $this->compiler->write('<?php } ?>');
    }

    /**
     * Sets a variable in a scope
     *
     * @param string value required - the value to set
     * @param string var required - the name of the variable to set
     */
    public function set(DOMElement &$element)
    {
        $value = $this->requiredAttr($element, 'value');
        $var = $this->requiredAttr($element, 'var', false);

        if ($var[0] != '$') {
            $var = "\$$var";
        }

        if (preg_match('/,/', $var)) {
            $var = explode(',', $var);
            $vstr = '';
            for ($i=0; $i<count($var); $i++) {
                $varVal = $var[$i];
                if ($varVal[0] != '$') {
                    $varVal = "\$$varVal";
                }

                $vstr .= $varVal;

                if ($i+1 < count($var)) {
                    $vstr .= ',';
                }
            }
            $this->compiler->write("<?php list($vstr) = $value; ?>");
        } else {
            $this->compiler->write("<?php $var = $value; ?>");
        }
    }

    /**
     * Prints out a value
     *
     * @param string value required - the value to output
     * @param boolean escapeXml optional, default true
     * @param string default optional - the default value to use in the event of an empty value
     * @param string var optional - the variable to store the output in
     * @param string format optional - format options,
     *                 "int" => formats as integer
     *                 "money" => formats as us money,
     *                 "boolean" => formats as Yes or No
     *                 "date:xxx" => formats as date, where xxx is the format string used by date()
     */
    public function out(DOMElement &$element)
    {
        $value = $this->requiredAttr($element, 'value');
        $escapeXml = $this->getBooleanAttr($element, 'escapeXml', true);
        $default = $this->getAttr($element, 'default', '');
        $var = $this->getUnquotedAttr($element, 'var', false);
        $format = $this->getUnquotedAttr($element, 'format');

        if (! $value) {
            $value = $default;
        }

        if (isset($format)) {
            if (substr($format, 0, 5) == 'date:') {
                $fstr = substr($format, 5);
                $value = "date('$fstr',$value)";
            } elseif ($format == 'int') {
                $value = "(int) $value";
            } elseif ($format == 'money') {
                $value = "money_format('%n', $value)";
            } elseif ($format == 'boolean') {
                $value = "($value?'Yes':'No')";
            } else {
                throw new CompilerException($this->compiler,
                    "invalid <core:out> format '$format'"
                );
            }
        }

        if ($escapeXml) {
            $value = "htmlentities($value)";
        }

        if ($var) {
            $this->compiler->write("<?php \$$var = $value; ?>");
        } else {
            $this->compiler->write("<?php echo $value; ?>");
        }
    }

    /**
     * Puts out an xml header, such as <?xml version="1.0" encoding="utf-8" ?>
     *
     * @param string encoding optional - the XML encoding, default is utf-8
     * @param string version optional - the XML version, default is 1.0
     *
     * @see CoreTag::BuildXMLHeader
     */
    public function xmlHeader(DOMElement &$element)
    {
        $this->compiler->write(
            "<?php echo CoreTag::BuildXMLHeader(".$this->argList(array(
                $this->getAttr($element, 'version'),
                $this->getAttr($element, 'encoding')
            ))."); ?>"
        );
    }

    /**
     * Converts your object to json .. hopefully :-/
     *
     * @param object the object you wish to convert
     * @param var an optional variable name to output to
     */
    public function json(DOMElement $element)
    {
        $object = $this->requiredAttr($element, 'object');
        $var = $this->getUnquotedAttr($element, 'var');

        if ($var) {
            $var = "\$$var =";
        } else {
            $var = 'print';
        }

        $this->compiler->write("<?php $var json_encode($object); ?>");
    }

    /**
     * Adds a php block - use sparingly
     */
    public function php(DOMElement &$element)
    {
        $this->compiler->write('<?php '.$element->textContent.'; ?>');
    }

    /**
     * Writes a CDATA block to the output
     *
     * @param simple boolean default false, if true don't process the
     * <core:cdata /> children, simpley output its textContent
     */
    public function cdata(DOMElement &$element)
    {
        if ($this->getBooleanAttr($element, 'simple', false)) {
            $this->compiler->write('<![CDATA['.$element->textContent.']]>');
        } else {
            $this->compiler->write('<![CDATA[');
            $this->process($element);
            $this->compiler->write(']]>');
        }
    }

    /**
     * Dumps the given object out via print_r. You may specify
     * whether or not to include <pre> tags.
     *
     * @param object var the variable to dump
     * @param boolean pre set to false to not include the <pre> wrap
     */
    public function dump(DOMElement &$element)
    {
        $object = $this->requiredAttr($element, 'var', false);
        $pre = $this->getBooleanAttr($element, 'pre', true);

        $dump = "echo '$object = '.htmlentities(print_r($object, true));";
        if ($pre) {
            $this->compiler->write("<pre><?php $dump ?></pre>");
        } else {
            $this->compiler->write("<?php $dump ?>");
        }
    }
}

// crappy json_encode for php <5.2.0
if (! function_exists('json_encode')) {
    function json_encode($obj)
    {
        $obj = is_array($obj) ? $obj : get_object_vars($obj);
        $items = array();
        foreach ($obj as $name=>$val) {
            array_push(sprintf("'%s':%s",
                $name,
                isset($val) ? 'null' : "'$val'"
            ));
        }
        return '{'.implode(', ', $items).'}';
    }
}

?>
