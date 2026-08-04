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

class Updater{
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

		file_put_contents("/var/www/bitrix/data/www/tempusshop.ru/local/cron/marketplace/log_updater.txt", print_r('START ', true).PHP_EOL,FILE_APPEND);
		file_put_contents('/var/www/bitrix/data/www/tempusshop.ru/local/cron/marketplace/log_updater.txt', print_r(date('Y-m-d H:i:s'), true) . "\r\n",FILE_APPEND);

		$this->getItems();
		// $this->updateDateStock();
		// $this->updateDisappear();
		// // $this->updateNameMP();
		// // $this->tnvd_update();
		// // $this->updateRich();
		// // $this->updateOzonId();
		// // $this->updateOzonId_TI();
		$this->updatePhotoMP();
	}

	public function getItems(){
		$arSelect = Array("ID", "PROPERTY_COLLECTION", "PROPERTY_BRAND", "PROPERTY_CML2_ARTICLE", "PROPERTY_COLORTERM","PROPERTY_IMAGE_MARKETPLACE","PROPERTY_NAME_MARKETPLACE","PROPERTY_126","DETAIL_PICTURE","TIMESTAMP_X", "CATALOG_QUANTITY","PROPERTY_DESC_RICH_OZON","PROPERTY_MECHANISM","PROPERTY_GLASS","PROPERTY_CASE","PROPERTY_WR","PROPERTY_FACE","PROPERTY_DATE_LAST_STOCK","PROPERTY_OZON_ID","PROPERTY_OZON_ID_TI","PROPERTY_WBARTICLE");
		$arFilter = Array(
			"IBLOCK_ID" => CProSet::IB_CATALOG,
			// "PROPERTY_CML2_ARTICLE" => array("ID-11S-2E","IQ-126-5E","IQ-133-5E","C036.407.16.050.00","C036.407.16.040.00","IQ-152-1E","IQ-152-5E","SSB430P1","SSB429P1","SSB427P1","SSB425P1","SUR555P1","SUR558P1","C039.251.33.367.00","C039.251.33.017.00","SUR557P1","C038.462.16.037.00","C030.250.11.106.00","C036.407.18.040.00","C033.051.22.118.01","C032.807.22.051.01","C032.807.22.041.10"),
			//"ID" => 13263,
			// "PROPERTY_OZON_ACTIVE_VALUE" => 'Да',
			// "ID" => [74538,74131]
			// "ID" => 1002
		);
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
				"LAST_STOCK" => $el['PROPERTY_DATE_LAST_STOCK_VALUE'],
				"OZON_ARTICLE" => $el['PROPERTY_WBARTICLE_VALUE'],
				"OZON_ID" => $el['PROPERTY_OZON_ID_VALUE'],
				"OZON_ID_TI" => $el['PROPERTY_OZON_ID_TI_VALUE'],
			];

		}
		print_r(count($this->items));
		//file_put_contents('/var/www/bitrix/data/www/tempusshop.ru/local/cron/marketplace/arr.txt', print_r($this->items, true) . "\r\n", FILE_APPEND | LOCK_EX);
	}

	public function updatePhotoMP(){
    $i = 0;
		foreach($this->items as $key => &$arItem){
			if (!empty($arItem['DETAIL_PICTURE'])) {
        //$ARtmp = CFile::GetFileArray($arItem['DETAIL_PICTURE']);
				$fileDetail = '/var/www/bitrix/data/www/tempusshop.ru' . CFile::GetPath($arItem["DETAIL_PICTURE"]);
        $dateM = filectime($fileDetail);
				//print_r($arItem['ARTICLE'].'\n');

				// file_put_contents("/var/www/bitrix/data/www/tempusshop.ru/local/cron/marketplace/bad.txt", print_r('date ', true).PHP_EOL, FILE_APPEND);
				// file_put_contents("/var/www/bitrix/data/www/tempusshop.ru/local/cron/marketplace/bad.txt", print_r($dateM, true).PHP_EOL, FILE_APPEND);
        if ((time() - 1 * 24 * 60 * 60) < $dateM) {
  				$id = $arItem['ID'];
          //file_put_contents("/var/www/bitrix/data/www/tempusshop.ru/local/cron/marketplace/bad.txt", print_r('fields: ', true).PHP_EOL, FILE_APPEND);
          //file_put_contents("/var/www/bitrix/data/www/tempusshop.ru/local/cron/marketplace/bad.txt", print_r($arItem, true).PHP_EOL, FILE_APPEND);
          $desiredWidth = 900;
          $desiredHeight = 1200;

          $watermarkImagePath = '/var/www/bitrix/data/www/tempusshop.ru/upload/testImg/watermark_6.png';
          $file = '/var/www/bitrix/data/www/tempusshop.ru' . CFile::GetPath($arItem["DETAIL_PICTURE"]);
          $resizedImage = '/var/www/bitrix/data/www/tempusshop.ru/upload/testImg/resized_jpg_'.$id.'.jpg';
          unlink($resizedImage);
					list($originalWidthD, $originalHeightD) = getimagesize($fileDetail);
					if ($originalWidthD > 900 and $originalWidthD == $originalHeightD) {
					  CFile::ResizeImageFile(
					    $file,

							$resizedImage,
					    array('width' => $desiredWidth, 'height' => $desiredHeight),
					    BX_RESIZE_IMAGE_EXACT,
					    array(),
					    100,
					    false
					  );
					} else {
						CFile::ResizeImageFile(
	             $file,
	             $resizedImage,
	             array('width'=>$desiredWidth,'height'=>$desiredHeight),
	             BX_RESIZE_IMAGE_PROPORTIONAL,
	             array(),
	             100,
	             false
	          );
					}

						//edit 18
						// Путь к файлу, в который будет сохранено увеличенное изображение
						$destinationImage = '/var/www/bitrix/data/www/tempusshop.ru/upload/testImg/resized_xl_'.$id.'.jpg';

						// Желаемые размеры увеличенного изображения

						// Получаем размеры исходного изображения
						list($originalWidth, $originalHeight) = getimagesize($resizedImage);

						$scale = $desiredHeight / $originalHeight;
						$dwEdit = $originalWidth * $scale;

						// Создаем новое изображение с заданными размерами
						$enlargedImage = imagecreatetruecolor($dwEdit, $desiredHeight);

						// Загружаем исходное изображение
						$originalImage = imagecreatefromjpeg($resizedImage);

						// Увеличиваем изображение с помощью функции imagecopyresampled()
						imagecopyresampled($enlargedImage, $originalImage, 0, 0, 0, 0, $dwEdit, $desiredHeight, $originalWidth, $originalHeight);

						// Сохраняем увеличенное изображение в новый файл
						imagejpeg($enlargedImage, $destinationImage);

						// Освобождаем память, выделенную для изображений
						//imagedestroy($resizedImage);
						imagedestroy($enlargedImage);

						unlink($resizedImage);
						$resizedImage = $destinationImage;
						//unlink($destinationImage);
					//end edit
          //$resizedImage = '/var/www/bitrix/data/www/tempusshop.ru'. $resizedImage['src'];
          //file_put_contents("/var/www/bitrix/data/www/tempusshop.ru/local/cron/marketplace/bad.txt", print_r('res2: ', true).PHP_EOL, FILE_APPEND);
          //file_put_contents("/var/www/bitrix/data/www/tempusshop.ru/local/cron/marketplace/bad.txt", print_r($resizedImage, true).PHP_EOL, FILE_APPEND);
          $inf = getimagesize($resizedImage);

          //file_put_contents("/var/www/bitrix/data/www/tempusshop.ru/local/cron/marketplace/bad.txt", print_r('inf: ', true).PHP_EOL, FILE_APPEND);
          //file_put_contents("/var/www/bitrix/data/www/tempusshop.ru/local/cron/marketplace/bad.txt", print_r($inf, true).PHP_EOL, FILE_APPEND);
          $resizedWidth = $inf[0];
          $resizedHeight = $inf[1];


          if ($resizedWidth < $desiredWidth) {

              $padding = ($desiredWidth - $resizedWidth) / 2;


              $image = imagecreatetruecolor($desiredWidth, $resizedHeight);
              $white = imagecolorallocate($image, 255, 255, 255);
              imagefill($image, 0, 0, $white);
              imagecopy(

	                $image,
                  imagecreatefromjpeg($resizedImage),
                  $padding,
                  0,
                  0,
                  0,
                  $resizedWidth,
                  $resizedHeight
              );

              $paddedImagePath = '/var/www/bitrix/data/www/tempusshop.ru/upload/testImg/processed_image_'.$id.'.jpg';
              $resas = imagejpeg($image, $paddedImagePath);
              //file_put_contents("/var/www/bitrix/data/www/tempusshop.ru/local/cron/marketplace/bad.txt", print_r($resas, true).PHP_EOL, FILE_APPEND);
              $resizedImage = $paddedImagePath;
          }

          $arWatermark = array(
             'position' => 'br',//допустимые http://prntscr.com/c3hc9i
             'type' => 'file',
             'size' => 'real',
             'alpha_level' => 100,//прозрачность
             'file' => $watermarkImagePath ,
          );
          //file_put_contents("/var/www/bitrix/data/www/tempusshop.ru/local/cron/marketplace/bad.txt", print_r('wt: ' .$arWatermark, true).PHP_EOL, FILE_APPEND);
          $tempFile = '/var/www/bitrix/data/www/tempusshop.ru/upload/testImg/ready_image_'.$id.'.jpg';

          CFile::ResizeImageFile(
             $resizedImage,
             $tempFile,
             array('width'=>$desiredWidth,'height'=>$desiredHeight),
             BX_RESIZE_IMAGE_PROPORTIONAL,
             $arWatermark,
             100,
             false
          );
            //file_put_contents("/var/www/bitrix/data/www/tempusshop.ru/local/cron/marketplace/bad.txt", print_r('end: ' .$end, true).PHP_EOL, FILE_APPEND);

  			$file = new \CFile;
  			$fileId = $file->SaveFile(CFile::MakeFileArray($tempFile),'resize_mp_images');
  			//file_put_contents("/var/www/bitrix/data/www/tempusshop.ru/local/cron/marketplace/bad.txt", print_r('file id: ' .$fileId, true).PHP_EOL, FILE_APPEND);
				if (!empty($fileId)) {
					CFile::Delete($arItem["IMAGE_MARKETPLACE"]);
  				CIBlockElement::SetPropertyValueCode($id, "IMAGE_MARKETPLACE", array('VALUE' => $fileId));
				}
  			// CIBlockElement::SetPropertyValuesEx(
  			//     $id,
  			//     false,
  			//     array(
  			//         $propertyCode => array('VALUE' => $fileId)
  			//     )
  			// );
				unlink($tempFile);
				unlink($resizedImage);
				unlink($paddedImagePath);
        $i = $i + 1;
  			}
        unset($ARtmp);

      }
		}
    //file_put_contents("/var/www/bitrix/data/www/tempusshop.ru/local/cron/marketplace/bad.txt", print_r('count: ' .$i, true).PHP_EOL, FILE_APPEND);
		unset($arItem);
	}

}

(new Updater())->run();
?>
