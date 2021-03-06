<?php

namespace Biigle\Http\Controllers\Views\Admin;

use Biigle\User;
use Carbon\Carbon;
use Biigle\Services\Modules;
use Biigle\Http\Controllers\Controller;

class IndexController extends Controller
{
    /**
     * Shows the admin dashboard.
     *
     * @param Modules $modules
     * @return \Illuminate\Http\Response
     */
    public function get(Modules $modules)
    {
        $users = User::select('login_at')->get();
        $allUsers = $users->count();
        $loginUsers = $users->where('login_at', '!=', null)->count();
        $activeUsersLastMonth = $users->where('login_at', '>', Carbon::now()->subMonth())->count();
        $activeUsersLastWeek = $users->where('login_at', '>', Carbon::now()->subWeek())->count();
        $activeUsersLastDay = $users->where('login_at', '>', Carbon::now()->subDay())->count();

        $installedModules = $modules->getInstalledModules();

        return view('admin.index', compact(
            'allUsers',
            'loginUsers',
            'activeUsersLastMonth',
            'activeUsersLastWeek',
            'activeUsersLastDay',
            'installedModules'
        ));
    }
}
