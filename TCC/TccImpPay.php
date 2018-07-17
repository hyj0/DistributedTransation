<?php
/**
 * Created by PhpStorm.
 * User: dell-pc
 * Date: 2018/7/11
 * Time: 21:54
 */

namespace TCC;
use DT\Config;

require_once "./TccTemplate.php";
require_once "../Config.php";


class TccImpPayOut extends TccTemplate
{
    private $uid;
    private $fee;

    private  $taskCommon;
    private $conn;

    public function __construct(TaskCommon $taskCommon)
    {
        $this->taskCommon = $taskCommon;
        $content = json_decode($this->taskCommon->getTaskContent());
        $this->uid = $content->uid;
        $this->fee = $content->fee;

        try {
            $this->conn = (new Config())->getPdoConnectByUid($this->uid);
        } catch (\PDOException $e) {
            $this->errStr = "TccTry err=".$e->getMessage()." errcode=".$e->getCode();
            return ;
        }
        $this->conn->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
    }

    public function TccTry() {
        try {
            $this->conn->beginTransaction();
            $sth = $this->conn->prepare("SELECT tttrl.status
                FROM t_tcc_temp_resource_log tttrl
                WHERE tttrl.tcc_trans_no = :tcc_trans_no AND tttrl.task_id = :task_id
                FOR UPDATE ;");
            $sth->execute(array(
                "tcc_trans_no" => $this->taskCommon->getTccTransNo(),
                "task_id" => $this->taskCommon->getTaskId(),
                ));
            if ($sth->rowCount() == 1) {
                $result = $sth->fetch(\PDO::FETCH_ASSOC);
                $status = $result['status'];
                $this->conn->rollBack();
                //'1--已锁定 2--锁定失败 11--释放资源成功'
                if ($status == 1) {
                    return 0;
                } elseif ($status == 2) {
                    return -1;
                } elseif ($status == 11) {
                    return -2;
                } else {
                    return -3;
                }
            }
            //empty
            if ($this->taskCommon->getTaskType() == 2) {
                //收入
                $sth = $this->conn->prepare("INSERT INTO t_tcc_temp_resource_log
                 (task_id, tcc_trans_no, status, task_type, temp_resource_content, update_time, create_time)
                VALUES (:task_id, :tcc_trans_no, :status, :task_type, '', sysdate(), sysdate());");
                $sth->execute(array(
                    "task_id" => $this->taskCommon->getTaskId(),
                    "tcc_trans_no" => $this->taskCommon->getTccTransNo(),
                    "status" => 1,
                    "task_type" => $this->taskCommon->getTaskType()
                ));
            } elseif ($this->taskCommon->getTaskType() == 1) {
                //支出
                $sth = $this->conn->prepare("SELECT fee, frozenFee, fee-ta.frozenFee as resFee
                    FROM t_account ta
                    WHERE ta.uid = :uid FOR UPDATE ;");
                $sth->execute(array(
                    "uid" => $this->uid,
                ));
                if ($sth->rowCount() != 1) {
                    $this->conn->rollBack();
                    $this->errStr = "no such account uid=".$this->uid;
                    return -4;
                }
                $result = $sth->fetch(\PDO::FETCH_ASSOC);
                $resFee = $result["resFee"];
                if ($resFee < $this->fee) {
                    $this->errStr = " resFee < payFee";
                    $this->conn->rollBack();
                    return -5;
                }

                //add log
                $sth = $this->conn->prepare("INSERT INTO t_tcc_temp_resource_log
                    (task_id, tcc_trans_no, status, task_type, temp_resource_content, update_time, create_time)
                    VALUES (:task_id, :tcc_trans_no, :status, :task_type, '', sysdate(), sysdate());");
                $sth->execute(array(
                    "task_id" => $this->taskCommon->getTaskId(),
                    "tcc_trans_no" => $this->taskCommon->getTccTransNo(),
                    "status" => 1,
                    "task_type" => $this->taskCommon->getTaskType()
                ));
                //change account
                $sth = $this->conn->prepare("UPDATE t_account
                    SET frozenFee = frozenFee+:payFee, update_time=sysdate()
                    WHERE uid = :uid;");
                $sth->execute(array(
                    "payFee" =>$this->fee,
                    "uid" => $this->uid
                ));

            } else {
                $this->conn->rollBack();
                $this->errStr = "TccImpPayOut task_type err task_type=".$this->taskCommon->getTaskType();
                return -3;
            }

        } catch (\PDOException $e) {
            $this->errStr = "TccTry err=".$e->getMessage()." errcode=".$e->getCode();
            $this->conn->rollBack();
            return -2;
        }
        $this->conn->commit();
        return 0;
    }

    public function TccConfirm() {
        try {
            $this->conn->beginTransaction();
            $sth = $this->conn->prepare("SELECT tttrl.status
                FROM t_tcc_temp_resource_log tttrl
                WHERE tttrl.tcc_trans_no = :tcc_trans_no AND tttrl.task_id = :task_id
                FOR UPDATE ;");
            $sth->execute(array(
                "tcc_trans_no" => $this->taskCommon->getTccTransNo(),
                "task_id" => $this->taskCommon->getTaskId(),
            ));
            if ($sth->rowCount() == 1) {
                $result = $sth->fetch(\PDO::FETCH_ASSOC);
                $status = $result['status'];
                //'1--已锁定 2--锁定失败 11--释放资源成功'
                if ($status == 1) {
                    //update temp resource log
                    $sth = $this->conn->prepare("UPDATE t_tcc_temp_resource_log
                        SET status = 11, update_time=sysdate()
                        WHERE task_id = :task_id AND tcc_trans_no = :tcc_trans_no
                          AND status = 1;");
                    $sth->execute(array(
                        "task_id" => $this->taskCommon->getTaskId(),
                        "tcc_trans_no" => $this->taskCommon->getTccTransNo()
                    ));
                    //change account
                    $sth  = $this->conn->prepare("UPDATE t_account
                        SET fee = fee - :payFee, frozenFee = frozenFee - :payFee, update_time=sysdate()
                        WHERE uid = :uid;");
                    $sth->execute(array(
                        "payFee" => $this->fee,
                        "uid" => $this->uid
                    ));
                } elseif ($status == 2) {
                    $this->conn->rollBack();
                    $this->errStr = "lock resource had failure";
                    return -2;
                } elseif ($status == 11) {
                    //success before
                    $this->conn->rollBack();
                    return 0;
                } else {
                    $this->conn->rollBack();
                    $this->errStr = "t_tcc_temp_resource_log status err status=".$status;
                    return -3;
                }
            } else {
                $this->conn->rollBack();
                $this->errStr = "no found t_tcc_temp_resource_log ";
                return -5;
            }
        } catch (\PDOException $e) {
            $this->errStr = "TccTry err=".$e->getMessage()." errcode=".$e->getCode();
            $this->conn->rollBack();
            return -2;
        }
        $this->conn->commit();
        return 0;
    }

    public function TccCancel() {
        try {
            $this->conn->beginTransaction();
            //update temp resource log
            //2-->11
            $sth = $this->conn->prepare("UPDATE t_tcc_temp_resource_log
                        SET status = 11, update_time=sysdate()
                        WHERE task_id = :task_id AND tcc_trans_no = :tcc_trans_no
                          AND status = 2;");
            $sth->execute(array(
                "task_id" => $this->taskCommon->getTaskId(),
                "tcc_trans_no" => $this->taskCommon->getTccTransNo()
            ));
            if ($sth->rowCount() == 1) {
                $this->conn->commit();
                return 0;
            }

            //1-->11
            $sth = $this->conn->prepare("UPDATE t_tcc_temp_resource_log
                        SET status = 11, update_time=sysdate()
                        WHERE task_id = :task_id AND tcc_trans_no = :tcc_trans_no
                          AND status = 1;");
            $sth->execute(array(
                "task_id" => $this->taskCommon->getTaskId(),
                "tcc_trans_no" => $this->taskCommon->getTccTransNo()
            ));
            if ($sth->rowCount() == 0) {
                $this->conn->commit();
                return 0;
            }

            //change account frozenFee
            $sth  = $this->conn->prepare("UPDATE t_account
                        SET frozenFee = frozenFee - :payFee, update_time=sysdate()
                        WHERE uid = :uid;");
            $sth->execute(array(
                "payFee" => $this->fee,
                "uid" => $this->uid
            ));

        } catch (\PDOException $e) {
            $this->errStr = "TccCancel err=".$e->getMessage()." errcode=".$e->getCode();
            $this->conn->rollBack();
            return -2;
        }
        $this->conn->commit();
        return 0;
    }
}

class TccImpPayIn extends TccTemplate
{
    private $uid;
    private $fee;

    private $taskCommon;
    private $conn;

    public function __construct(TaskCommon $taskCommon)
    {
        $this->taskCommon = $taskCommon;
        $content = json_decode($this->taskCommon->getTaskContent());
        $this->uid = $content->uid;
        $this->fee = $content->fee;
        try {
            $this->conn = (new Config())->getPdoConnectByUid($this->uid);
        } catch (\PDOException $e) {
            $this->errStr = "TccTry err=".$e->getMessage()." errcode=".$e->getCode();
            return ;
        }
        $this->conn->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
    }

    public function TccTry() {
        try {
            $this->conn->beginTransaction();
            $sth = $this->conn->prepare("INSERT INTO t_tcc_temp_resource_log
              (task_id, tcc_trans_no, status, task_type, temp_resource_content, update_time, create_time)
                    VALUES (:task_id, :tcc_trans_no, :status, :task_type, '', sysdate(), sysdate());");
            $sth->execute(array(
                "task_id" => $this->taskCommon->getTaskId(),
                "tcc_trans_no" => $this->taskCommon->getTccTransNo(),
                "status" => 1,
                "task_type" => $this->taskCommon->getTaskType()
            ));
        } catch (\PDOException $e) {
            $this->errStr = "TccTry err=".$e->getMessage()." errcode=".$e->getCode();
            $this->conn->rollBack();
            return -2;
        }
        $this->conn->commit();
        return 0;
    }

    public function TccConfirm() {
        try {
            $this->conn->beginTransaction();
            //update temp resource log
            $sth = $this->conn->prepare("UPDATE t_tcc_temp_resource_log
                        SET status = 11, update_time=sysdate()
                        WHERE task_id = :task_id AND tcc_trans_no = :tcc_trans_no
                          AND status = 1;");
            $sth->execute(array(
                "task_id" => $this->taskCommon->getTaskId(),
                "tcc_trans_no" => $this->taskCommon->getTccTransNo()
            ));
            if ($sth->rowCount() == 0) {
                $this->conn->rollBack();
                return 0;
            }

            //change account
            $sth  = $this->conn->prepare("UPDATE t_account
                        SET fee = fee + :payFee, update_time=sysdate()
                        WHERE uid = :uid;");
            $sth->execute(array(
                "payFee" => $this->fee,
                "uid" => $this->uid
            ));
        } catch (\PDOException $e) {
            $this->errStr = "TccTry err=".$e->getMessage()." errcode=".$e->getCode();
            $this->conn->rollBack();
            return -2;
        }
        $this->conn->commit();
        return 0;
    }

    public function TccCancel() {
        try {
            //update temp resource log
            $sth = $this->conn->prepare("UPDATE t_tcc_temp_resource_log
                        SET status = 11, update_time=sysdate()
                        WHERE task_id = :task_id AND tcc_trans_no = :tcc_trans_no
                          AND status = 1;");
            $sth->execute(array(
                "task_id" => $this->taskCommon->getTaskId(),
                "tcc_trans_no" => $this->taskCommon->getTccTransNo()
            ));
        } catch (\PDOException $e) {
            $this->errStr = "TccTry err=".$e->getMessage()." errcode=".$e->getCode();
            $this->conn->rollBack();
            return -2;
        }
        $this->conn->commit();
        return 0;
    }
}