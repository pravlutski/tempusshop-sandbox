<?php
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");
global $DB;

if ( empty($_POST) ){
  die('Пустой запрос. Ошибка. Я умер');
}

$data = $_POST;
$strSql = "SELECT * FROM wdhs_ozon_order_status";
$result = $DB->Query($strSql, false, $err_mess.__LINE__);
$statusDB = [];
while ( $row = $result->Fetch() ){
  $statusDB[ $row['status_oz'] ] = $row['status_bx'];
}

foreach( $data as $status_oz => $status_bx ){
  if ( $statusDB[$status_oz] ){
    echo "Status was set \n";
    if ( $statusDB[$status_oz] != $status_bx ){
      echo "status changed \n";
      if ( $status_bx == 'none' ){
        echo "status is null \n";
        $strSql = "DELETE FROM wdhs_ozon_order_status WHERE status_oz = '{$status_oz}'";
      }else{
        echo "Status updated \n";
        $strSql = "UPDATE wdhs_ozon_order_status SET status_bx = '{$status_bx}' WHERE status_oz = '{$status_oz}'";
      }
      echo $strSql;
      $DB->Query($strSql, false, $err_mess.__LINE__);
    }
  }else{
    echo "status was not set \n";
    if ($status_bx == 'none') continue;
    $strSql = "INSERT INTO wdhs_ozon_order_status (status_oz, status_bx) VALUES ('{$status_oz}','{$status_bx}')";
    $DB->Query($strSql, false, $err_mess.__LINE__);
  }
}

 ?>
