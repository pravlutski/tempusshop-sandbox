<?php
$_SERVER["DOCUMENT_ROOT"] = "/var/www/bitrix/data/www/tempusshop.ru";
$DOCUMENT_ROOT = $_SERVER["DOCUMENT_ROOT"];

define("NO_KEEP_STATISTIC", true);
define("NOT_CHECK_PERMISSIONS", true);
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");

CModule::IncludeModule('panel.manager');

function request( string $url, array $headers, ?string $data ):array
{
  $ch = curl_init( $url );
  curl_setopt_array( $ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => $headers,
    CURLOPT_POSTFIELDS => $data
  ]);

  $res = curl_exec( $ch );
  $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  curl_close( $ch );

  return json_decode($res, true);
}

$main = \Bitrix\Main\Application::getConnection();
$panel = new DBPanel;

$rows = $panel->select(['*'], 'ozon_main_settings_IP')->make();
$auth = array_column($rows, "value", "name");

$rows = $panel->select(['*'], 'ozon_sku_dict_IP')->make();
$dict = array_column( $rows, 'sku', 'model' );
$dictR = array_flip( $dict );

$strSql = "SELECT ARTICLE FROM ci_price_set WHERE PRICE_TYPE = 'OS'";
$rows = $main->query( $strSql );

$models = [];
while( $row = $rows->fetch() ){
  $skus[] = $dict[ $row['ARTICLE'] ];
}

$skus = array_filter( $skus );
$chunks = array_chunk( $skus, 1000 );

$headers = [
  "Api-Key: {$auth['key']}",
  "Client-Id:{$auth['client_id']}",
  'Content-Type:application/json'
];

$result = [];
foreach ( $chunks as $chunk ){
  $response = request(
    url: "https://api-seller.ozon.ru/v1/product/prices/details",
    headers: $headers,
    data: json_encode([ 'skus' => $chunk ]),
  );

  foreach( $response['prices'] as $priceData ){
    $result[] = [
      'model' => $dictR[ $priceData['sku'] ],
      'price' => $priceData['customer_price']['amount']
    ];
  }
  sleep(1);
}

$rows = array_map(fn($item) => implode(';', $item), $result);
$text = implode( PHP_EOL, $rows );

file_put_contents(
  "/var/www/bitrix/data/www/tempusshop.ru/admin/modules/forTest/export/clientPricesOZON.csv",
  print_r( $text, true )
);

?>
