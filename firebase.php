<?php

require_once 'vendor/autoload.php';

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

function getAccessToken($serviceAccountFile)
{
    $credentials = json_decode(file_get_contents($serviceAccountFile), true);

    $now = time();
    $jwtHeader = ['alg' => 'RS256', 'typ' => 'JWT'];
    $jwtClaimSet = [
        'iss' => $credentials['client_email'],
        'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
        'aud' => $credentials['token_uri'],
        'iat' => $now,
        'exp' => $now + 3600,
    ];

    $jwt = JWT::encode($jwtClaimSet, $credentials['private_key'], 'RS256');

    file_put_contents("uri.txt", $credentials['token_uri']);
    
    $ch = curl_init($credentials['token_uri']);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
        'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
        'assertion' => $jwt,
    ]));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);
    $response = curl_exec($ch);
    
    if ($response === false) {
        $error = curl_error($ch);
    }
    
    curl_close($ch);

    $tokenData = json_decode($response, true);

    return $tokenData['access_token'] ?? null;
}

$serviceAccountFile = 'locationconnection-firebase-adminsdk-bksh1-1adf68cdfb.json';
$accessToken = getAccessToken($serviceAccountFile);

?>