<?php

namespace Dias\Http\Controllers\Views;

class SettingsController extends Controller
{
    /**
     * Redirects to the profile settings.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        return redirect()->route('settings-profile');
    }

    /**
     * Shows the profile settings.
     *
     * @return \Illuminate\Http\Response
     */
    public function profile()
    {
        return view('settings.profile')
            ->withUser($this->user)
            ->withSaved(session('saved'));
    }

    /**
     * Shows the account settings.
     *
     * @return \Illuminate\Http\Response
     */
    public function account()
    {
        return view('settings.account')
            ->withUser($this->user)
            ->withSaved(session('saved'));
    }

    /**
     * Shows the tokens settings.
     *
     * @return \Illuminate\Http\Response
     */
    public function tokens()
    {
        return view('settings.tokens')
            ->withUser($this->user)
            ->withGenerated(session('generated'))
            ->withDeleted(session('deleted'));
    }
}
