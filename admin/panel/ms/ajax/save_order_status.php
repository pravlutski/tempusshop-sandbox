<?php
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");
global $DB;

if ( empty($_POST) ){
  die('Пустой запрос. Ошибка. Я умер');
}

$data = $_POST;
$strSql = "SELECT * FROM wdhs_ozon_order_status_ti";
$result = $DB->Query($strSql, false, $err_mess.__LINE__);
$statusDB = [];
while ( $row = $result->Fetch() ){
  $statusDB[ $row['status_oz'] ] = $row['status_bx'];
}

foreach( $data as $status_oz => $status_bx ){
  if ( $statusDB[$status_oz] ){
    if ( $statusDB[$status_oz] != $status_bx ){
      if ( $status_bx == 'none' ){
        $strSql = "DELETE FROM wdhs_ozon_order_status_ti WHERE status_oz = '{$status_oz}'";
      }else{

        $strSql = "UPDATE wdhs_ozon_order_status_ti SET status_bx = '{$status_bx}' WHERE status_oz = '{$status_oz}'";
      }
      echo $strSql;
      $DB->Query($strSql, false, $err_mess.__LINE__);
    }
  }else{
    if ($status_bx == 'none') continue;
    $strSql = "INSERT INTO wdhs_ozon_order_status_ti (status_oz, status_bx) VALUES ('{$status_oz}','{$status_bx}')";
    $DB->Query($strSql, false, $err_mess.__LINE__);
  }
}

 ?>
