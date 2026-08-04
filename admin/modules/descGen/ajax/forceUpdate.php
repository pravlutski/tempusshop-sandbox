<?php
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");
require($_SERVER["DOCUMENT_ROOT"]."/admin/modules/descGen/classes/DescriptionGenerator.php");

if ( empty($_POST) ){
  echo '<div class="status-bad status">Ошибка! Нет входных данных</div>';
  die;
}
if ( empty( $_POST['codes'] ) ){
  echo '<div class="status-bad status">Ошибка! Не указаны артикулы</div>';
  die;
}
$vendorCodes = explode( "\r\n", $_POST['codes'] );
foreach( $vendorCodes as $code ){
  $arVendor[] = trim( $code );
}
if ( is_array($arVendor) && count($arVendor) <= 0){
  echo '<div class="status-bad status">Ошибка! Массив с артикулами пустой</div>';
  die;
}
$detailFlag = $_POST['detail'];
$richFlag = $_POST['rich'];

$options = [
  'detail_flag' => $detailFlag == 1 ? true : false,
  'rich_flag' => $richFlag == 1 ? true : false,
  'vendor_codes' => $arVendor,
];

$ogjGen = new DescriptionGenerator();
$ogjGen->ajax( $options );
echo '<div class="status-good status">Обновление прошло успешно!</div>';
 ?>
