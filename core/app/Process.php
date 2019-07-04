<?php
/**
 * @link http://api.ibos.cn/
 * @copyright Copyright (c) 2018 IBOS Inc
 */

namespace App;
use swoole_table;
use swoole_process;


class Process
{

    const PROCESS_START = 0;//程序开始运行:当code=0 && signal=0 && status=0
    const PROCESS_STOP = 1;//程序正常结束运行:当code=0 && signal=0
    const PROCESS_ERROR = 2;//程序运行出错:当code!=0时出现(-1等)
    const PROCESS_TIMEOUT = 3;//程序运行超时

    static private $table;
    static private $process_stdout = [];
    static public $server;
    static private $column = [
        "taskid" => [\swoole_table::TYPE_INT, 8],
        "runid" => [\swoole_table::TYPE_STRING, 8],
        "status" => [\swoole_table::TYPE_INT, 1],
        "start" => [\swoole_table::TYPE_FLOAT, 8],
        "end" => [\swoole_table::TYPE_FLOAT, 8],
        "code"=> [\swoole_table::TYPE_INT, 1],
        "signal" => [\swoole_table::TYPE_INT, 4],
        "pipe"=> [\swoole_table::TYPE_INT, 8],
        "pid" => [\swoole_table::TYPE_INT, 8],
    ];

    /**
     * 任务运行进程内存块
     */
    public static function init()
    {
        self::$table = new swoole_table(LOAD_SIZE);
        foreach (self::$column as $key => $value){
            self::$table->column($key, $value[0], $value[1]);
        }
        self::$table->create();
    }

    /**
     * 信号
     */
    public static function signal()
    {
        swoole_process::signal(SIGCHLD, function ($sig){
            //阻塞模式，监听子进程，进行回收，否则会出现僵尸进程
            while ($ret = swoole_process::wait(false)){
                $pid = $ret['pid'];
                if (self::$table->exist($pid)){
                    $process = self::$table->get($pid);
                    $process['code'] = $ret['code'];
                    $process['signal'] = $ret['signal'];
                    if ($ret['code'] == 0){
                        $process['status'] = self::PROCESS_STOP;
                    }else{
                        $process['status'] = self::PROCESS_ERROR;
                    }
                    $process['end'] = time();
                    self::$table->set($pid, $process);
                    swoole_event_del($process['pipe']);
                    $runMemory = Tasks::getRunTaskMemory();
                    if (!empty($runMemory) && $runMemory->exist($process['runid'])){
                        $runMemory->del($process['runid']);
                    }
                    self::$table->del($pid);
                    unset(self::$process_stdout[$pid]);
                    $data = array_merge($process, ['type' => 'signal']);
                    self::$server->task(json_encode($data, JSON_UNESCAPED_UNICODE). "\r\n");
                }
            }
        });
    }

    /**
     * 创建子进程执行任务
     * @param $task
     */
    public static function createProcessByTask($task)
    {
        $process = new swoole_process(function (swoole_process $worker) use($task){
            $exec = explode(" ", $task['execute']);
            $execFile = array_shift($exec);
            $worker->exec($execFile, $exec);
        }, true, true);
        $pid = $process->start();
        if ($pid){
            //监听
            swoole_event_add($process->pipe, function ($pipe) use ($process, $pid){
                if (!isset(self::$process_stdout[$pid])) self::$process_stdout[$pid] = '';
                self::$process_stdout[$pid] .= $process->read();
            });
            $data = [
                'taskid' => $task['id'],
                'runid' => $task['runid'],
                'status' => self::PROCESS_START,
                'start' => time(),
                'pipe' => $process->pipe,
                'pid' => $pid,
            ];
            self::$table->set($pid, $data);
            $data = array_merge(['type' => 'insertprocess'], $data);
            self::$server->task(json_encode($data, JSON_UNESCAPED_UNICODE). "\r\n");
        }
    }

    /**
     * 所有进程
     * @return array
     */
    public static function getAllProcess()
    {
        $processes = [];
        if (count(self::$table) > 0){
            foreach (self::$table as $pid => $process){
                $processes[$pid] = [
                    'pid' => $pid,
                    'taskid' => $process['taskid'],
                    'runid' => $process['runid'],
                    'start' => $process['start'],
                    'end' => $process['end'],
                    'code' => $process['code'],
                    'signal' => $process['signal'],
                    'status' => $process['status']
                ];
            }
        }
        return $processes;
    }

    /**
     * 检查进程超时
     */
    public static function checkProcessTimeOut()
    {
        $process = self::$table;
        if (!empty($process)){
            foreach ($process as $pid => $value){
                $takses = Tasks::getTaskMemory();
                if (!empty($takses) && $takses->exist($value['taskid'])){
                    $task = $takses->get($value['taskid']);
                    $timeout = empty($task['timeout']) ? 30 : $task['timeout'];
                    if ($value['start'] + $timeout > time()){//超时
                        swoole_process::kill($pid, SIGTERM);
                        $process->del($pid);
                        unset(self::$process_stdout[$pid]);
                        $takses->del($value['runid']);
                        $data = json_encode(['type' => 'timeout', 'runid' => $value['runid']], JSON_UNESCAPED_UNICODE);
                        self::$server->task($data . "\r\n");
                    }
                }
            }
        }
    }
}