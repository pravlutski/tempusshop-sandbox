<?php

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");

// // use Yandex\Market;
// // use Bitrix\Main;
// // Main\Loader::includeModule('yandex.market');
//
// $data = [
//     // 'businessId' => 780792,
//     // 'categoryIds' => [932],
//     'campaignId' => 22194883
//     // 'dateFrom' => '01-04-2024',
//     // 'dateTo' => '05-04-2024'
// ];
//
// $url = 'https://api.partner.market.yandex.ru/reports/prices/generate?format=FILE';
// $ch = curl_init($url);
// curl_setopt(
// 			$ch,
// 			CURLOPT_HTTPHEADER,
// 			array(
//         "Content-Type: application/json",
// 				"Authorization: Bearer AQAAAAAENHYaAAdWYzVCd2tdhkeatol5KfPf9uY"
// 			)
// 		);
// curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
// curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
// curl_setopt($ch,CURLOPT_CONNECTTIMEOUT, 30);
// $result = curl_exec($ch);
// curl_close($ch);
// var_dump($result);
// // die;
// // $logPath = '/var/www/bitrix/data/www/tempusshop.ru/admin/modules/forTest/logs/YandexReportLog.txt';
// // file_put_contents($logPath, 'url: ' . $url . PHP_EOL, FILE_APPEND);
// // file_put_contents($logPath, 'request: ' . json_encode($data) . PHP_EOL, FILE_APPEND);
// // file_put_contents($logPath, 'result: ' . $result . PHP_EOL . '\r\n', FILE_APPEND);
// $queueData = json_decode($result, 1);
// // var_dump($queueData);
// sleep( $queueData['result']['estimatedGenerationTime'] / 1000  + 30);
//
// $url = 'https://api.partner.market.yandex.ru/reports/info/' . $queueData['result']['reportId'];
// $ch = curl_init($url);
// curl_setopt(
// 			$ch,
// 			CURLOPT_HTTPHEADER,
// 			array(
// 				"Content-Type: application/json",
//         "Authorization: Bearer AQAAAAAENHYaAAdWYzVCd2tdhkeatol5KfPf9uY"
// 			)
// 		);
// curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
// curl_setopt($ch,CURLOPT_CONNECTTIMEOUT, 30);
// $result = curl_exec($ch);
// curl_close($ch);
//
// // file_put_contents($logPath, 'url: ' . $url . PHP_EOL, FILE_APPEND);
// // file_put_contents($logPath, 'result: ' . $result . PHP_EOL . '\n\r', FILE_APPEND);
//
// var_dump($result);

//Part of updateprice.php
// if( $this->priceID == "YA") )
// {
//   for ($i = 1 ; $i < 3; $i++)
//   {
//     if ( $arItem['price_platform' . $i] != 0 )
//     {
//       if ( $this->getOptimalPrice($arItem["brand_id"], $arItem["price"], $arItem["b_id"]) < $arItem['price_platform' . $i] )
//       {
//         $new_price = $arItem['price_platform' . $i] * 0.99;
//       }
//     }
//   }
// }
// //END of part of updateprice.php
//
// var_dump(json_decode(CProSet::getOption("SETTINGS_RRC"), true)['ya']);
CModule::IncludeModule('maxyss.wb');
CModule::IncludeModule("iblock");
CModule::IncludeModule("panel.manager");

$objMS = new MoyskladAPI("s1");
$arFilter = array(
  "momentFrom" => date("Y-m-d", strtotime("-6 month")),
  //"momentFrom" => date("Y-m-d", strtotime("-1 day")),
  "momentTo" => date("Y-m-d"),
);
$arPosition = $objMS->getListProfitNew($arFilter);

var_dump($arPosition);
 ?>
