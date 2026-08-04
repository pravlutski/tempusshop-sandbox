<?php
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");

if( empty($_POST['date']) ){
  die('Не выбрана дата');
}

$logPathMain = "/var/www/bitrix/data/www/tempusshop.ru/admin/modules/OzonImport2/logs/orders/{$_POST['date']}_orders.txt";
$logPathStatus = "/var/www/bitrix/data/www/tempusshop.ru/admin/modules/OzonImport2/logs/orders/{$_POST['date']}_order_update.txt";

$logTextMain = file_get_contents( $logPathMain );
$logTextStatus = file_get_contents( $logPathStatus );

 ?>

 <details open class="log-main">
   <summary>Лог создания заказов</summary>
   <span class="text"><pre><? echo $logTextMain; ?></pre></span>
 </details>
 <details class="log-status">
   <summary>Лог обновления статусов</summary>
   <span class="text"><pre><? echo $logTextStatus; ?></pre></span>
 </details>
