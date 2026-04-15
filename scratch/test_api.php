<?php
/**
 * Scratch test for Joby API
 */

$app_id = '8c4e82ec';
$app_key = '29e2fa38b319a86d510952f7101cb866';
$country = 'ng';
$count = 1;

$url = "https://api.adzuna.com/v1/api/jobs/{$country}/search/1?app_id={$app_id}&app_key={$app_key}&results_per_page={$count}&content-type=application/json";

echo "Testing URL: $url\n\n";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Status: $http_code\n";
echo "Response:\n";
echo $response;
echo "\n";
