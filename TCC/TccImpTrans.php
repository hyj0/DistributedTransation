<?php
/**
 * Created by PhpStorm.
 * User: dell-pc
 * Date: 2018/7/11
 * Time: 21:55
 */

namespace TCC;
require_once "./TccTemplate.php";


class TccImpTransCreateOrder extends TccTemplate
{
    private $taskCommon;
    public function __construct($taskCommon)
    {
        $this->taskCommon = $taskCommon;
    }

    public function TccTry() {

    }

    public function TccConfirm() {

    }

    public function TccCancel() {
        print_r("tcc cancel\n");
    }
}

class TccImpTransReduceStock extends TccTemplate
{
    private $taskCommon;
    public function __construct($taskCommon)
    {
        $this->taskCommon = $taskCommon;
    }

    public function TccTry() {

    }

    public function TccConfirm() {

    }

    public function TccCancel() {
        print_r("tcc cancel\n");
    }
}