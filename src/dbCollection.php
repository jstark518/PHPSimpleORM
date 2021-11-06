<?php
/*
 * Copyright (c) 2021. Lorem ipsum dolor sit amet, consectetur adipiscing elit.
 * Morbi non lorem porttitor neque feugiat blandit. Ut vitae ipsum eget quam lacinia accumsan.
 * Etiam sed turpis ac ipsum condimentum fringilla. Maecenas magna.
 * Proin dapibus sapien vel ante. Aliquam erat volutpat. Pellentesque sagittis ligula eget metus.
 * Vestibulum commodo. Ut rhoncus gravida arcu.
 */

namespace SimpleORM;

use ArrayAccess;
use Iterator;

class dbCollection implements ArrayAccess, Iterator
{
    /**
     * @var Model
     */
    protected $objs = array();
    private $position = 0;

    public function __construct()
    {
        $this->position = 0;
    }


    // <editor-fold desc="implementation code">
    public function offsetExists($offset)
    {
        return isset($this->objs[$offset]);
    }

    public function offsetGet($offset)
    {
        return $this->objs[$offset] ?? null;
    }

    public function offsetSet($offset, $value)
    {
        if (is_null($offset)) {
            $this->objs[] = $value;
        } else {
            $this->objs[$offset] = $value;
        }
    }

    public function offsetUnset($offset)
    {
        unset($this->objs[$offset]);
    }
    //</editor-fold>

    /**
     * ResolveForeignKeys: Looks up foreign keys in the database
     * @param bool $ignore_hidden_keys
     */
    public function ResolveForeignKeys($ignore_hidden_keys = true)
    {
        foreach ($this->objs as $o) {
            $o->ResolveForeignKeys($ignore_hidden_keys);
        }
    }

    /***
     * toArray: Recursively gets each record's data for display to end user
     *
     * @param bool $hide_db_ts -- Optionally hide the created_at and udpated_at fields
     * @param bool $showempty -- Optionally show null fields
     * @return array
     */
    public function toArray(bool $hide_db_ts = false, bool $showempty = false)
    {
        $out_array = array();
        foreach ($this->objs as $obj) $out_array[] = $obj->toArray($hide_db_ts, $showempty);
        return $out_array;
    }


    public function current()
    {
        return $this->objs[$this->position];
    }

    public function next()
    {
        ++$this->position;
    }

    public function key()
    {
        return $this->position;
    }

    public function valid()
    {
        return isset($this->objs[$this->position]);
    }

    public function rewind()
    {
        $this->position = 0;
    }

    public function find($val, $k = "id") {
        return $this->objs[array_search($val, array_column($this->objs, $k))];
    }
}