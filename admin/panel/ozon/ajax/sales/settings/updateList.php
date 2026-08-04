<?php
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");
$panel = new DBPanel;

function getSalesList( array $settings ):array
{
  $url = "https://api-seller.ozon.ru/v1/actions";
  $headers = [
    "Api-Key:{$settings['key']}",
    "Client-Id:{$settings['client_id']}",
    "Content-Type:application/json"
  ];

  $options = [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => $headers,
    CURLOPT_TIMEOUT => 30,
    CURLOPT_CONNECTTIMEOUT => 30,
  ];

  $ch = curl_init($url);
  curl_setopt_array($ch, $options);

  $res = curl_exec($ch);
  $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  curl_close($ch);

  $response = json_decode($res, true);

  return [
    'response' => $response,
    'code' => $code,
  ];
}

function getActiveFlag( string $dateStart, string $dateEnd ):bool
{
  $tsStart = strtotime( $dateStart );
  $tsEnd = strtotime( $dateEnd );

  return $tsStart < time() && time() < $tsEnd;
}

//////////////////////////////////////////////////////////////////

$settings = $panel->select(['*'], 'ozon_main_settings_IP')->make();
$settings = array_column($settings, 'value', 'name');

$rows = $panel->select(['*'], 'ozon_sales_IP')->make();
$sales = array_column( $rows, 'sale_id', 'sale_id' );
$res = getSalesList( $settings );
$response = $res['response'];

$insert = [];
$update = [];

foreach ( $response['result'] as $row ){
  if ( $sales[ $row['id'] ] ){
    $update[ $row['id'] ] = [
      'name' => $row['title'],
      'active' => (int) getActiveFlag( $row['date_start'], $row['date_end'] ),
      'date_start' => $row['date_start'],
      'date_end' => $row['date_end'],
    ];
    continue;
  }
  $insert[ $row['id'] ] = [
    'sale_id' => $row['id'],
    'name' => $row['title'],
    'sort' => 9,
    'perc' => 0,
    'active' => (int) getActiveFlag( $row['date_start'], $row['date_end'] ),
    'date_start' => $row['date_start'],
    'date_end' => $row['date_end'],
  ];
}

if ( !empty($update) ){
  foreach ( $update as $id => $row ){
    $where = ['column' => 'sale_id', 'operator' => '=', 'value' => $id];
    $panel->update('ozon_sales_IP', $row, [$where]);
  }
}

if ( !empty($insert) ){
  $panel->insert( 'ozon_sales_IP', array_values($insert) );
}
 ?>
