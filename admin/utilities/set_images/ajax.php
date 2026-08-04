<?php
require_once($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/main/include/prolog_before.php');
// Отвечаем только на Ajax
//if ($_SERVER['HTTP_X_REQUESTED_WITH'] != 'XMLHttpRequest') {return;}

if(!CModule::IncludeModule('panel.manager'))return;
global $USER;

global $DB;
$objContent = new CPanelContent;
$objUtils = new CPanelUtils;
$objProduct = new CPanelProduct;
$objBrand = new CPanelBrand;
//$arResult["PROPS"] = $objContent->getProps();

global $USER;
$arGroups = $USER->GetUserGroupArray();

if (!$USER->IsAdmin() && !in_array(7, $arGroups))
{
    $arResult["ERROR"][] = "<p style='color:red;'>Доступ запрещен</p>";
    return ;
}

$arBrand = $objBrand->getList();

function findGoodArticle($article, $arBrand){
	$objUtils = new CPanelUtils;
	$article = mb_strtoupper($article);

	foreach($arBrand as $brand){
		if(strripos($article, $brand["name"]) !== false){
			$arClearStr = array();
			
			if (str_starts_with($brand["name"], $article)) {
				$arClearStr[] = mb_strtoupper($brand["name"]);
			}
			
			//альтернативные бренды
			if(strlen($brand["alt_name"]) > 0){
				$tmp = explode("|", $brand["alt_name"]);
				foreach($tmp as $key => &$name){
					$name = trim($name);
					if(strlen($name) > 0){
						$arClearStr[] = mb_strtoupper($name, "UTF-8");
					}
				}
				unset($name);
			}
			$article = str_replace($arClearStr, '', $article);

			$article = trim($article);
			//prent($article);
			if(strlen($brand["regular"]) > 2){
				preg_match($brand["regular"], $article, $matches);
				$matches = array_diff($matches, array(''));
				$matches = array_unique($matches);
				if($matches && count($matches) == 1 && strlen($matches[0]) > 0)
					$article = $matches[0];
			}

			$article = str_replace(array("  "), array(" "), $article);
			$article = trim($article);

			//если пятый символ -, то менять на J. если девятого символа нет - добавлять Y
			//if($brand_name == "Q&Q"){
			if($brand["id"] == 16){
				if($article[4] == "-") $article[4] = "J";
				if(strlen($article) == 8) $article[8] = "Y";
			}

			//для романсана 22 удаляем пробелы
			if($brand["id"] == 22){
				$article = str_replace(" ", "", $article);
			}

			//если поставщик 3. Денис (supplier_id = 39) и бренд Восток (brand_id = 38)
			if($brand["id"] == 38){
				$tmp = trim(array_pop(explode(" ", $article)));
				//$tmp = intval($tmp);
				//if($tmp > 0){
				if(strlen($tmp) > 0){
					$article = $tmp;

				}
			}

			//RA-KV0006Y10B
			//if($brand_name == "Orient"){
			if($brand["id"] == 2){
				if($article[2] == "-"){
					$article = substr($article, 0, 10);
				}else{
					$article = substr($article, 0, 9);
				}
			}elseif($brand["id"] == 14)
				$article = $article;
			elseif(strpos($article, " "))
				$article = strstr($article, " ", true);

			if($brand["id"] == 26 && $article[2] == "/"){
				$article = substr($article, 3);
			}

			//если Tissot то удаляем точку после буквы T
			if($brand["id"] == 20 && $article[0] == "T" && $article[1] == "."){
				$article = substr_replace($article, '', 1, 1);
			}
			break;
		}
	}

	//$alt_art = $objUtils->getArtnumber($art);
	$alt_art = $objUtils->getArtnumber($article);

	if($alt_art) return $alt_art; else return $article;
}

// Можно передавать в скрипт разный action и в соответствии с ним выполнять разные действия.

$arList = explode("\r\n", $_POST["list_articles"]);
$arList = array_diff($arList, array(''));

$action = $_POST['action'];

if (empty($action)) {return;}

if(is_array($arList) && count($arList) <= 0){
	$arResult["ERROR"][] = "<p style='color:red;'>Введите список</p>";
}

if($action == "stop"){
	$arResult["ERROR"][] = "<p style='color:red;'>Отмена</p>";
}

if($arResult["ERROR"]){
	$output = Array('offset' => 0, 'sucsess' => 1, 'error' => $arResult["ERROR"], 'info' => '');

	echo json_encode($output, JSON_UNESCAPED_UNICODE);

	header('Content-Type: application/json;charset=UTF-8');
	die();
}

$count = count($arList);
$step = 20;

// Получаем от клиента номер итерации
$offset = $_POST['offset'];

if($offset == 0){
	$_SESSION["STATUS_SET_IMAGES_CNT_1"] = 0;
	$_SESSION["STATUS_SET_IMAGES_CNT_2"] = 0;
	$_SESSION["STATUS_SET_IMAGES_CNT_3"] = 0;
}

$arListFilter = array_slice($arList, $offset, $step);

ob_start();

if(is_array($arListFilter) && count($arListFilter) > 0){
	//$arListFilter = explode("\r\n", $_POST["list_articles"]);
	//$arListFilter = array_diff($arListFilter, array(''));
	$el = new CIBlockElement;
	$arResult["ITEMS"] = array();

	foreach($arListFilter as $k => $list){
		$tmp = explode(";", $list);
		if(!is_array($tmp) || count($tmp) == 1){
			$tmp = explode("\t", $list);
		}
		//file_put_contents("/var/www/bitrix/data/www/tempusshop.ru/admin/utilities/set_images/1.txt", print_r([$list, $tmp], true), 8);
		$article = $tmp[0];
		$article = findGoodArticle($article, $arBrand);
		
		$arTmp = array_slice($tmp, 1);
		$arImg = [];
		foreach($arTmp as $v){
			if($v && str_starts_with($v, 'http')){
				$arImg[] = $v;
			}
		}
		//$arImg = array_slice($tmp, 1);
		$arImg = array_diff($arImg, array(''));
		/*$tmp = explode("@", $list);
		$article = $tmp[0];
		$article = findGoodArticle($article, $arBrand);

		$tmp[1] = str_replace("; ", ";", $tmp[1]);
		$arImg = explode(";", $tmp[1]);
		$arImg = array_diff($arImg, array(''));
		*/
		$arResult["ITEMS"][] = array(
			"LINE" => $k + 1,
			"ARTICLE" => $article,
			"ARTICLE_ORIGINAL" => $tmp[0],
			"LINK" => $arImg,
		);
	}

	foreach($arResult["ITEMS"] as $key => $arItem){
		$article = "ор. {$arItem["ARTICLE_ORIGINAL"]}, {$arItem["ARTICLE"]}";
		if($ELEMENT_ID = CPanelProduct::findArticle($arItem["ARTICLE"])){
			$db_props = CIBlockElement::GetProperty(CProSet::IB_CATALOG, $ELEMENT_ID, array("sort" => "asc"), Array("CODE"=>"MORE_PHOTO"));
			$arProp = array();
			while($ar_props = $db_props->Fetch()){
				$arProp[] = $ar_props["PROPERTY_VALUE_ID"];
			}
			$images = array();
			$arLink = $arItem["LINK"];

			if($_POST["replace3"] == "Y"){

				if ( filter_var($arLink[0], FILTER_VALIDATE_URL) )
				{
					$img_info = $objProduct->getImgInfo($arLink[0]);
					$arFilter = [
						"IBLOCK_ID" => 16,
						"ID" => $ELEMENT_ID,
					];
					$arSelect = ["IBLOCK_ID", "ID", "PROPERTY_INFOGRAPH_BASE", "PROPERTY_CML2_ARTICLE"];
					$res = CIBlockElement::GetList(array(), $arFilter, false, false, $arSelect);
					$itemIGP = '';
					while ( $r = $res->GetNext() ){
						$itemIGP = $r["PROPERTY_INFOGRAPH_BASE_VALUE"];
					}

					/*if( $img_info ){

						$types = array("", "gif", "jpeg", "png");
						$ext = $types[$img_info[2]];

						if($ext != 'png') continue;

						$img = $this->CutImageAlpha(imagecreatefrompng($link), $img_info[0], $img_info[1]);

						$path = $_SERVER["DOCUMENT_ROOT"].'/upload/newci/'.md5(rand()).'.'.$ext;
						imagepng($img, $path);

						if($btrx_img = CFile::MakeFileArray($path) ){
						   return $btrx_img;
						}
					}*/

					if( $img_info ) {
						// var_dump($img_info);
						$types = array("", "gif","jpeg","png");
						$ext = $types[ $img_info[2] ];

						if($img_info[2] == 18) $ext = "webp";

						$func = 'imagecreatefrom'.$ext;
						// $img = $objProduct->CutImage( $func($arLink[0]), $img_info[0], $img_info[1] );
						//$img = $func($arLink[0]);
						
						$img = $objProduct->CutImageAlpha($func($arLink[0]), $img_info[0], $img_info[1]);
						/*if ( $ext == 'png' ){
							imageAlphaBlending($img, true);
							imageSaveAlpha($img, true);
						}*/
						$path = $_SERVER["DOCUMENT_ROOT"].'/upload/info_graph_base/'.md5(rand()).'.'.$ext;
						imagepng( $img, $path );
						if( $btrx_img = CFile::MakeFileArray($path) ){
							$images[$arProp[0]]["VALUE"] = $btrx_img;

							$images[$arProp[0]]["DESCRIPTION"] = "";

							// $arFields['INFOGRAPH_BASE'] = $images[$arProp[0]]["VALUE"];
							// $arFields['INFOGRAPH_BASE']["del"] = $arFields['PROPERTY_INFOGRAPH_BASE']["del"] = "Y";
							//
							// $el->Update($ELEMENT_ID, $arFields);

							$file = new \CFile;
							$fileId = $file->SaveFile($btrx_img,'info_graph_base');
							if ( !empty($fileId) ){
								if ( !empty($itemIGP) ) CFile::Delete($itemIGP);
								// print_r( 'fileId --- ' .$fileId . '<br>');
								CIBlockElement::SetPropertyValueCode($ELEMENT_ID, "INFOGRAPH_BASE", array('VALUE' => $fileId));
							}

							$objProduct->genPreviewImage($ELEMENT_ID);
							unset($arProp[0]);
							unset($arLink[0]);
							$_SESSION["STATUS_SET_IMAGES_CNT_3"]++;
						}else{
						  $arResult["ERROR"][] = "<p style='color:red;'>{$article} - ссылка {$arLink[0]} не удалось получить путь к файлу</p>";
						}
					}else{
						$arResult["ERROR"][] = "<p style='color:red;'>{$article} - ссылка {$arLink[0]} не удалось получить размер файла</p>";
					}
				}else{
					$arResult["ERROR"][] = "<p style='color:red;'>{$article} - не валидная ссылка {$arLink[0]}</p>";
				}
			}

			if($_POST["replace1"] == "Y"){

				if ( filter_var($arLink[0], FILTER_VALIDATE_URL) )
				{
					$img_info = $objProduct->getImgInfo($arLink[0]);
					if($img_info)
					{
						$types = array("", "gif", "jpeg", "png");
						$ext = $types[$img_info[2]];

						if($img_info[2] == 18) $ext = "webp";

						$func = 'imagecreatefrom'.$ext;

						$img = $objProduct->CutImage( $func($arLink[0]), $img_info[0], $img_info[1] );

						$path = $_SERVER["DOCUMENT_ROOT"].'/upload/newci/'.md5(rand()).'.'.$ext;
						imagejpeg( $img, $path );
						if( $btrx_img = CFile::MakeFileArray($path) ){
							$images[$arProp[0]]["VALUE"] = $btrx_img;

							$images[$arProp[0]]["DESCRIPTION"] = "";

							$arFields['DETAIL_PICTURE'] = $images[$arProp[0]]["VALUE"];
							$arFields['DETAIL_PICTURE']["del"] = $arFields['PREVIEW_PICTURE']["del"] = "Y";

							$el->Update($ELEMENT_ID, $arFields);

							$objProduct->genPreviewImage($ELEMENT_ID);
//							prent($arFields);
							unset($arProp[0]);
							unset($arLink[0]);

							if($_POST["replace2"] != "Y"){
								foreach($arProp as $prop){
									$images[$prop] = array(
										"VALUE" => array(
											"name" => "",
											"type" => "",
											"tmp_name" => "",
											"error" => 4,
											"size" => 0,
											"description" => "",
										),
										"DESCRIPTION" => "",
									);
								}
							}
							$_SESSION["STATUS_SET_IMAGES_CNT_1"]++;
						}else{
							$arResult["ERROR"][] = "<p style='color:red;'>{$article} - ссылка {$arLink[0]} не удалось получить путь к файлу</p>";
						}
					}else{
						$arResult["ERROR"][] = "<p style='color:red;'>{$article} - ссылка {$arLink[0]} не удалось получить размер файла</p>";
					}
				}else{
					$arResult["ERROR"][] = "<p style='color:red;'>{$article} - не валидная ссылка {$arLink[0]}</p>";
				}
			}

			if($_POST["replace2"] == "Y"){

				if($_POST["replace1"] != "Y"){
					$images[$arProp[0]] = array(
						"VALUE" => array(
							"name" => "",
							"type" => "",
							"tmp_name" => "",
							"error" => 4,
							"size" => 0,
							"description" => "",
						),
						"DESCRIPTION" => "",
					);
				}

				$i = 0;
				foreach($arLink as &$link)
				{
					if ( filter_var($link, FILTER_VALIDATE_URL) )
					{
						$img_info = $objProduct->getImgInfo($link);

						if($img_info)
						{
							if($img_info["link"]) $link = $img_info["link"];

							$types = array("", "gif", "jpeg", "png");
							$ext = $types[$img_info[2]];

							if($img_info[2] == 18) $ext = "webp";

							$func = 'imagecreatefrom'.$ext;
							$img = $objProduct->CutImage( $func($link), $img_info[0], $img_info[1] );

							$path = $_SERVER["DOCUMENT_ROOT"].'/upload/newci/'.md5(rand()).'.'.$ext;
							imagejpeg( $img, $path );
							if( $btrx_img = CFile::MakeFileArray($path) ){
								$images["n{$i}"]["VALUE"] = $btrx_img;

								$images["n{$i}"]["DESCRIPTION"] = "";

								$i++;
								$_SESSION["STATUS_SET_IMAGES_CNT_2"]++;
							}else{
								$arResult["ERROR"][] = "<p style='color:red;'>{$article} - ссылка {$link} не удалось получить путь к файлу</p>";
							}
						}else{
							$arResult["ERROR"][] = "<p style='color:red;'>{$article} - ссылка {$link} не удалось получить размер файла</p>";
						}
					}else{
						$arResult["ERROR"][] = "<p style='color:red;'>{$article} - не валидная ссылка {$link}</p>";
					}
				}
				unset($link);
			}

			if($_POST["replace1"] != "Y" && $_POST["replace2"] != "Y" && $_POST["replace3"] != "Y"){
				foreach($arProp as $prop){
					$images[$prop] = array(
						"VALUE" => array(
							"name" => "",
							"type" => "",
							"tmp_name" => "",
							"error" => 4,
							"size" => 0,
							"description" => "",
						),
						"DESCRIPTION" => "",
					);
				}

				$i = 0;
				foreach($arLink as &$link)
				{
					if ( filter_var($link, FILTER_VALIDATE_URL) )
					{
						$img_info = $objProduct->getImgInfo($link);

						if($img_info)
						{
							if($img_info["link"]) $link = $img_info["link"];

							$types = array("", "gif", "jpeg", "png");

							$ext = $types[$img_info[2]];

							if($img_info[2] == 18) $ext = "webp";

							$func = 'imagecreatefrom'.$ext;
							$img = $objProduct->CutImage( $func($link), $img_info[0], $img_info[1] );
							$path = $_SERVER["DOCUMENT_ROOT"].'/upload/newci/'.md5(rand()).'.'.$ext;
							imagejpeg( $img, $path );
							if( $btrx_img = CFile::MakeFileArray($path) ){
								$images["n{$i}"]["VALUE"] = $btrx_img;

								$images["n{$i}"]["DESCRIPTION"] = "";

								//$images[$arProp[0]]["VALUE"] = $btrx_img;
								//$images[$arProp[0]]["DESCRIPTION"] = "";
								//unset($arProp[0]);
								$i++;
								$_SESSION["STATUS_SET_IMAGES_CNT_2"]++;
							}else{
								$arResult["ERROR"][] = "<p style='color:red;'>{$article} - ссылка {$link} не удалось получить путь к файлу</p>";
							}
						}else{
							$arResult["ERROR"][] = "<p style='color:red;'>{$article} - ссылка {$link} не удалось получить размер файла</p>";
						}
					}else{
						$arResult["ERROR"][] = "<p style='color:red;'>{$article} - не валидная ссылка {$link}</p>";
					}
				}
				unset($link);
			}
			if ( $_POST["replace3"] != "Y" ){
				CIBlockElement::SetPropertyValuesEx( $ELEMENT_ID, false, array("MORE_PHOTO" => $images) );
				
			}
		}else{
			$arResult["ERROR"][] = "<p style='color:red;'>{$article} - не найден ID товара</p>";
		}

	}
}

// Проверяем, все ли строки обработаны
$offset = $offset + $step;

if ($offset >= $count) {
	$sucsess = 1;

	$arCount = array_count_values ($arList);
	foreach($arCount as $val => $cnt){
		if($cnt > 1){
			$arResult["INFO"][] = "<p style='color:red;'>{$val} - количество повторений {$cnt}</p>";
		}
	}

	$arResult["INFO"][] = "<p>Всех элементов в списке - " . count($arList) . ". Установлено - 'Перезаписать детальную и первую дополнительную' - {$_SESSION["STATUS_SET_IMAGES_CNT_1"]}, 'Перезаписать со второй доп.картинки' - {$_SESSION["STATUS_SET_IMAGES_CNT_2"]}, 'Перезаписать базу для инфографики' - {$_SESSION["STATUS_SET_IMAGES_CNT_3"]}</p>";
} else {
	$sucsess = round($offset / $count, 2);
}

ob_end_clean();
// И возвращаем клиенту данные (номер итерации и сообщение об окончании работы скрипта)
$output = Array('offset' => $offset, 'sucsess' => $sucsess, 'error' => $arResult["ERROR"], 'info' => $arResult["INFO"]);

echo json_encode($output, JSON_UNESCAPED_UNICODE);

header('Content-Type: application/json;charset=UTF-8');
die();