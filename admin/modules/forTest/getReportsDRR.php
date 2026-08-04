<?php

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");
CModule::IncludeModule('maxyss.wb');

$auth = CMaxyssWb::settings_wb('WR');
// var_dump($auth);

$arFilter = Array(
        "IBLOCK_ID" => 16,
        "ACTIVE" => "Y",
        "ID" => 179947,
        "!PROPERTY_AEN2" => false,
  );

$arSelect = Array("ID", "IBLOCK_ID", "NAME", "PROPERTY_AEN2", "PROPERTY_PROP_MAXYSS_CARDID_WB", "PROPERTY_PROP_MAXYSS_NMID_CREATED_WB", "PROPERTY_PROP_MAXYSS_CHRTID_CREATED_WB", "PROPERTY_PROP_MAXYSS_PROMOCODES_WB", "PROPERTY_PROP_MAXYSS_DISCOUNTS_WB");

$res = CIBlockElement::GetList(Array("ID" => "ASC"), $arFilter, false, false, $arSelect);

while ($ob = $res->GetNextElement()) {
    var_dump($ob);
}

// $advIds = [
//   16317328 => [75455553,75458389,75457456],
//   16317218 => [75455080, 75539449],
//   16316724 => [75451387, 75451670],
// ];
// getAdvIds();
// var_dump($advIds);
// $ada = getReports($advIds,$auth);
// $baba = checkIfWorth($ada);


// var_dump($ada);
// var_dump($baba);

function getAdvIds()
{
  global $DB;
  $strSql = "SELECT advId, nmid FROM illiquid_wb WHERE advId != null";
  $resultDB = $DB->Query($strSql, false, $err_mess.__LINE__);
  $advIds = [];
  while ( $row = $resultDB->Fetch() ){
    $advIds[$row['advId']] = $row['nmid'];
  }
  // var_dump($advIds);
}

function getReports($advIds, $auth)
{
  if ($advIds == false){
    return false;
  }
  $data = [];

  foreach ($advIds as $id => $goods) {
    $data[] = [
      'id' => (int)$id,
      'interval' => [
        'begin' =>date("Y-m-d", strtotime("-7 day")),
        'end' => date("Y-m-d")
      ]
    ];
  }

  $url = 'https://advert-api.wb.ru/adv/v2/fullstats';
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
  curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
  curl_setopt($ch,CURLOPT_CONNECTTIMEOUT, 30);
  $result = curl_exec($ch);
  curl_close($ch);
  $reports = json_decode($result, 1);

  if ( !empty($reports) && is_array($reports) ){
    foreach ($reports as $adv) {
      $reportDRR[$adv['advertId']] = ['spentSum' => $adv['sum']];
    }
  }else{
    $reportsSpent = false;
  }

  $sales = 0;
  foreach ($advIds as $id => $goods) {
    $data = [
      'nmIDs' => $goods,
      'period' => [
        'begin' =>date("Y-m-d h:i:s", strtotime("-7 day")),
        'end' => date("Y-m-d h:i:s")
      ],
      'page' => 1
    ];

    $url = 'https://seller-analytics-api.wildberries.ru/api/v2/nm-report/detail';
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
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch,CURLOPT_CONNECTTIMEOUT, 30);
    $result = curl_exec($ch);
    curl_close($ch);
    $result = json_decode($result, 1);
    if ( empty($result['error']) && is_array($result) ){
      foreach ( $result['data']['cards'] as $card ){
        $sales += $card['statistics']['selectedPeriod']['ordersSumRub'];
      }
      $reportDRR[$id]['salesSum'] = $sales == 0 ? $report[$id]['spentSum'] : $sales;
    }
    sleep(20);
  }
  return $reportDRR;
}


function checkIfWorth($reportDRR)
{
  if ($reportDRR == false){
    return false;
  }
  foreach ($reportDRR as $advertId => $report) {
    var_dump([]);
    if ( $report['spentSum']/$report['salesSum'] > 0.1 ){
      $toDelete[] = (int)$advertId;
    }else{
      $toSave[] = (int)$advertId;
    }
  }
  return ['toDelete' => $toDelete, 'toSave' => $toSave];
}

 ?>
