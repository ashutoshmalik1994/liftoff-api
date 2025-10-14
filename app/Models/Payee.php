<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Payee extends Model
{
    use HasFactory;
    protected $table = 'payees';
	   protected $fillable = [
        'user_id', 'unique_id', 'payee_type', 'payee_name', 'nickname', 'email',
        'phone_no', 'account_no', 'address1', 'address2', 'city', 'state', 'zip', 'country'
    ];
    public function transactions()
    {
        return $this->hasMany(Transaction::class, 'payee_id');
    }
}
