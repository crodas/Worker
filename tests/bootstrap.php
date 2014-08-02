<?php

require __DIR__ . "/../vendor/autoload.php";

use Symfony\Component\Process\PhpProcess;

function initServer()
{
    $code = "<?php
    require realpath('" . __DIR__ . "/../vendor/autoload.php');
    \$config = new crodas\Worker\Config;
    \$config->setEngine('gearman');
    \$config->AddDirectory('" . __DIR__ . "');

    \$s = new crodas\Worker\Server(\$config);
    \$s->serve();
    ";

    $process = new PhpProcess($code);
    $process->start();
    sleep(5);

    return $process;
}
