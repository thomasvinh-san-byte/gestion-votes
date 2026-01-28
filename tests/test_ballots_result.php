<?php
// tests/test_ballots_result.php

$motionId = 'UUID_DE_TA_MOTION';

$url = 'http://192.168.1.18:8000/api/v1/ballots_result.php?motion_id=' . urlencode($motionId);

$json = file_get_contents($url);
if ($json === false) {
    echo "Erreur HTTP\n";
    exit(1);
}

echo "Réponse brute :\n$json\n\n";
$data = json_decode($json, true);
var_dump($data);

