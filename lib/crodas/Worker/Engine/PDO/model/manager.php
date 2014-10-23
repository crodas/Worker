<?php
namespace crodas\Worker\Engine\PDO;
use PDOStatement;
use Iterator;
use PDO;

require_once __DIR__ . "/generated.php";

/**
 *  Pseudo UUID support, prefixed with time so it is
 *  ordered by creation date
 */
if (!function_exists('openssl_random_pseudo_bytes')) {
    /**
     * Function borrowed from https://github.com/ramsey/uuid/blob/master/src/Uuid.php
     *
     *@copyright Copyright (c) 2013-2014 Ben Ramsey <http://benramsey.com>
     *@license http://opensource.org/licenses/MIT MIT
     */
    function openssl_random_pseudo_bytes($length)
    {
        $bytes = '';
        foreach (range(1, $length) as $i) {
            $bytes = chr(mt_rand(0, 255)) . $bytes;
        }

        return $bytes;
    }
}

/**
 *  Pseudo-UUID, it uses time and microtime() as the first 6 bytes
 */
function UUID1544907e1a3ff7()
{
    $bytes = str_split(bin2hex(openssl_random_pseudo_bytes(10)), 4);
    $bytes[0][0] = '4';
    $bytes[1][0] = '9';

    return dechex(time()) . '-' . str_pad(dechex(microtime()*1000), 4, '0')
    . '-' . $bytes[0] . '-' . $bytes[1]
    . '-' . $bytes[2] . $bytes[3] . $bytes[4];
}


class Cursor implements Iterator
{
    protected $manager;
    protected $mapper;
    protected $stmt;
    protected $id;
    protected $total;
    protected $offset;
    protected $all;

    public function __construct (ConnectionManager $manager, $mapper, PDOStatement $stmt)
    {
        $this->manager = $manager;
        $this->mapper  = $mapper;
        $this->stmt    = $stmt;
        $this->id      = $mapper->getId();
        $this->all     = $this->stmt->fetchAll(PDO::FETCH_ASSOC);
        $this->offset  = 0;
        $this->total   = count($this->all);
    }

    public function current()
    {
        return $this->mapper->map($this->all[$this->offset]);
    }
    
    public function key()
    {
        return $this->all[$this->offset][$this->id];
    }

    public function count()
    {
        return $this->total;
    }

    public function rewind()
    {
        $this->offset = 0;
    }

    public function valid()
    {
        return $this->offset < $this->total;
    }

    public function next()
    {
        ++$this->offset;
    }
}

class Stmt
{
    protected $stmt;
    protected $conn;
    protected $mapper;

    public function __construct(ConnectionManager $conn, \PDOStatement $stmt, $mapper)
    {
        $this->conn = $conn;
        $this->stmt = $stmt;
        $this->mapper = $mapper;
    }

    public function execute(Array $args)
    {
        $this->stmt->execute($args);
        return $this->mapper ? new Cursor($this->conn, $this->mapper, $this->stmt) : $this->stmt;
    }
}

class ConnectionManager
{
    protected $conn;
    protected $pdo;
    protected $level = 0;

    public function __construct(\PDO $pdo)
    {
        $this->pdo  = $pdo;
        $pdo->setAttribute( PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION );
    }

    public function getTables()
    {
        return array (
            'task' => 'tasks',
        );
    }

    public function populateFromArray($object, Array $data)
    {
        return $this->getTable(get_class($object))->populate($object, $data);
    }

    public function createFromArray($table, Array $data)
    {
        return $this->getTable($table)->map($data, false);
    }

    public function __get($table)
    {
        switch (strtolower($table)) {
        case 'tasks':
        case 'tasktableabstract':
        case 'taskentity':
        case 'crodas\\worker\\engine\\pdo\\tasktable':
        case 'crodas\\worker\\engine\\pdo\\task':
        case 'tasktable':
        case 'task':
            return new TaskTable($this);
        }

        throw new \RuntimeException("Cannot find table {$table}");
    }
    public function getTable($table)
    {
        switch (strtolower($table)) {
        case 'tasks':
        case 'tasktableabstract':
        case 'taskentity':
        case 'crodas\\worker\\engine\\pdo\\tasktable':
        case 'crodas\\worker\\engine\\pdo\\task':
        case 'tasktable':
        case 'task':
            return new TaskTable($this);
        }

        throw new \RuntimeException("Cannot find table {$table}");
    }

    private function queryPrepare($mapper, $sql, Array $args = array())
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($args);
        return $stmt;
    }

    public function queryOne($mapper, $sql, Array $args = array())
    {
        if (is_string($mapper)) {
            $mapper = $this->getTable($mapper);
        }
        $row = $this->queryPrepare($mapper, $sql, $args)->fetch();
        return $row ? ($mapper ? $mapper->map($row) : $row) : NULL;
    }

    public function prepare($mapper, $sql)
    {
        if (is_string($mapper)) {
            $mapper = $this->getTable($mapper);
        }
        $stmt = $this->pdo->prepare($sql);
        return new Stmt($this, $stmt, $mapper);
    }

    public function execute($sql)
    {
        return $this->pdo->exec($sql);
    }

    public function query($mapper, $sql, Array $args = array())
    {
        if (is_string($mapper)) {
            $mapper = $this->getTable($mapper);
        }
        
        $stmt = $this->queryPrepare($mapper, $sql, $args);
        return $mapper ? new Cursor($this, $mapper, $stmt)
        : $stmt->fetchAll();
    }


    public function createTables()
    {
        $pdo = $this->pdo;
        require __DIR__ . "/setup.php";
    }

    public function begin()
    {
        if (++$this->level == 1) {
            $this->pdo->beginTransaction();
        } else {
            $this->pdo->query("SAVEPOINT rollback_{$this->level}");
        }
    }

    public function commit()
    {
        if ($this->level == 1) {
            $this->pdo->commit();
        } else {
            $this->pdo->query("RELEASE SAVEPOINT rollback_{$this->level}");
        }
        --$this->level;
    }

    public function rollback()
    {
        if ($this->level == 1) {
            $this->pdo->rollback();
        } else {
            $this->pdo->query("ROLLBACK TO SAVEPOINT rollback_{$this->level}");
        }
        --$this->level;
    }

    protected function update($table, Array $data, Array $where)
    {
        $keys = array_keys($data);
        $columns = implode("= ?,", $keys) . ' = ?';
        $filter  = implode("= ?,", array_keys($where)) . " = ?";
        $sql = $this->pdo->prepare("UPDATE {$table} SET $columns WHERE $filter");
        $sql->execute(array_merge(array_values($data), array_values($where)));
    }

    protected function insert($table, Array $data)
    {
        $keys = array_keys($data);
        $columns = implode(",", $keys);
        $fields  = ':' . implode(",:", $keys);
        $sql = $this->pdo->prepare("INSERT INTO {$table}($columns) VALUES($fields)");
        $sql->execute($data);
    }

    public function delete($object)
    {
        $mapper = $this->getTable(get_class($object));
        $info   = $mapper->getTableAndId($object);
        $this->pdo->prepare("DELETE FROM {$info['table']} WHERE {$info['id']} = ?")->execute($info['value']);
        return $this;
    }

    public function save($object)
    {
        $original = $object->getOriginalData1544907e1a3ff7();
        $mapper   = $this->getTable(get_class($object));
        $array    = $mapper->getArray($object, $this, $original);

        if (empty($array)) {
            return $this;
        }

        $this->begin();

        $id = $mapper->getId();

        if (!empty($original[$id['property']])) {
            $this->update($mapper->getTable(), $array, array($id['column'] => $original[$id['property']]));
        } else {
            $this->insert($mapper->getTable(), $array);
            $array[$id['column']] = $this->pdo->lastInsertId();
        }

        $this->commit();

        $object->setOriginalData1544907e1a3ff7($this, $array);

        return $this;
    }
}
