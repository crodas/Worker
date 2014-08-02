<?php

require __DIR__ . "/../vendor/autoload.php";
$config = new crodas\Worker\Config;
$config
    ->setEngine('rabbitmq');
$config
    ->setEngine('gearman');
