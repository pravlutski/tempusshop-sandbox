<?php
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
			if($optins["price_r"] == "on")
				$arItem["price"] = 0;
			$in = array(
				"model" => "'".addslashes($arItem["article"])."'",
				"brand_id" => "'".addslashes($arItem["brand_id"])."'",
				"supplier_id" => "'".addslashes($arItem["supplier_id"])."'",
				"store_id" => 3,
				"price" => "'".addslashes($arItem["price"])."'",
				"count" => "'".addslashes($arItem["count"])."'",
				"bitrix_id" => "'".addslashes($bxID[$arItem["article"]])."'",
			);
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
		if($optins["price_no_clear"] != "on"){
			foreach($arBrand as $key => $arItem){
				$in = array(
					"brand_id" 		=> $arItem["id"],
					"supplier_id" 	=> $supplier_id,
				);
		
				$strSql = "SELECT id FROM ci_pricelist WHERE brand_id = '{$arItem["id"]}' AND supplier_id = '{$supplier_id}'";
				$results = $DB->Query($strSql, false, $err_mess.__LINE__);
				if (!$row = $results->Fetch()){
					$DB->Insert("ci_pricelist", $in, $err_mess.__LINE__);
				}
		
				
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
	function getPriceByFilter($arFilter = array(), $group = false, $arSelect = false, $order = false){
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
							$filterW[] = "(active = 'Y')";
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
						case "opt":
							$filterW[] = "(active_opt = 'Y')";
							break;	
						default:
							break;
					}
				}
				if(count($filterW) > 0){
					$filter[] = "(" . implode(" OR ", $filterW) . ")";
				}

			}else{
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
					case "wb":
						$filter[] = "active_wb = 'Y'";
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
				if(count($ar) > 0){
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
				if(count($ar) > 0){
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
				if(count($ar) > 0){
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
				if(count($ar) > 0){
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
		if(isset($arFilter["!article"])){
			if(is_array($arFilter["!article"])){
				$ar = array();
				foreach($arFilter["!article"] as &$item){
					$item = intval($item);
					if($item > 0)
						$ar[$item] = $item;
				}
				if(count($ar) > 0){
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
		if(count($filter) > 0){
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
	function changeActivityAll_06052020( $supp_id, $arStatus, $arBrand){
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
	
	function changeActivityAll($arSupplier){
		global $DB;
		if(in_array($arSupplier["active"], array("N","Y"))) $status = $arSupplier["active"]; else $status = "N";
		if(in_array($arSupplier["active_by"], array("N","Y"))) $status_by = $arSupplier["active_by"]; else $status_by = "N";
		if(in_array($arSupplier["active_pl"], array("N","Y"))) $status_pl = $arSupplier["active_pl"]; else $status_pl = "N";
		if(in_array($arSupplier["active_wb"], array("N","Y"))) $status_wb = $arSupplier["active_wb"]; else $status_wb = "N";
		if(in_array($arSupplier["active_opt"], array("N","Y"))) $status_opt = $arSupplier["active_opt"]; else $status_opt = "N";
		
		//prent($arBrand);
		
		$DB->Update("ci_price", array("active" => "'".$status."'", "active_by" => "'".$status_by."'", "active_pl" => "'".$status_pl."'", "active_wb" => "'".$status_wb."'", "active_opt" => "'".$status_opt."'"), "WHERE supplier_id='".$arSupplier["id"]."'", $err_mess.__LINE__);
		$DB->Update("ci_pricelist", array("active" => "'".$status."'", "active_by" => "'".$status_by."'", "active_pl" => "'".$status_pl."'", "active_wb" => "'".$status_wb."'", "active_opt" => "'".$status_opt."'"), "WHERE supplier_id='".$arSupplier["id"]."'", $err_mess.__LINE__);
		
		foreach($arSupplier["settings"]["brand"] as $brand_id => $ar){
			if(in_array($ar["active"], array("N","Y"))) $status = $ar["active"]; else $status = "N";
			if(in_array($ar["active_by"], array("N","Y"))) $status_by = $ar["active_by"]; else $status_by = "N";
			if(in_array($ar["active_pl"], array("N","Y"))) $status_pl = $ar["active_pl"]; else $status_pl = "N";
			if(in_array($ar["active_wb"], array("N","Y"))) $status_wb = $ar["active_wb"]; else $status_wb = "N";
			if(in_array($ar["active_opt"], array("N","Y"))) $status_opt = $ar["active_opt"]; else $status_opt = "N";
			$DB->Update("ci_price", array("active" => "'".$status."'", "active_by" => "'".$status_by."'", "active_pl" => "'".$status_pl."'", "active_wb" => "'".$status_wb."'", "active_opt" => "'".$status_opt."'"), "WHERE supplier_id='".$arSupplier["id"]."' AND brand_id='".$ar["id"]."'", $err_mess.__LINE__);
			$DB->Update("ci_pricelist", array("active" => "'".$status."'", "active_by" => "'".$status_by."'", "active_pl" => "'".$status_pl."'", "active_wb" => "'".$status_wb."'", "active_opt" => "'".$status_opt."'"), "WHERE supplier_id='".$arSupplier["id"]."' AND brand_id='".$ar["id"]."'", $err_mess.__LINE__);
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
				if(count($ar) > 0){
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
				if(count($ar) > 0){
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
				if(count($ar) > 0){
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
				if(count($ar) > 0){
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
		$objUtils = new CPanelUtils;
		//$arArtnumberAll = $objUtils->getArtnumberAll();
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
		if(isset($arFilter["model"])){
			if(is_array($arFilter["model"])){
				$ar = array();
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
		//$strSql .= " LIMIT 0,100";
		$results = $DB->Query($strSql, false, $err_mess.__LINE__);
		while ($row = $results->Fetch()){
			//if(!$model = $objUtils->getArtnumber($row["model"]))
			//	$model = $row["model"];
			if(!$model = $arArtnumberAll[$row["model"]])
				$model = $row["model"];
			
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
						$br["active_wb"] = $s_brand["active_wb"];
						$br["active_opt"] = $s_brand["active_opt"];
						
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
		if(count($arBrand) == 0){
			return "no select brand";
		}
		
		//список всех артикулов с брендом. если совсем ничего не нашли пробуем просто в уже загруженных найти бренд по арикулу
		$strSql = "SELECT model, brand_id FROM ci_price GROUP BY model";
		$results = $DB->Query($strSql, false, $err_mess.__LINE__);
		while ($row = $results->Fetch()){
			if($tmpBrand[$row["brand_id"]])
				$arArtPrice[$row["model"]] = $tmpBrand[$row["brand_id"]];
		}
		
		$arBXArticle = $this->getAllArticleBrand();
		
		$arOrigBrand = $arBrand;
		
		if(isset($settings["currency"]) && $settings["currency"] != "RUB"){
			$currency = $objCurrency->getDetail( $settings["currency"] );//курс валюты
			$amount = $currency["amount"];
			$rate = $currency["rate"];
		}else{
			$amount = $rate = 1;
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
							'active' => $v['active'],
							'active_by' => $v['active_by'],
							'active_pl' => $v['active_pl'],
							'active_wb' => $v['active_wb'],
							'active_opt' => $v['active_opt'],
						);
						
						$arClearStr[] = mb_strtoupper($name, "UTF-8");
					}
				}
				unset($name);
			}
		}
		
		array_multisort(array_map('strlen', $arClearStr), $arClearStr);
		$arClearStr = array_reverse($arClearStr);
		//AddMessage2Log($arClearStr);
		//if(count($arAltBrand) > 0) $arBrand = array_merge($arBrand, $arAltBrand);
		//if(count($arAltBrand) > 0) $arBrand = array_merge($arAltBrand, $arBrand);
		if(count($arAltBrand) > 0) $arBrand = array_merge($arBrand, $arAltBrand);

		//AddMessage2Log($arAltBrand);
		//AddMessage2Log($arBrand);
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
			//AddMessage2Log($arLists);die;
			$spreadsheet = new SpreadsheetReader($filename);
			$sheets = $spreadsheet->sheets();
			$ar = array();
			$i = 0;
			foreach ($sheets as $index => $Name){
				if(count($arLists) > 0 && !in_array($index, $arLists)) continue;
				$asd = $spreadsheet->ChangeSheet($index);
				//для прайсов у которых нет бренда пытаемся взять название бренда из названия листа
				if($brand_lock === true){
					$_brand = false;
					foreach($arBrand as $k => $v){
						if(stripos($Name, $v["name"]) !== false){
							$_brand = $v["name"];
							break;
						}
					}
				}
				foreach ($spreadsheet as $key => $row){
					$ar[$i] = $row;
					
					if($brand_lock === true && $_brand){
						$ar[$i]["brand"] = $_brand;
					}
					
					$ar[$i]["list_num"] = $index + 1;
					
					$i++;
				}
			}
//AddMessage2Log($ar);return false;

			//AddMessage2Log($ar);die;
			
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
			
			$arUp = array();
			foreach($ar as $key => $row){
				//$arUp[] = array_map('strtoupper', $row);
				$arUp[] = array_map(function($str){
					return mb_strtoupper($str, "UTF-8");
				}, $row);
			}
			if($supplier["id"] == 83){
				$ar = array();
				//AddMessage2Log($arUp);
				
				/*
				//коммент с 21 06 2021
				foreach($arUp as $key => &$arItem){
					if(mb_strstr($arItem[0], "ЧАСЫ")) $lastArticle = false;
					if(strlen($arItem[0]) && !ctype_digit($arItem[0]) && intval($arItem[4]) > 0 && mb_strstr($arItem[0], "ЧАСЫ")){
						$arItem[0] = trim(str_replace(array(", ШТ", "ЧАСЫ", "РЕМЕНЬ"), "", $arItem[0]));
						$ar[$key] = $arItem;
						$lastArticle = $key;
					}elseif(strlen($arItem[0]) && ctype_digit($arItem[0]) > 0 && $ar[$lastArticle]){
						$ar[$lastArticle][1] = $arItem[0];
						//AddMessage2Log($ar);
						//AddMessage2Log($lastArticle);
					}
				}
				//коммент с 08 07 2021 
				foreach($arUp as $key => &$arItem){
					if(mb_strstr($arItem[1], "ЧАСЫ")) $lastArticle = false;
					if(strlen($arItem[1]) && !ctype_digit($arItem[1]) && intval($arItem[2]) > 0 && mb_strstr($arItem[1], "ЧАСЫ")){
						$arItem[0] = trim(str_replace(array(", ШТ", "ЧАСЫ", "РЕМЕНЬ"), "", $arItem[1]));
						$ar[$key] = $arItem;
						$lastArticle = $key;
					}elseif(strlen($arItem[1]) && ctype_digit($arItem[1]) > 0 && $ar[$lastArticle]){
						$ar[$lastArticle][1] = $arItem[1];
						//AddMessage2Log($ar);
						//AddMessage2Log($lastArticle);
					}
				}*/
				
				/*
				//коммент с 16 09 2021 
				foreach($arUp as $key => &$arItem){
					if(mb_strstr($arItem[0], "ЧАСЫ")) $lastArticle = false;
					if(strlen($arItem[0]) && !ctype_digit($arItem[0]) && intval($arItem[6]) > 0 && mb_strstr($arItem[0], "ЧАСЫ")){
						$arItem[0] = trim(str_replace(array(", ШТ", "ЧАСЫ", "РЕМЕНЬ"), "", $arItem[0]));
						$ar[$key] = $arItem;
						$lastArticle = $key;
					}elseif(strlen($arItem[0]) && ctype_digit($arItem[0]) > 0 && $ar[$lastArticle]){
						$ar[$lastArticle][1] = $arItem[0];
						//AddMessage2Log($ar);
						//AddMessage2Log($lastArticle);
					}
				}
				unset($arItem);
				
				*/
				foreach($arUp as $key => &$arItem){
					if(mb_strstr($arItem[0], "ЧАСЫ")) $lastArticle = false;
					if(strlen($arItem[0]) && !ctype_digit($arItem[0]) && intval($arItem[1]) > 0 && mb_strstr($arItem[0], "ЧАСЫ")){
						$arItem[0] = trim(str_replace(array(", ШТ", "ЧАСЫ", "РЕМЕНЬ"), "", $arItem[0]));
						$ar[$key] = $arItem;
						$lastArticle = $key;
					}elseif(strlen($arItem[0]) && ctype_digit($arItem[0]) > 0 && $ar[$lastArticle]){
						$ar[$lastArticle][1] = $arItem[0];
						$ar[$lastArticle][2] = $arItem[1];
						//AddMessage2Log($ar);
						//AddMessage2Log($lastArticle);
					}
				}
				$arUp = $ar;
				//AddMessage2Log($arUp);
				//return false;
			}
			
			$ar = $arUp;

			//AddMessage2Log($ar);return false;
			/*$col_price = intval($settings_pricelist["col_price"]);
			$col_count = intval($settings_pricelist["col_count"]);
			$count_default = intval($settings_pricelist["count_default"]);
			$quntity_flag = $settings_pricelist["quntity_flag"];
			$quntity_value = $settings_pricelist["quntity_value"];
			$col_article = intval($settings_pricelist["col_article"]);
			$col_brand = intval($settings_pricelist["col_brand"]);
			$start_row = intval($settings_pricelist["start_row"]);
			if($settings_pricelist["clear_space"] == "Y")
				$clear_space = true;*/
	
			$arResult = array();
			unset($spreadsheet);
			//AddMessage2Log($col_brand);
			
			//AddMessage2Log($ar);
			$arIDAccess = array();
			foreach($arOrigBrand as $k => $v){
				$arIDAccess[$v["id"]] = $v["sale"];
			}
			//AddMessage2Log($arBrand);
			//AddMessage2Log($arOrigBrand);
			//AddMessage2Log($ar);return false;
			//AddMessage2Log($arBXArticle);return false;
			if($supplier["id"] == 77 && $ar[1][0] == "ПАРАМЕТРЫ:"){
				$profile = 2;
			}else{
				$profile = 1;
			}
			
			//разбираем данные которые прочитали из файла
			if(count($ar) > 0){
				//return array("true" => count($ar), "bad" => 432);
				//AddMessage2Log($perBrand);
				
				$margin = (float) $settings_pricelist["margin"];
				
				foreach ($ar as $key => $row){
					
					$tmp = $matches = array_diff($row, array(''));
					if(count($tmp) == 2 && strlen($tmp[0]) > 0) $perBrand = $tmp[0];
					//AddMessage2Log($perBrand);
					
					
					//подменяем колонки, если есть настройки по листам
					if($settings_pricelist_num[$row["list_num"]]["active"] == "Y"){
						$col_price = intval($settings_pricelist_num[$row["list_num"]]["col_price"]);
						$col_count = intval($settings_pricelist_num[$row["list_num"]]["col_count"]);
						$count_default = intval($settings_pricelist_num[$row["list_num"]]["count_default"]);
						$quntity_flag = $settings_pricelist_num[$row["list_num"]]["quntity_flag"];
						$quntity_value = $settings_pricelist_num[$row["list_num"]]["quntity_value"];
						$col_article = intval($settings_pricelist_num[$row["list_num"]]["col_article"]);
						$col_brand = intval($settings_pricelist_num[$row["list_num"]]["col_brand"]);
						$start_row = intval($settings_pricelist_num[$row["list_num"]]["start_row"]);
						if($settings_pricelist_num[$row["list_num"]]["clear_space"] == "Y")
							$clear_space = true;
					}else{
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
					}
					
					if($supplier["id"] == 77 && $profile == 2){
						$col_price = 5;
						$col_article = 1;
						$col_brand = 10;
						$start_row = 7;
					}
				
					
					
//AddMessage2Log($ar);
					if($key < ($start_row - 1)) continue;
					
					$article = $row[$col_article - 1];//29-04-2020
					//$article = mb_strtoupper($row[$col_article - 1]);
					//$article = strtoupper($row[$col_article - 1]);
					//die;


					if(!$col_brand || $col_brand == $col_article){
						//AddMessage2Log($col_brand);
						//foreach($arBrand as $k => $v){
						//	$article = str_replace($v["name"], '', $article);
						//}
						$article = str_replace($arClearStr, '', $article);
						
					}
					$article = trim($article);
					//AddMessage2Log($article);
					//if($clear_space === true) $article = str_replace(' ', '', $article);
					if($clear_space === true) {
						//$article = str_ireplace(array("(карманные)", "(с запанками)", "(с ручкой)", "(темно-коричневый ремешок)", "муж", "жен", "спорт", "мех.", "Lcd"), '', $article);

						$article = str_replace_once(' ', '', trim($article)); 
					}
					
					if(!$article) continue;
					
					if($this->isWatchBand($article)){
						$isWatchBand = true;
						$clearArt = false;
						//$article = trim(str_replace(array("РЕМЕШОК ДЛЯ", "РЕМЕШОК", "РЕМЕНЬ", " , ", " ,", ", ", " / ", " /", "/ ", "   ", "  "), array("","","",",",",",",","/","/","/"," "," "), $article));
						$article = trim(str_replace(array("РЕМЕШОК ДЛЯ ", "РЕМЕШОК ", "РЕМЕНЬ "), "", $article));

					}else{
						$isWatchBand = false;
						$clearArt = true;
					}
					//
					//AddMessage2Log($article);
					//AddMessage2Log($col_brand);die;
					$flg = false;
					
					if($arBXArticle[$article] && isset($arIDAccess[$arBXArticle[$article]["id"]])){
						$flg = true;
						$brand_name = $arBXArticle[$article]["name"];
						$brand_id = $arBXArticle[$article]["id"];
						$sale = $arIDAccess[$arBXArticle[$article]["id"]];
					}

					$flgBrand = false;
					if($flg === false && $supplier["id"] == 77){
						$flgBrand = true;
						//$ar
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
							//$brand_name = $arL[$row[1]]["name"];
							//$brand_id = $arL[$row[1]]["id"];
							//$sale = $arIDAccess[$arL[$row[1]]];
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
							);//AddMessage2Log($profile);
						//AddMessage2Log($arBrandL);
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
								//if($row[$col_brand - 1] == "ВОСТОК-ЕВРОПА 6S11/320C260"){
								//	AddMessage2Log($v);
								//}
								$brand_name = $v["name"];
								$brand_id = $v["id"];
								
								//костыль
								if (1==2 && $supplier["id"] == 37 && $brand_id == 1 && (stristr($article, "MTP") || stristr($article, "LTP"))){
									$sale = 52;
								//}elseif($supplier["id"] == 39 && $brand_id == 1){
								}elseif($supplier["id"] == 90 && $brand_id == 1){
									//if(preg_match("/^(A\-|AE\-|AEQ\-|AMW\-|AQ\-|B\-|CA\-|CPA\-|DB\-|DBC\-|DQ\-|F\-|HDA\-|HDC\-|HS\-|ID\-|LA\-|LQ\-|LRW\-|LTP\-|LTR\-|LW\-|LWA\-|LWS\-|LX\-|MCW\-|MQ\-|MRW\-|MTD\-|MTP\-|MTS\-|MW\-|MWA\-|MWC\-|MWD\-|PQ\-|SDB\-|W\-|WS\-|WSC\-|WV)\w+\-\w+$/", $article))
									
									if(preg_match("/^(A\-|AE\-|AEQ\-|AQ\-|AW\-|B\-|BA\-|BGA\-|BGD\-|CA\-|DB\-|DBC\-|DW\-|F\-|HDA\-|HDC\-|IQ\-|LA\-|LQ\-|LRW\-|LTP\-|LW\-|LWA\-|LWS\-|LX\-|MCW\-|MQ\-|MRW\-|MSG\-|MTP\-|MTS\-|MW\-|MWA\-|MWD\-|PRG\-|PRW\-|W\-|WS\-)\w+\-\w+$/", $article))
										$sale = 5;// у дениса стоял 5
									else
										$sale = $v["sale"];
								}else{
									$sale = $v["sale"];
								}
								$flg = true;
								break;
							}
						}
						unset($v);

						//if ($flg === false && !$row[$col_brand - 1]) {
						if ($flg === false && $col_brand == $col_article) {
							if($article[1] == "-" || $article[2] == "-" || $article[3] == "-"){
								foreach($arBrand as $k => $v){
									//if($v["name"] == "Casio"){
									if($v["id"] == 1){
										$brand_name = $v["name"];
										$brand_id = $v["id"];
										//костыль
										if (1==2 && $supplier["id"] == 37 && $brand_id == 1 && (stristr($article, "MTP") || stristr($article, "LTP"))){
											$sale = 52;
										//}elseif($supplier["id"] == 39 && $brand_id == 1){
										}elseif($supplier["id"] == 90 && $brand_id == 1){
											//если Денис
											if(preg_match("/^(A\-|AE\-|AEQ\-|AQ\-|AW\-|B\-|BA\-|BGA\-|BGD\-|CA\-|DB\-|DBC\-|DW\-|F\-|HDA\-|HDC\-|IQ\-|LA\-|LQ\-|LRW\-|LTP\-|LW\-|LWA\-|LWS\-|LX\-|MCW\-|MQ\-|MRW\-|MSG\-|MTP\-|MTS\-|MW\-|MWA\-|MWD\-|PRG\-|PRW\-|W\-|WS\-)\w+\-\w+$/", $article))
												$sale = 5;
											else
												$sale = $v["sale"];
										}else{
											$sale = $v["sale"];
										}
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
					}elseif(count($arOrigBrand) == 1 && $flg === false){
					
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
						//if($article == "A178WEA-1AES"){
						//	AddMessage2Log(strlen($row[$col_brand - 1]));
						//	AddMessage2Log(strlen($arBrand[0]["name"]));
						//}
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
						//костыль
						if (1==2 && $supplier["id"] == 37 && $brand_id == 1 && (stristr($article, "MTP") || stristr($article, "LTP")))
							$sale = 52;
						else
							$sale = $arBrand[0]["sale"];

						if(!$col_brand || $col_brand == $col_article)
							$article = str_ireplace($brand_name, "", $article);
					}elseif($flg === true){
						if (1==2 && $supplier["id"] == 37 && $brand_id == 1 && (stristr($article, "MTP") || stristr($article, "LTP"))){
							$sale = 52;
						//}elseif($supplier["id"] == 39 && $brand_id == 1){
						}elseif($supplier["id"] == 90 && $brand_id == 1){
							if(preg_match("/^(A\-|AE\-|AEQ\-|AQ\-|AW\-|B\-|BA\-|BGA\-|BGD\-|CA\-|DB\-|DBC\-|DW\-|F\-|HDA\-|HDC\-|IQ\-|LA\-|LQ\-|LRW\-|LTP\-|LW\-|LWA\-|LWS\-|LX\-|MCW\-|MQ\-|MRW\-|MSG\-|MTP\-|MTS\-|MW\-|MWA\-|MWD\-|PRG\-|PRW\-|W\-|WS\-)\w+\-\w+$/", $article))
								$sale = 5;
						}
						
						/*if($article == "AE-1200WHD-1A"){
							AddMessage2Log($article);
							AddMessage2Log($supplier["id"]);
							AddMessage2Log($sale);
						}*/
					}elseif($flgBrand === true && $arBrandL["id"]){
						//self::$badColumn[] = "Артикул - {$article}. Бренда неопределен3";
						//continue;
						$brand_name = $arBrandL["name"];
						$brand_id = $arBrandL["id"];
						$sale = $arBrandL["sale"];
						
					
						//AddMessage2Log($brand_id);AddMessage2Log($article);
						//AddMessage2Log($brand_name);AddMessage2Log($brand_id);AddMessage2Log($sale);
					}elseif($flg === false){
						self::$badColumn[] = "Артикул - {$article}. Бренда неопределен3";
						continue;
					}

					
					$price = $row[$col_price - 1];
					
					if($supplier["id"] == 77){
						if(strlen($row[9]) > 0){
							$price = $row[9];
							$sale = 0;
						}
						if($profile == 2) $sale = 0;
					}

					//$price = str_replace(" ", "", $price);
					$price = str_replace(array("'",'"'," ","$"), "", $price);

					$t = explode(",", $price);
					if(strlen($t[1]) == 2 && $t[1] == "00")
						$price = $t[0];
					elseif(count($t) == 2){
						$price = str_replace(",", ".", $price);
					}else{
						$price = str_replace(",", "", $price);
					}
						
					
//AddMessage2Log($article);
//AddMessage2Log($price);
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
					
//AddMessage2Log($article);
//AddMessage2Log($price);
					
					if(strlen($article) > 0 && $price > 0){
						
						/*if($article == "2426-4571144"){
							AddMessage2Log($arRegularReplace[$brand_id]["pattern"]);
							AddMessage2Log($arRegularReplace[$brand_id]["replacement"]);
							$article = preg_replace($arRegularReplace[$brand_id]["pattern"], $arRegularReplace[$brand_id]["replacement"], $article);
							AddMessage2Log($article);
						}*/
						//смотрим заполнено ли в бренде поля Поиск и замену по регулярному выражению
						if($arRegularReplace[$brand_id]){
							$_article = preg_replace($arRegularReplace[$brand_id]["pattern"], $arRegularReplace[$brand_id]["replacement"], $article);
							if(strlen($_article) > 0 && $_article != null){
								$article = $_article;
							}
								
						}
						
						if($clearArt === true){
							//удаляем из артикула по регулярке из настроек бренда. костыли ниже почистить!!!
							if($arRegular[$brand_id]){
								preg_match($arRegular[$brand_id], $article, $matches);
								$matches = array_diff($matches, array(''));
								$matches = array_unique($matches);
								//AddMessage2Log($article);
								//AddMessage2Log($matches);
								if($matches && count($matches) == 1 && strlen($matches[0]) > 0)
									$article = $matches[0];
							}
						
							//22/04/2020 поставил выше т.к. в праййсе дубли по артикулам. если будет тормозить вернуть обратно
							

							$article = str_replace(array("  "), array(" "), $article);
							$article = trim($article);
							
							//$pos = strpos($article, " ");
							//если поставщик 3. Денис (supplier_id = 39) и бренд Восток (brand_id = 38)
							//если поставщик 3. Денис (supplier_id = 39) и бренд Слава (brand_id = 59) или Спецназ (brand_id = 60)

							if($supplier["id"] == 39 && in_array($brand_id, array(38, 59, 60))){
								$tmp = trim(array_pop(explode(" ", $article)));
								//$tmp = intval($tmp);
								//if($tmp > 0){	
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
								if(!in_array($brand_id, array(70)))
									$article = strstr($article, " ", true);
							}
							if($brand_id == 26 && $article[2] == "/"){
								$article = substr($article, 3);
							}
							
							//если Tissot то удаляем точку после буквы T
							if($brand_id == 20 && $article[0] == "T" && $article[1] == "."){
								$article = substr_replace($article, '', 1, 1);
							}
							
							//CALVIN KLEIN если предпоследний символ символ, то перед ней поставить точку
							if($brand_id == 27 && !ctype_digit(substr($article, -2, 1)) && substr($article, -3, 1) != "."){
								$article = substr($article, 0, -2) . "." . substr($article, -2);
							}

							//AddMessage2Log($article);
							//AddMessage2Log($brand_id);
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
								if(count($matches) == 1 && strlen($matches[0]) > 0){
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
									//$ar
									//prent($article);
									//prent($arArt);
								}
								
								//if($article == "71604416"){AddMessage2Log($arArt);}
								if(count($arArt) > 0 && strlen($article) > 0){
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
												"sale" 			=> $sale,
											);
										}
									}
								}
							}
							//$article = preg_replace('~[^-a-zA-Z0-9_\s,\/()]+~', '', $article);
							//$article = trim(str_replace(array(" / ", " , ", ", ", "()", "( )"), array("/", ",",",",""), $article));
							
							//ищем правильный артикул, если введен 
							if($art = $objUtils->getArtnumber($article))
								$article = $art;
							//AddMessage2Log($article);
						}

					}

				}
				//return false;
				//if($USER->getID() == 587){AddMessage2Log($arResult);return false;}
				//AddMessage2Log($arResult);return false;
//
				
				//prent($arResult,0,1);//return;
				unset($row);
				if(!$arResult || count($arResult) == 0) return array("true" => 0);
				
				foreach($arResult as $key => &$arItem){
					$arItem["price"] = $arItem["price"] * $rate;
					if($arItem["sale"] > 0 && $arItem["sale"] < 100){
						$arItem["price"] = $arItem["price"] * ( 100 - $arItem["sale"] ) / 100;
					}
					
					if($margin > 0){
						$arItem["price"] = $arItem["price"] + $arItem["price"] * $margin / 100;
					}
					
					//костыль для AUDIO ORIENT
					if($arItem["supplier_id"] == 66 && $arItem["brand_id"] == 2){
						//$arItem["price"] = $arItem["price"] * 72;
					}
					$arItem["price"] = round($arItem["price"], 2);
					//AddMessage2Log($arItem["article"]);
					//AddMessage2Log($arItem["brand_id"]);
					/*
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
					
					//если поставщик 3. Денис (supplier_id = 39) и бренд Восток (brand_id = 38)
					if($arItem["supplier_id"] == 39 && $arItem["brand_id"] == 38){
						$tmp = trim(array_pop(explode(" ", $arItem["article"])));
						$tmp = intval($tmp);
						//AddMessage2Log($arItem["article"]);
//AddMessage2Log($tmp);
						//if(strlen($tmp) > 0){
						if($tmp > 0){	
							
							$arItem["article"] = $tmp;
							
						}
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
					
					//если Tissot то удаляем точку после буквы T
					if($arItem["brand_id"] == 20 && $arItem["article"][0] == "T" && $arItem["article"][1] == "."){
						$arItem["article"] = substr_replace($arItem["article"], '', 1, 1);
					}
					
					//ищем правильный артикул, если введен 
					//if($art = $objUtils->getArtnumber( $arItem["article"] ))
					//	$arItem["article"] = $art;
					*/

				}
					
				unset($arItem);
				unset($ar);
				//AddMessage2Log($arResult);return false;
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
					"active_wb" => $supplier["active_wb"],
					"active_opt" => $supplier["active_opt"],
				);

				//$this->changeActivityAll($supplier["id"], $arStatus, $tmpBrand);
				//AddMessage2Log($supplier);AddMessage2Log($arBrand);//return false;
				//$this->changeActivityAll($supplier["id"], $arStatus, $arBrand);
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
	
	
	public static function updateDateDelivery(){
		global $DB;
		
		$DB->Query("TRUNCATE TABLE ci_model_delivery", false, $err_mess.__LINE__);
		//return false;
		$arArticle = array();

		//$strSql = "SELECT el.ID as ID, el.IBLOCK_ID as IBLOCK_ID, pr.VALUE as ARTICLE FROM b_iblock_element el LEFT JOIN b_iblock_element_property pr ON el.ID=pr.IBLOCK_ELEMENT_ID WHERE el.ACTIVE = 'Y' AND ((el.IBLOCK_ID = '16' AND pr.IBLOCK_PROPERTY_ID = '123') OR (el.IBLOCK_ID = '17' AND pr.IBLOCK_PROPERTY_ID = '121'))";
		/*$strSql = "SELECT el.ID as ID, el.IBLOCK_ID as IBLOCK_ID, pr.PROPERTY_123 as ARTICLE FROM b_iblock_element el 
		LEFT JOIN 
		b_iblock_element_prop_s16 pr ON el.ID=pr.IBLOCK_ELEMENT_ID WHERE el.ACTIVE = 'Y' 
		AND ((el.IBLOCK_ID = '16' AND pr.PROPERTY_123 <> ''))";
		*/
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
		
		$strSql = "SELECT id, settings_pricelist FROM ci_suppliers";
		$results = $DB->Query($strSql, false, $err_mess.__LINE__);
		while ($row = $results->Fetch()){
			$tmp = json_decode($row["settings_pricelist"], true);
			$arSupp["s1"][$row["id"]] = $tmp["day_delivery"];
			$arSupp["s2"][$row["id"]] = $tmp["day_delivery_by"];
			$arSupp["s3"][$row["id"]] = $tmp["day_delivery_pl"];
			
			$arWorking[$row["id"]] = $tmp["working_time"];
		}
						
		$arPrice = array();
/*
		$strSql = "SELECT model, supplier_id, price FROM ci_price WHERE active='Y'";// GROUP BY model";MIN(price) as 
		$results = $DB->Query($strSql, false, $err_mess.__LINE__);
		while ($row = $results->Fetch()){
			if(isset($arSupp["s1"][$row["supplier_id"]])){
				//if(empty($arPrice["s1"][$row["model"]]) || $row["supplier_id"] == 47 || ($arPrice["s1"][$row["model"]]["supplier_id"] != 47 && $row["price"] < $arPrice["s1"][$row["model"]]["price"])){
				if(empty($arPrice["s1"][$row["model"]]) || ($row["price"] < $arPrice["s1"][$row["model"]]["price"])){
					$row["day_delivery"] = $arSupp["s1"][$row["supplier_id"]];
					$row["working_time"] = $arWorking[$row["supplier_id"]];
					$arPrice["s1"][$row["model"]] = $row;
				}
			}
		}
*/
		$arMin = array();
		$strSql = "SELECT model, supplier_id, price FROM ci_price WHERE active='Y' ORDER BY price ASC";// GROUP BY model";MIN(price) as 
		$results = $DB->Query($strSql, false, $err_mess.__LINE__);
		while ($row = $results->Fetch()){
			if(isset($arSupp["s1"][$row["supplier_id"]])){
				if(empty($arMin[$row["model"]])) $arMin[$row["model"]] = $row["price"];
				if(empty($arPrice["s1"][$row["model"]]) || ($arSupp["s1"][$row["supplier_id"]] < $arPrice["s1"][$row["model"]]["day_delivery"] && (($row["price"] - $arMin[$row["model"]]) / $row["price"] * 100) < 5)){
					$row["day_delivery"] = $arSupp["s1"][$row["supplier_id"]];
					$row["working_time"] = $arWorking[$row["supplier_id"]];
					$arPrice["s1"][$row["model"]] = $row;
				}
			}
		}
		/*
		$strSql = "SELECT model, supplier_id, price FROM ci_price WHERE active_by='Y'";// GROUP BY model";
		$results = $DB->Query($strSql, false, $err_mess.__LINE__);
		while ($row = $results->Fetch()){
			if(isset($arSupp["s2"][$row["supplier_id"]])){
				if(empty($arPrice["s2"][$row["model"]]) || $row["supplier_id"] == 44 || ($arPrice["s2"][$row["model"]]["supplier_id"] != 44 && $row["price"] < $arPrice["s2"][$row["model"]]["price"])){
					$row["day_delivery"] = $arSupp["s2"][$row["supplier_id"]];
					$row["working_time"] = $arWorking[$row["supplier_id"]];
					$arPrice["s2"][$row["model"]] = $row;
				}

			}
		}
		*/
		$arMin = array();
		$strSql = "SELECT model, supplier_id, price FROM ci_price WHERE active_by='Y' ORDER BY price ASC";// GROUP BY model";MIN(price) as 
		$results = $DB->Query($strSql, false, $err_mess.__LINE__);
		while ($row = $results->Fetch()){
			if(isset($arSupp["s2"][$row["supplier_id"]])){
				if(empty($arMin[$row["model"]])) $arMin[$row["model"]] = $row["price"];
				if(empty($arPrice["s2"][$row["model"]]) || ($arSupp["s2"][$row["supplier_id"]] < $arPrice["s2"][$row["model"]]["day_delivery"] && (($row["price"] - $arMin[$row["model"]]) / $row["price"] * 100) < 5)){
					$row["day_delivery"] = $arSupp["s2"][$row["supplier_id"]];
					$row["working_time"] = $arWorking[$row["supplier_id"]];
					$arPrice["s2"][$row["model"]] = $row;
				}
			}
		}
		/*
		$strSql = "SELECT model, supplier_id, price FROM ci_price WHERE active_pl='Y'";// GROUP BY model";
		$results = $DB->Query($strSql, false, $err_mess.__LINE__);
		while ($row = $results->Fetch()){
			if(isset($arSupp["s3"][$row["supplier_id"]])){
				if(empty($arPrice["s3"][$row["model"]]) || $row["supplier_id"] == 71 || ($arPrice["s3"][$row["model"]]["supplier_id"] != 71 && $row["price"] < $arPrice["s3"][$row["model"]]["price"])){
					$row["day_delivery"] = $arSupp["s3"][$row["supplier_id"]];
					$row["working_time"] = $arWorking[$row["supplier_id"]];
					$arPrice["s3"][$row["model"]] = $row;
				}
			}
		}
*/
		$arMin = array();
		$strSql = "SELECT model, supplier_id, price FROM ci_price WHERE active_pl='Y' ORDER BY price ASC";// GROUP BY model";MIN(price) as 
		$results = $DB->Query($strSql, false, $err_mess.__LINE__);
		while ($row = $results->Fetch()){
			if(isset($arSupp["s3"][$row["supplier_id"]])){
				if(empty($arMin[$row["model"]])) $arMin[$row["model"]] = $row["price"];
				if(empty($arPrice["s3"][$row["model"]]) || ($arSupp["s3"][$row["supplier_id"]] < $arPrice["s3"][$row["model"]]["day_delivery"] && (($row["price"] - $arMin[$row["model"]]) / $row["price"] * 100) < 5)){
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
			}
		}
		CLog::add2log(array("event" => "DD", "text" => "Проанализировано - " . count($arItems)));
		
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
		if(!$arItem["model"] || !$arItem["brand_id"] || !$arItem["supplier_id"]) return false;
			
	
		$in = array(
			"active" => "'" . $arItem["active"] . "'",
			"active_by" => "'" . $arItem["active_by"] . "'",
			"active_pl" => "'" . $arItem["active_pl"] . "'",
			"active_wb" => "'" . $arItem["active_wb"] . "'",
			"active_opt" => "'" . $arItem["active_opt"] . "'",
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
			$DB->Update("ci_price", array("active" => "'".$arItem["active"]."'", "active_by" => "'".$arItem["active_by"]."'", "active_pl" => "'".$arItem["active_pl"]."'", "active_wb" => "'".$arItem["active_wb"]."'", "active_opt" => "'".$arItem["active_opt"]."'"), "WHERE model = '{$arItem["model"]}' AND brand_id = '{$arItem["brand_id"]}' AND supplier_id = '{$arItem["supplier_id"]}'", $err_mess.__LINE__);
		}
	}
	
	public static function deletePriceUnused(){
		global $DB;
		$DB->Query("DELETE FROM ci_price_unused WHERE active = 'Y' AND active_by = 'Y' AND active_pl = 'Y' AND active_wb = 'Y' AND active_opt = 'Y'", false, $err_mess.__LINE__);
	}
}