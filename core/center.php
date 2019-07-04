<?php
require_once __DIR__. '/init.php';
use App\CenterServer;
use App\Tasks;
use App\Process;

$config = [
    'daemonize' => 0,
    'worker_num' => WORKER_NUM,
    'task_worker_num' => TASK_WORKER_NUM,
    'max_request' => 0,
    'dispatch_mode' => 3,
    'log_file' => ROOT_PATH . '/logs/center.log',
    'open_eof_split' => true,
    'package_eof' => "\r\n",
];

//初始化任务内存块
Tasks::init();
//载入定时任务到内存块
Tasks::loadTasks();
//初始化进程内存块
Process::init();
new CenterServer(CENTER_IP, CENTER_PORT, $config);