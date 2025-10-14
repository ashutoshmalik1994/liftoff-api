<?php

$curl = curl_init();

curl_setopt_array($curl, array(
  CURLOPT_URL => 'https://payments.usiopay.com/2.0/payments.svc/JSON/SubmitACHPayment',
  CURLOPT_RETURNTRANSFER => true,
  CURLOPT_ENCODING => '',
  CURLOPT_MAXREDIRS => 10,
  CURLOPT_TIMEOUT => 0,
  CURLOPT_FOLLOWLOCATION => true,
  CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
  CURLOPT_CUSTOMREQUEST => 'POST',
  CURLOPT_POSTFIELDS =>'{
    "MerchantID": "0000000001",
    "Login": "API0000000001",
    "Password": "Temp1234!",
    "RoutingNumber": "555555550",
    "AccountNumber": "123456789",
    "TransCode": "23",
    "Amount": "1",
    "FirstName": "Payee Final",
    "LastName": "Payee Final",
    "EmailAddress": "final@gmail.com",
    "Address1": "street 1 ,232 ,CA",
    "City": "fgbfb",
    "State": "NY",
    "Zip": "12345",
    "CheckNegativeAccounts": false,
    "StandardEntryCode": "WEB",
    "TokenizeOnly": false,
    "SameDayACH": ""
}',
  CURLOPT_HTTPHEADER => array(
    'Content-Type: application/json',
    'Cookie: ASP.NET_SessionId=iygupja0wxkl1410p2h1tywz'
  ),
));

$response = curl_exec($curl);

curl_close($curl);
echo $response;
