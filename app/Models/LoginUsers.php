<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Auth\Passwords\CanResetPassword;
use Illuminate\Auth\Passwords\CanResetPassword as CanResetPasswordTrait;
use App\Models\UserInformation;

class LoginUsers extends Model
{
    use HasFactory;

    public function information()
    {
        return $this->hasOne(UserInformation::class, 'user_id', 'id');
    }
}
