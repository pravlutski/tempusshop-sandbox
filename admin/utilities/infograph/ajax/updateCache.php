<?php
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");

$ms = new MoySkladAPI('s1');
$arFilter = [
  'momentFrom' => date('Y-m-d' , strtotime('- 3 months')),
  'momentTo' => date('Y-m-d')
];
$ms->getListProfitByAgent( 0, false, $arFilter );
$profit = $ms->MSPosition;
usort($profit, function($a, $b) {
  return $b['SELLSUM'] <=> $a['SELLSUM'];
});

file_put_contents('/var/www/bitrix/data/www/tempusshop.ru/admin/utilities/infograph/cache/cache.json', json_encode($profit) );
 ?>
