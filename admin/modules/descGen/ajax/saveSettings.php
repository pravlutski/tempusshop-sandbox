<?php
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");

global $DB;

if ( empty($_POST) ){
  die('post пустой. НЕУСПЕШНО');
}
$data = $_POST;

foreach ( $_POST as $fields => $text ){
  $fieldsArray = explode('+', $fields);
  $propId = $fieldsArray[0];
  $propName = str_replace('_',' ',$fieldsArray[1]);
  $optId = $fieldsArray[2];
  $optName = str_replace('_',' ',$fieldsArray[3]);

  $strSql = "SELECT 1 FROM sds_property_text_match WHERE option_id = '{$optId}'";
  $result = $DB->Query($strSql, false, $err_mess.__LINE__);

  if ( $result->SelectedRowsCount() > 0 ){
    $strSql = "UPDATE sds_property_text_match SET text = '{$text}' WHERE option_id = '{$optId}'";
  }else{
    $strSql = "INSERT INTO sds_property_text_match (property_id, property_name, option_id, option_name, text) VALUES ('{$propId}','{$propName}','{$optId}','{$optName}','{$text}')";
  }
  unset( $propId );
  unset( $propName );
  unset( $optId );
  unset( $optName );
  unset( $text );

  print_r($strSql . PHP_EOL);
  $DB->Query($strSql, false, $err_mess.__LINE__);
}
 ?>
