<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserInformation extends Model
{
    use HasFactory;

    protected $table = 'login_user_information';

    protected $fillable = [
        'user_id','profile_pic','ssn', 'date_of_birth', 'address_line_1', 'address_line_2',
        'city', 'state', 'postal_code', 'country','formation_doc','ownership_doc','personal_doc','supporting_doc',
    ];

    public function user()
    {
        return $this->belongsTo(LoginUsers::class, 'user_id', 'id');
    }

}
