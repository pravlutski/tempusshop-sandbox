<?$_SERVER["DOCUMENT_ROOT"] = "/var/www/bitrix/data/www/tempusshop.ru";
$DOCUMENT_ROOT = $_SERVER["DOCUMENT_ROOT"];

define("NO_KEEP_STATISTIC", true);
define("NOT_CHECK_PERMISSIONS", true);

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");
set_time_limit(3600);
ini_set('memory_limit','2048M');
if(!CModule::IncludeModule('panel.manager'))return;?>

<?
foreach ((array)$_SERVER['argv'] as $v){
	list($k,$v) = explode("=",$v);
	if ($k && $v) $_REQUEST[$k] = $v;
}

$objContent = new CPanelContent;
$objUtils = new CPanelUtils;
$objProduct = new CPanelProduct;
$objBrand = new CPanelBrand;
//$arResult["PROPS"] = $objContent->getProps();
//prent($_POST);
$arBrand = $objBrand->getList();

function findGoodArticle($article, $arBrand){
	$objUtils = new CPanelUtils;
	$article = mb_strtoupper($article);

	foreach($arBrand as $brand){
		if(strripos($article, $brand["name"]) !== false){
			$arClearStr = array();

			$arClearStr[] = mb_strtoupper($brand["name"]);
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

//$arFields['PREVIEW_PICTURE'] = CFile::ResizeImageGet("/var/www/bitrix/data/www/tempusshop.ru/upload/newci/687775f9c3ab6b264b2000f78d852f1c.jpeg", array('width'=>200, 'height'=>200), BX_RESIZE_IMAGE_PROPORTIONAL);

if ($_REQUEST["PARSE"] == "Y"){
	ob_start();


  $arList = file_get_contents('/var/www/bitrix/data/www/tempusshop.ru/admin/utilities/photo.txt');
  $arList = explode("\n", $arList);

	$arList = array_diff($arList, array(''));
	$el = new CIBlockElement;
	$arResult["ITEMS"] = array();
	foreach($arList as $list){
    if (!empty($list)) {
  		$tmp = explode("@", $list);
  		$article = $tmp[0];
  		$article = findGoodArticle($article, $arBrand);

  		$tmp[1] = str_replace("; ", ";", $tmp[1]);
  		$arImg = explode(";", $tmp[1]);
  		$arImg = array_diff($arImg, array(''));

  		$arResult["ITEMS"][] = array(
  			"ARTICLE" => $article,
  			"LINK" => $arImg,
  		);
    }
	}
	foreach($arResult["ITEMS"] as $key => $arItem){
		if($ELEMENT_ID = CPanelProduct::findArticle($arItem["ARTICLE"])){
			$db_props = CIBlockElement::GetProperty(CProSet::IB_CATALOG, $ELEMENT_ID, array("sort" => "asc"), Array("CODE"=>"MORE_PHOTO"));
			$arProp = array();
			while($ar_props = $db_props->Fetch()){
				$arProp[] = $ar_props["PROPERTY_VALUE_ID"];
				//prent($ar_props);
			}
			$images = array();
			//die;
			//$arProp = array_reverse($arProp);

			$arLink = $arItem["LINK"];


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

						}else{
							echo "{$article} - ссылка {$arLink[0]} не удалось получить путь к файлу\n";
						}
					}else{
						echo "{$article} - ссылка {$arLink[0]} не удалось получить размер файла\n";
					}
				}else{
					echo "{$article} - не валидная ссылка {$arLink[0]}\n";
				}
			}


			CIBlockElement::SetPropertyValuesEx($ELEMENT_ID, false, array("MORE_PHOTO" => $images));
      echo "{$ELEMENT_ID} - фото обновленно";
			//	echo "<p style='color:green'>{$ID} - {$article} установлен</p>";

	}
	//	prent($_POST);

	$set_text = ob_get_clean();
}
//sdfsdf@https://www.casio-europe.com/resource/images/watch/zoom/HS-80TW-1EF.jpg;https://opt-99999999.ssl.1c-bitrix-cdn.ru/main/706/7063f519fc1a6190dc9de2a9aedaddee/bitrix-property-tables-file-cell.png
?>
