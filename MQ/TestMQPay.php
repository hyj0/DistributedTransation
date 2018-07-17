<?php
/**
 * Created by PhpStorm.
 * User: dell-pc
 * Date: 2018/7/2
 * Time: 23:06
 */

require_once "../Config.php";
require_once "./Pay.php";

if (true)
{
    $paySect1 = new \DT\PaySect1(1, 10001, 10002, 10);
    $ret = $paySect1->DoPay();
    print_r($ret." ".$paySect1->getErrStr());

    (new \DT\PayMq())->CallPaySect2();

}

if (true)
{
    $paySect1 = new \DT\PaySect1(1, 10001, 10002, 50);
    $ret = $paySect1->DoPay();
    print_r($ret." ".$paySect1->getErrStr());

    (new \DT\PayMq())->CallPaySect2();

}