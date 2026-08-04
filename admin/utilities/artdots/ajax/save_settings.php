<?php
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");

if ( empty($_POST) ) die('Пустой запрос');

$data = [];
foreach ($_POST as $key => $value) {
  $strInfo = explode('_', $key);
  $arInt = array_map(function( $item ){return intval($item);}, explode(',', $value));
  $data[ $strInfo[0] ][ $strInfo[1] ] = $arInt;
}

// var_dump( $_SERVER["DOCUMENT_ROOT"] );
// var_dump( $data );
file_put_contents($_SERVER["DOCUMENT_ROOT"]. "/admin/utilities/artdots/db/settings.json", json_encode( $data ));
echo "Saved";
 ?>
