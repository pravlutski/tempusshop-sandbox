<?php
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");
require($_SERVER['DOCUMENT_ROOT'].'/admin/panel/engine/ozon/classes/adverts/AdvertConfigProvider.php');

$data = $_POST;
if ( empty($_POST) ) return;

$data['adverts'] = json_encode(explode( ',', $data['adverts'] ?? '' ));
$table = AdvertConfigProvider::getSettingsTable();
$data['average_coinvest'] = (100 - $data['average_coinvest']) / 100;
$panel = new DBPanel;
$arWhere[] = [
  'column' => 'cabinet',
  'operator' => '=',
  'value' => 'IP'
];

$panel->update( $table, $data, $arWhere );
?>
