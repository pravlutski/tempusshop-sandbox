<?php
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");
require($_SERVER["DOCUMENT_ROOT"]."/admin/panel/engine/wb/classes/CleanerWB.php");

$cabinet = $_POST["cabinet"];

$cleaner = new CleanerWB($cabinet);

$tableRows = $cleaner->checkLogsSize();
$logsData = $cleaner->checkTablesSize();

$arDict = [
  'products_cron' => 'Логи выгрузки товаров',
  'orders' => 'Логи импорта заказов',
  'stock' => 'Логи выгрузки остатков (stock)',
  'prices' => 'Логи выгрузки цен (price)',
  'reqests_stock' => 'Логи выгрузки остатков (reqests)',
  'reqests_prices' => 'Логи выгрузки цен (reqests)'
];
// echo '<pre>';
// var_dump($tableRows);
// echo '</pre>';
 ?>
 <div class="response-container" style="margin-bottom: 15px;">
   <h3>Кабинет <?=$_POST['cabinet']?></h3>
   <div class="type-row">
     <div class="type-name">
       Количство строк в таблице заказов
     </div>
     <div class="type-value">
       <?=$logsData?>
     </div>
   </div>
  <? foreach( $arDict as $type => $value ): ?>
  <div class="type-row">
    <div class="type-name">
      <?=$value?>
    </div>
    <div class="type-value">
      <?
      if ( stripos($type, '_') ){
        $first = explode('_', $type)[0];
        $second = explode('_', $type)[1];
        echo $tableRows[$first][$second][$cabinet];
      }else{
        echo $tableRows[$type][$cabinet];
      }
      ?>
    </div>
  </div>
  <? endforeach; ?>
</div>
