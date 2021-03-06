<?php

namespace Biigle\Tests;

use File;
use Event;
use Cache;
use Exception;
use Biigle\Role;
use Biigle\Image;
use ModelTestCase;
use Biigle\Volume;
use Carbon\Carbon;
use Biigle\Events\ImagesDeleted;
use Biigle\Events\TiledImagesDeleted;
use Illuminate\Database\QueryException;
use Symfony\Component\HttpKernel\Exception\HttpException;

class VolumeTest extends ModelTestCase
{
    /**
     * The model class this class will test.
     */
    protected static $modelClass = Volume::class;

    public function testAttributes()
    {
        $this->assertNotNull($this->model->name);
        $this->assertNotNull($this->model->url);
        $this->assertNotNull($this->model->media_type_id);
        $this->assertNotNull($this->model->creator_id);
        $this->assertNotNull($this->model->created_at);
        $this->assertNotNull($this->model->updated_at);
    }

    public function testNameRequired()
    {
        $this->model->name = null;
        $this->expectException(QueryException::class);
        $this->model->save();
    }

    public function testUrlRequired()
    {
        $this->model->url = null;
        $this->expectException(QueryException::class);
        $this->model->save();
    }

    public function testMediaTypeRequired()
    {
        $this->model->mediaType()->dissociate();
        $this->expectException(QueryException::class);
        $this->model->save();
    }

    public function testMediaTypeOnDeleteRestrict()
    {
        $this->expectException(QueryException::class);
        $this->model->mediaType()->delete();
    }

    public function testCreatorOnDeleteSetNull()
    {
        $this->model->creator()->delete();
        $this->assertNull($this->model->fresh()->creator_id);
    }

    public function testImages()
    {
        $image = ImageTest::create(['volume_id' => $this->model->id]);
        $this->assertEquals($image->id, $this->model->images()->first()->id);
    }

    public function testProjects()
    {
        $project = ProjectTest::create();
        $this->assertEquals(0, $this->model->projects()->count());
        $project->volumes()->attach($this->model);
        $this->assertEquals(1, $this->model->projects()->count());
    }

    public function testSetMediaType()
    {
        $type = MediaTypeTest::create();
        $this->assertNotEquals($type->id, $this->model->mediaType->id);
        $this->model->setMediaType($type);
        $this->assertEquals($type->id, $this->model->mediaType->id);
    }

    public function testSetMediaTypeId()
    {
        $type = MediaTypeTest::create();
        $this->assertNotEquals($type->id, $this->model->mediaType->id);
        $this->model->setMediaTypeId($type->id);
        $this->assertEquals($type->id, $this->model->mediaType->id);

        // media type does not exist
        $this->expectException(HttpException::class);
        $this->model->setMediaTypeId(99999);
    }

    public function testCastsAttrs()
    {
        $this->model->attrs = [1, 2, 3];
        $this->model->save();
        $this->assertEquals([1, 2, 3], $this->model->fresh()->attrs);
    }

    public function testParseImagesQueryString()
    {
        $return = Volume::parseImagesQueryString('');
        $this->assertEquals([], $return);

        $return = Volume::parseImagesQueryString(', 1.jpg , , 2.jpg, , , ');
        $this->assertEquals(['1.jpg', '2.jpg'], $return);

        $return = Volume::parseImagesQueryString(' 1.jpg ');
        $this->assertEquals(['1.jpg'], $return);
    }

    public function testImagesDeletedEventOnDelete()
    {
        Event::fake([ImagesDeleted::class]);

        $image = ImageTest::create(['volume_id' => $this->model->id]);
        $this->model->delete();

        Event::assertDispatched(ImagesDeleted::class, function ($event) use ($image) {
            return $event->uuids[0] === $image->uuid;
        });
    }

    public function testTiledImagesDeletedEventOnDelete()
    {
        Event::fake([ImagesDeleted::class, TiledImagesDeleted::class]);

        $image = ImageTest::create(['volume_id' => $this->model->id, 'tiled' => true]);
        $this->model->delete();

        Event::assertDispatched(ImagesDeleted::class, function ($event) use ($image) {
            return $event->uuids[0] === $image->uuid;
        });
        Event::assertDispatched(TiledImagesDeleted::class, function ($event) use ($image) {
            return $event->uuids[0] === $image->uuid;
        });
    }

    public function testAnnotationSessions()
    {
        $this->assertFalse($this->model->annotationSessions()->exists());
        $session = AnnotationSessionTest::create(['volume_id' => $this->model->id]);
        $this->assertTrue($this->model->annotationSessions()->exists());
    }

    public function testActiveAnnotationSession()
    {
        $active = AnnotationSessionTest::create([
            'volume_id' => $this->model->id,
            'starts_at' => Carbon::yesterday(),
            'ends_at' => Carbon::tomorrow(),
        ]);

        AnnotationSessionTest::create([
            'volume_id' => $this->model->id,
            'starts_at' => Carbon::yesterday()->subDay(),
            'ends_at' => Carbon::yesterday(),
        ]);

        $this->assertEquals($active->id, $this->model->activeAnnotationSession->id);
    }

    public function testHasConflictingAnnotationSession()
    {
        $a = AnnotationSessionTest::create([
            'volume_id' => $this->model->id,
            'starts_at' => '2016-09-04',
            'ends_at' => '2016-09-07',
        ]);

        $this->assertFalse($this->model->hasConflictingAnnotationSession($a));

        /*
         * |--a--|
         *  |-b-|
         */
        $b = AnnotationSessionTest::make([
            'volume_id' => $this->model->id,
            'starts_at' => '2016-09-05',
            'ends_at' => '2016-09-06',
        ]);

        $this->assertTrue($this->model->hasConflictingAnnotationSession($b));

        /*
         *   |--a--|
         * |-b-|
         */
        $b = AnnotationSessionTest::make([
            'volume_id' => $this->model->id,
            'starts_at' => '2016-09-03',
            'ends_at' => '2016-09-05',
        ]);

        $this->assertTrue($this->model->hasConflictingAnnotationSession($b));

        /*
         * |--a--|
         *     |-b-|
         */
        $b = AnnotationSessionTest::make([
            'volume_id' => $this->model->id,
            'starts_at' => '2016-09-06',
            'ends_at' => '2016-09-08',
        ]);

        $this->assertTrue($this->model->hasConflictingAnnotationSession($b));

        /*
         *  |--a--|
         * |---b---|
         */
        $b = AnnotationSessionTest::make([
            'volume_id' => $this->model->id,
            'starts_at' => '2016-09-03',
            'ends_at' => '2016-09-08',
        ]);

        $this->assertTrue($this->model->hasConflictingAnnotationSession($b));

        /*
         *     |--a--|
         * |-b-|
         */
        $b = AnnotationSessionTest::make([
            'volume_id' => $this->model->id,
            'starts_at' => '2016-09-03',
            'ends_at' => '2016-09-04',
        ]);

        $this->assertFalse($this->model->hasConflictingAnnotationSession($b));

        /*
         * |--a--|
         *       |-b-|
         */
        $b = AnnotationSessionTest::make([
            'volume_id' => $this->model->id,
            'starts_at' => '2016-09-07',
            'ends_at' => '2016-09-08',
        ]);

        $this->assertFalse($this->model->hasConflictingAnnotationSession($b));

        $b->save();
        $b = $b->fresh();
        // should not count the own annotation session (for updating)
        $this->assertFalse($this->model->hasConflictingAnnotationSession($b));
    }

    public function testUsers()
    {
        $editor = Role::editor();
        $u1 = UserTest::create();
        $u2 = UserTest::create();
        $u3 = UserTest::create();
        $u4 = UserTest::create();

        $p1 = ProjectTest::create();
        $p1->addUserId($u1, $editor->id);
        $p1->addUserId($u2, $editor->id);
        $p1->volumes()->attach($this->model);

        $p2 = ProjectTest::create();
        $p2->addUserId($u2, $editor->id);
        $p2->addUserId($u3, $editor->id);
        $p2->volumes()->attach($this->model);

        $users = $this->model->users()->get();
        // project creators are counted, too
        $this->assertEquals(5, $users->count());
        $this->assertEquals(1, $users->where('id', $u1->id)->count());
        $this->assertEquals(1, $users->where('id', $u2->id)->count());
        $this->assertEquals(1, $users->where('id', $u3->id)->count());
        $this->assertEquals(0, $users->where('id', $u4->id)->count());
    }

    public function testIsRemote()
    {
        $t = static::create(['url' => '/local/path']);
        $this->assertFalse($t->isRemote());
        $t->url = 'http://remote.path';
        $this->assertTrue($t->isRemote());
        $t->url = 'https://remote.path';
        $this->assertTrue($t->isRemote());
    }

    public function testOrderedImages()
    {
        ImageTest::create([
            'filename' => 'b.jpg',
            'volume_id' => $this->model->id,
        ]);
        ImageTest::create([
            'filename' => 'a.jpg',
            'volume_id' => $this->model->id,
        ]);
        $this->assertEquals('a.jpg', $this->model->orderedImages()->first()->filename);
    }

    public function testGetThumbnailAttributeNull()
    {
        $this->assertEquals(null, $this->model->thumbnail);
    }

    public function testGetThumbnailAttribute()
    {
        $i1 = ImageTest::create([
            'filename' => 'a.jpg',
            'volume_id' => $this->model->id,
        ]);
        $i2 = ImageTest::create([
            'filename' => 'b.jpg',
            'volume_id' => $this->model->id,
        ]);
        $i3 = ImageTest::create([
            'filename' => 'c.jpg',
            'volume_id' => $this->model->id,
        ]);

        // Should be the middle image ordered by name.
        $this->assertEquals($i2->uuid, $this->model->thumbnail->uuid);

        // If the thumbnail is deleted, purge the cache so a new thumbnail is selected.
        $i2->delete();
        $this->assertEquals($i1->uuid, $this->model->thumbnail->uuid);
    }

    public function testHasGeoInfo()
    {
        $this->assertFalse($this->model->hasGeoInfo());
        ImageTest::create([
            'lng' => 5.5,
            'lat' => 5.5,
            'volume_id' => $this->model->id,
        ]);
        $this->assertFalse($this->model->hasGeoInfo());
        $this->model->flushGeoInfoCache();
        $this->assertTrue($this->model->hasGeoInfo());
    }

    public function testFlushGeoInfoCacheProjects()
    {
        $p = ProjectTest::create();
        $p->volumes()->attach($this->model);
        $this->assertFalse($this->model->hasGeoInfo());
        $this->assertFalse($p->hasGeoInfo());
        ImageTest::create([
            'lng' => 5.5,
            'lat' => 5.5,
            'volume_id' => $this->model->id,
        ]);
        $this->model->flushGeoInfoCache();
        $this->assertTrue($this->model->hasGeoInfo());
        $this->assertTrue($p->hasGeoInfo());
    }

    public function testLinkAttrs()
    {
        foreach (['video_link', 'gis_link'] as $attr) {
            $this->assertNull($this->model->$attr);

            $this->model->$attr = 'http://example.com';
            $this->model->save();
            $this->assertEquals('http://example.com', $this->model->fresh()->$attr);

            $this->model->$attr = null;
            $this->model->save();
            $this->assertNull($this->model->fresh()->$attr);
        }
    }

    public function testHasTiledImages()
    {
        ImageTest::create(['tiled' => false, 'volume_id' => $this->model->id]);
        $this->assertFalse($this->model->hasTiledImages());
        ImageTest::create(['tiled' => true, 'volume_id' => $this->model->id, 'filename' => 'abc']);
        $this->assertFalse($this->model->hasTiledImages());
        Cache::flush();
        $this->assertTrue($this->model->hasTiledImages());
    }

    public function testSetAndGetDoiAttribute()
    {
        $this->model->doi = '10.3389/fmars.2017.00083';
        $this->model->save();
        $this->assertEquals('10.3389/fmars.2017.00083', $this->model->fresh()->doi);

        $this->model->doi = 'https://doi.org/10.3389/fmars.2017.00083';
        $this->model->save();
        $this->assertEquals('10.3389/fmars.2017.00083', $this->model->fresh()->doi);

        $this->model->doi = 'http://doi.org/10.3389/fmars.2017.00083';
        $this->model->save();
        $this->assertEquals('10.3389/fmars.2017.00083', $this->model->fresh()->doi);
    }

    public function testScopeAccessibleBy()
    {
        $user = UserTest::create();
        $project = ProjectTest::create();
        $project->addUserId($user->id, Role::guestId());

        $ids = Volume::accessibleBy($user)->pluck('id');
        $this->assertEmpty($ids);

        $project->addVolumeId($this->model->id);

        $ids = Volume::accessibleBy($user)->pluck('id');
        $this->assertContains($this->model->id, $ids);
    }

    public function testSanitizeUrl()
    {
        $this->model->url = 'http://example.com/images/';
        $this->assertEquals('http://example.com/images', $this->model->url);
    }

    public function testGetThumbnailsAttribute()
    {
        $id = $this->model->id;
        for ($i = 0; $i < 11; $i++) {
            ImageTest::create(['volume_id' => $id, 'filename' => "file{$i}"]);
        }

        $thumbnails = $this->model->thumbnails;
        $this->assertCount(10, $thumbnails);
        $this->assertInstanceOf(Image::class, $thumbnails[0]);
    }

    public function testGetThumbnailUrlAttribute()
    {
        $this->assertNull($this->model->thumbnailUrl);

        $i = ImageTest::create([
            'filename' => 'a.jpg',
            'volume_id' => $this->model->id,
        ]);

        $this->model->flushThumbnailCache();
        $this->assertStringContainsString($i->uuid, $this->model->thumbnailUrl);
    }

    public function testGetThumbnailsUrlAttribute()
    {
        $this->assertEmpty($this->model->thumbnailsUrl);

        $i = ImageTest::create([
            'filename' => 'a.jpg',
            'volume_id' => $this->model->id,
        ]);

        $this->model->flushThumbnailCache();
        $this->assertStringContainsString($i->uuid, $this->model->thumbnailsUrl[0]);
    }
}
