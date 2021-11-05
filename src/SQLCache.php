<?php
/*
 * Copyright (c) 2021. Lorem ipsum dolor sit amet, consectetur adipiscing elit.
 * Morbi non lorem porttitor neque feugiat blandit. Ut vitae ipsum eget quam lacinia accumsan.
 * Etiam sed turpis ac ipsum condimentum fringilla. Maecenas magna.
 * Proin dapibus sapien vel ante. Aliquam erat volutpat. Pellentesque sagittis ligula eget metus.
 * Vestibulum commodo. Ut rhoncus gravida arcu.
 */

namespace SimpleORM;

class SQLCache
{
    protected static array $cache = array();

    public static function GetFromCache($class, $key, $callback, $col = 'id')
    {
        if (isset(self::$cache[$class][$key])) {
            Debug::Query_Cache("{$class}->{$col} = {$key}: Cache hit");
            return self::$cache[$class][$key][$col];
        }
        Debug::Query_Cache("{$class}->{$col} = {$key}: Cache miss");
        return self::$cache[$class][$key][$col] = $callback($class, $key, $col);
    }
}