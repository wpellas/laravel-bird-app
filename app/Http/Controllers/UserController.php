<?php

namespace App\Http\Controllers;

use App\Models\Post;
use App\Models\User;
use App\Models\Follow;
use Illuminate\Http\Request;
use App\Events\OurExampleEvent;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\Cache;
use Intervention\Image\Facades\Image;
use Illuminate\Support\Facades\Storage;

class UserController extends Controller
{
    public function showCorrectHomepage() {
        if (auth()->check()) {
            return view('homepage-feed', [
                'posts' => auth()->user()->feedPosts()->latest()->paginate(4)
            ]);
        } else {
            $postCount = Cache::remember('postCount', 20, function () {
                return Post::count();
            });
            return view('homepage', [
                'postCount' => $postCount
            ]);
        }
    }

    public function register(Request $request) {
        $incomingFields = $request->validate([
            'username' => ['required', 'min:3', 'max:20', Rule::unique('users', 'username')],
            'email' => ['required', 'email', Rule::unique('users', 'email')],
            'password' => ['required', 'min:8', 'confirmed']
        ]); 
        $incomingFields['password'] = bcrypt($incomingFields['password']);
        $user = User::create($incomingFields);
        auth()->login($user);
        return redirect('/')->with('success', 'Thank you for creating an account!');
    }

    public function login(Request $request) {
        $incomingFields = $request->validate([
            'loginusername' => 'required',
            'loginpassword' => 'required'
        ]);

        if (auth()->attempt(['username' => $incomingFields['loginusername'], 'password' => $incomingFields['loginpassword']])) {
            $request->session()->regenerate();
            event(new OurExampleEvent(['username' => auth()->user()->username, 'action' => 'login']));
            return redirect('/')->with('success', 'You have successfully logged in.');
        } else {
            return redirect('/')->with('failure', 'Invalid username or password.');
        }
    }

    public function logout() {
        event(new OurExampleEvent(['username' => auth()->user()->username, 'action' => 'logout']));
        auth()->logout();
        return redirect('/')->with('success', 'You are now logged out.');
    }

    public function showAvatarForm() {
        return view('avatar-form');
    }

    public function storeAvatar(Request $request) {
        $request->validate([
            'avatar' => 'required|image|max:4096'
        ]);

        $user = auth()->user();
        
        $filename = $user->id . '-' . uniqid() . '.jpg';

        $imgData = Image::make($request->file('avatar'))->fit(128)->encode('jpg');
        Storage::put('public/avatars/' . $filename, $imgData);

        $oldAvatar = $user->avatar;

        $user->avatar = $filename;
        $user->save();

        if ($oldAvatar != "/fallback-avatar.jpg") {
            Storage::delete(str_replace("/storage/", "public/", $oldAvatar));
        }

        return back()->with('success', 'Your avatar has been updated!');
    }

    private function getSharedData(User $user) {
        $currentlyFollowing = 0;

        if (auth()->check()) { // only checks if you're logged in
            $currentlyFollowing = Follow::where([
                ['user_id', '=', auth()->user()->id],
                ['followeduser', '=', $user->id]
            ])->count();
        } // returns true/false (1/0) if the currently logged in user has the current user page in their followeduser column 

        View::share('sharedData', [
            'username' => $user->username,
            'postCount' => $user->posts()->count(),
            'followerCount' => $user->followers()->count(),
            'followingCount' => $user->followingTheseUsers()->count(),
            'avatar' => $user->avatar,
            'currentlyFollowing' => $currentlyFollowing
        ]);
    }

    public function profile(User $user) {
        $this->getSharedData($user);
        return view('profile-posts', [
            'posts' => $user->posts()->latest()->get()
        ]);
    }

    public function profileFollowers(User $user) {
        $this->getSharedData($user);
        return view('profile-followers', [
            'followers' => $user->followers()->latest()->get()
        ]);
    }

    public function profileFollowing(User $user) {
        $this->getSharedData($user);
        return view('profile-following', [
            'following' => $user->followingTheseUsers()->latest()->get()
        ]);
    }

    public function profileRaw (User $user) {
        return response()->json([
            'theHTML' => view('profile-posts-only', [
                'posts' => $user->posts()->latest()->get()
            ])->render(),
            'docTitle' => $user->username . "'s Profile"
        ]);
    }

    public function profileFollowersRaw (User $user) {
        return response()->json([
            'theHTML' => view('profile-followers-only', [
                'followers' => $user->followers()->latest()->get()
            ])->render(),
            'docTitle' => $user->username . "'s Followers"
        ]);
    }

    public function profileFollowingRaw (User $user) {
        return response()->json([
            'theHTML' => view('profile-following-only', [
                'following' => $user->followingTheseUsers()->latest()->get()
            ])->render(),
            'docTitle' => "Profiles that " . $user->username . " follows."
        ]);
    }

    public function loginApi(Request $request) {
        $incomingFields = $request->validate([
            'username' => 'required',
            'password' => 'required'
        ]);

        if (auth()->attempt($incomingFields)) {
            $user = User::where('username', $incomingFields['username'])->first();
            $token = $user->createToken('ourapptoken')->plainTextToken;
            return $token;
        }
        return 'token incorrect';
    }
}
