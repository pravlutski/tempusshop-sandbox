<?php
$data = [
  'skus' => ['2154031141']
];

$ch = curl_init( "https://api-seller.ozon.ru/v1/analytics/stocks" );
curl_setopt($ch, CURLOPT_HTTPHEADER, array(
  'Api-Key:46581b77-e317-4a5e-9b64-2d8cc9265c00',
  'Client-Id:2893807',
  'Content-Type:application/json'
));
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data, JSON_UNESCAPED_UNICODE));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_HEADER, false);
$res = curl_exec($ch);

curl_close($ch);
$res = json_decode($res, true);

echo '<pre>';
var_dump($res);
echo '</pre>';
 ?>
