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
        if ($_SERVER['PHP_SELF'] != '-') {
            array_shift($files);
        }

        $boostrap = "<?php 
        foreach (" . var_export($files, true) . " as \$file) {
            require_once \$file;
        }

        define('__WORKER__', " . var_export($id, true). ");

        \$config = crodas\Worker\Config::import(" . $this->config->export() .  ");
        \$config['worker_id'] = __WORKER__;

        \$server = new crodas\Worker\Server(\$config);
        \$server->worker();
        ";

        $this->log(null, "Starting process $id");
        $process = new PhpProcess($boostrap);
        $process->start();
        $process->id        = $id;
        $process->time      = time();
        $process->status    = empty($args) ? 'idle' : 'busy';
        $process->jobs     = 0;
        $process->failed    = 0;

        return $process;
    }

    protected function formatBytes($bytes, $precision = 2)
    { 
        $units = array('B', 'KB', 'MB', 'GB', 'TB'); 

        $bytes = max($bytes, 0); 
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024)); 
        $pow = min($pow, count($units) - 1); 

        // Uncomment one of the following alternatives
        $bytes /= pow(1024, $pow);
        // $bytes /= (1 << (10 * $pow)); 

        return round($bytes, $precision) . ' ' . $units[$pow]; 
    }

    protected function log($process, $text)
    {
        $date = date("r");
        if ($process) {
            echo "[$date] [{$process->id}] $text\n";
        } else {
            echo "[$date] [master] $text\n";
        }
    }

    protected function checkprocessHealth($process)
    {
        $memleak = $process->memory_last > $process->memory_begin*$this->config['memory_threshold'];
        if ($memleak && $process->jobs > $this->config['minimum_jobs']) {
            $process->stop(1);
            $this->log($process, "kill due memory growth over time");
        }
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

                $args = json_decode($parts[3], true);

                switch ($parts[1]) {
                case 'start':
                    $process->status    = 'busy';
                    $process->job       = Job::restore($this->config, $args['args']);
                    $process->time      = time();
                    $process->timeout   = max($args['timeout'], 60);
                    $this->log($process, "beging job '{$process->job->function}'");
                    break;
                case 'failed':
                    $process->job       = null;
                    $process->status    = 'idle';
                    $process->time      = time();
                    $process->failed++;
                    if (empty($process->memory_begin)) {
                        $process->memory_begin = $args[0];
                    }
                    $process->memory_last = $args[1];
                    $this->log($process, "done with error ({$args[2]}: {$args[3]})");
                    $this->checkprocessHealth($process);
                    break;
                case 'end':
                    $process->job->setResult($args[0]);
                    $process->job   = null;
                    $process->status = 'idle';
                    $process->time   = time();
                    $process->jobs++;
                    if (empty($process->memory_begin)) {
                        $process->memory_begin = $args[1];
                    }
                    $process->memory_last = $args[2];
                    $this->log($process, "done with success");
                    $this->checkprocessHealth($process);
                    break;
                case 'empty':
                    $this->log($process, "no job, respawning in {$args[0]} seconds");
                    break;
                }

            } else {
                $this->log($process, $line);
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

                $timeout = false;
                if ($process->status == 'busy' && $process->time+$process->timeout < time()) {
                    // kill it!
                    $timeout = true;
                    $process->stop(1);
                }

                if (!$process->isRunning()) {
                    $this->log(null, "seems dead, respawning"); 
                    if (!empty($process->job)) {
                        $this->log(null, "Rescheduling old failed job");
                        $this->config->getEngine()->push($process->job);
                        $process->job = null;
                    }
                    unset($processes[$i]);
                    --$workers;
                    continue;
                }
            }

            while ($workers < 8) {
                $processes[] = $this->createWorker(gethostname() . ':' . (++$id));
                ++$workers;
            }

            sleep(1);
        }
    }

    public function getServices()
    {
        $annotations = new Notoj\Annotations;

        foreach ($this->config->getDirectories() as $dir) {
            $dir = new Notoj\Dir($dir);
            $dir->getAnnotations($annotations);
        }

        $services = array();

        foreach ($annotations->get('Worker', true) as $worker) {
            foreach ($worker->get('Worker') as $args) {
                $name = current($args['args'] ?: []);
                if (empty($name)) {
                    continue;
                }
                $services[$name] = new Service($worker, $args['args'], $this);
            }
        }

        return $services;
    }

    protected function report($action, Array $args = [])
    {
        $data = json_encode($args);
        echo "\n\0{$action}\0" . strlen($data) . "\0" . $data . "\n";
        flush();
    }

    public function worker()
    { 
        $services = $this->getServices();

        if (empty($services)) {
            $sleep = rand(5, 20);
            $this->report('empty', [$sleep]);
            sleep($sleep);
            exit;
        }

        $engine = $this->config->getEngine();
        $engine->addServices(array_keys($services));


        while ($job = $engine->listen()) {
            $start_memory = memory_get_usage(true);
            $this->report('start', ['timeout' => $services[$job->function]->timeout, 'args' => $job->serialize()]);
            try {
                $result = $services[$job->function]->execute($job);
                if ($job->synchronous) {
                    $this->client->push($job->id, [$result]);
                }
            } catch (\Exception $e) {
                $end_memory = memory_get_usage(true);
                $this->report('failed', [$start_memory, $end_memory, get_class($e), $e->getMessage()]);
                continue;
            }
            $end_memory = memory_get_usage(true);
            $this->report('end', [$result, $start_memory, $end_memory]);
        }

    }
}

