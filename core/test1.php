<?php
require_once __DIR__. '/init.php';

use App\PDODB;

$sql = "update test set num=num+1 WHERE `id`=1";
$pdo = PDODB::getInstance()->query($sql);