<?php

use Workerman\Lib\Timer;

$config = json_decode(file_get_contents('config/clickhouse.json'), true);

Timer::add($config['timer'], function() use ($config) {
    $minutes = intval(1 + $config['timer'] / 60);
    `find {$config['unsentRowsDir']} -type f -mmin +$minutes | while read i; do cat "\$i" | curl  -H 'Content-Encoding: gzip' --data-binary @- '{$config['url']}&query=INSERT+INTO+{$config['table']}+FORMAT+JSONEachRow' && rm -f "\$i"; done`;
});
