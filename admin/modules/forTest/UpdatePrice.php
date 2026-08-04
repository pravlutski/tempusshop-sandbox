<?php
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");
CModule::IncludeModule('maxyss.wb');
$arSettings = CMaxyssWb::settings_wb('WR');


$item = prepareAllItemsPrice($arSettings, array(), 'WR');
$result = setPrices($arSettings['AUTHORIZATION'], $item['prices']);

print_r($result);

 function setPrices($Authorization = false, $items, $attempt = 1){

    if(!$Authorization) $Authorization = CMaxyssWb::get_setting_wb("AUTHORIZATION", "DEFAULT");
if($attempt > 3) return;
// if($attempt == 1){
//   fopen(self::$fileExclude, "w+");
// }
    // $event = new \Bitrix\Main\Event(MAXYSS_WB_NAME, "OnPriceUpload", array(&$items, $Authorization));
    // $event->send();



    $arResult = array();
    $items_chunk = array_chunk($items, 1000);

    file_put_contents($_SERVER['DOCUMENT_ROOT'].'/upload/log_items.txt', print_r($items_chunk, true));
    $err = '';
$arExclude = array();
    foreach ($items_chunk as $c) {
        $data_string['data'] = $c;
        file_put_contents('/var/www/bitrix/data/www/tempusshop.ru/admin/modules/forTest/logs/data_string.txt', print_r($data_string, true) . PHP_EOL, FILE_APPEND);
        $data_string = json_encode($data_string);
        file_put_contents('/var/www/bitrix/data/www/tempusshop.ru/admin/modules/forTest/logs/data_string.txt', print_r($data_string, true) . PHP_EOL, FILE_APPEND);
        $bck = CMaxyssWb::bck_wb();

        if($bck['BCK'] && $bck['BCK'] != "Y") {
            $res = CRestQueryWB::rest_query_na('https://discounts-prices-api.wb.ru', $data_string, "/api/v2/upload/task", $Authorization);
            // $url = 'https://discounts-prices-api.wb.ru/api/v2/upload/task';
            // $ch = curl_init($url);
            // curl_setopt(
            // 			$ch,
            // 			CURLOPT_HTTPHEADER,
            // 			array(
            // 				"Content-Type: application/json",
            // 				"Authorization: {$Authorization}"
            // 			)
            // 		);
            // curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            // curl_setopt($ch, CURLOPT_POSTFIELDS, $data_string);
            // curl_setopt($ch,CURLOPT_CONNECTTIMEOUT, 30);
            // $res = curl_exec($ch);
            // curl_close($ch);

            $result = array();
            if($res !='')
                $result = json_decode($res,1);
                file_put_contents('/var/www/bitrix/data/www/tempusshop.ru/admin/modules/forTest/logs/updateResultWb.txt', print_r($res, true) . PHP_EOL, FILE_APPEND);
            if(!is_set($result['error'])){
                // $eventLog = new \CEventLog;
                // $eventLog->Add(array("SEVERITY" => 'INFO', "AUDIT_TYPE_ID" => 'setPrices', "MODULE_ID" => MAXYSS_WB_NAME, "ITEM_ID" => "PRICE", "DESCRIPTION" => "upload price success" ));
            }else{
                $res_error = json_decode($result['error'],1);

      // запоминаем ошибки. при следующем запуске исключаем их
                foreach ($res_error['errors'] as $val){
                    $err .= $val;
        //Рост более 30 процентов
        if(strripos($val, "Рост более ")){
          preg_match("/\[(.*?)\]/ism", $val, $output);
          if($output[1]){
            $list = explode(" ", $output[1]);
            if(count($list) > 0){
              $arExclude = array_merge($arExclude, $list);
            }
          }

        }
                }


//                    if(LANG_CHARSET != 'utf-8') {
//                        $arResult["error"] = \Bitrix\Main\Text\Encoding::convertEncoding(
//                            $result['errors'],
//                            'utf-8',
//                            'windows-1251',
//                            $errorMessage = ""
//                        );
//                    }else{
                    $arResult["error"] = $err." ";
//                    }
                // $eventLog = new \CEventLog;
                // $eventLog->Add(array("SEVERITY" => 'INFO', "AUDIT_TYPE_ID" => 'setPrices', "MODULE_ID" => MAXYSS_WB_NAME, "ITEM_ID" => "PRICE", "DESCRIPTION" => implode(', ', $res_error['errors']) ));
            }
        }else{
    // file_put_contents("/home/bitrix/logs/wb/wb_err.txt", print_r(json_decode($c, true), true) . "\r\n", FILE_APPEND | LOCK_EX);
    // file_put_contents("/home/bitrix/logs/wb/wb_err.txt", print_r(json_decode($bck, true), true) . "\r\n", FILE_APPEND | LOCK_EX);
  }
    }

// if(count($arExclude) > 0){
//   foreach($arExclude as $nmId){
//     file_put_contents(self::$fileExclude, $nmId . "\r\n", FILE_APPEND | LOCK_EX);
//   }
//   // CMaxyssWbprice::prepareSetPrice($items);
//   $eventLog->Add(array("SEVERITY" => 'INFO', "AUDIT_TYPE_ID" => 'setPrices', "MODULE_ID" => MAXYSS_WB_NAME, "ITEM_ID" => "PRICE", "DESCRIPTION" => "Найдено " . count($arExclude) . " ошибок. Шлем без них. Попытка - {$attempt}." ));
//
//   self::setPrices($Authorization, $items, $attempt + 1);
// }


    return $arResult;
}

function prepareAllItemsPrice($arSettings, $arrFilter = array(), $cabinet = "DEFAULT"){
//end edit
    $cabinet =  $arSettings['LK'];
    $IBLOCK_ID = $arSettings["IBLOCK_ID"];
    //edit
    // $arProp = self::getPropsSync($cabinet);
    // $arID = self::getItemsWB();
    //end edit
    $arInfoOff = CCatalogSKU::GetInfoByProductIBlock($IBLOCK_ID);

    $item_price = array();
    $item_discounts_revoke = array();
    $item_discounts = array();
    $item_promocodes = array();
    $item_promocodes_revoke = array();

    $arSelect = Array("ID", "IBLOCK_ID", "NAME", "PROPERTY_PROP_MAXYSS_CARDID_WB", "PROPERTY_PROP_MAXYSS_NMID_CREATED_WB", "PROPERTY_PROP_MAXYSS_CHRTID_CREATED_WB", "PROPERTY_PROP_MAXYSS_PROMOCODES_WB", "PROPERTY_PROP_MAXYSS_DISCOUNTS_WB");
    //edit

    $arFilter = Array(
        "IBLOCK_ID" => intval($IBLOCK_ID),
        "ACTIVE" => "Y",
        "ID" => 183288,
    );
//end edit

    $arCustomFilter = array();
    if($arSettings["CUSTOM_FILTER"]) {
        $filter_custom = new FilterCustomWB();
        $arCustomFilter = $filter_custom->parseCondition( json_decode( htmlspecialchars_decode($arSettings["CUSTOM_FILTER"]),1) , array() );
    }
    elseif ($arSettings['FILTER_PROP'] != '' && $arSettings['FILTER_PROP_ID'] != '')
        $arFilter['PROPERTY_' . $arSettings['FILTER_PROP']] = $arSettings['FILTER_PROP_ID'];

    if(!empty($arrFilter)){
        $arFilter = array_merge($arFilter, $arrFilter);
    }
    //lg
    // file_put_contents("/var/www/bitrix/data/www/tempusshop.ru/bitrix/modules/maxyss.wb/last_send.txt", print_r($arFilter, true));
    $res = CIBlockElement::GetList(Array("ID" => "ASC"), $arFilter, false, false, $arSelect);
    $arIds = array();
    while ($ob = $res->GetNextElement()) {
        $ar_tovar = array();
        $arFields = $ob->GetFields();
        $arProps = $ob->GetProperties();
        $key_cabinet = false;

        if(!isset($arIds[$arFields['ID']])) {
            $arIds[$arFields['ID']] = $arFields['ID'];
            $ar_tovar = CCatalogProduct::GetByID($arFields["ID"]); // item as product
            if ($ar_tovar["TYPE"] == 1) {
                $item_nmId = 0;
                //edit
                $keyNMID = $keyCHRTID = false;
                if(is_array($arProps['PROP_MAXYSS_NMID_CREATED_WB']["DESCRIPTION"])){
                  $keyNMID = array_search($cabinet, $arProps['PROP_MAXYSS_NMID_CREATED_WB']["DESCRIPTION"]);
                }

                if(is_array($arProps['PROP_MAXYSS_CHRTID_CREATED_WB']["DESCRIPTION"])){
                  $keyCHRTID = array_search($cabinet, $arProps['PROP_MAXYSS_CHRTID_CREATED_WB']["DESCRIPTION"]);
                }

                if ($keyNMID !== false && $keyCHRTID !== false && $arProps['PROP_MAXYSS_NMID_CREATED_WB']['VALUE'][$keyNMID] > 0 && $arProps['PROP_MAXYSS_CHRTID_CREATED_WB']['VALUE'][$keyCHRTID] > 0) {
                  $item_nmId = intval($arProps['PROP_MAXYSS_NMID_CREATED_WB']['VALUE'][$keyNMID]);
                }
                // if(is_array($arProps['PROP_MAXYSS_CARDID_WB']["DESCRIPTION"]))
                //     $key_cabinet = array_search($cabinet, $arProps['PROP_MAXYSS_CARDID_WB']["DESCRIPTION"]);
                // if($cabinet == "DEFAULT" && $key_cabinet===false && is_array($arProps['PROP_MAXYSS_CARDID_WB']["DESCRIPTION"])){
                //     $key_cabinet = array_search('', $arProps['PROP_MAXYSS_CARDID_WB']["DESCRIPTION"]);
                // }
                // if($key_cabinet !== false) {
                //     if ($arProps['PROP_MAXYSS_NMID_CREATED_WB']['VALUE'][$key_cabinet] > 0 && $arProps['PROP_MAXYSS_CHRTID_CREATED_WB']['VALUE'][$key_cabinet] > 0) {
                //         $item_nmId = intval($arProps['PROP_MAXYSS_NMID_CREATED_WB']['VALUE'][$key_cabinet]);
                //     }
                // }
                //end edit
                if ($item_nmId > 0) {

                    $price = array(
                        "nmID" => $item_nmId,
                        "price" => get_price($arSettings['PRICE_TYPE'], $arSettings['PRICE_PROP'], $arSettings['PRICE_TYPE_PROP'], $arSettings['PRICE_TYPE_NO_DISCOUNT'], $arFields['ID'], $arSettings["SITE"], $arSettings["PRICE_TYPE_FORMULA"], $arSettings["PRICE_TYPE_FORMULA_ACTION"]),
                        // "price" => 11110
                    );
                    $item_price[] = $price;

                    // discounts
                    if (intval($arFields["PROPERTY_PROP_MAXYSS_DISCOUNTS_WB_VALUE"]) > 0) {
                        // ��������� ������
                        $item_discounts[] = array(
                            "discount" => intval($arFields["PROPERTY_PROP_MAXYSS_DISCOUNTS_WB_VALUE"]),
                            "nm" => $item_nmId,
                        );
                    } else {
                        // ����� ������
                        $item_discounts_revoke[] = $item_nmId;
                    }

//                        // promocodes
//                        if (intval($arFields["PROPERTY_PROP_MAXYSS_PROMOCODES_WB_VALUE"]) > 0) {
//                            // ��������� ����������
//                            $item_promocodes[] = array(
//                                "discount" => intval($arFields["PROPERTY_PROP_MAXYSS_PROMOCODES_WB_VALUE"]),
//                                "nm" => intval($arFields["PROPERTY_PROP_MAXYSS_NMID_CREATED_WB_VALUE"]),
//                            );
//                        } else {
//                            // ����� ����������
//                            $item_promocodes_revoke[] = intval($arFields["PROPERTY_PROP_MAXYSS_NMID_CREATED_WB_VALUE"]);
//                        }

                }
            }
            elseif ($ar_tovar["TYPE"] == 3)
            {
                if (is_array($arInfoOff)) {

                    $arSelectOff = Array("ID", "IBLOCK_ID", "NAME", "PROPERTY_PROP_MAXYSS_NMID_CREATED_WB", "PROPERTY_PROP_MAXYSS_CHRTID_CREATED_WB", "PROPERTY_PROP_MAXYSS_PROMOCODES_WB", "PROPERTY_PROP_MAXYSS_DISCOUNTS_WB");
                    $rsOffers = CIBlockElement::GetList(array(), array('IBLOCK_ID' => $arInfoOff['IBLOCK_ID'], "!PROPERTY_PROP_MAXYSS_NMID_CREATED_WB" => false, "!PROPERTY_PROP_MAXYSS_CHRTID_CREATED_WB" => false, "ACTIVE" => "Y", 'PROPERTY_' . $arInfoOff['SKU_PROPERTY_ID'] => $arFields["ID"]), false, false, $arSelectOff);
                    $arItems = array();
                    while ($arOffer = $rsOffers->GetNextElement()) {
                        $item_off_nmId = 0; $chrtID = 0;
                        $key_cabinet_prop = false;
                        $arFieldsOff = $arOffer->GetFields();
                        $arPropOff = $arOffer->GetProperties();
                        if(!isset($arIds[$arFieldsOff['ID']])) {
                            $arIds[$arFieldsOff['ID']] = $arFieldsOff['ID'];

                            if (is_array($arPropOff['PROP_MAXYSS_CHRTID_CREATED_WB']['DESCRIPTION']))
                                $key_cabinet_prop = array_search($cabinet, $arPropOff['PROP_MAXYSS_CHRTID_CREATED_WB']['DESCRIPTION']);

                            if($key_cabinet_prop === false && $cabinet == 'DEFAULT' && is_array($arPropOff['PROP_MAXYSS_CHRTID_CREATED_WB']['DESCRIPTION']))
                                $key_cabinet_prop = array_search('', $arPropOff['PROP_MAXYSS_CHRTID_CREATED_WB']['DESCRIPTION']);

                            if ($key_cabinet_prop !== false && $arPropOff['PROP_MAXYSS_CHRTID_CREATED_WB']['VALUE'][$key_cabinet_prop] != '' && $arPropOff['PROP_MAXYSS_NMID_CREATED_WB']['VALUE'][$key_cabinet_prop] != '') {
                                $chrtID = intval($arPropOff['PROP_MAXYSS_CHRTID_CREATED_WB']['VALUE'][$key_cabinet_prop]);
                                $item_off_nmId = intval($arPropOff['PROP_MAXYSS_NMID_CREATED_WB']['VALUE'][$key_cabinet_prop]);
                            }
                            if ($item_off_nmId > 1 && $chrtID > 1) {
                                $arItems[$item_off_nmId][$arFieldsOff['ID']] = $arFieldsOff;
                            }
                        }
                    }
                    if (!empty($arItems)) {
                        foreach ($arItems as $key => $i) {

                            foreach ($i as $c) {

                                $tp_price[] = get_price($arSettings['PRICE_TYPE'], $arSettings['PRICE_PROP'], $arSettings['PRICE_TYPE_PROP'], $arSettings['PRICE_TYPE_NO_DISCOUNT'], $c['ID'], $arSettings["SITE"], $arSettings["PRICE_TYPE_FORMULA"], $arSettings["PRICE_TYPE_FORMULA_ACTION"]);

                                $tp_discounts[] = intval($c["PROPERTY_PROP_MAXYSS_DISCOUNTS_WB_VALUE"]);

//                                    $tp_promocodes[] = intval($c["PROPERTY_PROP_MAXYSS_PROMOCODES_WB_VALUE"]);
                            }
                            if ($arSettings['PRICE_MAX_MIN'] == 'MAX') {
                                $price = max($tp_price);
                            } else {
                                $price = min($tp_price);
                            }
                            $price = array(
                                "nmID" => intval($key),
                                "price" => $price,
                                // "price" => 11110

                            );
                            $item_price[] = $price;


                            // discounts

                            if ($arSettings['PRICE_MAX_MIN'] == 'MAX') {
                                $discount = min($tp_discounts);
                            } else {
                                $discount = max($tp_discounts);
                            }
                            if ($discount > 0) {
                                // ��������� ������
                                $discounts = array(
                                    "discount" => $discount,
                                    "nmID" => intval($key),
                                );
                                $item_discounts[] = $discounts;
                            } else {
                                // ����� ������
                                $item_discounts_revoke[] = intval($key);
                            }

                            // promocodes

//                                if ($arSettings['PRICE_MAX_MIN'] == 'MAX') {
//                                    $promocode = min($tp_promocodes);
//                                } else {
//                                    $promocode = max($tp_promocodes);
//                                }
//
//                                if ($promocode > 0) {
//                                    // ��������� ����������
//                                    $item_promocodes[] = array(
//                                        "discount" => $promocode,
//                                        "nm" => $key,
//                                    );
//                                } else {
//                                    // ����� ����������
//                                    $item_promocodes_revoke[] = $key;
//                                }

                            unset($tp_promocodes, $tp_discounts, $tp_price);
                        }
                    }
                }
            }
        }


    }
    $forReturn = array("prices"=>$item_price, "discounts"=>$item_discounts, "discounts_revoke"=>$item_discounts_revoke, "promocodes"=>$item_promocodes,"promocodes_revoke"=>$item_promocodes_revoke );

    file_put_contents('/var/www/bitrix/data/www/tempusshop.ru/admin/modules/forTest/logs/dataUpdateResultWb.txt', var_export($forReturn, true) . PHP_EOL, FILE_APPEND);

    return $forReturn;
}

function get_price($type, $prop, $type_prop, $no_discount, $product_id, $lid, $formula, $formula_action){

    $formula = floatval(str_replace(',', '.', $formula));
    if($prop=="Y"){
        // for property
        $arSelect = Array("ID", "IBLOCK_ID", "NAME", "PROPERTY_".$type_prop);
        $arFilter = Array("ID"=>$product_id);
        $res = CIBlockElement::GetList(Array('ID'=>'asc'), $arFilter, false, false, $arSelect);

        if($ob = $res->GetNextElement())
        {
            $arFields = $ob->GetFields();
            if($formula != '' && $formula_action != 'NOT'){
                switch ($formula_action){

                    case 'ADD':
                        $result = $arFields['PROPERTY_'.strtoupper($type_prop).'_VALUE'] + $formula;
                        break;

                   case 'MULTIPLY':
                        $result = $arFields['PROPERTY_'.strtoupper($type_prop).'_VALUE'] * $formula;
                        break;

                   case 'DIVIDE':
                        $result = $arFields['PROPERTY_'.strtoupper($type_prop).'_VALUE'] / $formula;
                        break;

                   case 'SUBTRACT':
                        $result = $arFields['PROPERTY_'.strtoupper($type_prop).'_VALUE'] - $formula;
                        break;

                    default:
                        break;
                }
            }
            else
            {
                $result = $arFields['PROPERTY_'.strtoupper($type_prop).'_VALUE'];
            }

        }else{
            $result = 0;
        }

    }else{
        // for price

        $selectedPriceType = 0;
        if (!empty($type)) {
            $price = (int)$type;
            if ($price > 0) {
                $priceIterator = Catalog\GroupAccessTable::getList([
                    'select' => ['CATALOG_GROUP_ID'],
                    'filter' => ['=CATALOG_GROUP_ID' => $price]
                ]);
                $priceType = $priceIterator->fetch();
                if (empty($priceType))
                    $arErrors[] = GetMessage('WB_MAXYSS_ERROR_PRICE');
                else
                    $selectedPriceType = $price;
                unset($priceType, $priceIterator);
            } else {
                $arErrors[] = GetMessage('WB_MAXYSS_ERROR_PRICE');
            }
        }


        if($selectedPriceType > 0) {
            $priceFilter = [
                '@PRODUCT_ID' => $product_id,
                [
                    'LOGIC' => 'OR',
                    '<=QUANTITY_FROM' => 1,
                    '=QUANTITY_FROM' => null
                ],
                [
                    'LOGIC' => 'OR',
                    '>=QUANTITY_TO' => 1,
                    '=QUANTITY_TO' => null
                ]
            ];
            if ($selectedPriceType > 0)
                $priceFilter['=CATALOG_GROUP_ID'] = $selectedPriceType;

            $iterator = Catalog\PriceTable::getList([
                'select' => ['ID', 'PRODUCT_ID', 'CATALOG_GROUP_ID', 'PRICE', 'CURRENCY'],
                'filter' => $priceFilter
            ]);
            $offerLinks = array();
            while ($price = $iterator->fetch()) {
                $id = (int)$price['PRODUCT_ID'];
                $priceTypeId = (int)$price['CATALOG_GROUP_ID'];
                $offerLinks[$id]['PRICES'][$priceTypeId] = $price;
                unset($priceTypeId, $id);
            }

            foreach ($offerLinks as $key => $row) {
                $arPrice = CCatalogProduct::GetOptimalPrice(
                    $key,
                    1,
                    array(2),
                    'N',
                    $row['PRICES'],
                    $lid,
                    array()
                );
            }

            if ($no_discount == "Y") {
                if($formula != '' && $formula_action != 'NOT'){
                    switch ($formula_action){
                        case 'ADD':
                            $result = round($arPrice['RESULT_PRICE']['BASE_PRICE'] + $formula, 0);
                            break;

                        case 'MULTIPLY':
                            $result = round($arPrice['RESULT_PRICE']['BASE_PRICE'] * $formula, 0);
                            break;

                        case 'DIVIDE':
                            $result = round($arPrice['RESULT_PRICE']['BASE_PRICE'] / $formula, 0);
                            break;

                        case 'SUBTRACT':
                            $result = round($arPrice['RESULT_PRICE']['BASE_PRICE'] - $formula, 0);
                            break;

                        default:
                            break;
                    }
                }
                else
                {
                    $result = round($arPrice['RESULT_PRICE']['BASE_PRICE'], 0);
                }
            } else {
                if($formula != '' && $formula_action != 'NOT'){
                    switch ($formula_action){
                        case 'ADD':
                            $result = round($arPrice['RESULT_PRICE']['DISCOUNT_PRICE'] + $formula, 0);
                            break;

                        case 'MULTIPLY':
                            $result = round($arPrice['RESULT_PRICE']['DISCOUNT_PRICE'] * $formula, 0);
                            break;

                        case 'DIVIDE':
                            $result = round($arPrice['RESULT_PRICE']['DISCOUNT_PRICE'] / $formula, 0);
                            break;

                        case 'SUBTRACT':
                            $result = round($arPrice['RESULT_PRICE']['DISCOUNT_PRICE'] - $formula, 0);
                            break;

                        default:
                            break;
                    }
                }
                else
                {
                    $result = round($arPrice['RESULT_PRICE']['DISCOUNT_PRICE'], 0);
                }
            }
        }else{
            $result = 0;
        }
    }

    return intval($result);
}

 ?>
