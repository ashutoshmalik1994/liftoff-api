<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Transaction extends Model
{
    use HasFactory;

    protected $table = 'transactions';

    protected $fillable = [
        'user_id', 'debit_card_id', 'bank_id', 'wallet_id', 'payee_id', 'invoice_no',
        'payee_account_no', 'email', 'memo', 'payee_cat', 'amount', 'payment_date',
        'payee_id_acc', 'status', 'trans_comment', 'confirmation', 'transfer_mode',
        'same_day_ach', 'rtp', 'type', 'settlement_date', 'rtn_code', 'rtn_date'
    ];
    public function payee()
{
    return $this->belongsTo(Payee::class, 'payee_id', 'id');
}
}
