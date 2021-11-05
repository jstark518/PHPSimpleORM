<?php
/*
 * Copyright (c) 2021. Lorem ipsum dolor sit amet, consectetur adipiscing elit.
 * Morbi non lorem porttitor neque feugiat blandit. Ut vitae ipsum eget quam lacinia accumsan.
 * Etiam sed turpis ac ipsum condimentum fringilla. Maecenas magna.
 * Proin dapibus sapien vel ante. Aliquam erat volutpat. Pellentesque sagittis ligula eget metus.
 * Vestibulum commodo. Ut rhoncus gravida arcu.
 */

namespace SimpleORM;

class Database_Structure
{
    protected static array $db_structure;

    public static function init()
    {
//        if (file_exists("../classes/_database_structure.php")) {
//            include_once("../classes/_database_structure.php");
//            self::$db_structure = get_db_struct();
//        }
    }

    public static function GetFKForTable($tablename): array
    {
        if (isset(self::$db_structure) === false) {
            self::init();
        }
        $tablename = strtolower($tablename);
        if (!isset(self::$db_structure[$tablename]['fk'])) return array(); // No foreign keys
        $fks = self::$db_structure[$tablename]['fk'];
        $out = array();
        foreach ($fks as $name => $value) {
            list($tablename, $foreign_column) = explode(".", $value);
            $out[$name] = ["class" => self::GetClassForTable($tablename), "column" => $foreign_column];
        }
        return $out;
    }

    public static function GetClassForTable($tablename)
    {
        if (isset(self::$db_structure) === false) {
            self::init();
        }
        return self::$db_structure[$tablename]['class'];
    }
}