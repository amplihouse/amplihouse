<?php

use Workerman\Lib\Timer;

$config = json_decode(file_get_contents('config/clickhouse.json'), true);

$rows = '';

$chWorker->onMessage = function($connection, $data) use (&$rows)
{
    $rows .= $data;
};

$chWorker->onWorkerStop = $chWorker->onWorkerReload = function() use (&$rows, $config)
{
    if ($rows) {
        file_put_contents($config['unsentRowsDir'] . '/' . date('Y-m-d_H:i:s') . '.gz', gzencode($rows) , FILE_APPEND | LOCK_EX);
        $rows = '';
    }
};

Timer::add($config['timer'], $chWorker->onWorkerStop);
