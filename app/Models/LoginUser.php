<?php

// app/Models/LoginUser.php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use PHPOpenSourceSaver\JWTAuth\Contracts\JWTSubject;

class LoginUser extends Authenticatable implements JWTSubject
{
    protected $table = 'login_users';

    // ✅ Required methods for JWT
    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    public function getJWTCustomClaims()
    {
        return [];
    }

    // Optional: add fillable/hidden etc. as needed
}
