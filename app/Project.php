<?php

namespace Biigle;

use DB;
use Cache;
use Biigle\Jobs\DeleteVolume;
use Biigle\Modules\Videos\Video;
use Illuminate\Database\Eloquent\Model;

class Project extends Model
{
    /**
     * The attributes hidden from the model's JSON form.
     *
     * @var array
     */
    protected $hidden = [
        'pivot',
    ];

    /**
     * Scope a query to all projects that the user and the volume with the given ID have in common.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param User $user
     * @param int $volumeId
     * @param array $roles Array of role IDs to restrict the project membership to. Default is any role.
     *
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeInCommon($query, User $user, $volumeId, $roles = null)
    {
        return $query->whereExists(function ($query) use ($user, $volumeId, $roles) {
            $query->select(DB::raw(1))
                ->from('project_user')
                ->join('project_volume', 'project_user.project_id', '=', 'project_volume.project_id')
                ->whereRaw('project_user.project_id = projects.id')
                ->where('project_user.user_id', $user->id)
                ->when(is_array($roles), function ($query) use ($roles) {
                    return $query->whereIn('project_user.project_role_id', $roles);
                })
                ->where('project_volume.volume_id', $volumeId);
        });
    }

    /**
     * The members of this project. Every member has a project-specific
     * `project_role_id` besides their global user role.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function users()
    {
        return $this->belongsToMany(User::class)
            ->withPivot('project_role_id as project_role_id');
    }

    /**
     * All members of this project with the `admin` role.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function admins()
    {
        return $this->users()->whereProjectRoleId(Role::adminId());
    }

    /**
     * All members of this project with the `editor` role.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function editors()
    {
        return $this->users()->whereProjectRoleId(Role::editorId());
    }

    /**
     * All members of this project with the `guest` role.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function guests()
    {
        return $this->users()->whereProjectRoleId(Role::guestId());
    }

    /**
     * The user that created this project. On creation this user is
     * automatically added to the project's users with the 'admin' role by
     * the ProjectObserver.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function creator()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Adds the user with the given role to this project.
     *
     * @param int $userId
     * @param int $roleId
     * @return void
     */
    public function addUserId($userId, $roleId)
    {
        $this->users()->attach($userId, ['project_role_id' => $roleId]);
    }

    /**
     * Changes the role of an existing user in this project.
     *
     * @param int $userId
     * @param int $roleId
     * @return void
     */
    public function changeRole($userId, $roleId)
    {
        $this->users()->updateExistingPivot($userId, ['project_role_id' => $roleId]);
    }

    /**
     * Determines if the user can be removed from the project.
     *
     * @param int $userId
     * @return  bool
     */
    public function userCanBeRemoved($userId)
    {
        return $this->admins()->where('id', '!=', $userId)->exists();
    }

    /**
     * Removes the user by ID from this project.
     *
     * @param int $userId
     * @return bool
     */
    public function removeUserId($userId)
    {
        if ($this->userCanBeRemoved($userId)) {
            return (boolean) $this->users()->detach($userId);
        }

        return false;
    }

    /**
     * The volumes of this project.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function volumes()
    {
        return $this->belongsToMany(Volume::class);
    }

    /**
     * Adds a volume to this project if it wasn't already.
     *
     * @deprecated Use `$project->volumes()->attach($id)` instead.
     * @param int $id
     * @return void
     */
    public function addVolumeId($id)
    {
        $this->volumes()->syncWithoutDetaching($id);
        // Maybe we get a new thumbnail now.
        Cache::forget("project-thumbnail-url-{$this->id}");
    }

    /**
     * Detaches the volume from this project. Fails if this is the last
     * project, the volume is attached to, unless force is `true`.
     *
     * @param Volume $volume
     * @param bool $force Delete the volume completely if this is the last
     * project it belongs to
     */
    public function removeVolume($volume, $force = false)
    {
        if (!$volume) {
            // nothing to remove
            return;
        }

        // this is the last project the volume belongs to, so it should be
        // deleted
        if ($volume->projects()->count() === 1) {
            // but delete the volume only with force!
            if (!$force) {
                abort(400, 'The volume would not belong to any project after detaching. Use the "force" argument to detach and delete it.');
            }

            DeleteVolume::dispatch($volume);
        }

        // if the volume still belongs to other projects, just detach it
        $this->volumes()->detach($volume->id);
        // Maybe we get a new thumbnail now.
        Cache::forget("project-thumbnail-url-{$this->id}");
    }

    /**
     * Detaches all volumes from this project. Fails if this is the last
     * project, one of the volumes is attached to, unless force is `true`.
     *
     * @param bool $force
     */
    public function removeAllVolumes($force = false)
    {
        $volumes = $this->volumes;

        if (!$force) {
            foreach ($volumes as $volume) {
                if ($volume->projects()->count() === 1) {
                    abort(400, 'One volume would not belong to any project after detaching. Use the "force" argument or detach and delete it first.');
                }
            }
        }

        foreach ($volumes as $volume) {
            $this->removeVolume($volume, $force);
        }
        Cache::forget("project-thumbnail-url-{$this->id}");
    }

    /**
     * The label trees, this project is using.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function labelTrees()
    {
        return $this->belongsToMany(LabelTree::class);
    }

    /**
     * The private label trees that authorized this project to use them.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function authorizedLabelTrees()
    {
        return $this->belongsToMany(LabelTree::class, 'label_tree_authorized_project');
    }

    /**
     * URL to a unique thumbnail image for this project.
     *
     * @return string
     */
    public function getThumbnailUrlAttribute()
    {
        return Cache::remember("project-thumbnail-url-{$this->id}", 3600, function () {
            $volume = $this->volumes()
                ->select('id')
                ->orderBy('id')
                ->first();

            if ($volume) {
                return $volume->thumbnailUrl;
            } elseif (class_exists(Video::class)) {
                $video = Video::where('project_id', $this->id)->first();

                if ($video) {
                    return $video->thumbnailUrl;
                }
            }

            return null;
        });
    }

    /**
     * Check if the project has volumes which have some images with GPS coordinates.
     *
     * @return bool
     */
    public function hasGeoInfo()
    {
        return Cache::remember("project-{$this->id}-has-geo-info", 3600, function () {
            return Image::whereIn('volume_id', function ($query) {
                return $query->select('volume_id')
                    ->from('project_volume')
                    ->where('project_id', $this->id);
            })
            ->whereNotNull('lng')
            ->whereNotNull('lat')
            ->exists();
        });
    }

    /**
     * Flush the cached information if this project has volumes which have images with
     * GPS coordinates.
     */
    public function flushGeoInfoCache()
    {
        Cache::forget("project-{$this->id}-has-geo-info");
    }
}
