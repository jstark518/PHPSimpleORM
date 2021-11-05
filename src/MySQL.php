<?php
/*
 * Copyright (c) 2021. Lorem ipsum dolor sit amet, consectetur adipiscing elit.
 * Morbi non lorem porttitor neque feugiat blandit. Ut vitae ipsum eget quam lacinia accumsan.
 * Etiam sed turpis ac ipsum condimentum fringilla. Maecenas magna.
 * Proin dapibus sapien vel ante. Aliquam erat volutpat. Pellentesque sagittis ligula eget metus.
 * Vestibulum commodo. Ut rhoncus gravida arcu.
 */

namespace SimpleORM;

use mysqli_result;

/**
 * MySQL Connector class
 */
class MySQL extends \mysqli
{
    protected static MySQL $instance;

    /**
     * Connects to a MySQL server.
     *
     * @param string $host -- Host name of the MySQL server.
     * @param string $user -- Username for the MySQL server.
     * @param string $password -- Password for the MySQL server.
     * @param string $db -- The database to 'use'.
     */
    public function __construct(string $config)
    {
        $host = DB_Config::getConfig($config)->host;
        $user = DB_Config::getConfig($config)->user;
        $password = DB_Config::getConfig($config)->password;
        $db = DB_Config::getConfig($config)->db;
        @parent::__construct($host, $user, $password, $db);
        if (mysqli_connect_errno()) {
            die("Connection Error (" .
                mysqli_connect_error() . ') ' .
                mysqli_connect_errno()
            );
        }
    }

    /**
     * getInstance: Get the existing SQL connection, or create one.
     * @return MySQL
     */
    public static function getInstance($config = DB_Config::DEFAULT): MySQL
    {
        if (!isset(self::$instance)) {
            self::$instance = new self($config);
        }
        return self::$instance;
    }

    public function query($query, $resultmode = NULL): mysqli_result
    {
        Debug::SQL_Query($query);
        if (!$this->real_query($query)) {
            Debug::SQL_Error($query, $this);
        }
        $result = new mysqli_result($this);
        return $result;
    }

    /*
     * This function inserts data to the database
     *  takes an array and turns it into SQL with escaped string
     */
    public function insertArray($table, $data, $db = ''): mysqli_result
    {
        foreach ($data as $field => $value) {
            $fields[] = '`' . $field . '`';
            if (in_array($value, SQL::$functions)) {
                $values[] = "$value";
            } else {
                $values[] = "'" . @parent::real_escape_string($value) . "'";
            }
        }
        $field_list = join(',', $fields);
        $value_list = join(', ', $values);

        $query = sprintf(/** @lang sql */ "INSERT INTO {$table} (%s) VALUES (%s)", $field_list, $value_list);

        if (!($result = $this->query($query))) {
            Debug::SQL_Error($query, $this);
        }
        return $result;
    }

    /*
     * This function inserts data to the database
     *   takes an array and turns it into SQL with escaped string
     * TODO: Shares a lot of code with insertArray, can probably be merged.
    */
    public function updateArray($table, $data, $key, $keyvalue): mysqli_result
    {
        foreach ($data as $field => $value) {
            if (in_array($value, SQL::$functions) || is_numeric($value)) {
                $safevalue = $value;
            } else {
                $safevalue = "'" . @parent::real_escape_string($value) . "'";
            }
            $fields[] = "`{$field}` = {$safevalue}";
        }
        $field_list = join(',', $fields);

        $query = sprintf(/** @lang sql */ "UPDATE %s SET %s WHERE %s = %s", $table, $field_list, $key, $keyvalue);

        if (!($result = $this->query($query))) {
            Debug::SQL_Error($query, $this);
        }
        return $result;
    }

    public static function Now()
    {
        $inst = self::getInstance();
        $result = $inst->query("SELECT now();");
        return $result->fetch_row()[0];
    }
}