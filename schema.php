<?php

Class Clickhouse
{
    private $config;

    function __construct() {
        $this->config = json_decode(file_get_contents('config/clickhouse.json'), true);
    }

    function query($query)
    {
        $r = @file_get_contents("{$this->config['url']}&query=", null, stream_context_create(['http' => ['method' => 'POST', 'content' => $query, 'timeout' => 5, 'ignore_errors' => true,]]));
        //echo "$query\n$r\n";

        return $r;
    }

    function execute($query)
    {
        return !$this->query($query);
    }

    function insert($row)
    {
        $row = json_encode($row);
        //echo $row . "\n";
        return $this->query("INSERT INTO amplihouse.raw FORMAT JSONEachRow\n$row");
    }
}

Class Schema
{
    private $clickhouse;
    private $schema;
    public $lock;

    function __construct() {
        $this->clickhouse = new Clickhouse();
        $this->schema = json_decode(file_get_contents('config/schema.json'), true);
        $this->lock = @json_decode(file_get_contents('config/schema.lock.json'), true);
    }

    function createTable($table)
    {
        $tableConfig = $this->schema[$table];
        $columns = [];
        $queries = [];

        foreach ($tableConfig['columns'] as $column => $columnParams) {
            if ($tableConfig['type'] === 'table') {
                $columns[] = ($columnParams['newName'] ?? $column) . ' ' . $columnParams['type'];
            } else if ($tableConfig['type'] === 'matview') {
                $queryString = $column;
                if (!empty($columnParams['query'])) {
                    $queryString = "{$columnParams['query']} as $column";
                }
                $queries[] = $queryString;
            } else if ($tableConfig['type'] === 'view') {
                $queryString = $column;
                if (!empty($columnParams['query'])) {
                    $queryString = "{$columnParams['query']} as $column";
                }
                $queries[] = $queryString;
            }
        }

        return $this->clickhouse->execute(str_replace(['%columns', '%queries'], ["\n  " . join(",\n  ", $columns) . "\n", "\n  " . join(",\n  ", $queries) . "\n"], $tableConfig['template']));
    }

    function dropTable($table)
    {
        return $this->clickhouse->execute("DROP TABLE amplihouse.$table;");
    }

    function alterTableColumn($table, $column, $action, $type = '')
    {
        return $this->clickhouse->execute("ALTER TABLE amplihouse.$table $action COLUMN $column $type;");
    }
    
    function update() {
        if (!$this->lock) {
            //$query = "DROP DATABASE amplihouse;";
            //$this->clickhouse->execute($query);

            $query = "CREATE DATABASE amplihouse;";
            $this->clickhouse->execute($query);

            foreach ($this->schema as $i => $v) {
                $this->createTable($i);
            }

            $this->schemaLock = $this->schema;
        } elseif ($this->schema != $this->lock) {
            foreach ($this->schema as $table => $tableConfig) {
                if (!isset($this->lock[$table])) {
                    $this->createTable($table);
                } elseif ($this->schema[$table] == $this->lock[$table]) {
                    continue;
                } elseif ($this->schema[$table]['template'] != $this->lock[$table]['template'] || $this->schema[$table]['type'] != $this->lock[$table]['type']) {
                    $this->dropTable($table);
                    $this->createTable($table);
                } elseif($tableConfig['type'] == 'matview') {
                    $this->dropTable($table);
                    $this->createTable($table);
                } elseif($tableConfig['type'] == 'matview') {
                    $this->createTable($table);
                } elseif($this->schema[$table]['columns'] != $this->lock[$table]['columns']) {
                    foreach ($tableConfig['columns'] as $column => $columnParams) {
                        if (!isset($this->lock[$table]['columns'][$column])) {
                            $this->alterTableColumn($table, $column, 'ADD', $columnParams['type']);
                        } elseif ($this->schema[$table]['columns'][$column] != $this->lock[$table]['columns'][$column]) {
                            if (!$this->alterTableColumn($table, $column, 'MODIFY', $columnParams['type'])) {
                                $this->alterTableColumn($table, $column, 'DROP');
                                $this->alterTableColumn($table, $column, 'ADD', $columnParams['type']);
                            }
                        }
                    }

                    foreach ($this->lock[$table]['columns'] as $column => $columnParams) {
                        if (!isset($this->schema[$table]['columns'][$column])) {
                            $this->alterTableColumn($table, $column, 'DROP');
                        }
                    }
                }

                $this->lock[$table] = $this->schema[$table];
            }

            foreach ($this->lock as $table => $tableConfig) {
                if (!isset($this->schema[$table])) {
                    $this->dropTable($table);
                    unset($this->lock[$table]);
                }
            }
        }
        
        file_put_contents('config/schema.lock.json', json_encode($this->lock,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));
    }
}

$schema = new Schema();
$schema->update();
