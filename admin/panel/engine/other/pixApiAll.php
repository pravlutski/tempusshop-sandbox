<?php
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
$dbPanel = new DBPanel;

$xmlIds = file_get_contents('/var/www/bitrix/data/www/tempusshop.ru/admin/panel/engine/other/ids.json');
$data = json_decode( $xmlIds, true );
$chunks = array_chunk($data, 328);
$chunkId = isset($argv[1]) ? (int)$argv[1] : 999;

if ( !isset( $chunks[$chunkId] ) ) die("No chunk with defined key {$chunkId}\n");


$arSelect = Array("ID","IBLOCK_ID","IBLOCK_SECTION_ID","PROPERTY_CML2_ARTICLE","PROPERTY_INFOGRAPH_BASE","DETAIL_PICTURE");
$arFilter = Array(
    "IBLOCK_ID" => CProSet::IB_CATALOG,
    "PROPERTY_OZON_ACTIVE_VALUE" => 'Да',
    // "PROPERTY_INFOGRAPH_BASE" => false,
    'ID' => $chunks[ $chunkId ]
);

$result = CIBlockElement::GetList(array(), $arFilter, false, false, $arSelect);

$i = 0;
while ($el = $result->GetNext()){
  $command = "php /var/www/bitrix/data/www/tempusshop.ru/admin/panel/engine/other/pixApi.php " . (int)$el['ID'] . " " . (int)$chunkId;
  $res = shell_exec($command);
  usleep( 300000 );
  $i++;

}
print_r($i);



/*
while ($el = $result->GetNext()){

    if (!empty($el['DETAIL_PICTURE']) && empty($el['PROPERTY_INFOGRAPH_BASE_VALUE'])) {
          $ch = curl_init('https://api.pixian.ai/api/v2/remove-background');
          $imageUrl = 'https://tempusshop.ru'.CFile::GetPath($el['DETAIL_PICTURE']);

          curl_setopt($ch, CURLOPT_POST, true);
          curl_setopt($ch, CURLOPT_HTTPHEADER,
              array('Authorization: Basic cHh6YjY5aDI3M3NlOTluOjlvdGo2bmlqNmljM3RoNjl2cDBycXVjYjFvOTdpbml0YjdpYzk1dGFzMG1taHI5bzRmMjE='));
          curl_setopt($ch, CURLOPT_POSTFIELDS,
              array(
                'image.url' => $imageUrl,
              ));
          curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
          curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);

          $data = curl_exec($ch);
          if (curl_getinfo($ch, CURLINFO_HTTP_CODE) == 200) {
            file_put_contents("/var/www/bitrix/data/www/tempusshop.ru/admin/panel/engine/other/tmp/pixian_result.png", $data);


            $file = new \CFile;
    				$fileId = $file->SaveFile(CFile::MakeFileArray('/var/www/bitrix/data/www/tempusshop.ru/admin/panel/engine/other/tmp/pixian_result.png'),'info_graph_base');
    				if (!empty($fileId)) {
    					CIBlockElement::SetPropertyValueCode($el['ID'], "INFOGRAPH_BASE", array('VALUE' => $fileId));
            }
            echo "Good: " . $fileId;
          } else {
            echo "Error: " . $data;
          }
          curl_close($ch);
    }
}
*/
