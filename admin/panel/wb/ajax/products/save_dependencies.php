<?php
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");

if ( empty($_POST) ){
  die('empty post');
}
foreach ( $_POST as $key => $value ){
  // if ( empty($value) ) continue;
  $option_id = explode( '+', $key )[0];
  $char_id = explode( '+', $key )[1];

  if ( strrpos($value,';') ){
    $val = explode(';', $value);
    $data = json_encode($val, JSON_UNESCAPED_UNICODE);
  }else{
    $data = empty($value) ? NULL :json_encode([$value], JSON_UNESCAPED_UNICODE);
  }

  $strSql = "UPDATE wdhs_wb_product_props_dependencies SET value = '{$data}' WHERE option_id = '{$option_id}' AND char_id = '{$char_id}'";
  $DB->Query($strSql, false, $err_mess.__LINE__);
}

 ?>
