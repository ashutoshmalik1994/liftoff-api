<?php

namespace App\Jobs;

use App\Models\BankAccount;
use App\Models\Merchant;
use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class VerifyAndCreateBankAccount implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $bank;
    protected $user;

    public function __construct($bank, $user)
    {
        $this->bank = $bank;
        $this->user = $user;
    }

    public function handle()
    {
        $merchant = Merchant::where('user_id', $this->user->id)->first();

        $payload = json_encode([
            'MerchantID'     => $merchant->merchant_id,
            'Login'          => $merchant->api_username,
            'Password'       => $merchant->api_password,
            'RoutingNumber'  => $this->bank->routing_no,
            'AccountNumber'  => $this->bank->account_no,
        ]);

        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => 'https://payments.usiopay.com/2.0/payments.svc/JSON/VerifyACHAccount',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Accept: application/json',
            ],
        ]);
        $response = curl_exec($curl);
        curl_close($curl);

        $data = json_decode($response);

        if ($data->Status === 'success' && $data->Message === 'Account Found') {
            $this->bank->update(['status' => 'verified']);
        } else {
            $this->bank->update(['status' => 'failed_verification']);
        }
    }
}
