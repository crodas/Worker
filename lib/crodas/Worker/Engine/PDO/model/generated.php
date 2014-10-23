<?php
namespace crodas\Worker\Engine\PDO;

class TaskTableAbstractQueryBuilder
{
    protected $manager;

    protected $table = 'tasks';
    protected $cols = array();
    protected $joins = array();
    protected $limit = 20;
    protected $skip = 0;
    protected $vars = array();
    protected $where = array();
    protected $sort = array();

    public function __construct(ConnectionManager $manager)
    {
        $this->manager = $manager;
    }


    public function sort($column, $dir = 1)
    {
        switch ($column) {
        case 'taskId':
        case 'task_id':
            $this->sort['task_id'] = 'task_id ' . ($dir  == 1 ? 'ASC' : 'DESC');
            break;

        case 'taskType':
        case 'task_type':
            $this->sort['task_type'] = 'task_type ' . ($dir  == 1 ? 'ASC' : 'DESC');
            break;

        case 'taskPayload':
        case 'task_payload':
            $this->sort['task_payload'] = 'task_payload ' . ($dir  == 1 ? 'ASC' : 'DESC');
            break;

        case 'taskStatus':
        case 'task_status':
            $this->sort['task_status'] = 'task_status ' . ($dir  == 1 ? 'ASC' : 'DESC');
            break;

        case 'taskHandle':
        case 'task_handle':
            $this->sort['task_handle'] = 'task_handle ' . ($dir  == 1 ? 'ASC' : 'DESC');
            break;

        default:
            throw new \RuntimeException("Cannot sort by {$column}");
        }
        return $this;
    }

    public function getInfo()
    {
        return array(
            'where' => $this->where,
            'vars'  => $this->vars,
            'limit' => $this->limit,
            'skip' => $this->skip,
            'joins' => $this->joins,
            'cols'  => $this->cols,
            'sort'  => $this->sort,
        );
    }

    public function skip($skip)
    {
        $this->skip = (int)$skip;
        return $this;
    }

    public function where($column, $value, $op = '=')
    {
        $name = 'f' . uniqid(true);
        $this->vars[$name] = $value;

        switch ($column) {
        case 'taskId':
        case 'task_id':
            $this->where[$name] = 'task_id ' . $op . " :$name";
            break;

        case 'taskType':
        case 'task_type':
            $this->where[$name] = 'task_type ' . $op . " :$name";
            break;

        case 'taskPayload':
        case 'task_payload':
            $this->where[$name] = 'task_payload ' . $op . " :$name";
            break;

        case 'taskStatus':
        case 'task_status':
            $this->where[$name] = 'task_status ' . $op . " :$name";
            break;

        case 'taskHandle':
        case 'task_handle':
            $this->where[$name] = 'task_handle ' . $op . " :$name";
            break;

        default:
            throw new \RuntimeException("Cannot sort by {$column}");
        }
        return $this;
    }

    public function limit($limit)
    {
        $this->limit = (int)$limit;
        return $this;
    }

    public function getQuery()
    {
        $joins = join("", $this->joins);
        $cols = join(", ", $this->cols);
        if (!empty($cols)) {
            $cols = ", $cols";
        }
        $sql = "SELECT tasks.* $cols FROM tasks $joins";
        if (!empty($this->where)) {
            $sql .= "WHERE " . implode(" AND ", $this->where);
        }
        if (!empty($this->sort)) {
            $sql .= " ORDER BY " . implode(", ", $this->sort);
        }
        $sql .= " LIMIT {$this->skip}, {$this->limit}";

        return $sql;
    }

    public function get()
    {
        return $this->manager->query('tasks', $this->getQuery(), $this->vars);
    }

}

abstract class TaskTableAbstract implements \ArrayAccess
{
    protected $manager;

    public function __construct(ConnectionManager $manager)
    {
        $this->manager = $manager;
    }

    public function getTableAndId(Task $row)
    {
        return [
        'table' => 'tasks',
        'id'    => 'task_id',
        'value' => [$row->taskId],
        ];
    }


    public function offsetSet($id, $object)
    {
    }

    public function offsetUnset($id)
    {
    }

    public function offsetExists($id)
    {
        return $this->findOneBytaskId($id) instanceof Task;
    }

    public function offsetGet($id)
    {
        return $this->findOneBytaskId($id);
    }

    public function queryBuilder()
    {
        return new TaskTableAbstractQueryBuilder($this->manager);
    }

    public function first()
    {
        return $this->manager->queryOne(
            $this,
            "
            SELECT 
                tasks.*
            FROM tasks 
                
            LIMIT 1
            ");
    }

    public function all($offset = 0, $limit = 20)
    {
        $offset = (int)$offset;
        $limit = (int)$limit;

        return $this->manager->query(
            $this,
            "
            SELECT 
                tasks.*
            FROM tasks 
                
            LIMIT $offset, $limit
            ");
    }

    public function getReflection()
    {
        return ['columns'=>[0=>['property'=>'taskId','column'=>'task_id','type'=>'integer','is_relation'=>false,'relate_to'=>NULL,'annotation'=>[0=>'autoincrement']],1=>['property'=>'taskType','column'=>'task_type','type'=>'string','is_relation'=>false,'relate_to'=>NULL,'annotation'=>[]],2=>['property'=>'taskPayload','column'=>'task_payload','type'=>'text','is_relation'=>false,'relate_to'=>NULL,'annotation'=>[]],3=>['property'=>'taskStatus','column'=>'task_status','type'=>'integer','is_relation'=>false,'relate_to'=>NULL,'annotation'=>[]],4=>['property'=>'taskHandle','column'=>'task_handle','type'=>'string','is_relation'=>false,'relate_to'=>NULL,'annotation'=>[]]],'primary_key'=>['property'=>'taskId','column'=>'task_id']];
    }


    public function findOneByTaskId($task_id)
    {
        return $this->manager->queryOne(
            $this,
            "
            SELECT 
                tasks.*
            FROM tasks 
                
            WHERE 
                tasks.task_id = ?
            LIMIT 1
            ",
            [$task_id]
        );
    }

    public function findByTaskId($task_id, $offset = 0, $limit = 20)
    {
        $offset = (int)$offset;
        $limit = (int)$limit;


        return $this->manager->query(
            $this,
            "
            SELECT 
                tasks.*
            FROM tasks 
                
            WHERE 
                tasks.task_id = ?
            LIMIT $offset, $limit
            ",
            [$task_id]
        );
    }
    public function findOneByTaskHandleAndTaskStatus($task_handle, $task_status)
    {
        return $this->manager->queryOne(
            $this,
            "
            SELECT 
                tasks.*
            FROM tasks 
                
            WHERE 
                tasks.task_handle = ? AND tasks.task_status = ?
            LIMIT 1
            ",
            [$task_handle, $task_status]
        );
    }

    public function findByTaskHandleAndTaskStatus($task_handle, $task_status, $offset = 0, $limit = 20)
    {
        $offset = (int)$offset;
        $limit = (int)$limit;


        return $this->manager->query(
            $this,
            "
            SELECT 
                tasks.*
            FROM tasks 
                
            WHERE 
                tasks.task_handle = ? AND tasks.task_status = ?
            LIMIT $offset, $limit
            ",
            [$task_handle, $task_status]
        );
    }


    public function populate(Task $object, Array $data, $fromDb = false)
    {
        $object->setOriginalData1544907e1a3ff7($this->manager, $data, $fromDb);
        return $object;
    }

    public function map(Array $row, $fromDb = true)
    {
        $object = new Task;
        $object->setOriginalData1544907e1a3ff7($this->manager, $row, $fromDb);
        return $object;
    }

    public function getId()
    {
        return array(
            'property' => 'taskId',
            'column'   => 'task_id',
        );
    }

    public function getTable()
    {
        return 'tasks';
    }

    public function getArray($object, ConnectionManager $conn, Array $prev = array())
    {
        $data = $object->toArray($conn);

        $validation = array();

        foreach ($data as $key => $value) {
            if (!empty($prev[$key]) && $prev[$key] === $value) {
                unset($data[$key]);
            }
        }


        if (!empty($validation)) {
            $error = new \RuntimeException("Validation error");
            $error->validations = $validation;
            throw $error;
        }

        return $data;
    }

    public function truncate()
    {
        return $this->manager->execute('DELETE FROM tasks');
    }

    public function total()
    {
        return $this->manager->queryOne(NULL, 'SELECT count(*) AS total FROM tasks')['total'];
    }
}
trait TaskEntity
{
    private $originalData1544907e1a3ff7 = array();
    private $manager1544907e1a3ff7;

    public $taskId = NULL;

    public $taskType = NULL;

    public $taskPayload = NULL;

    public $taskStatus = 1;

    public $taskHandle = '';


    public function setOriginalData1544907e1a3ff7(ConnectionManager $manager, Array $row, $fromDb = true)
    {
        $data = array();
        $this->manager1544907e1a3ff7 = $manager;
        if (array_key_exists('task_id', $row)) {
            $data['taskId'] = $row['task_id'];
            $this->taskId = $row['task_id'];
        }
        if (array_key_exists('tasks_taskId', $row)) {
            $data['taskId'] = $row['tasks_taskId'];
            $this->taskId = $row['tasks_taskId'];
        }
        if (array_key_exists('task_type', $row)) {
            $data['taskType'] = $row['task_type'];
            $this->taskType = $row['task_type'];
        }
        if (array_key_exists('tasks_taskType', $row)) {
            $data['taskType'] = $row['tasks_taskType'];
            $this->taskType = $row['tasks_taskType'];
        }
        if (array_key_exists('task_payload', $row)) {
            $data['taskPayload'] = $row['task_payload'];
            $this->taskPayload = $row['task_payload'];
        }
        if (array_key_exists('tasks_taskPayload', $row)) {
            $data['taskPayload'] = $row['tasks_taskPayload'];
            $this->taskPayload = $row['tasks_taskPayload'];
        }
        if (array_key_exists('task_status', $row)) {
            $data['taskStatus'] = $row['task_status'];
            $this->taskStatus = $row['task_status'];
        }
        if (array_key_exists('tasks_taskStatus', $row)) {
            $data['taskStatus'] = $row['tasks_taskStatus'];
            $this->taskStatus = $row['tasks_taskStatus'];
        }
        if (array_key_exists('task_handle', $row)) {
            $data['taskHandle'] = $row['task_handle'];
            $this->taskHandle = $row['task_handle'];
        }
        if (array_key_exists('tasks_taskHandle', $row)) {
            $data['taskHandle'] = $row['tasks_taskHandle'];
            $this->taskHandle = $row['tasks_taskHandle'];
        }

        if ($fromDb) {
            $this->originalData1544907e1a3ff7 = array_merge(
                $this->originalData1544907e1a3ff7,
                $data
            );
        }
    }

    public function getOriginalData1544907e1a3ff7()
    {
        return $this->originalData1544907e1a3ff7;
    }


    final public function toArray(ConnectionManager $conn)
    {
        return array(
            'task_id' => $this->taskId,
            'task_type' => $this->taskType,
            'task_payload' => $this->taskPayload,
            'task_status' => $this->taskStatus,
            'task_handle' => $this->taskHandle,
        );
    }

}
