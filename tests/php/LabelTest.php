<?php

namespace Biigle\Tests;

use Biigle\Label;
use ModelTestCase;
use Illuminate\Database\QueryException;

class LabelTest extends ModelTestCase
{
    /**
     * The model class this class will test.
     */
    protected static $modelClass = Label::class;

    public function testAttributes()
    {
        $this->assertNotNull($this->model->name);
        $this->assertNotNull($this->model->color);
        $this->assertNull($this->model->created_at);
        $this->assertNull($this->model->updated_at);
        $this->assertNotNull($this->model->uuid);
    }

    public function testNameRequired()
    {
        $this->model->name = null;
        $this->expectException(QueryException::class);
        $this->model->save();
    }

    public function testColorRequired()
    {
        $this->model->color = null;
        $this->expectException(QueryException::class);
        $this->model->save();
    }

    public function testUuidRequired()
    {
        $this->model->uuid = null;
        $this->expectException(QueryException::class);
        $this->model->save();
    }

    public function testUuidUnique()
    {
        self::create(['uuid' => 'c796ccec-c746-308f-8009-9f1f68e2aa62']);
        $this->expectException(QueryException::class);
        self::create(['uuid' => 'c796ccec-c746-308f-8009-9f1f68e2aa62']);
    }

    public function testParent()
    {
        $parent = self::create();
        $child = self::create(['parent_id' => $parent->id]);
        $this->assertEquals($parent->id, $child->parent->id);
    }

    public function testParentOnDeleteCascade()
    {
        $parent = self::create();
        $child = self::create(['parent_id' => $parent->id]);
        $parent->delete();
        $this->assertNull($child->fresh());
    }

    public function testTree()
    {
        $tree = LabelTreeTest::create();
        $label = self::create(['label_tree_id' => $tree->id]);
        $this->assertEquals($tree->id, $label->tree->id);
    }

    public function testLabelTreeOnDeleteCascade()
    {
        $tree = LabelTreeTest::create();
        $label = self::create(['label_tree_id' => $tree->id]);
        $tree->delete();
        $this->assertNull($label->fresh());
    }

    public function testChildren()
    {
        $parent = self::create();
        $child = self::create(['parent_id' => $parent->id]);
        $this->assertEquals($child->id, $parent->children()->first()->id);
    }

    public function testSetColorAttribute()
    {
        $label = self::create();
        $label->color = '#aabbcc';
        $this->assertEquals('aabbcc', $label->color);
    }

    public function testIsUsedAnnotationLabel()
    {
        $a = AnnotationLabelTest::create(['label_id' => $this->model->id]);
        $this->assertTrue($this->model->isUsed());
        $a->delete();
        $this->assertFalse($this->model->isUsed());
    }

    public function testIsUsedImageLabel()
    {
        $i = ImageLabelTest::create(['label_id' => $this->model->id]);
        $this->assertTrue($this->model->isUsed());
        $i->delete();
        $this->assertFalse($this->model->isUsed());
    }

    public function testCanBeDeleted()
    {
        $a = AnnotationLabelTest::create(['label_id' => $this->model->id]);
        $this->assertFalse($this->model->canBeDeleted());
    }
}
