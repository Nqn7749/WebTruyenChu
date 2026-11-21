
<?php

$url = 'https://vi.wikisource.org/wiki/Main_Page';

$ch = curl_init($url);

curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 20,
    CURLOPT_FOLLOWLOCATION => true,
]);

$response = curl_exec($ch);

if ($response === false) {
    echo "SSL ERROR: " . curl_error($ch) . PHP_EOL;
    echo "Error code: " . curl_errno($ch) . PHP_EOL;
} else {
    echo "HTTPS OK" . PHP_EOL;
    echo "HTTP status: " . curl_getinfo($ch, CURLINFO_HTTP_CODE) . PHP_EOL;
}

curl_close($ch);
