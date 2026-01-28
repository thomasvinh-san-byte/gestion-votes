<?php
// tests/test_cast_ballot.php

$motionId = 'UUID_DE_TA_MOTION';
$memberId = 'UUID_DU_MEMBRE';

$payload = [
    'motion_id' => $motionId,
    'member_id' => $memberId,
    'value'     => 'for', // for / against / abstain / nsp
];

$ch = curl_init('http://192.168.1.18:8000/api/v1/ballots_cast.php');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_HTTPHEADER     => [
        'Content-Type: application/json',
        'Accept: application/json',
    ],
    CURLOPT_POSTFIELDS     => json_encode($payload),
]);

$response = curl_exec($ch);
if ($response === false) {
    echo "Erreur cURL : " . curl_error($ch) . "\n";
    exit(1);
}
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP $httpCode\n";
echo "Réponse brute :\n$response\n\n";

$data = json_decode($response, true);
var_dump($data);

