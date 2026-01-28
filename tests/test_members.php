<?php
// tests/test_members.php

$url = 'http://192.168.1.18:8000/api/v1/members.php';

$json = file_get_contents($url);
if ($json === false) {
    echo "Erreur HTTP\n";
    exit(1);
}

$data = json_decode($json, true);

echo "Réponse brute :\n";
echo $json . "\n\n";

echo "Décodé :\n";
var_dump($data);

