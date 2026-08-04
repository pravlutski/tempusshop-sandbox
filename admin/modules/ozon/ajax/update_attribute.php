<?php require_once($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_before.php');

if(!CModule::IncludeModule('panel.manager'))return;
set_time_limit(0);
$conn = OZON_API_CONN;
global $DB;

$strSql = "SELECT * FROM wdhs_ozon_attribute_category";
$results = $DB->Query($strSql, false, $err_mess.__LINE__);
while ($row = $results->Fetch()){
  $arTypes[] = $row;
}
$strSql = "SELECT * FROM wdhs_ozon_main_settings";
$results = $DB->Query($strSql, false, $err_mess.__LINE__);
while ($row = $results->Fetch()){
  $arSetting[$row['name']] = $row['value'];
}

$conn['api_url'] = $arSetting['api_url'];
$conn['client_id'] = $arSetting['client_id'];
$conn['token'] = $arSetting['key'];

if (!empty($arTypes)) {
  $DB->Query("DELETE FROM wdhs_ozon_attribute WHERE 1=1", false, $err_mess.__LINE__);
  foreach ($arTypes as $key => $value) {

    $data = array(
      "description_category_id" => intval($value['category_id']),
      "language" => "DEFAULT",
      "type_id" => intval($value['type_id'])
    );
    $ch = curl_init( $conn['api_url'] . '/v1/description-category/attribute');
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
      'Api-Key:' . $conn['token'],
      'Client-Id:' . $conn['client_id'],
      'Content-Type:application/json'
    ));
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data, JSON_UNESCAPED_UNICODE));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_HEADER, false);
    $res = curl_exec($ch);
    curl_close($ch);
    $dataAtt = json_decode($res,true);
    print_r($res);
    foreach ($dataAtt['result'] as $att) {
      if ($att['dictionary_id'] != '0') {
        $DB->Query("DELETE FROM wdhs_ozon_attribute_list WHERE attribute_id = '".intval($att['id'])."'", false, $err_mess.__LINE__);
        $next = 1;
        $lv_id = 0;
        while ($next == 1){
          $dataDic = array(
           "attribute_id" => intval($att['id']),
           "description_category_id" => intval($value['category_id']),
           "language" => "DEFAULT",
           "last_value_id" => $lv_id,
           "limit" => 5000,
           "type_id" => intval($value['type_id'])
          );

          $ch = curl_init( $conn['api_url'] . '/v1/description-category/attribute/values');
          curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Api-Key:' . $conn['token'],
            'Client-Id:' . $conn['client_id'],
            'Content-Type:application/json'
          ));
          curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($dataDic, JSON_UNESCAPED_UNICODE));
          curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
          curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
          curl_setopt($ch, CURLOPT_HEADER, false);
          $resD = curl_exec($ch);
          $resD = json_decode($resD,true);
          curl_close($ch);
          // file_put_contents("/var/www/bitrix/data/www/tempusshop.ru/admin/modules/ozon/ajax/bad.txt", print_r('-------', true).PHP_EOL, FILE_APPEND);
          // file_put_contents("/var/www/bitrix/data/www/tempusshop.ru/admin/modules/ozon/ajax/bad.txt", print_r($resD, true).PHP_EOL, FILE_APPEND);
          foreach ($resD['result'] as $n => $p) {
            $in = array(
              "attribute_id" => "'".intval($att['id'])."'",
              "value_id" => "'".intval($p['id'])."'",
              "value" => "'".addslashes($p['value'])."'",
            );
            $DB->Insert("wdhs_ozon_attribute_list", $in, $err_mess.__LINE__);
          }


          if ($resD['has_next'] != 1) {
            $next = 0;
          }
          if (!empty($resD['result'])) {
            $lastKey = count($resD['result']) - 1;
            $lv_id = $resD['result'][$lastKey]['id'];
          }
        }
      } else {
        $bt_id = $att['dictionary_id'];
      }
      $in = array(
        "type_id" => "'".$value['type_id']."'",
        "attribute_id" => "'".$att['id']."'",
        "category_id" => "'".intval($value['category_id'])."'",
        "name" => "'".$att['name']."'",
        "description" => "'".$att['description']."'",
        "type" => "'".$att['type']."'",
        "is_collection" => "'".$att['is_collection']."'",
        "is_required" => "'".$att['is_required']."'",
        "is_aspect" => "'".$att['is_aspect']."'",
        "group_name" => "'".$att['group_name']."'",
        "group_id" => "'".$att['group_id']."'",
        "dictionary_id" => "'".$att['dictionary_id']."'",
        "dictionary_id_bitrix" => "'".$bt_id."'",
      );
      //print_r($in);
      $DB->Insert("wdhs_ozon_attribute", $in, $err_mess.__LINE__);
    }


  }
}


require($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/epilog_after.php');
