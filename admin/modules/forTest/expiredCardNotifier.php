<?php
$_SERVER["DOCUMENT_ROOT"] = "/var/www/bitrix/data/www/tempusshop.ru";
$DOCUMENT_ROOT = $_SERVER["DOCUMENT_ROOT"];

define("NO_KEEP_STATISTIC", true);
define("NOT_CHECK_PERMISSIONS", true);

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");
CModule::IncludeModule("panel.manager");

$objContent = new CPanelContent;
$res = $objContent->getTasks();

$messageBody = "";
$counter = 0;
foreach( $res as $task ){
	if (!($brand = $objContent->getSection($task['brand_id']))) $brand['name'] = 'Ошибка';
  if ($task['datetime'] <= date('Y-m-d G:i:s',strtotime('now' . '-120 hour')) ){
    $messageBody .= $task['model'] . "\r\n";
    $counter += 1;
  }
}
if ( $counter > 0 ){
  $messageHead = "Превышен лимит для следующих товаров (". $counter ."):\r\n";
  $message = $messageHead . $messageBody;
}else{
  $message = "Нет карточек с превышенным лимитом";
}

mail('sofax57260@cgbird.com, tamaneh819@huleos.com', 'Контент-редактор', $message);
 ?>
