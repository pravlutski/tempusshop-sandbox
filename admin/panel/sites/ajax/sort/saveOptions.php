<?php
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");
CModule::IncludeModule('panel.manager');
$dbPanel = new DBPanel;

if ( empty($_POST) ){
  die('EMPTY POST ARRAY');
}
$groupsImport = [];
if ( !empty($_POST['order_data']) ){
  $arPairs = explode('&', $_POST['order_data']);
  foreach ($arPairs as $pair) {
    $groupsImport[] = urldecode( explode('=',$pair)[1] );
  }
}
if ( !empty($_POST['txt_data']) ){
  $arPairs = explode('&', $_POST['txt_data']);
  foreach ($arPairs as $pair) {
    $strTmp = urldecode( explode('=',$pair)[1] );
  }
  if ( !empty($strTmp) ){
    $arData = explode("\r\n", $strTmp);
    $groupsImport = array_merge($groupsImport, $arData);
  }
}

if ( in_array($_POST['site'], ['ru', 'by']) ){
  $table = 'sites_sort_list_' . $_POST['site'];
}else{
  die('НЕВЕРНЫЙ КАБИНЕТ');
}

// var_dump( $groupsImport );
// var_dump( $_POST['site'] );
// var_dump( $table );

if ( !empty($groupsImport) ){
  $dbPanel->query("DELETE FROM {$table} WHERE 1=1");
  $data = array_map(function($item){
    return ['group_name' => $item];
  }, $groupsImport);
  // var_dump($data);
  $dbPanel->insert($table, $data);
}
 ?>
