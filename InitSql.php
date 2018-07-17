<?php
/**
 * Created by PhpStorm.
 * User: dell-pc
 * Date: 2018/7/18
 * Time: 0:26
 */

namespace DT;
require_once "./Config.php";


class Init
{
    public function __construct()
    {
        $config = new Config();
        foreach ($config->dbSourceList as $mysqlCfg) {
            $conn = $config->PdoConnect($mysqlCfg);
            $conn->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
            $this->CreatePayTables($conn);
        }

        $this->CreateTccTables($config->PdoConnect($config->tccDbSource));
    }

    public function CreatePayTables(\PDO $conn) {
        try {
            $conn->exec("
DROP TABLE IF EXISTS t_account;
CREATE TABLE `t_account` (
  `uid` int(11) NOT NULL,
  `name` varchar(64) NOT NULL,
  `status` int(11) NOT NULL,
  `password` varchar(128) NOT NULL,
  `fee` int(11) NOT NULL DEFAULT '0' COMMENT '余额',
  `frozenFee` int(11) NOT NULL DEFAULT '0',
  `update_time` datetime NOT NULL,
  `create_time` datetime NOT NULL,
  PRIMARY KEY (`uid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

DROP TABLE IF EXISTS t_account_log;
CREATE TABLE t_account_log
(
    id INT(11) PRIMARY KEY NOT NULL AUTO_INCREMENT,
    uid INT(11) NOT NULL,
    type INT(11) NOT NULL COMMENT '类型 1--转账',
    sub_type INT(11) NOT NULL COMMENT '收支类型 1--收入 2--支出',
    trans_no VARCHAR(100) NOT NULL COMMENT '交易号',
    fee INT(11) NOT NULL COMMENT '金额，单位分',
    org_fee INT(11) NOT NULL COMMENT '交易前的余额',
    end_fee INT(11) NOT NULL COMMENT '交易后的余额',
    update_time DATETIME NOT NULL,
    create_time DATETIME NOT NULL
);
CREATE UNIQUE INDEX t_account_log_trans_no_uid_uindex ON t_account_log (trans_no, uid);

DROP TABLE IF EXISTS t_trans_log;
CREATE TABLE t_trans_log
(
    tid INT(11) PRIMARY KEY NOT NULL AUTO_INCREMENT,
    type INT(11) NOT NULL COMMENT '交易类型 1--转账 ',
    trans_no VARCHAR(100) NOT NULL COMMENT '交易号',
    from_uid INT(11) NOT NULL,
    to_uid INT(11) NOT NULL,
    fee INT(11) NOT NULL COMMENT '交易额，单位分',
    trans_state INT(11) DEFAULT '1' NOT NULL COMMENT '交易状态 1--已付款，2--对方已收到（完成）',
    update_time DATETIME NOT NULL,
    create_time DATETIME NOT NULL
);
CREATE UNIQUE INDEX t_trans_log_trans_no_uindex ON t_trans_log (trans_no);

DROP TABLE IF EXISTS t_tcc_temp_resource_log;
CREATE TABLE `t_tcc_temp_resource_log` (
  `task_id` int(11) NOT NULL DEFAULT '0' COMMENT '任务id',
  `tcc_trans_no` varchar(128) NOT NULL,
  `status` int(11) NOT NULL COMMENT '1--已锁定 2--锁定失败 11--释放资源成功',
  `task_type` int(11) NOT NULL,
  `temp_resource_content` varchar(256) NOT NULL COMMENT '资源内容',
  `update_time` datetime NOT NULL,
  `create_time` datetime NOT NULL,
  PRIMARY KEY (`tcc_trans_no`,`task_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;");
        } catch (\PDOException $e) {
            print_r($e->getMessage());
        }
    }

    public function CreateTccTables(\PDO $conn) {
        try {
            $conn->exec("
DROP TABLE IF EXISTS t_tcc_transaction;
CREATE TABLE t_tcc_transaction
(
    tcc_id INT(11) PRIMARY KEY NOT NULL AUTO_INCREMENT,
    tcc_trans_no VARCHAR(128) NOT NULL,
    trans_type INT(11) NOT NULL COMMENT '交易类型 1--转账 2--下单',
    task_count INT(11) NOT NULL COMMENT '任务数',
    tcc_status INT(11) NOT NULL COMMENT '状态 0--初始 1--try完成  2--conform完成
	11--try失败 22--cancel完成',
    update_time DATETIME NOT NULL,
    create_time DATETIME NOT NULL
);

DROP TABLE IF EXISTS t_tcc_task;
CREATE TABLE t_tcc_task
(
    task_id INT(11) PRIMARY KEY NOT NULL AUTO_INCREMENT,
    tcc_trans_no VARCHAR(128) DEFAULT '0' NOT NULL,
    task_type INT(11) NOT NULL COMMENT '任务类型 1--支出 2--收入 3--生成订单 4--减少库存',
    task_status INT(11) NOT NULL COMMENT '任务状态 0--初始 1--try完成 2--conform完成
	  11--try失败 22--cancel完成',
    task_content VARCHAR(256) NOT NULL COMMENT '任务内容',
    updatet_time DATETIME NOT NULL,
    create_time DATETIME NOT NULL
);
CREATE UNIQUE INDEX t_tcc_transaction_tcc_trans_no_uindex ON t_tcc_transaction (tcc_trans_no);
CREATE UNIQUE INDEX tcc_task_task_id_pk ON t_tcc_task (task_id);
CREATE UNIQUE INDEX tcc_task_task_id_tcc_trans_no_pk ON t_tcc_task (task_id, tcc_trans_no);");
        } catch (\PDOException $e) {
            print_r($e->getMessage());
        }
    }

    public function CreateUser($uid, $name, $fee) {
        $config = new Config();
        $conn = $config->getPdoConnectByUid($uid);
        $sth = $conn->prepare("INSERT INTO t_account (uid, name, status, password, fee, frozenFee, update_time, create_time)
          VALUES (:uid, :name, 1, :password, :fee, 0, sysdate(), sysdate());");
        $sth->execute(array(
            "uid" => $uid,
            "name" => $name,
            "password" => "test",
            "fee" => $fee
        ));
    }
}

$init = new \DT\Init();
$init->CreateUser(10001, "hyj1", 10000);
$init->CreateUser(10002, "hyj2", 10000);
$init->CreateUser(10003, "hyj3", 10000);
