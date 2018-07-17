<?php
/**
 * Created by PhpStorm.
 * User: dell-pc
 * Date: 2018/7/11
 * Time: 21:55
 */

namespace TCC;

require_once "./TccImpPay.php";
require_once "./TccImpTrans.php";


class TccImp
{
    public static function getTccImpInstance($task_type, $taskCommon) {
        #'任务类型 1--支出 2--收入 3--生成订单 4--减少库存',
        switch ($task_type) {
            case 1:
                return new TccImpPayOut($taskCommon);
                break;
            case 2:
                return new TccImpPayIn($taskCommon);
            case 3:
                return new TccImpTransCreateOrder($taskCommon);
            case 4:
                return new TccImpTransReduceStock($taskCommon);
            default:
                return null;
                break;
        }
    }
}