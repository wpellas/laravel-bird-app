<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Follow;
use Illuminate\Http\Request;

class FollowController extends Controller
{
    public function createFollow(User $user) {
        // you cannot follow yourself
        if ($user->id === auth()->user()->id) {
            return back()->with('failure', 'You cannot follow yourself.');
        }
        // you cannot follow someone you're already following
        // checks to see if the current user already has the $user id in their followeduser table
        $existCheck = Follow::where([
            ['user_id', '=', auth()->user()->id],
            ['followeduser', '=', $user->id]
            ])->count();
        if ($existCheck) {
            return back()->with('failure', 'You are already following this user.');
        }

        $newFollow = new Follow; // Don't need mass assignment since we're not making an array
        $newFollow->user_id = auth()->user()->id; // whatever user is currently logged in is the one creating the follow
        $newFollow->followeduser = $user->id; // the incoming users id
        $newFollow->save(); // save to db

        return back()->with('success', "You followed $user->username.");
    }

    public function removeFollow(User $user) {
        Follow::where([ //checks the follow table
            ['user_id', '=', auth()->user()->id], // checks if the column user_id has the same entry as the current user session id
            ['followeduser', '=', $user->id] // checks if the followeduser is the same as the user page id
        ])->delete(); // deletes the entry
        return back()->with('success', "$user->username has been unfollowed.");
    }
}
