<?php
/**
 * Created by PhpStorm.
 * User: dell-pc
 * Date: 2018/7/11
 * Time: 0:14
 */

require_once "TccTrans.php";

if (true)
{
    $taskListContent = array(
        array(
            "task_type" => 1,//pay out
            "uid" => 10001,
            "fee" => 10
        ),
        array(
            "task_type" => 1, //pay out
            "uid" => 10002,
            "fee" => 20
        ),
        array(
            "task_type" => 2, //pay in
            "uid" => 10003,
            "fee" => 30
        )
    );

    $tccTrans = new \TCC\TccTrans(1, count($taskListContent), $taskListContent);

    $ret = $tccTrans->Init();
    if ($ret != 0)
    {
        print_r("tccTrans Init err ret=".$ret." err=".$tccTrans->errStr);
        die();
    }

    for ($index = 0; $index < 3; $index++) {
        $ret = $tccTrans->LoopRunTask();
    }
}

if (true)
{
    $taskListContent = array(
        array(
            "task_type" => 1,//pay out
            "uid" => 10001,
            "fee" => 10001
        ),
        array(
            "task_type" => 1, //pay out
            "uid" => 10002,
            "fee" => 20
        ),
        array(
            "task_type" => 2, //pay in
            "uid" => 10003,
            "fee" => 10021
        )
    );

    $tccTrans = new \TCC\TccTrans(1, count($taskListContent), $taskListContent);

    $ret = $tccTrans->Init();
    if ($ret != 0)
    {
        print_r("tccTrans Init err ret=".$ret." err=".$tccTrans->errStr);
        die();
    }

    for ($index = 0; $index < 3; $index++) {
        $ret = $tccTrans->LoopRunTask();
    }
}


