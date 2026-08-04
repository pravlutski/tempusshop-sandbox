<?php
$_SERVER["DOCUMENT_ROOT"] = "/home/bitrix/ext_www/tempusshop.ru";
$DOCUMENT_ROOT = $_SERVER["DOCUMENT_ROOT"];

define("NO_KEEP_STATISTIC", true);
define("NOT_CHECK_PERMISSIONS", true);

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");
CModule::IncludeModule('panel.manager');

function getItemsMS( DBPanel $panel ):array
{
  $tableRU = $panel->select( ['*'], 'ms_profit_ru_12' )->make();
  $tableBY = $panel->select( ['*'], 'ms_profit_by_12' )->make();

  foreach ( $tableRU as $item ){
    $profit[ $item['model'] ] = [
      'model' => $item['model'],
      'quantity' => $item['quantity'],
      'index' => 'ru'
    ];
  }

  foreach ( $tableBY as $item ){
    if ( isset($profit[ $item['model'] ]) ) continue;

    $profit[ $item['model'] ] = [
      'model' => $item['model'],
      'quantity' => $item['quantity'],
      'index' => 'by'
    ];
  }

  usort($profit, function($a, $b) {
    return $b['quantity'] <=> $a['quantity'];
  });

  $result = [];

  foreach ( $profit as $arVal ){
    $result[ $arVal['model'] ] = $arVal['quantity'];
  }

  return $result;
}

$panel = new DBPanel;
$profit = getItemsMS( $panel );


$arFilter = [
  "IBLOCK_ID" => 16,
  "!SORT" => 9999
];

$arSelect = ["IBLOCK_ID", "ID", "PROPERTY_CML2_ARTICLE", "SORT"];

$res = CIBlockElement::GetList(array("SORT" => "ASC"), $arFilter, false, false, $arSelect);

$goods = [];
while ( $row = $res->GetNext() ){
  $goods[] = [
    'model' => $row["PROPERTY_CML2_ARTICLE_VALUE"],
    'profit' => $profit[ $row["PROPERTY_CML2_ARTICLE_VALUE"] ],
    'index' => $row["SORT"]
  ];
}

echo '<div class="list-sorted">';
foreach ( $goods as $item ) {
  echo '<div class="item-sorted">';
  echo '<div class="model-name chunk">' . $item['model'] . '</div>';
  echo '<div class="index chunk">' . $item['profit'] . '</div>';
  echo '<div class="index chunk">' . $item['index'] . '</div>';
  echo '</div>';
}
echo '</div>';


 ?>
