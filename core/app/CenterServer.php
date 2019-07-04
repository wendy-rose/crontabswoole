<?php
/**
 * @link http://api.ibos.cn/
 * @copyright Copyright (c) 2018 IBOS Inc
 */

namespace App;

use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use swoole_server;
use swoole_process;
use swoole_client;


class CenterServer
{
    private $config;
    private $serv;
    private $monolog;
    private $pdo;
    private $host;
    private $port;

    const LOAD_TASKS = 0;//载入任务tasks进程
    const GET_TASKS = 1;//获取到期task进程

    /**
     * 构造方法
     * CenterServer constructor.
     * @param $host
     * @param $port
     * @param $config
     */
    public function __construct($host, $port, $config)
    {
        $this->monolog = new Logger('center');
        $this->monolog->pushHandler(new StreamHandler(ROOT_PATH . '/logs/center.log', Logger::DEBUG));
        $this->host = $host;
        $this->port = $port;
        $this->config = $config;
        $this->serv = new swoole_server($host, $port);
        $this->serv->set($this->config);

        $this->serv->on('ManagerStart', [$this, 'onManagerStart']);
        $this->serv->on('WorkerStart', [$this, 'onWorkerStart']);
        $this->serv->on('WorkerError', [$this, 'onWorkerError']);
        $this->serv->on('WorkerStop', [$this, 'onWorkerStop']);
        $this->serv->on('ManagerStop', [$this, 'onManagerStop']);
        $this->serv->on('Receive', [$this, 'onReceive']);
        $this->serv->on('PipeMessage', [$this, 'onPipeMessage']);
        $this->serv->on('Task', [$this, 'onTask']);
        $this->serv->on('Finish', [$this, 'onFinish']);

        Process::$server = $this->serv;

        $this->serv->start();
    }

    /**
     * 当管理进程启动时调用它,manager进程中不能添加定时器,manager进程中可以调用task功能
     * @param $server
     */
    public function onManagerStart(swoole_server $server)
    {

    }

    /**
     * 此事件在Worker进程/Task进程启动时发生
     * @param swoole_server $server
     * @param $worker_id
     */
    public function onWorkerStart(swoole_server $server, $worker_id)
    {
        $this->pdo = PDODB::getInstance();
        if (!$server->taskworker){
            Process::signal();
        }
        if ($server->taskworker){
            if ($worker_id == WORKER_NUM + self::LOAD_TASKS){//准点载入任务
                $server->after((60 - date('s')) * 1000, function () use ($server){
                    Tasks::checkTasks();
                    $server->tick(60000, function () use ($server) {
                        Tasks::checkTasks();
                    });
                });
            }elseif ($worker_id == WORKER_NUM + self::GET_TASKS){//检查需要执行的任务
                $client = new swoole_client(SWOOLE_SOCK_TCP);
                if ($client->connect(CENTER_IP, CENTER_PORT)){
                    $server->tick(500, function () use ($server, $client){
                        $tasks = Tasks::getRunTasks();
                        if (!empty($tasks)){
                            foreach ($tasks as $task){
                                $client->send(json_encode($task) . "\r\n");
                            }
                        }
                    });
                }
            }
        }
        //每10秒检测子进程执行情况，合适的时候直接杀子进程，比如脚本执行超时，避免大量僵尸进程出现
        $server->tick(10000, function (){
            Process::checkProcessTimeOut();
        });
    }

    /**
     * 当worker/task_worker进程发生异常后会在Manager进程内回调此函数
     * @param $server
     * @param $worker_id 异常进程的编号
     * @param $worker_pid 异常进程的ID
     * @param $exit_code 退出的状态码，范围是 1 ～255
     */
    public function onWorkerError(swoole_server $server, $worker_id, $worker_pid, $exit_code)
    {
        $this->monolog->error("worker/task_worker进程发生异常: worker_id:" . $worker_id . " worker_pid,: " . $worker_pid . " exit_code: " .$exit_code);
    }

    /**
     * 此事件在worker进程终止时发生。在此函数中可以回收worker进程申请的各类资源
     * @param $server
     * @param $worker_id 是一个从0-$worker_num之间的数字，表示这个worker进程的ID,$worker_id和进程PID没有任何关系
     */
    public function onWorkerStop(swoole_server $server, $worker_id)
    {
        $this->monolog->info("WorkerStop;worker进程终止: worker_id:" . $worker_id);
    }

    /**
     * 当管理进程结束时调用它
     * @param $server
     */
    public function onManagerStop(swoole_server $server)
    {
        //管理进程退出前需要删除所有在运行的任务，否则会造成僵尸进程，从process内存表读取
        $process = Process::getAllProcess();
        if (!empty($process)){
            foreach ($process as $key => $value){
                swoole_process::kill($key, SIGTERM);
            }
            unset($key, $value, $process);
            $this->monolog->info("ManagerStop;管理进程结束");
        }
    }

    /**
     * 接收到数据时回调此函数，发生在worker进程中
     * @param swoole_server $server
     * @param int $fd
     * @param int $reactor_id
     * @param string $data
     */
    public function onReceive(swoole_server $server, int $fd, int $reactor_id, string $data)
    {
        $data = json_decode(trim($data), true);
        Process::createProcessByTask($data);
    }

    /**
     * 当工作进程收到由 sendMessage 发送的管道消息时会触发onPipeMessage事件,task和worker可能会触发
     * @param swoole_server $server
     * @param int $src_worker_id
     * @param mixed $message
     */
    public function onPipeMessage(swoole_server $server, int $src_worker_id, $message)
    {

    }

    /**
     * 在task_worker进程内被调用
     * @param swoole_server $serv
     * @param int $task_id
     * @param int $src_worker_id
     * @param mixed $data
     */
    public function onTask(swoole_server $serv, int $task_id, int $src_worker_id, $data)
    {
        $data = json_decode($data, true);
        if (!empty($data)){
            switch ($data['type']){
                case 'timeout':
                    $sqls = ["UPDATE `process` SET `status`=". Process::PROCESS_TIMEOUT. " WHERE `runid`='{$data['runid']}'"];
                    break;
                case 'insertprocess':
                    $sqls = ["INSERT INTO `process` (`taskid`, `runid`, `status`, `start`, `pipe`, `pid`) VALUES ({$data['taskid']}, '{$data['runid']}', {$data['status']}, {$data['start']}, {$data['pipe']}, {$data['pid']})"];
                    break;
                case 'signal':
                    $sqls = [
                        "UPDATE `process` SET `code`={$data['code']},`signal`={$data['signal']},`status`={$data['status']},`end`={$data['end']} WHERE `runid`='{$data['runid']}'",
                        "UPDATE `cron` SET `runtime`= {$data['end']},`num`=`num`+1 WHERE `id`={$data['taskid']}"
                    ];
                    break;
                default:
                    $sqls = [];
                    break;
            }
            if (!empty($sqls)){
                foreach ($sqls as $sql){
                    $this->pdo->query($sql);
                }
            }
        }
    }

    /**
     * 当worker进程投递的任务在task_worker中完成时，task进程会通过swoole_server->finish()方法将任务处理的结果发送给worker进程
     * @param swoole_server $serv
     * @param int $task_id
     * @param string $data
     */
    public function onFinish(swoole_server $serv, int $task_id, string $data)
    {

    }
}