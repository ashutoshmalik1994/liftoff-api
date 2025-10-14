<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Merchant extends Model
{
    protected $table = 'merchants';
	protected $fillable = [
        'merchant_id',
        'merchant_id_credit',
        'api_username',
        'api_username_merchant',
        'api_password',
        'status',
    ];
    use HasFactory;
}
