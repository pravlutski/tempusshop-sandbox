<?php
$_SERVER["DOCUMENT_ROOT"] = "/var/www/bitrix/data/www/tempusshop.ru";
$DOCUMENT_ROOT = $_SERVER["DOCUMENT_ROOT"];

define("NO_KEEP_STATISTIC", true);
define("NOT_CHECK_PERMISSIONS", true);

function getAuthCookie():string
{
  $path = "/var/www/bitrix/data/www/tempusshop.ru/admin/panel/engine/wb/configs/analytics_cookie.json";

  if ( !file_exists( $path ) ) die('Нет куки файла');

  $json = file_get_contents( $path );
  $cookieArray = json_decode( $json, true );

  return $cookieArray['cookie'];
}

function getFinalPrice( array $array ):array
{
  $chunks = array_chunk( $array, 30, true );

  $baseUrl = 'https://www.wildberries.ru/__internal/u-card/cards/v4/list';

  $arQuery = [
    'appType' => '1',
    'curr' => 'rub',
    'dest' => '-3339991',
    'spp' => '30',
    // 'hide_vflags' => '4294967296',
    // 'hide_dtype' => '10',
    'ab_testing' => 'false',
    'nm' => '',
  ];
  foreach ( $chunks as $chunk ){
    $aq = $arQuery;
    $aq['nm'] = implode(';', $chunk);

    $query = http_build_query($aq);
    $url = $baseUrl . '?' . $query;

    $ch = curl_init($url);
    curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
    curl_setopt( $ch, CURLOPT_CONNECTTIMEOUT, 30 );
    curl_setopt( $ch, CURLOPT_COOKIE, getAuthCookie() );
    $resCurl = curl_exec($ch);
    curl_close($ch);
    $result[] = json_decode($resCurl,true);
    sleep( rand(1,2) );
  }

  $goods = [];

  foreach ( $result as $chunk ){
    foreach ( $chunk['products'] as $card ){
      $goods[ $card['id'] ] = [
        'nmid' => $card['id'],
        'price' => $card['sizes'][0]['price']['product'] / 100,
      ];
    }
  }

  return $goods;
}

$json = file_get_contents("/var/www/bitrix/data/www/tempusshop.ru/admin/modules/forTest/nms.txt");
$rows = json_decode( $json, true );

$rows = getFinalPrice($rows);
$csv = [];

foreach ( $rows as $id => $data ){
  $csv[] = "{$data['nmid']};{$data['price']}";
}

file_put_contents(
  "/var/www/bitrix/data/www/tempusshop.ru/admin/modules/forTest/nmids.csv",
  implode(PHP_EOL, $csv)
);
 ?>
<a href="/admin/modules/forTest/nmids.csv" download>Жмяк</a>
