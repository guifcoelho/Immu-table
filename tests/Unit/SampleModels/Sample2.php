<?php

namespace guifcoelho\JsonModels\Tests\Unit\SampleModels;

use guifcoelho\JsonModels\Model;

class Sample2 extends Model
{

    protected $fillable = ['id', 'name', 'email'];

    protected $table = "test_table2";

}

