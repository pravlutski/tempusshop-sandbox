<?php
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");
global $DB;

if ( empty($_POST) ){
  die('Пустой запрос. Ошибка. Я умер');
}

$data = $_POST;
$strSql = "SELECT * FROM wdhs_wb_order_status";
$result = $DB->Query($strSql, false, $err_mess.__LINE__);
$statusDB = [];
while ( $row = $result->Fetch() ){
  $statusDB[ $row['status_wb'] ] = $row['status_bx'];
}

foreach( $data as $status_oz => $status_bx ){
  if ( $statusDB[$status_oz] ){
    if ( $statusDB[$status_oz] != $status_bx ){
      if ( $status_bx == 'none' ){
        $strSql = "DELETE FROM wdhs_wb_order_status WHERE status_wb = '{$status_oz}'";
      }else{
        $strSql = "UPDATE wdhs_wb_order_status SET status_bx = '{$status_bx}' WHERE status_wb = '{$status_oz}'";
      }
      echo $strSql;
      $DB->Query($strSql, false, $err_mess.__LINE__);
    }
  }else{
    if ($status_bx == 'none') continue;
    $strSql = "INSERT INTO wdhs_wb_order_status (status_wb, status_bx) VALUES ('{$status_oz}','{$status_bx}')";
    $DB->Query($strSql, false, $err_mess.__LINE__);
  }
}

 ?>
