<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PayeeBank extends Model
{
    protected $table = 'default_payee_banks';	
    use HasFactory;
	protected $fillable = [
        'user_id',     
        'unique_id',
        'payee_id',
        'transaction_mode',
        'account_holder_name',
        'routing_no',
        'account_no',
        'account_type'
   ];
}
