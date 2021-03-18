<?php

use Workerman\Lib\Timer;

$config = json_decode(file_get_contents('config/clickhouse.json'), true);

Timer::add($config['timer'], function() use ($config) {
    foreach (scandir($config['unsentRowsDir']) as $file) {
        if (!in_array($file, ['.', '..', date('Y-m-d_H:i:s')])) {
            $filename = "{$config['unsentRowsDir']}/$file";
            $e = `cat "$filename" | curl  -H 'Content-Encoding: gzip' --data-binary @- '{$config['url']}&query=INSERT+INTO+{$config['table']}+FORMAT+JSONEachRow'`;
            if (!$e) {
                `rm -f "$filename"`;
            } else {
                break;
            }
        }
    }
});
