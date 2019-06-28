<?php

namespace guifcoelho\JsonModels;

use guifcoelho\JsonModels\Config;
use guifcoelho\JsonModels\Query;
use guifcoelho\JsonModels\Exceptions\JsonModelsException;

class Model{

    /**
     * Table name
     *
     * @var string
     */
    protected $table = '';

    /**
     * Name of fields which will not be returned in toArray() (and similar) functions
     *
     * @var array
     */
    protected $hidden = [];


    /**
     * Name of the table's primary key. Unique items will be defined by this. It must be an integer between 1 and infinity.
     *
     * @var string
     */
    protected $primary_key = 'id';

    /**
     * All required table fields. If any field name is provided, the data loading will require them.
     *
     * @var array
     */
    protected $fields = [];

    /**
     * Creates a new JsonModel object
     *
     * @param array $dados
     */
    public function __construct(array $data = []){
        if(count($data) > 0){
            $this->loadData($data);
        }
    }

    /**
     * Loads the configuration file and returns the table path
     *
     * @return string
     */
    public static function getTablePath(): string
    {
        $table = (new static)->table;
        return (new Config)->get('path_to_tables')."/{$table}.json";
    }

    public function getFields():array
    {
        return $this->fields;
    }

    /**
     * Returns the table primary key
     *
     * @return integer
     */
    public static function getPrimaryKey():string
    {
        return (new static)->primary_key;
    }

    public static function getName():string
    {
        $class = explode("\\", get_class(new static()));
        return strtolower($class[count($class)-1]);

    }

    /**
     * Loads the data fields into the JsonModel. It will must respect the list of fields constraint.
     *
     * @param array $data
     * @return void
     */
    protected function loadData(array $data):void
    {
        if(count($this->fields) == 0){
            foreach($data as $field => $value){
                $this->$field = $value;
                $this->fields[] = $field;
            }
        }else{
            foreach($this->fields as $field){
                if(!array_key_exists($field, $data)){
                    throw new JsonModelsException("Field '{$field}' was not found in the data provided");
                }
                $this->$field = $data[$field];
            }
        }
        
    }

    /**
     * Querys the json table with
     *
     * @param string $field
     * @param ...$params Must provide comparison sign and value (sign is optional)
     * 
     * @return Query
     */
    public static function where(string $field, ...$params):Query
    {
        return (new Query(get_class(new static)))->where($field, ...array_values($params));
    }

    /**
     * Returns all data inside JsonModel table
     */
    public static function all(){
        return (new Query(get_class(new static)))->all();
    }

    /**
     * ```belongsToOne``` relationship
     *
     * @param string $owner_class
     * @param string $field
     * @param string $field_in_owner_class
     */
    protected function belongsToOne(string $owner_class, string $field = '', string $field_in_owner_class = ''){
        $owner_primary_key = $owner_class::getPrimaryKey();
        if($field == ''){
            $owner_std_name = $owner_class::getName();
            $field = "{$owner_std_name}_{$owner_primary_key}";
        }
        if($field_in_owner_class == ''){
            $field_in_owner_class = $owner_primary_key;
        }
        return $owner_class::where($field_in_owner_class, $this->$field);
    }

    /**
     * ```hasMany``` relationship
     *
     * @param  string $child_class
     * @param string $field_in_owned_class
     * @param string $field
     */
    protected function hasMany(string $child_class, string $field_in_owned_class = '', string $field = ''){
        if($field_in_owned_class == ''){
            $owner_std_name = static::getName();
            $field_in_owned_class = "{$owner_std_name}_{$this->primary_key}";
        }
        if($field == ''){
            $field = $this->primary_key;
        }
        return $child_class::where($field_in_owned_class, $this->$field);
    }

    /**
     * Extracts the data into an array.
     *
     * @return array
     */
    public function toArray():array
    {
        $arr = [];
        foreach($this->fields as $field){
            $arr[$field] = $this->$field;
        }
        return $arr;
    }

    /**
     * Extracts the data into json
     *
     * @return string
     */
    public function toJson():string
    {
        return json_encode($this->toArray());
    }
}
