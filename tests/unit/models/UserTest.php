<?php

use Dias\User;
use Dias\Role;

class UserTest extends ModelWithAttributesTest
{
    public static function create($fn = 'joe', $ln = 'user', $pw = 'pw', $mail = false)
    {
        $user = new User;
        $user->firstname = $fn;
        $user->lastname = $ln;
        $user->password = bcrypt($pw);
        $user->email = ($mail) ? $mail : str_random(10);

        return $user;
    }

    private $user;

    public function setUp()
    {
        parent::setUp();
        $this->user = self::create();
    }

    public function testCreation()
    {
        $this->assertTrue($this->user->save());
    }

    public function testAttributes()
    {
        $this->user->save();
        $this->assertNotNull($this->user->firstname);
        $this->assertNotNull($this->user->lastname);
        $this->assertNotNull($this->user->name);
        $this->assertNotNull($this->user->password);
        $this->assertNotNull($this->user->email);
        $this->assertNotNull($this->user->role_id);
        $this->assertNotNull($this->user->created_at);
        $this->assertNotNull($this->user->updated_at);
    }

    public function testFirstnameRequired()
    {
        $this->user->firstname = null;
        $this->setExpectedException('Illuminate\Database\QueryException');
        $this->user->save();
    }

    public function testLastnameRequired()
    {
        $this->user->lastname = null;
        $this->setExpectedException('Illuminate\Database\QueryException');
        $this->user->save();
    }

    public function testPasswordRequired()
    {
        $this->user->password = null;
        $this->setExpectedException('Illuminate\Database\QueryException');
        $this->user->save();
    }

    public function testEmailRequired()
    {
        $this->user->email = null;
        $this->setExpectedException('Illuminate\Database\QueryException');
        $this->user->save();
    }

    public function testEmailUnique()
    {
        $this->user = self::create('joe', 'user', 'pw', 'test@test.com');
        $this->user->save();
        $this->user = self::create('joe', 'user', 'pw', 'test@test.com');
        $this->setExpectedException('Illuminate\Database\QueryException');
        $this->user->save();
    }

    public function testProjects()
    {
        $project = ProjectTest::create();
        $project->save();
        $this->user->save();
        $role = RoleTest::create();
        $role->save();
        $project->addUserId($this->user->id, $role->id);

        $this->assertEquals($this->user->projects()->first()->id, $project->id);
    }

    public function testRole()
    {
        $this->user->save();
        $role = $this->user->role;
        $this->assertNotNull($role);
        $this->assertEquals(Role::editorId(), $role->id);
    }

    public function testIsAdmin()
    {
        $this->user->save();
        $this->assertFalse($this->user->isAdmin);
        $this->user->role()->associate(Role::admin());
        $this->assertTrue($this->user->isAdmin);
    }

    public function testHiddenAttributes()
    {
        // API key mustn't show up in the JSON
        $this->user->generateAPIKey();
        $jsonUser = json_decode((string) $this->user);
        $this->assertObjectNotHasAttribute('firstname', $jsonUser);
        $this->assertObjectNotHasAttribute('lastname', $jsonUser);
        $this->assertObjectNotHasAttribute('password', $jsonUser);
        $this->assertObjectNotHasAttribute('email', $jsonUser);
        $this->assertObjectNotHasAttribute('remember_token', $jsonUser);
        $this->assertObjectNotHasAttribute('created_at', $jsonUser);
        $this->assertObjectNotHasAttribute('updated_at', $jsonUser);
        $this->assertObjectNotHasAttribute('login_at', $jsonUser);
        $this->assertObjectNotHasAttribute('api_key', $jsonUser);
    }

    public function testApiKey()
    {
        $this->user->save();
        $this->assertNull($this->user->api_key);
        $key = $this->user->generateApiKey();
        $this->user->save();
        $this->assertEquals($key, $this->user->fresh()->api_key);
    }

    public function testCanSeeOneOfProjects()
    {
        $this->user->save();
        $project = ProjectTest::create();
        $project->save();
        $projectIds = [$project->id];
        $this->assertFalse($this->user->canSeeOneOfProjects($projectIds));
        $project->addUserId($this->user->id, Role::guestId());
        Cache::flush();
        $this->assertTrue($this->user->canSeeOneOfProjects($projectIds));
        $project->changeRole($this->user->id, Role::editorId());
        Cache::flush();
        $this->assertTrue($this->user->canSeeOneOfProjects($projectIds));
        $project->changeRole($this->user->id, Role::adminId());
        Cache::flush();
        $this->assertTrue($this->user->canSeeOneOfProjects($projectIds));
    }

    public function testCanEditInOneOfProjects()
    {
        $this->user->save();
        $project = ProjectTest::create();
        $project->save();
        $projectIds = [$project->id];
        $this->assertFalse($this->user->canEditInOneOfProjects($projectIds));
        $project->addUserId($this->user->id, Role::guestId());
        Cache::flush();
        $this->assertFalse($this->user->canEditInOneOfProjects($projectIds));
        $project->changeRole($this->user->id, Role::editorId());
        Cache::flush();
        $this->assertTrue($this->user->canEditInOneOfProjects($projectIds));
        $project->changeRole($this->user->id, Role::adminId());
        Cache::flush();
        $this->assertTrue($this->user->canEditInOneOfProjects($projectIds));
    }

    public function testCanAdminOneOfProjects()
    {
        $this->user->save();
        $project = ProjectTest::create();
        $project->save();
        $projectIds = [$project->id];
        $this->assertFalse($this->user->canAdminOneOfProjects($projectIds));
        $project->addUserId($this->user->id, Role::guestId());
        Cache::flush();
        $this->assertFalse($this->user->canAdminOneOfProjects($projectIds));
        $project->changeRole($this->user->id, Role::editorId());
        Cache::flush();
        $this->assertFalse($this->user->canAdminOneOfProjects($projectIds));
        $project->changeRole($this->user->id, Role::adminId());
        Cache::flush();
        $this->assertTrue($this->user->canAdminOneOfProjects($projectIds));
    }
}
