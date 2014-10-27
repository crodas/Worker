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

use crodas\Worker\Engine\PDO\ConnectionManager;
use crodas\Worker\Engine\PDO\Task;
use crodas\Worker\Config;
use crodas\Worker\Job;
use PDO;

require __DIR__ . '/PDO/model/autoload.php';

class EPDO extends Engine
{
    protected $conn;
    protected $config;
    protected $handle;
    protected $args;
    protected $sql;
    protected $services = array();

    public function setConfig(Config $config)
    {
        $pdo = new PDO($config['pdo'], $config['user'] ?: '', $config['password'] ?: '');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $this->conn = new ConnectionManager($pdo);
        $this->config = $config;
        $this->handle = uniqid(true);
    }

    public function push(Job $job)
    {
        $task = new Task;
        $task->taskType = $job->function;
        $task->taskPayload = $job->serialize();
        $this->conn->save($task);
    }

    public function addServices(Array $services)
    {
        $this->services = array_merge($this->services, $services);
        $types = implode(",", array_fill(0, count($this->services), "?"));
        if (preg_match('/^mysql:/', $this->config['pdo'])) {
            $this->sql = $this->conn->prepare(NULL, "UPDATE tasks SET task_handle=?, task_status=1 
                WHERE task_handle='' AND task_type IN ($types) LIMIT 1");
        } else {
            $this->sql = $this->conn->prepare(NULL, "UPDATE tasks SET task_handle=?, task_status=1 
                WHERE task_id IN (
                    SELECT task_id 
                    FROM tasks 
                    WHERE 
                        task_handle='' AND task_type IN ($types)
                 LIMIT 1
                )");
        }


        $this->args = array_merge([$this->handle], $this->services);
    }

    public function listen()
    {
        if (empty($this->sql)) {
            throw new \RuntimeException("You need to add services first");
        }
        $tasks = $this->conn->tasks;
        do {
            $this->sql->execute($this->args);
            $task = $tasks->findOneByTaskHandleAndTaskStatus($this->handle, 1);
            if ($task) {
                $task->taskStatus = 2;
                $this->conn->save($task);
                break;
            }
            usleep(200000);
        } while (true);
        return Job::restore($this->config, $task->taskPayload);
    }

}

