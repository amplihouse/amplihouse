<?php

use Workerman\Worker;

require_once __DIR__ . '/vendor/autoload.php';

include "schema.php";

$config = json_decode(file_get_contents('config/server.json'), true);

$chWorker = new Worker("tcp://{$config['host']}:{$config['localPort']}");
$chWorker->user = 'www-data';
$chWorker->name = 'clickhouse';

$chWorker->onWorkerStart = function () use ($chWorker) {
    include "chWorker.php";
};

$httpWorker = new Worker("http://{$config['host']}:{$config['port']}");
$httpWorker->user = 'www-data';
$httpWorker->name = 'http';
$httpWorker->count = $config['forks'];

$httpWorker->onWorkerStart = function () use ($httpWorker, $config) {
    include "httpWorker.php";
};

$cronWorker = new Worker();
$cronWorker->user = 'www-data';
$cronWorker->name = 'cron';

$cronWorker->onWorkerStart = function () use ($cronWorker, $config) {
    include "cronWorker.php";
};

Worker::runAll();
