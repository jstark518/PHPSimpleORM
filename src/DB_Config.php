<?php
/*
 * Copyright (c) 2021. Lorem ipsum dolor sit amet, consectetur adipiscing elit.
 * Morbi non lorem porttitor neque feugiat blandit. Ut vitae ipsum eget quam lacinia accumsan.
 * Etiam sed turpis ac ipsum condimentum fringilla. Maecenas magna.
 * Proin dapibus sapien vel ante. Aliquam erat volutpat. Pellentesque sagittis ligula eget metus.
 * Vestibulum commodo. Ut rhoncus gravida arcu.
 */

namespace SimpleORM;

use Closure;
use phpDocumentor\Reflection\Types\Callable_;

class DB_Config
{
    /**
     * @var DB_Config[]
     */
    public const DEFAULT = "main";
    private static array $configs = array();
    public string $host;
    public string $user;
    public string $password;
    public string $db;
    public int $flags = 0;
    public Closure $onError ;

    public static function getConfig(string $config_name = self::DEFAULT): DB_Config
    {
        if(!isset(self::$configs[$config_name])) {
            self::$configs[$config_name] = new self();
        }
        return self::$configs[$config_name];
    }
    final public function ErrorEvent(string $error, object $src): void
    {
        if(is_callable($this->onError)) call_user_func($this->onError, $error, $src);
    }
}