<?php
/*
 * Copyright (c) 2021. Lorem ipsum dolor sit amet, consectetur adipiscing elit.
 * Morbi non lorem porttitor neque feugiat blandit. Ut vitae ipsum eget quam lacinia accumsan.
 * Etiam sed turpis ac ipsum condimentum fringilla. Maecenas magna.
 * Proin dapibus sapien vel ante. Aliquam erat volutpat. Pellentesque sagittis ligula eget metus.
 * Vestibulum commodo. Ut rhoncus gravida arcu.
 */

namespace SimpleORM;

class DB_Config
{
    /**
     * @var DB_Config[]
     */
    protected static array $configs = array();
    public string $host;
    public string $user;
    public string $password;
    public string $db;

    public static function getConfig($config_name): DB_Config
    {
        if(!isset(self::$configs[$config_name])) {
            self::$configs[$config_name] = new self();
        }
        return self::$configs[$config_name];
    }

}