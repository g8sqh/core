<?php

namespace Biigle\Tests;

use App;
use Mockery;
use ModelTestCase;
use Biigle\LabelSource;
use Illuminate\Database\QueryException;

class LabelSourceTest extends ModelTestCase
{
    /**
     * The model class this class will test.
     */
    protected static $modelClass = LabelSource::class;

    public function testAttributes()
    {
        $this->assertNotNull($this->model->name);
    }

    public function testNameRequired()
    {
        $this->model->name = null;
        $this->expectException(QueryException::class);
        $this->model->save();
    }

    public function testNameUnique()
    {
        self::create(['name' => 'xyz']);
        $this->expectException(QueryException::class);
        self::create(['name' => 'xyz']);
    }

    public function testGetAdapter()
    {
        $mock = Mockery::mock();

        App::singleton('Biigle\Services\LabelSourceAdapters\AbCdAdapter', function () use ($mock) {
            return $mock;
        });

        $source = self::create(['name' => 'ab_cd']);

        $this->assertEquals($mock, $source->getAdapter());
    }
}
