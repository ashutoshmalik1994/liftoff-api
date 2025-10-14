<?php<?php

namespace App\Helpers;

use Carbon\Carbon;

class TransactionHelper
{
    /**
     * Get formatted deposit date
     *
     * @param  object $transaction
     * @param  string|null $apiSubmitDate
     * @return string|null
     */
    public static function getDepositDate($transaction, $apiSubmitDate = null)
    {
        if (!empty($transaction->rtn_code)) {
            return null;
        }

        if (!empty($apiSubmitDate)) {
            $timestamp = preg_replace('/[^0-9]/', '', $apiSubmitDate);
            return Carbon::createFromTimestampMs($timestamp)->format('m/d/Y');
        }

        return $transaction->updated_at ? Carbon::parse($transaction->updated_at)->format('m/d/Y') : null;
    }
}
