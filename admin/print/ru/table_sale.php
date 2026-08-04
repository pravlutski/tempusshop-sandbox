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
$strSql = "SELECT * FROM offline_price_ru WHERE active = 'Y'";
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
    echo '<pre>';
    var_dump($percentage);
    echo '</pre>';
    if (abs($percentage) > 10) {
      $arResult[$row['article']] = ['old_price' => round($row['old_price'],0), 'price' => round($row['price'],0), "diff" => $diff, "diff_p" => $percentage];
      $arKeyFilter[] = $row['article'];
    }
  }
}

// Получаем ID типа цены BASE_BEL
$priceTypeCode = "BASE_SITE";
$dbPriceType = CCatalogGroup::GetList(
    array(),
    array(
        "NAME" => $priceTypeCode
    )
);

$priceTypeID = null;
if ($arPriceType = $dbPriceType->Fetch()) {
    $priceTypeID = $arPriceType["ID"];
}

$sales = array('30','153','154','155','156','157',',158','159','160','161','162','163','164','165','166','167','168','169','170','171','172','173');

$iblockID = 16; // Замените на ID вашего инфоблока
$arSelect = Array("ID", "NAME", "IBLOCK_ID", "PROPERTY_123", "PROPERTY_AVAILABILITY_BY", "PROPERTY_MINIMUM_PRICE", "PROPERTY_CML2_ARTICLE", "PROPERTY_FINALCOUNTRY");
$arFilter = Array(
    "IBLOCK_ID" => CProSet::IB_CATALOG,
    "PROPERTY_AVAILABILITY_RU" => 512,
    "ACTIVE" => "Y",
);
if (isset($_GET['diff'])) {
  $arFilter['PROPERTY_CML2_ARTICLE'] = $arKeyFilter;
  //print_r($arKeyFilter);
}
$result = CIBlockElement::GetList(array(), $arFilter, false, false, $arSelect);
$arPrint = array();
while ($ob = $result->GetNext()) {
    $elementID = $ob["ID"];

    // Получаем цену для типа BASE_BEL
    $priceRes = CPrice::GetList(
        array(),
        array(
            "PRODUCT_ID" => $elementID,
            "CATALOG_GROUP_ID" => $priceTypeID
        )
    );

    if ($arPrice = $priceRes->Fetch()) {
        $price = $arPrice['PRICE'];
        $price= round($price,0);
        // Получаем цену со скидкой
        $arDiscounts = CCatalogDiscount::GetDiscountByPrice(
            $arPrice['ID'],
            $USER->GetUserGroupArray(),
            "N",
            "s1"
        );
        foreach ($arDiscounts as $k=>$v) {
            if (!in_array($v['ID'],$sales)) {
                unset($arDiscounts[$k]);
            }
        }
        $discountPrice = CCatalogProduct::CountPriceWithDiscount(
            $price,
            $arPrice['CURRENCY'],
            $arDiscounts
        );
        $discountPrice= round($discountPrice,0);
        // Проверяем, есть ли скидка
        if ($price != $discountPrice && $discountPrice > 0) {
            $arPrint[$elementID] = [
                'ARTICLE' => $ob["PROPERTY_CML2_ARTICLE_VALUE"],
                'NAME' => $ob['NAME'],
                'PRICE' => $price,
                'NEW_PRICE' => $discountPrice,
                'COUNTRY' => $ob['PROPERTY_FINALCOUNTRY_VALUE'],
                'DATE' => date('d.m.Y')
            ];
        }
    }
}
?>
<style>
@page {
  size: 30mm <?=$pageHeight?>mm;
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
    height: 10mm;
    display:flex;
    flex-direction:column;
    border-bottom: 1px dashed black;
  }
  .second {
    margin-top: 1mm;
    display: flex;
    justify-content: center;
    font-family: 'Open Sans';
    font-weight: 600;
    font-size: 7pt;
  }
  .second-1{
    text-align: center;
  }
  .third {
    margin-top: 1mm;
    display:flex;
    flex-direction:row;
    justify-content: center;
    text-align: center;
  }
  .third-1{
    display: flex;

    font-size: 7pt;
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
        <span><?=$item['PRICE']?>,00 ₽, шт.</span>
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
