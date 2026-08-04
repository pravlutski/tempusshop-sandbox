<?php
require $_SERVER['DOCUMENT_ROOT'] . '/local/vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
class CPanelPricelist{
	static $badColumn;
	function __construct(){

	}
	function getList($arFilter = array()){
		global $DB;
		$strSql = "SELECT * FROM ci_pricelist";
		$arr = array();
		if($arFilter){
			$filter = [];
			foreach($arFilter as $col => $val){
				if(is_array($val)){
					if($col[0] == "!"){
						$filter[] = str_replace("!", "", $col) . " NOT IN ('" . implode("','", $val)."')";
					}else{
						$filter[] = "{$col} IN ('" . implode("','", $val)."')";
					}

				}else{
					$filter[] = "{$col} = '" . $val . "'";
				}
			}
			if($filter)
				$strSql .= " WHERE " . implode(" AND ", $filter);
			//prent($strSql2);prent($filter);
		}

		$strSql .= " ORDER BY timestamp desc";

		$results = $DB->Query($strSql, false, $err_mess.__LINE__);
		while ($row = $results->Fetch()){
			$arr[] = $row;
		}
		return $arr;
	}

	private function add($supplier_id,$supplier_name, $arBrand, $data, $options = array()){
		global $DB;

		//
		$nds = 'N';
		$strSql = "SELECT nds FROM ci_suppliers WHERE id = '{$supplier_id}'";
		$results = $DB->Query($strSql, false, $err_mess.__LINE__);
		if ($row = $results->Fetch()) {
		    $nds = $row['nds'];
		}
		$res["true"] = $res["bad"] = 0;
		if($supplier_id < 0 || count($arBrand) <= 0 || count($data) <= 0)
			return $res;
		//удаляем старые связки
		//return $res;
		if($options["price_no_clear"] != "on"){
			foreach($arBrand as $key => $arItem){
				$DB->Query("DELETE FROM ci_price WHERE supplier_id = '".$supplier_id."' AND brand_id = '".$arItem["id"]."'", false, $err_mess.__LINE__);
				$DB->Query("DELETE FROM ci_pricelist WHERE supplier_id = '".$supplier_id."' AND brand_id = '".$arItem["id"]."'", false, $err_mess.__LINE__);
			}
		}

		$strSql = "SELECT el.ID as ID, pr.PROPERTY_123 as ARTICLE
			FROM
				b_iblock_element el
			LEFT JOIN
				b_iblock_element_prop_s16 pr
			ON el.ID=pr.IBLOCK_ELEMENT_ID
			WHERE
				el.IBLOCK_ID = '16' AND pr.PROPERTY_123 <> ''";

		$bxID = array();
		$results = $DB->Query($strSql, false, $err_mess.__LINE__);

		while ($row = $results->Fetch()){
			if(strlen($row["ARTICLE"]) > 0)
				$bxID[$row["ARTICLE"]] = $row["ID"];
		}

		//пишем
		foreach($data as $key => $arItem){
			if($options["price_r"] == "on")
				$arItem["price"] = 0;
				//file_put_contents("/var/www/bitrix/data/www/tempusshop.ru/bitrix/modules/panel.manager/classes/models/checkNikita.txt", print_r($arItem["article"], true) . "\r\n",FILE_APPEND);
				//file_put_contents("/var/www/bitrix/data/www/tempusshop.ru/bitrix/modules/panel.manager/classes/models/checkNikita.txt", print_r($arItem["price"], true) . "\r\n",FILE_APPEND);
				if ($nds == 'Y') {
					$price_n = $arItem["price"];
				} else {
					$price_n = $arItem["price"] + ($arItem["price"] * 0.2);
				}
				if ( $arItem['supplier_id'] == 41 ){
					$item = [
						'model' => $arItem['article'],
						'price' => $arItem['price'],
						'price_n' => $arItem['price_n'],
						'nds' => $nds,
					];
					file_put_contents(
						'/var/www/bitrix/data/www/tempusshop.ru/bitrix/modules/panel.manager/classes/models/checkNatalya.txt',
						print_r($item, true) . PHP_EOL,
						FILE_APPEND
					);
				}
				//file_put_contents("/var/www/bitrix/data/www/tempusshop.ru/bitrix/modules/panel.manager/classes/models/checkNikita.txt", print_r($price_n, true) . "\r\n",FILE_APPEND);
				//file_put_contents("/var/www/bitrix/data/www/tempusshop.ru/bitrix/modules/panel.manager/classes/models/checkNikita.txt", print_r('###', true) . "\r\n",FILE_APPEND);
					$in = array(
						"model" => "'".addslashes($arItem["article"])."'",
						"brand_id" => "'".addslashes($arItem["brand_id"])."'",
						"supplier_id" => "'".addslashes($arItem["supplier_id"])."'",
						"store_id" => 3,
						"price" => "'".addslashes($arItem["price"])."'",
						"price_n" => "'".addslashes($price_n)."'",
						//"count" => "'".addslashes($arItem["count"])."'",
						"count" => 500,
						"multiplicity" => "'".addslashes($arItem["multiplicity"])."'",
						"priceСurrency" => "'".addslashes($arItem["priceСurrency"])."'",
						"currency" => "'".addslashes($arItem["currency"])."'",
						"bitrix_id" => "'".addslashes($bxID[$arItem["article"]])."'",
					);
//
//AddMessage2Log($in);

			$ID = $DB->Insert("ci_price", $in, $err_mess.__LINE__);
			if($ID > 0){
				$res["true"]++;
			}else{
				$res["bad"]++;
			}
		}
//		die;
		unset($arItem);
		if($options["price_no_clear"] != "on"){
			foreach($arBrand as $key => $arItem){
				$in = array(
					"brand_id" 		=> $arItem["id"],
					"supplier_id" 	=> $supplier_id,
					"currency" => "'".addslashes($options["currency"])."'",
				);

				$strSql = "SELECT id FROM ci_pricelist WHERE brand_id = '{$arItem["id"]}' AND supplier_id = '{$supplier_id}'";
				$results = $DB->Query($strSql, false, $err_mess.__LINE__);
				if (!$row = $results->Fetch()){
					$DB->Insert("ci_pricelist", $in, $err_mess.__LINE__);
				}


			}
		}

		CProSet::setOption("UPDATE_PRICE_ANALISYS", "NEED_START");
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
	function getPriceByFilter($arFilter = array(), $group = false, $arSelect = [], $order = false){
		global $DB;
		$arr = array();

		if($arSelect && is_array($arSelect)) $select = implode(",", $arSelect); else $select = "*";

		$strSql = "SELECT {$select} FROM ci_price";
		$filter = array();
		/************** по сайту ****************/
		if(isset($arFilter["website"])){

			if(is_array($arFilter["website"])){
				$filterW = array();
				foreach($arFilter["website"] as $website){
					switch($website){
						case "s1":
							$filterW[] = "(active_ru = 'Y')";
							break;
						case "s2":
							$filterW[] = "(active_by = 'Y')";
							break;
						case "s3":
							$filterW[] = "(active_pl = 'Y')";
							break;
						case "wb":
							$filterW[] = "(active_wb = 'Y')";
							break;
						case "wbtl":
							$filterW[] = "(active_wbtl = 'Y')";
							break;
						case "sb":
							$filterW[] = "(active_sb = 'Y')";
							break;
						case "av":
							$filterW[] = "(active_av = 'Y')";
							break;
						case "kz":
							$filterW[] = "(active_kz = 'Y')";
							break;
						case "ozkz":
							$filterW[] = "(active_ozkz = 'Y')";
							break;
						case "ozti":
							$filterW[] = "(active_ozti = 'Y')";
							break;
						case "opt":
							$filterW[] = "(active_opt = 'Y')";
							break;
						case "v1":
							$filterW[] = "(active_ya = 'Y')";
							break;
						case "v2":
							$filterW[] = "(active_os = 'Y')";
							break;
						default:
							break;
					}
				}
				if(count_($filterW) > 0){
					$filter[] = "(" . implode(" OR ", $filterW) . ")";
				}

			}else{
				switch($arFilter["website"]){
					case "s1":
						$filter[] = "active_ru = 'Y'";
						break;
					case "s2":
						$filter[] = "active_by = 'Y'";
						break;
					case "s3":
						$filter[] = "active_pl = 'Y'";
						break;
					case "wb":
						$filter[] = "active_wb = 'Y'";
						break;
					case "wbtl":
						$filter[] = "active_wbtl = 'Y'";
						break;
					case "sb":
						$filter[] = "active_sb = 'Y'";
						break;
					case "av":
						$filter[] = "active_av = 'Y'";
						break;
					case "kz":
						$filter[] = "active_kz = 'Y'";
						break;
					case "ozkz":
						$filter[] = "active_ozkz = 'Y'";
						break;
					case "ozti":
						$filter[] = "active_ozti = 'Y'";
						break;
					case "opt":
						$filter[] = "active_opt = 'Y'";
						break;
					case "v1":
						$filter[] = "active_ya = 'Y'";
						break;
					case "v2":
						$filter[] = "active_os = 'Y'";
						break;
					default:
						break;
				}
			}
		}
		if(isset($arFilter["price_id"])){

			if(is_array($arFilter["price_id"])){
				$filterW = array();
				foreach($arFilter["price_id"] as $website){
					switch($website){
						case "ru":
							$filterW[] = "(active_ru = 'Y')";
							break;
						case "by":
							$filterW[] = "(active_by = 'Y')";
							break;
						case "pl":
							$filterW[] = "(active_pl = 'Y')";
							break;
						case "ya":
							$filterW[] = "(active_ya = 'Y')";
							break;
						case "os":
							$filterW[] = "(active_os = 'Y')";
							break;
						case "wb":
							$filterW[] = "(active_wb = 'Y')";
							break;
						case "wbtl":
							$filterW[] = "(active_wbtl = 'Y')";
							break;
						case "sb":
							$filterW[] = "(active_sb = 'Y')";
							break;
						case "kz":
							$filterW[] = "(active_kz = 'Y')";
							break;
						case "ozkz":
							$filterW[] = "(active_ozkz = 'Y')";
							break;
						case "ozti":
							$filterW[] = "(active_ozti = 'Y')";
							break;
						case "av":
							$filterW[] = "(active_av = 'Y')";
							break;
						case "opt":
							$filterW[] = "(active_opt = 'Y')";
							break;
						default:
							break;
					}
				}
				if(count_($filterW) > 0){
					$filter[] = "(" . implode(" OR ", $filterW) . ")";
				}

			}else{
				switch($arFilter["price_id"]){
					case "ru":
						$filter[] = "active_ru = 'Y'";
						break;
					case "by":
						$filter[] = "active_by = 'Y'";
						break;
					case "pl":
						$filter[] = "active_pl = 'Y'";
						break;
					case "ya":
						$filter[] = "active_ya = 'Y'";
						break;
					case "os":
						$filter[] = "active_os = 'Y'";
						break;
					case "wb":
						$filter[] = "active_wb = 'Y'";
						break;
					case "wbtl":
						$filter[] = "active_wbtl = 'Y'";
						break;
					case "sb":
						$filter[] = "active_sb = 'Y'";
						break;
					case "av":
						$filter[] = "active_av = 'Y'";
						break;
					case "kz":
						$filter[] = "active_kz = 'Y'";
						break;
					case "ozkz":
						$filter[] = "active_ozkz = 'Y'";
						break;
					case "ozti":
						$filter[] = "active_ozti = 'Y'";
						break;
					case "opt":
						$filter[] = "active_opt = 'Y'";
						break;
					default:
						break;
				}
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
				if(count_($ar) > 0){
					$filter[] = "supplier_id IN ('" . implode("','", $ar)."')";
				}
			}else{
				$item = intval($arFilter["supplier_id"]);
				if($item > 0)
					$filter[] = "supplier_id = '" . $item . "'";
			}
		}
		if(isset($arFilter["!supplier_id"])){
			if(is_array($arFilter["!supplier_id"])){
				$ar = array();
				foreach($arFilter["!supplier_id"] as &$item){
					$item = intval($item);
					if($item > 0)
						$ar[$item] = $item;
				}
				if(count_($ar) > 0){
					$filter[] = "supplier_id NOT IN ('" . implode("','", $ar)."')";
				}
			}else{
				$item = intval($arFilter["!supplier_id"]);
				if($item > 0)
					$filter[] = "supplier_id <> '" . $item . "'";
			}
		}

		/************** по bitrix_id ****************/
		if(isset($arFilter["bitrix_id"])){
			if(is_array($arFilter["bitrix_id"])){
				$ar = array();
				foreach($arFilter["bitrix_id"] as &$item){
					$item = intval($item);
					if($item > 0)
						$ar[$item] = $item;
				}
				if(count_($ar) > 0){
					$filter[] = "bitrix_id IN ('" . implode("','", $ar)."')";
				}
			}else{
				$item = intval($arFilter["bitrix_id"]);
				if($item > 0)
					$filter[] = "bitrix_id = '" . $item . "'";
			}
		}
		if(isset($arFilter["!bitrix_id"])){
			if(is_array($arFilter["!bitrix_id"])){
				$ar = array();
				foreach($arFilter["!bitrix_id"] as &$item){
					$item = intval($item);
					if($item > 0)
						$ar[$item] = $item;
				}
				if(count_($ar) > 0){
					$filter[] = "bitrix_id NOT IN ('" . implode("','", $ar)."')";
				}
			}else{
				$item = intval($arFilter["!bitrix_id"]);
				if($item > 0)
					$filter[] = "bitrix_id <> '" . $item . "'";
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
				if(count_($ar) > 0){
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
				if(count_($ar) > 0){
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
				if(count_($ar) > 0){
					$filter[] = "model IN ('" . implode("','", $ar)."')";
				}
			}elseif(strlen($arFilter["article"]) > 3){
				$filter[] = "model = '" . $arFilter["article"] . "'";
			}
		}
		if(isset($arFilter["!article"])){
			if(is_array($arFilter["!article"])){
				$ar = array();
				foreach($arFilter["!article"] as &$item){
					$item = intval($item);
					if($item > 0)
						$ar[$item] = $item;
				}
				if(count_($ar) > 0){
					$filter[] = "article NOT IN ('" . implode("','", $ar)."')";
				}
			}else{
				$item = intval($arFilter["!article"]);
				if($item > 0)
					$filter[] = "article <> '" . $item . "'";
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
		if(count_($filter) > 0){
			foreach($filter as $key => $f){
				if($key == 0)
					$strSql .= " WHERE " . $f;
				else
					$strSql .= " AND " . $f;
			}
		}
		if($group !== false){
			$strSql .= " GROUP BY {$group}";
		}

		if($order){
			$strSql .= " ORDER BY {$order}";
		}else{
			$strSql .= " ORDER BY model asc";
		}
//prent($strSql);

		$results = $DB->Query($strSql, false, $err_mess.__LINE__);
		while ($row = $results->Fetch()){
			if ($row['model']) $row['model'] = trim($row['model']);
			if($group !== false)
				$arr[$row[$group]] = $row;
			else
				$arr[] = $row;
		}
		return $arr;
	}

	function getPriceByFilterNew($arFilter = array(), $group = false, $arSelect = [], $order = false, $price_type){
		global $DB;
		$arr = array();

		if($arSelect && is_array($arSelect)) $select = implode(",", $arSelect); else $select = "*";

		$strSql = "SELECT {$select} FROM ci_price";
		$filter = array();
		/************** по сайту ****************/
		if(isset($arFilter["website"])){

			if(is_array($arFilter["website"])){
				$filterW = array();
				foreach($arFilter["website"] as $website){
					switch($website){
						case "s1":
							$filterW[] = "(active_ru = 'Y')";
							break;
						case "s2":
							$filterW[] = "(active_by = 'Y')";
							break;
						case "s3":
							$filterW[] = "(active_pl = 'Y')";
							break;
						case "wb":
							$filterW[] = "(active_wb = 'Y')";
							break;
						case "wbtl":
							$filterW[] = "(active_wbtl = 'Y')";
							break;
						case "sb":
							$filterW[] = "(active_sb = 'Y')";
							break;
						case "av":
							$filterW[] = "(active_av = 'Y')";
							break;
						case "kz":
							$filterW[] = "(active_kz = 'Y')";
							break;
						case "ozkz":
							$filterW[] = "(active_ozkz = 'Y')";
							break;
						case "ozti":
							$filterW[] = "(active_ozti = 'Y')";
							break;
						case "opt":
							$filterW[] = "(active_opt = 'Y')";
							break;
						case "v1":
							$filterW[] = "(active_ya = 'Y')";
							break;
						case "v2":
							$filterW[] = "(active_os = 'Y')";
							break;
						default:
							break;
					}
				}
				if(count_($filterW) > 0){
					$filter[] = "(" . implode(" OR ", $filterW) . ")";
				}

			}else{
				switch($arFilter["website"]){
					case "s1":
						$filter[] = "active_ru = 'Y'";
						break;
					case "s2":
						$filter[] = "active_by = 'Y'";
						break;
					case "s3":
						$filter[] = "active_pl = 'Y'";
						break;
					case "wb":
						$filter[] = "active_wb = 'Y'";
						break;
					case "wbtl":
						$filter[] = "active_wbtl = 'Y'";
						break;
					case "sb":
						$filter[] = "active_sb = 'Y'";
						break;
					case "av":
						$filter[] = "active_av = 'Y'";
						break;
					case "kz":
						$filter[] = "active_kz = 'Y'";
						break;
					case "ozkz":
						$filter[] = "active_ozkz = 'Y'";
						break;
					case "ozti":
						$filter[] = "active_ozti = 'Y'";
						break;
					case "opt":
						$filter[] = "active_opt = 'Y'";
						break;
					case "v1":
						$filter[] = "active_ya = 'Y'";
						break;
					case "v2":
						$filter[] = "active_os = 'Y'";
						break;
					default:
						break;
				}
			}
		}
		if(isset($arFilter["price_id"])){

			if(is_array($arFilter["price_id"])){
				$filterW = array();
				foreach($arFilter["price_id"] as $website){
					switch($website){
						case "ru":
							$filterW[] = "(active_ru = 'Y')";
							break;
						case "by":
							$filterW[] = "(active_by = 'Y')";
							break;
						case "pl":
							$filterW[] = "(active_pl = 'Y')";
							break;
						case "ya":
							$filterW[] = "(active_ya = 'Y')";
							break;
						case "os":
							$filterW[] = "(active_os = 'Y')";
							break;
						case "wb":
							$filterW[] = "(active_wb = 'Y')";
							break;
						case "wbtl":
							$filterW[] = "(active_wbtl = 'Y')";
							break;
						case "sb":
							$filterW[] = "(active_sb = 'Y')";
							break;
						case "kz":
							$filterW[] = "(active_kz = 'Y')";
							break;
						case "ozkz":
							$filterW[] = "(active_ozkz = 'Y')";
							break;
						case "ozti":
							$filterW[] = "(active_ozti = 'Y')";
							break;
						case "av":
							$filterW[] = "(active_av = 'Y')";
							break;
						case "opt":
							$filterW[] = "(active_opt = 'Y')";
							break;
						default:
							break;
					}
				}
				if(count_($filterW) > 0){
					$filter[] = "(" . implode(" OR ", $filterW) . ")";
				}

			}else{
				switch($arFilter["price_id"]){
					case "ru":
						$filter[] = "active_ru = 'Y'";
						break;
					case "by":
						$filter[] = "active_by = 'Y'";
						break;
					case "pl":
						$filter[] = "active_pl = 'Y'";
						break;
					case "ya":
						$filter[] = "active_ya = 'Y'";
						break;
					case "os":
						$filter[] = "active_os = 'Y'";
						break;
					case "wb":
						$filter[] = "active_wb = 'Y'";
						break;
					case "wbtl":
						$filter[] = "active_wbtl = 'Y'";
						break;
					case "sb":
						$filter[] = "active_sb = 'Y'";
						break;
					case "av":
						$filter[] = "active_av = 'Y'";
						break;
					case "kz":
						$filter[] = "active_kz = 'Y'";
						break;
					case "ozkz":
						$filter[] = "active_ozkz = 'Y'";
						break;
					case "ozti":
						$filter[] = "active_ozti = 'Y'";
						break;
					case "opt":
						$filter[] = "active_opt = 'Y'";
						break;
					default:
						break;
				}
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
				if(count_($ar) > 0){
					$filter[] = "supplier_id IN ('" . implode("','", $ar)."')";
				}
			}else{
				$item = intval($arFilter["supplier_id"]);
				if($item > 0)
					$filter[] = "supplier_id = '" . $item . "'";
			}
		}
		if(isset($arFilter["!supplier_id"])){
			if(is_array($arFilter["!supplier_id"])){
				$ar = array();
				foreach($arFilter["!supplier_id"] as &$item){
					$item = intval($item);
					if($item > 0)
						$ar[$item] = $item;
				}
				if(count_($ar) > 0){
					$filter[] = "supplier_id NOT IN ('" . implode("','", $ar)."')";
				}
			}else{
				$item = intval($arFilter["!supplier_id"]);
				if($item > 0)
					$filter[] = "supplier_id <> '" . $item . "'";
			}
		}

		/************** по bitrix_id ****************/
		if(isset($arFilter["bitrix_id"])){
			if(is_array($arFilter["bitrix_id"])){
				$ar = array();
				foreach($arFilter["bitrix_id"] as &$item){
					$item = intval($item);
					if($item > 0)
						$ar[$item] = $item;
				}
				if(count_($ar) > 0){
					$filter[] = "bitrix_id IN ('" . implode("','", $ar)."')";
				}
			}else{
				$item = intval($arFilter["bitrix_id"]);
				if($item > 0)
					$filter[] = "bitrix_id = '" . $item . "'";
			}
		}
		if(isset($arFilter["!bitrix_id"])){
			if(is_array($arFilter["!bitrix_id"])){
				$ar = array();
				foreach($arFilter["!bitrix_id"] as &$item){
					$item = intval($item);
					if($item > 0)
						$ar[$item] = $item;
				}
				if(count_($ar) > 0){
					$filter[] = "bitrix_id NOT IN ('" . implode("','", $ar)."')";
				}
			}else{
				$item = intval($arFilter["!bitrix_id"]);
				if($item >= 0)
					$filter[] = "bitrix_id <> '" . $item . "'";
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
				if(count_($ar) > 0){
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
				if(count_($ar) > 0){
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
				if(count_($ar) > 0){
					$filter[] = "model IN ('" . implode("','", $ar)."')";
				}
			}elseif(strlen($arFilter["article"]) > 3){
				$filter[] = "model = '" . $arFilter["article"] . "'";
			}
		}
		if(isset($arFilter["!article"])){
			if(is_array($arFilter["!article"])){
				$ar = array();
				foreach($arFilter["!article"] as &$item){
					$item = intval($item);
					if($item > 0)
						$ar[$item] = $item;
				}
				if(count_($ar) > 0){
					$filter[] = "article NOT IN ('" . implode("','", $ar)."')";
				}
			}else{
				$item = intval($arFilter["!article"]);
				if($item > 0)
					$filter[] = "article <> '" . $item . "'";
			}
		}

		if(isset($arFilter["search_text"]) && strlen($arFilter["search_text"]) > 3){
			$filter[] = "model LIKE '%" . addslashes($arFilter["search_text"]) . "%'";
		}
		if(isset($arFilter["price_from"])){
			if ($price_type == 'price') {
				$filter[] = "price >= '" . addslashes($arFilter["price_from"]) . "'";
			} else {
				$filter[] = "price_n >= '" . addslashes($arFilter["price_from"]) . "'";
			}
		}
		if(isset($arFilter["price_to"])){
			if ($price_type == 'price') {
				$filter[] = "price <= '" . addslashes($arFilter["price_to"]) . "'";
			} else {
				$filter[] = "price_n <= '" . addslashes($arFilter["price_to"]) . "'";
			}
		}
		//prent($filter);
		if(count_($filter) > 0){
			foreach($filter as $key => $f){
				if($key == 0)
					$strSql .= " WHERE " . $f;
				else
					$strSql .= " AND " . $f;
			}
		}
		if($group !== false){
			$strSql .= " GROUP BY {$group}";
		}

		if($order){
			$strSql .= " ORDER BY {$order}";
		}else{
			$strSql .= " ORDER BY model asc";
		}
//prent($strSql);

		$results = $DB->Query($strSql, false, $err_mess.__LINE__);
		while ($row = $results->Fetch()){
			if($group !== false)
				$arr[$row[$group]] = $row;
			else
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

			CProSet::setOption("UPDATE_PRICE_ANALISYS", "NEED_START");
			return true;
		} else return false;
	}
	function changeActivity( $id, $status ){
		global $DB;
		if(!in_array_($status, array("N","Y"))) return;
		$id = intval( $id );
		$res = $this->getPricelistDetail( $id );
		if( $res ){
			$brand_id = $res["brand_id"];
			$supplier_id = $res["supplier_id"];
			$DB->Update("ci_price", array("active" => "'".$status."'"), "WHERE supplier_id='".$supplier_id."' AND brand_id='".$brand_id."'", $err_mess.__LINE__);
			$DB->Update("ci_pricelist", array("active" => "'".$status."'"), "WHERE id='".$id."'", $err_mess.__LINE__);
//			CProSet::setOption("UPDATE_CATALOG", "Y");

			CProSet::setOption("UPDATE_PRICE_ANALISYS", "NEED_START");
			return true;
		} else return false;
	}

	function changeActivityAll($arSupplier){
		global $DB;
		// крайне костылёво, но так завели изначально и тянется
		if(in_array_($arSupplier["active_ru"], array("N","Y"))) $status = $arSupplier["active_ru"]; else $status = "N";
		if(in_array_($arSupplier["active_by"], array("N","Y"))) $status_by = $arSupplier["active_by"]; else $status_by = "N";
		if(in_array_($arSupplier["active_pl"], array("N","Y"))) $status_pl = $arSupplier["active_pl"]; else $status_pl = "N";
		if(in_array_($arSupplier["active_wb"], array("N","Y"))) $status_wb = $arSupplier["active_wb"]; else $status_wb = "N";
		if(in_array_($arSupplier["active_wbtl"], array("N","Y"))) $status_wbtl = $arSupplier["active_wbtl"]; else $status_wbtl = "N";
		if(in_array_($arSupplier["active_wbby"], array("N","Y"))) $status_wbby = $arSupplier["active_wbby"]; else $status_wbby = "N";
		if(in_array_($arSupplier["active_sb"], array("N","Y"))) $status_sb = $arSupplier["active_sb"]; else $status_sb = "N";
		if(in_array_($arSupplier["active_opt"], array("N","Y"))) $status_opt = $arSupplier["active_opt"]; else $status_opt = "N";
		if(in_array_($arSupplier["active_ya"], array("N","Y"))) $status_v1 = $arSupplier["active_ya"]; else $status_v1 = "N";
		if(in_array_($arSupplier["active_os"], array("N","Y"))) $status_v2 = $arSupplier["active_os"]; else $status_v2 = "N";
		if(in_array_($arSupplier["active_ozti"], array("N","Y"))) $status_ozti = $arSupplier["active_ozti"]; else $status_ozti = "N";
		if(in_array_($arSupplier["active_av"], array("N","Y"))) $status_av = $arSupplier["active_av"]; else $status_av = "N";

		//prent($arBrand);

		$DB->Update("ci_price",
		array(
			"active_ru" => "'".$status."'", "active_by" => "'".$status_by."'",
			"active_pl" => "'".$status_pl."'", "active_sb" => "'".$status_sb."'", "active_wb" => "'".$status_wb."'",
			"active_wbtl" => "'".$status_wbtl."'",
			"active_wbby" => "'".$status_wbby."'",
			"active_ozti" => "'".$status_ozti."'","active_av" => "'".$status_av."'",
			"active_opt" => "'".$status_opt."'", "active_ya" => "'".$status_v1."'", "active_os" => "'".$status_v2."'"
		), "WHERE supplier_id='".$arSupplier["id"]."'", $err_mess.__LINE__);

		$DB->Update("ci_pricelist",
		array(
			"active_ru" => "'".$status."'",
			"active_by" => "'".$status_by."'",
			"active_pl" => "'".$status_pl."'",
			"active_sb" => "'".$status_sb."'", "active_wb" => "'".$status_wb."'",
			"active_wbtl" => "'".$status_wbtl."'",
			"active_wbby" => "'".$status_wbby."'",
			"active_ozti" => "'".$status_ozti."'",
			"active_av" => "'".$status_av."'","active_opt" => "'".$status_opt."'",
			"active_ya" => "'".$status_v1."'", "active_os" => "'".$status_v2."'"
		), "WHERE supplier_id='".$arSupplier["id"]."'", $err_mess.__LINE__);

		foreach($arSupplier["settings"]["brand"] as $brand_id => $ar){
			if(in_array_($ar["active_ru"], array("N","Y"))) $status = $ar["active_ru"]; else $status = "N";
			if(in_array_($ar["active_by"], array("N","Y"))) $status_by = $ar["active_by"]; else $status_by = "N";
			if(in_array_($ar["active_pl"], array("N","Y"))) $status_pl = $ar["active_pl"]; else $status_pl = "N";
			if(in_array_($ar["active_wb"], array("N","Y"))) $status_wb = $ar["active_wb"]; else $status_wb = "N";
			if(in_array_($ar["active_wbtl"], array("N","Y"))) $status_wbtl = $ar["active_wbtl"]; else $status_wbtl = "N";
			if(in_array_($ar["active_wbby"], array("N","Y"))) $status_wbby = $ar["active_wbby"]; else $status_wbby = "N";
			if(in_array_($ar["active_sb"], array("N","Y"))) $status_sb = $ar["active_sb"]; else $status_sb = "N";
			if(in_array_($ar["active_opt"], array("N","Y"))) $status_opt = $ar["active_opt"]; else $status_opt = "N";
			if(in_array_($ar["active_ya"], array("N","Y"))) $status_v1 = $ar["active_ya"]; else $status_v1 = "N";
			if(in_array_($ar["active_os"], array("N","Y"))) $status_v2 = $ar["active_os"]; else $status_v2 = "N";
			if(in_array_($ar["active_ozti"], array("N","Y"))) $status_ozti = $ar["active_ozti"]; else $status_ozti = "N";
			if(in_array_($ar["active_av"], array("N","Y"))) $status_av = $ar["active_av"]; else $status_av = "N";

			$DB->Update("ci_price",
			array("active_ru" => "'".$status."'",
				"active_by" => "'".$status_by."'", "active_pl" => "'".$status_pl."'",
				"active_wb" => "'".$status_wb."'",
				"active_wbtl" => "'".$status_wbtl."'",
				"active_wbby" => "'".$status_wbby."'",
				"active_ozti" => "'".$status_ozti."'",
				"active_sb" => "'".$status_sb."'", "active_av" => "'".$status_av."'", "active_opt" => "'".$status_opt."'",
				"active_ya" => "'".$status_v1."'", "active_os" => "'".$status_v2."'"
			), "WHERE supplier_id='".$arSupplier["id"]."' AND brand_id='".$ar["id"]."'", $err_mess.__LINE__);

			$DB->Update("ci_pricelist",
			array(
				"active_ru" => "'".$status."'",
				"active_by" => "'".$status_by."'",
				"active_pl" => "'".$status_pl."'",
				"active_wb" => "'".$status_wb."'",
				"active_wbtl" => "'".$status_wbtl."'",
				"active_wbby" => "'".$status_wbby."'",
				"active_ozti" => "'".$status_ozti."'",
				"active_sb" => "'".$status_sb."'", "active_av" => "'".$status_av."'", "active_opt" => "'".$status_opt."'", "active_ya" => "'".$status_v1."'", "active_os" => "'".$status_v2."'"), "WHERE supplier_id='".$arSupplier["id"]."' AND brand_id='".$ar["id"]."'", $err_mess.__LINE__);
		}

//		CProSet::setOption("UPDATE_CATALOG", "Y");
		CProSet::setOption("UPDATE_PRICE_ANALISYS", "NEED_START");
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
				if(count_($ar) > 0){
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
				if(count_($ar) > 0){
					$filter[] = "name IN ('" . implode("','", $ar)."')";
				}
			}else{
				if(strlen($arFilter["name"]) > 0)
					$filter[] = "name = '" . $arFilter["name"] . "'";
			}
			//$filter[] = "model LIKE '%" . addslashes($arFilter["name"]) . "%'";
			//$filter[] = "model = '" . addslashes($arFilter["name"]) . "'";
		}
		if(isset($arFilter["bitrix_id"])){
			if(is_array($arFilter["bitrix_id"])){
				$ar = array();
				foreach($arFilter["bitrix_id"] as &$item){
					$item = intval($item);
					if($item > 0)
						$ar[$item] = $item;
				}
				if(count_($ar) > 0){
					$filter[] = "bitrix_id IN ('" . implode("','", $ar)."')";
				}
			}else{
				$item = intval($arFilter["bitrix_id"]);
				if($item > 0)
					$filter[] = "bitrix_id = '" . $item . "'";
			}
		}
		//prent($filter);
		if(count_($filter) > 0){
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

	function getCompetitorPriceByFilter($priceId, $arFilter = array()){
		if(!$priceId) return;
		global $DB;
		$arr = array();
		$strSql = "SELECT * FROM ci_price_competitor";

		$filter = ["PRICE_TYPE = '{$priceId}'"];

		/************** по артикулу ****************/
		if(isset($arFilter["article"])){
			if(is_array($arFilter["article"])){
				$ar = array();
				foreach($arFilter["article"] as $item){
					if(strlen($item) > 0)
						$ar[$item] = $item;
				}
				if(count_($ar) > 0){
					$filter[] = "ARTICLE IN ('" . implode("','", $ar)."')";
				}
			}else{
				if(strlen($arFilter["article"]) > 0)
					$filter[] = "ARTICLE = '" . $arFilter["article"] . "'";
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
		//$strSql .= " ORDER BY name asc";

		$results = $DB->Query($strSql, false, $err_mess.__LINE__);
		while ($row = $results->Fetch()){
			$arr[] = $row;
		}
		return $arr;
	}

	function getCeneoPriceByFilter($arFilter = array()){
		global $DB;
		$arr = array();
		$strSql = "SELECT * FROM ci_ceneo_price";
		$filter = array();
		if(isset($arFilter["id"])){
			if(is_array($arFilter["id"])){
				$ar = array();
				foreach($arFilter["id"] as &$item){
					$item = intval($item);
					if($item > 0)
						$ar[$item] = $item;
				}
				if(count_($ar) > 0){
					$filter[] = "id IN ('" . implode("','", $ar)."')";
				}
			}else{
				$item = intval($arFilter["id"]);
				if($item > 0)
					$filter[] = "id = '" . $item . "'";
			}
		}
		if(isset($arFilter["bitrix_id"])){
			if(is_array($arFilter["bitrix_id"])){
				$ar = array();
				foreach($arFilter["bitrix_id"] as &$item){
					$item = intval($item);
					if($item > 0)
						$ar[$item] = $item;
				}
				if(count_($ar) > 0){
					$filter[] = "bitrix_id IN ('" . implode("','", $ar)."')";
				}
			}else{
				$item = intval($arFilter["bitrix_id"]);
				if($item > 0)
					$filter[] = "bitrix_id = '" . $item . "'";
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
				if(count_($ar) > 0){
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
		if(count_($filter) > 0){
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
	function getWbPriceByFilter($arFilter = array()){
		global $DB;
		$arr = array();
		$strSql = "SELECT * FROM ci_wb_price";
		$filter = array();
		if(isset($arFilter["id"])){
			if(is_array($arFilter["id"])){
				$ar = array();
				foreach($arFilter["id"] as &$item){
					$item = intval($item);
					if($item > 0)
						$ar[$item] = $item;
				}
				if(count_($ar) > 0){
					$filter[] = "id IN ('" . implode("','", $ar)."')";
				}
			}else{
				$item = intval($arFilter["id"]);
				if($item > 0)
					$filter[] = "id = '" . $item . "'";
			}
		}
		if(isset($arFilter["bitrix_id"])){
			if(is_array($arFilter["bitrix_id"])){
				$ar = array();
				foreach($arFilter["bitrix_id"] as &$item){
					$item = intval($item);
					if($item > 0)
						$ar[$item] = $item;
				}
				if(count_($ar) > 0){
					$filter[] = "bitrix_id IN ('" . implode("','", $ar)."')";
				}
			}else{
				$item = intval($arFilter["bitrix_id"]);
				if($item > 0)
					$filter[] = "bitrix_id = '" . $item . "'";
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
				if(count_($ar) > 0){
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
		if(count_($filter) > 0){
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
	function getOnlinerPriceByFilter($arFilter = array(), $checkAlternative = true){
		global $DB;
		$arr = array();
		$objUtils = new CPanelUtils;
		if($checkAlternative)
			$arArtnumberAll = $objUtils->getArtnumberAll();
		$strSql = "SELECT * FROM ci_catalog_onliner";
		//, p.section as section, p.brand as brand, p.url as url, p.shop_price as shop_price

		$strSql = "SELECT p.id as id, p.min_price as min_price, p.min_price2 as min_price2, p.min_price3 as min_price3, a.article as model
		FROM
			ci_catalog_onliner p
		LEFT JOIN
			ci_onliner_articles	a ON p.id=a.id";
		/*$strSql = "SELECT p.id as id, p.section as section,	p.brand as brand, p.url as url, p.min_price as min_price,
		p.shop_price as shop_price, p.model as model
		FROM
			ci_catalog_onliner p";*/

		//  return false;
		/************** по артикулу ****************/
		$filter = [];
		if(isset($arFilter["model"])){
			if(is_array($arFilter["model"])){
				$ar = [];
				foreach($arFilter["model"] as $item){
					if(strlen($item) > 0)
						$ar[$item] = $item;
				}
				if(count($ar) > 0){
					$filter[] = "a.article IN ('" . implode("','", $ar)."')";
					//$filter[] = "p.model IN ('" . implode("','", $ar)."')";
					//$filter[] = "(a.article IN ('" . implode("','", $ar)."') OR p.model IN ('" . implode("','", $ar)."'))";
				}
			}else{
				if(strlen($arFilter["model"]) > 0)
					$filter[] = "a.article = '" . $arFilter["model"] . "'";
				//$filter[] = "p.model = '" . $arFilter["model"] . "'";
				//$filter[] = "(a.article = '" . $arFilter["model"] . "' OR p.model = '" . $arFilter["model"] . "')";
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
			//if(!$model = $objUtils->getArtnumber($row["model"]))
			//	$model = $row["model"];
			$model = $row["model"];
			if($checkAlternative && $arArtnumberAll[$row["model"]])
				$model = $arArtnumberAll[$row["model"]];

			$arr[] = array(
				"name" => $model,
				"minPrice" => $row["min_price"],
				"minPrice2" => $row["min_price2"],
				"minPrice3" => $row["min_price3"],
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
				if(count_($ar) > 0){
					$filter[] = "model IN ('" . implode("','", $ar)."')";
				}
			}else{
				if(strlen($arFilter["model"]) > 0)
					$filter[] = "model = '" . addslashes($arFilter["model"]) . "'";
			}
		}

		if(isset($arFilter["!model"])){
			if(is_array($arFilter["!model"])){
				$ar = array();
				foreach($arFilter["!model"] as &$item){
					$item = intval($item);
					if($item > 0)
						$ar[$item] = $item;
				}
				if(count_($ar) > 0){
					$filter[] = "model NOT IN ('" . implode("','", $ar)."')";
				}
			}else{
				if(strlen($arFilter["!model"]) > 0)
					$filter[] = "model = '" . addslashes($arFilter["!model"]) . "'";
			}
		}

		if(count_($filter) > 0){
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

	function prepareMinPrice($price = []) {
		$groupedPrices = [];
		foreach ($price as $item) {
			$article = $item['ARTICLE'];
			if ($item['PRICE_DISCOUNT'] && $item['PRICE_DISCOUNT'] < $item['PRICE']) {
				$groupedPrices[$article][] = $item['PRICE_DISCOUNT'];
			} else {
				$groupedPrices[$article][] = $item['PRICE'];
			}
		}

		$result = [];
		foreach ($groupedPrices as $article => $prices) {
			sort($prices);

			$minPrices = array_slice($prices, 0, 3);

			$result[$article] = [
				'name' => $article,
				'minPrice' => $minPrices[0] ?? null,
				'minPrice2' => $minPrices[1] ?? null,
				'minPrice3' => $minPrices[2] ?? null
			];
		}

		return $result;
	}

	// загрузка прайслиста
	function upload($filename, $form, $supplier){
		global $DB, $USER;
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
		$settings_pricelist_num = $supplier["settings_pricelist_detail"];//настройки обработки прайса по листам
		$settings = $supplier["settings"];//настройки поставщика

		$arBrand = array();
		$availBrand = $objBrand->getList();

		$arRegular = $arRegularReplace = array();

		//проверяем какие бренды пришли из формы с теми что разрешены в настройках. оставляем только разрешенные

		foreach($form["brand"] as $f_brand){
			foreach($settings["brand"] as $s_brand){
				if($s_brand["id"] == $f_brand){
					if($br = $objBrand->getDetail( $f_brand )){

						// file_put_contents("/var/www/bitrix/data/www/tempusshop.ru/bitrix/modules/panel.manager/classes/models/checkDenis.txt", print_r($s_brand["sale"], true) . "\r\n",FILE_APPEND);
						$br["sale"] = floatval(str_replace(',','.',$s_brand["sale"]));
						$br["priority"] = (float) $s_brand["priority"];
						// file_put_contents("/var/www/bitrix/data/www/tempusshop.ru/bitrix/modules/panel.manager/classes/models/checkDenis.txt", print_r($br["sale"], true) . "\r\n",FILE_APPEND);
						$br["active_ru"] = $s_brand["active_ru"];
						$br["active_by"] = $s_brand["active_by"];
						$br["active_pl"] = $s_brand["active_pl"];
						$br["active_wb"] = $s_brand["active_wb"];
						$br["active_wbtl"] = $s_brand["active_wbtl"];
						$br["active_sb"] = $s_brand["active_sb"];
						$br["active_opt"] = $s_brand["active_opt"];
						$br["active_ya"] = $s_brand["active_ya"];
						$br["active_os"] = $s_brand["active_os"];
						$br["active_ozti"] = $s_brand["active_ozti"];
						$br["active_av"] = $s_brand["active_av"];

						$br["name"] = mb_strtoupper($br["name"]);

						$arBrand[] = $br;

						if(strlen($br["regular"]) > 2){
							$arRegular[$br["id"]] = $br["regular"];
						}


						if(strlen($br["regular_search"]) > 2 && strlen($br["regular_replace"]) >= 2){
							$arRegularReplace[$br["id"]] = array(
								"pattern" => $br["regular_search"],
								"replacement" => $br["regular_replace"],
							);
						}

					}
					$tmpBrand[$br["id"]] = array("id" => $br["id"], "name" => $br["name"]);
				}
			}
		}
		if(count_($arBrand) == 0){
			return "no select brand";
		}

		//список всех артикулов с брендом. если совсем ничего не нашли пробуем просто в уже загруженных найти бренд по арикулу
		$strSql = "SELECT model, brand_id FROM ci_price WHERE SUBSTR(model, 1, 3) != 'VST' GROUP BY model";
		$results = $DB->Query($strSql, false, $err_mess.__LINE__);
		while ($row = $results->Fetch()){
			if($tmpBrand[$row["brand_id"]])
				$arArtPrice[$row["model"]] = $tmpBrand[$row["brand_id"]];
		}

		$arBXArticle = $this->getAllArticleBrand();// список альтернативных названий брендов

		$arOrigBrand = $arBrand;

		if(isset($settings["currency"]) && $settings["currency"] != "RUB"){
			$currency = $objCurrency->getDetail( $settings["currency"] );//курс валюты
			$amount = $currency["amount"];
			$rate = $currency["rate"];

			$arParams["CURRENCY"] = $currency["id"];
		}else{
			$amount = $rate = 1;
			$arParams["CURRENCY"] = "RUB";
		}

		$arAltBrand = array();
		$arClearStr = array();//массив для удаления из артикула хлама
		foreach($arBrand as $k => $v) {
			$arBrandName[$v["name"]] = $v["name"];

			$arClearStr[] = mb_strtoupper($v["name"]);
			//альтернативные бренды
			if(strlen($v["alt_name"]) > 0){
				$tmp = explode("|", $v["alt_name"]);
				foreach($tmp as $key => &$name){
					$name = trim($name);
					if(strlen($name) > 0){
						$arAltBrand[] = array(
							'id' => $v['id'],
							'sort' => $v['sort'],
							'name' => mb_strtoupper($name, "UTF-8"),
							'sale' => $v['sale'],
							'priority' => $v["priority"],
							'active_ru' => $v['active_ru'],
							'active_by' => $v['active_by'],
							'active_pl' => $v['active_pl'],
							'active_wb' => $v['active_wb'],
							'active_wbtl' => $v['active_wbtl'],
							'active_sb' => $v['active_sb'],
							'active_opt' => $v['active_opt'],
							'active_ya' => $v['active_ya'],
							'active_os' => $v['active_os'],
							'active_ozti' => $v['active_ozti'],
							'active_av' => $v['active_av'],
						);

						$arClearStr[] = mb_strtoupper($name, "UTF-8");
					}
				}
				unset($name);
			}
		}

		array_multisort(array_map('strlen', $arClearStr), $arClearStr);
		$arClearStr = array_reverse($arClearStr);

		if(count_($arAltBrand) > 0) $arBrand = array_merge($arBrand, $arAltBrand);

		try{
			$arLists = array();
			if(strlen($settings_pricelist["num_lists"]) > 0){
				$tmp = explode(",", $settings_pricelist["num_lists"]);
				foreach($tmp as $k => $v){
					$list = intval($v);
					if($list > 0)$arLists[$list-1] = $list-1;
				}

			}

			$brand_lock = false;
			if($settings_pricelist["brand_from_list"] == "Y")
				$brand_lock = true;


			$spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($filename);

			$cntSheets = $spreadsheet->getSheetCount() - 1;
			$i = 0;
			$ar = array();
			for($index = 0; $index <= $cntSheets; $index++){
				if(count_($arLists) > 0 && !in_array_($index, $arLists)) continue;
				$sheet = $spreadsheet->getSheet($index);

				//для прайсов у которых нет бренда пытаемся взять название бренда из названия листа
				if($brand_lock === true){
					$sheetName = $spreadsheet->getSheetNames()[$index];
					$_brand = false;
					foreach($arBrand as $k => $v){
						if(stripos($sheetName, $v["name"]) !== false){
							$_brand = $v["name"];
							break;
						}
					}
				}

				$data = $sheet->toArray();
				foreach ($data as $key => $row){
					$ar[$i] = $row;

					if($brand_lock === true && $_brand){
						$ar[$i]["brand"] = $_brand;
					}

					$ar[$i]["list_num"] = $index + 1;

					$i++;
				}
			}
			if ( $supplier['id'] == 146 ){
				// var_dump($data);
			}
			// прайсы присылают разные и не всегда парсятся. пробуем распарсить PHPExcel, если ничего не получилось выше
			if(count_($ar) <= 1){

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

			$arUp = array();
			foreach($ar as $key => $row){
				//$arUp[] = array_map('strtoupper', $row);
				$arUp[] = array_map(function($str){
					return mb_strtoupper($str, "UTF-8");
				}, $row);
			}

			// если поставщик "Андрей (Москва, ПН-ПТ 9-15 | 16)"
			if($supplier["id"] == 83){
				$ar = array();
				foreach($arUp as $key => &$arItem){
					if(mb_strstr($arItem[0], "ЧАСЫ")) $lastArticle = false;
					if(strlen($arItem[0]) && !ctype_digit($arItem[0]) && intval($arItem[1]) > 0 && mb_strstr($arItem[0], "ЧАСЫ")){
						$arItem[0] = trim(str_replace(array(", ШТ", "ЧАСЫ", "РЕМЕНЬ"), "", $arItem[0]));
						$ar[$key] = $arItem;
						$lastArticle = $key;
					}elseif(strlen($arItem[0]) && ctype_digit($arItem[0]) > 0 && $ar[$lastArticle]){
						$ar[$lastArticle][1] = $arItem[0];
						$ar[$lastArticle][2] = $arItem[1];
					}
				}
				$arUp = $ar;
			}

			$ar = $arUp;

			$arResult = array();
			unset($spreadsheet);
			$arAlternate = $objUtils->getArtnumberAll();
			$arIDAccess = array();
			foreach($arOrigBrand as $k => $v){
				$arIDAccess[$v["id"]] = $v["sale"];
			}

			// если поставщик Наталья ПТ (Москва, ПН-ПТ 9-12 | 16), то разные варианты присылает
			if($supplier["id"] == 77 && $ar[1][0] == "ПАРАМЕТРЫ:"){
				$profile = 2;
			}else{
				$profile = 1;
			}

			if(intval($form["col_brand"]) > 0){
				$col_brand = $form["col_brand"];
			}
			if(intval($form["col_article"]) > 0){
				$col_article = $form["col_article"];
			}
			if(intval($form["col_price"]) > 0){
				$col_price = $form["col_price"];
			}
			if(intval($form["col_count"]) > 0){
				$col_count = $form["col_count"];
			}
			if(intval($form["col_multiplicity"]) > 0){
				$col_multiplicity = $form["col_multiplicity"];
			}
			//разбираем данные которые прочитали из файла
			if(count_($ar) > 0){
				//file_put_contents("/var/www/bitrix/data/www/tempusshop.ru/bitrix/modules/panel.manager/classes/models/upload.txt", print_r($ar, true) . "\r\n", FILE_APPEND);

				//wdhs
						$margin = $settings_pricelist["margin"];
				//end wdhs
				// $margin = (float) $settings_pricelist["margin"];
				$margin_round = (!empty($settings_pricelist["margin_round"]) ? intval($settings_pricelist["margin_round"]) : 2);

				// file_put_contents("/var/www/bitrix/data/www/tempusshop.ru/bitrix/modules/panel.manager/classes/models/articesCs.txt", print_r($arBXArticle, true) . " -> bxarticlem\r\n",FILE_APPEND);
				// file_put_contents("/var/www/bitrix/data/www/tempusshop.ru/bitrix/modules/panel.manager/classes/models/articesCs.txt", print_r($arIDAccess, true) . " -> ARIDASCCES\r\n",FILE_APPEND);
				foreach ($ar as $key => $row){

					$tmp = $matches = array_diff($row, array(''));
					if(count_($tmp) == 2 && strlen($tmp[0]) > 0) $perBrand = $tmp[0];

					//подменяем колонки, если есть настройки по листам
					if($settings_pricelist_num[$row["list_num"]]["active"] == "Y"){
						if(!$col_price)
							$col_price = intval($settings_pricelist_num[$row["list_num"]]["col_price"]);
						if(!$col_article)
							$col_article = intval($settings_pricelist_num[$row["list_num"]]["col_article"]);
						if(!$col_brand)
							$col_brand = intval($settings_pricelist_num[$row["list_num"]]["col_brand"]);
						if(!$col_count)
							$col_count = intval($settings_pricelist_num[$row["list_num"]]["col_count"]);
						if(!$col_multiplicity)
							$col_multiplicity = intval($settings_pricelist_num[$row["list_num"]]["col_multiplicity"]);
						$count_default = intval($settings_pricelist_num[$row["list_num"]]["count_default"]);
						$quntity_flag = $settings_pricelist_num[$row["list_num"]]["quntity_flag"];
						$quntity_value = $settings_pricelist_num[$row["list_num"]]["quntity_value"];
						$start_row = intval($settings_pricelist_num[$row["list_num"]]["start_row"]);
						if($settings_pricelist_num[$row["list_num"]]["clear_space"] == "Y")
							$clear_space = true;
					}else{
						if(!$col_price)
							$col_price = intval($settings_pricelist["col_price"]);
						if(!$col_article)
							$col_article = intval($settings_pricelist["col_article"]);
						if(!$col_brand)
							$col_brand = intval($settings_pricelist["col_brand"]);
						$col_count = intval($settings_pricelist["col_count"]);
						$col_multiplicity = intval($settings_pricelist["col_multiplicity"]);
						$count_default = intval($settings_pricelist["count_default"]);
						$quntity_flag = $settings_pricelist["quntity_flag"];
						$quntity_value = $settings_pricelist["quntity_value"];
						$start_row = intval($settings_pricelist["start_row"]);
						if($settings_pricelist["clear_space"] == "Y")
							$clear_space = true;
					}

					if($supplier["id"] == 77 && $profile == 2){
						$col_price = 5;
						$col_article = 1;
						$col_brand = 10;
						$start_row = 7;
					}

					if($key < ($start_row - 1)) continue;

					$article = $row[$col_article - 1];//29-04-2020

					if(!$col_brand || $col_brand == $col_article){

						$article = str_replace($arClearStr, '', $article);

					}
					$article = trim($article);
					if($clear_space === true) {
						$article = str_replace_once(' ', '', trim($article));
					}

					//file_put_contents("/var/www/bitrix/data/www/tempusshop.ru/bitrix/modules/panel.manager/classes/models/articesCs.txt", print_r($article, true) . " - ISXOD\r\n", FILE_APPEND);
					//file_put_contents("/var/www/bitrix/data/www/tempusshop.ru/bitrix/modules/panel.manager/classes/models/articesCs.txt", print_r('>>>>>', true) . "\r\n", FILE_APPEND);
					//wdhs проверка для олега
					if ($supplier["id"] == 124 && (strpos($article, '(') !== false) && (strpos($article, ')') !== false)) {
							unset($article);
					}

					if(!$article) continue;



					//file_put_contents("/var/www/bitrix/data/www/tempusshop.ru/bitrix/modules/panel.manager/classes/models/articesCs.txt", print_r('#############', true) . "s\r\n", FILE_APPEND);

					if($this->isWatchBand($article)){
						$isWatchBand = true;
						$clearArt = false;
						//$article = trim(str_replace(array("РЕМЕШОК ДЛЯ", "РЕМЕШОК", "РЕМЕНЬ", " , ", " ,", ", ", " / ", " /", "/ ", "   ", "  "), array("","","",",",",",",","/","/","/"," "," "), $article));
						$article = trim(str_replace(array("РЕМЕШОК ДЛЯ ", "РЕМЕШОК ", "РЕМЕНЬ "), "", $article));

					}else{
						$isWatchBand = false;
						$clearArt = true;
					}

					$flg = false; // поиск бренда в каждой строке



					if (isset($arAlternate[$article])) {
						$article = $arAlternate[$article];
					}

					if($arBXArticle[$article] && isset($arIDAccess[$arBXArticle[$article]["id"]])){

						$flg = true;
						$brand_name = $arBXArticle[$article]["name"];
						$brand_id = $arBXArticle[$article]["id"];
						$sale = $arIDAccess[$arBXArticle[$article]["id"]];

					} else {
						if ($supplier["id"] == 124) {

							// file_put_contents("/var/www/bitrix/data/www/tempusshop.ru/bitrix/modules/panel.manager/classes/models/articesCs.txt", print_r($article, true) . " -> ISXOD\r\n", FILE_APPEND);
							$article = str_replace("RUS", '', $article);
							$ed_article = trim(str_replace(" ", '', $article));
							$ed_article2 = trim(str_replace("-", "J", $article));
							// file_put_contents("/var/www/bitrix/data/www/tempusshop.ru/bitrix/modules/panel.manager/classes/models/articesCs.txt", print_r($ed_article, true) . " -> ED1\r\n",FILE_APPEND);
							// file_put_contents("/var/www/bitrix/data/www/tempusshop.ru/bitrix/modules/panel.manager/classes/models/articesCs.txt", print_r($ed_article2, true) . " -> ED2\r\n",FILE_APPEND);
							if($arBXArticle[$ed_article] && isset($arIDAccess[$arBXArticle[$ed_article]["id"]])){
								$article = $ed_article;
								$flg = true;
								$brand_name = $arBXArticle[$article]["name"];
								$brand_id = $arBXArticle[$article]["id"];
								$sale = $arIDAccess[$arBXArticle[$article]["id"]];
								// file_put_contents("/var/www/bitrix/data/www/tempusshop.ru/bitrix/modules/panel.manager/classes/models/articesCs.txt", print_r($article, true) . " -> NEW ART\r\n", FILE_APPEND);
								// file_put_contents("/var/www/bitrix/data/www/tempusshop.ru/bitrix/modules/panel.manager/classes/models/articesCs.txt", print_r($brand_name, true) . " -> BRAND\r\n", FILE_APPEND);
								// file_put_contents("/var/www/bitrix/data/www/tempusshop.ru/bitrix/modules/panel.manager/classes/models/articesCs.txt", print_r("######", true) . " -> BRAND\r\n", FILE_APPEND);
							} else if($arBXArticle[$ed_article2] && isset($arIDAccess[$arBXArticle[$ed_article2]["id"]])){
								$article = $ed_article2;
								$flg = true;
								$brand_name = $arBXArticle[$article]["name"];
								$brand_id = $arBXArticle[$article]["id"];
								$sale = $arIDAccess[$arBXArticle[$article]["id"]];
								// file_put_contents("/var/www/bitrix/data/www/tempusshop.ru/bitrix/modules/panel.manager/classes/models/articesCs.txt", print_r($article, true) . " -> NEW ART\r\n", FILE_APPEND);
								// file_put_contents("/var/www/bitrix/data/www/tempusshop.ru/bitrix/modules/panel.manager/classes/models/articesCs.txt", print_r($brand_name, true) . " -> BRAND\r\n", FILE_APPEND);
								// file_put_contents("/var/www/bitrix/data/www/tempusshop.ru/bitrix/modules/panel.manager/classes/models/articesCs.txt", print_r("######", true) . " -> BRAND\r\n", FILE_APPEND);
							}
						}
					}
					//file_put_contents("/var/www/bitrix/data/www/tempusshop.ru/bitrix/modules/panel.manager/classes/models/articesCs.txt", print_r($article, true) . " -> ART\r\n", FILE_APPEND);
					$flgBrand = false;
					if($flg === false && $supplier["id"] == 77){
						$flgBrand = true;

						$arL = array(
							"CTZ" => array("id" => 32, "name" => "Citizen"),
							"GR" => array("id" => 43, "name" => "Ingersol"),
							"RL" => array("id" => 33, "name" => "Royal London"),
							"RMN" => array("id" => 22, "name" => "Romanson"),
							"SAM" => array("id" => 56, "name" => "Swiss Alpine Military"),
							"STM" => array("id" => 55, "name" => "Storm"),
							"DOXA" => array("id" => 10, "name" => "DOXA"),
							"LOCMAN" => array("id" => 53, "name" => "Locman"),
						);
						if($profile == 1 && $row[1] && $arL[$row[1]] && !$row[8]){
							$arBrandL = array(
								"id" => $arL[$row[1]]["id"],
								"name" => $arL[$row[1]]["name"],
								"sale" => $arIDAccess[$arL[$row[1]]["id"]],
							);
						}elseif($profile == 2 && $row[0] && $arL[$row[0]] && !$row[4]){
							$arBrandL = array(
								"id" => $arL[$row[0]]["id"],
								"name" => $arL[$row[0]]["name"],
								"sale" => $arIDAccess[$arL[$row[0]]["id"]],
							);
						}


					}

					if($flg === false && $flgBrand === false && ($col_brand > 0 || $brand_lock === true) && count($arOrigBrand) > 1){
						//пропускаем если нету в массиве с нужными брендами
						foreach($arBrand as $k => &$v){
							if($brand_lock === true)
								$pos = stripos($row["brand"], $v["name"]);
							else
								$pos = mb_stripos($row[$col_brand - 1], $v["name"]);

							if ($pos !== false) {
								$brand_name = $v["name"];
								$brand_id = $v["id"];

								$sale = $v["sale"];

								$flg = true;
								break;
							}
						}
						unset($v);

						//if ($flg === false && !$row[$col_brand - 1]) {
						if ($flg === false && $col_brand == $col_article) {
							if( ($article[1] == "-" || $article[2] == "-" || $article[3] == "-") && mb_strtoupper(substr($article, 0, 3)) != 'VST' ){
								foreach($arBrand as $k => $v){
									//if($v["name"] == "Casio"){
									if($v["id"] == 1){
										$brand_name = $v["name"];
										$brand_id = $v["id"];

										$sale = $v["sale"];
										$flg = true;
									}
								}
							}
						}

						if($flg === false){

							if($arArtPrice[$article]){
								$brand_name = $arArtPrice[$article]["name"];
								$brand_id = $arArtPrice[$article]["id"];

								$flg = true;
							}else{
								//если и так не нашли то пробуем искать по строке $perBrand где возмет быть бренд
								//$perBrand
								if(mb_stripos($perBrand, "BABY") !== false){
									$brand_name = "CASIO";
									$brand_id = 1;
									//AddMessage2Log($article);
									$flg = true;
								}elseif(mb_stripos($perBrand, "КОМАНДИРСКИЕ") !== false){
									$brand_name = "Восток";
									$brand_id = 38;

									$flg = true;
								}
							}

						}

						if ($flg === false) {
							//self::$badColumn[] = "Артикул - {$article}. Бренда нет в доступных к загрузке";
							continue;
						}

						//return false;
					}elseif(count_($arOrigBrand) == 1 && $flg === false){
						// если выбран 1 бренд
						$flg = false;
						if(strlen($arBrand[0]["name"]) > 0){
							if($col_brand > 0)
								$flg = stripos($row[$col_brand - 1], $arBrand[0]["name"]);
							else
								$flg = true;

							//если написание бренда точь в точь только на кириллице
							// && strlen($row[$col_brand - 1]) == strlen($arBrand[0]["name"])
							if($flg === false){
								if(!preg_match("/^[^а-я]+$/", $row[$col_brand - 1])){
									$tr = array(
										"А"=>"A","Е"=>"E","К"=>"K","М"=>"M","Н"=>"H","О"=>"O","Р"=>"P","С"=>"C","Т"=>"T",
										"а"=>"a","е"=>"e","к"=>"k","м"=>"m","н"=>"h","о"=>"o","р"=>"p","с"=>"c","т"=>"t",
									);
									$rebrand = strtr($row[$col_brand - 1],$tr);
									$flg = stripos($rebrand, $arBrand[0]["name"]);
									if ($flg !== false){
										$arBrand[0]["name"] = $rebrand;
									}
									//AddMessage2Log($rebrand);
								}

							}

						}else{
							self::$badColumn[] = "Артикул - {$article}. Бренд неопределен";
							continue;
						}

						if ($flg === false) {
							//if($arBrand[0]["name"] == "Casio" && ($article[1] == "-" || $article[2] == "-" || $article[3] == "-"))
							if($arBrand[0]["id"] == 1 && ($article[1] == "-" || $article[2] == "-" || $article[3] == "-"))
								$flg = true;
						}
						//
						if ($flg === false) {
							self::$badColumn[] = "Артикул - {$article}. Бренд неопределен2";
							continue;
						}
						$brand_name = $arBrand[0]["name"];
						$brand_id = $arBrand[0]["id"];

						$sale = $arBrand[0]["sale"];

						if(!$col_brand || $col_brand == $col_article)
							$article = str_ireplace($brand_name, "", $article);
					}elseif($flgBrand === true && $arBrandL["id"]){
						//self::$badColumn[] = "Артикул - {$article}. Бренда неопределен3";
						//continue;
						$brand_name = $arBrandL["name"];
						$brand_id = $arBrandL["id"];
						$sale = $arBrandL["sale"];

					}elseif($flg === false){
						self::$badColumn[] = "Артикул - {$article}. Бренда неопределен3";
						continue;
					}


					$price = $row[$col_price - 1];
					$price = preg_replace("/[^0-9.,]/", "", $price);
					//file_put_contents("/var/www/bitrix/data/www/tempusshop.ru/bitrix/modules/panel.manager/classes/models/articesCs.txt", print_r($article, true). ' -> ' . trim($price) . "\r\n", FILE_APPEND);
					if($supplier["id"] == 77){
						if(strlen($row[9]) > 0){
							$price = $row[9];
							$sale = 0;
						}
						if($profile == 2) $sale = 0;
					}

					//$price = str_replace(" ", "", $price);
					$price = str_replace(array("'",'"'," ","$"), "", $price);

					// если есть и точки и запятая, то считаем что запятые это разряды и удаляем их
					if(substr_count($price, ',') >= 1 && substr_count($price, '.') == 1){
						$price = str_replace(",", "", $price);
					}

					$t = explode(",", $price);
					if(strlen($t[1]) == 2 && $t[1] == "00")
						$price = $t[0];
					elseif(count_($t) == 2){
						$price = str_replace(",", ".", $price);
					}else{
						$price = str_replace(",", "", $price);
					}

					if($brand_id > 0 && $supplier["settings_brand_sale"][$brand_id] && $supplier["settings_brand_sale"][$brand_id]){
						foreach($supplier["settings_brand_sale"][$brand_id] as $regular){
							if($regular["active"] == "Y" && preg_match($regular["regular"], $article)){
								$sale = $regular["sale"];
							}
						}
					}
					$price = floatval($price);

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

					}elseif($count_default > 0){
						$count = $count_default;
					}else{
						$count = 0;
					}

					// кратность
					if($col_multiplicity > 0){
						$multiplicity = (int)$row[$col_multiplicity - 1];
						if($multiplicity <= 1) $multiplicity = 1;
					}else{
						$multiplicity = 1;
					}

					if($count <= 0){
						self::$badColumn[] = "Количество - {$count}. Артикул - {$article}";
						continue;
					}

					if(strlen($article) > 0 && $price > 0){

						if($clearArt === true){
							//удаляем из артикула по регулярке из настроек бренда. костыли ниже почистить!!!
							if($arRegular[$brand_id]){
								preg_match($arRegular[$brand_id], $article, $matches);
								$matches = array_diff($matches, array(''));
								$matches = array_unique($matches);

								if($matches && count($matches) == 1 && strlen($matches[0]) > 0)
									$article = $matches[0];
							}

							//22/04/2020 поставил выше т.к. в праййсе дубли по артикулам. если будет тормозить вернуть обратно


							$article = str_replace(array("  "), array(" "), $article);
							$article = trim($article);

							//$pos = strpos($article, " ");
							//если поставщик 3. Денис (supplier_id = 39) и бренд Восток (brand_id = 38)
							//если поставщик 3. Денис (supplier_id = 39) и бренд Слава (brand_id = 59) или Спецназ (brand_id = 60)

							if($supplier["id"] == 39 && in_array_($brand_id, array(38, 59, 60))){
								$tmp = trim(array_pop(explode(" ", $article)));

								if(strlen($tmp) > 0){
									$article = $tmp;

								}
							}

							if($supplier["id"] == 87){
								$article = str_replace(array("ЧАСЫ-НАР.", "FАСЫ-НАР.", '"', "  "), array(" ", " ", " ", " "), $article);
								$article = trim($article);
							}

							if($supplier["id"] == 41 && $brand_id == 20){
								//Если в артикуле 14 символов, то нужно поставить точки после 4, 7, 9,12 символов. Если 9 символов, то после 3,4, 7 символов
								if(strlen($article) == 14){
									$pattern = '/(.{4})(.{3})(.{2})(.{3})/i';

									$replacement = '$1.$2.$3.$4.';
									$article = preg_replace($pattern, $replacement, $article);

								}elseif(strlen($article) == 9){
									$pattern = '/(.{3})(.{1})(.{3})/i';

									$replacement = '$1.$2.$3.';
									$article = preg_replace($pattern, $replacement, $article);
								}
							}
							//если поставщик 3. Денис (supplier_id = 39) и бренд романсан (brand_id = 22)
							if($supplier["id"] == 39 && $brand_id == 22){
								$tmp = trim(array_shift(explode(" ", $article)));
								//$tmp = intval($tmp);
								//if($tmp > 0){
								if(strlen($tmp) > 0){
									$article = $tmp;

								}
							}

							//для романсана 22 удаляем пробелы
							if($brand_id == 22){
								$article = str_replace(" ", "", $article);
							}
							//для Claude Bernard
							if($brand_id == 70){
								$article = str_replace(" ", "-", $article);
							}

							//для QQ 16 чистим
							//если пятый символ -, то менять на J. если девятого символа нет - добавлять Y
							if($brand_id == 16){
								$article = str_replace(array("МУЖ", "ЖЕН", "LCD"), array("", "", ""), $article);
								$article = trim($article);
								if ( $supplier['id'] == 63 ){
									if ( str_contains($article, " ") ) $article = end( explode(' ', $article) );
								}
								if($article[4] == "-") $article[4] = "J";
								if(strlen($article) == 8) $article[8] = "Y";
							}
							//if($brand_name == "Orient"){
							if($brand_id == 2){
								if($article[2] == "-"){
									$article = substr($article, 0, 10);
								}else{
									$article = substr($article, 0, 9);
								}
								//}elseif($brand_name == "Roamer")
							}elseif($brand_id == 14)
								$article = $article;
							elseif(strpos($article, " ")){
								if(!in_array_($brand_id, array(70)))
									$article = strstr($article, " ", true);
							}
							if($brand_id == 26 && $article[2] == "/"){
								$article = substr($article, 3);
							}

							//если Tissot то удаляем точку после буквы T
							if($brand_id == 20 && $article[0] == "T" && $article[1] == "."){
								$article = substr_replace($article, '', 1, 1);
							}
							if ( in_array($brand_id, [20, 31, 39]) ){
								$article = $this->formatArticle( $brand_id, $article );
							}

							//CALVIN KLEIN если предпоследний символ символ, то перед ней поставить точку
							if($brand_id == 27 && !ctype_digit(substr($article, -2, 1)) && substr($article, -3, 1) != "."){
								$article = substr($article, 0, -2) . "." . substr($article, -2);
							}

							// Преобразование артикулов CASIO и запись в БД альтернативных
							if ( $brand_id == 1 && $modArticle = $this->modifyArticle($article) ){
								$strSql = "SELECT * FROM ci_catalog_artnumbers WHERE alternative = '".$modArticle['raw']."'";
								$results = $DB->Query($strSql, false, $err_mess.__LINE__);
								if ( $results -> SelectedRowsCount() <= 0 ){
									$objUtils -> addAltAn( $modArticle['mod'], $modArticle['raw'] );
								}
								$article = $modArticle['mod'];
							}

							//ищем правильный артикул, если введен
							if($art = $objUtils->getArtnumber($article))
								$article = $art;

							$k = md5($article);
							if(!$arResult[$k]){
								$arResult[$k] = array(
									"article" 		=> $article,
									"brand_id" 		=> $brand_id,
									"brand" 		=> $brand_name,
									"supplier_id" 	=> $supplier["id"],
									"price" 		=> $price,
									"count" 		=> $count,
									"multiplicity" 		=> $multiplicity,
									"sale" 			=> $sale,
								);
							}else{
								if($arResult[$k]["price"] < $price) $price = $arResult[$k]["price"];
								$arResult[$k] = array(
									"article" 		=> $article,
									"brand_id" 		=> $brand_id,
									"brand" 		=> $brand_name,
									"supplier_id" 	=> $supplier["id"],
									"price" 		=> $price,
									"count" 		=> $count,
									"multiplicity" 		=> $multiplicity,
									"sale" 			=> $sale,
								);
							}
						}else{
							if($supplier["id"] == 66){

								$article = preg_replace('~[^-a-zA-Z0-9_\s,\/()]+~', '', $article);
								$article = trim(str_replace(array(" / ", " , ", ", ", "()", "( )", "  ", "(-)", "))", " -"), array("/", ",", ",", "", "", " ", "", ")", ""), $article));

								preg_match("/\([A-Za-z0-9]+\)/i", $article, $matches);
								$matches = array_diff($matches, array(''));
								$matches = array_unique($matches);
								$arArt = array();
								if(count_($matches) == 1 && strlen($matches[0]) > 0){
									$tmp = str_replace($matches[0], "", $article);
									$article = str_replace(array("(",")"), "", $matches[0]);
									if($ar = explode(",", $tmp)){
										foreach($ar as $k => $v){
											if($ar2 = explode("/", $v)){
												foreach($ar2 as $k2 => $v2){
													if($ar3 = explode(" ", $v2)){
														foreach($ar3 as $k3 => $v4){
															$arArt[] = $v4;
														}
													}else{
														$arArt[] = $v2;
													}
												}
											}else{
												$arArt[] = $v;
											}
										}
									}elseif($ar = explode("/", $tmp)){
										foreach($ar as $k => $v){
											$arArt[] = $v;
										}
									}
									$arArt = array_diff($arArt, array(''));
									$arArt = array_unique($arArt);
								}

								//if($article == "71604416"){AddMessage2Log($arArt);}

								if(count_($arArt) > 0 && strlen($article) > 0){
									foreach($arArt as $k => $v){
										if($art = $objUtils->getArtnumber($v . " ({$article})"))
											$article2 = $art;// . " ({$article})";
										else
											$article2 = $v . " ({$article})";

										$k = md5($article2);
										if(!$arResult[$k]){
											$arResult[$k] = array(
												"article" 		=> $article2,
												"brand_id" 		=> $brand_id,
												"brand" 		=> $brand_name,
												"supplier_id" 	=> $supplier["id"],
												"price" 		=> $price,
												"count" 		=> $count,
												"multiplicity" 		=> $multiplicity,
												"sale" 			=> $sale,
											);
										}else{
											if($arResult[$k]["price"] < $price) $price = $arResult[$k]["price"];
											$arResult[$k] = array(
												"article" 		=> $article2,
												"brand_id" 		=> $brand_id,
												"brand" 		=> $brand_name,
												"supplier_id" 	=> $supplier["id"],
												"price" 		=> $price,
												"count" 		=> $count,
												"multiplicity" 		=> $multiplicity,
												"sale" 			=> $sale,
											);
										}
									}
								}
							}

							//ищем правильный артикул, если введен
							if($art = $objUtils->getArtnumber($article))
								$article = $art;
							//AddMessage2Log($article);
						}

						//смотрим заполнено ли в бренде поля Поиск и замену по регулярному выражению
						if($arRegularReplace[$brand_id]){
							$_article = preg_replace($arRegularReplace[$brand_id]["pattern"], $arRegularReplace[$brand_id]["replacement"], $article);
							if(strlen($_article) > 0 && $_article != null){
								$article = $_article;
							}

						}
					}

				}

				unset($row);
				if(!$arResult || count($arResult) == 0) return array("true" => 0);

				foreach($arResult as $key => &$arItem){
					$arItem["currency"] = $arParams["CURRENCY"];
					$arItem["priceСurrency"] = $arItem["price"];

					$arItem["price"] = $arItem["price"] * $rate;
					if($arItem["sale"] > -100 && $arItem["sale"] < 100){
						$arItem["price"] = $arItem["price"] * ( 100 - $arItem["sale"] ) / 100;
						$arItem["priceСurrency"] = $arItem["priceСurrency"] * ( 100 - $arItem["sale"] ) / 100;
					}
					// if($margin > 0){
					// 	//$arItem["price"] = $arItem["price"] + $arItem["price"] * $margin / 100;
					// 	$arItem["price"] = $arItem["price"] / ((100 - $margin) / 100);
					// 	$arItem["priceСurrency"] = $arItem["priceСurrency"] + $arItem["priceСurrency"] * $margin / 100;
					// }
					if (strpos($margin, '/') === false) {
						if($margin > 0){
							//$arItem["price"] = $arItem["price"] + $arItem["price"] * $margin / 100;
							$arItem["price"] = $arItem["price"] / ((100 - $margin) / 100);
							$arItem["priceСurrency"] = $arItem["priceСurrency"] + $arItem["priceСurrency"] * $margin / 100;
						}
					} else {
						$ip = explode('/', $margin);
						$margin = $ip[0] / $ip[1];
						$arItem["price"] = $arItem["price"] / ((100 - $margin) / 100);
						$arItem["priceСurrency"] = $arItem["priceСurrency"] + $arItem["priceСurrency"] * $margin / 100;
					}


					//костыль для AUDIO ORIENT
					if($arItem["supplier_id"] == 66 && $arItem["brand_id"] == 2){
						//$arItem["price"] = $arItem["price"] * 72;
					}
					$arItem["price"] = round($arItem["price"], 4);
					$arItem["priceСurrency"] = round($arItem["priceСurrency"], 4);

				}

				unset($arItem);
				unset($ar);
				//получаем товары которые были (для отчета)
				$arHistory = array();
				$tmpBrand = array();
				foreach($arBrand as $key => $arItem){
					$tmp = $this->getPriceByFilter(array("supplier_id" => $supplier["id"], "brand_id" => $arItem["id"]));
					$arHistory = array_merge($arHistory, $tmp);
				}

				//обновляем данные в базе
				$form["currency"] = $arParams["CURRENCY"];

				file_put_contents("/var/www/bitrix/data/www/tempusshop.ru/bitrix/modules/panel.manager/classes/models/pricePrimeta.txt", print_r($arResult, true));

				$res = $this->add($supplier["id"],$supplier["name"], $arBrand, $arResult, $form);

				//ставим активность
				$this->changeActivityAll($supplier);
				$this->changeActivityUnused();
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
			echo $E->getLine();
			echo $E->getTrace();
			// var_dump($E);
			// die;
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

					$price_diff = (($arHistory[$model]["price"] - $arAdd[$model]["price"]) / $arHistory[$model]["price"]) * 100;
					$price_diff = abs($price_diff);

					$style = "";
					if($price_diff > 20){
						$style = "font-size: 15px;color:red;";
					}

					$html .= "<p class='label def' style='{$style}'>У модели - " . $model . " изменилась цена с " . $arHistory[$model]["price"] . " на " . $arAdd[$model]["price"] . "</p>";
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
		if(count_(self::$badColumn)){
			foreach(self::$badColumn as $k => $v){
				$html .= "<p class='label label-danger'>" . $v . "</p>";
			}
		}
		/*
		if(count_($arHistory) > count($arAdd)){
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

	public static function addItemsProductIdDiff($productIds = []){
		global $DB;
		if (!$productIds) return;
		foreach($productIds as $id){
			$in = array(
				"product_id" => "'".addslashes($id)."'"
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
	public static function getDateUpdate($article){
		global $DB;
		if(strlen($article) <= 0) return;
		$strSql = "SELECT DATE_RECEIPT, DATE_DISAPPEAR FROM ci_items_date WHERE CODE = '".addslashes($article)."' LIMIT 1";
		$results = $DB->Query($strSql, false, $err_mess.__LINE__);
		if ($row = $results->Fetch()){
			return $row;
		}
		return false;
	}

	public static function checkIfExpressOZ($supplierId, $typeStore, $price)
	{
		$suppliers = [103];
		if ( !in_array($supplierId, $suppliers) ){
			return $typeStore;
		}
		$filteredTypes = [];
		$priceFilter = 10000;
		if ( $price < $priceFilter ){
			foreach ( $typeStore as $key => $type){
				if ( $type == 'Express 7D' ) continue;
				$filteredTypes[] = $type;
			}
			return $filteredTypes;
		}
		return $typeStore;
	}

	public static function checkIfExpressYA( $typeStore )
	{
		foreach ( $typeStore as $key => $type ){
			if ( $type == 'Express 7D' ) return true;
		}
		return false;
	}

	public static function updateDateDelivery_old(){
		global $DB;

		$arFilter = Array(
		  "IBLOCK_ID" => 16,
		  "PROPERTY_EX_YA_VALUE" => 'Да'
		);
		$arSelect = ['IBLOCK_ID', 'ID', 'PROPERTY_EX_YA'];
		$result = CIBlockElement::GetList(array(), $arFilter, false, false, $arSelect);
		while( $art = $result->GetNext() ){
		  CIBlockElement::SetPropertyValuesEx($art['ID'], 16, array('EX_YA' => 2085));
		}

		//return false;
		$arArticle = array();
		$modelsForExpress = self::setWarehouseIfPriceAllows();
		//добавляем код элемента. чтоб потом по нему выбирать для кеша catalog.element
		$strSql = "SELECT el.ID as ID, el.IBLOCK_ID as IBLOCK_ID, el.CODE as CODE, pr.PROPERTY_123 as ARTICLE FROM b_iblock_element el
		LEFT JOIN
		b_iblock_element_prop_s16 pr ON el.ID=pr.IBLOCK_ELEMENT_ID WHERE el.ACTIVE = 'Y'
		AND ((el.IBLOCK_ID = '16' AND pr.PROPERTY_123 <> ''))";

		$results = $DB->Query($strSql, false, $err_mess.__LINE__);
		while ($row = $results->Fetch()){
			//$arArticle[$row["ARTICLE"]] = $row;
			$arArticle[$row["ID"]] = $row;
		}

		$arSupp = $arWorking = array();

		$strSql = "SELECT id, settings_pricelist, settings_type_sklad FROM ci_suppliers";
		$results = $DB->Query($strSql, false, $err_mess.__LINE__);
		while ($row = $results->Fetch()){
			$tmp = json_decode($row["settings_pricelist"], true);
			$tmpTypes = json_decode($row["settings_type_sklad"], true);
			$arSupp["s1"][$row["id"]] = $tmp["day_delivery"];
			$arSupp["s2"][$row["id"]] = $tmp["day_delivery_by"];
			$arSupp["s3"][$row["id"]] = $tmp["day_delivery_pl"];
			$arTypes[$row["id"]] = $tmpTypes;
			$arWorking[$row["id"]] = $tmp["working_time"];
		}

		$arPrice = array();

		$arMin = array();
		$strSql = "SELECT model, supplier_id, price, bitrix_id FROM ci_price WHERE active_ru='Y' ORDER BY price ASC";// GROUP BY model";MIN(price) as
		$results = $DB->Query($strSql, false, $err_mess.__LINE__);
		while ($row = $results->Fetch()){
			if(isset($arSupp["s1"][$row["supplier_id"]])){
				if(empty($arMin[$row["model"]])) $arMin[$row["model"]] = $row["price"];
				// if(empty($arPrice["s1"][$row["model"]]) || ($arSupp["s1"][$row["supplier_id"]] < $arPrice["s1"][$row["model"]]["day_delivery"] && (($row["price"] - $arMin[$row["model"]]) / $row["price"] * 100) < 5)){

				if(empty($arPrice["s1"][$row["model"]]) || (isset($arTypes[$row["supplier_id"]]) &&  $row["price"] == $arMin[$row["model"]])) {

					$arReType[$row["model"]] = $arTypes[$row["supplier_id"]];
					$row['type_sklad'] = $arTypes[$row["supplier_id"]];
					//ограничение по цене для экспресса озон
					// $arReType[$row["model"]] = self::checkIfExpressOZ( intval($row["supplier_id"]), $arTypes[$row["supplier_id"]], $row['price'] );
					// $row['type_sklad'] = self::checkIfExpressOZ( intval($row["supplier_id"]), $arTypes[$row["supplier_id"]], $row['price'] );
					$row["day_delivery"] = $arSupp["s1"][$row["supplier_id"]];
					$row["working_time"] = $arWorking[$row["supplier_id"]];
					$arPrice["s1"][$row["model"]] = $row;
					//ограничение по цене для экспресса яндекс
					if ( self::checkIfExpressYA( $arTypes[$row["supplier_id"]] ) ){
						CIBlockElement::SetPropertyValuesEx( $row["bitrix_id"], 16, array('EX_YA' => 2084) );
					}
					// if ( !in_array($arItem["bitrix_id"], $excludeIds) ){
					// 	$excludeIds[] = $row['bitrix_id'];
					// }
				}
			}
		}
		//file_put_contents("/var/www/bitrix/data/www/tempusshop.ru/bitrix/modules/panel.manager/classes/models/prices_10.txt", print_r('prce:', true) . "\r\n", FILE_APPEND);
		//file_put_contents("/var/www/bitrix/data/www/tempusshop.ru/bitrix/modules/panel.manager/classes/models/prices_10.txt", print_r($arPrice["s1"], true) . "\r\n", FILE_APPEND);
		//file_put_contents("/var/www/bitrix/data/www/tempusshop.ru/bitrix/modules/panel.manager/classes/models/prices_10.txt", print_r('min:', true) . "\r\n", FILE_APPEND);
		//file_put_contents("/var/www/bitrix/data/www/tempusshop.ru/bitrix/modules/panel.manager/classes/models/prices_10.txt", print_r($arMin, true) . "\r\n", FILE_APPEND);
		//file_put_contents("/var/www/bitrix/data/www/tempusshop.ru/bitrix/modules/panel.manager/classes/models/prices_10.txt", print_r('supp:', true) . "\r\n", FILE_APPEND);
		//file_put_contents("/var/www/bitrix/data/www/tempusshop.ru/bitrix/modules/panel.manager/classes/models/prices_10.txt", print_r($arSupp, true) . "\r\n", FILE_APPEND);
		$arMin = array();
		$strSql = "SELECT model, supplier_id, price FROM ci_price WHERE active_by='Y' ORDER BY price ASC";// GROUP BY model";MIN(price) as
		$results = $DB->Query($strSql, false, $err_mess.__LINE__);
		while ($row = $results->Fetch()){
			if(isset($arSupp["s2"][$row["supplier_id"]])){
				if(empty($arMin[$row["model"]])) $arMin[$row["model"]] = $row["price"];
				if(empty($arPrice["s2"][$row["model"]]) || ($arSupp["s2"][$row["supplier_id"]] < $arPrice["s2"][$row["model"]]["day_delivery"])) {
					$row["day_delivery"] = $arSupp["s2"][$row["supplier_id"]];
					$row["working_time"] = $arWorking[$row["supplier_id"]];
					$arPrice["s2"][$row["model"]] = $row;
				}
			}
		}

		$arMin = array();
		$strSql = "SELECT model, supplier_id, price FROM ci_price WHERE active_pl='Y' ORDER BY price ASC";// GROUP BY model";MIN(price) as
		$results = $DB->Query($strSql, false, $err_mess.__LINE__);
		while ($row = $results->Fetch()){
			if(isset($arSupp["s3"][$row["supplier_id"]])){
				if(empty($arMin[$row["model"]])) $arMin[$row["model"]] = $row["price"];
				if(empty($arPrice["s3"][$row["model"]]) || ($arSupp["s3"][$row["supplier_id"]] < $arPrice["s3"][$row["model"]]["day_delivery"])) {
					$row["day_delivery"] = $arSupp["s3"][$row["supplier_id"]];
					$row["working_time"] = $arWorking[$row["supplier_id"]];
					$arPrice["s3"][$row["model"]] = $row;
				}
			}
		}

		/* Товарам, у которых есть конкурент Generalwatches установить срок доставки 50. */
		$arDelivery50 = array();
		$strSql = "SELECT bitrix_id FROM ci_yandex_price WHERE info='Generalwatches'";
		$results = $DB->Query($strSql, false, $err_mess.__LINE__);
		while ($row = $results->Fetch()){
			$arDelivery50[$row["bitrix_id"]] = true;
		}

		$arItems = array();
		//foreach($arArticle as $code => $arItem){
		foreach($arArticle as $id => $arItem){
			$tmp = array();


			if($arPrice["s1"][$arItem["ARTICLE"]]){
				$tmp["bitrix_id"] = $arItem["ID"];
				$tmp["bitrix_code"] = $arItem["CODE"];
				$tmp["model"] = $arItem["ARTICLE"];
				$tmp["is_sku"] = ($arItem["IBLOCK_ID"] == 17 ? "Y" : "N");
				$tmp["site_id"] = "s1";
				if ($arPrice["s1"][$arItem["ARTICLE"]]["type_sklad"]) {
					$tmp['type_sklad'] = $arPrice["s1"][$arItem["ARTICLE"]]["type_sklad"];
					//file_put_contents("/var/www/bitrix/data/www/tempusshop.ru/bitrix/modules/panel.manager/classes/models/price.txt", print_r($arItem["ARTICLE"], true) . "\r\n", FILE_APPEND);
					//file_put_contents("/var/www/bitrix/data/www/tempusshop.ru/bitrix/modules/panel.manager/classes/models/price.txt", print_r($tmp['type_sklad'], true) . "\r\n", FILE_APPEND);
				}

				//$tmp["day_delivery"] = $arSupp["s1"][$arPrice["s1"][$arItem["ARTICLE"]]["supplier_id"]];
				$tmp["day_delivery"] = $arPrice["s1"][$arItem["ARTICLE"]]["day_delivery"];
				$tmp["working_time"] = $arPrice["s1"][$arItem["ARTICLE"]]["working_time"];

				$tmp["supplier_id"] = $arPrice["s1"][$arItem["ARTICLE"]]["supplier_id"];
				//prent($tmp);die;
				$arItems[] = $tmp;
			}

			$tmp = array();
			if($arPrice["s2"][$arItem["ARTICLE"]]){
				$tmp["bitrix_id"] = $arItem["ID"];
				$tmp["bitrix_code"] = $arItem["CODE"];
				$tmp["model"] = $arItem["ARTICLE"];
				$tmp["is_sku"] = ($arItem["IBLOCK_ID"] == 17 ? "Y" : "N");
				$tmp["site_id"] = "s2";

				//$tmp["day_delivery"] = $arSupp["s2"][$arPrice["s2"][$arItem["ARTICLE"]]["supplier_id"]];
				$tmp["day_delivery"] = $arPrice["s2"][$arItem["ARTICLE"]]["day_delivery"];
				$tmp["working_time"] = $arPrice["s2"][$arItem["ARTICLE"]]["working_time"];

				$tmp["supplier_id"] = $arPrice["s2"][$arItem["ARTICLE"]]["supplier_id"];

				$arItems[] = $tmp;
			}

			$tmp = array();
			if($arPrice["s3"][$arItem["ARTICLE"]]){
				$tmp["bitrix_id"] = $arItem["ID"];
				$tmp["bitrix_code"] = $arItem["CODE"];
				$tmp["model"] = $arItem["ARTICLE"];
				$tmp["is_sku"] = ($arItem["IBLOCK_ID"] == 17 ? "Y" : "N");
				$tmp["site_id"] = "s3";

				//$tmp["day_delivery"] = $arSupp["s3"][$arPrice["s3"][$arItem["ARTICLE"]]["supplier_id"]];
				$tmp["day_delivery"] = $arPrice["s3"][$arItem["ARTICLE"]]["day_delivery"];
				$tmp["working_time"] = $arPrice["s3"][$arItem["ARTICLE"]]["working_time"];

				$tmp["supplier_id"] = $arPrice["s3"][$arItem["ARTICLE"]]["supplier_id"];

				$arItems[] = $tmp;
			}
		}
		$DB->Query("TRUNCATE TABLE ci_model_delivery", false, $err_mess.__LINE__);
		foreach($arItems as $key => $arItem){
			$in = array(
				"bitrix_id" => $arItem["bitrix_id"],
				"bitrix_code" => "'".addslashes($arItem["bitrix_code"])."'",
				"model" => "'".addslashes($arItem["model"])."'",
				"is_sku" => "'".$arItem["is_sku"]."'",
				"site_id" => "'".$arItem["site_id"]."'",
				"supplier_id" => "'".$arItem["supplier_id"]."'",
				"day_delivery" => $arItem["day_delivery"],
				"working_time" => $arItem["working_time"],
			);
			$DB->Insert("ci_model_delivery", $in, $err_mess.__LINE__);

			if($arItem["site_id"] == "s1"){

				if($arDelivery50[$arItem["bitrix_id"]]){
					$day_delivery = 50;
				}else{
					$day_delivery = $arItem["day_delivery"];
				}

				CIBlockElement::SetPropertyValuesEx($arItem["bitrix_id"], CProSet::IB_CATALOG, array("DELIVERY_DAY_RU" => $day_delivery));
				if (!empty($arItem['type_sklad'])) {
					if ( in_array( $arItem['model'], $modelsForExpress ) && !in_array('Express 7D', $arItem['type_sklad']) ){
						$arItem['type_sklad'][] = "Express 7D";
					}
					foreach ($arItem['type_sklad'] as $key => $value) {
						$arValues[] = ['VALUE' => $value];
					}
					//file_put_contents("/var/www/bitrix/data/www/tempusshop.ru/bitrix/modules/panel.manager/classes/models/price.txt", print_r('values', true) . "\r\n", FILE_APPEND);
					//file_put_contents("/var/www/bitrix/data/www/tempusshop.ru/bitrix/modules/panel.manager/classes/models/price.txt", print_r($arValues, true) . "\r\n", FILE_APPEND);
					CIBlockElement::SetPropertyValueCode($arItem["bitrix_id"], 'TYPEOFSKLAD', $arValues);
					unset($arValues);
				}

			}
		}
		CLog::add2log(array("event" => "DD", "text" => "Проанализировано - " . count($arItems)));

	}

	public static function updateDateDelivery($arIDs = []){
		global $DB;

		$transitDaysRU = json_decode(CProSet::getOption("TRANSIT_DAYS_RU"), true);
		$transitDaysBY = json_decode(CProSet::getOption("TRANSIT_DAYS_BY"), true);

		$arReserved = self::getReserveItems();
		$arUpdate = [];
		//$curDay = date("w"); // номер дня недели
		$arBx = array();
		$modelsForExpress = self::setWarehouseIfPriceAllows();

		$arFilter = [
			"IBLOCK_ID" => 16,
			"!PROPERTY_123" => false,
		];

		if (is_array($arIDs) && count($arIDs) > 0) {
			$arFilter["ID"] = $arIDs;
		}

		$arSelect = [
			"ID", "CODE", "PROPERTY_123", "PROPERTY_3047", "PROPERTY_2813", "PROPERTY_2843", "PROPERTY_3110",

		];
		$rs = CIBlockElement::GetList([], $arFilter, false, false, $arSelect);

		while($ar = $rs->GetNext()){
			$arBx[$ar["ID"]] = [
				"ID" => $ar["ID"],
				"CODE" => $ar["CODE"],
				"ARTICLE" => $ar["PROPERTY_123_VALUE"],
				"EX_YA" => $ar["PROPERTY_3047_ENUM_ID"],
				"DELIVERY_DAY_RU" => $ar["PROPERTY_2813_VALUE"],
				//"TYPEOFSKLAD" => $ar["PROPERTY_2843_VALUE"],
				"DELIVERY_JSON" => false,
			];
			//TYPEOFSKLAD приводим к виду как сохранять собираемся
			$tmp = [];
			foreach($ar["PROPERTY_2843_VALUE"] as $k => $v){
				$tmp[$k] = ["VALUE" => $v];
			}
			usort($tmp, function($a, $b) {
				return strcmp($a['VALUE'], $b['VALUE']);
			});

			$arBx[$ar["ID"]]["TYPEOFSKLAD"] = $tmp;

			if($ar["PROPERTY_3110_VALUE"]){
				$jsonData = stripslashes(html_entity_decode($ar["PROPERTY_3110_VALUE"]));
				$arBx[$ar["ID"]]["DELIVERY_JSON"] = json_decode($jsonData, true);
			}

		}

		if (!$arBx) return;

		$arSupplier = array();

		$strSql = "SELECT id, active_ru, active_by, settings, settings_pricelist, settings_type_sklad FROM ci_suppliers";
		$results = $DB->Query($strSql, false, $err_mess.__LINE__);
		while ($row = $results->Fetch()){
			$tmp = json_decode($row["settings_pricelist"], true);
			$working_week = ($tmp["working_week"] ? $tmp["working_week"] : []);
			if ($row["active_ru"] == "Y") {
				$deniedBrands = [];
				foreach (json_decode($row["settings"], true)["brand"] as $v) {
					if ($v["active_ru"] == "N") {
						$deniedBrands[] = $v["id"];
					}
				}
				$arSupplier["s1"][$row["id"]] = [
					"day_delivery" => $tmp["day_delivery"],
					"type_sklad" => json_decode($row["settings_type_sklad"], true),
					"working_week" => $working_week,
					"working_time" => $tmp["working_time"],
					"location" => $tmp["location"],
					"denied_brands" => $deniedBrands,
				];
			}

			if ($row["active_by"] == "Y") {
				$deniedBrands = [];
				foreach (json_decode($row["settings"], true)["brand"] as $v) {
					if ($v["active_by"] == "N") {
						$deniedBrands[] = $v["id"];
					}
				}
				$arSupplier["s2"][$row["id"]] = [
					"day_delivery" => $tmp["day_delivery"],
					"type_sklad" => json_decode($row["settings_type_sklad"], true),
					"working_week" => $working_week,
					"working_time" => $tmp["working_time"],
					"location" => $tmp["location"],
					"denied_brands" => $deniedBrands,
				];
			}

		}

		$arPrice = array();
		$arCheck = [];

		$arArticle = array_column($arBx, "ARTICLE");

		$strSql = "SELECT
			active_ru, active_by, model, count, supplier_id, price, bitrix_id
		FROM ci_price
		WHERE
			(active_ru='Y' OR active_by='Y') AND model IN ('".implode("','", $arArticle)."')
		ORDER BY price ASC";

		$results = $DB->Query($strSql, false, $err_mess.__LINE__);
		while ($row = $results->Fetch()){
			$check = true;
			//arReserved
			//if (isset($arCheck[$row["bitrix_id"]]) && isset($arCheck[$row["bitrix_id"]]["s1"])) {
			//	$check = $arCheck[$row["bitrix_id"]]["s1"];
			//}

			if ($row["active_ru"] == "Y") {
				if (isset($arReserved[$row["bitrix_id"]]) && isset($arReserved[$row["bitrix_id"]]["s1"])) {
					if($arReserved[$row["bitrix_id"]]["s1"] >= $row["count"]){
						$can_buy = false;
						$arReserved[$row["bitrix_id"]]["s1"] -= $row["count"];
						$row["count"] = 0;
					}else{
						$can_buy = true;
					}
				} else {
					$can_buy = true;
				}
			}

			if(
				$row["active_ru"] == "Y" && $row["count"] > 0 &&
				isset($arSupplier["s1"][$row["supplier_id"]]) &&
				$can_buy
			){
				$supplier = $arSupplier["s1"][$row["supplier_id"]];
				//$curDay
				//if(empty($arMin[$row["model"]])) $arMin[$row["model"]] = $row["price"];
				//if(empty($arPrice["s1"][$row["model"]]) || (isset($supplier["type_sklad"]) && $row["price"] == $arMin[$row["model"]])) {
				if ($arPrice["s1"][$row["model"]]) {
					$diff = ($row['price'] - $arPrice["s1"][$row["model"]]["price"]) / $arPrice["s1"][$row["model"]]["price"] * 100;
				} else {
					$diff = false;
				}

				if(empty($arPrice["s1"][$row["model"]]) || ($supplier["day_delivery"] < $arPrice["s1"][$row["model"]]["day_delivery"] && $diff && $diff < 5)) {
					$row['type_sklad'] = $supplier["type_sklad"];
					$row["day_delivery"] = $supplier["day_delivery"];
					$row["working_week"] = $supplier["working_week"];
					$row["working_time"] = $supplier["working_time"];
					$row["location"] = $supplier["location"];

					$skip = false;
					if ($arPrice["s1"][$row["model"]]) {
						// если записан склады москвы, то пропускаем перезапись
						if (in_array($arPrice["s2"][$row["model"]]["supplier_id"], [47, 129, 141, 128])) {
							if ($arPrice["s2"][$row["model"]]["day_delivery"] < $row["day_delivery"]) {
								$skip = true;
							}
						}
					}
					if (!$skip)
						$arPrice["s1"][$row["model"]] = $row;
				}
			}

			//$check = true;
			//if (isset($arCheck[$row["bitrix_id"]]) && isset($arCheck[$row["bitrix_id"]]["s2"]))
			//	$check = $arCheck[$row["bitrix_id"]]["s2"];
			if ($row["active_by"] == "Y") {
				if (isset($arReserved[$row["bitrix_id"]]) && isset($arReserved[$row["bitrix_id"]]["s2"])) {
					if($arReserved[$row["bitrix_id"]]["s2"] >= $row["count"]){
						$can_buy = false;
						$arReserved[$row["bitrix_id"]]["s2"] -= $row["count"];
						$row["count"] = 0;
					}else{
						$can_buy = true;
					}
				} else {
					$can_buy = true;
				}
			}

			if(
				$row["active_by"] == "Y" && $row["count"] > 0 &&
				isset($arSupplier["s2"][$row["supplier_id"]]) &&
				$can_buy
			){
				$supplier = $arSupplier["s2"][$row["supplier_id"]];

				if ($arPrice["s2"][$row["model"]]) {
					$diff = ($row['price'] - $arPrice["s2"][$row["model"]]["price"]) / $arPrice["s2"][$row["model"]]["price"] * 100;
				} else {
					$diff = false;
				}

				if(empty($arPrice["s2"][$row["model"]]) ||
					($supplier["day_delivery"] < $arPrice["s2"][$row["model"]]["day_delivery"] && $diff && $diff < 5) ||
					$row["supplier_id"] == 149
				) {

					$row["day_delivery"] = $supplier["day_delivery"];
					$row["working_week"] = $supplier["working_week"];
					$row["working_time"] = $supplier["working_time"];
					$row["location"] = $supplier["location"];

					$skip = false;
					if ($arPrice["s2"][$row["model"]]) {
						// если записан склад минск, то пропускаем перезапись
						if ($arPrice["s2"][$row["model"]]["supplier_id"] == 149) {
							$skip = true;
						}
					}
					//prent($arPrice["s2"][$row["model"]]);
					if (!$skip) {
						$arPrice["s2"][$row["model"]] = $row;
					}
				}
			}
		}
		//prent($arPrice);
		/* Товарам, у которых есть конкурент Generalwatches установить срок доставки 50. */
		$arDelivery50 = array();
		$strSql = "SELECT bitrix_id FROM ci_yandex_price WHERE info='Generalwatches'";
		$results = $DB->Query($strSql, false, $err_mess.__LINE__);
		while ($row = $results->Fetch()){
			$arDelivery50[$row["bitrix_id"]] = true;
		}
		//prent($arPrice);
		$arItems = array();
		foreach($arBx as $id => $arItem){
			$arDelivery = [
				"s1" => false,
				"s2" => false,
			];
			if($arPrice["s1"][$arItem["ARTICLE"]]){
				$price = $arPrice["s1"][$arItem["ARTICLE"]];
				$type_sklad = ($price["type_sklad"] ? $price["type_sklad"] : false);
				$day_delivery = $price["day_delivery"];
				$working_week = $price["working_week"];
				$working_time = $price["working_time"];

				/*$arItems[] = [
					"bitrix_id" => $arItem["ID"],
					"bitrix_code" => $arItem["CODE"],
					"model" => $arItem["ARTICLE"],
					"is_sku" => ($arItem["IBLOCK_ID"] == 17 ? "Y" : "N"),
					"site_id" => "s1",
					"day_delivery" => $day_delivery,
					"working_week" => $working_week,
					"working_time" => $working_time,
					"supplier_id" => $price["supplier_id"],
				];*/

				$arDelivery["s1"] = [
					"day_delivery" => $day_delivery,
					"working_week" => $working_week,
					"working_time" => $working_time,
					"supplier_id" => $price["supplier_id"],
					"location" => $price["location"],
					"transit_days" => ($price["location"] == "minsk" ? $transitDaysBY : []),
				];

				if($arDelivery50[$arItem["ID"]]){
					$day_delivery = 50;
				}

				if($arItem["DELIVERY_DAY_RU"] != $day_delivery){
					$arUpdate[$arItem["ID"]]["DELIVERY_DAY_RU"] = $day_delivery;
				}

				if (is_array($type_sklad)) {

					if ( in_array( $arItem['ARTICLE'], $modelsForExpress ) && !in_array('Express 7D', $type_sklad) ){
						$type_sklad[] = "Express 7D";
					}
					$arValues = [];
					foreach ($type_sklad as $key => $value) {
						$arValues[] = ['VALUE' => $value];
					}

					usort($arValues, function($a, $b) {
						return strcmp($a['VALUE'], $b['VALUE']);
					});

					if(md5(serialize($arValues)) != md5(serialize($arItem["TYPEOFSKLAD"])))
						$arUpdate[$arItem["ID"]]["TYPEOFSKLAD"] = $arValues;
				}else{
					if($arItem["TYPEOFSKLAD"]){
						$arUpdate[$arItem["ID"]]["TYPEOFSKLAD"] = false;
					}
				}

				//ограничение по цене для экспресса яндекс
				if (self::checkIfExpressYA($price["type_sklad"])){
					if($arItem["EX_YA"] != 2084){
						$arUpdate[$arItem["ID"]]["EX_YA"] = 2084;
					}
				}else{
					if($arItem["EX_YA"] != 2085){
						$arUpdate[$arItem["ID"]]["EX_YA"] = 2085;
					}
				}
			}else{
				if($arItem["TYPEOFSKLAD"]){
					$arUpdate[$arItem["ID"]]["TYPEOFSKLAD"] = false;
				}
			}

			if($arPrice["s2"][$arItem["ARTICLE"]]){
				$price = $arPrice["s2"][$arItem["ARTICLE"]];
				$day_delivery = $price["day_delivery"];
				$working_week = $price["working_week"];
				$working_time = $price["working_time"];
				/*$arItems[] = [
					"bitrix_id" => $arItem["ID"],
					"bitrix_code" => $arItem["CODE"],
					"model" => $arItem["ARTICLE"],
					"is_sku" => ($arItem["IBLOCK_ID"] == 17 ? "Y" : "N"),
					"site_id" => "s2",
					"day_delivery" => $day_delivery,
					"working_week" => $working_week,
					"working_time" => $working_time,
					"supplier_id" => $price["supplier_id"],
				];*/
				$arDelivery["s2"] = [
					"day_delivery" => $day_delivery,
					"working_week" => $working_week,
					"working_time" => $working_time,
					"supplier_id" => $price["supplier_id"],
					"location" => $price["location"],
					"transit_days" => ($price["location"] == "moscow" ? $transitDaysRU : []),
				];
			}

			if(!$arItem["DELIVERY_JSON"] || md5(json_encode($arItem["DELIVERY_JSON"])) != md5(json_encode($arDelivery)))
				$arUpdate[$arItem["ID"]]["DELIVERY_JSON"] = json_encode($arDelivery, JSON_UNESCAPED_SLASHES);
		}
		/*$DB->Query("TRUNCATE TABLE ci_model_delivery", false, $err_mess.__LINE__);

		foreach($arItems as $key => $arItem){
			$in = array(
				"bitrix_id" => $arItem["bitrix_id"],
				"bitrix_code" => "'".addslashes($arItem["bitrix_code"])."'",
				"model" => "'".addslashes($arItem["model"])."'",
				"is_sku" => "'".$arItem["is_sku"]."'",
				"site_id" => "'".$arItem["site_id"]."'",
				"supplier_id" => "'".$arItem["supplier_id"]."'",
				"day_delivery" => $arItem["day_delivery"],
				//"working_week" => "'".addslashes(serialize($arItem["working_week"]))."'",
				"working_time" => $arItem["working_time"],
			);
			$DB->Insert("ci_model_delivery", $in, $err_mess.__LINE__);

		}*/

		foreach($arUpdate as $elID => $arProp){
			CIBlockElement::SetPropertyValuesEx($elID, 16, $arProp);
		}

		/*if (count($arUpdate) > 0) {
			// собираем массив чтобы отправить в темпус
			require_once($_SERVER['DOCUMENT_ROOT'] . '/local/classes/SyncHelper.php');

			$syncHelper = new SyncHelper();
			$arData = [
				"ACTION" => "UPDATE_PROPS_ITEMS",
				"DATA" => [],
			];
			foreach ($arUpdate as $elID => $arProp) {
				if (!$arBx[$elID]["ARTICLE"]) continue;

				$arData["DATA"][] = [
					"ARTICLE" => $arBx[$elID]["ARTICLE"],
					"PROPERTIES" => $arProp,
				];

			}
			$syncHelper->sendCustom($arData);
		}*/
		require_once($_SERVER['DOCUMENT_ROOT'] . '/local/classes/SyncHelper.php');
		$syncHelper = new SyncHelper();
		$syncHelper->sendPrices($arIDs ?? []);

		$arBacktrace = \Bitrix\Main\Diag\Helper::getBackTrace(10, DEBUG_BACKTRACE_IGNORE_ARGS);
		file_put_contents("/var/www/bitrix_logs/tempus/rabbitmq/price/updateDateDelivery.txt", print_r([$arBacktrace], true), 8);

		CLog::add2log(array("event" => "DD", "text" => "Проанализировано - " . count($arItems)));
		//prent($arUpdate);
	}

	public static function updateProps($arIDs = []){
		global $DB;

		$transitDaysRU = json_decode(CProSet::getOption("TRANSIT_DAYS_RU"), true);
		$transitDaysBY = json_decode(CProSet::getOption("TRANSIT_DAYS_BY"), true);

		$arUpdate = [];

		$arBx = [];
		$modelsForExpress = self::setWarehouseIfPriceAllows();

		$arFilter = [
			"IBLOCK_ID" => 16,
			"!PROPERTY_123" => false,
		];

		if (is_array($arIDs) && count($arIDs) > 0) {
			$arFilter["ID"] = $arIDs;
		}

		$arSelect = [
			"ID", "CODE", "PROPERTY_123",
			"PROPERTY_3047", "PROPERTY_2813",
			"PROPERTY_2843", "PROPERTY_3110",
			"PROPERTY_267", "PROPERTY_282",
			"CATALOG_AVAILABLE", "CATALOG_QUANTITY",
		];
		$rs = CIBlockElement::GetList([], $arFilter, false, false, $arSelect);

		while($ar = $rs->GetNext()){
			$arBx[$ar["ID"]] = [
				"ID" => $ar["ID"],
				"CODE" => $ar["CODE"],
				"ARTICLE" => $ar["PROPERTY_123_VALUE"],
				"EX_YA" => $ar["PROPERTY_3047_ENUM_ID"],
				"DELIVERY_DAY_RU" => $ar["PROPERTY_2813_VALUE"],
				"AVAILABILITY_BY" => $ar["PROPERTY_267_ENUM_ID"],
				"AVAILABILITY_RU" => $ar["PROPERTY_282_ENUM_ID"],
				"DELIVERY_JSON" => false,
				"CATALOG_AVAILABLE" => $ar["CATALOG_AVAILABLE"],
				"CATALOG_QUANTITY" => $ar["CATALOG_QUANTITY"],
			];
			//TYPEOFSKLAD приводим к виду как сохранять собираемся
			$tmp = [];
			foreach($ar["PROPERTY_2843_VALUE"] as $k => $v){
				$tmp[$k] = ["VALUE" => $v];
			}
			usort($tmp, function($a, $b) {
				return strcmp($a['VALUE'], $b['VALUE']);
			});

			$arBx[$ar["ID"]]["TYPEOFSKLAD"] = $tmp;

			if($ar["PROPERTY_3110_VALUE"]){
				$jsonData = stripslashes(html_entity_decode($ar["PROPERTY_3110_VALUE"]));
				$arBx[$ar["ID"]]["DELIVERY_JSON"] = json_decode($jsonData, true);
			}

		}

		if (!$arBx) return;

		$suppliers = [];

		$strSql = "SELECT * FROM ci_suppliers";
		$results = $DB->Query($strSql, false, $err_mess.__LINE__);
		while ($row = $results->Fetch()){
			$tmp = json_decode($row["settings_pricelist"], true);
			$working_week = ($tmp["working_week"] ? $tmp["working_week"] : []);

			$suppliers[$row["id"]] = [
				"day_delivery" => $tmp["day_delivery"],
				"type_sklad" => json_decode($row["settings_type_sklad"], true),
				"working_week" => $working_week,
				"working_time" => $tmp["working_time"],
				"location" => $tmp["location"],
			];
		}

		$arArticle = array_column($arBx, "ARTICLE");

		$arPrice = [];

		if (is_array($arArticle) && count($arArticle) > 1000) {
			$arArticle = [];
		}
		//$arArticle = ['A-130WE-7A'];
		// получаем мин цены
		$arPrice['s1'] = self::getMinPrices('RU', $arArticle);
		$arPrice['s2'] = self::getMinPrices('BY', $arArticle);
//prent($arPrice);
		/*
		пока хардкод
		store_id 1 - немига, 2 - новокуз
		*/
		/* Товарам, у которых есть конкурент Generalwatches установить срок доставки 50. */
		$arDelivery50 = array();
		$strSql = "SELECT bitrix_id FROM ci_yandex_price WHERE info='Generalwatches'";
		$results = $DB->Query($strSql, false, $err_mess.__LINE__);
		while ($row = $results->Fetch()){
			$arDelivery50[$row["bitrix_id"]] = true;
		}
		$ar = [];
		$arItems = array();
		foreach($arBx as $id => $arItem){
			$arDelivery = [
				"s1" => false,
				"s2" => false,
			];
			$availability = [
				"s1" => [
					"in_store" => 0,
					"in_supplier" => 0,
				],
				"s2" => [
					"in_store" => 0,
					"in_supplier" => 0,
				],
			];
			if ($arPrice["s1"][$arItem["ARTICLE"]]) {
				$price = $arPrice["s1"][$arItem["ARTICLE"]][0];
				$supplier = $suppliers[$price["supplier_id"]] ?? false;

				$type_sklad = ($supplier["type_sklad"] ? $supplier["type_sklad"] : false);
				$day_delivery = $supplier["day_delivery"];
				$working_week = $supplier["working_week"];
				$working_time = $supplier["working_time"];

				$arDelivery["s1"] = [
					"day_delivery" => $day_delivery,
					"working_week" => $working_week,
					"working_time" => $working_time,
					"supplier_id" => $price["supplier_id"],
					"location" => $supplier["location"],
					"transit_days" => ($supplier["location"] == "minsk" ? $transitDaysBY : []),
				];

				if($arDelivery50[$arItem["ID"]]){
					$day_delivery = 50;
				}

				if($arItem["DELIVERY_DAY_RU"] != $day_delivery){
					$arUpdate[$arItem["ID"]]["DELIVERY_DAY_RU"] = $day_delivery;
				}

				if (is_array($type_sklad)) {

					if ( in_array( $arItem['ARTICLE'], $modelsForExpress ) && !in_array('Express 7D', $type_sklad) ){
						$type_sklad[] = "Express 7D";
					}
					$arValues = [];
					foreach ($type_sklad as $key => $value) {
						$arValues[] = ['VALUE' => $value];
					}

					usort($arValues, function($a, $b) {
						return strcmp($a['VALUE'], $b['VALUE']);
					});

					if(md5(serialize($arValues)) != md5(serialize($arItem["TYPEOFSKLAD"])))
						$arUpdate[$arItem["ID"]]["TYPEOFSKLAD"] = $arValues;
				}else{
					if($arItem["TYPEOFSKLAD"]){
						$arUpdate[$arItem["ID"]]["TYPEOFSKLAD"] = false;
					}
				}

				//ограничение по цене для экспресса яндекс
				if (self::checkIfExpressYA($supplier["type_sklad"])){
					if($arItem["EX_YA"] != 2084){
						$arUpdate[$arItem["ID"]]["EX_YA"] = 2084;
					}
				}else{
					if($arItem["EX_YA"] != 2085){
						$arUpdate[$arItem["ID"]]["EX_YA"] = 2085;
					}
				}

				foreach ($arPrice["s1"][$arItem["ARTICLE"]] as $item) {
					if ($item['store_id'] == 2) {
						$availability['s1']['in_store'] += $item['count'];
					} else {
						$availability['s1']['in_supplier'] += $item['count'];
					}
				}

				if ($availability['s1']['in_store']) {
					if ($arItem["AVAILABILITY_RU"] != 2126) {
						$arUpdate[$arItem["ID"]]["AVAILABILITY_RU"] = 2126;
					}
				} else if ($availability['s1']['in_supplier']) {
					if ($arItem["AVAILABILITY_RU"] != 512) {
						$arUpdate[$arItem["ID"]]["AVAILABILITY_RU"] = 512;
					}
				} else {
					if ($arItem["AVAILABILITY_RU"] != 514) {
						$arUpdate[$arItem["ID"]]["AVAILABILITY_RU"] = 514;
					}
				}

			} else {
				if($arItem["TYPEOFSKLAD"]){
					$arUpdate[$arItem["ID"]]["TYPEOFSKLAD"] = false;
				}
				if ($arItem["AVAILABILITY_RU"] != 514) {
					$arUpdate[$arItem["ID"]]["AVAILABILITY_RU"] = 514;
				}
			}

			if($arPrice["s2"][$arItem["ARTICLE"]]){
				$price = $arPrice["s2"][$arItem["ARTICLE"]][0];
				$supplier = $suppliers[$price["supplier_id"]] ?? false;

				$day_delivery = $supplier["day_delivery"];
				$working_week = $supplier["working_week"];
				$working_time = $supplier["working_time"];

				$arDelivery["s2"] = [
					"day_delivery" => $day_delivery,
					"working_week" => $working_week,
					"working_time" => $working_time,
					"supplier_id" => $price["supplier_id"],
					"location" => $supplier["location"],
					"transit_days" => ($supplier["location"] == "moscow" ? $transitDaysRU : []),
				];

				foreach ($arPrice["s2"][$arItem["ARTICLE"]] as $item) {
					if ($item['store_id'] == 1) {
						$availability['s2']['in_store'] += $item['count'];
					} else {
						$availability['s2']['in_supplier'] += $item['count'];
					}
				}

				if ($availability['s2']['in_store']) {
					if ($arItem["AVAILABILITY_BY"] != 492) {
						$arUpdate[$arItem["ID"]]["AVAILABILITY_BY"] = 492;
					}
				} else if ($availability['s2']['in_supplier']) {
					if ($arItem["AVAILABILITY_BY"] != 493) {
						$arUpdate[$arItem["ID"]]["AVAILABILITY_BY"] = 493;
					}
				} else {
					if ($arItem["AVAILABILITY_BY"] != 494) {
						$arUpdate[$arItem["ID"]]["AVAILABILITY_BY"] = 494;
					}
				}
			} else {
				if ($arItem["AVAILABILITY_BY"] != 494) {
					$arUpdate[$arItem["ID"]]["AVAILABILITY_BY"] = 494;
				}
			}

			if(!$arItem["DELIVERY_JSON"] || md5(json_encode($arItem["DELIVERY_JSON"])) != md5(json_encode($arDelivery))) {
				$arUpdate[$arItem["ID"]]["DELIVERY_JSON"] = json_encode($arDelivery, JSON_UNESCAPED_SLASHES);

				//prent($arItem["DELIVERY_JSON"]);prent($arDelivery);prent($arItem);
				//prent('-----------------');
				$ar['DELIVERY_JSON'][] = [
					'ARTICLE' => $arItem['ARTICLE'],
					'OLD' => $arItem["DELIVERY_JSON"],
					'NEW' => $arDelivery,
				];
			}

			if ($arUpdate[$arItem["ID"]]["AVAILABILITY_BY"] || $arUpdate[$arItem["ID"]]["AVAILABILITY_RU"]) {
				$ar['AVAILABILITY'][] = [
					'ARTICLE' => $arItem['ARTICLE'],
					'AVAILABILITY_BY' => $arUpdate[$arItem["ID"]]["AVAILABILITY_BY"],
					'AVAILABILITY_BY_OLD' => $arItem["AVAILABILITY_BY"],
					'AVAILABILITY_RU' => $arUpdate[$arItem["ID"]]["AVAILABILITY_RU"],
					'AVAILABILITY_RU_OLD' => $arItem["AVAILABILITY_RU"],
				];
			}
			
			$sumQuantity = array_sum($availability['s1']) + array_sum($availability['s2']);
			if($sumQuantity > 0) $avail = "Y"; else $avail = "N";
			
			if ($arItem["CATALOG_QUANTITY"] != $sumQuantity || $arItem["CATALOG_AVAILABLE"] != $avail) {
				$in = array(
					"QUANTITY"	=> $sumQuantity,
					"AVAILABLE"	=> "'".$avail."'",
				);
				$DB->Update("b_catalog_product", $in, "WHERE ID='".$arItem["ID"]."'", $err_mess.__LINE__);
			}

			
			//prent($sumQuantity);
				//"CATALOG_AVAILABLE" => $ar["CATALOG_AVAILABLE"],
				//"CATALOG_QUANTITY" => $ar["CATALOG_QUANTITY"],
		}
		
		if (count($arUpdate) > 1000) {
			$arBacktrace = \Bitrix\Main\Diag\Helper::getBackTrace(20, DEBUG_BACKTRACE_IGNORE_ARGS);
			file_put_contents("/var/www/bitrix/data/www/tempusshop.ru/dev/123321.txt", 
			print_r([
			$arBacktrace, $arPrice['s1'], $arUpdate[2935]
			], true) . "\r\n");

		}

		//prent($arUpdate);
		//return $ar;
		
		//$arBx[$ar["ID"]]["ARTICLE"]
		//prent($arUpdate);die;
		foreach($arUpdate as $elID => $arProp){
			CIBlockElement::SetPropertyValuesEx($elID, 16, $arProp);
		}

		require_once($_SERVER['DOCUMENT_ROOT'] . '/local/classes/SyncHelper.php');
		$syncHelper = new SyncHelper();
		$syncHelper->sendPrices($arIDs ?? []);

		CLog::add2log(array("event" => "DD", "text" => "Проанализировано - " . count($arBx) . ', изменено - ' . count($arUpdate)));
		//prent($arUpdate);
		return $ar;
	}

	public static function getMinPrices($priceType = 'RU', $arArticle = []){ 
		if (!Bitrix\Main\Loader::includeModule('panel.manager')) {
			die('not install panel.manager');
		}
		$service = PanelManager::getPriceManager();
		$servicePrice = $service->updatePriceService($priceType, 'debug');
		if ($arArticle) {
			$filter = [
				'article' => $arArticle
			];
			$servicePrice->market->setPriceFilter($filter);
		}
		$result = $servicePrice->getMinPurchasePrice();

		return $result;
	}

	//проверяем доступен ли товар с учетом резервов
	public static function getReserveItems(){

		global $DB;

		$arReserved = [];

		$strSql = "SELECT * FROM ci_reserved";

		$results = $DB->Query($strSql, false, $err_mess.__LINE__);
		$arIDs = [];
		while ($row = $results->Fetch()){
			if ($row["RESERVED_s1"] > 0) {
				$arReserved[$row["PRODUCT_ID"]]["s1"] = $row["RESERVED_s1"];
			}
			if ($row["RESERVED_s2"] > 0) {
				$arReserved[$row["PRODUCT_ID"]]["s2"] = $row["RESERVED_s2"];
			}
		}

		$strSql = "SELECT * FROM ci_price_quarantine";

		$results = $DB->Query($strSql, false, $err_mess.__LINE__);
		while ($row = $results->Fetch()){
			$sID = false;
			switch($row["PRICE_ID"]){
				case "RU": $sID = "s1"; break;
				case "BY": $sID = "s2"; break;
			}
			if($sID)
				$arReserved[$row["PRODUCT_ID"]][$sID] = 1000; // костылем ставим 1000
		}

		return $arReserved;
	}

	static function setWarehouseIfPriceAllows(){

	  global $DB;
	  // ДЖоиним таблицу ci_price и ci_suppliers по supplier_id
	  $strSql = "SELECT cp.model AS model, cp.price AS price, cp.supplier_id AS supp_id, cs.settings_type_sklad AS wh
	  FROM ci_price AS cp
	  JOIN ci_suppliers AS cs
	  ON cp.supplier_id = cs.id
	  WHERE cp.active_ozti = 'Y'";
	  $res = $DB->Query( $strSql, false, $err_mess.__LINE__ );

	  // Формируем массив с группировкой по моделям
	  $ar = [];
	  while ( $row = $res->Fetch() ){
	    $ar[ $row["model"] ][] = [
	      'model' => $row['model'],
	      'price' => $row['price'],
	      'supp_id' => $row['supp_id'],
	      'wh' => json_decode( $row['wh'] )
	    ];
	  }

	  $sorted = [];
	  foreach ( $ar as $model => $arData ){
	    $tmp = $arData;
	    if ( count($arData) >= 2 ){
	      // Сортируем предложения по цене
	      usort($tmp, function($a, $b) {
	        return $a['price'] <=> $b['price'];
	      });
	      // Если у предложения с минимальной ценой уже установлен склад, пропускаем итерацию
	      if ( in_array( "Express 7D", $tmp[0]['wh'] ) ) continue;

	      for ( $i = 1; $i <= count($tmp) - 1; $i++ ){
	        // Считаем разницу в процентах между минимальным предложением и следующим
	        $diff = ( $tmp[$i]['price'] - $tmp[0]['price'] ) / $tmp[0]['price'] * 100;
	        // Если разница больше 10 процентов, выходим из цикла, тк дальше нет смысла смотреть
	        if ( $diff > 10 ) break;
	        // Проверяем у следующего подходящего предложения наличие нужного склада и если есть выписываем модель в массив
	        if ( in_array("Express 7D", $tmp[$i]['wh'])){
	          $sorted[] = $model;
						break;
	        }
	      }
	    }
	  }
	  return $sorted;
	}


	public function getAllArticleBrand(){
		global $DB;

		$arBXBrand = array();
		$arSelect = Array("ID", "NAME");
		$arFilter = Array(
			"IBLOCK_ID" => CProSet::IB_BRANDS,
		);
		$result = CIBlockElement::GetList(array(), $arFilter, false, false, $arSelect);
		while ($el = $result->GetNext()){
			$arBXBrand[mb_strtoupper($el["NAME"], 'UTF-8')] = $el["ID"];
		}

		$strSql = "SELECT id, name FROM ci_brands";

		$results = $DB->Query($strSql, false, $err_mess.__LINE__);
		while ($row = $results->Fetch()){
			if($arBXBrand[mb_strtoupper($row["name"], 'UTF-8')]){
				$arBrand[$arBXBrand[mb_strtoupper($row["name"], 'UTF-8')]] = $row;
			}else{
				//	prent($row);
			}

		}

		$strSql = "SELECT pr.PROPERTY_87 as BRAND, pr.PROPERTY_123 as ARTICLE
		FROM
			b_iblock_element el
		LEFT JOIN
			b_iblock_element_prop_s16 pr
		ON el.ID=pr.IBLOCK_ELEMENT_ID
		WHERE
			el.IBLOCK_ID = '16' AND pr.PROPERTY_87 <> '' AND pr.PROPERTY_123 <> ''";

		$results = $DB->Query($strSql, false, $err_mess.__LINE__);
		while ($row = $results->Fetch()){
			if($arBrand[$row["BRAND"]])
				$arArticle[$row["ARTICLE"]] = $arBrand[$row["BRAND"]];
		}

		return $arArticle;
	}

	function isWatchBand($article = ""){
		if(preg_match("/РЕМЕШОК/", $article) || preg_match("/РЕМЕНЬ/", $article))
			return true;
		return false;
	}

	function updatePriceUnused($arItem){
		global $DB;
		global $USER;
		if(!$arItem["model"] || !$arItem["brand_id"] || !$arItem["supplier_id"]) return false;


		$in = array(
			"active_ru" => "'" . $arItem["active_ru"] . "'",
			"active_by" => "'" . $arItem["active_by"] . "'",
			"active_pl" => "'" . $arItem["active_pl"] . "'",
			"active_wb" => "'" . $arItem["active_wb"] . "'",
			"active_wbtl" => "'" . $arItem["active_wbtl"] . "'",
			"active_sb" => "'" . $arItem["active_sb"] . "'",
			"active_opt" => "'" . $arItem["active_opt"] . "'",
			"active_ya" => "'" . $arItem["active_ya"] . "'",
			"active_os" => "'" . $arItem["active_os"] . "'",
			"active_ozti" => "'" . $arItem["active_ozti"] . "'",
			"user_id" => "'".$USER->GetId()."'",
		);

		$strSql = "SELECT * FROM ci_price_unused WHERE model = '{$arItem["model"]}' AND brand_id = '{$arItem["brand_id"]}' AND supplier_id = '{$arItem["supplier_id"]}'";

		$results = $DB->Query($strSql, false, $err_mess.__LINE__);
		if ($row = $results->Fetch()){
			$DB->Update("ci_price_unused", $in, "WHERE model = '{$arItem["model"]}' AND brand_id = '{$arItem["brand_id"]}' AND supplier_id = '{$arItem["supplier_id"]}'", $err_mess.__LINE__);
		}else{
			$in["model"] = "'".addslashes($arItem["model"])."'";
			$in["brand_id"] = "'".addslashes($arItem["brand_id"])."'";
			$in["supplier_id"] = "'".addslashes($arItem["supplier_id"])."'";
			$DB->Insert("ci_price_unused", $in, $err_mess.__LINE__);
		}
		self::deletePriceUnused();

	}

	function getListUnused(){
		global $DB;

		$strSql = "SELECT * FROM ci_price_unused";

		$results = $DB->Query($strSql, false, $err_mess.__LINE__);
		$ar = array();
		while ($row = $results->Fetch()){
			$ar[] = $row;
		}

		return $ar;
	}

	function changeActivityUnused(){
		global $DB;
		foreach($this->getListUnused() as $key => $arItem){
			$DB->Update("ci_price", array("active_ru" => "'".$arItem["active_ru"]."'", "active_by" => "'".$arItem["active_by"]."'", "active_pl" => "'".$arItem["active_pl"]."'", "active_sb" => "'".$arItem["active_sb"]."'", "active_opt" => "'".$arItem["active_opt"]."'", "active_ya" => "'".$arItem["active_ya"]."'","active_av" => "'".$arItem["active_av"]."'","active_ozti" => "'".$arItem["active_ozti"]."'", "active_os" => "'".$arItem["active_os"]."'",), "WHERE model = '{$arItem["model"]}' AND brand_id = '{$arItem["brand_id"]}' AND supplier_id = '{$arItem["supplier_id"]}'", $err_mess.__LINE__);
		}
	}

	public static function deletePriceUnused(){
		global $DB;
		$DB->Query("DELETE FROM ci_price_unused WHERE active = 'Y' AND active_by = 'Y' AND active_pl = 'Y' AND active_wb = 'Y' AND active_sb = 'Y' AND active_opt = 'Y'", false, $err_mess.__LINE__);
	}

	public function convertCurrencyPrice(){
		global $DB;
		$objCurrency = new CPanelCurrency;
		$arCurrency = $objCurrency->getList();
		$suppsNDS = $this->getSuppliersWithNDS();

		$strSql = "SELECT id, price, price_n, priceСurrency, currency, supplier_id FROM ci_price WHERE currency <> 'RUB'";
		$results = $DB->Query($strSql, false, $err_mess.__LINE__);
		$arNeedUpdate = [];
		while ($row = $results->Fetch()){
			if($row["priceСurrency"] > 0){
				$price = $row["price"];
				$newPrice = $row["priceСurrency"] * $arCurrency[$row["currency"]]["rate"];

				if ( !empty($suppsNDS[ $row['supplier_id'] ]) ){
					$price_n = $newPrice;
				}else{
					$price_n = $newPrice * 1.2;
				}

				if(round($newPrice,4) != round($price,4)){
					$arNeedUpdate[] = [
						"ID" => $row["id"],
						"PRICE_NEW" => $newPrice,
						"PRICE_NDS" => $price_n,
						"PRICE_OLD" => $price,
					];
				}
			}
		}
		file_put_contents("/home/bitrix/logs/convert_price.txt", date("Y-m-d H:i:s") . print_r($arNeedUpdate, true), FILE_APPEND);
		foreach($arNeedUpdate as $arItem){
			$DB->Update("ci_price", array("price" => "'".$arItem["PRICE_NEW"]."'", "price_n" => "'".$arItem["PRICE_NDS"]."'"), "WHERE id='".$arItem["ID"]."'", $err_mess.__LINE__);
		}
	}

	private function getSuppliersWithNDS():array
	{
		global $DB;
		$strSql = "SELECT id, nds FROM ci_suppliers WHERE nds = 'Y'";
		$rows = $DB->Query( $strSql );
		$result = [];

		while ( $row = $rows->Fetch() ){
			$result[ $row['id'] ] = $row['id'];
		}

		return $result;
	}

	public function modifyArticle($code)
	{

    $code = trim($code);
    // $patterns = [
    //   "/[A-Z]+?\-[0-9A-Z]+?\-[0-9][ABCDEQVH][0-9]/",
    //   "/[A-Z]+?\-[0-9A-Z]+?\-[0-9][ABCDEQVH]/",
    //   "/B[0-9A-Z]+\-[0-9][ABCDEQVH][0-9]/",
    //   "/B[0-9A-Z]+\-[0-9][ABCDEQVH]/",
    //   "/[A-Z]+?\-[0-9]+?\-[0-9][ABCDEQVH][0-9]/",
    //   "/[A-Z]+?\-[0-9]+?\-[0-9][ABCDEQVH]/",
    //   "/[A-Z]+?\-[0-9A-Z]+?\-[0-9]\b/"
    // ];
		$patterns = file_get_contents('/var/www/bitrix/data/www/tempusshop.ru/bitrix/components/adm/utils.artnumbers/settings/patterns.json');
		$patterns = json_decode($patterns, 1);
		if( empty($patterns) ){
			return $code;
		}
		$letters = file_get_contents('/var/www/bitrix/data/www/tempusshop.ru/bitrix/components/adm/utils.artnumbers/settings/letters.json');
		$letters = explode(',',$letters);

   file_put_contents('/var/www/bitrix/data/www/tempusshop.ru/admin/ajax/pricelist/log/article_mod_log.txt', $code . ' ', FILE_APPEND);

    $modCode = str_replace('PD-', 'D-', $code);
    if( !stripos($modCode, 'PD-') ){
     file_put_contents('/var/www/bitrix/data/www/tempusshop.ru/admin/ajax/pricelist/log/article_mod_log.txt', '-> '. $modCode . ' ', FILE_APPEND);
    }

    if ( substr($modCode, 0, 2) === 'LA' && stripos($modCode, '-') != 2 ){
      $modCode = 'LA-' . substr($modCode, 2, strlen($modCode) - 2 );
     file_put_contents('/var/www/bitrix/data/www/tempusshop.ru/admin/ajax/pricelist/log/article_mod_log.txt', '-> '. $modCode . ' ', FILE_APPEND);
    }
    if ( substr($modCode, 0, 1) === 'A' && ctype_digit(substr($modCode, 1, 1)) ){
      $modCode = 'A-' . substr($modCode, 1, strlen($modCode) - 1 );
     file_put_contents('/var/www/bitrix/data/www/tempusshop.ru/admin/ajax/pricelist/log/article_mod_log.txt', '-> '. $modCode . ' ', FILE_APPEND);
    }

    foreach ($patterns as $pattern){
      if ( preg_match($pattern, $modCode, $match) ){

        if ( in_array(substr($match[0], -1, 1), $letters) ){
          $modified = substr( $match[0], 0, strlen($match[0]) - 1 ). 'E';
         file_put_contents('/var/www/bitrix/data/www/tempusshop.ru/admin/ajax/pricelist/log/article_mod_log.txt', '-> '. $match[0] .'E (GOOD)' . PHP_EOL, FILE_APPEND);
          return ["mod" => $modified, "raw"=> $code];
        }

       file_put_contents('/var/www/bitrix/data/www/tempusshop.ru/admin/ajax/pricelist/log/article_mod_log.txt', '-> '. $match[0] .' (GOOD)' . PHP_EOL, FILE_APPEND);
        return ["mod" => $match[0], "raw"=> $code];

      }
    }
   file_put_contents('/var/www/bitrix/data/www/tempusshop.ru/admin/ajax/pricelist/log/article_mod_log.txt', '-> ' . $code . ' (EXCEPTION)' . PHP_EOL, FILE_APPEND);
    return false;

  }

	private function formatArticle(string $brand, string $article): string
	 {
	  // Получаем настройки из json
	  $filePath = $_SERVER["DOCUMENT_ROOT"] . "/admin/utilities/artdots/db/settings.json";
	  // echo $filePath;
	  // echo 'Проверка файла<br>';
	  if ( !file_exists( $filePath ) ) return $article;
	  // echo 'Получаем настройки<br>';
	  $settings = file_get_contents( $filePath );
	  if ( empty($settings) ) return $article;
	  // echo 'Декодиование файла<br>';
	  $settings = json_decode( $settings, true );
	  if ( empty($settings) ) return $article;
	  // Если артикул уже имеет в себе точку, то возвращем его
	  // echo 'Проверка формата<br>';
	  if ( stripos($article, '.') ) return $article;
	  // Отдельный костыль для тиссо
	  if ( $brand == 20 && !empty( $settings[ $brand ] ) && strlen($article) == 12 ){
	    $article .= '00';
	  }
	  // Проверяем настройки
	  if ( empty( $settings[$brand][strlen( $article )] ) ){
	    // echo 'No Profile';
	    return $article;
	  }
	  // echo 'Формируем артикул<br>';
	  // Формируем артикул
	  $positions = $settings[$brand][strlen( $article )];
	  $arStr = str_split( $article );
	  $res = '';

	  foreach ($arStr as $code => $char) {
	    if ( in_array($code + 1, $positions) ){
	      $res .= $char . '.';
	    }else{
	      $res .= $char;
	    }
	  }
	  return $res;
	}
}
