<?php
$_SERVER["DOCUMENT_ROOT"] = "/var/www/bitrix/data/www/tempusshop.ru";
$DOCUMENT_ROOT = $_SERVER["DOCUMENT_ROOT"];

define("NO_KEEP_STATISTIC", true);
define("NOT_CHECK_PERMISSIONS", true);

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");

global $USER;
global $DB;
$arGroups = $USER->GetUserGroupArray();

$arPrice = array();
$strSql = "SELECT * FROM offline_price WHERE active = 'Y'";
$results = $DB->Query($strSql, false, $err_mess.__LINE__);
$flg = false;
while ($row = $results->Fetch()){
  if (intval($row['old_price']) - intval($row['price']) != 0) {
    $diff = intval($row['price']) - intval($row['old_price']);
    if ($diff > 0) {
      $percentage = round(($diff / intval($row['price'])) * 100,2);
    } else {
      $percentage = round(($diff / intval($row['old_price'])) * 100,2);
    }
    if (abs($percentage) > 10) {
      $arResult[$row['article']] = ['old_price' => round($row['old_price'],0), 'price' => round($row['price'],0), "diff" => $diff, "diff_p" => $percentage];
      $arKeyFilter[] = $row['article'];
    }
  }
}
$iblockID = 16; // Замените на ID вашего инфоблока
$arFilter = array(
    "IBLOCK_ID" => $iblockID,
    "PROPERTY_CML2_ARTICLE" => $arKeyFilter,
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
$arPrint = array_chunk($arPrint, 3);
$arCate = array_chunk($arPrint, 8);
// foreach ($arResult as $key => $value) {
//   $iblockID = 16; // Замените на ID вашего инфоблока
//   $arFilter = array(
//       "IBLOCK_ID" => $iblockID,
//       "PROPERTY_CML2_ARTICLE" => $key,
//   );
//   $res = CIBlockElement::GetList(array('ID','IBLOCK_ID'), $arFilter, false, false, array("ID"));
//   if ($ob = $res->GetNextElement()) {
//       $arFields = $ob->GetFields();
//       $elementID = $arFields["ID"];
//   }
//   $arSection = getSectionsElement($elementID);
//   $brand = $arSection[1]['NAME'];
//   $arPrint[] = [
//     'ARTICLE' => $key,
//     'BRAND' => $brand,
//     'PRICE' => $value['price']
//   ];
// }
?>
<style>
.main{
  width:226.7px;
  height:106.5px;
  border: 2px solid black;
  display:flex;
  flex-direction:column;
}
.background {
  position: absolute;
  z-index: -1;
}
.first {
  border: 1px solid black;
  position: relative;
  height: 38.4px;
  text-align: center;
  font-size: 9px;
  font-weight: 600;
  line-height: 1.2;
  font-family: 'Open Sans';
  display: flex;
  justify-content: center;
  align-items: center;
    overflow: hidden;
}
.second {
  border: 1px solid black;
  height: 47.8px;
  font-size: 12px;
  display: flex;
  justify-content: center;
  font-family: 'Open Sans';
  font-weight: 600;
  /* align-items: center; */
}
.third {
  border: 1px solid black;
  display:flex;
  height:30.3px;
  flex-direction:row;
}
.third-1{
  position: relative;
  overflow: hidden;
  width:188.9px;
  display: flex;
  justify-content: flex-end;
  align-items: center;
  font-size: 18;
  font-weight: 700;
  font-family: 'Open Sans';
}
.third-1 span{
  padding-right: 5px;
}
.third-2{
  display: flex;
  justify-content: center;
  align-items: center;
  font-size: 12px;
  text-align: center;
  width: 37.7px;
  font-family: 'Open Sans';
  border-left-color: #111;
  border-left-style: solid;
  border-left-width: 2px;
}
.row {
  display: flex;
  flex-direction: row;
  gap:10px;
  margin-bottom: 5px;
}
</style>
<body>
<?foreach ($arCate as $key => $arPrint):?>
<div style="margin-bottom: 75px;">
<?foreach ($arPrint as $key => $arProduct):?>
<div class="row">
  <?foreach ($arProduct as $key => $item):?>
  <div class="col-sm-4 main">
    <div class="first">
      <div class="background"><img src="/bitrix/components/adm/offline.pricelist/templates/.default/img/background.png"></div>
      ООО "ВОТЧ-ТРЕЙД"<br>УНП 192848849
    </div>
    <div class="second" style="display: flex; flex-direction:column">
      <div class="second-1" style="display:flex; margin: 0 auto 0 auto; padding: 2px">
        <?=$item['NAME']?>
      </div>
      <div class="second-2" style="display:flex; flex-direction:row; padding: 2px">
        <span style="display: flex;"><?=$item['COUNTRY']?></span>
        <span style="margin-left: auto; display:flex;"><?=$item['DATE']?></span>
      </div>
    </div>
    <div class="third">
      <div class="third-1">
        <div class="background"><img src="/bitrix/components/adm/offline.pricelist/templates/.default/img/background.png"></div>
        <span><?=$item['PRICE']?>,00</span>
      </div>
      <div class="third-2">
        б.руб.<br>
        шт.
      </div>
    </div>
  </div>
  <?endforeach;?>
</div>
<?endforeach;?>
</div>
<?endforeach;?>
</body>
<script src="https://code.jquery.com/jquery-3.7.1.slim.min.js" integrity="sha256-kmHvs0B+OpCW5GVHUNjv9rOmY0IvSIRcf7zGUDTDQM8=" crossorigin="anonymous"></script>
<script>
$(document).ready(function() {
    window.print();
});

</script>
