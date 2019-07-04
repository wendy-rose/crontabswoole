<?php
/**
 * @link http://api.ibos.cn/
 * @copyright Copyright (c) 2018 IBOS Inc
 */

namespace App;

use swoole_table;


class Tasks
{

    const NORMAL = 0;//正常
    const STOP = 1;//暂停

    const RunStatusNormal = 0;//未运行
    const RunStatusStart = 1;//准备运行
    const RunStatusSuccess = 4;//运行成功
    const RunStatusFailed = 5;//运行失败

    //初始化定时任务内存结构
    static private $column = [
        "taskname" => [\swoole_table::TYPE_STRING, 256],
        "rule" => [\swoole_table::TYPE_STRING, 256],
        "timeout" => [\swoole_table::TYPE_STRING, 8],
        "status" => [\swoole_table::TYPE_STRING, 2],
        "execute" => [\swoole_table::TYPE_STRING, 512],
    ];

    //需要执行任务内存块结构
    static private $runTaskColumn = [
        "minute" => [\swoole_table::TYPE_STRING, 12],
        "sec" => [\swoole_table::TYPE_STRING, 12],
        "id" => [\swoole_table::TYPE_STRING, 20],
        "runid" => [\swoole_table::TYPE_STRING, 20],
        "runStatus" => [\swoole_table::TYPE_STRING, 2],
        "runTimeStart" => [\swoole_table::TYPE_STRING, 20],
    ];

    static private $taskMemory;

    static private $runTaskMemory;

    /**
     * 初始化
     */
    public static function init()
    {
        self::createTaskMemory();
        self::createRunTaskMemory();
    }

    /**
     * 初始化创建定时任务内存块
     */
    private static function createTaskMemory()
    {
        self::$taskMemory = new swoole_table(LOAD_SIZE * 2);
        foreach (self::$column as $key => $v){
            self::$taskMemory->column($key,$v[0], $v[1]);
        }
        self::$taskMemory->create();
    }

    /**
     * 初始化需要执行任务内存块
     */
    private static function createRunTaskMemory()
    {
        self::$runTaskMemory = new swoole_table(LOAD_SIZE * 2);
        foreach (self::$runTaskColumn as $key => $v){
            self::$runTaskMemory->column($key,$v[0], $v[1]);
        }
        self::$runTaskMemory->create();
    }

    /**
     * 载入定时任务到内存块
     */
    public static function loadTasks()
    {
        $sql = 'SELECT * FROM cron WHERE status = '. self::NORMAL;
        $tasks = PDODB::getInstance()->getAll($sql);
        if (!empty($tasks)){
            foreach ($tasks as $task){
                self::$taskMemory->set($task['id'], [
                    "taskname" => $task["taskname"],
                    "rule" => $task["rule"],
                    "timeout" => $task["timeout"],
                    "status" => $task["status"],
                    "execute" => $task["execute"],
                ]);
            }
        }
    }

    /**
     * 每一分钟执行一次，判断下一分钟需要执行的任务并且放到内存
     */
    public static function checkTasks()
    {
        $tasks = static::getTaskMemory();
        if (count($tasks) > 0){
            $time = time();
            foreach ($tasks as $id => $task){
                if ($task['status'] != self::NORMAL){
                    continue;
                }else{
                    $ret = ParseCrontab::parse($task['rule'], $time);
                    if (!empty($ret)){
                        $min = date('YmdHi');
                        $time = strtotime(date('Y-m-d H:i'));
                        foreach ($ret as $sec){
                            if (count(self::$runTaskMemory) < LOAD_SIZE){//防止内存溢出
                                $key = Common::generateRunId();
                                self::$runTaskMemory->set($key, [
                                    'minute' => $min,
                                    'sec' => $time + $sec,
                                    'id' => $id,
                                    'runid' => $key,
                                    'runStatus' => self::RunStatusNormal
                                ]);
                            }
                        }
                    }
                }
            }
        }
    }

    /**
     * 获取当前可执行的任务
     * @return array
     */
    public static function getRunTasks()
    {
        $data = [];
        if (count(self::$runTaskMemory) <= 0){
            return [];
        }
        $min = date('YmdHi');
        foreach (self::$runTaskMemory as $k => $task){
            if (self::$taskMemory->exist($task['id'])){
                if ($min == $task['minute']){
                    if (time() >= $task['sec'] &&  $task['runStatus'] == self::RunStatusNormal){
                        $value = self::$taskMemory->get($task['id']);
                        $data[$k] = array_merge($value, ['runid' => $k, 'id' => $task['id']]);
                        self::$runTaskMemory->set($k, ['runStatus' => self::RunStatusStart, 'runTimeStart' => time()]);
                    }
                }
            }
        }
        return $data;
    }

    /**
     * 获取定时任务内存块
     * @return mixed
     */
    public static function getTaskMemory()
    {
        return self::$taskMemory;
    }

    /**
     * 获取需要执行任务的内存块
     * @return mixed
     */
    public static function getRunTaskMemory()
    {
        return self::$runTaskMemory;
    }
}