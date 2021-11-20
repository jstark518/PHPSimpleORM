<?php
/*
 * Copyright (c) 2021.
 */

namespace SimpleORM;

use mysqli;
use mysqli_result;

/**
 * MySQL Connector class
 */
class MySQL extends mysqli
{
    protected static MySQL $instance;
    protected DB_Config $config;

    /**
     * Connects to a MySQL server.
     *
     * @param string $config
     */
    public function __construct(string $config)
    {
        $this->config = DB_Config::getConfig($config);

        @parent::__construct($this->config->host, $this->config->user, $this->config->password, $this->config->db);
        if (mysqli_connect_errno()) {
            die("Connection Error (" .
                mysqli_connect_error() . ') ' .
                mysqli_connect_errno()
            );
        }
    }

    /**
     * @param DB_Config[]|string $config
     * @return MySQL
     */
    public static function getInstance(string $config = DB_Config::DEFAULT): MySQL
    {
        if (!isset(self::$instance)) {
            self::$instance = new self($config);
        }
        return self::$instance;
    }

    /**
     * Override for query
     * @param string $query
     * @param null $resultmode
     * @return mysqli_result
     */
    final public function query($query, $resultmode = NULL): mysqli_result
    {
        Debug::SQL_Query($query);
        if (!$this->real_query($query)) {
            $this->config->ErrorEvent($query, $this);
            //  Debug::SQL_Error($query, $this);
        }
        return new mysqli_result($this);
    }

    /**
     * This function inserts data to the database
     *   takes an array and turns it into SQL with escaped string
     * @param string $table
     * @param array $data
     * @return mysqli_result
     */
    final public function insertArray(string $table, array $data): mysqli_result
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

        $query = sprintf(/** @lang sql */ "INSERT INTO `%s` (%s) VALUES (%s)", $table, $field_list, $value_list);

        if (!($result = $this->query($query))) {
            Debug::SQL_Error($query, $this);
        }
        return $result;
    }

    /**
     * This function inserts data to the database
     *   takes an array and turns it into SQL with escaped string
     * @param string $table
     * @param array $data
     * @param string $key
     * @param string $keyvalue
     * @return mysqli_result
     */
    final public function updateArray(string $table, array $data, string $key, string $keyvalue): mysqli_result
    {
        foreach ($data as $field => $value) {
            if (in_array($value, SQL::$functions) || is_numeric($value)) {
                $safevalue = $value;
            } else {
                $safevalue = "'" . @parent::real_escape_string($value) . "'";
            }
            $fields[] = "$field = $safevalue";
        }
        $field_list = join(',', $fields);

        $query = sprintf(/** @lang sql */ "UPDATE `%s` SET %s WHERE %s = %s", $table, $field_list, $key, $keyvalue);

        if (!($result = $this->query($query))) {
            Debug::SQL_Error($query, $this);
        }
        return $result;
    }

    /**
     * Get the MySQL server's current time
     * @return string
     */
    public static function Now(): string
    {
        $inst = self::getInstance();
        $result = $inst->query("SELECT now();");
        return $result->fetch_row()[0];
    }
}