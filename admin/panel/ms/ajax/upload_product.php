<?php require_once($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_before.php');
//
if(!CModule::IncludeModule('panel.manager') || !CModule::IncludeModule("iblock") || !CModule::IncludeModule("main") || $_SERVER['HTTP_X_REQUESTED_WITH'] != 'XMLHttpRequest') return;
global $DB;


if ($_POST['request'] == 'start') {
system("/usr/bin/php -f /var/www/bitrix/data/www/tempusshop.ru/admin/modules/OzonImport2/importProducts.php > /dev/null 2>&1 &");
}

if ($_POST['request'] == 'kill') {
  exec("pgrep -f importProducts.php",$output,$code);
  if(is_array($output) && count($output) > 0){
    foreach($output as $pid)
      exec("kill -9 {$pid}");

    $DB->Update("wdhs_ozon_upload_status_new", array("status" => "'COMPLETE'","percent" => "'100'"), "WHERE agent = 'products'", $err_mess.__LINE__);
  }
}

echo json_encode(array("status" => "ok"), JSON_UNESCAPED_UNICODE);

header('Content-Type: application/json;charset=UTF-8');
die();
