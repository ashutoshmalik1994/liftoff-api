<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BankAccount extends Model
{
    use HasFactory;

    
	protected $fillable = [
		'user_id',
		'country',
		'name',
		'bank1_address1',
		'bank1_address2',
		'bank1_city',
		'bank1_state',
		'bank1_zip',
		'account_no',
		'routing_no',
		'transit_no',
		'financial_institute_no',
		'bank_nickname',
		'bank_account_type',
		'bank_name',
		'bank_street_address',
		'bank_city',
		'bank_state',
		'bank_zip',
		'bank_check_no',
		'bank_fractional_number',
		'signature_name',
		'signature',
	];

    public function transactions()
    {
        return $this->hasMany(Transaction::class, 'bank_id');
    }
}
