<?php

namespace Biigle\Http\Controllers\Api;

use Biigle\Project;
use Biigle\LabelTree;
use Biigle\Visibility;
use Illuminate\Http\Request;
use Biigle\Http\Requests\StoreProjectLabelTree;
use Illuminate\Auth\Access\AuthorizationException;

class ProjectLabelTreeController extends Controller
{
    /**
     * Display all label trees used by the specified project.
     *
     * @api {get} projects/:id/label-trees Get all used label trees
     * @apiGroup Projects
     * @apiName IndexProjectLabelTrees
     * @apiPermission projectMember
     * @apiDescription This endpoint lists all label trees that are used by the project.
     *
     * @apiParam {Number} id The project ID.
     *
     * @apiSuccessExample {json} Success response:
     * [
     *    {
     *       "id": 1,
     *       "name": "Global",
     *       "description": "The global label tree",
     *       "labels": [
     *           {
     *               "id": 1,
     *               "name": "Trash",
     *               "color": "bada55",
     *               "parent_id": null,
     *               "label_tree_id": 1
     *           }
     *       ]
     *    },
     *    {
     *       "id": 2,
     *       "name": "Special",
     *       "description": "The project specific label tree",
     *       "labels": []
     *    }
     * ]
     *
     * @param int $id Project ID
     * @return \Illuminate\Http\Response
     */
    public function index($id)
    {
        $project = Project::findOrFail($id);
        $this->authorize('access', $project);

        return $project->labelTrees()
            ->select('id', 'name', 'description')
            ->with('labels')
            ->get();
    }

    /**
     * Display all label trees that can be used by the specified project.
     *
     * @api {get} projects/:id/label-trees/available Get all available label trees
     * @apiGroup Projects
     * @apiName IndexProjectAvailableLabelTrees
     * @apiPermission projectMember
     * @apiDescription This endpoint lists all label trees that _can be_ used by the project (do not confuse this with the "used label trees" endpoint).
     *
     * @apiParam {Number} id The project ID.
     *
     * @apiSuccessExample {json} Success response:
     * [
     *    {
     *       "id": 1,
     *       "name": "Global",
     *       "description": "The global label tree"
     *    },
     *    {
     *       "id": 2,
     *       "name": "Special",
     *       "description": "The project specific label tree"
     *    }
     * ]
     *
     * @param int $id Project ID
     * @return \Illuminate\Http\Response
     */
    public function available($id)
    {
        $project = Project::findOrFail($id);
        $this->authorize('access', $project);

        $public = LabelTree::publicTrees()
            ->select('id', 'name', 'description')->get();
        $authorized = $project->authorizedLabelTrees()
            ->select('id', 'name', 'description')->get();

        return $public->merge($authorized)->unique('id')->all();
    }

    /**
     * Adds a label tree to the specified project.
     *
     * @api {post} projects/:id/label-trees Add a label tree
     * @apiGroup Projects
     * @apiName AttachProjectLabelTrees
     * @apiPermission projectAdmin
     * @apiDescription The label tree must be either public or have the project authorized to use it.
     *
     * @apiParam {Number} id The project ID
     *
     * @apiParam (Required attributes) {Number} id The label tree ID
     *
     * @apiParamExample {String} Request example:
     * id: 3
     *
     * @param StoreProjectLabelTree $request
     * @return \Illuminate\Http\Response
     */
    public function store(StoreProjectLabelTree $request)
    {
        $request->project->labelTrees()->syncWithoutDetaching([$request->input('id')]);

        if (static::isAutomatedRequest($request)) {
            return;
        }

        if ($request->has('_redirect')) {
            return redirect($request->input('_redirect'))
                ->with('saved', true);
        }

        return redirect()->back()
            ->with('saved', true);
    }

    /**
     * Removes a label tree form the specified project.
     *
     * @api {delete} projects/:pid/label-trees/:lid Remove a label tree
     * @apiGroup Projects
     * @apiName DetachProjectLabelTrees
     * @apiPermission projectAdmin
     *
     * @apiParam {Number} pid The project ID.
     * @apiParam {Number} lid The label tree ID.
     *
     * @param Request $request
     * @param  int  $pid
     * @param  int  $lid
     * @return \Illuminate\Http\Response
     */
    public function destroy(Request $request, $pid, $lid)
    {
        $project = Project::findOrFail($pid);
        $this->authorize('update', $project);
        $count = $project->labelTrees()->detach($lid);

        if (static::isAutomatedRequest($request)) {
            return;
        }

        if ($request->has('_redirect')) {
            return redirect($request->input('_redirect'))
                ->with('deleted', $count > 0);
        }

        return redirect()->back()
            ->with('deleted', $count > 0);
    }
}
