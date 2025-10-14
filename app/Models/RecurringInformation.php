<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RecurringInformation extends Model
{
    use HasFactory;

    protected $fillable = [
        'status',
        'user_id',
        'recurring',
        'first_payment_date',
        'last_bill_date',
        'number_of_payments',
        'amount',
        'final_amount',
        'payment_processed',
        'payer',
        'payable_to',
        'same_day_ach',
        'business_days',
        'purpose',
        'count_payments',
        'transaction_status',
        'next_bill_date',
    ];
public function payerRelation()
    {
        return $this->belongsTo(Payee::class, 'payer', 'id');
    }

    // Relationship to BankAccount (payable_to)
    public function payableToRelation()
    {
        return $this->belongsTo(BankAccount::class, 'payable_to', 'id');
    }
}
