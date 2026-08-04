<?php
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");

global $DB;
$strSql = "SELECT * FROM wdhs_wb_main_settings WHERE cabinet = 'WR'";
$auth = $DB->Query($strSql, false, $err_mess.__LINE__)->Fetch()['api'];

$url = 'https://content-api.wildberries.ru/content/v2/object/charcs/60?locale=ru';
$ch = curl_init($url);
curl_setopt(
      $ch,
      CURLOPT_HTTPHEADER,
      array(
        "Content-Type: application/json",
        "Authorization: {$auth}"
      )
    );
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch,CURLOPT_CONNECTTIMEOUT, 30);
$res = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);
if ( curl_errno( $ch ) ) {
  $error_msg = curl_error( $ch );
  die( $error_msg );
}
if ( $http_code != 200) die( $http_code );

$result = json_decode( $res, 1 );
if ( empty($result) || empty($result['data']) ) die('Empty');
$import = [];
foreach ( $result['data'] as $prop ){
  $import[] = [
    'char_id' => intval($prop['charcID']),
    'subject_name' => strval($prop['subjectName']),
    'subject_id' => intval($prop['subjectID']),
    'name' => strval($prop['name']),
    'required' => $prop['required'] == false ? 'N' : 'Y',
    'unit_name' => strval($prop['unitName']),
    'max_count' => intval($prop['maxCount']),
    'popular' => $prop['popular'] == false ? 'N' : 'Y',
    'char_type' => intval($prop['charcType']),
  ];
}
foreach ( $import as $row ){
  $strSql = formulateRequest( $row, 'wdhs_wb_product_props');
  // var_dump( $strSql );
  $DB->Query($strSql, false, $err_mess.__LINE__);
}

function formulateRequest( array $array, string $table ):string // Люблю велосипеды изобретать
{
  $columnsDB = "(";
  $valuesDB = "(";
  foreach ( $array as $column => $value ){
    if ( array_key_last($array) == $column ){
      $columnsDB .= $column . ")";
      $valuesDB .= "'{$value}')";
    }else{
      $columnsDB .= $column . ", ";
      $valuesDB .= "'{$value}', ";
    }
  }
  $strSql = "INSERT INTO {$table} {$columnsDB} VALUES {$valuesDB}";
  return $strSql;
}
 ?>
