#!/usr/bin/php
<?php
$_SERVER["DOCUMENT_ROOT"] = "/var/www/bitrix/data/www/tempusshop.ru";
$DOCUMENT_ROOT = $_SERVER["DOCUMENT_ROOT"];

define("NO_KEEP_STATISTIC", true);
define("NOT_CHECK_PERMISSIONS", true);

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");
require($_SERVER["DOCUMENT_ROOT"] . "/vendor/autoload.php");
set_time_limit(0);

use Bitrix\Main\Application,
	Bitrix\Main\Loader;
use Intervention\Image\ImageManagerStatic as Image;

class InfoGraph{
	public function __construct(){
		global $DB;
		$this->loadModules();

		$this->db = $DB;

	}

	private function loadModules(){
		Loader::includeModule("main");
		Loader::includeModule("iblock");
    }

	public function run(){

		foreach ((array)$_SERVER['argv'] as $v){
			list($k,$v) = explode("=",$v);
			if ($k && $v) $request[$k] = $v;
		}

		$this->getItems();
        $this->createInfoGraph();
	}

	public function getItems(){
    file_put_contents("/var/www/bitrix/data/www/tempusshop.ru/local/cron/infograph/log.txt", print_r('START'.date('Y-m-d H:i:s'), true).PHP_EOL, FILE_APPEND);

    $arSelect = Array("ID", "PROPERTY_COLLECTION", "PROPERTY_BRAND", "PROPERTY_CML2_ARTICLE", "PROPERTY_COLORTERM","PROPERTY_IMAGE_MARKETPLACE","PROPERTY_NAME_MARKETPLACE","PROPERTY_126","DETAIL_PICTURE","TIMESTAMP_X", "CATALOG_QUANTITY","PROPERTY_DESC_RICH_OZON","PROPERTY_MECHANISM","PROPERTY_GLASS","PROPERTY_CASE","PROPERTY_WR","PROPERTY_FACE","PROPERTY_DATE_LAST_STOCK",
    "PROPERTY_WARRANTY","PROPERTY_INFO_WB_IMAGE");
    // $arFilter = Array(
    //   "IBLOCK_ID" => CProSet::IB_CATALOG,

    //   //"ID" => 178901
		// 	"ACTIVE" => "Y",
		// 	"!PROP_MAXYSS_NMID_CREATED_WB" => false
    // );

		$arFilter = Array(
			"IBLOCK_ID"	=> 16,
		  //"PROPERTY_CML2_ARTICLE" => array(),
            "ID" => 181717

		);
		// var_dump(count($arFilter['XML_ID']));
		// die;
//$arFilter['ID'] = 174809;
		//$arFilter["ID"] = CMaxyssWb::getItemsWB();
    //Array("nPageSize"=>50)
    $result = CIBlockElement::GetList(array(), $arFilter, false, false, $arSelect);
    while ($el = $result->GetNext()){

      $this->items[] = [
        "ID" => $el["ID"],
        "BRAND_ID" => $el["PROPERTY_BRAND_VALUE"],
        "COLLECTION" => $el["PROPERTY_COLLECTION_VALUE"],
        "ARTICLE" => $el["PROPERTY_CML2_ARTICLE_VALUE"],
        "COLLECTION_UPDATE" => $el["PROPERTY_CML2_ARTICLE_VALUE"],
        "COLORTERM" => $el["PROPERTY_COLORTERM_VALUE"],
        'TYPE' => array_shift($el['PROPERTY_126_VALUE']),
        'DETAIL_PICTURE' => $el['DETAIL_PICTURE'],
        "TIMESTAMP_X" => $el['TIMESTAMP_X'],
        "CATALOG_QUANTITY" => $el['CATALOG_QUANTITY'],
        "COLORTERM_UPDATE" => "",
        "IMAGE_MARKETPLACE" => $el['PROPERTY_IMAGE_MARKETPLACE_VALUE'],
        "NAME_MARKETPLACE" => $el['PROPERTY_NAME_MARKETPLACE_VALUE'],
        "RICH_DESC" => $el['PROPERTY_DESC_RICH_OZON_VALUE']['TEXT'],
        "MECHANISM" => $el['PROPERTY_MECHANISM_VALUE'],
        "GLASS" => $el['PROPERTY_GLASS_VALUE'],
        "CASE" => array_shift($el['PROPERTY_CASE_VALUE']),
        "WR" => $el['PROPERTY_WR_VALUE'],
        "FACE" => $el['PROPERTY_FACE_ENUM_ID'],
        "WARRANTY" => $el['PROPERTY_WARRANTY_VALUE'],
        "INFO_WB_IMAGE" => $el['PROPERTY_INFO_WB_IMAGE_VALUE'],
        "LAST_STOCK" => $el['PROPERTY_DATE_LAST_STOCK_VALUE'],
      ];

    }
		print_r(count($this->items));
    file_put_contents("/var/www/bitrix/data/www/tempusshop.ru/local/cron/infograph/log.txt", print_r('Всего эл-ов:'.count($this->items) , true).PHP_EOL, FILE_APPEND);
	}

    public function createInfoGraph() {
    $manager = new Intervention\Image\ImageManager(['driver' => 'gd']);

    foreach ($this->items as $key => &$arItem) {
        if (!empty($arItem['DETAIL_PICTURE'])) {
            $fileDetail = '/var/www/bitrix/data/www/tempusshop.ru' . CFile::GetPath($arItem["DETAIL_PICTURE"]);
            $dateM = filectime($fileDetail);

            //if ((time() - 1 * 24 * 60 * 60) < $dateM) {
                $id = $arItem['ID'];
                $desiredWidth = 685;
                $desiredHeight = 930;

                $file = '/var/www/bitrix/data/www/tempusshop.ru' . CFile::GetPath($arItem["DETAIL_PICTURE"]);
                $r0_main = '/var/www/bitrix/data/www/tempusshop.ru/upload/testImg/test/main_detail_resize-'.$id.'.jpg';

                // Delete existing file if it exists
                if (file_exists($r0_main)) {
                    unlink($r0_main);
                }

                list($originalWidthD, $originalHeightD) = getimagesize($fileDetail);

                // Create initial resized image
                $image = $manager->make($file);

                if ($originalWidthD > 653 && $originalWidthD == $originalHeightD) {
                    $image->fit($desiredWidth, $desiredHeight);
                } else {
                    $image->resize($desiredWidth, $desiredHeight, function ($constraint) {
                        $constraint->aspectRatio();
                    });
                }

                $image->save($r0_main);

                // Check and adjust width if needed
                $image = $manager->make($r0_main);
                if ($image->width() < 685) {
                    $needed_width = 685 - $image->width();
                    $needed_width_p = $needed_width / 2;

                    $newImage = $manager->canvas(685, $image->height(), '#ffffff');
                    $newImage->insert($image, 'top', intval($needed_width_p), 0);

                    $r0_main_r = '/var/www/bitrix/data/www/tempusshop.ru/upload/testImg/test/main_detail_resize-'.$id.'.jpg';
                    $newImage->save($r0_main_r);
                } else {
                    $r0_main_r = $r0_main;
                }

                // Check and adjust height if needed
                $image = $manager->make($r0_main_r);
                if ($image->height() < 930) {
                    $needed_height = 930 - $image->height();
                    $needed_height_p = $needed_height / 2;

                    $newImage = $manager->canvas($image->width(), 930, '#ffffff');
                    $newImage->insert($image, 'top', 0, intval($needed_height_p));

                    $r0_main_r = '/var/www/bitrix/data/www/tempusshop.ru/upload/testImg/test/main_detail_resize-hehight-'.$id.'.jpg';
                    $newImage->save($r0_main_r);
                }

                // Add white borders
                $image = $manager->make($r0_main_r);
                $newImage = $manager->canvas($image->width() + 18, $image->height() + 224, '#ffffff');
                $newImage->insert($image, 'top-left', 18, 224);
                $r0_top_left = '/var/www/bitrix/data/www/tempusshop.ru/upload/testImg/test/main_top_left-'.$id.'.jpg';
                $newImage->save($r0_top_left);

                // Add more white space
                $image = $manager->make($r0_top_left);
                $newImage = $manager->canvas($image->width() + 229, $image->height() + 70, '#ffffff');
                $newImage->insert($image, 'top-left', 0, 0);
                $r0_bot_right = '/var/www/bitrix/data/www/tempusshop.ru/upload/testImg/test/main_bot_right-'.$id.'.jpg';
                $newImage->save($r0_bot_right);

                $resized = $r0_bot_right;
                $image = $manager->make($resized);
                $srcWidth = $image->width();
                $srcHeight = $image->height();

                // Apply watermarks
                if (!empty($arItem['BRAND_ID'])) {
                    $brandWater = '/var/www/bitrix/data/www/tempusshop.ru/local/cron/infograph/wablon/brand/'.$arItem['BRAND_ID'].'.png';
                    if (file_exists($brandWater)) {
                        $image->insert($brandWater, 'center');
                        $resized = '/var/www/bitrix/data/www/tempusshop.ru/upload/testImg/test/res_brand-'.$id.'.jpg';
                        $image->save($resized);
                    } else {
                        file_put_contents("/var/www/bitrix/data/www/tempusshop.ru/local/cron/infograph/log.txt", print_r($arItem['ID'].' - Проблема со свойством BRAND', true).PHP_EOL, FILE_APPEND);
                    }
                }

                if (!empty($arItem['MECHANISM']) && file_exists($resized)) {
                    $mechWater = $this->getMechanismWatermarkPath($arItem['MECHANISM']);
                    if ($mechWater) {
                        $image = $manager->make($resized);
                        $image->insert($mechWater, 'center');
                        $resized = '/var/www/bitrix/data/www/tempusshop.ru/upload/testImg/test/res_mech-'.$id.'.jpg';
                        $image->save($resized);
                    }
                }

                if (!empty($arItem['WARRANTY']) && file_exists($resized)) {
                    $warWater = $this->getWarrantyWatermarkPath($arItem['WARRANTY']);
                    if ($warWater) {
                        $image = $manager->make($resized);
                        $image->insert($warWater, 'center');
                        $resized = '/var/www/bitrix/data/www/tempusshop.ru/upload/testImg/test/res_war-'.$id.'.jpg';
                        $image->save($resized);
                    }
                }

                if (!empty($arItem['GLASS']) && file_exists($resized)) {
                    $glassWater = $this->getGlassWatermarkPath($arItem['GLASS']);
                    if ($glassWater) {
                        $image = $manager->make($resized);
                        $image->insert($glassWater, 'center');
                        $resized = '/var/www/bitrix/data/www/tempusshop.ru/upload/testImg/test/res_glass-'.$id.'.jpg';
                        $image->save($resized);
                    }
                }

                if (!empty($arItem['WR']) && file_exists($resized)) {
                    $wrWater = $this->getWRWatermarkPath($arItem['WR']);
                    if ($wrWater) {
                        $image = $manager->make($resized);
                        $image->insert($wrWater, 'center');
                        $resized = '/var/www/bitrix/data/www/tempusshop.ru/upload/testImg/test/res_wr-'.$id.'.jpg';
                        $image->save($resized);
                    }
                }

                if (file_exists($resized)) {
                    $logoWater = '/var/www/bitrix/data/www/tempusshop.ru/local/cron/infograph/wablon/logo_tempus.png';
                    $image = $manager->make($resized);
                    $image->insert($logoWater, 'center');
                    $finalImage = '/var/www/bitrix/data/www/tempusshop.ru/upload/testImg/test/RESULT-'.$id.'.jpg';
                    $image->save($finalImage);

                    // Save to Bitrix
                    $file = new \CFile;
                    $fileId = $file->SaveFile(CFile::MakeFileArray($finalImage), 'info_graph_image');
                    if (!empty($fileId)) {
                        CFile::Delete($arItem["INFO_WB_IMAGE"]);
                        CIBlockElement::SetPropertyValueCode($id, "INFO_WB_IMAGE", array('VALUE' => $fileId));
                        file_put_contents("/var/www/bitrix/data/www/tempusshop.ru/local/cron/infograph/log_good.txt", print_r($arItem['ID'].' - изображение инфографики успешно обновлено', true).PHP_EOL, FILE_APPEND);
                    }
                }
            //}
        } else {
            file_put_contents("/var/www/bitrix/data/www/tempusshop.ru/local/cron/infograph/log.txt", print_r($arItem['ID'].' - отсутствует детальное изображение', true).PHP_EOL, FILE_APPEND);
        }
        unset($arItem);
    }
}

// Helper methods for watermark paths
private function getMechanismWatermarkPath($mechanism) {
    $basePath = '/var/www/bitrix/data/www/tempusshop.ru/local/cron/infograph/wablon/mecha/';
    $map = [
        'Кварцевые' => 'qartz.png',
        'Механические' => 'mehanicheskie.png',
        'Автоматические с ручным подзаводом' => 'automatic_podzavod.png',
        'Автоматические' => 'automatic.png',
        'Автокварц (кинетик)' => 'autoqartz.png',
        'Процессор' => 'processor.png'
    ];

    if (isset($map[$mechanism])) {
        $path = $basePath . $map[$mechanism];
        return file_exists($path) ? $path : null;
    }

    file_put_contents("/var/www/bitrix/data/www/tempusshop.ru/local/cron/infograph/log.txt", print_r('Проблема со свойством MECHANISM: ' . $mechanism, true).PHP_EOL, FILE_APPEND);
    return null;
}

private function getWarrantyWatermarkPath($warranty) {
    $basePath = '/var/www/bitrix/data/www/tempusshop.ru/local/cron/infograph/wablon/garantiya/';
    $map = [
        '1 год' => 'garantiya_1_god.png',
        '1 года' => 'garantiya_1_god.png',
        '2 года' => 'garantiya_2_goda.png',
        '3 года' => 'garantiya_3_goda.png'
    ];

    if (isset($map[$warranty])) {
        $path = $basePath . $map[$warranty];
        return file_exists($path) ? $path : null;
    }

    file_put_contents("/var/www/bitrix/data/www/tempusshop.ru/local/cron/infograph/log.txt", print_r('Проблема со свойством WARRANTY: ' . $warranty, true).PHP_EOL, FILE_APPEND);
    return null;
}

private function getGlassWatermarkPath($glass) {
    $basePath = '/var/www/bitrix/data/www/tempusshop.ru/local/cron/infograph/wablon/steklo/';
    $map = [
        'Минеральное' => 'mineralnoe.png',
        'Органическое' => 'organicheskoe.png',
        'Сапфировое' => 'sapfirovoe.png'
    ];

    if (isset($map[$glass])) {
        $path = $basePath . $map[$glass];
        return file_exists($path) ? $path : null;
    }

    file_put_contents("/var/www/bitrix/data/www/tempusshop.ru/local/cron/infograph/log.txt", print_r('Проблема со свойством GLASS: ' . $glass, true).PHP_EOL, FILE_APPEND);
    return null;
}

private function getWRWatermarkPath($wr) {
    $basePath = '/var/www/bitrix/data/www/tempusshop.ru/local/cron/infograph/wablon/WR/';
    $map = [
        'WR30m' => 'WR30.png',
        'WR200m' => 'WR200.png',
        'WR1000m' => 'WR1000.png',
        'WR100m' => 'WR100.png',
        'WR600m' => 'WR600.png',
        'WR300m' => 'WR300.png',
        'WR50m' => 'WR50.png',
        'WR500m' => 'WR500.png',
        'WR180m' => 'WR180.png',
        'WR150m' => 'WR150.png'
    ];

    if (isset($map[$wr])) {
        $path = $basePath . $map[$wr];
        return file_exists($path) ? $path : null;
    }

    file_put_contents("/var/www/bitrix/data/www/tempusshop.ru/local/cron/infograph/log.txt", print_r('Проблема со свойством WR: ' . $wr, true).PHP_EOL, FILE_APPEND);
    return null;
}


}

(new InfoGraph())->run();
?>
