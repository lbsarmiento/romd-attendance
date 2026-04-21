<?php

$url = "https://sienna-jay-693502.hostingersite.com/api/ttis";

$payload = [
    "name" => "PTC Benguet",
    "province_id" => 10,
    "classification" => "PTC",
    "address" => "Wangal, La Trinidad",
    "email" => "pptc-benguet@tesda.gov.ph"
];

// 🔑 Your Bearer Token (IMPORTANT)
$token = "Bearer eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpZCI6MSwiZW1haWwiOiJyb21vLnJvbWRAdGVzZGEuZ292LnBoIiwicm9sZSI6ImFkbWluIiwiaWF0IjoxNzc1MDA5MjAyLCJleHAiOjE3NzU2MTQwMDJ9.kR0H8yz0Zf-EHwlVzEi2MaCMoHPzyvSBJfV6jNL5Ukg";

$ch = curl_init($url);

curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));

curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Authorization: Bearer " . $token,
    "Content-Type: application/json",
    "Accept: application/json"
]);

// Optional (to avoid blocking issues)
curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/5.0");

$response = curl_exec($ch);

if (curl_errno($ch)) {
    echo "Curl error: " . curl_error($ch);
    exit;
}

curl_close($ch);

// Output response
echo $response;