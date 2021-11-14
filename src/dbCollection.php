<?php
/*
 * Copyright (c) 2021
 * JONATHAN STARK
 */

namespace SimpleORM;

use ArrayAccess;
use Iterator;

class dbCollection implements ArrayAccess, Iterator
{
    protected array $objs = array();
    private int $position;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->position = 0;
    }

    // <editor-fold desc="implementation code">

    /**
     * @param mixed $offset
     * @return bool
     */
    public function offsetExists($offset): bool
    {
        return isset($this->objs[$offset]);
    }

    /**
     * @param mixed $offset
     * @return mixed|null
     */
    public function offsetGet($offset)
    {
        return $this->objs[$offset] ?? null;
    }

    /**
     * @param mixed $offset
     * @param mixed $value
     */
    public function offsetSet($offset, $value)
    {
        if (is_null($offset)) {
            $this->objs[] = $value;
        } else {
            $this->objs[$offset] = $value;
        }
    }

    /**
     * @param $offset
     */
    public function offsetUnset($offset)
    {
        unset($this->objs[$offset]);
    }
    //</editor-fold>

    /**
     * ResolveForeignKeys: Looks up foreign keys in the database
     * @param bool $ignore_hidden_keys
     */
    public function ResolveForeignKeys(bool $ignore_hidden_keys = true)
    {
        foreach ($this->objs as $o) {
            $o->ResolveForeignKeys($ignore_hidden_keys);
        }
    }

    /**
     * toArray: Recursively gets each record's data for display to end user
     *
     * @param bool $hide_db_ts -- Optionally hide the created_at and updated_at fields
     * @param bool $showempty -- Optionally show null fields
     * @return array
     */
    public function toArray(bool $hide_db_ts = false, bool $showempty = false): array
    {
        $out_array = array();
        foreach ($this->objs as $obj) $out_array[] = $obj->toArray($hide_db_ts, $showempty);
        return $out_array;
    }

    /**
     * @return mixed
     */
    public function current()
    {
        return $this->objs[$this->position];
    }

    /**
     *
     */
    public function next()
    {
        ++$this->position;
    }

    /**
     * @return bool|float|int|string|null
     */
    public function key()
    {
        return $this->position;
    }

    /**
     * @return bool
     */
    public function valid(): bool
    {
        return isset($this->objs[$this->position]);
    }

    /**
     *
     */
    public function rewind()
    {
        $this->position = 0;
    }

    /**
     * @param $val
     * @param string $k
     * @return mixed
     */
    public function find($val, $k = "id") {
        return $this->objs[array_search($val, array_column($this->objs, $k))];
    }
}