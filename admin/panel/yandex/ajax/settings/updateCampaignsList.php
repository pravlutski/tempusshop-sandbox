<?php
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");
require("{$_SERVER['DOCUMENT_ROOT']}/admin/panel/engine/yandex/lib/bootstrap.php");

if ( empty($_POST['cabinet']) ) throw new Exception("empty cabinet value");
$cabinet = $_POST['cabinet'];

UIProcessor::init();

$rows = UIProcessor::data()->settings()->getCampaignsList( $cabinet );
$response = UIProcessor::api()->getCampaigns( query: ['limit' => 100] );

$result = $response->getData()->decode();

$campaignsIds = [];

foreach ( $rows as $row ){
  $campaignsIds[ $row['campaignId'] ] = true;
}

$res = [];
foreach ( $result['campaigns'] as $c ){
  var_dump( isset($campaignsIds[$c['id']]) );
  if ( $c['apiAvailability'] != 'AVAILABLE' ) continue;
  if ( isset($campaignsIds[$c['id']]) ) continue;
  $res[] = [
    'campaignId' => $c['id'],
    'domain' => $c['domain'],
    'cabinet' => $cabinet,
  ];
}

if ( empty($res) ) exit();

UIProcessor::updater()->insertSome(
  into: Config::instance()->getTableName('campaigns_list'),
  values: $res
);
 ?>
