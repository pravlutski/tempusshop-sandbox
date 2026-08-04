<?php
$_SERVER["DOCUMENT_ROOT"] = "/var/www/bitrix/data/www/tempusshop.ru";
$DOCUMENT_ROOT = $_SERVER["DOCUMENT_ROOT"];

define("NO_KEEP_STATISTIC", true);
define("NOT_CHECK_PERMISSIONS", true);

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");

global $USER;
global $DB;
$arGroups = $USER->GetUserGroupArray();


$iblockID = 16; // Замените на ID вашего инфоблока
$arSelect = Array("ID", "IBLOCK_ID", "PROPERTY_123", "PROPERTY_AVAILABILITY_BY", "CATALOG_PRICE_2", "PROPERTY_CML2_ARTICLE");
$arFilter = Array(
    "IBLOCK_ID" => CProSet::IB_CATALOG,
    "PROPERTY_AVAILABILITY_BY" => 492,
    "ACTIVE" => "Y",
);

$result = CIBlockElement::GetList(array(), $arFilter, false, false, $arSelect);
$arPrint = array();
while ($ob = $result->GetNext()) {
    $elementID = $ob["ID"];
    $arPrice = CCatalogProduct::GetOptimalPrice($elementID, 1, array(2));

    $price = isset($arPrice['RESULT_PRICE']) ? $arPrice['RESULT_PRICE']['BASE_PRICE'] : 0;
    $newPrice = isset($arPrice['RESULT_PRICE']) ? $arPrice['RESULT_PRICE']['DISCOUNT_PRICE'] : 0;

    // Проверяем, есть ли скидка
    if ($price != $newPrice && $newPrice > 0) {
        $arPrint[$elementID] = [
            'ARTICLE' => $ob["PROPERTY_CML2_ARTICLE_VALUE"],
            'NAME' => $ob['NAME'],
            'PRICE' => $price,
            'NEW_PRICE' => $newPrice,
        ];
    }
}

print_r($arPrint);
$arPrint = array_chunk($arPrint, 3);
$arCate = array_chunk($arPrint, 11);
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
        height:75.5px;
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
        height: 26.4px;
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
        height: 18.8px;
        font-size: 12px;
        display: flex;
        justify-content: center;
        font-family: 'Open Sans';
        font-weight: 600;
        align-items: center;
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
        margin-bottom: 10px;
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
                        <div class="second">
                            <?=$item['NAME']?>
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
