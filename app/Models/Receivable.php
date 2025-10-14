<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Receivable extends Model
{
    use HasFactory;

    protected $table = 'receivables'; // Optional, agar table name auto match ho raha ho

    protected $fillable = [
        'user_id', 'request_id', 'request_wallet_id', 'request_to', 'debit_card_request_to',
        'payment_from', 'from_email', 'from_phone', 'payable_to', 'check_no', 'editable',
        'Recurring', 'start_date', 'end_date', 'month', 'amount', 'memo', 'printed_by',
        'ref_id', 'status', 'same_day_ach', 'settlement_date', 'rtn_code', 'rtn_date'
    ];
    public function payee()
{
    return $this->belongsTo(Payee::class, 'request_id', 'id');
}
}
