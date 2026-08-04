<?php
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");

if ( empty($_POST) ) die('Empty POST');
$fields = $_POST;

foreach ( $fields as $key => $value ){

    $char_id = explode('+', $key)[0];
    $field = explode('+', $key)[1];
    if ( strrpos($value, ';') ){
      $value = explode(';', $value);
      $value = json_encode($value, JSON_UNESCAPED_UNICODE);
    }
    elseif ( $value == '' ){
      $value = NULL;
    }else{
      if ( $field == 'custom_value') $value = json_encode([$value], JSON_UNESCAPED_UNICODE);
    }
    $strSql = "UPDATE wdhs_wb_product_props SET {$field} = '{$value}' WHERE char_id = '{$char_id}'";
    $DB->Query($strSql, false, $err_mess.__LINE__);
}
 ?>
