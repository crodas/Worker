<?php

require __DIR__ . "/config.php";

$client = new crodas\Worker\Client($config);
$result = array();
for($i=0; $i < 5; $i++) {
    $result[$i] = $client->pushSync('send:email', ['cesar:' . $i]);
}

$client->wait();

var_dump($result[0]->getResult());

