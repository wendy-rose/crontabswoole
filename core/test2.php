<?php
require_once __DIR__. '/init.php';

use App\PDODB;

sleep(10);
$sql = "update test set num=num+1 WHERE `id`=2";
$pdo = PDODB::getInstance()->query($sql);