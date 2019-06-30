<?php

namespace guifcoelho\JsonModels\Tests\Unit;

use guifcoelho\JsonModels\Tests\TestCase;
use guifcoelho\JsonModels\Exceptions\JsonModelsException;

use guifcoelho\JsonModels\Query;
use guifcoelho\JsonModels\Model;
use Illuminate\Support\Collection;

use guifcoelho\JsonModels\Tests\Unit\SampleModels\Sample;
use guifcoelho\JsonModels\Tests\Unit\SampleModels\Sample3;
use guifcoelho\JsonModels\Tests\Unit\SampleModels\Sample4;
use guifcoelho\JsonModels\Tests\Unit\SampleModels\SampleOwned;

class CreateModelTest extends TestCase
{
    use \guifcoelho\JsonModels\Testing\Support\JsonTablesAssertions;

    public function test_create_and_load_json_model()
    {
        $data = jsonModelsFactory(Sample::class, 10, $this->factory_path)->create();
        file_put_contents(Sample::getTablePath(), json_encode($data->toArray()));
        $model_data = Sample::all();
        $this->assertTrue(get_class($model_data) == Collection::class);
        $this->assertSimilarArrays($data->toArray(), $model_data->toArray());
    }

    public function test_query_json_model()
    {
        $data = jsonModelsFactory(Sample::class, 10, $this->factory_path)->create();
        file_put_contents(Sample::getTablePath(), json_encode($data->toArray()));
        $model_data = Sample::where('id', '>', 5)->get();
        $this->assertTrue(get_class($model_data) == Collection::class);
        $filter_data = [];
        foreach($data as $item){
            if($item->id > 5){
                $filter_data[] = $item->toArray();
            }
        }
        $this->assertSimilarArrays($filter_data, $model_data->toArray());
    }

    public function test_get_queried(){
        $dummy = jsonModelsFactory(Sample::class, 10, $this->factory_path)->create();
        $query = Sample::where('id', '>', 5);
        $queried = $query->getQueried();
        $collection = $query->get();
        $collection_id = array_values(array_column($collection->toArray(), 'id'));
        foreach($queried as $id){
            $this->assertTrue(array_search($id, $collection_id) !== false);
        }
    }

    public function test_querying_with_invalid_sign(){
        jsonModelsFactory(Sample::class, 10, $this->factory_path)->create();
        try{
            $model = Sample::where('id', 'das', 3);
        }catch(JsonModelsException $e){
            $this->assertTrue($e->getMessage() == "The second argument must be a valid comparison sign");
        }
    }

    public function test_querying_with_invalid_sign2(){
        jsonModelsFactory(Sample::class, 10, $this->factory_path)->create();
        try{
            $model = Sample::where('id', 'dasdas', 3);
        }catch(JsonModelsException $e){
            $this->assertTrue($e->getMessage() == "The second argument must be a valid comparison sign");
        }
    }

    public function test_querying_with_invalid_value(){
        jsonModelsFactory(Sample::class, 10, $this->factory_path)->create();
        try{
            $model = Sample::where('id', '==', [1,2,3]);
        }catch(JsonModelsException $e){
            $this->assertTrue($e->getMessage() == "The third argument must be either a number or a string");
        }
    }

    public function test_querying_empty_table(){
        if(file_exists(Sample::getTablePath())){
            unlink(Sample::getTablePath());
        }
        if(file_exists(SampleOwned::getTablePath())){
            unlink(SampleOwned::getTablePath());
        }
        $this->assertTrue(count(Sample::all()) == 0);
    }

    public function test_query_with_non_json_model_class(){
        try{
            $query = new Query(get_class($this));
        }catch(JsonModelsException $e){
            $this->assertTrue($e->getMessage() == "'".get_class($this)."' must be a subclass of '".Model::class."'");
        }
    }

    public function test_inserting_with_object_different_than_array_or_collection(){
        $data = 12345;
        try{
            (new Query(Sample::class))->insert($data);
        }catch(JsonModelsException $e){
            $this->assertTrue($e->getMessage() == "Data to be inserted must 'array' or subclass of '".Collection::class."'");
        }
    }

    public function test_querying_first_null(){
        $dummy = jsonModelsFactory(Sample::class, 10, $this->factory_path)->create();
        $query = Sample::where('id', '<', 0)->first();
        $this->assertTrue($query == null);
    }

    public function test_querying_with_where_chaining(){
        $dummy = jsonModelsFactory(Sample::class, 10, $this->factory_path)->create();
        $ids = array_column($dummy->toArray(), 'id');
        $collection = Sample::where('id', '>=', $ids[0])
                            ->where('id', '<', max($ids)/2)
                            ->get();
        $this->assertTrue(count($collection) == 10/2-1);
    }

    public function test_querying_orWhere(){
        $dummy1 = jsonModelsFactory(Sample::class, $this->factory_path)->create();
        $dummy2 = jsonModelsFactory(Sample::class, $this->factory_path)->create();
        $collection = Sample::where('id', $dummy1->id)
                        ->orWhere('id', $dummy2->id)
                        ->get();
        foreach($collection as $item){
            $this->assertTrue($item->id == $dummy1->id || $item->id == $dummy2->id);
        }
    }

    public function test_exception_while_querying_with_fields_constraint(){
        try{
            $dummy = jsonModelsFactory(Sample3::class, 10, $this->factory_path)->create();
        }catch(JsonModelsException $e){
            $this->assertTrue($e->getMessage() == "Field 'email' was not found in the data provided");
        }
    }

    public function test_querying_with_fields_constraint(){
        $data = jsonModelsFactory(Sample4::class, 10, $this->factory_path)->create();
        $this->assertJsonTableHas(Sample4::class, $data->toArray());
    }

    public function test_inserting_array_of_data(){
        $dummy = jsonModelsFactory(Sample::class, 10, $this->factory_path)->make();
        $arr_dummy = $dummy->toArray();
        $models_inserted = (new Query(Sample::class))->insert($arr_dummy);
        $arr_inserted = $models_inserted->toArray();
        $this->assertJsonTableHas(Sample::class, $arr_inserted);
        
        foreach(range(0, count($dummy)-1) as $i){
            foreach(array_keys($arr_dummy[0]) as $key){
                if($key != 'id'){
                    $this->assertTrue($arr_dummy[$i][$key] === $arr_inserted[$i][$key]);
                }
            }
        }
    }
}
