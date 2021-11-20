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
    private static array $db_structure;

    public static function init(): void
    {
        if (file_exists("../classes/_database_structure.php")) {
            include_once("../classes/_database_structure.php");
            if(function_exists("get_db_struct")) {
                self::$db_structure = get_db_struct();
            }
        }
    }

    public static function GetFKForTable(string $tableName): array
    {
        if (isset(self::$db_structure) === false) {
            self::init();
        }
        $tableName = strtolower($tableName);
        if (!isset(self::$db_structure[$tableName]['fk'])) {
            return array(); // No foreign keys
        }
        $fks = self::$db_structure[$tableName]['fk'];
        $out = array();
        foreach ($fks as $name => $value) {
            list($tableName, $foreign_column) = explode(".", $value);
            $out[$name] = ["class" => self::GetClassForTable($tableName), "column" => $foreign_column];
        }
        return $out;
    }

    public static function GetClassForTable(string $tableName): string
    {
        if (isset(self::$db_structure) === false) {
            self::init();
        }
        return self::$db_structure[$tableName]['class'];
    }
}