<?php

/**
 * PHPSTLExpressionParser class definition
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

/**
 * Expands expressinos for PHPSTLCompiler and co
 *
 * An expression is a string of one of the following four formats:
 *   ${obj.prop} or ${obj.method()}
 *   @{arbitrary()}
 *   ${=something.to.echo()}
 *   @{=arbitrary_echo()}
 *
 * The latter two forms are "echo" forms
 * Suppose the above four lines were in a variable $buffer as text:
 *   print PHPSTLExpressionParser::expand($buffer);
 * Would print:
 *   <?php $obj->prop; ?> or <?php $obj.method(); ?>
 *   <?php arbitrary(); ?>
 *   <?php echo $something->to->echo(); ?>
 *   <?php echo arbitrary_echo(); ?>
 *
 * Note, for whatever your formatting persuasion may be, whitespace is
 * irrelevant and so the following 3 expressions are all equivalent:
 *   1) @{if(clause){${do.shit()}}}
 *   2) @{ if(clause) { ${ do.shit() } } }
 *   3) @{
 *        if (clause) {
 *          ${do.shit()}
 *        }
 *      }
 *
 * Here's a more complex example demonstrating nesting:
 *   @{ if (${node.nodeType} == @{XML_ELEMENT_NODE}): }
 *     &lt;${=node.tagName}&gt;
 *   @{ endif }
 * Expanding results in:
 *   <?php if ($node->nodeType == XML_ELEMENT_NODE): ?>
 *     &lt;<?php echo $node->tagName; ?>&gt;
 *   <?php endif; ?>
 *
 * Note on nesting:
 *   Echoing is turned off when expanding nested expressions, so the following
 *   will result in a thrown RuntimeException when expanded:
 *     ${some.obj.call(${=someval})}
 *   Since trying to expand it would otherwise result in the nonsensical php:
 *     <?php $some->obj->call(echo $someval); ?>
 *   If for some perverse reason you really do want to form the above degenerate
 *   php, you can workaround this like so:
 *     ${some.obj.call(@{echo ${someval}}}
 *   This is made intentionally cumbersome since if you want to write that, you
 *   should really have to know why and have read this documentation.
 */
class PHPSTLExpressionParser
{
    /**
     * The public entry point, instances are used for internal context only.
     *
     * @param string $buffer the input string to process
     *
     * @param boolean $echoOk whether echo expressions are okay or not, defaults
     * to true; an echo expression is something like ${=...} or @{=...}
     *
     * @param boolean $phpWrap whether to wrap final expressions in <?php ... ?>
     * processing instruction blocks or not, defaults to true
     *
     * @return string the expanded result
     */
    public static function expand($buffer, $echoOk=true, $phpWrap=true)
    {
        $expn = new self($echoOk, $phpWrap);
        $buffer = $expn->stashExpressions($buffer);
        $buffer = $expn->unstashExpressions($buffer);
        return $buffer;
    }

    static private $ExprStashFormat  = '[[%s]]';
    static private $ExprStashPattern = '/\[\[([0-9a-f]{40})\]\]/';

    private $stash=array();
    private $echoOk;
    private $phpWrap;

    private function __construct($echoOk=true, $phpWrap=true)
    {
        $this->echoOk = $echoOk;
        $this->phpWrap = $phpWrap;
    }

    /*
     * Stashes expressions of the format [$@]{...}, inner expressions are
     * stashed first
     *
     * Each matching brace pair is passed through stashExpression, if that
     * returns null a RuntimeException is thrown
     */
    private function stashExpressions($buffer)
    {
        assert(is_array($this->stash));
        assert(is_string($buffer));
        $lvl = 0;
        $start = $end = -1;
        for ($i=0; $i<strlen($buffer); $i++) {
            if (
                ($buffer[$i] == '$' || $buffer[$i] == '@') &&
                strlen($buffer) > $i+1 && $buffer[$i+1] == '{'
            ) {
                if ($lvl++ == 0) {
                    $start = $i;
                }
            } elseif ($lvl > 0 && $buffer[$i] == '}') {
                if (--$lvl == 0) {
                    $end = $i;
                    $len = $end-$start+1;
                    $expr = substr($buffer, $start, $len);
                    $repl = $this->stashExpression($expr);
                    if (! isset($repl)) {
                        throw new RuntimeException(
                            "invalid expression '$expr'"
                        );
                    }
                    assert(is_string($repl));
                    $buffer =
                        substr($buffer, 0, $start).
                        $repl.
                        substr($buffer, $end+1);
                    $i += strlen($repl) - $len;

                    $start = $end = -1;
                }
            }
        }
        return $buffer;
    }

    /*
     * Stashes a single expression after calling stashExpressions on the meat of
     * the expression to stash any sub expressions. For this function's
     * purposes, the format of an expression is loosley:
     *   [$@]{=?meat}
     *
     * If $echoOk is off, and the expression is an echo, returns null
     *
     * If the meat is missing, returns null, this eliminates degenerate forms
     * like: ${} @{} ${=} @{=} and any whitespace variations on such
     *
     * After sub expressions are stashed, all .s in the meat not preceeded or
     * succeeded by another . are replaced with ->
     *
     * If the expression is of the form ${...} a '$' is prepended
     *
     * If php wrap is on, and the resulting meat doesn't end in a ':', ';', or
     * '}', then a ';' is appended
     *
     * If php wrap is on, the meat is then turned into <?php meat ?>
     *
     * The meat is then stashed under a unique 40-character hex string in
     * $stash, and the string sprintf($ExprStashFormat, $hexstring) is returned
     */
    private function stashExpression($expr)
    {
        assert(is_array($this->stash));
        if (! strlen($expr) > 3) {
            return null;
        }
        $meat = substr($expr, 2, strlen($expr)-3);
        $ret = '%s';
        if ($meat[0] == '=') {
            if (! $this->echoOk) {
                throw new RuntimeException(
                    "invalid $expr, not allowed to echo"
                );
            }
            if (! strlen($meat) > 1) {
                return null;
            }
            $meat = substr($meat, 1);
            $ret = "echo $ret";
        }
        $meat = trim($meat);
        if (! strlen($meat) > 0) {
            return null;
        }

        $old = array($this->echoOk, $this->phpWrap);
        $this->echoOk = $this->phpWrap = false;
        $meat = $this->stashExpressions($meat, array($this, 'stashExpression'));
        list($this->echoOk, $this->phpWrap) = $old;

        if ($expr[0] == '$') {
            $meat = '$'.preg_replace('/(?<!\.)\.(?!\.)/', '->', $meat);
        }

        if ($this->phpWrap) {
            $ret = "<?php $ret ?>";
            if (! preg_match('/[:;}]$/', $meat)) {
                $meat .= ';';
            }
        }

        $id = sha1(uniqid('php-stl-expression-stash-'));
        $key = sprintf(self::$ExprStashFormat, $id);
        assert(! array_key_exists($key, $this->stash));
        $this->stash[$id] = $meat;
        return sprintf($ret, $key);
    }

    /*
     * Replaces anything that matches $ExprStashPattern with the contents of the
     * stash entry keyed by the first capture from the pattern
     */
    private function unstashExpressions($buffer)
    {
        return preg_replace_callback(
            self::$ExprStashPattern,
            array($this, 'unstashExpression'),
            $buffer
        );
    }

    /*
     * preg_replace_callback callback for unstashExpressions
     */
    private function unstashExpression($matches)
    {
        assert(is_array($this->stash));
        assert(count($matches) > 1);
        $id = $matches[1];
        assert(array_key_exists($id, $this->stash));
        $ret = $this->stash[$id];
        unset($this->stash[$id]);
        return $this->unstashExpressions($ret);
    }
}

?>
