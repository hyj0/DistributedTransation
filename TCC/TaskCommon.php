<?php
/**
 * Created by PhpStorm.
 * User: dell-pc
 * Date: 2018/7/11
 * Time: 22:19
 */

namespace TCC;


class TaskCommon
{
    private $task_id;
    private $tcc_trans_no;
    private $task_type;
    private $task_status;
    private $task_content;

    public function __construct($arr)
    {
        $this->setTaskId($arr["task_id"]);
        $this->setTccTransNo($arr["tcc_trans_no"]);
        $this->setTaskType($arr["task_type"]);
        $this->setTaskStatus($arr["task_status"]);
        $this->setTaskContent($arr["task_content"]);
    }

    /**
     * @return mixed
     */
    public function getTaskContent()
    {
        return $this->task_content;
    }

    /**
     * @param mixed $task_content
     */
    public function setTaskContent($task_content)
    {
        $this->task_content = $task_content;
    }

    /**
     * @return mixed
     */
    public function getTaskId()
    {
        return $this->task_id;
    }

    /**
     * @param mixed $task_id
     */
    public function setTaskId($task_id)
    {
        $this->task_id = $task_id;
    }

    /**
     * @return mixed
     */
    public function getTccTransNo()
    {
        return $this->tcc_trans_no;
    }

    /**
     * @param mixed $tcc_trans_no
     */
    public function setTccTransNo($tcc_trans_no)
    {
        $this->tcc_trans_no = $tcc_trans_no;
    }

    /**
     * @return mixed
     */
    public function getTaskType()
    {
        return $this->task_type;
    }

    /**
     * @param mixed $task_type
     */
    public function setTaskType($task_type)
    {
        $this->task_type = $task_type;
    }

    /**
     * @return mixed
     */
    public function getTaskStatus()
    {
        return $this->task_status;
    }

    /**
     * @param mixed $task_status
     */
    public function setTaskStatus($task_status)
    {
        $this->task_status = $task_status;
    }

}