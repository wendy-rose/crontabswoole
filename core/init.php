<?php
define('ROOT_PATH', __DIR__);
define('CENTER_IP', '0.0.0.0');
define('CENTER_PORT', 9503);
define('MYSQL_HOST', 'mysql');
define('MYSQL_PORT', 3306);
define('MYSQL_DB_NAME', 'crontab');
define('MYSQL_DB_USER', 'root');
define('MYSQL_DB_PWD', 'root');
define('LOAD_SIZE', 1024);
define('WORKER_NUM', 4);
define('TASK_WORKER_NUM', 4);

require_once ROOT_PATH . '/vendor/autoload.php';
//重定向PHP错误日志到logs目录
ini_set('error_log', ROOT_PATH.  '/logs/php_errors.log');
//时区
date_default_timezone_set("PRC");
