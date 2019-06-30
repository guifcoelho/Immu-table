<?php

namespace guifcoelho\JsonModels;

use JsonMachine\JsonMachine;
use guifcoelho\JsonModels\Model;
use Illuminate\Support\Collection;
use guifcoelho\JsonModels\Exceptions\JsonModelsException;

class Query{

    protected $class = '';

    protected $queried = [];

    /**
     * Instanciates a Query object
     *
     * @param array $data
     */
    public function __construct(string $class){
        if(!is_subclass_of($class, Model::class)){
            throw new JsonModelsException("'{$class}' must be a subclass of '".Model::class."'");
        }
        $this->class = $class;
    }

    /**
     * Loads table data JsonMachine
     * 
     * @param bool $as_stream
     */
    private function loadTable(bool $as_stream = true)
    {
        $table_path = $this->class::getTablePath();
        if(!file_exists($table_path)){
            return [];
        }
        if($as_stream){
            return JsonMachine::fromFile($table_path);
        }else{
            return json_decode(file_get_contents($table_path), true);
        }
    }

    /**
     * Evaluates a JsonModel object according to its field, comparison sign and value searched
     *
     * @param mixed $el
     * @param string $sign
     * @param mixed $value
     * @return boolean
     */
    private function evalModelItem($el, string $sign, $value):bool
    {
        switch($sign){
            case '=':
            case '==': return $el == $value;
            case '<': return $el < $value;
            case '>': return $el > $value;
            case '<=': return $el <= $value;
            case '>=': return $el >= $value;
            case '===': return $el === $value;
            default: throw new JsonModelsException("The second argument must be a valid comparison sign");
        }
    }

    /**
     * Support function. Gets the query arguments
     *
     * @param array $args
     * @return array
     */
    public static function getQueryArguments(array $args):array
    {
        $sign = "==";
        if(count($args) == 1){
            $value = $args[0];
        }
        if(count($args) == 2){
            if(!is_string($args[0]) || strlen($args[0]) > 3){
                throw new JsonModelsException("The second argument must be a valid comparison sign");
            }
            $sign = $args[0];
            if(!is_numeric($args[1]) && !is_string($args[1])){
                throw new JsonModelsException("The third argument must be either a number or a string");
            }
            $value = $args[1];
        }
        return [
            'sign' => $sign,
            'value' => $value
        ];
    }

    /**
     * Return list of primary keys queried
     *
     * @return array
     */
    public function getQueried():array
    {
        return $this->queried;
    }

    /**
     * Queries the JsonModel table
     *
     * @param string $field
     * @param ...$params Must provide comparison sign and value (sign is optional)
     * @return self
     */
    public function where(string $field, ...$params):self
    {
        $args = static::getQueryArguments($params);
        $primary_key_name = $this->class::getPrimaryKey();
        if(count($this->queried) == 0){
            $data = $this->loadTable();
            $query = [];
            foreach($data as $item){
                if($this->evalModelItem($item[$field], $args['sign'], $args['value'])){
                    $query[] = $item[$primary_key_name];
                }
            }
            $this->queried = $query;
        }else{
            $collection = $this->get();
            $query = [];
            foreach($collection as $item){
                if($this->evalModelItem($item->$field, $args['sign'], $args['value'])){
                    $query[] = $item->$primary_key_name;
                }
            }
            $this->queried = $query;
        }
        
        return $this;
    }  
    
    /**
     * Queries the json table with orWhere statement
     *
     * @param string $field
     * @param ...$params Must provide comparison sign and value (sign is optional)
     * @return self
     */
    public function orWhere(string $field, ...$params):self
    {
        $args = static::getQueryArguments($params);
        $query = $this->class::where($field, $args['sign'], $args['value'])->getQueried();
        $this->queried = array_unique(array_merge($this->queried, $query));
        return $this;
    }

    /**
     * Returns the first item of the collection. It will return null if nothing is found
     */
    public function first(){
        if(count($this->queried) == 0){
            return null;
        }
        $data = $this->loadTable();
        $primary_key = $this->class::getPrimaryKey();
        foreach($data as $item){
            if($item[$primary_key] == $this->queried[0]){
                return new $this->class($item);
            }
        }
    }

    

    /**
     * Returns all data inside the json table
     *
     * @return Collection
     */
    public function all():Collection
    {
        $data = $this->loadTable(false);
        foreach($data as &$item){
            $item = new $this->class($item);
        }
        return new Collection($data);
    }

    /**
     * Gets the queried collection
     *
     * @return Collection
     */
    public function get():Collection
    {
        $queried = $this->queried;
        $collection = [];
        if(count($queried) > 0){
            $data = $this->loadTable();
            $primary_key = $this->class::getPrimaryKey();
            foreach($data as $item){
                $item_primary_key_value = $item[$primary_key];
                if(array_search($item_primary_key_value, $queried) !== false){
                    $collection[] = new $this->class($item);
                    $queried = array_filter($queried, function($el) use($item_primary_key_value){
                        return $el != $item_primary_key_value;
                    });
                }
            }
            
        }
        return new Collection($collection);
    }

    /**
     * Gets the last primary key (as defined in the JsonModel) of the json file
     *
     * @return integer
     */
    public function getLastPrimaryKeyValue():int
    {
        $data = $this->loadTable();
        $last_primary_key_value = 0;
        foreach($data as $item){
            $last_primary_key_value = max($last_primary_key_value, $item[$this->class::getPrimaryKey()]);
        }
        return $last_primary_key_value;
    }

    /**
     * Inserts data into the JsonModel table. DO NOT use except for testing or prototyping
     *
     * @param array|Collection $data
     * @return void
     */
    public function insert($data)
    {
        if(!is_array($data) && !is_object($data) || (is_object($data) && !get_class($data) == Collection::class && !is_subclass_of($data, Collection::class))){
            throw new JsonModelsException("Data to be inserted must 'array' or subclass of '".Collection::class."'");
        }
        $current = $this->loadTable();
        $collection = [];
        foreach($current as $item){
            $collection[] = new $this->class($item);
        }
        $last_primary_key_value = $this->getLastPrimaryKeyValue();
        $primary_key = $this->class::getPrimaryKey();

        if(is_array($data)){
            foreach($data as &$item){
                if(is_array($item)){
                    $item = new $this->class($item);
                }
            }
            $data = new Collection($data);
        }
        foreach(range(0,count($data)-1) as $i){
            $data[$i]->$primary_key = ++$last_primary_key_value;
            $data[$i] = new $this->class($data[$i]->toArray());
            $collection[] = $data[$i];
        }
        
        $collection = new Collection($collection);
        file_put_contents($this->class::getTablePath(), json_encode($collection->toArray()));
        
        $models_inserted = new Collection($data);

        return count($models_inserted) == 1 ? $models_inserted[0] : $models_inserted;
    }
}
