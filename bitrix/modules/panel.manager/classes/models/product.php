<?
class CPanelProduct{
	public $LAST_ERROR;
	function __construct(){
		$this->logger = new TsLogger("/CPanelProduct/");
	}

	function addProduct($id){
		global $DB;

		if(!$id || !$model = $this->getProduct($id)) {
			$this->LAST_ERROR = "Нет записи {$id} в таблице контент редактора";
			return false;
		}
		
		$this->logger->log("LOG", "Добавляем товар", [$id]);
		
		//$fields = unserialize($model['fields']);
		//$fields = json_decode($model['fields'], true);
				// !временно / потом оставить json_decode
		if(isJSON($model["fields"])){
			$fields = json_decode((string)$model["fields"], true );
		}else{
			$fields = unserialize($model["fields"]);
		}

		$props = json_decode((string)$model['props'], true);
		//prent($fields);
		if(intval($fields['collection']) <= 0 || intval($fields['brand']) <= 0) {
			$this->LAST_ERROR = "Нет свойств в таблице контент редактора. Товар не будет создан";
			return false;
		}
		
		$this->logger->log("LOG", "props", $props);
		
		if( $res = CIBlockSection::GetByID( $fields['brand'] ) ){
            $sec = $res->Fetch();
			$brand_name = $sec['NAME'];
        }
		$product_id = false;
		
		$this->logger->log("LOG", "brand_name", $brand_name);
		
		//$links = [$fields['img_1'],$fields['img_2'],$fields['img_3'],$fields['img_4'],$fields['img_5'],$fields['img_6']];
		$images = $this->ProcessImages($fields["img_watch"]);
		
		$this->logger->log("LOG", "images", $images);
		
		$img_infograph = [];
		if($fields['infograph']){
			$links = [$fields['infograph']];
			$img_infograph = $this->ProcessImages($links);

			if(!$img_infograph && $fields["img_watch"]){
				$img = $this->findInfographImg($fields["img_watch"]);
				if($img){
					$img_infograph = [$img];
				}
			}
		}
		$this->logger->log("LOG", "img_infograph", $img_infograph);
		$this->LAST_ERROR = "";
		
		$this->logger->log("LOG", "fields", $fields);
		
		if (!self::findArticle($fields['artnumber'])){
			
			$this->logger->log("LOG", "Нет артикула", $fields);
            
			if( count_($images) > 0 && $brand_name ){
				
				$code = $this->GetSymbolCode($brand_name . ' ' . $fields['artnumber']);
				
				if($fields["uniq_code"] == "Y")
					$code .= "_" . rand(0,100);
				
				$this->logger->log("LOG", "code", $code);
				
                $arr = [
                    'NAME' => $brand_name . ' ' . $fields['artnumber'],
                    'CODE' => $code,
										'SORT' => 9999,
                    'IBLOCK_ID' => CProSet::IB_CATALOG,
                    'IBLOCK_SECTION' => $fields['collection'],
                    'ACTIVE' => 'Y',
                    'DETAIL_TEXT_TYPE' => ($model['detail_text_type'] ? $model['detail_text_type'] : "html"),
                    'DETAIL_TEXT' => ($model['detail_text'] ? $model['detail_text'] : null),
                    'DETAIL_PICTURE' => $images[0],
                    'PROPERTY_VALUES' => $this->GetProps( $fields, $props, $images, $img_infograph )
                ];
				
				$this->logger->log("LOG", "arr", $arr);


                $el = new CIBlockElement;
                if ( $btr_id = $el->Add($arr) )
                {
					$this->logger->log("LOG", "Добавили", $btr_id);
                    $vat = Array(
                        'ID' => $btr_id,
                        "VAT_INCLUDED" => 'Y',
                        'VAT_ID' => 1,
						'QUANTITY' => 0
                    );
                    CCatalogProduct::Add( $vat );
					$status = "P";
					$product_id = $btr_id;
					//добавляем товар в индекс
					$this->logger->log("LOG", "Добавляем индекс", $product_id);
					
					$this->addIndex($btr_id);
					//создаем превью из детальной
					$this->logger->log("LOG", "создаем превью из детальной", $product_id);
					
					$this->genPreviewImage($btr_id);
					
					$this->logger->log("LOG", "конец превью", $product_id);
					
					CCatalogProduct::Update($product_id, Array("WEIGHT" => 100, "LENGTH" => 100, "WIDTH" => 100, "HEIGHT" => 100));
					$this->writeLog( $arr );

                } else {
					$status = "E";
					$this->LAST_ERROR = $el->LAST_ERROR;
					$this->writeLog( ['model' => $fields['artnumber'], 'status' => 'E', 'error_text' => $this->LAST_ERROR] );
                }


				$arLog = array(
					"date" => date("Y-m-d H:i:s"),
					"data" => $arr,
					"status" => $status,
					"error" => $this->LAST_ERROR,
				);
				file_put_contents("/var/www/bitrix_logs/admin/add_product/add_product_" . date("Y_m_d") . ".txt", serialize($arLog) . "\r\n", FILE_APPEND);

            } else {
				$status = "E";
				$this->LAST_ERROR = "Бренд не найден или картинки не загружены";
				$this->writeLog( ['model' => $fields['artnumber'], 'status' => 'E', 'error_text' => $this->LAST_ERROR] );
            }
        } else {
			$this->logger->log("LOG", "Есть артикул");
			$prid = self::findArticle( $fields['artnumber'] );
			global $DB;
			$strSql = "SELECT 1 FROM ci_task WHERE model ='{$fields['artnumber']}' AND status = 2";
			$res = $DB->Query( $strSql );
			if ( $res->SelectedRowsCount() > 0 ){
				$el = new CIBlockElement;
				$arr = [
						// 'NAME' => $brand_name . ' ' . $fields['artnumber'],
						// 'CODE' => $code,
						// 'SORT' => 505,
						// 'IBLOCK_ID' => CProSet::IB_CATALOG,
						'IBLOCK_SECTION' => $fields['collection'],
						'ACTIVE' => 'Y',
						'DETAIL_TEXT_TYPE' => ($model['detail_text_type'] ? $model['detail_text_type'] : "html"),
						'DETAIL_TEXT' => ($model['detail_text'] ? $model['detail_text'] : null),
						'DETAIL_PICTURE' => $images[0],
						'PROPERTY_VALUES' => $this->GetProps( $fields, $props, $images, $img_infograph, false )
				];
				$this->logger->log("LOG", "Обновляем товар", $arr);
				$result = $el->Update( $prid, $arr );
				if ( !$result ){
					$status = "E";
					$this->LAST_ERROR = $el->LAST_ERROR;
					$this->writeLog( ['model' => $fields['artnumber'], 'status' => 'E', 'error_text' => $this->LAST_ERROR] );
				}
				$product_id = $prid;
			}else{
				$status = "E";
				$this->LAST_ERROR = "Такой артикул уже есть в системе";
				$this->writeLog( ['model' => $fields['artnumber'], 'status' => 'E', 'error_text' => $this->LAST_ERROR] );
			}
        }

		$params = array(
			"status" => "'".$status."'",
			"error" => "'".$this->LAST_ERROR."'",
		);

		if($product_id !== false)
			$params["product_id"] = $product_id;
		
		$this->logger->log("LOG", "Конец", $params);
		
		$this->setProductParams($id, $params);
		
		$this->logger->log("LOG", "Конец2", $status); 
		
		if($status == "E") return false;

		return $product_id;
	}
	function genPreviewImage($ID){
		$ID = intval($ID);
		if($ID <= 0) return;
		$arFilter = Array(
			"IBLOCK_ID" => 16,
			"ID" => $ID,
//			"PREVIEW_PICTURE" => false,
			"!DETAIL_PICTURE" => false,
		);
		$res = CIBlockElement::GetList(Array(), $arFilter, false, false, array("ID", "NAME", "IBLOCK_ID", "DETAIL_PICTURE"));
		if($arFields = $res->GetNext()){
			$el = new CIBlockElement;
			$img_resize_path = CFile::ResizeImageGet(
				$arFields["DETAIL_PICTURE"],
				array('width'=>'400', 'height'=>'400'),
				BX_RESIZE_IMAGE_PROPORTIONAL
			);
			if(filesize($_SERVER["DOCUMENT_ROOT"] . $img_resize_path["src"]) > 0){
				$data = array (
					'PREVIEW_PICTURE' => CFile::MakeFileArray($img_resize_path["src"]),
				);
				$res2 = $el->Update($arFields["ID"], $data);
			}
		}
	}
	public static function findArticle( $artnumber ){
		if(!$artnumber) return;
		CModule::IncludeModule("iblock");
        $artnumber = trim($artnumber);
        $objRes = CIBlockElement::GetList(array(), array('IBLOCK_ID' => CProSet::IB_CATALOG, '=PROPERTY_CML2_ARTICLE' => $artnumber), false, false, array('ID'));
        if ($res = $objRes->GetNext())
            return $res['ID'];
        else
            return false;
	}
	function findByID( $ID ){
		if(!$ID) return;
		CModule::IncludeModule("iblock");
        $ID = intval($ID);
        $objRes = CIBlockElement::GetList(array(), array('IBLOCK_ID' => CProSet::IB_CATALOG, 'ID' => $ID), false, false, array('PROPERTY_CML2_ARTICLE'));
        if ($res = $objRes->GetNext())
            return $res['PROPERTY_CML2_ARTICLE_VALUE'];
        else
            return false;
	}
	private function addIndex($id){
		CModule::IncludeModule("iblock");
		CModule::IncludeModule("catalog");
		CModule::IncludeModule("search");
		$res = CIBlockElement::GetByID($id);
		if($ar_res = $res->GetNext()){
			$url = "=ID={$ar_res["ID"]}&EXTERNAL_ID={$ar_res["EXTERNAL_ID"]}&IBLOCK_SECTION_ID={$ar_res["IBLOCK_SECTION_ID"]}&IBLOCK_TYPE_ID={$ar_res["IBLOCK_TYPE_ID"]}&IBLOCK_ID={$ar_res["IBLOCK_ID"]}&IBLOCK_CODE={$ar_res["IBLOCK_CODE"]}&IBLOCK_EXTERNAL_ID={$ar_res["IBLOCK_EXTERNAL_ID"]}&CODE={$ar_res["CODE"]}";
			$params = array(
				"DATE_CHANGE"=>$ar_res["DATE_CREATE"],
				"TITLE"=>$ar_res["NAME"],
				"SITE_ID"=>array("s1","s2"),
				"PARAM1"=>$ar_res["IBLOCK_TYPE_ID"],
				"PARAM2"=>CProSet::IB_CATALOG,
				"PERMISSIONS"=>array(1,2,3,4,5,6),
				"URL"=>$url,
				"BODY"=>$ar_res["NAME"],
				"TAGS"=>$ar_res["TAGS"]
			);
			CSearch::Index(
				"iblock",
				$ar_res["ID"],
				$params,
				true
			);
		}
	}
	private function getProduct($id){
		if(!$id) return;
		global $DB;
        $id = trim($id);
//		$strSql = "SELECT * FROM ci_application WHERE id = '".$id."' AND status = 'W' LIMIT 1";
		$strSql = "SELECT * FROM ci_application WHERE id = '".$id."' LIMIT 1";

		$results = $DB->Query($strSql, false, $err_mess.__LINE__);
		if ($row = $results->Fetch()){
			return $row;
		}else
			return false;
	}
	private function setProductParams($id, $params){
		//if(!$id || !in_array_($status, array("W", "E", "P"))) return;
		if(!$id || count_($params) <= 0) return;

		global $DB;
		$DB->Update("ci_application", $params, "WHERE id='".$id."'", $err_mess.__LINE__);
	}
    function GetSymbolCode($name){
        $params = Array(
            "max_len"               => "100", // обрезает символьный код до 100 символов
            "change_case"           => "L", // буквы преобразуются к нижнему регистру
            "replace_space"         => "_", // меняем пробелы на нижнее подчеркивание
            "replace_other"         => "_", // меняем левые символы на нижнее подчеркивание
            "delete_repeat_replace" => "true", // удаляем повторяющиеся нижние подчеркивания
            "use_google"            => "false", // отключаем использование google
        );
        return CUtil::translit($name, 'en', $params);
    }
    function ProcessImages($links){
        $images = array();

        foreach($links as $url) {
			$link = $url;
            if ( filter_var($link, FILTER_VALIDATE_URL) ){

				$img_info = $this->getImgInfo($link);

                if( $img_info ){

					if($img_info["link"]) $link = $img_info["link"];

                    $types = array("", "gif", "jpeg", "png");
                    $ext = $types[$img_info[2]];

					if($img_info[2] == 18) $ext = "webp";

                    /*$func = 'imagecreatefrom'.$ext;

                    $img = $this->CutImage($func($link), $img_info[0], $img_info[1]);
                    $path = $_SERVER["DOCUMENT_ROOT"].'/upload/newci/'.md5(rand()).'.'.$ext;
                    imagejpeg( $img, $path );*/

					$func = 'imagecreatefrom'.$ext;
					$img = $this->CutImage( $func($link), $img_info[0], $img_info[1] ); 
					$path = $_SERVER["DOCUMENT_ROOT"].'/upload/newci/'.md5(rand()).'.'.$ext;

					if($ext == 'png') {
						imagesavealpha($img, true);
						imagepng($img, $path, 0);
					} elseif($ext == 'gif') {
						imagegif($img, $path);
					} elseif($ext == 'webp') {
						imagewebp($img, $path, 100);
					} else {
						imagejpeg($img, $path);
					}

                    if( $btrx_img = CFile::MakeFileArray($path) ){
                        $images[] = $btrx_img;
					}else{
						$arLog = [
							"error_download" => "ошибка при загрузке",
							"url" => $link,
							"temp_path" => $path,
						];
						$this->writeLog($arLog);
					}
                }else{
					$arLog = [
						"error_download" => "getImgInfo empty",
						"url" => $link,
					];
					$this->writeLog($arLog);
				}
            }else{
				$arLog = [
					"error_download" => "FILTER_VALIDATE_URL",
					"url" => $link,
				];
				$this->writeLog($arLog);
			}
        }

        return $images;
    }

	function getImgInfo($link){

		if($img_info = getimagesize($link)){

			return $img_info;

		}else{

			$ext = end(explode(".", $link));

			$file_tmp = $_SERVER["DOCUMENT_ROOT"] . "/upload/temp.{$ext}";
			unlink($file_tmp);

			$agent= 'Mozilla/4.0 (compatible; MSIE 6.0; Windows NT 5.1; SV1; .NET CLR 1.0.3705; .NET CLR 1.1.4322)';

			if(strripos($link, "certina.com")){
				$agent= 'curl/7.29.0';
			}

			$ch = curl_init();
			curl_setopt($ch, CURLOPT_URL, $link);
			curl_setopt($ch, CURLOPT_HEADER, false);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($ch, CURLOPT_CONNECTTIMEOUT ,0);
			curl_setopt($ch, CURLOPT_TIMEOUT, 400);

			curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
			curl_setopt($ch, CURLOPT_USERAGENT, $agent);
			//curl_setopt($ch, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1);

			$response = curl_exec($ch);
			$info = curl_getinfo($ch);
			$error = curl_error($ch);
			curl_close($ch);

			//prent($response);prent($info);prent($error);prent($link);
			file_put_contents($file_tmp, $response);

			if($img_info = getimagesize($file_tmp)){

				$img_info["link"] = $file_tmp;

				return $img_info;

			}
		}
		return false;

	}

    function CutImage_old($img, $xb, $yb){

		$borders = array('upper' => $yb, 'right' => 0, 'lower' => 0, 'left' => $xb);

		for($y = 0; $y < $yb-1; $y++){

			for($x = 0; $x < $xb-1; $x++){

				$index = imagecolorat($img, $x, $y);
				if ( $index != 16777215 && $index != 16711422 && $index != 2130706432 && $index != 2147483647){

					if($y < $borders['upper']) $borders['upper'] = $y;
					if($y > $borders['lower']) $borders['lower'] = $y;
					if($x > $borders['right']) $borders['right'] = $x;
					if($x < $borders['left']) $borders['left']= $x;
				}

			}

		}

		$hight = $yb - $borders['upper'] - ($yb - $borders['lower']);
		$width = $xb - $borders['left'] - ($xb - $borders['right']);
		$new_image = imagecreatetruecolor($width, $hight);
		$whiteBackground = imagecolorallocate($new_image, 255, 255, 255);
		imagefill($new_image,0,0,$whiteBackground);
		imagecopy($new_image, $img, 0,0, $borders['left'], $borders['upper'], $width, $hight);
		return $new_image;

    }
	
	function CutImage($img, $xb, $yb){
		$left = $xb;
		$right = 0;
		$top = $yb;
		$bottom = 0;
		
		$found = false;
		$step = 5;
		
		$hasAlpha = false;
		if(imageistruecolor($img)) {
			for($y = 0; $y < min(10, $yb); $y++) {
				for($x = 0; $x < min(10, $xb); $x++) {
					$color = imagecolorat($img, $x, $y);
					$alpha = ($color >> 24) & 0x7F;
					if($alpha > 0) {
						$hasAlpha = true;
						break 2;
					}
				}
			}
		}
		
		for($y = 0; $y < $yb; $y += $step){
			for($x = 0; $x < $xb; $x += $step){
				$color = imagecolorat($img, $x, $y);
				$r = ($color >> 16) & 0xFF;
				$g = ($color >> 8) & 0xFF;
				$b = $color & 0xFF;
				
				if($r < 240 || $g < 240 || $b < 240){
					$found = true;
					
					for($cx = max(0, $x - $step); $cx <= $x; $cx++){
						$cColor = imagecolorat($img, $cx, $y);
						$cR = ($cColor >> 16) & 0xFF;
						$cG = ($cColor >> 8) & 0xFF;
						$cB = $cColor & 0xFF;
						
						if($cR < 240 || $cG < 240 || $cB < 240){
							if($cx < $left) $left = $cx;
							break;
						}
					}
					
					for($cx = min($xb - 1, $x + $step); $cx >= $x; $cx--){
						$cColor = imagecolorat($img, $cx, $y);
						$cR = ($cColor >> 16) & 0xFF;
						$cG = ($cColor >> 8) & 0xFF;
						$cB = $cColor & 0xFF;
						
						if($cR < 240 || $cG < 240 || $cB < 240){
							if($cx > $right) $right = $cx;
							break;
						}
					}
					
					if($y < $top){
						for($cy = max(0, $y - $step); $cy <= $y; $cy++){
							$cColor = imagecolorat($img, $x, $cy);
							$cR = ($cColor >> 16) & 0xFF;
							$cG = ($cColor >> 8) & 0xFF;
							$cB = $cColor & 0xFF;
							
							if($cR < 240 || $cG < 240 || $cB < 240){
								if($cy < $top) $top = $cy;
								break;
							}
						}
					}
					
					if($y > $bottom){
						for($cy = min($yb - 1, $y + $step); $cy >= $y; $cy--){
							$cColor = imagecolorat($img, $x, $cy);
							$cR = ($cColor >> 16) & 0xFF;
							$cG = ($cColor >> 8) & 0xFF;
							$cB = $cColor & 0xFF;
							
							if($cR < 240 || $cG < 240 || $cB < 240){
								if($cy > $bottom) $bottom = $cy;
								break;
							}
						}
					}
				}
			}
		}
		
		if(!$found){
			$step = 1;
			for($y = 0; $y < $yb; $y += $step){
				for($x = 0; $x < $xb; $x += $step){
					$color = imagecolorat($img, $x, $y);
					$r = ($color >> 16) & 0xFF;
					$g = ($color >> 8) & 0xFF;
					$b = $color & 0xFF;
					
					if($r < 240 || $g < 240 || $b < 240){
						$found = true;
						if($x < $left) $left = $x;
						if($x > $right) $right = $x;
						if($y < $top) $top = $y;
						if($y > $bottom) $bottom = $y;
					}
				}
			}
		}
		
		if(!$found){
			$new_image = imagecreatetruecolor(1, 1);
			imagefill($new_image, 0, 0, imagecolorallocate($new_image, 255, 255, 255));
			return $new_image;
		}

		$margin = 3;
		$left = max(0, $left - $margin);
		$right = min($xb - 1, $right + $margin);
		$top = max(0, $top - $margin);
		$bottom = min($yb - 1, $bottom + $margin);
		
		$width = $right - $left + 1;
		$height = $bottom - $top + 1;
		
		$new_image = imagecreatetruecolor($width, $height);
		
		if($hasAlpha) {
			imagesavealpha($new_image, true);
			$transparent = imagecolorallocatealpha($new_image, 0, 0, 0, 127);
			imagefill($new_image, 0, 0, $transparent);
			
			imagecopy($new_image, $img, 0, 0, $left, $top, $width, $height);
			
			$new_left = $width;
			$new_right = 0;
			$new_top = $height;
			$new_bottom = 0;
			
			for($y = 0; $y < $height; $y++){
				for($x = 0; $x < $width; $x++){
					$color = imagecolorat($new_image, $x, $y);
					$alpha = ($color >> 24) & 0x7F;
					
					if($alpha < 120){
						if($x < $new_left) $new_left = $x;
						if($x > $new_right) $new_right = $x;
						if($y < $new_top) $new_top = $y;
						if($y > $new_bottom) $new_bottom = $y;
					}
				}
			}
			
			if($new_left < $new_right && $new_top < $new_bottom && 
			   ($new_left > 0 || $new_right < $width-1 || $new_top > 0 || $new_bottom < $height-1)){
				
				$new_width = $new_right - $new_left + 1;
				$new_height = $new_bottom - $new_top + 1;
				
				$final_image = imagecreatetruecolor($new_width, $new_height);
				imagesavealpha($final_image, true);
				$transparent = imagecolorallocatealpha($final_image, 0, 0, 0, 127);
				imagefill($final_image, 0, 0, $transparent);
				
				imagecopy($final_image, $new_image, 0, 0, $new_left, $new_top, $new_width, $new_height);
				
				imagedestroy($new_image);
				return $final_image;
			}
		} else {
			imagecopy($new_image, $img, 0, 0, $left, $top, $width, $height);
		}
		
		return $new_image;
	}

	function CutImageAlpha($img, $xb, $yb) {
		imagesavealpha($img, true);

		$borders = [
			'upper' => $yb,
			'right' => 0,
			'lower' => 0,
			'left' => $xb
		];

		for($y = 0; $y < $yb; $y++) {
			for($x = 0; $x < $xb; $x++) {
				$color = imagecolorat($img, $x, $y);
				$rgba = imagecolorsforindex($img, $color);

				// Пиксель считается фоном, если он полностью прозрачный
				$isTransparent = ($rgba['alpha'] == 127);

				if (!$isTransparent) {
					if($y < $borders['upper']) $borders['upper'] = $y;
					if($y > $borders['lower']) $borders['lower'] = $y;
					if($x > $borders['right']) $borders['right'] = $x;
					if($x < $borders['left']) $borders['left'] = $x;
				}
			}
		}

		$width = max(1, $borders['right'] - $borders['left'] + 1);
		$height = max(1, $borders['lower'] - $borders['upper'] + 1);

		// Создаем новое изображение с прозрачным фоном
		$newImage = imagecreatetruecolor($width, $height);
		imagesavealpha($newImage, true);

		// прозрачный цвет
		$transparent = imagecolorallocatealpha($newImage, 0, 0, 0, 127);
		imagefill($newImage, 0, 0, $transparent);

		imagecopy(
			$newImage,
			$img,
			0, 0,
			$borders['left'], $borders['upper'],
			$width, $height
		);

		return $newImage;
	}

    function GetProps($fields, $props, $images, $img_infograph, $flag = true){
		
		$this->logger->log("LOG", "GetProps", $fields);
		
        foreach( $props as $code => $value ) $res[$code] = $value;
        if ( $flag ) $res['MORE_PHOTO'] = $images;
        $res['CML2_ARTICLE']  = $fields['artnumber'];
        //$res['DIAMETER']   = $fields['DIAMETER'];
        //$res['THICKNESS']  = $fields['THICKNESS'];
        if( filter_var($fields['manual'], FILTER_VALIDATE_URL) )
            $res['FILE2'] = CFile::MakeFileArray( $fields['manual'] );
		
		$this->logger->log("LOG", "GetProps1", $res);
		
		$video = stripslashes($fields['video']);
		$res['VIDEO_YOUTUBE'] = parse_url($video)['path'];
		//$video = stripcslashes($fields['video']);
        //parse_str(parse_url($video)['query'], $vid);prent($vid);
        //$res['VIDEO_YOUTUBE'] = $vid['v'];
		$res['SITE_ID']  = array("s1","s2");

		if ( in_array( $props['BRAND'], [88200, 43508] ) ){
			$res['2743'] = str_replace('.', '', $fields['artnumber']);
		}

		$res['AVAILABILITY_BY']  = 494;
		$res['AVAILABILITY_RU']  = 514;
		$res['AVAILABILITY_PL']  = 549;
		
		$this->logger->log("LOG", "GetProps2", $res);
		
		//берем штрихкод из файла
		$res['AEN']  = getNextBarcode("");
		
		$this->logger->log("LOG", "GetProps AEN", $res['AEN']);
		
		$res['AEN2']  = getNextBarcode("2");
		
		$this->logger->log("LOG", "GetProps AEN2", $res['AEN2']);
		
		if(is_array($img_infograph) && count($img_infograph) > 0){
			$res['INFOGRAPH_BASE'] = $img_infograph[0];
		}

		//AVAILABILITY_BY 494 Нет в наличии
		//AVAILABILITY_RU 514	Нет в наличии
		//AVAILABILITY_PL 549	Brak w magazynie
		
		$this->logger->log("LOG", "GetProps end", $res);
		
        return $res;
    }

	function getNOS(){
		global $DB;
		$objBrand = new CPanelBrand;
		$arPrice = array();


		/* получаем нужных поставщиков */
		$strSql = "SELECT id FROM ci_suppliers WHERE opt_supplier = 'N'";
		$results = $DB->Query($strSql, false, $err_mess.__LINE__);
		while ($row = $results->Fetch()){
			$arSup[] = $row["id"];
		}
		//prent($arSup);

		/* получаем все артикулы из прайсов */
		$strSql = "SELECT * FROM ci_price WHERE supplier_id IN ('".implode("','", $arSup)."')";
		$results = $DB->Query($strSql, false, $err_mess.__LINE__);
		while ($row = $results->Fetch()){
			$active = false;
			foreach($row as $k => $v){
				if (str_starts_with($k, 'active_') && $v == "Y") {
					$active = true;
					break;
				}
			}
			if($active === true)
				$arPrice[] = $row;
		}


		/* выбираем артикулы из каталога сайта */
        $res = CIBlockElement::GetList(array(), array('IBLOCK_ID' => CProSet::IB_CATALOG, "!PROPERTY_CML2_ARTICLE" => false), false, false, array('PROPERTY_CML2_ARTICLE'));
        while ($arRes = $res->GetNext()){
			$models_catalog[] = $arRes["PROPERTY_CML2_ARTICLE_VALUE"];
		}

		$res = $objBrand->getList();
		foreach($res as $key => $ar){
			$brands[$ar["id"]] = $ar["name"];
		}
		/* сравниваем */
		$temp = $arr = array();
		foreach( $arPrice as $model ){
			if( !in_array_($model['model'], $models_catalog) && !in_array_($model['model'], $temp)  ){
				$temp[] = $model['model'];
				$arr[] = ['an' => $model['model'], 'brand' => $brands[$model['brand_id']]];
			}
		}
		return $arr;
	}


	/* методы для sku и обычных */

	/* аналог findArticle
	* находим ID товаров по артикулу
	*/
	public static function findArticleAll( $artnumber ){
		$artnumber = trim($artnumber);
		if(!$artnumber) return;
		global $DB;
		$strSql = "SELECT IBLOCK_ELEMENT_ID, IBLOCK_PROPERTY_ID FROM b_iblock_element_property WHERE VALUE = '" . addslashes($artnumber) . "'";

		$results = $DB->Query($strSql, false, $err_mess.__LINE__);
		$ar = false;
		while ($row = $results->Fetch()){
			if($row["IBLOCK_PROPERTY_ID"] == CProSet::PROP_ID_ARTICLE){
				$ar[] = array(
					"IBLOCK_ID" => CProSet::IB_CATALOG,
					"ID" => $row["IBLOCK_ELEMENT_ID"]
				);
			}elseif($row["IBLOCK_PROPERTY_ID"] == CProSet::PROP_ID_ARTICLE_SKU){
				$ar[] = array(
					"IBLOCK_ID" => CProSet::IB_CATALOG_SKU,
					"ID" => $row["IBLOCK_ELEMENT_ID"]
				);
			}
		}
		//
		if($ar === false){
			if($el = self::findArticle($artnumber)){
				$ar[] = array(
					"IBLOCK_ID" => CProSet::IB_CATALOG,
					"ID" => $el
				);
			}
			//prent($asd);
		}

		return $ar;
	}

	private function writeLog( $arr )
	{
		$date = date("Y-m-d");
		$filePath = "/var/www/bitrix/data/www/logs/content_editor/CE_{$date}.txt";
		$data = json_encode( $arr );
		file_put_contents( $filePath, $data . PHP_EOL, FILE_APPEND );
	}

	function findInfographImg($links){
        foreach( $links as $link )
        {
            if ( filter_var($link, FILTER_VALIDATE_URL) ){

				$img_info = $this->getImgInfo($link);

                if( $img_info ){

                    $types = array("", "gif", "jpeg", "png");
                    $ext = $types[$img_info[2]];

					if($ext != 'png') continue;

                    $img = $this->CutImageAlpha(imagecreatefrompng($link), $img_info[0], $img_info[1]);

                    $path = $_SERVER["DOCUMENT_ROOT"].'/upload/newci/'.md5(rand()).'.'.$ext;
                    imagepng($img, $path);

                    if($btrx_img = CFile::MakeFileArray($path) ){
                       return $btrx_img;
					}
                }
            }
        }

        return false;
	}

}
?>
