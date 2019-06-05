<?php

namespace Biigle\Tests;

use Exception;
use Biigle\Role;
use ModelTestCase;
use Biigle\LabelTree;
use Biigle\Visibility;
use Illuminate\Database\QueryException;
use Symfony\Component\HttpKernel\Exception\HttpException;

class LabelTreeTest extends ModelTestCase
{
    /**
     * The model class this class will test.
     */
    protected static $modelClass = LabelTree::class;

    public function testAttributes()
    {
        $this->assertNotNull($this->model->name);
        $this->assertNotNull($this->model->description);
        $this->assertNotNull($this->model->created_at);
        $this->assertNotNull($this->model->updated_at);
        $this->assertNotNull($this->model->uuid);
    }

    public function testNameRequired()
    {
        $this->model->name = null;
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

    public function testVisibilityOnDeleteRestrict()
    {
        $this->expectException(QueryException::class);
        $this->model->visibility()->delete();
    }

    public function testMembers()
    {
        $user = UserTest::create();
        $this->model->members()->attach($user->id, ['role_id' => Role::adminId()]);
        $this->assertNotNull($this->model->members()->find($user->id));
    }

    public function testLabels()
    {
        $this->assertFalse($this->model->labels()->exists());
        LabelTest::create(['label_tree_id' => $this->model->id]);
        $this->assertTrue($this->model->labels()->exists());
    }

    public function testCanBeDeletedAnnotationLabel()
    {
        $this->assertTrue($this->model->canBeDeleted());
        $label = LabelTest::create(['label_tree_id' => $this->model->id]);
        $this->assertTrue($this->model->canBeDeleted());
        AnnotationLabelTest::create(['label_id' => $label->id]);
        $this->assertFalse($this->model->canBeDeleted());
    }

    public function testCanBeDeletedImageLabel()
    {
        $this->assertTrue($this->model->canBeDeleted());
        $label = LabelTest::create(['label_tree_id' => $this->model->id]);
        $this->assertTrue($this->model->canBeDeleted());
        ImageLabelTest::create(['label_id' => $label->id]);
        $this->assertFalse($this->model->canBeDeleted());
    }

    public function testAddMember()
    {
        $this->assertFalse($this->model->members()->exists());
        $this->model->addMember(UserTest::create(), Role::admin());
        $this->assertEquals(Role::adminId(), $this->model->members()->first()->role_id);
    }

    public function testAddMemberUserExists()
    {
        $user = UserTest::create();
        $this->model->addMember($user, Role::admin());
        $this->expectException(QueryException::class);
        $this->model->addMember($user, Role::admin());
    }

    public function testMemberCanBeRemoved()
    {
        $editor = UserTest::create();
        $admin = UserTest::create();
        $this->model->addMember($admin, Role::admin());
        $this->model->addMember($editor, Role::editor());
        $this->assertFalse($this->model->memberCanBeRemoved($admin));
        $this->assertTrue($this->model->memberCanBeRemoved($editor));
        $this->model->addMember(UserTest::create(), Role::admin());
        $this->assertTrue($this->model->memberCanBeRemoved($admin));
    }

    public function testUpdateMember()
    {
        $user = UserTest::create();
        $this->model->addMember($user, Role::editor());
        $this->assertEquals(Role::editorId(), $this->model->members()->first()->role_id);
        $this->model->updateMember($user, Role::admin());
        $this->assertEquals(Role::adminId(), $this->model->members()->first()->role_id);
    }

    public function testProjects()
    {
        // label trees without users are attached by default
        $project = ProjectTest::create();
        $this->assertNotNull($this->model->projects()->find($project->id));
    }

    public function testAuthorizedProjects()
    {
        $project = ProjectTest::create();
        $this->model->authorizedProjects()->attach($project->id);
        $this->assertNotNull($this->model->authorizedProjects()->find($project->id));
    }

    public function testPublicScope()
    {
        $public = static::create(['visibility_id' => Visibility::publicId()]);
        $private = static::create(['visibility_id' => Visibility::privateId()]);

        $ids = LabelTree::publicTrees()->pluck('id');
        $this->assertContains($public->id, $ids);
        $this->assertNotContains($private->id, $ids);
    }

    public function testPrivateScope()
    {
        $public = static::create(['visibility_id' => Visibility::publicId()]);
        $private = static::create(['visibility_id' => Visibility::privateId()]);

        $ids = LabelTree::privateTrees()->pluck('id');
        $this->assertContains($private->id, $ids);
        $this->assertNotContains($public->id, $ids);
    }

    public function testDetachUnauthorizedProjects()
    {
        $tree = self::create();
        $unauthorized = ProjectTest::create();
        $authorized = ProjectTest::create();
        // label trees without users are attached by default
        $tree->authorizedProjects()->attach($authorized->id);
        $tree->detachUnauthorizedProjects();
        $this->assertEquals([$authorized->id], array_map('intval', $tree->projects()->pluck('id')->all()));
    }

    public function testOrderLabelsByName()
    {
        LabelTest::create([
            'label_tree_id' => $this->model->id,
            'name' => 'z',
        ]);
        LabelTest::create([
            'label_tree_id' => $this->model->id,
            'name' => 'a',
        ]);

        $this->assertEquals('a', $this->model->labels()->first()->name);
    }

    public function testScopeAccessibleBy()
    {
        $this->model->delete();
        $user = UserTest::create();
        $tree = self::create(['visibility_id' => Visibility::publicId()]);
        $tree2 = self::create(['visibility_id' => Visibility::privateId()]);
        $tree3 = self::create(['visibility_id' => Visibility::privateId()]);

        $ids = LabelTree::accessibleBy($user)->pluck('id')->toArray();
        $this->assertEquals([$tree->id], $ids);

        $tree2->addMember($user, Role::editor());

        $ids = LabelTree::accessibleBy($user)->pluck('id')->toArray();
        $this->assertEquals([$tree->id, $tree2->id], $ids);

        $project = ProjectTest::create(['creator_id' => $user->id]);
        $project->labelTrees()->attach($tree3);

        $ids = LabelTree::accessibleBy($user)->pluck('id')->toArray();
        $this->assertEquals([$tree->id, $tree2->id, $tree3->id], $ids);
    }

    public function testScopeAccessibleByAdmin()
    {
        $user = UserTest::create(['role_id' => Role::adminId()]);
        $tree = self::create(['visibility_id' => Visibility::privateId()]);

        $ids = LabelTree::accessibleBy($user)->pluck('id')->toArray();
        $this->assertContains($tree->id, $ids);
    }

    public function testVersion()
    {
        $version = LabelTreeVersionTest::create();
        $this->assertFalse($this->model->version()->exists());
        $this->model->version_id = $version->id;
        $this->assertTrue($this->model->version()->exists());
    }

    public function testVersions()
    {
        $this->assertEquals(0, $this->model->versions()->count());
        $version = LabelTreeVersionTest::create(['label_tree_id' => $this->model->id]);
        $this->assertEquals(1, $this->model->versions()->count());
    }

    public function testCascadeDeleteMaster()
    {
        $version = LabelTreeVersionTest::create();
        $this->model->version_id = $version->id;
        $this->model->save();
        $version->labelTree()->delete();
        $this->assertNull($version->fresh());
        $this->assertNull($this->model->fresh());
    }

    public function testRestrictDeleteMasterIfLabelIsUsed()
    {
        $version = LabelTreeVersionTest::create();
        $this->model->version_id = $version->id;
        $this->model->save();
        AnnotationLabelTest::create([
            'label_id' => LabelTest::create(['label_tree_id' => $this->model->id])
        ]);
        $this->expectException(QueryException::class);
        $version->labelTree()->delete();
    }
}
