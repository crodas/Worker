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

namespace crodas\Worker;

use Notoj;
use Symfony\Component\Process\PhpProcess;

class Server
{
    protected $config;
    protected $client;
    protected $services = array();

    public function __construct(Config $config)
    {
        $this->config = $config;
        $this->client = new Client($config);
    }

    protected function createWorker($id)
    {
        $files = get_included_files();
        array_shift($files);

        $boostrap = "<?php 
        foreach (" . var_export($files, true) . " as \$file) {
            require_once \$file;
        }

        define('__WORKER__', $id);

        \$config = crodas\Worker\Config::import(" . $this->config->export() .  ");

        \$server = new crodas\Worker\Server(\$config);
        \$server->worker();
        ";

        echo "master> Starting process $id\n";
        $process = new PhpProcess($boostrap);
        $process->start();
        $process->id = $id;
        $process->time = time();
        $process->status = empty($args) ? 'idle' : 'busy';

        return $process;
    }

    protected function processReport($process, $stdout)
    {
        foreach (explode("\n", $stdout) as $line) {
            if (empty($line)) continue;
            if ($line[0] == "\0") {
                $parts = explode("\0", $line);
                if ($parts[2] != strlen($parts[3])) {
                    die("Invalid response");
                }

                switch ($parts[1]) {
                case 'start':
                    $process->status = 'busy';
                    $process->task   = json_decode($parts[3], true)['args'];
                    $process->time   = time();
                    break;
                case 'end':
                    $process->status = 'idle';
                    $process->time   = time();
                    break;
                }

            } else {
                echo "Process::{$process->id}> $line\n";
            }
        }
    }

    public function serve()
    {
        $processes = array();
        $workers   = 0;
        $id = 0;
        while (true) {
            foreach ($processes as $i => $process) {
                $output = $process->getOutput();
                if(!empty($output)) {
                    $this->processReport($process, $output);
                    $processes[$i]->clearOutput();
                }

                if ($process->status == 'busy' && $process->time+60 < time()) {
                    // kill it!
                    $process->stop(1);
                }

                if (!$process->isRunning()) {
                    echo "master> {$process->id} seems dead, respawning\n";
                    if (!empty($process->task)) {
                        echo "master>\t Rescheduling old task\n";
                        $this->client->push($process->task[0], $process->task[1]);
                    }
                    unset($processes[$i]);
                    --$workers;
                    continue;
                }
            }

            while ($workers < 8) {
                $processes[] = $this->createWorker(++$id);
                ++$workers;
            }

            sleep(1);
        }
    }

    protected function report($action, $args)
    {
        $data = json_encode($args);
        echo "\0{$action}\0" . strlen($data) . "\0" . $data . "\n";
        flush();
    }

    public function worker()
    { 
        $annotations = new Notoj\Annotations;
        foreach ($this->config->getDirectories() as $dir) {
            $dir = new Notoj\Dir($dir);
            $dir->getAnnotations($annotations);
        }

        foreach ($annotations->get('Worker', true) as $worker) {
            foreach ($worker->get('Worker') as $args) {
                $name = current($args['args'] ?: []);
                if (empty($name)) {
                    continue;
                }
                $services[$name] = new Service($worker, $args['args']);
            }
        }

        $engine = $this->config->getEngine();
        $engine->addServices(array_keys($services));

        $ite = 0;

        while ($task = $engine->listen()) {
            $this->report('start', ['timeout' => $services[$task[0]]->timeout, 'args' => $task]);
            $services[$task[0]]->execute($task[1]);
            $this->report('end');
            $ite++;
        }

    }
}

