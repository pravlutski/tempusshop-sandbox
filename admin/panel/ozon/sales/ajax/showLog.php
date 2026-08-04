<?php
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");

CModule::IncludeModule('panel.manager');
$dbPanel = new DBPanel;

$search = empty($_POST['search']) ? false : $_POST['search'];
$sale = empty($_POST['sale']) ? false : $_POST['sale'];
$cabinet = empty($_POST['cabinet']) ? 'IP' : $_POST['cabinet'];

$select = $dbPanel->select(['*'], "ozon_sales_detail_log_IP");

if ( !empty($search) ){
  $select = $select->where('model', $search);
}
if ( !empty($sale) ){
  $select = $select->where('saleId', $sale);
}

$rows = $select->desc('date')->limit(1000)->make();

foreach ( $rows as $row ):
 ?>
 <div class="card <?echo ($row['status'] == 'Y') ? 'active' : 'inactive' ;?>">
   <div class="card-header">
     <span class="card-name"><b><?=$row['date']?></b> - <?=$row['saleName']?></span>
     <span class="card-status"><b>Статус:</b> <?echo ($row['status'] == 'Y') ? 'В акции' : 'Не в акции' ;?></span>
   </div>
   <div class="card-body">
     <div class="card-row">
       <span class="row-name">Товар: </span>
       <span class="row-value"><?=$row['model']?></span>
     </div>
     <div class="card-row">
       <span class="row-name">Комментарий: </span>
       <span class="row-value"><?=$row['reason']?></span>
     </div>
     <div class="card-row">
       <span class="row-name">Себестоимость: </span>
       <span class="row-value"><?=$row['cost']?></span>
     </div>
     <div class="card-row">
       <span class="row-name">Базовая цена товара: </span>
       <span class="row-value"><?=$row['startPrice']?></span>
     </div>
     <div class="card-row">
       <span class="row-name">Цена вхождения: </span>
       <span class="row-value"><?=$row['price']?></span>
     </div>
     <div class="card-row">
       <span class="row-name">Модуль ДЦ: </span>
       <span class="row-value"><?echo ($row['isPriceDynamic'] == 'Y') ? 'Да' : 'Нет' ;?></span>
     </div>
     <div class="card-row">
       <span class="row-name">Макс. цена вхождения: </span>
       <span class="row-value"><?=$row['maxActionPrice']?></span>
     </div>
     <div class="card-row">
       <span class="row-name">Макс. эластичная цена: </span>
       <span class="row-value"><? echo $row['priceMaxElastic'] ?? '-'?></span>
     </div>
     <div class="card-row">
       <span class="row-name">Мин. эластичная цена: </span>
       <span class="row-value"><? echo $row['priceMinElastic'] ?? '-'?></span>
     </div>
   </div>
 </div>

<? endforeach; ?>
