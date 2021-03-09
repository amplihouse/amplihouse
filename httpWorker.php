<?php

use Workerman\Connection\AsyncTcpConnection;

$schema = json_decode(file_get_contents('config/schema.lock.json'), true);

$chConnection = new AsyncTcpConnection("tcp://{$config['host']}:{$config['localPort']}");
$chConnection->connect();

$httpWorker->onMessage = function($connection, $request) use ($chConnection, $schema)
{
    if (($post = $request->post()) && !empty($post['e']) && ($events = json_decode($post['e'], true)) && is_array($events)) {
        $rows = '';

        foreach ($events as $event) {
            $event['country'] = $_SERVER['GEOIP_COUNTRY_NAME'] ?? '';
            $event['country_code'] = $_SERVER['GEOIP_COUNTRY_CODE'] ?? '';
            $event['region'] = $_SERVER['GEOIP_REGION_NAME'] ?? '';
            $event['city'] = $_SERVER['GEOIP_CITY_NAME'] ?? '';

            if (!empty($event['event_properties']) && is_array($event['event_properties'])) {
                foreach ($event['event_properties'] as $name => $value) {
                    $newName = '_' . preg_replace('/[^a-zA-Z0-9]+/', '_', $name);
                    if (!isset($event[$newName])) {
                        $event[$newName] = $value;
                    }
                }
            }

            if (!empty($event['user_properties']) && is_array($event['user_properties'])) {
                foreach ($event['user_properties'] as $command => $user_properties) {
                    if ($command == '$set') {
                        foreach ($user_properties as $name => $value) {
                            $newName = preg_replace('/[^a-zA-Z0-9]+/', '_', $name);
                            if (!isset($event[$newName])) {
                                $event[$newName] = $value;
                            }
                        }
                    }
                }
            }

            $row = [];
            foreach ($event as $metric => &$value) {
                if ($metricParams = $schema['raw']['columns'][$metric]??null) {
                    if (!empty($metricParams['jsonStringify'])) {
                        $value = json_encode($value);
                    }

                    if (strpos($metricParams['type'], 'Int') !== false) {
                        $value = intval($value);
                    } else if (strpos($metricParams['type'], 'Float') !== false) {
                        $value = floatval($value);
                    } else if (strpos($metricParams['type'], 'DateTime') !== false) {
                        if ($metricParams['sourceFormat'] && $metricParams['sourceFormat'] === 'timestamp_ms') {
                            $value = date('Y-m-d H:i:s', intval($value/1000));
                        } else {
                            $value = date('Y-m-d H:i:s', strtotime($value));
                        }
                    } else {
                        $value = strval($value);
                    }

                    if (!empty($metricParams['replace']) && !empty($metricParams['replace']['regexp'])) {
                        $value = preg_replace("/{$metricParams['replace']['regexp']}/", $metricParams['replace']['newSubstring'], $value);
                    }

                    if (!empty($metricParams['newName'])) {
                        $newName = $metricParams['newName'];
                        $row[$newName] = $value;
                        unset($row[$metric]);
                    } else {
                        $row[$metric] = $value;
                    }

                    if (!empty($metricParams['ignoreList']) && in_array($value, $metricParams['ignoreList'])) {
                        continue 2;
                    }
                }
            }

            $rows .= json_encode($row).PHP_EOL;
        }

        $chConnection->send($rows);
    }

    return $connection->close('');
};
