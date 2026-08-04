<?php
class CPanelPricelist{
	static $badColumn;
	function __construct(){
		
	}
	function getList(){
		global $DB;
		$arr = array();
		$strSql = "SELECT * FROM ci_pricelist ORDER BY timestamp desc";
		$results = $DB->Query($strSql, false, $err_mess.__LINE__);
		while ($row = $results->Fetch()){
			$arr[] = $row;
		}
		return $arr;
	}

	private function add($supplier_id, $arBrand, $data, $optins = array()){
		global $DB;
		$res["true"] = $res["bad"] = 0;
		if($supplier_id < 0 || count($arBrand) <= 0 || count($data) <= 0)
			return $res;
		//удаляем старые связки
		if($optins["price_no_clear"] != "on"){
			foreach($arBrand as $key => $arItem){
				$DB->Query("DELETE FROM ci_price WHERE supplier_id = '".$supplier_id."' AND brand_id = '".$arItem["id"]."'", false, $err_mess.__LINE__);
				$DB->Query("DELETE FROM ci_pricelist WHERE supplier_id = '".$supplier_id."' AND brand_id = '".$arItem["id"]."'", false, $err_mess.__LINE__);
			}
		}
		
		//пишем
		foreach($data as $key => $arItem){
			if($optins["price_r"] == "on")
				$arItem["price"] = 0;
			$in = array(
				"model" => "'".addslashes($arItem["article"])."'",
				"brand_id" => "'".addslashes($arItem["brand_id"])."'",
				"supplier_id" => "'".addslashes($arItem["supplier_id"])."'",
				"store_id" => 3,
				"price" => "'".addslashes($arItem["price"])."'",
				"count" => "'".addslashes($arItem["count"])."'"
			);
			$ID = $DB->Insert("ci_price", $in, $err_mess.__LINE__);
			if($ID > 0){
				$res["true"]++;
			}else{
				$res["bad"]++;
			}
		}
		unset($arItem);
		if($optins["price_no_clear"] != "on"){
			foreach($arBrand as $key => $arItem){
				$in = array(
					"brand_id" 		=> $arItem["id"],
					"supplier_id" 	=> $supplier_id,
				);
				$DB->Insert("ci_pricelist", $in, $err_mess.__LINE__);
			}
		}
		return $res;
	}

	function isPricelist( $id ){
		global $DB;
		$id = (int)$id;
		if($id <= 0) return;
		$strSql = "SELECT id FROM ci_pricelist WHERE id = '{$id}' LIMIT 1";
		$results = $DB->Query($strSql, false, $err_mess.__LINE__);
		if ($row = $results->Fetch()){
			return true;
		}
		return false;
	}
	function getPricelistDetail( $id ){
		global $DB;
		$id = (int)$id;
		if($id <= 0) return;
		$strSql = "SELECT * FROM ci_pricelist WHERE id = '{$id}'";
		$results = $DB->Query($strSql, false, $err_mess.__LINE__);
		if ($row = $results->Fetch()){
			return $row;
		}
		return false;
	}
	function getCntProducts($brand_id, $supplier_id){
		global $DB;
		$strSql = "SELECT COUNT(id) as count FROM ci_price WHERE brand_id = {$brand_id} AND supplier_id = {$supplier_id}";
		$results = $DB->Query($strSql, false, $err_mess.__LINE__);
		if ($row = $results->Fetch())
			return $row["count"];
		return false;

	}
	//товары для связки поставщик-бренд
	function getProductsRoll( $supplier_id, $brand_id ){
		global $DB;
		if( $supplier_id <= 0 || $brand_id <= 0 ) return false;
		$arr = array();
		$strSql = "SELECT * FROM ci_price WHERE supplier_id = {$supplier_id} AND brand_id = {$brand_id} ORDER BY model asc";
		$results = $DB->Query($strSql, false, $err_mess.__LINE__);
		while ($row = $results->Fetch()){
			$arr[] = $row;
		}
		return $arr;
	}
	
	//function getAllPrice(){
	function getAllPrice(){
		global $DB;
		$arr = array();
		$strSql = "SELECT * FROM ci_price ORDER BY model asc";
		$results = $DB->Query($strSql, false, $err_mess.__LINE__);
		while ($row = $results->Fetch()){
			$arr[] = $row;
		}
		return $arr;
	}
	function getPriceByFilter($arFilter = array(), $group = false, $arSelect = false){
		global $DB;
		$arr = array();
		if($arSelect && is_array($arSelect)) $select = implode(",", $arSelect); else $select = "*";
		$strSql = "SELECT {$select} FROM ci_price";
		$filter = array();
		/************** по сайту ****************/
		if(isset($arFilter["website"])){
			switch($arFilter["website"]){
				case "s1":
					$filter[] = "active = 'Y'";
					break;
				case "s2":
					$filter[] = "active_by = 'Y'";
					break;
				case "s3":
					$filter[] = "active_pl = 'Y'";
					break;
				default:
					break;
			}
		}
		/************** по поставщику ****************/
		if(isset($arFilter["supplier_id"])){
			if(is_array($arFilter["supplier_id"])){
				$ar = array();
				foreach($arFilter["supplier_id"] as &$item){
					$item = intval($item);
					if($item > 0)
						$ar[$item] = $item;
				}
				if(count($ar) > 0){
					$filter[] = "supplier_id IN ('" . implode("','", $ar)."')";
				}
			}else{
				$item = intval($arFilter["supplier_id"]);
				if($item > 0)
					$filter[] = "supplier_id = '" . $item . "'";
			}
		}
		/************** по бренду ****************/
		if(isset($arFilter["brand_id"])){
			if(is_array($arFilter["brand_id"])){
				$ar = array();
				foreach($arFilter["brand_id"] as &$item){
					$item = intval($item);
					if($item > 0)
						$ar[$item] = $item;
				}
				if(count($ar) > 0){
					$filter[] = "brand_id IN ('" . implode("','", $ar)."')";
				}
			}else{
				$item = intval($arFilter["brand_id"]);
				if($item > 0)
					$filter[] = "brand_id = '" . $item . "'";
			}
		}
		if(isset($arFilter["id"])){
			if(is_array($arFilter["id"])){
				$ar = array();
				foreach($arFilter["id"] as &$item){
					$item = intval($item);
					if($item > 0)
						$ar[$item] = $item;
				}
				if(count($ar) > 0){
					$filter[] = "id IN ('" . implode("','", $ar)."')";
				}
			}else{
				$item = intval($arFilter["id"]);
				if($item > 0)
					$filter[] = "id = '" . $item . "'";
			}
		}
		/************** по артикулу ****************/
		if(isset($arFilter["article"])){
			if(is_array($arFilter["article"])){
				$ar = array();
				foreach($arFilter["article"] as &$item){
					$ar[$item] = $item;
				}
				unset($item);
				if(count($ar) > 0){
					$filter[] = "model IN ('" . implode("','", $ar)."')";
				}
			}elseif(strlen($arFilter["article"]) > 3){
				$filter[] = "model = '" . $arFilter["article"] . "'";
			}
		}
		if(isset($arFilter["search_text"]) && strlen($arFilter["search_text"]) > 3){
			$filter[] = "model LIKE '%" . addslashes($arFilter["search_text"]) . "%'";
		}
		if(isset($arFilter["price_from"])){
			$filter[] = "price >= '" . addslashes($arFilter["price_from"]) . "'";
		}
		if(isset($arFilter["price_to"])){
			$filter[] = "price <= '" . addslashes($arFilter["price_to"]) . "'";
		}
		//prent($filter);
		if(count($filter) > 0){
			foreach($filter as $key => $f){
				if($key == 0)
					$strSql .= " WHERE " . $f;
				else
					$strSql .= " AND " . $f;
			}
		}
		if($group === true){
			$strSql .= " GROUP BY model";
		}
		$strSql .= " ORDER BY model asc";

		$results = $DB->Query($strSql, false, $err_mess.__LINE__);
		while ($row = $results->Fetch()){
			$arr[] = $row;
		}
		return $arr;
	}
	function delete( $id ){
		global $DB;
		$id = intval( $id );
		$res = $this->getPricelistDetail( $id );
		if( $res ){
			$brand_id = $res["brand_id"];
			$supplier_id = $res["supplier_id"];
			$DB->Query("DELETE FROM ci_price WHERE supplier_id = '".$supplier_id."' AND brand_id = '".$brand_id."'", false, $err_mess.__LINE__);
			$DB->Query("DELETE FROM ci_pricelist WHERE id = '".$id."'", false, $err_mess.__LINE__);
			return true;
		} else return false;
	}
	function changeActivity( $id, $status ){
		global $DB;
		if(!in_array($status, array("N","Y"))) return;
		$id = intval( $id );
		$res = $this->getPricelistDetail( $id );
		if( $res ){
			$brand_id = $res["brand_id"];
			$supplier_id = $res["supplier_id"];
			$DB->Update("ci_price", array("active" => "'".$status."'"), "WHERE supplier_id='".$supplier_id."' AND brand_id='".$brand_id."'", $err_mess.__LINE__);
			$DB->Update("ci_pricelist", array("active" => "'".$status."'"), "WHERE id='".$id."'", $err_mess.__LINE__);
//			CProSet::setOption("UPDATE_CATALOG", "Y");
			return true;
		} else return false;
	}
	//меняем статус активности у всего поставщика
	function changeActivityAll( $supp_id, $arStatus, $arBrand){
		global $DB;
		if(in_array($arStatus["active"], array("N","Y"))) $status = $arStatus["active"]; else $status = "N";
		if(in_array($arStatus["active_by"], array("N","Y"))) $status_by = $arStatus["active_by"]; else $status_by = "N";
		if(in_array($arStatus["active_pl"], array("N","Y"))) $status_pl = $arStatus["active_pl"]; else $status_pl = "N";
		$supp_id = intval( $supp_id );
		//prent($arBrand);
		
		$DB->Update("ci_price", array("active" => "'".$status."'", "active_by" => "'".$status_by."'", "active_pl" => "'".$status_pl."'"), "WHERE supplier_id='".$supp_id."'", $err_mess.__LINE__);
		$DB->Update("ci_pricelist", array("active" => "'".$status."'", "active_by" => "'".$status_by."'", "active_pl" => "'".$status_pl."'"), "WHERE supplier_id='".$supp_id."'", $err_mess.__LINE__);
		
		foreach($arBrand as $brand_id => $ar){
			if(in_array($ar["active"], array("N","Y"))) $status = $ar["active"]; else $status = "N";
			if(in_array($ar["active_by"], array("N","Y"))) $status_by = $ar["active_by"]; else $status_by = "N";
			if(in_array($ar["active_pl"], array("N","Y"))) $status_pl = $ar["active_pl"]; else $status_pl = "N";
			$DB->Update("ci_price", array("active" => "'".$status."'", "active_by" => "'".$status_by."'", "active_pl" => "'".$status_pl."'"), "WHERE supplier_id='".$supp_id."' AND brand_id='".$ar["id"]."'", $err_mess.__LINE__);
			$DB->Update("ci_pricelist", array("active" => "'".$status."'", "active_by" => "'".$status_by."'", "active_pl" => "'".$status_pl."'"), "WHERE supplier_id='".$supp_id."' AND brand_id='".$ar["id"]."'", $err_mess.__LINE__);
		}
//		CProSet::setOption("UPDATE_CATALOG", "Y");
	}
	function isProduct( $id ){
		global $DB;
		$id = (int)$id;
		if($id <= 0) return;
		$strSql = "SELECT id FROM ci_price WHERE id = '{$id}' LIMIT 1";
		$results = $DB->Query($strSql, false, $err_mess.__LINE__);
		if ($row = $results->Fetch()){
			return true;
		}
		return false;
	}
	function deleteProduct( $id ){
		global $DB;
		$id = intval( $id );
		if($this->isProduct( $id )){
			$DB->Query("DELETE FROM ci_price WHERE id = '".$id."'", false, $err_mess.__LINE__);
			return true;
		} else return false;
	}
	function getYandexPriceByFilter($arFilter = array()){
		global $DB;
		$arr = array();
		$strSql = "SELECT * FROM ci_yandex_price";
		$filter = array();
		if(isset($arFilter["id"])){
			if(is_array($arFilter["id"])){
				$ar = array();
				foreach($arFilter["id"] as &$item){
					$item = intval($item);
					if($item > 0)
						$ar[$item] = $item;
				}
				if(count($ar) > 0){
					$filter[] = "id IN ('" . implode("','", $ar)."')";
				}
			}else{
				$item = intval($arFilter["id"]);
				if($item > 0)
					$filter[] = "id = '" . $item . "'";
			}
		}
		/************** по артикулу ****************/
		if(isset($arFilter["name"])){
			if(is_array($arFilter["name"])){
				$ar = array();
				foreach($arFilter["name"] as $item){
					if(strlen($item) > 0)
						$ar[$item] = $item;
				}
				if(count($ar) > 0){
					$filter[] = "name IN ('" . implode("','", $ar)."')";
				}
			}else{
				if(strlen($arFilter["name"]) > 0)
					$filter[] = "name = '" . $arFilter["name"] . "'";
			}
			//$filter[] = "model LIKE '%" . addslashes($arFilter["name"]) . "%'";
			//$filter[] = "model = '" . addslashes($arFilter["name"]) . "'";
		}
		//prent($filter);
		if(count($filter) > 0){
			foreach($filter as $key => $f){
				if($key == 0)
					$strSql .= " WHERE " . $f;
				else
					$strSql .= " AND " . $f;
			}
		}
		$strSql .= " ORDER BY name asc";

		$results = $DB->Query($strSql, false, $err_mess.__LINE__);
		while ($row = $results->Fetch()){
			$arr[] = $row;
		}
		return $arr;
	}
	
	function getOnlinerPriceByFilter($arFilter = array()){
		global $DB;
		$arr = array();
		$strSql = "SELECT * FROM ci_catalog_onliner";
		/************** по артикулу ****************/
		if(isset($arFilter["model"])){
			if(is_array($arFilter["model"])){
				$ar = array();
				foreach($arFilter["model"] as $item){
					if(strlen($item) > 0)
						$ar[$item] = $item;
				}
				if(count($ar) > 0){
					$filter[] = "model IN ('" . implode("','", $ar)."')";
				}
			}else{
				if(strlen($arFilter["model"]) > 0)
					$filter[] = "model = '" . $arFilter["model"] . "'";
			}
		}
		if(count($filter) > 0){
			foreach($filter as $key => $f){
				if($key == 0)
					$strSql .= " WHERE " . $f;
				else
					$strSql .= " AND " . $f;
			}
		}
		
		$results = $DB->Query($strSql, false, $err_mess.__LINE__);
		while ($row = $results->Fetch()){
			$arr[] = array(
				"name" => $row["model"],
				"minPrice" => $row["min_price"],
			);
		}
		return $arr;
	}
	/* цены битрикс (которые занесены в отдельную таблицу)*/
	function getCatalogPriceByFilter($arFilter = array()){
		global $DB;
		$arr = array();
		$strSql = "SELECT * FROM ci_price_catalog";
		$filter = array();
		/************** по артикулу ****************/
		if(isset($arFilter["model"])){
			if(is_array($arFilter["model"])){
				$ar = array();
				foreach($arFilter["model"] as $item){
					if(strlen($item) > 0)
						$ar[$item] = addslashes($item);
				}
				if(count($ar) > 0){
					$filter[] = "model IN ('" . implode("','", $ar)."')";
				}
			}else{
				if(strlen($arFilter["model"]) > 0)
					$filter[] = "model = '" . addslashes($arFilter["model"]) . "'";
			}
		}
		//prent($filter);
		if(count($filter) > 0){
			//$strSql22 = implode(" AND ",$filter);
			//prent($strSql22);
			foreach($filter as $key => $f){
				if($key == 0)
					$strSql .= " WHERE " . $f;
				else
					$strSql .= " AND " . $f;
			}
		}
//prent($strSql);
		$results = $DB->Query($strSql, false, $err_mess.__LINE__);
		while ($row = $results->Fetch()){
			$arr[] = $row;
		}
		return $arr;
	}
	function upload($filename, $form, $supplier){
		global $DB;
		error_reporting(0);
		if (!class_exists('SpreadsheetReader')){
			require($_SERVER["DOCUMENT_ROOT"] . '/bitrix/php_interface/include/classes/excel/excel_reader.php');
			require($_SERVER["DOCUMENT_ROOT"] . '/bitrix/php_interface/include/classes/excel/SpreadsheetReader.php');
			require($_SERVER["DOCUMENT_ROOT"] . '/bitrix/php_interface/include/classes/phpexcel/PHPExcel/IOFactory.php');
		}


		$objUtils = new CPanelUtils;
		$objCurrency = new CPanelCurrency;
		$objBrand = new CPanelBrand;

		$settings_pricelist = $supplier["settings_pricelist"];//настройки обработки прайса
		$settings = $supplier["settings"];//настройки поставщика
		
		$arBrand = array();
		$availBrand = $objBrand->getList();
		
		//prent($settings["brand"]);
		//проверяем какие бренды пришли из формы с теми что разрешены в настройках. оставляем только разрешенные
		foreach($form["brand"] as $f_brand){
			foreach($settings["brand"] as $s_brand){
				if($s_brand["id"] == $f_brand){
					if($br = $objBrand->getDetail( $f_brand )){
						
						$br["sale"] = (float) $s_brand["sale"];
						$br["priority"] = (float) $s_brand["priority"];
						
						$br["active"] = $s_brand["active"];
						$br["active_by"] = $s_brand["active_by"];
						$br["active_pl"] = $s_brand["active_pl"];
						
						$arBrand[] = $br;
					}
						
				}
			}
		}
		if(count($arBrand) == 0){
			return "no select brand";
		}
		

		if(isset($settings["currency"]) && $settings["currency"] != "RUB"){
			$currency = $objCurrency->getDetail( $settings["currency"] );//курс валюты
			$amount = $currency["amount"];
			$rate = $currency["rate"];
		}else{
			$amount = $rate = 1;
		}

		foreach($arBrand as $k => $v) $arBrandName[$v["name"]] = $v["name"];
		
		try{
		
			$spreadsheet = new SpreadsheetReader($filename);
			$sheets = $spreadsheet->sheets();
			$ar = array();
			foreach ($sheets as $index => $Name){
				$asd = $spreadsheet->ChangeSheet($index);
				foreach ($spreadsheet as $key => $row){
					$ar[] = $row;
				}
			}

			//
			
			if(count($ar) <= 1){
				
				// Файл xlsx
				$xls = PHPExcel_IOFactory::load($filename);
				// Первый лист
				$xls->setActiveSheetIndex(0);
				$sheet = $xls->getActiveSheet();
				$ar = array();
				foreach ($sheet->toArray() as $row) {
					$ar[] = $row;
				}
			}

			$col_price = intval($settings_pricelist["col_price"]);
			$col_count = intval($settings_pricelist["col_count"]);
			$count_default = intval($settings_pricelist["count_default"]);
			$quntity_flag = $settings_pricelist["quntity_flag"];
			$quntity_value = $settings_pricelist["quntity_value"];
			$col_article = intval($settings_pricelist["col_article"]);
			$col_brand = intval($settings_pricelist["col_brand"]);
			$start_row = intval($settings_pricelist["start_row"]);
			if($settings_pricelist["clear_space"] == "Y")
				$clear_space = true;
	
			$arResult = array();
			unset($spreadsheet);
			//разбираем данные которые прочитали из файла
			if(count($ar) > 0){
				//return array("true" => count($ar), "bad" => 432);
				
				foreach ($ar as $key => $row){
				
					if($key < ($start_row - 1)) continue;
					
					$article = $row[$col_article - 1];
					
					foreach($arBrand as $k => $v){
						$article = str_replace($v["name"], '', $article);
					}
					//if($clear_space === true) $article = str_replace(' ', '', $article);
					if($clear_space === true) {
						$article = str_replace(array("(карманные)", "(с запанками)", "(с ручкой)", "(темно-коричневый ремешок)"), '', $article);

						$article = str_replace_once(' ', '', trim($article)); 
					}
					if($col_brand > 0 && count($arBrand) > 1){
						//пропускаем если нету в массиве с нужными брендами
						$flg = false;
						foreach($arBrand as $k => $v){
							$pos = stripos($row[$col_brand - 1], $v["name"]);
							//$pos = stripos($article, $v["name"]);
							if ($pos !== false) {
								$brand_name = $v["name"];
								$brand_id = $v["id"];
								//костыль
								if ($supplier["id"] == 37 && $brand_id == 1 && (stristr($article, "MTP") || stristr($article, "LTP")))
									$sale = 54;
								else
									$sale = $v["sale"];
								$flg = true;
							}
						}
						if ($flg === false) {
							if($article[1] == "-" || $article[2] == "-" || $article[3] == "-"){
								foreach($arBrand as $k => $v){
									if($v["name"] == "Casio"){
										$brand_name = $v["name"];
										$brand_id = $v["id"];
										//костыль
										if ($supplier["id"] == 37 && $brand_id == 1 && (stristr($article, "MTP") || stristr($article, "LTP")))
											$sale = 54;
										else
											$sale = $v["sale"];
										$flg = true;
									}
								}
							}
						}
						if ($flg === false) {
							//self::$badColumn[] = "Артикул - {$article}. Бренда нет в доступных к загрузке";
							continue;
						}
					}elseif(count($arBrand) == 1){
						$flg = false;
						if(strlen($arBrand[0]["name"]) > 0){
							if($col_brand > 0)
								$flg = stripos($row[$col_brand - 1], $arBrand[0]["name"]);
							else
								$flg = true;
						}else{
							self::$badColumn[] = "Артикул - {$article}. Бренд неопределен";
							continue;
						}
						if ($flg === false) {
							if($arBrand[0]["name"] == "Casio" && ($article[1] == "-" || $article[2] == "-" || $article[3] == "-"))
								$flg = true;
						}
						if ($flg === false) {
							self::$badColumn[] = "Артикул - {$article}. Бренд неопределен2";
							continue;
						}
						$brand_name = $arBrand[0]["name"];
						$brand_id = $arBrand[0]["id"];
						//костыль
						if ($supplier["id"] == 37 && $brand_id == 1 && (stristr($article, "MTP") || stristr($article, "LTP")))
							$sale = 54;
						else
							$sale = $arBrand[0]["sale"];
						
					}else{
						self::$badColumn[] = "Артикул - {$article}. Бренда неопределен3";
						continue;
					}
					
					
					$price = $row[$col_price - 1];
					$price = str_replace(" ", "", $price);
					$t = explode(",", $price);
					if(strlen($t[1]) == 2 && $t[1] == "00")
						$price = $t[0];
					else
						$price = str_replace(",", "", $price);
					//if($article == "CASIO A-158WA-1D")prent($price, 1,1);
					//$price = str_replace(",", ".", $price);
					$price = (float) $price;
					
					if($col_count > 0){
						$count = $row[$col_count - 1];
						if($quntity_flag == "str"){
							if($count == $quntity_value){
								$count = $count_default;
							}else{
								$count = 0;
							}
						}elseif($quntity_flag == "int"){
							$count = str_replace(array(">", "<"), array("", ""), $count);
							if(strripos($count, ",")){
								$count = substr($count, 0, strripos($count, ","));
							}
							$count = intval($count);
						}

						//if($count <= 0)
						//	$count = $count_default;
					}elseif($count_default > 0){
						$count = $count_default;
					}else{
						$count = 0;
					}
					if($count <= 0){
						self::$badColumn[] = "Количество - {$count}. Артикул - {$article}";
						continue;
					}
					if(strlen($article) > 0 && $price > 0){
						$arResult[] = array(
							"article" 		=> $article,
							"brand_id" 		=> $brand_id,
							"brand" 		=> $brand_name,
							"supplier_id" 	=> $supplier["id"],
							"price" 		=> $price,
							"count" 		=> $count,
							"sale" 			=> $sale,
						);
					}

				}
				//prent($arResult,0,1);return;
				unset($row);
				foreach($arResult as $key => &$arItem){
					$arItem["price"] = $arItem["price"] * $rate;
					if($arItem["sale"] > 0 && $arItem["sale"] < 100){
						$arItem["price"] = $arItem["price"] * ( 100 - $arItem["sale"] ) / 100;
					}
					
					$arItem["article"] = str_ireplace($arItem["brand"], "", $arItem["article"]);
					$arItem["article"] = str_replace(array("  "), array(" "), $arItem["article"]);
					$arItem["article"] = trim($arItem["article"]);
					
					$pos = strpos($arItem["article"], " ");
					
					//если пятый символ -, то менять на J. если девятого символа нет - добавлять Y
					if($arItem["brand"] == "Q&Q"){
						if($arItem["article"][4] == "-") $arItem["article"][4] = "J";
						if(strlen($arItem["article"]) == 8) $arItem["article"][8] = "Y";
						//prent($arItem,0,1);die;
					}
					//RA-KV0006Y10B
					if($arItem["brand"] == "Orient"){
						//if(substr($arItem["article"], 0, 2) == "RA"){
						if($arItem["article"][2] == "-"){
							$arItem["article"] = substr($arItem["article"], 0, 10);
						}else{
							$arItem["article"] = substr($arItem["article"], 0, 9);
						}
					}elseif($arItem["brand"] == "Roamer")
						$arItem["article"] = $arItem["article"];
					elseif(strpos($arItem["article"], " "))
						$arItem["article"] = strstr($arItem["article"], " ", true);
					
					if($arItem["brand_id"] == 26 && $arItem["article"][2] == "/"){
						$arItem["article"] = substr($arItem["article"], 3);
					}
					
					//ищем правильный артикул, если введен 
					if($art = $objUtils->getArtnumber( $arItem["article"] ))
						$arItem["article"] = $art;
				}
					
				unset($arItem);
				unset($ar);
				//получаем товары которые были (для отчета)
				$arHistory = array();
				$tmpBrand = array();
				foreach($arBrand as $key => $arItem){
					$tmp = $this->getPriceByFilter(array("supplier_id" => $supplier["id"], "brand_id" => $arItem["id"]));
					$arHistory = array_merge($arHistory, $tmp);
					
			/*		$tmpBrand[$arItem["id"]] = array(
						"id" => $arItem["id"]
						"active" => $arItem["active"],
						"active_by" => $arItem["active_by"],
						"active_pl" => $arItem["active_pl"],
					);*/
				}
				
				//обновляем данные в базе
				$res = $this->add($supplier["id"], $arBrand, $arResult, $form);
				//ставим активность
				$arStatus = array(
					"active" => $supplier["active"],
					"active_by" => $supplier["active_by"],
					"active_pl" => $supplier["active_pl"],
				);

				//$this->changeActivityAll($supplier["id"], $arStatus, $tmpBrand);
				$this->changeActivityAll($supplier["id"], $arStatus, $arBrand);
				//получаем товары которые стали после добавления (для отчета)
				$arAdd = array();
				foreach($arBrand as $key => $arItem){
					$tmp = $this->getPriceByFilter(array("supplier_id" => $supplier["id"], "brand_id" => $arItem["id"]));
					$arAdd = array_merge($arAdd, $tmp);
				}
				$res["diff"] = $this->priceDiff($arHistory, $arAdd);
				foreach($arBrand as $key => $arItem){
					$DB->Update("ci_pricelist", array("log" => "'".addslashes($res["diff"])."'"), "WHERE supplier_id='".$supplier["id"]."' AND brand_id='".$arItem["id"]."'", $err_mess.__LINE__);
				}
				return $res;//$arResult;
			}
			
		}catch (Exception $E){
			echo $E->getMessage();
		}
	}

	//разница между тем что было и тем что загрузили
	public function priceDiff($ar1, $ar2){
		$html = "";
		$add = $delete = $change = 0;
		$arModel = array();//список моделей которые надо обновить на сайте
		$arHistory = $arAdd = array();
		foreach($ar1 as $arItem){
			$arHistory[$arItem["model"]] = $arItem;
		}
		foreach($ar2 as $arItem){
			$arAdd[$arItem["model"]] = $arItem;
		}
		//prent($arAdd, 1, 1);
		$arAll = array();
		foreach($arHistory as $arItem)
			$arAll[$arItem["model"]] = $arItem["model"];
		foreach($arAdd as $arItem)
			$arAll[$arItem["model"]] = $arItem["model"];
		
		foreach($arAll as $model){
			if(isset($arHistory[$model]) && isset($arAdd[$model])){
				if($arHistory[$model]["price"] != $arAdd[$model]["price"]){
					$change++;
					$html .= "<p class='label def'>У модели - " . $model . " изменилась цена с " . $arHistory[$model]["price"] . " на " . $arAdd[$model]["price"] . "</p>";
					$arModel[$model] = $model;
				}
			}elseif(isset($arHistory[$model]) && !isset($arAdd[$model])){
				$delete++;
				$html .= "<p class='label def'>Удалена модель - " . $model . "</p>";
				$arModel[$model] = $model;
				
				//обновляем время удаления товара
				self::updateDateDisappear($model);
			}elseif(!isset($arHistory[$model]) && isset($arAdd[$model])){
				$add++;
				$html .= "<p class='label def'>Добавлена модель - " . $model . "</p>";
				$arModel[$model] = $model;
				
				//обновляем время поступления товара
//				self::updateDateReceipt($model);
			}
		}
		if(count(self::$badColumn)){
			foreach(self::$badColumn as $k => $v){
				$html .= "<p class='label label-danger'>" . $v . "</p>";
			}
		}
		/*
		if(count($arHistory) > count($arAdd)){
			foreach($arHistory as $arItem){
				if(isset($arAdd[$arItem["model"]])){
					$change++;
					if($arItem["price"] != $arAdd[$arItem["model"]]["price"]){
						$html .= "<p class='label def'>У модели - " . $arItem["model"] . " изменилась цена с " . $arItem["price"] . " на " . $arAdd[$arItem["model"]]["price"] . "</p>";
					}else{
					//	$add++;
					//	$html .= "<p class='label def'>Модель - " . $arItem["model"] . " не изменялась</p>";
					}
				}else{
					$delete++;
					$html .= "<p class='label def'>Удалена модель - " . $arItem["model"] . "</p>";
				}
			}
			foreach($arAdd as $arItem){
				if(!isset($arHistory[$arItem["model"]])){
					$add++;
					$html .= "<p class='label def'>Добавлена модель - " . $arItem["model"] . "</p>";
				}
			}
		}else{
			foreach($arAdd as $arItem){
				if(isset($arHistory[$arItem["model"]])){
					if($arItem["price"] != $arHistory[$arItem["model"]]["price"]){
						$change++;
						$html .= "<p class='label def'>У модели - " . $arItem["model"] . " изменилась цена с " . $arHistory[$arItem["model"]]["price"] . " на " . $arItem["price"] . "</p>";
					}
				}else{
					$add++;
					$html .= "<p class='label def'>Добавлена модель - " . $arItem["model"] . "</p>";
				}
			}
		}
		*/
		$_html = "<p class='label label-success'>Добавлено {$add} моделей</p>";
		$_html .= "<p class='label label-danger'>Удалено {$delete} моделей</p>";
		$_html .= "<p class='label label-default'>Изменена цена {$change} моделей</p>";
		$_html .= $html;
		
		//добавляем в таблицу модели которые надо обновить
		self::addItemsDiff($arModel);
		return $_html;

	}
	public function addItemsDiff($arModel){
		global $DB;
		foreach($arModel as $model){
			$in = array(
				"model" => "'".addslashes($model)."'"
			);
			$DB->Insert("ci_items_diff", $in, $err_mess.__LINE__);
		}
	}
	
	public function updateDateReceipt($article){
		global $DB;
		return true;
		if(strlen($article) <= 0) return;
		$strSql = "SELECT CODE FROM ci_items_date WHERE CODE = '".addslashes($article)."' LIMIT 1";
		$results = $DB->Query($strSql, false, $err_mess.__LINE__);
		if ($row = $results->Fetch()){
			$DB->Update("ci_items_date", array("DATE_RECEIPT" => "'".date("Y-m-d H:i:s")."'"), "WHERE CODE='".$row["CODE"]."'", $err_mess.__LINE__);
		}else{
			$in = array(
				"CODE" => "'".addslashes($article)."'",
				"DATE_RECEIPT" => "'".date("Y-m-d H:i:s")."'",
			);
			$DB->Insert("ci_items_date", $in, $err_mess.__LINE__);
		}
	}
	public function updateDateDisappear($article){
		global $DB;
		if(strlen($article) <= 0) return;
		$strSql = "SELECT CODE FROM ci_items_date WHERE CODE = '".addslashes($article)."' LIMIT 1";
		$results = $DB->Query($strSql, false, $err_mess.__LINE__);
		if ($row = $results->Fetch()){
			$DB->Update("ci_items_date", array("DATE_DISAPPEAR" => "'".date("Y-m-d H:i:s")."'"), "WHERE CODE='".$row["CODE"]."'", $err_mess.__LINE__);
		}else{
			$in = array(
				"CODE" => "'".addslashes($article)."'",
				"DATE_DISAPPEAR" => "'".date("Y-m-d H:i:s")."'",
			);
			$DB->Insert("ci_items_date", $in, $err_mess.__LINE__);
		}
	}
	public function getDateUpdate($article){
		global $DB;
		if(strlen($article) <= 0) return;
		$strSql = "SELECT DATE_RECEIPT, DATE_DISAPPEAR FROM ci_items_date WHERE CODE = '".addslashes($article)."' LIMIT 1";
		$results = $DB->Query($strSql, false, $err_mess.__LINE__);
		if ($row = $results->Fetch()){
			return $row;
		}
		return false;
	}
}