<?php require_once($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_before.php');
if($_SERVER['HTTP_X_REQUESTED_WITH'] != 'XMLHttpRequest') return;


// var_dump($_POST);

if ( isset($_POST['site_id']) && in_array( $_POST['site_id'], ['RU', 'BY', 'RU_NKZ'] ) ){
  $site_id = $_POST['site_id'];
}else{
  $site_id = 'ALL';
}

$command = "/usr/bin/php81 -f /var/www/bitrix/data/www/tempusshop.ru/local/cron/UpdateTopList.php {$site_id} >/dev/null 2>&1 &";

// var_dump( $command );

system( $command );

echo json_encode(array("OK"), JSON_UNESCAPED_UNICODE);

header('Content-Type: application/json;charset=UTF-8');
die();
