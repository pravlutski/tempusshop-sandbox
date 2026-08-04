<?php
require_once($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_before.php');

$dictionary = [
    79 => ['name' => '1500p','site' => 'RU', 'type' => 'Сертификат'],
    80 => ['name' => '3000p','site' => 'RU', 'type' => 'Сертификат'],
    81 => ['name' => '600p','site' => 'RU', 'type' => 'Сертификат'],
    82 => ['name' => '900p','site' => 'RU', 'type' => 'Сертификат'],
    83 => ['name' => '15000p','site' => 'RU', 'type' => 'Сертификат'],
    84 => ['name' => '30000p','site' => 'RU', 'type' => 'Сертификат'],
    1119 => ['name' => '50000p','site' => 'RU', 'type' => 'Сертификат'],

    85 => ['name' => '50p','site' => 'BY', 'type' => 'Сертификат'],
    86 => ['name' => '100p','site' => 'BY', 'type' => 'Сертификат'],
    87 => ['name' => '200p','site' => 'BY', 'type' => 'Сертификат'],
    88 => ['name' => '300p','site' => 'BY', 'type' => 'Сертификат'],
    89 => ['name' => '400p','site' => 'BY', 'type' => 'Сертификат'],

    90 => ['name' => '5%','site' => 'RU', 'type' => 'Скидка'],
    91 => ['name' => '10%','site' => 'RU', 'type' => 'Скидка'],
    92 => ['name' => '5%','site' => 'BY', 'type' => 'Скидка'],
    93 => ['name' => '10%','site' => 'BY', 'type' => 'Скидка'],
    100 => ['name' => '15%','site' => 'RU', 'type' => 'Скидка'],
    101 => ['name' => '15%','site' => 'RU', 'type' => 'Скидка']
];

$path = '/var/www/bitrix/data/www/tempusshop.ru/admin/ajax/coupon/logs/log.txt';

if ( !file_exists($path) ) die('Нет данных');

$logString = file_get_contents( $path );

$arLog = explode( PHP_EOL, $logString );
$arLog = array_filter( $arLog );
$data_sale = [];
$data_sert = [];
foreach ($arLog as $json) {
  $arRow = json_decode( $json, true );
  // var_dump($arRow); die;
  $arRow['ACTIVE_FROM'] = date( 'Y.m.d', strtotime( $arRow['CREATED_AT'] . ' +0 months' ) );
  $arRow['ACTIVE_TO'] = date( 'Y.m.d', strtotime( $arRow['CREATED_AT'] . ' +12 months' ) );
  $arRow['CREATED_AT'] = date('Y.m.d G:i:s', strtotime( $arRow['CREATED_AT'] ));
  if ( stripos($dictionary[ $arRow['DISCOUNT_ID'] ]['name'], '%') ){
    $data_sale[] = $arRow;
  }else{
    $data_sert[] = $arRow;
  }
}
$data_sale = array_reverse( $data_sale );
$data_sert = array_reverse( $data_sert );
 ?>
 <div class="log-block" style="display:flex; flex-direction: row; margin-left: -30px" >
   <div class="log-sale-block" style=" border-right: 1px solid rgba(0,0,0,0.25)">
     <table id="log-table-sale" class="table table-stripped">
       <thead>
         <tr>
           <th style="width:82px !important">Дата создания</th>
           <th>Скидка</th>
           <th>Сайт</th>
           <th>Срок действия</th>
           <th>Комментарий</th>
           <th>Кем создано</th>
           <th>Купон</th>
         </tr>
       </thead>
       <tbody>
         <?
         foreach ( $data_sale as $row ){
           echo "<tr>";

           echo "<td>{$row['CREATED_AT']}</td>";
           echo "<td>{$dictionary[$row['DISCOUNT_ID']]['name']}</td>";
           echo "<td>{$dictionary[$row['DISCOUNT_ID']]['site']}</td>";
           echo "<td>{$row['ACTIVE_FROM']} - {$row['ACTIVE_TO']}</td>";
           echo "<td>{$row['DESCRIPTION']}</td>";
           echo "<td>{$row['USER']}</td>";
           echo "<td>{$row['COUPON']}</td>";

           echo "<tr>";
         }
         ?>
       </tbody>
     </table>
   </div>
   <div class="log-sert-block" style=" margin-left: 15px">
     <table id="log-table-sert" class="table table-stripped">
       <thead>
         <tr>
           <th style="width:82px !important">Дата создания</th>
           <th>Номинал</th>
           <th>Сайт</th>
           <th>Срок действия</th>
           <th>Комментарий</th>
           <th>Кем создано</th>
           <th>Купон</th>
         </tr>
       </thead>
       <tbody>
         <?
         foreach ( $data_sert as $row ){
           echo "<tr>";

           echo "<td>{$row['CREATED_AT']}</td>";
           echo "<td>{$dictionary[$row['DISCOUNT_ID']]['name']}</td>";
           echo "<td>{$dictionary[$row['DISCOUNT_ID']]['site']}</td>";
           echo "<td>{$row['ACTIVE_FROM']} - {$row['ACTIVE_TO']}</td>";
           echo "<td>{$row['DESCRIPTION']}</td>";
           echo "<td>{$row['USER']}</td>";
           echo "<td>{$row['COUPON']}</td>";

           echo "<tr>";
         }
         ?>
       </tbody>
     </table>
   </div>
 </div>

<style media="screen">
@media (max-width: 1666px) {
  .log-block {
    font-size: 12px;
  }
}
th, td{
  padding: 5px !important;
  font-size: 13px;
}
</style>
