<?php
$_SERVER["DOCUMENT_ROOT"] = "/var/www/bitrix/data/www/tempusshop.ru";
$DOCUMENT_ROOT = $_SERVER["DOCUMENT_ROOT"];

define("NO_KEEP_STATISTIC", true);
define("NOT_CHECK_PERMISSIONS", true);

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");

global $USER;
global $DB;
$arGroups = $USER->GetUserGroupArray();

$strSql = "SELECT model FROM ci_price WHERE supplier_id = 128";
$cp = $DB->Query( $strSql );
while ( $row = $cp->Fetch() ){
  $ci_price[ $row['model'] ] = 1;
}
unset($cp);
unset($row);

$arPrice = array();
$strSql = "SELECT * FROM offline_price_ru WHERE active = 'Y'";
$results = $DB->Query($strSql, false, $err_mess.__LINE__);
$flg = false;
while ($row = $results->Fetch()){
  if ( empty($ci_price[ $row['article'] ]) ) continue;
  if (intval($row['old_price']) - intval($row['price']) != 0) {
    $diff = intval($row['price']) - intval($row['old_price']);
    if ($diff > 0) {
      $percentage = round(($diff / intval($row['price'])) * 100,2);
    } else {
      $percentage = round(($diff / intval($row['old_price'])) * 100,2);
    }
    if (abs($percentage) > 10) {
      $arResult[$row['article']] = ['old_price' => round($row['old_price'],0), 'price' => round($row['price'],0), "diff" => $diff, "diff_p" => $percentage];
    }
  }
}

$arFilter = array(
    "IBLOCK_ID" => 16,
    "PROPERTY_CML2_ARTICLE" => array_keys($arResult),
);
$res = CIBlockElement::GetList(array(), $arFilter, false, false, array('ID','IBLOCK_ID','NAME','PROPERTY_CML2_ARTICLE', 'PROPERTY_FINALCOUNTRY'));
while ($ob = $res->GetNext()){
    $elementID = $ob["ID"];
    $arPrint[$elementID] = [
        'ARTICLE' => $ob["PROPERTY_CML2_ARTICLE_VALUE"],
        'NAME' => $ob['NAME'],
        'PRICE' => $arResult[$ob["PROPERTY_CML2_ARTICLE_VALUE"]]['price'],
        'COUNTRY' => $ob['PROPERTY_FINALCOUNTRY_VALUE'],
        'DATE' => date('d.m.Y')
      ];
}
$pageHeight = count($arPrint) * 10 + 5;
?>
<style>
.main{
  display: none;
}
@page {
  size: 30mm 11mm;
  margin: 0;
  padding: 0;
}
@media print {
  * {
    -moz-box-shadow: none !important;
    -moz-text-shadow: none !important;
  }
  html, body {
    background: white;
    width: 30mm;
    margin: 0 !important;
    padding: 1pt 0 0 0 !important;
    border: 0 !important;
  }
  .main{
    width: 30mm;
    height: 11mm;
    display:flex;
    flex-direction:column;
    /* border-bottom: 1px dashed black; */
    page-break-after: always; /* принудительный разрыв после каждого ценника */
    page-break-inside: avoid; /* запрет разрыва внутри */
    justify-content: center;
  }
  .second {
    /* margin-top: 1mm; */
    display: flex;
    align-items: center;
    justify-content: center;
    font-family: 'Open Sans';
    font-weight: 600;
    font-size: 8pt;
    width: 100%;
  }
  .second-1{
    text-align: center;
  }
  .third {
    /* margin-top: 1mm; */
    display:flex;
    flex-direction:row;
    justify-content: center;
    text-align: center;
    width: 100%;
  }
  .third-1{
    display: flex;

    font-size: 10pt;
    font-weight: 650;
    font-family: 'Open Sans';
  }
}
</style>
<body>
<? foreach ($arPrint as $key => $item): ?>
<div class="main">
  <div class="second" style="">
    <div class="second-1" style="text-align: center">
      <?=$item['NAME']?>
    </div>
  </div>
  <div class="third">
    <div class="third-1" style="">
      <span><?=$item['PRICE']?> руб.</span>
    </div>
  </div>
</div>
<? endforeach; ?>
</body>
<script src="https://code.jquery.com/jquery-3.7.1.slim.min.js" integrity="sha256-kmHvs0B+OpCW5GVHUNjv9rOmY0IvSIRcf7zGUDTDQM8=" crossorigin="anonymous"></script>
<script>
$(document).ready(function() {
    window.print();
});

</script>
