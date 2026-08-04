<?php
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");
require("{$_SERVER['DOCUMENT_ROOT']}/admin/panel/engine/yandex/lib/bootstrap.php");

if ( empty($_POST) ) throw new Exception("POST cannot be empty");

UIProcessor::init();
$config = Config::instance();
$api = UIProcessor::api();
$data = UIProcessor::data();
$updater = UIProcessor::updater();

$response = $api->getPromos();
if ( $response->getHttpCode() !== $config->getSuccessHttpCode() )
{
  throw new Exception("Cannot get promos list");
}

$result = $response->getData()->decode()['result'];
$rows = $data->getPromosList( $_POST['cabinet'] );

$saved = [];
$insert = [];

foreach ( $rows as $row ){
  $saved[ $row['promo_id'] ] = $row;
}

foreach ( $result['promos'] as $promo ){
  if ( isset($saved[$promo['id']]) ) continue;
  $activeFlag = strtotime($promo['period']['dateTimeFrom']) <= time() && time() <= strtotime($promo['period']['dateTimeTo']);
  $insert[] = [
    'promo_id' => $promo['id'],
    'name' => $promo['name'],
    'active' => $activeFlag ? 1 : 0,
    'date_from' => $promo['period']['dateTimeFrom'],
    'date_to' => $promo['period']['dateTimeTo'],
    'type' => $promo['mechanicsInfo']['type'],
    'cabinet' => $_POST['cabinet'],
  ];
}

$update = [];
foreach ( $saved as $promoId => $data ){
  $isActive = strtotime($data['date_from']) <= time() && time() <= strtotime($data['date_to']);
  if ( $data['active'] == $isActive ) continue;
  $update = ["active" => $isActive ? 1 : 0];
  UIProcessor::updater()->update(
    table: Config::instance()->getTableName('promos_list'),
    values: $update,
    where: ['promo_id' => $promoId]
  );
}

if ( empty($insert) ) exit();

UIProcessor::updater()->insertSome(
  into: Config::instance()->getTableName('promos_list'),
  values: $insert
);
 ?>
