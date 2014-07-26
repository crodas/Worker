<?php

namespace crodas\Worker\Service;

use crodas\Worker;

/**
 *  @Service(worker-config, {
 *      engine: { default: 'gearman'},
 *      path: { require: true, type: array_dir},
 *      host:  { default: 'localhost'},
 *      port:  { default: 0}
 *  })
 */
function worker_config(Array $config)
{
    return $config;
}

/**
 *  @Service(worker, {
 *  }, { shared: true})
 */
function worker_service(Array $config, $context, $service)
{
    $config = $service('worker-config');

    $Config = new Worker\Config;
    $Config->setEngine($config['engine']);
    $Config['host'] = $config['host'];
    $Config['port'] = $config['port'];

    return new Worker\Client($Config);
}

/**
 *  @Service(worker-server, {
 *  }, { shared: true})
 */
function worker_daemon(Array $config, $context, $service)
{
    $config = $service('worker-config');

    $Config = new Worker\Config;
    $Config->setEngine($config['engine']);
    foreach ($config['path'] as $dir) {
        $Config->addDirectory($dir);
    }

    $Config['host'] = $config['host'];
    $Config['port'] = $config['port'];

    return new Worker\Server($Config);
}
