<?php

/**
 *  @Worker("task:simple")
 */
function task_foobar(Array $args, $job, $server)
{
    sleep(5);
    return strrev($args[0]);
}
