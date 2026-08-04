<?php require_once($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_before.php');

if(!CModule::IncludeModule('panel.manager'))return;

global $DB;
if (!empty($_POST) && isset($_POST['cabinet']) && isset($_POST['clientId'])  && isset($_POST['api'])) {
  if ( empty($_POST['settings']['exclude']) ) {
    $_POST['settings']['exclude'] = [];
  }else{
    $excl = explode(PHP_EOL, $_POST['settings']['exclude']);
    $excl = array_filter( $excl );
    $excl = array_map( 'trim', $excl );
    $excl = array_map(function($item){
      return end( explode('_', $item) );
    }, $excl);
    $excl = implode(',', $excl);
    $_POST['settings']['exclude'] = $excl;
  }
  // print_r($_POST['settings']);
  $in =  array(
    'clientId' => "'". $_POST['clientId'] ."'",
    'api' => "'". $_POST['api'] ."'",
    'settings' => "'".json_encode($_POST['settings'])."'"
  );
  $DB->Update("wdhs_wb_main_settings", $in, "WHERE cabinet='{$_POST['cabinet']}'");

}
require($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/epilog_after.php');
