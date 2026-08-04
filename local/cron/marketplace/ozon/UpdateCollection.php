#!/usr/bin/php
<?php
$_SERVER["DOCUMENT_ROOT"] = "/var/www/bitrix/data/www/tempusshop.ru";
$DOCUMENT_ROOT = $_SERVER["DOCUMENT_ROOT"];

define("NO_KEEP_STATISTIC", true);
define("NOT_CHECK_PERMISSIONS", true);

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");
set_time_limit(3600);

use Bitrix\Main\Application,
	Bitrix\Main\Loader;

class UpdateCollection{
	public function __construct(){
		global $DB;
		$this->loadModules();

		$this->db = $DB;

		$this->logger = new TsLogger("/ozon/" . __CLASS__ . "/");
		$this->workers = new WorkersChecker(__CLASS__);

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

		if (!$this->workers->checkStatus()) {
			$this->logger->log("LOG", "Обработчик занят");
			exit();
		}
		//file_put_contents("/var/www/bitrix/data/www/tempusshop.ru/local/cron/marketplace/ozon/bad.txt", print_r('START ', true).PHP_EOL,);
		$this->workers->updateStatus("Y");

		$this->getItems();
		$this->prepareItems();
		$this->updateProperty();

		$this->workers->updateStatus("N");
	}

	public function getItems(){
		$arSelect = Array("ID", "PROPERTY_COLLECTION", "PROPERTY_BRAND", "PROPERTY_CML2_ARTICLE", "PROPERTY_COLORTERM","IMAGE_MARKETPLACE","PROPERTY_126","DETAIL_PICTURE","TIMESTAMP_X");
		$arFilter = Array(
			"IBLOCK_ID" => CProSet::IB_CATALOG,
			//"ID" => 12889
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
				"COLORTERM_UPDATE" => "",
			];

		}
	}

	public function prepareItems(){
		foreach($this->items as $key => &$arItem){
			if($arItem["BRAND_ID"] == 7971){
				// casio
				preg_match("/([a-zA-Z0-9]{1,3}-[a-zA-Z0-9]{1,2}[0-9]+).+/i", $arItem["ARTICLE"], $matches);
				if(is_array($matches) && count($matches) == 2){
					$arItem["COLLECTION_UPDATE"] = $matches[1];

					$cTerm = str_replace($matches[1], "", $arItem["ARTICLE"]);
					$cTerm = trim($cTerm, "-");
					$arItem["COLORTERM_UPDATE"] = $cTerm;
				}
			}
			if($arItem["BRAND_ID"] == 36661){
				//Q&Q
				preg_match("/(.+J)/", $arItem["ARTICLE"], $matches);
				if(is_array($matches) && count($matches) == 2){
					$arItem["COLLECTION_UPDATE"] = $matches[1];

					$cTerm = str_replace($matches[1], "", $arItem["ARTICLE"]);
					$arItem["COLORTERM_UPDATE"] = $cTerm;
				}
			}
			if($arItem["BRAND_ID"] == 43588){
				//Citizen
				preg_match("/(.+-)/", $arItem["ARTICLE"], $matches);
				if(is_array($matches) && count($matches) == 2){
					$newm = trim($matches[1], "-");
					$arItem["COLLECTION_UPDATE"] = $newm;
					$cTerm = str_replace($matches[1], "", $arItem["ARTICLE"]);
					$arItem["COLORTERM_UPDATE"] = $cTerm;
				}
			}
			if($arItem["BRAND_ID"] == 75585){
				//Seiko
				$parts = preg_split('/(?=\d)/', $arItem["ARTICLE"], 2);
				if (count($parts) === 2) {
					$arItem["COLLECTION_UPDATE"] = $parts[0];
				  $arItem["COLORTERM_UPDATE"] = $parts[1];
				}
			}
			if($arItem["BRAND_ID"] == 7973){
				//Orient
				preg_match('/^([A-Za-z]+[0-9]{2})(.*)$/', $arItem["ARTICLE"], $matches);
				if (is_array($matches) && count($matches) == 3) {
					$arItem["COLLECTION_UPDATE"] = $matches[1];
					$cTerm = str_replace($matches[1], "", $arItem["ARTICLE"]);
					$arItem["COLORTERM_UPDATE"] = $cTerm;
				} else {
					preg_match('/^((.*)-(.{4}))(.*)$/', $arItem["ARTICLE"], $matches);
					if (is_array($matches)) {
						$arItem["COLLECTION_UPDATE"] = $matches[1];
						$cTerm = str_replace($matches[1], "", $arItem["ARTICLE"]);
						$arItem["COLORTERM_UPDATE"] = $cTerm;
					}
				}
				if (empty($arItem["COLLECTION_UPDATE"])) {
					preg_match('/^([A-Za-z]+\d[A-Za-z])(.*)$/', $arItem["ARTICLE"], $matches);
					if (is_array($matches)) {
						$arItem["COLLECTION_UPDATE"] = $matches[1];
						$cTerm = str_replace($matches[1], "", $arItem["ARTICLE"]);
						$arItem["COLORTERM_UPDATE"] = $cTerm;
					}
				}
			}

			if($arItem["BRAND_ID"] == 43508){
				//Tissot
					preg_match('/^([^\.]+\.[^\.]+\.)(.*)$/', $arItem["ARTICLE"], $matches);
					if (is_array($matches) && count($matches) == 3) {
						$arItem["COLLECTION_UPDATE"] = $matches[1];
						$cTerm = str_replace($matches[1], "", $arItem["ARTICLE"]);
						$arItem["COLORTERM_UPDATE"] = $cTerm;
					}
			}
			if($arItem["BRAND_ID"] == 118278){
				//Восток
				$newm = substr($arItem["ARTICLE"], 0, 4);
				$arItem["COLLECTION_UPDATE"] = $newm;
				$cTerm = str_replace($newm, "", $arItem["ARTICLE"]);
				$arItem["COLORTERM_UPDATE"] = $cTerm;
			}

		}
		//BGA-152-7B1
		unset($arItem);
	}

	public function updateProperty(){
		foreach($this->items as $key => $arItem){
			if($arItem["COLLECTION"] != $arItem["COLLECTION_UPDATE"]){

				$text = "Обновили COLLECTION " . $arItem["ID"] . " - Был {$arItem["COLLECTION"]}, стал {$arItem["COLLECTION_UPDATE"]}";
				$this->logger->log("LOG", $text);

				CIBlockElement::SetPropertyValuesEx($arItem["ID"], false, array("COLLECTION" => $arItem["COLLECTION_UPDATE"]));
			}
			if($arItem["COLORTERM"] != $arItem["COLORTERM_UPDATE"]){
				$text = "Обновили COLORTERM " . $arItem["ID"] . " - Был {$arItem["COLORTERM"]}, стал {$arItem["COLORTERM_UPDATE"]}";
				$this->logger->log("LOG", $text);
				CIBlockElement::SetPropertyValuesEx($arItem["ID"], false, array("COLORTERM" => $arItem["COLORTERM_UPDATE"]));
			}
		}
	}

	public function updateNameMP(){
		foreach($this->items as $key => &$arItem){
			if ((time() - 1 * 24 * 60 * 60) < strtotime($arItem['TIMESTAMP_X'])){
				$arSection = getSectionsElement($arItem["ID"]);
				$dsc1 = $arItem['TYPE'] . " " . mb_strtolower($arSection[0]["NAME"]) . " {$arSection[1]["NAME"]} {$arSection[2]["NAME"]} {$arItem['ARTICLE']}";

				CIBlockElement::SetPropertyValueCode($arItem["ID"], "NAME_MARKETPLACE", array('VALUE' => $dsc1));
				unset($dsc1);
			}
		}
		unset($arItem);
	}

	public function updatePhotoMP(){
		foreach($this->items as $key => &$arItem){
			if (!empty($arItem['DETAIL_PICTURE']) and (time() - 1 * 24 * 60 * 60) < strtotime($arItem['TIMESTAMP_X'])) {
				$id = $arItem['ID'];
        // file_put_contents("/var/www/bitrix/data/www/tempusshop.ru/local/cron/marketplace/ozon/bad.txt", print_r('id: ' .$id, true).PHP_EOL, FILE_APPEND);
        // file_put_contents("/var/www/bitrix/data/www/tempusshop.ru/local/cron/marketplace/ozon/bad.txt", print_r('fields: ', true).PHP_EOL, FILE_APPEND);
        // file_put_contents("/var/www/bitrix/data/www/tempusshop.ru/local/cron/marketplace/ozon/bad.txt", print_r($arItem, true).PHP_EOL, FILE_APPEND);
        $desiredWidth = 600;
        $desiredHeight = 800;

        $watermarkImagePath = '/var/www/bitrix/data/www/tempusshop.ru/upload/testImg/watermark_4.png';
        $file = '/var/www/bitrix/data/www/tempusshop.ru' . CFile::GetPath($arItem["DETAIL_PICTURE"]);
        $resizedImage = '/var/www/bitrix/data/www/tempusshop.ru/upload/testImg/resized_tmp_'.$id.'.jpg';
        unlink($resizedImage);
        CFile::ResizeImageFile(
           $file,
           $resizedImage,
           array('width'=>$desiredWidth,'height'=>$desiredHeight),
           BX_RESIZE_IMAGE_PROPORTIONAL,
           array(),
           100,
           false
        );

        //$resizedImage = '/var/www/bitrix/data/www/tempusshop.ru'. $resizedImage['src'];
        // file_put_contents("/var/www/bitrix/data/www/tempusshop.ru/local/cron/marketplace/ozon/bad.txt", print_r('res2: ', true).PHP_EOL, FILE_APPEND);
        // file_put_contents("/var/www/bitrix/data/www/tempusshop.ru/local/cron/marketplace/ozon/bad.txt", print_r($resizedImage, true).PHP_EOL, FILE_APPEND);
        $inf = getimagesize($resizedImage);
        // file_put_contents("/var/www/bitrix/data/www/tempusshop.ru/local/cron/marketplace/ozon/bad.txt", print_r('inf: ', true).PHP_EOL, FILE_APPEND);
        // file_put_contents("/var/www/bitrix/data/www/tempusshop.ru/local/cron/marketplace/ozon/bad.txt", print_r($inf, true).PHP_EOL, FILE_APPEND);
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
            //file_put_contents("/var/www/bitrix/data/www/tempusshop.ru/local/cron/marketplace/ozon/bad.txt", print_r($resas, true).PHP_EOL, FILE_APPEND);
            $resizedImage = $paddedImagePath;
        }

        $arWatermark = array(
           'position' => 'br',//допустимые http://prntscr.com/c3hc9i
           'type' => 'file',
           'size' => 'real',
           'alpha_level' => 100,//прозрачность
           'file' => $watermarkImagePath ,
        );
        //file_put_contents("/var/www/bitrix/data/www/tempusshop.ru/local/cron/marketplace/ozon/bad.txt", print_r('wt: ' .$arWatermark, true).PHP_EOL, FILE_APPEND);
        $tempFile = '/var/www/bitrix/data/www/tempusshop.ru/upload/testImg/ready_image_'.$id.'.jpg';
        unlink($tempFile);
        CFile::ResizeImageFile(
           $resizedImage,
           $tempFile,
           array('width'=>$desiredWidth,'height'=>$desiredHeight),
           BX_RESIZE_IMAGE_PROPORTIONAL,
           $arWatermark,
           100,
           false
        );
          //file_put_contents("/var/www/bitrix/data/www/tempusshop.ru/local/cron/marketplace/ozon/bad.txt", print_r('end: ' .$end, true).PHP_EOL, FILE_APPEND);

			$file = new \CFile;
			$fileId = $file->SaveFile(CFile::MakeFileArray($tempFile),'resize_mp_images');
			//file_put_contents("/var/www/bitrix/data/www/tempusshop.ru/local/cron/marketplace/ozon/bad.txt", print_r('file id: ' .$fileId, true).PHP_EOL, FILE_APPEND);

			CIBlockElement::SetPropertyValueCode($id, "IMAGE_MARKETPLACE", array('VALUE' => $fileId));
			// CIBlockElement::SetPropertyValuesEx(
			//     $id,
			//     false,
			//     array(
			//         $propertyCode => array('VALUE' => $fileId)
			//     )
			// );
			}
		}
		unset($arItem);
	}


}

(new UpdateCollection())->run();
?>
