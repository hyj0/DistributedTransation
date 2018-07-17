<?php
/**
 * Created by PhpStorm.
 * User: dell-pc
 * Date: 2018/7/3
 * Time: 23:09
 */

namespace DT;

class MysqlCfg
{
    private $host = null;
    private $port = -1;
    private $user = null;
    private $password = null;
    private $database = null;

    /**
     * @return null
     */
    public function getHost()
    {
        return $this->host;
    }

    /**
     * @return int
     */
    public function getPort()
    {
        return $this->port;
    }

    /**
     * @return null
     */
    public function getUser()
    {
        return $this->user;
    }

    /**
     * @return null
     */
    public function getPassword()
    {
        return $this->password;
    }

    /**
     * @return null
     */
    public function getDatabase()
    {
        return $this->database;
    }

    /**
     * MysqlCfg constructor.
     * @param null $host
     * @param int $port
     * @param null $user
     * @param null $password
     * @param null $database
     */
    public function __construct($host, $port, $user, $password, $database)
    {
        $this->host = $host;
        $this->port = $port;
        $this->user = $user;
        $this->password = $password;
        $this->database = $database;
    }

}

class Config
{
    function __construct()
    {
        $this->dbSourceList = array(
            new MysqlCfg("127.0.0.1", 33060, "root", "root", "dtpay"),
            new MysqlCfg("127.0.0.1", 33061, "root", "root", "dtpay"),
        );
        $this->tccDbSource = new MysqlCfg("127.0.0.1", 33060, "root", "root", "tcc");
    }

    /**
     * @return array
     */
    public function getDbSourceList()
    {
        return $this->dbSourceList;
    }

    function mysqliConnect(MysqlCfg $cfg) {
        $mysql = mysqli_connect($cfg->getHost(), $cfg->getUser(), $cfg->getPassword(), $cfg->getDatabase(), $cfg->getPort());
        return $mysql;
    }

    public function PdoConnect(MysqlCfg $cfg) {
        return new \PDO("mysql:host=".$cfg->getHost().";dbname=".$cfg->getDatabase().";port=".$cfg->getPort().";",
            $cfg->getUser(), $cfg->getPassword());
    }

    public function getDbConnect($index) {
        $hash = $index % count($this->getDbSourceList());
        return $this->mysqliConnect($this->getDbSourceList()[$hash]);
    }

    public function getPdoConnectByUid($uid) {
        $hash = $uid % count($this->getDbSourceList());
        return $this->PdoConnect($this->getDbSourceList()[$hash]);
    }

    public function getTccConnect() {
        return $this->PdoConnect($this->tccDbSource);
    }
}