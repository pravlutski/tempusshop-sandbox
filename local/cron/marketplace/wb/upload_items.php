#!/usr/bin/php
<?php
//#!/usr/local/php/bin/php -q
//
$_SERVER["DOCUMENT_ROOT"] = "/var/www/bitrix/data/www/tempusshop.ru";
$DOCUMENT_ROOT = $_SERVER["DOCUMENT_ROOT"];

define("NO_KEEP_STATISTIC", true);
define("NOT_CHECK_PERMISSIONS", true);

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");
set_time_limit(0);

CModule::IncludeModule("iblock");
CModule::IncludeModule("main");
CModule::IncludeModule("panel.manager");
CModule::IncludeModule('maxyss.wb');

$objPricelist = new CPanelPricelist;
$objSupplier = new CPanelSupplier;
$objCurrency = new CPanelCurrency;
$objUtils = new CPanelUtils;

foreach ((array)$_SERVER['argv'] as $v){
	list($k,$v) = explode("=",$v);
	if ($k && $v) $_REQUEST[$k] = $v;
}

if(in_array($_REQUEST["cabinet"], array("DEFAULT", "WR"))){
	$cabinet = $_REQUEST["cabinet"];
}else{
	$cabinet = "WR";
}

CProSet::setOption("WB_UPLOAD_ITEMS", "");
CProSet::setOption("WB_UPLOAD_ITEMS_PER", "0");

$arFilter = Array(
	"IBLOCK_ID"	=> 16,
	"ACTIVE"	=> "Y",
	">PROPERTY_WBPRICE" => 0,
	">CATALOG_QUANTITY" => 0,
	"!PROPERTY_PROP_MAXYSS_WB" => false,
);

if($cabinet == "DEFAULT"){
	$arFilter["!PROPERTY_AEN"] = false;
	$arFilter["!PROPERTY_WBARTICLE"] = false;
}else{
	$arFilter["!PROPERTY_AEN2"] = false;
	$arFilter["!PROPERTY_WBARTICLE2"] = false;
}
// $arFilter["!PROPERTY_BRAND"] = '36661';
$arFilter["ID"] = CMaxyssWb::getItemsWB();
// $arFilter["ID"] = 4336;
// $arFilter["XML_ID"]
//$rs = CIBlockElement::GetList(array(), $arFilter, false, false, array("ID", "CODE", "DETAIL_PICTURE", "PROPERTY_aen"));
$rs = CIBlockElement::GetList(array(), $arFilter, false, false, array("ID"));
$cntAll = $rs->SelectedRowsCount();

$arLogDetail = array();
$i = 0;

$arSettings = CMaxyssWb::settings_wb($cabinet);

while($ar = $rs->GetNext()){
    $id_element = $ar["ID"];
    //$item_info = CMaxyssWb::PrepareItem($id_element, $cabinet);
	$item_info = CAddinMaxyssWB::PrepareItemNewApiContent($id_element, $cabinet);
	file_put_contents('/var/www/bitrix/data/www/tempusshop.ru/local/cron/marketplace/wb/dkTestlog.txt', print_r($item_info,1) .PHP_EOL);
    if ($item_info !== false){
		// op
			if ( empty($item_info['nmID']) ){
				$item_info['item']["sizes"][0]["price"] = CMaxyssWb::get_price($arSettings['PRICE_TYPE'], $arSettings['PRICE_PROP'], $arSettings['PRICE_TYPE_PROP'], $arSettings['PRICE_TYPE_NO_DISCOUNT'], $id_element, $lid, $arSettings["PRICE_TYPE_FORMULA"], $arSettings["PRICE_TYPE_FORMULA_ACTION"]);
			}


            /*if($arTovar['WEIGHT'] > 0){
                $item_info['item']["characteristics"][] = array(GetMessage('MAXYSS_WB_CARD_WEIGHT_UPAC')=>intval($arTovar['WEIGHT']));
            }
            if($arTovar['WEIGHT'] > 0){
                $item_info['item']["characteristics"][] = array(GetMessage('MAXYSS_WB_CARD_WEIGHT_UPAC_KG')=>floatval($arTovar['WEIGHT']/1000));
            }
               if($arTovar['WIDTH'] > 0){
                $item_info['item']["characteristics"][] = array(GetMessage('MAXYSS_WB_CARD_WIDTH_UPAC')=>$arTovar['WIDTH']/10);
            }
            if($arTovar['LENGTH'] > 0){
                $item_info['item']["characteristics"][] = array(GetMessage('MAXYSS_WB_CARD_LENGTH_UPAC')=>$arTovar['LENGTH']/10);
            }
            if($arTovar['HEIGHT'] > 0){
                $item_info['item']["characteristics"][] = array(GetMessage('MAXYSS_WB_CARD_HEIGHT_UPAC')=>$arTovar['HEIGHT']/10);
            }*/
            if($item_info['item']["sizes"][0]["skus"][0] == '') {
                $barcode_gen = CMaxyssWb::getBarcodes(1, $arSettings['UUID'], $arSettings['AUTHORIZATION']);
                if (!empty($barcode_gen['data'])) {
//                    $barcode = array_shift($barcode_gen['data']);
                    $barcode = $barcode_gen['data'][0];
                    CIBlockElement::SetPropertyValuesEx($id_element, false, array(
                        $arSettings['SHKOD'] => $barcode,
                    ));
                    $item_info['item']["sizes"][0]["skus"][0] = $barcode;
                }
            }

			// $newChr = array();
			// foreach($item_info["item"]["characteristics"] as $k => &$v){
			// 	foreach($v as $_k => $prop){
			// 		if(is_array($prop) && count($prop) == 1){
			// 			$newChr[$_k] = $prop[0];
			// 			$item_info["item"]["characteristics"][$k][$_k] = $prop[0];
			// 		}else{
			// 			$newChr[$_k] = $prop;
			// 		}
			// 	}
			// }

			if ($item_info["item"]['nmID']) {
				file_put_contents("/var/www/bitrix/data/www/tempusshop.ru/local/cron/marketplace/wb/nmids_upload_res.txt", print_r('---save---' , true).PHP_EOL, FILE_APPEND);
				file_put_contents("/var/www/bitrix/data/www/tempusshop.ru/local/cron/marketplace/wb/nmids_upload_res.txt", print_r($result , true).PHP_EOL, FILE_APPEND);
          //if($_REQUEST['param'] != 'photo') {
              $res_upload = CAddinMaxyssWB::UpdateCadrNewApiContent($item_info['item'], $id_element, $arSettings["AUTHORIZATION"]);
              $res .= $id_element . ' ' . $res_upload . '<br>';
          //}
          //if($res_upload == GetMessage("WB_MAXYSS_PRODUCT_UPLOAD") || $_REQUEST['param'] == 'photo') {
              if (!empty($item_info['img'])) {
                  $res_upload = CAddinMaxyssWB::AddMediaFile($item_info['img'], $item_info['item']['nmID'], $arSettings['AUTHORIZATION']);
                  $res .= $id_element . ' ' . $res_upload . '<br>';
              }
          //}
								// $result = CAddinMaxyssWB::AddRecomends($item_info,$id_element);
								// file_put_contents("/var/www/bitrix/data/www/tempusshop.ru/local/cron/marketplace/wb/nmids_upload_res.txt", print_r('---save---' , true).PHP_EOL, FILE_APPEND);
								// file_put_contents("/var/www/bitrix/data/www/tempusshop.ru/local/cron/marketplace/wb/nmids_upload_res.txt", print_r($result , true).PHP_EOL, FILE_APPEND);
      }
      else
      {
          //if($_REQUEST['param'] != 'photo') {
              if ($arSettings['ARTICLE_LINK'] != '' && $item_info['article_link'] != '') {
								$dataUpload = [
									'subjectID' => 60,
									'variants' => [$item_info['item']]
								];
								$res_upload = CAddinMaxyssWB::UploadCadrNewApiContent($dataUpload, $id_element, $arSettings["AUTHORIZATION"]);
								file_put_contents('/var/www/bitrix/data/www/tempusshop.ru/local/cron/marketplace/wb/uplTESTlog.txt',print_r($res_upload,1).PHP_EOL);
                  // $res_upload = CAddinMaxyssWB::UploadCadrNewApiContent(array('vendorCode' => $item_info['article_link'], 'cards' => array($item_info['item'])), $id_element, $arSettings["AUTHORIZATION"]);
              } else {
								$dataUpload = [
									'subjectID' => 60,
									'variants' => [$item_info['item']]
								];
                  $res_upload = CAddinMaxyssWB::UploadCadrNewApiContent($dataUpload, $id_element, $arSettings["AUTHORIZATION"]);
									file_put_contents('/var/www/bitrix/data/www/tempusshop.ru/local/cron/marketplace/wb/uplTESTlog.txt',print_r($res_upload,1).PHP_EOL);
              }
              $res .= $id_element . ' ' . $res_upload . '<br>';

	          	if(!empty($item_info['img'])) {

								$data_filter = [
									'settings' => [
										'filter' => [
											'textSearch' => $item_info['item']['vendorCode'],
											"withPhoto" => -1,
										],
										'cursor' => ['limit' => 5]
									]
								];
								$ch = curl_init('https://suppliers-api.wildberries.ru/content/v2/get/cards/list?locale=ru');
								curl_setopt(
											$ch,
											CURLOPT_HTTPHEADER,
											array(
												"Content-Type: application/json",
												"Authorization: {$arSettings["AUTHORIZATION"]}"
											)
										);
								curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
								curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data_filter));
								curl_setopt($ch,CURLOPT_CONNECTTIMEOUT, 30);
								$result = curl_exec($ch);
								curl_close($ch);
								$result = json_decode($result,1);
								if ( !empty($result['cards']) ){
								  foreach ($result['cards'] as $card){
								    if ($card['vendorCode'] === $data_filter['settings']['filter']['textSearch']){
								      $nmid = $card['nmID'];
								    }
								  }
								}else{
								  $nmid = '';
								}

                $res_upload = CAddinMaxyssWB::AddMediaFile($item_info['img'], $nmid, $arSettings['AUTHORIZATION']);
                $res .= $id_element . ' ' . $res_upload . '<br>';
								file_put_contents('/var/www/bitrix/data/www/tempusshop.ru/local/cron/marketplace/wb/newCardMedia.txt', print_r($res_upload,1) . PHP_EOL, FILE_APPEND);
              }

          //}
          /*if($res_upload == GetMessage("WB_MAXYSS_PRODUCT_UPLOAD") || $_REQUEST['param'] == 'photo')
              if(!empty($item_info['img'])) {
                      $res_upload = CAddinMaxyssWB::AddMediaFile($item_info['img'], $item_info['item']['VendorCode'], $arSettings['AUTHORIZATION']);
                      $res .= $id_element . ' ' . $res_upload . '<br>';
              }*/
      }

        $tmp = serialize($item_info);

        if(strripos($tmp, "ошибк") !== false || strripos($tmp, "позже") !== false){
			$arLog = array(
				"event" => "WB",
				"text" => "Обмен с WB ошибка",
				"detail" => array(
					"item_info" => encrypt_decrypt(serialize($item_info)),
					"res" => encrypt_decrypt($res),
				),
			);
			CLog::add2log($arLog);
        }

    }
	$i++;

	if($i % 50 == 0){
		$per = ($i / $cntAll) * 100;
		$per = round($per, 2);
		CProSet::setOption("WB_UPLOAD_ITEMS", "Обработано {$i} товаров ({$per} %)");
		CProSet::setOption("WB_UPLOAD_ITEMS_PER", $per);

		$globalPer = 25 + ($per / 100 * 25);
		CProSet::setOption("WB_ALL_CYCLE_PER", round($globalPer, 2));
	}
}
CProSet::setOption("WB_UPLOAD_ITEMS", "Обработано {$i} товаров");
CProSet::setOption("WB_UPLOAD_ITEMS_PER", 100);

/*
if(count($arLogDetail) > 0){
	$arLog = array(
		"event" => "WB",
		"text" => "Обмен с WB",
		"detail" => $arLogDetail,
	);
	CLog::add2log($arLog);
}*/

//require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/epilog_after.php");
?>
