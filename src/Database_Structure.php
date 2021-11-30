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

    public static function init(string $modelDir = "../classes/"): void
    {
        $file = sprintf("%s/%s/%s", getcwd(), $modelDir, '_database_structure.inc');
        if (file_exists($file)) {
            self::$db_structure = unserialize(base64_decode(file_get_contents($file)));
        }
        else {
            self::$db_structure = self::build($modelDir);
        }
    }

    public static function build(string $modelDir = "../classes/"): array
    {
        $sql = MySQL::getInstance();

        $result = $sql->Query("SHOW TABLES");
        $table_array = array();
        
        while ($row = $result->fetch_row()) {
            $classname = self::FormatClassName($row[0]);
            $class = self::FindModel($modelDir, $classname);
            if ($class === false) {
                //MakeModel($classname, $row[0]);
            } else {
                $table_array[$row[0]]['table'] = $row[0];
                $table_array[$row[0]]['class'] = $class;
                self::GetCols($sql, $row[0], $table_array[$row[0]]);
            }
        }
        $query = "SELECT `TABLE_SCHEMA`, `TABLE_NAME`, `COLUMN_NAME`, `REFERENCED_TABLE_SCHEMA`, `REFERENCED_TABLE_NAME`, `REFERENCED_COLUMN_NAME` FROM `INFORMATION_SCHEMA`.`KEY_COLUMN_USAGE` WHERE `TABLE_SCHEMA` = SCHEMA() AND `REFERENCED_TABLE_NAME` IS NOT NULL;";
        $result2 = $sql->Query($query);
        while ($row = $result2->fetch_assoc()) {
            $table_array[$row['TABLE_NAME']]['fk'][$row['COLUMN_NAME']] = $row['REFERENCED_TABLE_NAME'] . '.' . $row['REFERENCED_COLUMN_NAME'];
        }

        $file = sprintf("%s/%s/%s", getcwd(), $modelDir, '_database_structure.inc');
        $out_file = fopen($file, "w");
        fputs($out_file, base64_encode(serialize($table_array)));
        fclose($out_file);
        return $table_array;
    }

    private static function FindModel(string $dir, string $tableName): ?string
    {
        $file = getcwd() . '/' . $dir . strtolower($tableName) . ".php";
        if (!file_exists($file)) {
            echo "Couldn't find model for $tableName !\r\n";
            return false;
        }
        $handle = fopen($file, "r");
        $n = 0;
        while (($line = fgets($handle)) !== false) {
            $reg = "/class\s(\w+)\s(\w+)\s(\w+)\s/";
            if (preg_match($reg, $line, $matches)) {
                if (
                    $matches[2] == "extends" 
                    && ($matches[3] == "Model" || $matches[3] == "Auth")
                ) {
                    return $matches[1];
                } else {
                    echo "Could not find class for $tableName!!\r\n";
                    return null;
                }
            }
        }
        return null;
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

    private static function FormatClassName(string $table): string
    {
        // table_name into TableName, would regex be better, faster, cleaner?
        return join("", array_map(function ($e) { return ucfirst($e); }, explode("_", $table)));
    }

    private static function GetCols(MySQL $sql, string $tableName, array &$ref): void
    {
        $query = "SHOW COLUMNS FROM `$tableName`";
        $result = $sql->Query($query);
        while ($row = $result->fetch_assoc()) {
            $ref["columns"][] = $row;
        }
    }
}