<?php

namespace Biigle\Tests\Policies;

use Cache;
use TestCase;
use Biigle\Role;
use Carbon\Carbon;
use Biigle\Tests\UserTest;
use Biigle\Tests\LabelTest;
use Biigle\Tests\ImageTest;
use Biigle\Tests\ProjectTest;
use Biigle\Tests\VolumeTest;
use Biigle\Tests\AnnotationTest;
use Biigle\Tests\AnnotationLabelTest;
use Biigle\Tests\AnnotationSessionTest;

class AnnotationPolicyTest extends TestCase
{
    private $user;
    private $guest;
    private $admin;
    private $editor;
    private $project;
    private $annotation;
    private $globalAdmin;

    public function setUp()
    {
        parent::setUp();
        $this->image = ImageTest::create();
        $this->project = ProjectTest::create();
        $this->project->volumes()->attach($this->image->volume);
        $this->annotation = AnnotationTest::create([
            'image_id' => $this->image->id,
            'project_volume_id' => $this->project->volumes()->find($this->image->volume_id)->pivot->id,
        ]);
        $this->user = UserTest::create();
        $this->guest = UserTest::create();
        $this->editor = UserTest::create();
        $this->admin = UserTest::create();
        $this->project->addUserId($this->guest->id, Role::$guest->id);
        $this->project->addUserId($this->editor->id, Role::$editor->id);
        $this->project->addUserId($this->admin->id, Role::$admin->id);
        $this->globalAdmin = UserTest::create(['role_id' => Role::$admin->id]);

        $this->otherProject = ProjectTest::create();
        $this->otherProject->volumes()->attach($this->image->volume);
        $this->otherGuest = UserTest::create();
        $this->otherEditor = UserTest::create();
        $this->otherAdmin = UserTest::create();
        $this->otherProject->addUserId($this->otherGuest->id, Role::$guest->id);
        $this->otherProject->addUserId($this->otherEditor->id, Role::$editor->id);
        $this->otherProject->addUserId($this->otherAdmin->id, Role::$admin->id);

        $this->others = [$this->otherGuest, $this->otherEditor, $this->otherAdmin];
    }

    public function testAccess()
    {
        $this->assertFalse($this->user->can('access', $this->annotation));
        $this->assertTrue($this->guest->can('access', $this->annotation));
        $this->assertTrue($this->editor->can('access', $this->annotation));
        $this->assertTrue($this->admin->can('access', $this->annotation));
        $this->assertTrue($this->globalAdmin->can('access', $this->annotation));

        foreach ($this->others as $user) {
            $this->assertTrue($user->can('access', $this->annotation));
        }
    }

    public function testAccessAnnotationSession()
    {
        $this->annotation->created_at = Carbon::yesterday();
        $this->annotation->save();

        $session = AnnotationSessionTest::create([
            'volume_id' => $this->annotation->image->volume_id,
            'starts_at' => Carbon::today(),
            'ends_at' => Carbon::tomorrow(),
            'hide_own_annotations' => true,
            'hide_other_users_annotations' => true,
        ]);

        $this->assertFalse($this->user->can('access', $this->annotation));
        $this->assertTrue($this->guest->can('access', $this->annotation));
        $this->assertTrue($this->editor->can('access', $this->annotation));
        $this->assertTrue($this->admin->can('access', $this->annotation));
        $this->assertTrue($this->globalAdmin->can('access', $this->annotation));

        $session->users()->attach([
            $this->user->id,
            $this->guest->id,
            $this->editor->id,
            $this->admin->id,
            $this->globalAdmin->id,
        ]);
        Cache::flush();

        $this->assertFalse($this->user->can('access', $this->annotation));
        $this->assertFalse($this->guest->can('access', $this->annotation));
        $this->assertFalse($this->editor->can('access', $this->annotation));
        $this->assertFalse($this->admin->can('access', $this->annotation));
        $this->assertTrue($this->globalAdmin->can('access', $this->annotation));

        $this->markTestIncomplete('Update the annotation session behavior with project volumes');
    }

    public function testUpdate()
    {
        $this->assertFalse($this->user->can('update', $this->annotation));
        $this->assertFalse($this->guest->can('update', $this->annotation));
        $this->assertTrue($this->editor->can('update', $this->annotation));
        $this->assertTrue($this->admin->can('update', $this->annotation));
        $this->assertTrue($this->globalAdmin->can('update', $this->annotation));

        foreach ($this->others as $user) {
            $this->assertFalse($user->can('update', $this->annotation));
        }
    }

    public function testAttachLabel()
    {
        $allowedLabel = LabelTest::create();
        $this->project->labelTrees()->attach($allowedLabel->label_tree_id);

        $disallowedLabel = LabelTest::create();
        $otherDisallowedLabel = LabelTest::create();
        $this->otherProject->labelTrees()->attach($otherDisallowedLabel->label_tree_id);

        $this->assertFalse($this->user->can('attach-label', [$this->annotation, $allowedLabel]));
        $this->assertFalse($this->user->can('attach-label', [$this->annotation, $disallowedLabel]));
        $this->assertFalse($this->user->can('attach-label', [$this->annotation, $otherDisallowedLabel]));

        $this->assertFalse($this->guest->can('attach-label', [$this->annotation, $allowedLabel]));
        $this->assertFalse($this->guest->can('attach-label', [$this->annotation, $disallowedLabel]));
        $this->assertFalse($this->guest->can('attach-label', [$this->annotation, $otherDisallowedLabel]));

        $this->assertTrue($this->editor->can('attach-label', [$this->annotation, $allowedLabel]));
        $this->assertFalse($this->editor->can('attach-label', [$this->annotation, $disallowedLabel]));
        $this->assertFalse($this->editor->can('attach-label', [$this->annotation, $otherDisallowedLabel]));

        $this->assertTrue($this->admin->can('attach-label', [$this->annotation, $allowedLabel]));
        $this->assertFalse($this->admin->can('attach-label', [$this->annotation, $disallowedLabel]));
        $this->assertFalse($this->admin->can('attach-label', [$this->annotation, $otherDisallowedLabel]));

        $this->assertTrue($this->globalAdmin->can('attach-label', [$this->annotation, $allowedLabel]));
        $this->assertTrue($this->globalAdmin->can('attach-label', [$this->annotation, $disallowedLabel]));
        $this->assertTrue($this->globalAdmin->can('attach-label', [$this->annotation, $otherDisallowedLabel]));

        foreach ($this->others as $user) {
            $this->assertFalse($user->can('attach-label', [$this->annotation, $allowedLabel]));
            $this->assertFalse($user->can('attach-label', [$this->annotation, $disallowedLabel]));
            $this->assertFalse($user->can('attach-label', [$this->annotation, $otherDisallowedLabel]));
        }
    }

    public function testDestroy()
    {
        // Has a label of user.
        $a1 = AnnotationTest::create([
            'image_id' => $this->annotation->image_id,
            'project_volume_id' => $this->annotation->project_volume_id,
        ]);
        AnnotationLabelTest::create([
            'annotation_id' => $a1->id,
            'user_id' => $this->user->id,
        ]);

        // Has a label of guest.
        $a2 = AnnotationTest::create([
            'image_id' => $this->annotation->image_id,
            'project_volume_id' => $this->annotation->project_volume_id,
        ]);
        AnnotationLabelTest::create([
            'annotation_id' => $a2->id,
            'user_id' => $this->guest->id,
        ]);

        // Has a label of editor.
        $a3 = AnnotationTest::create([
            'image_id' => $this->annotation->image_id,
            'project_volume_id' => $this->annotation->project_volume_id,
        ]);
        AnnotationLabelTest::create([
            'annotation_id' => $a3->id,
            'user_id' => $this->editor->id,
        ]);

        // Has labels of editor and admin.
        $a4 = AnnotationTest::create([
            'image_id' => $this->annotation->image_id,
            'project_volume_id' => $this->annotation->project_volume_id,
        ]);
        AnnotationLabelTest::create([
            'annotation_id' => $a4->id,
            'user_id' => $this->editor->id,
        ]);
        AnnotationLabelTest::create([
            'annotation_id' => $a4->id,
            'user_id' => $this->admin->id,
        ]);

        $this->assertFalse($this->user->can('destroy', $a1));
        $this->assertFalse($this->user->can('destroy', $a2));
        $this->assertFalse($this->user->can('destroy', $a3));
        $this->assertFalse($this->user->can('destroy', $a4));

        $this->assertFalse($this->guest->can('destroy', $a1));
        $this->assertFalse($this->guest->can('destroy', $a2));
        $this->assertFalse($this->guest->can('destroy', $a3));
        $this->assertFalse($this->guest->can('destroy', $a4));

        $this->assertFalse($this->editor->can('destroy', $a1));
        $this->assertFalse($this->editor->can('destroy', $a2));
        $this->assertTrue($this->editor->can('destroy', $a3));
        // There is a label of another user attached.
        $this->assertFalse($this->editor->can('destroy', $a4));

        $this->assertTrue($this->admin->can('destroy', $a1));
        $this->assertTrue($this->admin->can('destroy', $a2));
        $this->assertTrue($this->admin->can('destroy', $a3));
        $this->assertTrue($this->admin->can('destroy', $a4));

        $this->assertTrue($this->globalAdmin->can('destroy', $a1));
        $this->assertTrue($this->globalAdmin->can('destroy', $a2));
        $this->assertTrue($this->globalAdmin->can('destroy', $a3));
        $this->assertTrue($this->globalAdmin->can('destroy', $a4));

        foreach ($this->others as $user) {
            $this->assertFalse($user->can('destroy', $a1));
            $this->assertFalse($user->can('destroy', $a2));
            $this->assertFalse($user->can('destroy', $a3));
            $this->assertFalse($user->can('destroy', $a4));
        }
    }
}
