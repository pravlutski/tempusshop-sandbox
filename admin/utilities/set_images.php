<?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/header.php");?>
<?if(!CModule::IncludeModule('panel.manager'))return;?>
<h1 class="page-header">Загрузить картинки к товарам</h1>

<?

$objContent = new CPanelContent;
$objUtils = new CPanelUtils;
$objProduct = new CPanelProduct;
$objBrand = new CPanelBrand;
//$arResult["PROPS"] = $objContent->getProps();

global $USER;
$arGroups = $USER->GetUserGroupArray();

if (!$USER->IsAdmin() && !in_array(7, $arGroups))
{
    $APPLICATION->AuthForm(GetMessage("PERMISION_DENIED"));
    return ;
}
//prent($_POST);
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

//$arFields['PREVIEW_PICTURE'] = CFile::ResizeImageGet("/var/www/bitrix/data/www/tempusshop.ru/upload/newci/687775f9c3ab6b264b2000f78d852f1c.jpeg", array('width'=>200, 'height'=>200), BX_RESIZE_IMAGE_PROPORTIONAL);

if (($USER->IsAdmin() || in_array(7, $arGroups)) && $_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST["set_images"])){
	ob_start();
	$arList = explode("\r\n", $_POST["list_articles"]);
	$arList = array_diff($arList, array(''));
	$el = new CIBlockElement;
	$arResult["ITEMS"] = array();

	foreach($arList as $k => $list){
		$tmp = explode("@", $list);
		$article = $tmp[0];
		$article = findGoodArticle($article, $arBrand);

		$tmp[1] = str_replace("; ", ";", $tmp[1]);
		$arImg = explode(";", $tmp[1]);
		$arImg = array_diff($arImg, array(''));

		$arResult["ITEMS"][] = array(
			"LINE" => $k + 1,
			"ARTICLE" => $article,
			"ARTICLE_ORIGINAL" => $tmp[0],
			"LINK" => $arImg,
		);
	}

	foreach($arResult["ITEMS"] as $key => $arItem){
		$article = "строка {$arItem["LINE"]} - ор. {$arItem["ARTICLE_ORIGINAL"]}, {$arItem["ARTICLE"]}";
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

      if($_POST["replace3"] == "Y"){

        if ( filter_var($arLink[0], FILTER_VALIDATE_URL) )
        {
          $img_info = $objProduct->getImgInfo($arLink[0]);
          // print_r($ELEMENT_ID . '<br>');
          // var_dump($img_info );
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
          // print_r( 'itemIGP --- ' . $itemIGP . '<br>');
          if( $img_info ) {
            // var_dump($img_info);
            $types = array("", "gif","jpeg","png");
            $ext = $types[ $img_info[2] ];

            if($img_info[2] == 18) $ext = "webp";

            $func = 'imagecreatefrom'.$ext;
            // $img = $objProduct->CutImage( $func($arLink[0]), $img_info[0], $img_info[1] );
            $img = $func($arLink[0]);
            if ( $ext == 'png' ){
              imageAlphaBlending($img, true);
              imageSaveAlpha($img, true);
            }
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
//							prent($arFields);
              unset($arProp[0]);
              unset($arLink[0]);

            }else{
              echo "<p style='color:red;'>{$article} - ссылка {$arLink[0]} не удалось получить путь к файлу</p>";
            }
          }else{
            echo "<p style='color:red;'>{$article} - ссылка {$arLink[0]} не удалось получить размер файла</p>";
          }
        }else{
          echo "<p style='color:red;'>{$article} - не валидная ссылка {$arLink[0]}</p>";
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
						}else{
							echo "<p style='color:red;'>{$article} - ссылка {$arLink[0]} не удалось получить путь к файлу</p>";
						}
					}else{
						echo "<p style='color:red;'>{$article} - ссылка {$arLink[0]} не удалось получить размер файла</p>";
					}
				}else{
					echo "<p style='color:red;'>{$article} - не валидная ссылка {$arLink[0]}</p>";
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
							}else{
								echo "<p style='color:red;'>{$article} - ссылка {$link} не удалось получить путь к файлу</p>";
							}
						}else{
							echo "<p style='color:red;'>{$article} - ссылка {$link} не удалось получить размер файла</p>";
						}
					}else{
						echo "<p style='color:red;'>{$article} - не валидная ссылка {$link}</p>";
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
							}else{
								echo "<p style='color:red;'>{$article} - ссылка {$link} не удалось получить путь к файлу</p>";
							}
						}else{
							echo "<p style='color:red;'>{$article} - ссылка {$link} не удалось получить размер файла</p>";
						}
					}else{
						echo "<p style='color:red;'>{$article} - не валидная ссылка {$link}</p>";
					}
				}
				unset($link);
			}
      if ( $_POST["replace3"] != "Y" ){
        CIBlockElement::SetPropertyValuesEx( $ELEMENT_ID, false, array("MORE_PHOTO" => $images) );
      }
			//	echo "<p style='color:green'>{$ID} - {$article} установлен</p>";

		}else{
				echo "<p style='color:red;'>{$article} - не найден ID товара</p>";
		}

	}
	//	prent($_POST);

	$set_text = ob_get_clean();
}
//sdfsdf@https://www.casio-europe.com/resource/images/watch/zoom/HS-80TW-1EF.jpg;https://opt-99999999.ssl.1c-bitrix-cdn.ru/main/706/7063f519fc1a6190dc9de2a9aedaddee/bitrix-property-tables-file-cell.png
?>
<form action="/admin/utilities/set_images.php" method="post" >
	<div class="page_header_selects clearfix">
		<div class="col-sm-12 row" style=" margin: 0;">
			<label style="">Список (Артикул@урл1;урл2;урл3)</label>
			<textarea class="form-control select_w" name="list_articles" style="width: 100%;height: 200px;font-size: 9px;"><?if($_POST["list_articles"]):?><?=addslashes($_POST["list_articles"])?><?endif?></textarea>
		</div>
		<div class="col-sm-12 row panel panel-default" style=" ">
			<div class="" style="padding: 10px 0 10px 4px;    margin: 10px 0 10px 0;">
				<input type="checkbox" class="btn-checkbox" id="replace1" name="replace1" value="Y" <?if($_POST["replace1"] == "Y"):?>checked<?endif?>>
				<label for="replace1" style="line-height: 18px;float: left;">Перезаписать детальную и первую дополнительную</label>
			</div>
			<div class="" style="padding: 10px 0 10px 4px;    margin: 20px 0 25px 0;">
				<input type="checkbox" class="btn-checkbox" id="replace2" name="replace2" value="Y" <?if($_POST["replace2"] == "Y"):?>checked<?endif?>>
				<label for="replace2" style="line-height: 18px;float: left;">Перезаписать со второй доп.картинки</label>
			</div>
      <div class="" style="padding: 10px 0 10px 4px;    margin: 20px 0 25px 0;">
        <input type="checkbox" class="btn-checkbox" id="replace3" name="replace3" value="Y" <?if($_POST["replace3"] == "Y"):?>checked<?endif?>>
        <label for="replace3" style="line-height: 18px;float: left;">Перезаписать базу для инфографики</label>
      </div>
			<span class="badge" style="margin: 0 0 10px 5px;">Если не выбран ни один селектор картинки будут добавлены в конец списка к дополнительным</span>
		</div>

	</div>

	<input type="submit" class="btn btn-primary btn_big_width" name="set_images" value="Установить">
</form>

<?
echo $set_text;
?>
<?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/footer.php");?>
