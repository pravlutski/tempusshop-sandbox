#!/usr/bin/php
<?php
$_SERVER["DOCUMENT_ROOT"] = "/var/www/bitrix/data/www/tempusshop.ru";
$DOCUMENT_ROOT = $_SERVER["DOCUMENT_ROOT"];

define("NO_KEEP_STATISTIC", true);
define("NOT_CHECK_PERMISSIONS", true);

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");
require($_SERVER["DOCUMENT_ROOT"] . "/vendor/autoload.php");
set_time_limit(0);

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
    $this->createInfoGrahp();
	}

	public function getItems(){
    file_put_contents("/var/www/bitrix/data/www/tempusshop.ru/local/cron/infograph/log.txt", print_r('START'.date('Y-m-d H:i:s'), true).PHP_EOL, FILE_APPEND);

    $arSelect = Array("ID", "PROPERTY_COLLECTION", "PROPERTY_BRAND", "PROPERTY_CML2_ARTICLE", "PROPERTY_COLORTERM","PROPERTY_IMAGE_MARKETPLACE","PROPERTY_NAME_MARKETPLACE","PROPERTY_126","DETAIL_PICTURE","TIMESTAMP_X", "CATALOG_QUANTITY","PROPERTY_DESC_RICH_OZON","PROPERTY_MECHANISM","PROPERTY_GLASS","PROPERTY_CASE","PROPERTY_WR","PROPERTY_FACE","PROPERTY_DATE_LAST_STOCK",
    "PROPERTY_WARRANTY","PROPERTY_INFOOZON_IMAGE");
    // $arFilter = Array(
    //   "IBLOCK_ID" => CProSet::IB_CATALOG,
    //   //"ID" => 181397,
    //   //"ID" => 178901
		// 	"ACTIVE" => "Y",
		// 	"!PROP_MAXYSS_NMID_CREATED_WB" => false
    // );

		$arFilter = Array(
      "IBLOCK_ID" => 16,
      "ID" => 181717,
			//"SECTION_ID" => 558
			// "!INFOOZON_IMAGE" => true,
			//"PROPERTY_BRAND_VALUE" => 43508,
			//"PROPERTY_OZON_ACTIVE_VALUE" => 'Да',
			//"PROPERTY_CML2_ARTICLE" => array()
      // "XML_ID" => [597,4276,34975,177804,118344,181061,78129,4152,13476,13351,13263,4332,4618,74537,4622,7424,74539,129484]
    );
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
        "INFOOZON_IMAGE" => $el['PROPERTY_INFOOZON_IMAGE_VALUE'],
        "LAST_STOCK" => $el['PROPERTY_DATE_LAST_STOCK_VALUE'],
      ];

    }
		//print_r(count($this->items));
    file_put_contents("/var/www/bitrix/data/www/tempusshop.ru/local/cron/infograph/log_ozon.txt", print_r('Всего эл-ов:'.count($this->items) , true).PHP_EOL, FILE_APPEND);
	}

	public function createInfoGrahp() {
    $manager = new Intervention\Image\ImageManager(['driver' => 'gd']);

    foreach($this->items as $key => &$arItem) { 

        if (!empty($arItem['DETAIL_PICTURE'])) {
            $fileDetail = '/var/www/bitrix/data/www/tempusshop.ru' . CFile::GetPath($arItem["DETAIL_PICTURE"]);
            $dateM = filectime($fileDetail);

            $id = $arItem['ID'];
            $desiredWidth = 685;
            $desiredHeight = 930;

            $file = '/var/www/bitrix/data/www/tempusshop.ru' . CFile::GetPath($arItem["DETAIL_PICTURE"]);
            $r0_main = '/var/www/bitrix/data/www/tempusshop.ru/upload/testImg/ozonnew/ozon_main_detail_resize-'.$id.'.jpg';

            if (file_exists($r0_main)) {
                unlink($r0_main);
            }

            list($originalWidthD, $originalHeightD) = getimagesize($fileDetail);


            $image = $manager->make($file);

            if ($originalWidthD > 653 && $originalWidthD == $originalHeightD) {
                $image->fit($desiredWidth, $desiredHeight);
            } else {
                $image->resize($desiredWidth, $desiredHeight, function ($constraint) {
                    $constraint->aspectRatio();
                });
            }

            $image->save($r0_main);


            $image = $manager->make($r0_main);
            $checkWidth = $image->width();

            if ($checkWidth < 685) {
                $srcWidth = $image->width();
                $srcHeight = $image->height();
                $needed_width = 685 - $srcWidth;
                $needed_width_p = $needed_width / 2;

                $newImage = $manager->canvas(685, $srcHeight, '#ffffff');
                $newImage->insert($image, 'left', $needed_width_p);

                $r0_main_r = '/var/www/bitrix/data/www/tempusshop.ru/upload/testImg/ozonnew/ozon_main_detail_resize-'.$id.'.jpg';
                $newImage->save($r0_main_r);
            } else {
                $r0_main_r = $r0_main;
            }


            $image = $manager->make($r0_main_r);
            $checkHeight = $image->height();

            if ($checkHeight < 930) {
                $srcWidth = $image->width();
                $srcHeight = $image->height();
                $needed_height = 930 - $srcHeight;
                $needed_height_p = $needed_height / 2;

                $newImage = $manager->canvas($srcWidth, 930, '#ffffff');
                $newImage->insert($image, 'top', $needed_height_p);

                $r0_main_r = '/var/www/bitrix/data/www/tempusshop.ru/upload/testImg/ozonnew/ozon_main_detail_resize-hehight-'.$id.'.jpg';
                $newImage->save($r0_main_r);
            }


            $image = $manager->make($r0_main_r);
            $newImage = $manager->canvas($image->width() + 18, $image->height() + 224, '#ffffff');
            $newImage->insert($image, 'top-left', 18, 224);
            $r0_top_left = '/var/www/bitrix/data/www/tempusshop.ru/upload/testImg/ozonnew/ozon_main_top_left-'.$id.'.jpg';
            $newImage->save($r0_top_left);


            $image = $manager->make($r0_top_left);
            $newImage = $manager->canvas($image->width() + 229, $image->height() + 70, '#ffffff');
            $newImage->insert($image, 'top-left');
            $r0_bot_right = '/var/www/bitrix/data/www/tempusshop.ru/upload/testImg/ozonnew/ozon_main_bot_right-'.$id.'.jpg';
            $newImage->save($r0_bot_right);

            $resized = $r0_bot_right;
            $image = $manager->make($resized);
            $srcWidth = $image->width();
            $srcHeight = $image->height();


            if (!empty($arItem['BRAND_ID'])) {
                $brandWater = '/var/www/bitrix/data/www/tempusshop.ru/local/cron/infograph/wablon/brand_ozon/'.$arItem['BRAND_ID'].'.png';

                if (file_exists($brandWater)) {
                    $image->insert($brandWater, 'center');
                    $tempFile = '/var/www/bitrix/data/www/tempusshop.ru/upload/testImg/ozonnew/ozon_res_brand-'.$id.'.jpg';
                    $image->save($tempFile);
                    $resized = $tempFile;
                } else {
                    file_put_contents("/var/www/bitrix/data/www/tempusshop.ru/local/cron/infograph/log_ozon.txt", print_r($arItem['ID'].' - Проблема со свойством BRAND', true).PHP_EOL, FILE_APPEND);
                }
            }


            if (!empty($arItem['MECHANISM']) && isset($resized)) {
                $image = $manager->make($resized);
                $mechWater = null;

                switch ($arItem['MECHANISM']) {
                    case 'Кварцевые':
                        $mechWater = '/var/www/bitrix/data/www/tempusshop.ru/local/cron/infograph/wablon/ozon/mecha/qartz.png';
                        break;
                    case 'Механические':
                        $mechWater = '/var/www/bitrix/data/www/tempusshop.ru/local/cron/infograph/wablon/ozon/mecha/mehanicheskie.png';
                        break;
                    case 'Автоматические с ручным подзаводом':
                        $mechWater = '/var/www/bitrix/data/www/tempusshop.ru/local/cron/infograph/wablon/ozon/mecha/automatic_podzavod.png';
                        break;
                    case 'Автоматические':
                        $mechWater = '/var/www/bitrix/data/www/tempusshop.ru/local/cron/infograph/wablon/ozon/mecha/automatic.png';
                        break;
                    case 'Автокварц (кинетик)':
                        $mechWater = '/var/www/bitrix/data/www/tempusshop.ru/local/cron/infograph/wablon/ozon/mecha/autoqartz.png';
                        break;
                    case 'Процессор':
                        $mechWater = '/var/www/bitrix/data/www/tempusshop.ru/local/cron/infograph/wablon/ozon/mecha/processor.png';
                        break;
                }

                if ($mechWater && file_exists($mechWater)) {
                    $image->insert($mechWater, 'center');
                    $tempFile = '/var/www/bitrix/data/www/tempusshop.ru/upload/testImg/ozonnew/ozon_res_mech-'.$id.'.jpg';
                    $image->save($tempFile);
                    $resized = $tempFile;
                } else {
                    file_put_contents("/var/www/bitrix/data/www/tempusshop.ru/local/cron/infograph/log.txt", print_r($arItem['ID'].' - Проблема со свойством MECHANISM', true).PHP_EOL, FILE_APPEND);
                }
            }


            if (!empty($arItem['WARRANTY']) && isset($resized)) {
                $image = $manager->make($resized);
                $warWater = null;

                switch ($arItem['WARRANTY']) {
                    case '1 год':
                    case '1 года':
                        $warWater = '/var/www/bitrix/data/www/tempusshop.ru/local/cron/infograph/wablon/ozon/garantiya/garantiya_1_god.png';
                        break;
                    case '2 года':
                        $warWater = '/var/www/bitrix/data/www/tempusshop.ru/local/cron/infograph/wablon/ozon/garantiya/garantiya_2_goda.png';
                        break;
                    case '3 года':
                        $warWater = '/var/www/bitrix/data/www/tempusshop.ru/local/cron/infograph/wablon/ozon/garantiya/garantiya_3_goda.png';
                        break;
                }

                if ($warWater && file_exists($warWater)) {
                    $image->insert($warWater, 'center');
                    $tempFile = '/var/www/bitrix/data/www/tempusshop.ru/upload/testImg/ozonnew/ozon_res_war-'.$id.'.jpg';
                    $image->save($tempFile);
                    $resized = $tempFile;
                } else {
                    file_put_contents("/var/www/bitrix/data/www/tempusshop.ru/local/cron/infograph/log_ozon.txt", print_r($arItem['ID'].' - Проблема со свойством WARRANTY', true).PHP_EOL, FILE_APPEND);
                }
            }


            if (!empty($arItem['GLASS']) && isset($resized)) {
                $image = $manager->make($resized);
                $glassWater = null;

                switch ($arItem['GLASS']) {
                    case 'Минеральное':
                        $glassWater = '/var/www/bitrix/data/www/tempusshop.ru/local/cron/infograph/wablon/ozon/steklo/mineralnoe.png';
                        break;
                    case 'Органическое':
                        $glassWater = '/var/www/bitrix/data/www/tempusshop.ru/local/cron/infograph/wablon/ozon/steklo/organicheskoe.png';
                        break;
                    case 'Сапфировое':
                        $glassWater = '/var/www/bitrix/data/www/tempusshop.ru/local/cron/infograph/wablon/ozon/steklo/sapfirovoe.png';
                        break;
                }

                if ($glassWater && file_exists($glassWater)) {
                    $image->insert($glassWater, 'center');
                    $tempFile = '/var/www/bitrix/data/www/tempusshop.ru/upload/testImg/ozonnew/ozon_res_glass-'.$id.'.jpg';
                    $image->save($tempFile);
                    $resized = $tempFile;
                } else {
                    file_put_contents("/var/www/bitrix/data/www/tempusshop.ru/local/cron/infograph/log_ozon.txt", print_r($arItem['ID'].' - Проблема со свойством GLASS', true).PHP_EOL, FILE_APPEND);
                }
            }


            if (!empty($arItem['WR']) && isset($resized)) {
                $image = $manager->make($resized);
                $wrWater = null;

                switch ($arItem['WR']) {
                    case 'WR30m':
                        $wrWater = '/var/www/bitrix/data/www/tempusshop.ru/local/cron/infograph/wablon/ozon/WR/WR30.png';
                        break;
                    case 'WR200m':
                        $wrWater = '/var/www/bitrix/data/www/tempusshop.ru/local/cron/infograph/wablon/ozon/WR/WR200.png';
                        break;
                    case 'WR1000m':
                        $wrWater = '/var/www/bitrix/data/www/tempusshop.ru/local/cron/infograph/wablon/ozon/WR/WR1000.png';
                        break;
                    case 'WR100m':
                        $wrWater = '/var/www/bitrix/data/www/tempusshop.ru/local/cron/infograph/wablon/ozon/WR/WR100.png';
                        break;
                    case 'WR600m':
                        $wrWater = '/var/www/bitrix/data/www/tempusshop.ru/local/cron/infograph/wablon/ozon/WR/WR600.png';
                        break;
                    case 'WR300m':
                        $wrWater = '/var/www/bitrix/data/www/tempusshop.ru/local/cron/infograph/wablon/ozon/WR/WR300.png';
                        break;
                    case 'WR50m':
                        $wrWater = '/var/www/bitrix/data/www/tempusshop.ru/local/cron/infograph/wablon/ozon/WR/WR50.png';
                        break;
                    case 'WR500m':
                        $wrWater = '/var/www/bitrix/data/www/tempusshop.ru/local/cron/infograph/wablon/ozon/WR/WR500.png';
                        break;
                    case 'WR180m':
                        $wrWater = '/var/www/bitrix/data/www/tempusshop.ru/local/cron/infograph/wablon/ozon/WR/WR180.png';
                        break;
                    case 'WR150m':
                        $wrWater = '/var/www/bitrix/data/www/tempusshop.ru/local/cron/infograph/wablon/ozon/WR/WR150.png';
                        break;
                }

                if ($wrWater && file_exists($wrWater)) {
                    $image->insert($wrWater, 'center');
                    $tempFile = '/var/www/bitrix/data/www/tempusshop.ru/upload/testImg/ozonnew/ozon_res_wr-'.$id.'.jpg';
                    $image->save($tempFile);
                    $resized = $tempFile;
                } else {
                    file_put_contents("/var/www/bitrix/data/www/tempusshop.ru/local/cron/infograph/log_ozon.txt", print_r($arItem['ID'].' - Проблема со свойством WR', true).PHP_EOL, FILE_APPEND);
                }
            }


            if (isset($resized)) {
                $image = $manager->make($resized);
                $logoWater = '/var/www/bitrix/data/www/tempusshop.ru/local/cron/infograph/wablon/ozon/logo_tempus.png';

                if (file_exists($logoWater)) {
                    $image->insert($logoWater, 'center');
                    $tempFile = '/var/www/bitrix/data/www/tempusshop.ru/upload/testImg/ozonnew/ozon_RESULT-'.$id.'.jpg';
                    $image->save($tempFile);
                    $resized = $tempFile;


                    $file = new \CFile;
                    $fileId = $file->SaveFile(CFile::MakeFileArray($resized), 'info_graph_image');

                    if (!empty($fileId)) {
                        CFile::Delete($arItem["INFOOZON_IMAGE"]);
                        CIBlockElement::SetPropertyValueCode($id, "INFOOZON_IMAGE", array('VALUE' => $fileId));
                        file_put_contents("/var/www/bitrix/data/www/tempusshop.ru/local/cron/infograph/ozon_log_good.txt", print_r($arItem['ID'].' - изображение инфографики успешно обновлено', true).PHP_EOL, FILE_APPEND);


                        $filesToDelete = [
                            '/var/www/bitrix/data/www/tempusshop.ru/upload/testImg/ozonnew/ozon_RESULT-'.$id.'.jpg',
                            '/var/www/bitrix/data/www/tempusshop.ru/upload/testImg/ozonnew/ozon_res_wr-'.$id.'.jpg',
                            '/var/www/bitrix/data/www/tempusshop.ru/upload/testImg/ozonnew/ozon_res_glass-'.$id.'.jpg',
                            '/var/www/bitrix/data/www/tempusshop.ru/upload/testImg/ozonnew/ozon_res_war-'.$id.'.jpg',
                            '/var/www/bitrix/data/www/tempusshop.ru/upload/testImg/ozonnew/ozon_res_mech-'.$id.'.jpg',
                            '/var/www/bitrix/data/www/tempusshop.ru/upload/testImg/ozonnew/ozon_res_brand-'.$id.'.jpg'
                        ];

                        foreach ($filesToDelete as $fileToDelete) {
                            if (file_exists($fileToDelete)) {
                                unlink($fileToDelete);
                            }
                        }
                    }
                }
            }
        } else {
            file_put_contents("/var/www/bitrix/data/www/tempusshop.ru/local/cron/infograph/log_log.txt", print_r($arItem['ID'].' - отсутсвует деатльное изображение', true).PHP_EOL, FILE_APPEND);
        }

        unset($arItem);
    }
}
}

(new InfoGraph())->run();
?>
