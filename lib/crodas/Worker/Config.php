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

use ArrayObject;

class Config extends ArrayObject
{
    protected $engine;
    protected $engine_name;
    protected $dirs = [];

    public static function import(Array $definition)
    {
        $object = new self;
        $object->dirs = $definition['dirs'];
        $object->setEngine($definition['engine_name']);
        foreach ($definition['all'] as $key => $value) {
            $object[$key] = $value;
        }

        return $object;
    }

    public function export()
    {
        return var_export(array(
            'dirs' => $this->dirs,
            'engine_name' => $this->engine_name,
            'all' => (array)$this,
        ), true);
    }

    public function offsetExists($index)
    {
        return true;
    }

    public function offsetGet($index)
    {
        if (!parent::offsetExists($index)) {
            return false;
        }

        return parent::offsetGet($index);
    }

    public function getEngine()
    {
        return $this->engine;
    }

    public function addDirectory($dir)
    {
        $this->dirs[] = $dir;
        return $this;
    }

    public function getDirectories()
    {
        return $this->dirs;
    }

    public function setEngine($engine)
    {
        if (is_string($engine)) {
            if (class_exists($engine))  {
                $class = $engine;
            } else {
                $class = 'crodas\Worker\Engine\\' . ucfirst($engine);
            }
            if (!class_exists($class)) {
                throw new \RuntimeException("Cannot find class $class");
            }
            $engine = new $class;
        }

        $this->engine_name = get_class($engine);

        $engine->setConfig($this);

        $this->engine = $engine;

        return $this;
    }
}
