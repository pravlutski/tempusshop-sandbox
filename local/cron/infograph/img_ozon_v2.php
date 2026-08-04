#!/usr/bin/php
<?php
$_SERVER["DOCUMENT_ROOT"] = "/var/www/bitrix/data/www/tempusshop.ru";
$DOCUMENT_ROOT = $_SERVER["DOCUMENT_ROOT"];

define("NO_KEEP_STATISTIC", true);
define("NOT_CHECK_PERMISSIONS", true);

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");
set_time_limit(0);

use Bitrix\Main\Application,
	Bitrix\Main\Loader;

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

	public function createInfoGrahp(){
		foreach($this->items as $key => &$arItem){
			print_r($arItem['ID']);
			print_r('###');
		 if (!empty($arItem['DETAIL_PICTURE'])) {
				$fileDetail = '/var/www/bitrix/data/www/tempusshop.ru' . CFile::GetPath($arItem["DETAIL_PICTURE"]);
        $dateM = filectime($fileDetail);

        //if ((time() - 1 * 24 * 60 * 60) < $dateM) {
          $id = $arItem['ID'];
          $desiredWidth = 685;
          $desiredHeight = 930;


          $file = '/var/www/bitrix/data/www/tempusshop.ru' . CFile::GetPath($arItem["DETAIL_PICTURE"]);
          $r0_main = '/var/www/bitrix/data/www/tempusshop.ru/upload/testImg/ozonnew/ozon_main_detail_resize-'.$id.'.jpg';
          unlink($r0_main);
          list($originalWidthD, $originalHeightD) = getimagesize($fileDetail);
          if ($originalWidthD > 653 and $originalWidthD == $originalHeightD) {
            CFile::ResizeImageFile(
              $file,
              $r0_main,
              array('width' => $desiredWidth, 'height' => $desiredHeight),
              BX_RESIZE_IMAGE_EXACT,
              array(),
              100,
              false
            );
          } else {
            CFile::ResizeImageFile(
               $file,
               $r0_main,
               array('width'=>$desiredWidth,'height'=>$desiredHeight),
               BX_RESIZE_IMAGE_PROPORTIONAL,
               array(),
               100,
               false
            );
          }

					$srcImage = imagecreatefromstring(file_get_contents($r0_main));
					print_r($srcImag);
					print_r('%%%');
					$checkWidth = imagesx($srcImage);

					if ($checkWidth < 685) {

						$srcWidth = imagesx($srcImage);
						$srcHeight = imagesy($srcImage);

						$needed_width = 685 - $srcWidth;
						$needed_width_p = $needed_width / 2;

						$newWidth = $srcWidth + $needed_width;
						$newHeight = $srcHeight;


						$newImage = imagecreatetruecolor($newWidth, $newHeight);
						$white = imagecolorallocate($newImage, 255, 255, 255);
						imagefilledrectangle($newImage, 0, 0, $newWidth, $newHeight, $white);

						// Копируем исходное изображение на новое изображение с белым фоном
						$dstX = $needed_width_p;
						$dstY = 0;
						imagecopy($newImage, $srcImage, $dstX, $dstY, 0, 0, $srcWidth, $srcHeight);

						$r0_main_r = '/var/www/bitrix/data/www/tempusshop.ru/upload/testImg/ozonnew/ozon_main_detail_resize-'.$id.'.jpg';
						// Выводим изображение или сохраняем в файл
						imagejpeg($newImage, $r0_main_r);
						//
						// print_r('<br>');
						// print_r('<img src="'.str_replace('/var/www/bitrix/data/www/tempusshop.ru','',$r0_main_r).'">');
						// print_r('<br>');
						// Освобождаем память от созданных изображений
						imagedestroy($srcImage);
						imagedestroy($newImage);

					} else {
						imagedestroy($srcImage);
						$r0_main_r = $r0_main;
					}
					//end_edit

					$srcImage = imagecreatefromjpeg($r0_main_r);

					$checkheight = imagesx($srcImage);

					if ($checkheight < 930) {

						$srcWidth = imagesx($srcImage);
						$srcHeight = imagesy($srcImage);

						$needed_height = 930 - $srcHeight;
						$needed_height_p = $needed_height / 2;

						$newWidth = $srcWidth;
						$newHeight = $srcHeight+$needed_height;


						$newImage = imagecreatetruecolor($newWidth, $newHeight);
						$white = imagecolorallocate($newImage, 255, 255, 255);
						imagefilledrectangle($newImage, 0, 0, $newWidth, $newHeight, $white);

						// Копируем исходное изображение на новое изображение с белым фоном
						$dstX = 0;
						$dstY = $needed_height_p;
						imagecopy($newImage, $srcImage, $dstX, $dstY, 0, 0, $srcWidth, $srcHeight);

						$r0_main_r = '/var/www/bitrix/data/www/tempusshop.ru/upload/testImg/ozonnew/ozon_main_detail_resize-hehight-'.$id.'.jpg';
						// Выводим изображение или сохраняем в файл
						imagejpeg($newImage, $r0_main_r);

						// print_r('<br>');
						// print_r('<img src="'.str_replace('/var/www/bitrix/data/www/tempusshop.ru','',$r0_main_r).'">');
						// print_r('<br>');
						// Освобождаем память от созданных изображений
						imagedestroy($srcImage);
						imagedestroy($newImage);

					} else {
						imagedestroy($srcImage);
						$r0_main_r = $r0_main;
					}



          $srcImage = imagecreatefromjpeg($r0_main_r);
          // Получаем исходные размеры изображения
          $srcWidth = imagesx($srcImage);
          $srcHeight = imagesy($srcImage);
          // Создаем новое изображение с необходимыми размерами и белым фоном
          $newWidth = $srcWidth + 18; // увеличиваем ширину на 18 пикселей
          $newHeight = $srcHeight + 224; // верх 224 - 70
          $newImage = imagecreatetruecolor($newWidth, $newHeight);
          $white = imagecolorallocate($newImage, 255, 255, 255); // задаем белый цвет
          imagefilledrectangle($newImage, 0, 0, $newWidth, $newHeight, $white);

          // Копируем исходное изображение на новое изображение с белым фоном
          $dstX = 18; // слева +18
          $dstY = 224; // верх 70
          imagecopy($newImage, $srcImage, $dstX, $dstY, 0, 0, $srcWidth, $srcHeight);

          $r0_top_left = '/var/www/bitrix/data/www/tempusshop.ru/upload/testImg/ozonnew/ozon_main_top_left-'.$id.'.jpg';
          // Выводим изображение или сохраняем в файл
          imagejpeg($newImage, $r0_top_left);

          // Освобождаем память от созданных изображений
          imagedestroy($srcImage);
          imagedestroy($newImage);


          $srcImage = imagecreatefromjpeg($r0_top_left);
          // Получаем исходные размеры изображения
          $srcWidth = imagesx($srcImage);
          $srcHeight = imagesy($srcImage);

          // Создаем новое изображение с необходимыми размерами и белым фоном
          $newWidth = $srcWidth + 229; // увеличиваем ширину на 18 пикселей
          $newHeight = $srcHeight + 70; // верх 224 - 70
          $newImage = imagecreatetruecolor($newWidth, $newHeight);
          $white = imagecolorallocate($newImage, 255, 255, 255); // задаем белый цвет
          imagefilledrectangle($newImage, 0, 0, $newWidth, $newHeight, $white);

          // Копируем исходное изображение на новое изображение с белым фоном
          $dstX = 0; // слева +18
          $dstY = 0; // верх 70
          imagecopy($newImage, $srcImage, $dstX, $dstY, 0, 0, $srcWidth, $srcHeight);

          $r0_bot_right = '/var/www/bitrix/data/www/tempusshop.ru/upload/testImg/ozonnew/ozon_main_bot_right-'.$id.'.jpg';
          // Выводим изображение или сохраняем в файл
          imagejpeg($newImage, $r0_bot_right);

          // Освобождаем память от созданных изображений
          imagedestroy($srcImage);
          imagedestroy($newImage);

					//edit
					$resized = $r0_bot_right;

					$srcImage = imagecreatefromjpeg($resized);

					$srcWidth = imagesx($srcImage);
					$srcHeight = imagesy($srcImage);

					imagedestroy($srcImage);
          //бренд
          if (!empty($arItem['BRAND_ID'])) {
            $brandWater = '/var/www/bitrix/data/www/tempusshop.ru/local/cron/infograph/wablon/brand_ozon/'.$arItem['BRAND_ID'].'.png';
            if (!isset($brandWater)) {
              file_put_contents("/var/www/bitrix/data/www/tempusshop.ru/local/cron/infograph/log_ozon.txt", print_r($arItem['ID'].' - Проблема со свойством BRAND', true).PHP_EOL, FILE_APPEND);
            }
            $arWatermark = array(
               'position' => 'mc',//допустимые http://prntscr.com/c3hc9i
               'type' => 'file',
               'size' => 'real',
               'alpha_level' => 100,//прозрачность
               'file' => $brandWater ,
            );


            $tempFile = '/var/www/bitrix/data/www/tempusshop.ru/upload/testImg/ozonnew/ozon_res_brand-'.$id.'.jpg';
            $newW = $srcWidth; $newH = $srcHeight;
            CFile::ResizeImageFile(
               $resized,
               $tempFile,
               array('width'=>$newW,'height'=>$newH),
               BX_RESIZE_IMAGE_PROPORTIONAL,
               $arWatermark,
               100,
               false
            );

            $resized = $tempFile;
          }

          // механизм
          if (!empty($arItem['MECHANISM']) and isset($resized)) {
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
              default:
                file_put_contents("/var/www/bitrix/data/www/tempusshop.ru/local/cron/infograph/log.txt", print_r($arItem['ID'].' - Проблема со свойством MECHANISM', true).PHP_EOL, FILE_APPEND);
                break;
            }

            $arWatermark = array(
               'position' => 'mc',//допустимые http://prntscr.com/c3hc9i
               'type' => 'file',
               'size' => 'real',
               'alpha_level' => 100,//прозрачность
               'file' => $mechWater ,
            );


            $tempFile = '/var/www/bitrix/data/www/tempusshop.ru/upload/testImg/ozonnew/ozon_res_mech-'.$id.'.jpg';

            $newW = $srcWidth; $newH = $srcHeight;
            CFile::ResizeImageFile(
               $resized,
               $tempFile,
               array('width'=>$newW,'height'=>$newH),
               BX_RESIZE_IMAGE_PROPORTIONAL,
               $arWatermark,
               100,
               false
            );

            $resized = $tempFile;
          }
          //гарантия
          if (!empty($arItem['WARRANTY']) and isset($resized)) {
            switch ($arItem['WARRANTY']) {
              case '1 год':
                $warWater = '/var/www/bitrix/data/www/tempusshop.ru/local/cron/infograph/wablon/ozon/garantiya/garantiya_1_god.png';
                break;
              case '1 года':
                $warWater = '/var/www/bitrix/data/www/tempusshop.ru/local/cron/infograph/wablon/ozon/garantiya/garantiya_1_god.png';
                break;
              case '2 года':
                $warWater = '/var/www/bitrix/data/www/tempusshop.ru/local/cron/infograph/wablon/ozon/garantiya/garantiya_2_goda.png';
                break;
              case '3 года':
                $warWater = '/var/www/bitrix/data/www/tempusshop.ru/local/cron/infograph/wablon/ozon/garantiya/garantiya_3_goda.png';
                break;
              default:
                file_put_contents("/var/www/bitrix/data/www/tempusshop.ru/local/cron/infograph/log_ozon.txt", print_r($arItem['ID'].' - Проблема со свойством WARRANTY', true).PHP_EOL, FILE_APPEND);
                break;
            }

            $arWatermark = array(
               'position' => 'mc',//допустимые http://prntscr.com/c3hc9i
               'type' => 'file',
               'size' => 'real',
               'alpha_level' => 100,//прозрачность
               'file' => $warWater ,
            );

            $tempFile = '/var/www/bitrix/data/www/tempusshop.ru/upload/testImg/ozonnew/ozon_res_war-'.$id.'.jpg';
            $newW = $srcWidth; $newH = $srcHeight;
            CFile::ResizeImageFile(
               $resized,
               $tempFile,
               array('width'=>$newW,'height'=>$newH),
               BX_RESIZE_IMAGE_PROPORTIONAL,
               $arWatermark,
               100,
               false
            );

            $resized = $tempFile;
          }
          //стекло
          if (!empty($arItem['GLASS']) and isset($resized)) {
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
              default:
                file_put_contents("/var/www/bitrix/data/www/tempusshop.ru/local/cron/infograph/log_ozon.txt", print_r($arItem['ID'].' - Проблема со свойством GLASS', true).PHP_EOL, FILE_APPEND);
                break;
            }

            $arWatermark = array(
               'position' => 'mc',//допустимые http://prntscr.com/c3hc9i
               'type' => 'file',
               'size' => 'real',
               'alpha_level' => 100,//прозрачность
               'file' => $glassWater ,
            );

            //print_r($arWatermark);
            //print_r('<br>');

            $tempFile = '/var/www/bitrix/data/www/tempusshop.ru/upload/testImg/ozonnew/ozon_res_glass-'.$id.'.jpg';
            $newW = $srcWidth; $newH = $srcHeight;
            CFile::ResizeImageFile(
               $resized,
               $tempFile,
               array('width'=>$newW,'height'=>$newH),
               BX_RESIZE_IMAGE_PROPORTIONAL,
               $arWatermark,
               100,
               false
            );

            $resized = $tempFile;
          }
          //WR
          if (!empty($arItem['WR']) and isset($resized)) {
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
              default:
                file_put_contents("/var/www/bitrix/data/www/tempusshop.ru/local/cron/infograph/log_ozon.txt", print_r($arItem['ID'].' - Проблема со свойством WR', true).PHP_EOL, FILE_APPEND);
                break;
            }

            $arWatermark = array(
               'position' => 'mc',//допустимые http://prntscr.com/c3hc9i
               'type' => 'file',
               'size' => 'real',
               'alpha_level' => 100,//прозрачность
               'file' => $wrWater ,
            );


            $tempFile = '/var/www/bitrix/data/www/tempusshop.ru/upload/testImg/ozonnew/ozon_res_wr-'.$id.'.jpg';
            $newW = $srcWidth; $newH = $srcHeight;
            CFile::ResizeImageFile(
               $resized,
               $tempFile,
               array('width'=>$newW,'height'=>$newH),
               BX_RESIZE_IMAGE_PROPORTIONAL,
               $arWatermark,
               100,
               false
            );

            $resized = $tempFile;
          }
          //лого
          if (isset($resized)) {

            $logoWater = '/var/www/bitrix/data/www/tempusshop.ru/local/cron/infograph/wablon/ozon/logo_tempus.png';

            $arWatermark = array(
               'position' => 'mc',//допустимые http://prntscr.com/c3hc9i
               'type' => 'file',
               'size' => 'real',
               'alpha_level' => 100,//прозрачность
               'file' => $logoWater ,
            );


            $tempFile = '/var/www/bitrix/data/www/tempusshop.ru/upload/testImg/ozonnew/ozon_RESULT-'.$id.'.jpg';
            $newW = $srcWidth; $newH = $srcHeight;
            CFile::ResizeImageFile(
               $resized,
               $tempFile,
               array('width'=>$newW,'height'=>$newH),
               BX_RESIZE_IMAGE_PROPORTIONAL,
               $arWatermark,
               100,
               false
            );

            $resized = $tempFile;
          }


          $file = new \CFile;
    			$fileId = $file->SaveFile(CFile::MakeFileArray($resized),'info_graph_image');
    			//file_put_contents("/var/www/bitrix/data/www/tempusshop.ru/local/cron/marketplace/bad.txt", print_r('file id: ' .$fileId, true).PHP_EOL, FILE_APPEND);
  				if (!empty($fileId)) {
  					CFile::Delete($arItem["INFOOZON_IMAGE"]);
    				CIBlockElement::SetPropertyValueCode($id, "INFOOZON_IMAGE", array('VALUE' => $fileId));
            file_put_contents("/var/www/bitrix/data/www/tempusshop.ru/local/cron/infograph/ozon_log_good.txt", print_r($arItem['ID'].' - изображение инфографики успешно обновлено', true).PHP_EOL, FILE_APPEND);
						unlink('/var/www/bitrix/data/www/tempusshop.ru/upload/testImg/ozonnew/ozon_RESULT-'.$id.'.jpg');
						unlink('/var/www/bitrix/data/www/tempusshop.ru/upload/testImg/ozonnew/ozon_res_wr-'.$id.'.jpg');
						unlink('/var/www/bitrix/data/www/tempusshop.ru/upload/testImg/ozonnew/ozon_res_glass-'.$id.'.jpg');
						unlink('/var/www/bitrix/data/www/tempusshop.ru/upload/testImg/ozonnew/ozon_res_war-'.$id.'.jpg');
						unlink('/var/www/bitrix/data/www/tempusshop.ru/upload/testImg/ozonnew/ozon_res_mech-'.$id.'.jpg');
						unlink('/var/www/bitrix/data/www/tempusshop.ru/upload/testImg/ozonnew/ozon_res_mech-'.$id.'.jpg');
						unlink('/var/www/bitrix/data/www/tempusshop.ru/upload/testImg/ozonnew/ozon_res_brand-'.$id.'.jpg');
  				}
        //}
      } else {
        file_put_contents("/var/www/bitrix/data/www/tempusshop.ru/local/cron/infograph/log_log.txt", print_r($arItem['ID'].' - отсутсвует деатльное изображение', true).PHP_EOL, FILE_APPEND);
      }
		 unset($arItem);
		}
  }
}

(new InfoGraph())->run();
?>
