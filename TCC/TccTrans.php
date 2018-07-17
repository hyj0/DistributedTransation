<?php
/**
 * Created by PhpStorm.
 * User: dell-pc
 * Date: 2018/7/10
 * Time: 23:48
 */

namespace TCC;

use DT\Config;

require_once "../Config.php";
require_once "TaskCommon.php";
require_once "TccImp.php";

class Task
{
    private $tcc_id;
    private $tcc_trans_no ;
    private $trans_type;
    private $task_count;
    private $tcc_status;

    public $errStr;

    public function getTaskList() {
        $retList = array();
        try {
            $this->conn->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
            $sth =  $this->conn->prepare("SELECT task_id, tcc_trans_no, task_type, task_status, task_content
                FROM t_tcc_task ttt
                WHERE ttt.tcc_trans_no = :tcc_trans_no;");
            $sth->execute(array("tcc_trans_no" => $this->tcc_trans_no));
            if ($sth->rowCount() <= 0) {
                $this->errStr = "add get t_tcc_task err  tcc_trans_no=".$this->tcc_trans_no;
                return null;
            }

            while (1) {
                $result = $sth->fetch(\PDO::FETCH_ASSOC);
                if (!$result) {
                    break;
                }
                array_push($retList, $result);
            }
            return $retList;

        } catch (\PDOException $e) {
            $this->errStr = "getTaskList err=".$e->getMessage()." errcode=".$e->getCode();
            return null;
        }
    }

    /**
     * Task constructor.
     * @param $tcc_id
     * @param $tcc_trans_no
     * @param $trans_type
     * @param $task_count
     * @param $tcc_status
     */
    public function __construct($tcc_id, $tcc_trans_no, $trans_type, $task_count, $tcc_status)
    {
        $this->tcc_id = $tcc_id;
        $this->tcc_trans_no = $tcc_trans_no;
        $this->trans_type = $trans_type;
        $this->task_count = $task_count;
        $this->tcc_status = $tcc_status;

        $this->conn = null;
        try {
            $this->conn = (new Config())->getTccConnect();
        } catch (\PDOException $e) {
            $this->errStr = "getTccConnect err=".$e->getMessage()." errcode=".$e->getCode();
            return;
        }

        $this->TaskList = $this->getTaskList();
    }

    public function TaskTry() {
        for ($idx = 0; $idx < count($this->TaskList); $idx++) {
            $taskCommon = new TaskCommon($this->TaskList[$idx]);
            if ($taskCommon->getTaskStatus() == 0) {
                $impl = (new TccImp())->getTccImpInstance($taskCommon->getTaskType(), $taskCommon);
                $ret = $impl->TccTry();
                if ($ret == 0) {
                    $this->updateState($taskCommon->getTaskId(), 0, 1);
                    continue;
                } else {
                    $this->updateState($taskCommon->getTaskId(), 0, 11);
                    return 11;
                }
            } else if ($taskCommon->getTaskStatus() == 11) {
                return -2;
            }
        }
        return 0;
    }

    public function TaskConfirm () {
        for ($idx = 0; $idx < count($this->TaskList); $idx++) {
            $taskCommon = new TaskCommon($this->TaskList[$idx]);
            if ($taskCommon->getTaskStatus() == 2) {
                continue;
            } else if ($taskCommon->getTaskStatus() == 1) {
                $impl = TccImp::getTccImpInstance($taskCommon->getTaskType(), $taskCommon);
                $ret = $impl->TccConfirm();
                if ($ret == 0) {
                    $this->updateState($taskCommon->getTaskId(), 1, 2);
                    continue;
                } else {
                    /*err */
                    return -1;
                }
            }
        }
        return 0;
    }

    public function TaskCancel() {
        for ($idx = 0; $idx < count($this->TaskList); $idx++) {
            $taskCommon = new TaskCommon($this->TaskList[$idx]);
            if ($taskCommon->getTaskStatus() == 22) {
                continue;
            } elseif ($taskCommon->getTaskStatus() == 11) {
                $impl = TccImp::getTccImpInstance($taskCommon->getTaskType(), $taskCommon);
                $ret = $impl->TccCancel();
                if ($ret == 0) {
                    $this->updateState($taskCommon->getTaskId(), 11, 22);
                    continue;
                } else {
                    /*err */
                    return -1;
                }
            } elseif ($taskCommon->getTaskStatus() == 1) {
                $impl = TccImp::getTccImpInstance($taskCommon->getTaskType(), $taskCommon);
                $ret = $impl->TccCancel();
                if ($ret == 0) {
                    $this->updateState($taskCommon->getTaskId(), 1, 22);
                    continue;
                } else {
                    /*err */
                    return -1;
                }
            }
        }
        return 0;
    }

    private function updateState($getTaskId, $fromState, $toState)
    {
        $sth = $this->conn->prepare("UPDATE t_tcc_task
            SET task_status = :toState, updatet_time=sysdate()
            WHERE task_status = :fromState AND task_id = :task_id;");
        $sth->execute(array(
            "toState" => $toState,
            "fromState" => $fromState,
            "task_id" => $getTaskId
        ));
        if ($sth->rowCount() <= 0) {
            return -1;
        }
        return 0;
    }
}

class TccTrans
{
    public $errStr;
    private $trans_type;
    private $task_count;
    private $tcc_status;

    private $tcc_id;
    private $tcc_trans_no;
    private $taskContentList;
    private $conn;
    /**
     * TccTrans constructor.
     * @param $trans_type
     * @param $task_count
     * @param $taskContentList
     */
    public function __construct($trans_type, $task_count, $taskContentList)
    {
        $this->trans_type = $trans_type;
        $this->task_count = $task_count;
        $this->taskContentList = $taskContentList;

        $this->conn = null;
    }

    public function newTransNo($trans_type) {
        date_default_timezone_set('UTC');
        return sprintf("TCC-". $trans_type ."-".date("Ymdhis"). "-" . rand(10000, 90000));
    }

    public function Init()
    {
        $conn = null;
        try {
            $conn = (new Config())->getTccConnect();
        } catch (\PDOException $e) {
            $this->errStr = "getTccConnect err=".$e->getMessage()." errcode=".$e->getCode();
            return -1;
        }

        $this->tcc_trans_no = $this->newTransNo($this->trans_type);

        $this->tcc_status = 0;
        try {
            $conn->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
            $conn->beginTransaction();
            $sth = $conn->prepare("INSERT INTO t_tcc_transaction (tcc_trans_no, trans_type, task_count, tcc_status, update_time, create_time)
                      VALUES (:tcc_trans_no, :trans_type, :task_count, :tcc_status, sysdate(), sysdate());");
            $sth->execute(array(
                "tcc_trans_no" => $this->tcc_trans_no,
                "trans_type" => $this->trans_type,
                "task_count" => $this->task_count,
                "tcc_status" => $this->tcc_status));
            if ($sth->rowCount() != 1) {
                $conn->rollBack();
                $this->errStr = "add t_tcc_transaction err  tcc_trans_no=".$this->tcc_trans_no;
                return -2;
            }
            $this->tcc_id = $conn->lastInsertId();

            for ($i = 0; $i < $this->task_count; $i++) {

                $paramArr = $this->taskContentList[$i];

                $sth = $conn->prepare("INSERT INTO t_tcc_task (tcc_trans_no, task_type, task_status, task_content, updatet_time, create_time)
                VALUES (:tcc_trans_no, :task_type, :task_status, :task_content, sysdate(), sysdate());");
                $sth->execute(array(
                    "tcc_trans_no" => $this->tcc_trans_no,
                    "task_type" => $paramArr["task_type"],
                    "task_status" => 0,
                    "task_content" => json_encode($paramArr)));
                if ($sth->rowCount() != 1) {
                    $conn->rollBack();
                    $this->errStr = "add t_tcc_task err ".json_encode($paramArr);
                    return -3;
                }
            }
        } catch (\PDOException $e) {
            $this->errStr = "Init err=".$e->getMessage()." errcode=".$e->getCode();
            $conn->rollBack();
            return -1;
        }
        $conn->commit();
        return 0;
    }

    /**
     * @return int
     */
    public function LoopRunTask()
    {
        $conn = null;
        try {
            $conn = (new Config())->getTccConnect();
        } catch (\PDOException $e) {
            $this->errStr = "getTccConnect err=".$e->getMessage()." errcode=".$e->getCode();
            return -1;
        }

        $this->conn = $conn;

        try {
            $conn->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
//            $conn->beginTransaction();
            $sth = $conn->prepare("SELECT ttt.tcc_id, tcc_trans_no, trans_type, task_count, tcc_status
                FROM t_tcc_transaction ttt
                WHERE ttt.tcc_status IN (0, 1, 11);");
            $sth->execute();
            if ($sth->rowCount() <= 0) {
//                $conn->rollBack();
                return 0;
            }

            while (1) {
                $result = $sth->fetch(\PDO::FETCH_ASSOC);
                if (!$result) {
                    break;
                }

                $tcc_id = $result["tcc_id"];
                $tcc_trans_no = $result["tcc_trans_no"];
                $trans_type = $result["trans_type"];
                $task_count = $result["task_count"];
                $tcc_status = $result["tcc_status"];
                $task = new Task($tcc_id, $tcc_trans_no, $trans_type, $task_count, $tcc_status);
                if ($tcc_status == 0) {
                    $ret = $task->TaskTry();
                    if ($ret == 0) {
                        $this->updateStatus($tcc_id, 0, 1);
                    }
                    else
                    {
                        $this->updateStatus($tcc_id, 0, 11);
                    }
                } else if ($tcc_status == 1) {
                    $ret = $task->TaskConfirm();
                    if ($ret == 0) {
                        $this->updateStatus($tcc_id, 1, 2);
                    } else {
                        /*err here*/
                    }
                } else if ($tcc_status == 11) {
                    $ret = $task->TaskCancel();
                    if ($ret == 0) {
                        $this->updateStatus($tcc_id, 11, 22);
                    } else {
                        /*err here*/
                    }
                }
            }
        } catch (\PDOException $e) {
            $this->errStr = "LoopRunTask err=".$e->getMessage()." errcode=".$e->getCode();
//            $conn->rollBack();
            return -1;
        }
//        $conn->commit();
        return 0;
    }

    private function updateStatus($tcc_id, $fromState, $toState)
    {
        $sth = $this->conn->prepare("UPDATE t_tcc_transaction
            SET tcc_status = :toState, update_time=sysdate()
            WHERE tcc_status = :fromState AND tcc_id = :tcc_id;");
        $sth->execute(array(
            "toState" => $toState,
            "fromState" => $fromState,
            "tcc_id" => $tcc_id
        ));
        if ($sth->rowCount() <= 0) {
            return -1;
        }
        return 0;
    }
}