<?php
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");

if ( empty($_POST['profile_id']) ){
  header('HTTP/1.1 400 Bad Request');
  die;
}
global $USER;
$panel = new DBPanel;

$profile = $panel->select(['brand_id', 'platform'], 'am_brand_profiles')->where('id', $_POST['profile_id'])->make()[0];

$rows = $panel->select(['*'], 'am_campaign_products')->where('platform', $profile['platform'])->where('brand', $profile['brand_id'])->make();
$advertIds = [];

foreach ( $rows as $row ){
  $advertIds[ $row['advertId'] ] = 1;
}
$advertIds = array_keys( $advertIds );

if ( !empty($advertIds) ){

  $statusCheck = array_map( fn($item) => false, array_flip($advertIds) );

  require("{$_SERVER['DOCUMENT_ROOT']}/admin/panel/engine/common/adverts/lib/bootstrap.php");

  $config = Loader::loadConfig( $profile['platform'] );
  Config::init( $config );

  CommunicationService::log("START");
  CommunicationService::log("DELETION OF {$_POST['profile_id']} and linked requested by {$USER->getLogin()}");

  $api = Loader::loadApiManager();
  if ( $profile['platform'] == 'ozon' ) $api->authorize();

  foreach ( $advertIds as $advertId ){
    $request = ($profile['platform'] == 'ozon') ? $advertId : ['id' => $advertId];
    $response = $api->changeCampaignActivity( $request, 'disable' );
    $reason = false;

    if( !empty($response['result']['error']) ){
       $reason = str_contains($response['result']['error'], 'не найдена');
    }

    if ( $response['code'] == 200 || $reason === true ){
      $statusCheck[ $advertId ] = true;
      $where = [ 'column' => 'advertId', 'operator' => '=', 'value' => $advertId ];
      $panel->delete( 'am_campaign_products', [$where] );
    }

    usleep( 800000 );
  }

  if ( array_sum($statusCheck) != count($statusCheck) ) {
    var_dump($statusCheck);
    throw new Exception("Не удалось отключить одну или несколько кампаний. Попробуйте позже");
  }
}



$where = [
  'column' => 'id',
  'operator' => '=',
  'value' => $_POST['profile_id']
];
$panel->delete('am_brand_profiles', [$where]);
 ?>
