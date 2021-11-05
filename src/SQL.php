<?php
/*
 * Copyright (c) 2021. Lorem ipsum dolor sit amet, consectetur adipiscing elit.
 * Morbi non lorem porttitor neque feugiat blandit. Ut vitae ipsum eget quam lacinia accumsan.
 * Etiam sed turpis ac ipsum condimentum fringilla. Maecenas magna.
 * Proin dapibus sapien vel ante. Aliquam erat volutpat. Pellentesque sagittis ligula eget metus.
 * Vestibulum commodo. Ut rhoncus gravida arcu.
 */

namespace SimpleORM;

abstract class SQL
{
    const Descending = "DESC";
    const Ascending = "ASC";
    const Now = "now()";
    const Time6HRS = "DATE_ADD(now(),INTERVAL 6 HOUR)";
    const Time20s = "DATE_ADD(now(),INTERVAL 20 SECOND)";
    const True = "true";
    const False = "false";
    const AND = "AND";
    const OR = "OR";
    const GROUPBY = "GROUP BY";
    const NULL = "NULL";
    public static array $functions = array(self::Now, self::True, self::False, self::Time20s, self::Time6HRS, self::NULL);
}
