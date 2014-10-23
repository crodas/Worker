<?php

require __DIR__ . "/../vendor/autoload.php";

use Symfony\Component\Process\PhpProcess;

define('XPDO', "sqlite:" . sys_get_temp_dir() . '/worker.db');
@unlink(sys_get_temp_dir() . "/worker.db");

$dbh = new PDO(XPDO);
$dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$dbh->exec(file_get_contents(__DIR__ . '/../lib/crodas/Worker/Engine/PDO/struct.sqlite'));


function initServer()
{
    $process = new PhpProcess(file_get_contents(__DIR__ . '/worker.php'), __DIR__);
    $process->start();
    sleep(5);

    return $process;
}
