# crontabswoole
基于swoole的定时任务，精确到秒级

①必须安装swoole扩展，且扩展版本大于4.0

②执行crontab.sql文件，在cron添加想要执行的定时任务，格式如下：

     0     1    2    3    4    5
     *     *    *    *    *    *
     -     -    -    -    -    -
     |     |    |    |    |    |
     |     |    |    |    |    +----- day of week (0 - 6) (Sunday=0)
     |     |    |    |    +----- month (1 - 12)
     |     |    |    +------- day of month (1 - 31)
     |     |    +--------- hour (0 - 23)
     |     +----------- min (0 - 59)
      +------------- sec (0-59)
      
③运行center.php即可


