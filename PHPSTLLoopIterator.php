<?php

/**
 * PHPSTLLoopIterator class definition
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
 * @category     Tag
 * @author       Red Tree Systems, LLC <php-stl@redtreesystems.com>
 * @copyright    2007 Red Tree Systems, LLC
 * @license      MPL 1.1
 * @version      1.6
 * @link         http://php-stl.redtreesystems.com
 */

/**
 * PHPSTLLoopIterator
 *
 * @category Tag
 */
class PHPSTLLoopIterator
{
    public $count = 0;
    public $current = null;
    public $index = 0;
    public $list = null;

    public function __construct($list)
    {
        $this->list = $list;
        $this->count = count($this->list);
        if ($this->count > 0) {
            $this->current = $this->list[0];
        }
    }

    public function isFirst()
    {
        return !$this->count;
    }

    public function isLast()
    {
        return !(($this->index + 1) < $this->count);
    }

    public function isAltRow()
    {
        return ($this->index % 2);
    }

    /**
     * DEPRECATED, just use the public property
     */
    public function getCount()
    {
        return $this->count;
    }

    /**
     * DEPRECATED, just use the public property
     */
    public function getCurrent()
    {
        return $this->current;
    }

    /**
     * DEPRECATED, just use the public property
     */
    public function getIndex()
    {
        return $this->index;
    }
}

?>
