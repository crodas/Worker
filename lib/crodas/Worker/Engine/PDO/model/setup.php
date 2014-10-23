<?php

use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\Type;
use Doctrine\DBAL\Configuration;
use Doctrine\DBAL\DriverManager;



function createTableTasks($schema)
{
    
    try {
        $table = $schema->createTable('tasks');
    } catch (\Exception $e) {
        $table = $schema->getTable('tasks');
    }
    $columns = [0=>'task_id',1=>'task_type',2=>'task_payload',3=>'task_status',4=>'task_handle'];
    foreach (array_keys($table->Getcolumns()) as $column) {
        if (!in_array($column, $columns)) {
            $table->dropColumn($column);
        }
    }

    try {
        $table->addColumn('task_id', 'integer', ['notnull'=>false,'autoincrement'=>true,'default'=>NULL]);
    } catch (\Exception $e) {
        $column = $table->GetColumn('task_id');
        $column->setType(Type::GetType('integer'));
    }
                
    try {
        $table->addColumn('task_type', 'string', ['notnull'=>false,'length'=>40,'default'=>NULL]);
    } catch (\Exception $e) {
        $column = $table->GetColumn('task_type');
        $column->setType(Type::GetType('string'));
    }
    try {
        $table->addColumn('task_payload', 'text', ['notnull'=>false,'default'=>NULL]);
    } catch (\Exception $e) {
        $column = $table->GetColumn('task_payload');
        $column->setType(Type::GetType('text'));
    }
    try {
        $table->addColumn('task_status', 'integer', ['notnull'=>false,'default'=>1]);
    } catch (\Exception $e) {
        $column = $table->GetColumn('task_status');
        $column->setType(Type::GetType('integer'));
    }
    try {
        $table->addColumn('task_handle', 'string', ['notnull'=>false,'length'=>20,'default'=>'']);
    } catch (\Exception $e) {
        $column = $table->GetColumn('task_handle');
        $column->setType(Type::GetType('string'));
    }
    $table->setPrimaryKey([0=>'task_id']);
    $table->addIndex([0=>'task_handle',1=>'task_status']);
}

$config = new Configuration();
$connectionParams = array(
    'pdo' => $pdo,
);

$conn = DriverManager::getConnection($connectionParams, $config);

$sm = $conn->getSchemaManager();
$schema = $sm->createSchema();

$tables = array (
    'task' => 'tasks',
);
foreach ($schema->GetTables() as $table) {
    if (!in_array($table->GetName(), $tables)) {
        $schema->dropTable($table->getName());
    }
}


createTableTasks($schema);

$sqls = $sm->createSchema()->getMigrateToSql($schema, $conn->getDatabasePlatform());
$conn->beginTransaction();
foreach ($sqls as $sql) {
    $conn->executequery($sql);
}
$conn->commit();
