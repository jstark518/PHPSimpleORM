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
    private array $objs = array();
    private int $position;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->position = 0;
    }

    /**
     * @param mixed $offset
     * @return bool
     */
    final public function offsetExists($offset): bool
    {
        return isset($this->objs[$offset]);
    }

    /**
     * @param mixed $offset
     * @return mixed|null
     */
    final public function offsetGet($offset)
    {
        return $this->objs[$offset] ?? null;
    }

    /**
     * @param mixed $offset
     * @param mixed $value
     */
    final public function offsetSet($offset, $value): void
    {
        if (is_null($offset)) {
            $this->objs[] = $value;
        } else {
            $this->objs[$offset] = $value;
        }
    }

    /**
     * @param mixed $offset
     */
    final public function offsetUnset($offset): void
    {
        unset($this->objs[$offset]);
    }

    /**
     * ResolveForeignKeys: Looks up foreign keys in the database
     * @param bool $ignore_hidden_keys
     * @return dbCollection
     */
    final public function ResolveForeignKeys(bool $ignore_hidden_keys = true): dbCollection
    {
        foreach ($this->objs as $o) {
            $o->ResolveForeignKeys($ignore_hidden_keys);
        }
        return $this;
    }

    /**
     * toArray: Recursively gets each record's data for display to end user
     *
     * @param bool $hide_db_ts -- Optionally hide the created_at and updated_at fields
     * @param bool $showempty -- Optionally show null fields
     * @return array
     */
    final public function toArray(bool $hide_db_ts = false, bool $showempty = false): array
    {
        $out_array = array();
        foreach ($this->objs as $obj) $out_array[] = $obj->toArray($hide_db_ts, $showempty);
        return $out_array;
    }

    /**
     * @return mixed
     */
    final public function current()
    {
        return $this->objs[$this->position];
    }

    /**
     *
     */
    final public function next()
    {
        ++$this->position;
    }

    /**
     * @return float|int|null
     */
    final public function key()
    {
        return $this->position;
    }

    /**
     * @return bool
     */
    final public function valid(): bool
    {
        return isset($this->objs[$this->position]);
    }

    /**
     *
     */
    final public function rewind(): void
    {
        $this->position = 0;
    }

    /**
     * @param $val
     * @param string $k
     * @return mixed
     */
    final public function find($val, $k = "id")
    {
        return $this->objs[array_search($val, array_column($this->objs, $k))];
    }
}