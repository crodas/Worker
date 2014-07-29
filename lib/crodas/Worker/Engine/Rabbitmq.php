<?php
/*
  +---------------------------------------------------------------------------------+
  | Copyright (c) 2014 César Rodas                                                  |
  +---------------------------------------------------------------------------------+
  | Redistribution and use in source and binary forms, with or without              |
  | modification, are permitted provided that the following conditions are met:     |
  | 1. Redistributions of source code must retain the above copyright               |
  |    notice, this list of conditions and the following disclaimer.                |
  |                                                                                 |
  | 2. Redistributions in binary form must reproduce the above copyright            |
  |    notice, this list of conditions and the following disclaimer in the          |
  |    documentation and/or other materials provided with the distribution.         |
  |                                                                                 |
  | 3. All advertising materials mentioning features or use of this software        |
  |    must display the following acknowledgement:                                  |
  |    This product includes software developed by César D. Rodas.                  |
  |                                                                                 |
  | 4. Neither the name of the César D. Rodas nor the                               |
  |    names of its contributors may be used to endorse or promote products         |
  |    derived from this software without specific prior written permission.        |
  |                                                                                 |
  | THIS SOFTWARE IS PROVIDED BY CÉSAR D. RODAS ''AS IS'' AND ANY                   |
  | EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED       |
  | WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE          |
  | DISCLAIMED. IN NO EVENT SHALL CÉSAR D. RODAS BE LIABLE FOR ANY                  |
  | DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES      |
  | (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;    |
  | LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND     |
  | ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT      |
  | (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS   |
  | SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE                     |
  +---------------------------------------------------------------------------------+
  | Authors: César Rodas <crodas@php.net>                                           |
  +---------------------------------------------------------------------------------+
*/
namespace crodas\Worker\Engine;

use PhpAmqpLib\Connection\AMQPConnection;
use PhpAmqpLib\Message\AMQPMessage;
use crodas\Worker\Config;
use crodas\Worker\Task;

class RabbitMQ extends Engine
{
    protected $conn;
    protected $channel;
    protected $msg;
    protected $config;

    public function setConfig(Config $config)
    {
        $this->conn = new AMQPConnection(
            $config['host'] ?: "localhost",
            $config['port'] ?: 5672,
            $config['user'] ?: "guest",
            $config['password'] ?: "guest"
        );

        $this->channel = $this->conn->channel();
        $this->config  = $config;
    }

    public function __destruct()
    {
        $this->channel->close();
        $this->conn->close();
    }

    public function push(Task $task)
    {
        $msg = new AMQPMessage(
            $task->serialize(),
            array('delivery_mode' => 2) # make message persistent
        );

        $this->channel->basic_publish($msg, '', $task->function);
    }

    public function callback($msg)
    {
        $this->msg = $msg;
        $msg->delivery_info['channel']->basic_ack($msg->delivery_info['delivery_tag']);
    }

    public function addServices(Array $services)
    {
        foreach ($services as $service) {
            $this->channel->queue_declare($service, false, true, false, false);
            $this->channel->basic_consume($service, '', false, false, false, false, [$this, 'callback']);
        }
    }

    public function listen()
    {
        $this->channel->basic_qos(null, 1, null);
        $this->channel->wait();

        return Task::restore($this->config, $this->msg->body);
    }
}
