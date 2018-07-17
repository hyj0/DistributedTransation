<?php
/**
 * Created by PhpStorm.
 * User: dell-pc
 * Date: 2018/7/3
 * Time: 23:37
 */

namespace DT;


class DataMap
{
    public static function getUidHash($uid, $size)  {
        return $uid % $size;
    }
}