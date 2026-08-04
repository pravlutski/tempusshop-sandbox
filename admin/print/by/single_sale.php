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
if (!isset($_POST['model']) || $_POST['model'] == '') {
  header('Location: /admin/pricelist/offline.php');
  die;
}
// Получаем ID типа цены BASE_BEL
$priceTypeCode = "BASE_BEL";
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

$models = explode("\n",$_POST['model']);
array_filter($models, function($value) {
    return !empty($value);
});

// Восстановить порядок ключей
$models = array_values($models);
$models = array_map('trim', $models);
$sales = array('30','153','154','155','156','157',',158','159','160','161','162','163','164','165','166','167','168','169','170','171','172','173');

$iblockID = 16;
$arSelect = Array("ID", "NAME", "IBLOCK_ID", "PROPERTY_123", "PROPERTY_AVAILABILITY_BY", "CATALOG_PRICE_2", "PROPERTY_CML2_ARTICLE", "PROPERTY_FINALCOUNTRY","PROPERTY_DP_DISCOUNT");
$arFilter = Array(
    "IBLOCK_ID" => CProSet::IB_CATALOG,
    "PROPERTY_AVAILABILITY_BY" => 492,
    "ACTIVE" => "Y",
    'PROPERTY_CML2_ARTICLE' => $models,
);
//print_r($arFilter);
$result = CIBlockElement::GetList(array(), $arFilter, false, false, $arSelect);
$arPrint = array();
while ($ob = $result->GetNext()) {
    $elementID = $ob["ID"];
    //print_r($elementID);
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
            "s2"
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
        

        if (isset($ob['PROPERTY_DP_DISCOUNT_VALUE'])) {
          $saleSkd = 1 + (intval($ob['PROPERTY_DP_DISCOUNT_VALUE']) / 100);
          $discountPrice = $price;
          $price = $price * $saleSkd;
          $price = round($price,0);

        }
        // Проверяем, есть ли скидка
        // if ($price != $discountPrice && $discountPrice > 0) {
        if ( $discountPrice > 0) {
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

if (!empty($arPrint)) {
    //print_r($arPrint);
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
            justify-content: center;
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
            gap:5px;
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
                                    <? if ( $item['PRICE'] != $item['NEW_PRICE'] ):?>
                                    <span style="font-weight: 500;text-decoration: line-through;"><?=$item['PRICE']?>,00</span>
                                  <? endif; ?>
                                    <span><?=$item['NEW_PRICE']?>,00</span>
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
<?} else {
  $iblockID = 16; // Замените на ID вашего инфоблока
  $arFilter = array(
      "IBLOCK_ID" => $iblockID,
      "PROPERTY_CML2_ARTICLE" => $models,
  );
  $res = CIBlockElement::GetList(array(), $arFilter, false, false, array('ID','IBLOCK_ID','NAME','PROPERTY_CML2_ARTICLE'));
  while ($ob = $res->GetNext()){
      $elementID = $ob["ID"];
      $arPrint[$elementID] = [
          'ARTICLE' => $ob["PROPERTY_CML2_ARTICLE_VALUE"],
          'NAME' => $ob['NAME'],
          'PRICE' => $arResult[$ob["PROPERTY_CML2_ARTICLE_VALUE"]]['price']
        ];
  }
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

<?}?>
<script src="https://code.jquery.com/jquery-3.7.1.slim.min.js" integrity="sha256-kmHvs0B+OpCW5GVHUNjv9rOmY0IvSIRcf7zGUDTDQM8=" crossorigin="anonymous"></script>
<script>
    $(document).ready(function() {
        window.print();
    });

</script>
