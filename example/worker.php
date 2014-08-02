<?php

/**
 *  @Worker("send:email", { timeout : 120 })
 *  @Worker("send:email:now")
 */
function worker($args, $job)
{
    sleep(rand(1, 5));
    return strrev($args[0]);
}
