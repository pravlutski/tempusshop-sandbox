<?php
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");

if( empty($_POST['date']) ){
  die('Не выбрана дата');
}
if ( empty($_POST['cabinet']) ){
  $cabinet = 'WR';
}
if ( empty($_POST['mode']) ){
  $mode = 'full';
}
$mode = $_POST['mode'];
$cabinet = $_POST['cabinet'];

$logPathMain = "/var/www/bitrix/data/www/tempusshop.ru/admin/modules/WBImport/logs/orders/{$cabinet}/{$_POST['date']}_orders.txt";
$logPathStatus = "/var/www/bitrix/data/www/tempusshop.ru/admin/modules/WBImport/logs/orders/{$cabinet}/{$_POST['date']}_order_update.txt";

$logTextMain = file_get_contents( $logPathMain );
$logTextStatus = file_get_contents( $logPathStatus );

if ( !empty($logTextMain) && $mode == 'short' ){
  $arText = explode(PHP_EOL, $logTextMain);
  $logTextMain = '';
  foreach ( $arText as $row ){
    if ( !strrpos($row, 'already exists') ){
      $logTextMain .= $row . PHP_EOL;
    }
    if ( strrpos($row, 'END') ){
      $logTextMain .= PHP_EOL;
    }
  }
}else{
  $arText = explode(PHP_EOL, $logTextMain);
  $logTextMain = '';
  foreach ( $arText as $row ){
    $logTextMain .= $row . PHP_EOL;
    if ( strrpos($row, 'END') ){
      $logTextMain .= PHP_EOL;
    }
  }
}
$arText = explode(PHP_EOL, $logTextStatus);
$logTextStatus = '';
foreach ( $arText as $row ){
  $logTextStatus .= $row . PHP_EOL;
  if ( strrpos($row, 'END') ){
    $logTextStatus .= PHP_EOL;
  }
}
 ?>

 <details open class="log-main">
   <summary>Лог создания заказов (<? echo $cabinet; ?>)</summary>
   <hr>
   <span class="text"><pre><? echo $logTextMain; ?></pre></span>
 </details>
 <details class="log-status">
   <summary>Лог обновления статусов (<? echo $cabinet; ?>)</summary>
   <hr>
   <span class="text"><pre><? echo $logTextStatus; ?></pre></span>
 </details>
