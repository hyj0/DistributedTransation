<?php
/**
 * Created by PhpStorm.
 * User: dell-pc
 * Date: 2018/7/3
 * Time: 23:24
 */

namespace DT;


class PaySect1
{
    private $type = -1;
    private $from_uid = -1;
    private $to_uid = -1;
    private $fee = -1;
    private $tid = -1;

    private $errStr = "";

    public function newTransNo($fromUid, $toUid) {
        date_default_timezone_set('UTC');
        return sprintf($this->from_uid."-".$this->to_uid."-".date("Ymdhis"). "-" . rand(10000, 90000));
    }

    /**
     * @return string
     */
    public function getErrStr()
    {
        return $this->errStr;
    }

    /**
     * PaySect1 constructor.
     * @param int $type
     * @param int $from_uid
     * @param int $to_uid
     * @param int $fee
     */
    public function __construct($type, $from_uid, $to_uid, $fee)
    {
        $this->type = $type;
        $this->from_uid = $from_uid;
        $this->to_uid = $to_uid;
        $this->fee = $fee;
    }

    public function DoPay() {
        if ($this->type != 1  || $this->from_uid <= 0 || $this->to_uid <= 0
            || $this->fee <= 0) {
            $this->errStr = "params err";
            return -1;
        }

        $conn = null;
        try {
            $conn = (new Config())->getPdoConnectByUid($this->from_uid);
        } catch (\PDOException $e) {
            $this->errStr = "getPdoConnect err=".$e->getMessage()." errcode=".$e->getCode();
            return -1;
        }


        try {
            $conn->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

            $conn->beginTransaction();
            $sth = $conn->prepare("SELECT ta.fee FROM t_account ta WHERE ta.uid = ? FOR UPDATE ;");
            $sth->execute(array($this->from_uid));

            if ($sth->rowCount() != 1) {
                $conn->rollBack();
                $this->errStr = "no found user:".$this->from_uid;
                return -2;
            }
            $fromUserAccount = $sth->fetch(\PDO::FETCH_ASSOC);
            if (!$fromUserAccount) {
                $this->errStr = "get User Account info err=fecth null";
                $conn->rollBack();
                return -3;
            }

            $fromUserFee = $fromUserAccount["fee"];
            if ($fromUserFee < $this->fee) {
                $this->errStr = "account fee < trans fee!!";
                $conn->rollBack();
                return -4;
            }

            $trans_no = $this->newTransNo($this->from_uid, $this->to_uid);

            $sth = $conn->prepare("INSERT INTO t_account_log
              (uid, type, sub_type, trans_no, fee, org_fee, end_fee,  update_time, create_time)
               VALUES (:uid, :type, :sub_type, :trans_no, :fee, :org_fee, :end_fee, sysdate(), sysdate());");
            $sth->execute(array(
                ":uid" => $this->from_uid,
                ":type" => $this->type,
                ":sub_type" => 2,
                ":trans_no" => $trans_no,
                ":fee" =>$this->fee,
                ":org_fee" => $fromUserFee,
                ":end_fee" => $fromUserFee - $this->fee
            ));
            if ($sth->rowCount() != 1) {
                $this->errStr = "insert t_account_log rowCount!=1";
                $conn->rollBack();
                return -4;
            }

            $sth = $conn->prepare("INSERT INTO t_trans_log
                 (type, trans_no, from_uid, to_uid, fee, trans_state, update_time, create_time)
                  VALUES (:type, :trans_no, :from_uid, :to_uid, :fee, :trans_state,  sysdate(), sysdate())");
            $sth->execute(array(
                ":type" => $this->type,
                ":trans_no" => $trans_no,
                ":from_uid" => $this->from_uid,
                ":to_uid" => $this->to_uid,
                ":fee" => $this->fee,
                "trans_state" => 1
            ));
            if ($sth->rowCount() != 1) {
                $this->errStr = "insert t_trans_log rowCount!=1";
                $conn->rollBack();
                return -4;
            }

            $this->tid = $conn->lastInsertId();

            $sth = $conn->prepare("UPDATE t_account
                SET fee = :fee, update_time=sysdate()
                WHERE uid = :uid;");
            $sth->execute(array(
                ":fee" => $fromUserFee - $this->fee,
                ":uid" => $this->from_uid
            ));
            if ($sth->rowCount() != 1) {
                $this->errStr = "UPDATE t_account rowCount!=1";
                $conn->rollBack();
                return -5;
            }

            $conn->commit();
        } catch (\PDOException $err) {
            $this->errStr = "Pdo err=".$err->getMessage()." errcode=".$err->getCode();
            $conn->rollBack();
            return -22;
        }
        return 0;
    }

}

class PayMq
{
    private $errStr;

    public function CallPaySect2() {
        foreach ((new Config())->dbSourceList as $db) {
            $conn = null;
            try {
                $conn = (new Config())->PdoConnect($db);
            } catch (\PDOException $e) {
                $this->errStr = "getPdoConnect err=".$e->getMessage()." errcode=".$e->getCode();
                continue;
            }

            try {
                $sth = $conn->prepare("SELECT  type, ttl.tid, ttl.trans_no, from_uid, ttl.to_uid, ttl.fee
                    FROM t_trans_log ttl
                    WHERE ttl.trans_state = 1;");
                $sth->execute();
                if ($sth->rowCount() <= 0) {
                    continue;
                }
                while (1) {
                    $result = $sth->fetch(\PDO::FETCH_ASSOC);
                    if (!$result) {
                        break;
                    }

                    $type = $result["type"];
                    $tid = $result["tid"];
                    $trans_no = $result["trans_no"];
                    $from_uid = $result["from_uid"];
                    $to_uid = $result["to_uid"];
                    $fee = $result["fee"];

                    $paySect2 = new PaySect2($tid, $type, $trans_no, $from_uid, $to_uid, $fee);
                    $ret = $paySect2->DoPay();
                    if ($ret == 0) {
                        $this->FinishPay($tid, $trans_no, $from_uid);
                    }
                }
            } catch (\PDOException $err) {
                $this->errStr = "pdo err=".$err->getMessage();
                continue;
            }
        }
    }

    public function FinishPay($tid, $tran_no, $fromUid) {
        $conn = null;
        try {
            $conn = (new Config())->getPdoConnectByUid($fromUid);
        } catch (\PDOException $e) {
            $this->errStr = "getPdoConnect err=".$e->getMessage()." errcode=".$e->getCode();
            return -1;
        }

        try {
            $sth = $conn->prepare("UPDATE t_trans_log
                 SET trans_state = 2, update_time=sysdate()
                  WHERE trans_no = :tran_no And tid = :tid AND trans_state = 1;");
            $sth->execute(array(
                ":tran_no" => $tran_no,
                ":tid" => $tid
            ));

        } catch (\PDOException $err) {
            $this->errStr = "pdo err=".$err->getMessage();
            return -1;
        }

        return 0;
    }
}

class PaySect2
{
    private $tid, $type, $trans_no, $from_uid, $to_uid, $fee;

    /**
     * PaySect2 constructor.
     * @param $tid
     * @param $type
     * @param $trans_no
     * @param $from_uid
     * @param $to_uid
     * @param $fee
     */
    public function __construct($tid, $type, $trans_no, $from_uid, $to_uid, $fee)
    {
        $this->tid = $tid;
        $this->type = $type;
        $this->trans_no = $trans_no;
        $this->from_uid = $from_uid;
        $this->to_uid = $to_uid;
        $this->fee = $fee;
    }


    public function DoPay() {
        $conn = null;
        try {
            $conn = (new Config())->getPdoConnectByUid($this->to_uid);
        } catch (\PDOException $e) {
            $this->errStr = "getPdoConnect err=".$e->getMessage()." errcode=".$e->getCode();
            return -1;
        }

        try {
            $conn->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

            $conn->beginTransaction();

            $sth = $conn->prepare("SELECT ta.fee FROM t_account ta WHERE ta.uid = ? FOR UPDATE ;");
            $sth->execute(array($this->to_uid));

            if ($sth->rowCount() != 1) {
                $conn->rollBack();
                $this->errStr = "no found user:".$this->from_uid;
                return -2;
            }
            $toUserAccount = $sth->fetch(\PDO::FETCH_ASSOC);
            if (!$toUserAccount) {
                $this->errStr = "get User Account info err=fecth null";
                $conn->rollBack();
                return -3;
            }

            $toUserFee = $toUserAccount["fee"];

            $sth = $conn->prepare("INSERT INTO t_account_log
                (uid, type, sub_type, trans_no, fee, org_fee, end_fee, update_time, create_time)
                  VALUES (:uid, :type, :sub_type, :trans_no, :fee, :org_fee, :end_fee, sysdate(), sysdate());");
            $sth->execute(array(
                ":uid" => $this->to_uid,
                ":type" => $this->type,
                ":sub_type" => 1,
                ":trans_no" => $this->trans_no,
                ":fee" => $this->fee,
                ":org_fee" => $toUserFee,
                ":end_fee" => $toUserFee + $this->fee
            ));
            if ($sth->rowCount() != 1) {
                $this->errStr = "INSERT INTO t_account_log err=fecth null";
                $conn->rollBack();
                return -3;
            }

            $sth = $conn->prepare("UPDATE t_account
                SET fee = :fee, update_time=sysdate()
                WHERE uid = :uid;");
            $sth->execute(array(
                ":fee" => $toUserFee + $this->fee,
                ":uid" => $this->to_uid
            ));
            if ($sth->rowCount() != 1) {
                $this->errStr = "UPDATE t_account rowCount!=1";
                $conn->rollBack();
                return -4;
            }
            $conn->commit();
        } catch (\PDOException $e) {
            $this->errStr = "pdo err=".$e->getMessage()." errcode=".$e->getCode();
            return -1;
        }

        return 0;
    }
}