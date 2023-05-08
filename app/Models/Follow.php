<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Follow extends Model
{
    use HasFactory;

    public function userDoingTheFollowing() {
        return $this->belongsTo(User::class, 'user_id'); // transfers the user id into the User class as that id
    }

    public function userBeingFollowed() {
        return $this->belongsTo(User::class, 'followeduser'); // uses the id registered in column 'followeduser' to pinpoint which user it should reference
    }
}
