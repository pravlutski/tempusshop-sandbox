<?php
$_SERVER["DOCUMENT_ROOT"] = "/var/www/bitrix/data/www/tempusshop.ru";
$DOCUMENT_ROOT = $_SERVER["DOCUMENT_ROOT"];

define("NO_KEEP_STATISTIC", true);
define("NOT_CHECK_PERMISSIONS", true);

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");
set_time_limit(600);

CModule::IncludeModule("iblock");
CModule::IncludeModule("main");
CModule::IncludeModule("panel.manager");
CModule::IncludeModule('maxyss.wb');

$objPricelist = new CPanelPricelist;
$objSupplier = new CPanelSupplier;
$objCurrency = new CPanelCurrency;
$objUtils = new CPanelUtils;
$dbPanel = new DBPanel;

$logFile = '/var/www/bitrix_logs/debug/pixApi_debug.log';
$pid = getmypid();
$startTime = time();

//$IDS = array($argv[1]);
$productId = isset($argv[1]) ? (int)$argv[1] : 0;
var_dump($productId);
$chunkNum = isset( $argv[2] ) ? $argv[2] : '';
//$IDS = array(210007);
file_put_contents($logFile,
    "[" . date('Y-m-d H:i:s') . "][PID:$pid] START processing ID: $productId\n",
    FILE_APPEND
);

$arSelect = Array("ID","IBLOCK_ID","IBLOCK_SECTION_ID","PROPERTY_CML2_ARTICLE","PROPERTY_INFOGRAPH_BASE","DETAIL_PICTURE");
$arFilter = Array(
	"IBLOCK_ID" => CProSet::IB_CATALOG,
	//"ID" => $IDS,
	"ID" => $productId,
);

$result = CIBlockElement::GetList(array(), $arFilter, false, false, $arSelect);

$trickField = 'PROPERTY_INFOGRAPH_BASE_VALUE';
if ( $chunkNum !== '' ){
  $trickField = 'tricked';
}

while ($el = $result->GetNext()){

    if (!empty($el['DETAIL_PICTURE']) && empty($el[$trickField])) {
			$ch = curl_init('https://api.pixian.ai/api/v2/remove-background');
			$imageUrl = 'https://tempusshop.ru'.CFile::GetPath($el['DETAIL_PICTURE']);

			curl_setopt($ch, CURLOPT_POST, true);
			curl_setopt($ch, CURLOPT_HTTPHEADER,
				array('Authorization: Basic cHh6YjY5aDI3M3NlOTluOjlvdGo2bmlqNmljM3RoNjl2cDBycXVjYjFvOTdpbml0YjdpYzk1dGFzMG1taHI5bzRmMjE=')
			);
			curl_setopt($ch, CURLOPT_POSTFIELDS,
				array(
					'image.url' => $imageUrl,
				)
			);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);

			curl_setopt($ch, CURLOPT_TIMEOUT, 180);
			curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);

			file_put_contents($logFile,
				"[" . date('Y-m-d H:i:s') . "][PID:$pid] Sending curl request for ID: $productId, URL: $imageUrl\n",
				FILE_APPEND
			);

      for ( $i = 0; $i < 5; $i++ ){
        $data = curl_exec($ch);
        if ( curl_getinfo($ch, CURLINFO_HTTP_CODE) == 200 ){
          $filePath = "/var/www/bitrix/data/www/tempusshop.ru/admin/panel/engine/other/tmp/pixian_result{$chunkNum}.png";
  				file_put_contents($filePath, $data);
          if ( filesize($filePath) <= 0 ) {
            usleep( 500000 );
            continue;
          }
          break;
        }
      }

			if (curl_getinfo($ch, CURLINFO_HTTP_CODE) == 200) {
        $filePath = "/var/www/bitrix/data/www/tempusshop.ru/admin/panel/engine/other/tmp/pixian_result{$chunkNum}.png";
        var_dump($filePath);
				file_put_contents($filePath, $data);


				$file = new \CFile;
				$fileId = $file->SaveFile(CFile::MakeFileArray($filePath),'info_graph_base');
				if (!empty($fileId)) {
					CIBlockElement::SetPropertyValueCode($el['ID'], "INFOGRAPH_BASE", array('VALUE' => $fileId));
					file_put_contents($logFile,
						"[" . date('Y-m-d H:i:s') . "][PID:$pid] Success for ID: $productId, FileID: $fileId\n",
						FILE_APPEND
					);
				}
				echo "Good: " . $fileId;
        file_put_contents(
          "/var/www/bitrix/data/www/tempusshop.ru/admin/panel/engine/other/logs/log_good{$chunkNum}.txt",
          $productId . PHP_EOL,
          FILE_APPEND
        );
        usleep(500000);
			} else {
				echo "Error: " . $data;
        file_put_contents(
          "/var/www/bitrix/data/www/tempusshop.ru/admin/panel/engine/other/logs/log_error{$chunkNum}.txt",
          print_r( $data, true ) . PHP_EOL,
          FILE_APPEND
        );
        sleep(7);
			}
          curl_close($ch);
    }
}
