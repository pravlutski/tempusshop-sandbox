<?php
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");
require($_SERVER['DOCUMENT_ROOT'].'/admin/panel/engine/ozon/classes/adverts/AdvertConfigProvider.php');

$alloweFilePath = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
$savePaths = [
  'own' => AdvertConfigProvider::getOwnFilePath(),
  'comp' => AdvertConfigProvider::getCompetitorFilePath(),
];

foreach ( $_FILES as $key => $data ){
  var_dump( $data );
  if ( empty($data) ) continue;
  if ( $data['type'] != $alloweFilePath ){
    throw new Exception("Неподдерживаемый тип файла key = {$own}, type = {$data['type']}");
  }
  if ( $data['error'] !== UPLOAD_ERR_OK ){
    throw new Exception("Ошибка загрузки файла key = {$own}");
  }
  // unlink( "{$saveFilePath}/{$key}.xlsx" );
  $res = rename(
    $data['tmp_name'],
    $savePaths[$key]
  );
  var_dump( $savePaths[$key] );
  var_dump($res);
}
 ?>
