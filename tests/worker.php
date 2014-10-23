<?php
require realpath(__DIR__ . '/../vendor/autoload.php');
$config = new crodas\Worker\Config(['pdo' => 'sqlite:' . sys_get_temp_dir() . '/worker.db']);
$config->setEngine('EPDO');
$config->AddDirectory(__DIR__);

$s = new crodas\Worker\Server($config);
$s->serve();
    
