<?php

namespace App\Services;

class UsioService
{
    public function getTransactionDetails($merchantId, $login, $password, $confirmation)
    {
        $payload = [
            "MerchantID"       => $merchantId,
            "Login"            => $login,
            "Password"         => $password,
            "ReturnAccountMask"=> true,
            "Confirmation"     => $confirmation,
            "IncludeHPPValues" => true
        ];

        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => "https://payments.usiopay.com/2.0/payments.svc/JSON/GetTransactionDetails",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "POST",
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_HTTPHEADER => [
                "Content-Type: application/json"
            ],
        ]);

        $response = curl_exec($curl);

        if (curl_errno($curl)) {
            throw new \Exception('Usio API Error: ' . curl_error($curl));
        }

        curl_close($curl);

        return json_decode($response, true);
    }
}
