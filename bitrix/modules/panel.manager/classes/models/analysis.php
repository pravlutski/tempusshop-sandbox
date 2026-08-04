<?php
class CPanelAnalysis{
	function __construct(){

	}
	function getList($priceID){
		global $DB;
		if(!in_array($priceID, array("ru","by","pl","ya","os","wb","wbtl","av", "sb","kz","ozkz","ozti"))) return;
		$arr = array();
		$strSql = "SELECT * FROM ci_analysis WHERE price_id = '{$priceID}'";
		$results = $DB->Query($strSql, false, $err_mess.__LINE__);
		while ($row = $results->Fetch()){
			$arr[] = $row;
		}
		return $arr;
	}

	function getDetail( $id, $price_id ){
		global $DB;
		$id = (int)$id;
		if($id <= 0) return;
		$strSql = "SELECT * FROM ci_analysis WHERE id = '{$id}' AND price_id = '{$price_id}'";
		$results = $DB->Query($strSql, false, $err_mess.__LINE__);
		if ($row = $results->Fetch()){
			return $row;
		}
		return false;

	}
/*	function apply($id, $arSettings){
		$res = $this->setSettings($id, $arSettings);
		return $res;
	}*/
	function apply($arr = null) {
		global $DB;
		$id = intval($arr["id"]);
		$price_id = addslashes($arr["price_id"]);
		$brand_id = intval($arr["brand_id"]);
		$collection_id = intval($arr["selected_collection_id"]);
		$article = trim($arr["article_value"]);
		$settings = $arr["settings"];

		$file = 'test.txt';
		$current = file_get_contents($file);
		$current .= print_r($arr, true);
		file_put_contents($file, $current);

		if ($brand_id <= 0) return false;

		$in = array(
			"brand_id" => "'" . $brand_id . "'",
			"settings" => "'" . json_encode($settings, JSON_UNESCAPED_UNICODE) . "'",
			"collection_id" => ($collection_id > 0) ? "'" . $collection_id . "'" : "NULL",
			"article" => ($article != '') ? "'" . $article . "'" : "NULL"
		);

		if ($this->isProfile($id)) {
			$strSql = "SELECT id, collection_id, article FROM ci_analysis WHERE id <> '{$id}' AND brand_id = '{$brand_id}' AND price_id = '{$price_id}'";
			if ($collection_id > 0) {
				$strSql .= " AND collection_id = '{$collection_id}'";
			}
			if ($article != '') {
				$strSql .= " AND article = '{$article}'";
			}
			$results = $DB->Query($strSql, false, $err_mess . __LINE__);
			if ($row = $results->Fetch()) {
				// Если найден другой профиль с такими же параметрами, возвращаем false, чтобы не обновлять
				return false;
			} else {
				// Если другого профиля с такими параметрами не найдено, обновляем существующий
				$DB->Update("ci_analysis", $in, "WHERE id='" . $id . "'", $err_mess . __LINE__);
				return true;
			}
		} else {
			// Проверяем, есть ли профиль с такими же параметрами (бренд, регион, коллекция, артикул)
			$strSql = "SELECT id FROM ci_analysis WHERE brand_id = '{$brand_id}' AND price_id = '{$price_id}'";
			if ($collection_id > 0) {
				$strSql .= " AND collection_id = '{$collection_id}'";
			}
			if ($article != '') {
				$strSql .= " AND article = '{$article}'";
			}
			$strSql .= " LIMIT 1";
			$results = $DB->Query($strSql, false, $err_mess . __LINE__);
			if (!$row = $results->Fetch()) {
				// Если профиля с такими параметрами не найдено, создаем новый
				$in["price_id"] = "'" . $price_id . "'";
				$ID = $DB->Insert("ci_analysis", $in, $err_mess . __LINE__);
				if ($ID > 0) return true;
			} else {
				// Если найден другой профиль с такими же параметрами, возвращаем false, чтобы не создавать дубликат
				return false;
			}
		}

		return false;
	}

	function delete( $id ){
		global $DB;
		$id = intval( $id );
		if($id <= 0) return;
		if($this->isProfile($id)){
			$DB->Query("DELETE from ci_analysis WHERE id = '".$id."'", false, $err_mess.__LINE__);
			return true;
		} else return false;
	}

	function isProfile( $id ){
		global $DB;
		$id = (int)$id;
		$strSql = "SELECT id FROM ci_analysis WHERE id = {$id} LIMIT 1";
		$results = $DB->Query($strSql, false, $err_mess.__LINE__);
		if ($row = $results->Fetch()){
			return true;
		}
		return false;
	}
	function getListByFilter($arFilter = array()){
		global $DB;
		$arr = array();
		$strSql = "SELECT * FROM ci_analysis";
		$filter = array();

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
					$filter[] = "brand_id IN (" . implode(",", $ar).")";
				}
			}else{
				$item = intval($arFilter["brand_id"]);
				if($item > 0)
					$filter[] = "brand_id = '" . $item . "'";
			}
		}
		/************** по сайту ****************/
		if(isset($arFilter["site_id"])){
			if(is_array($arFilter["site_id"])){
				$ar = array();
				foreach($arFilter["site_id"] as $item){
					$ar[$item] = $item;
				}
				if(count($ar) > 0){
					$filter[] = "site_id IN (" . implode(",", $ar).")";
				}
			}else{
				$filter[] = "site_id = '" . $arFilter["site_id"] . "'";
			}
		}
		if(isset($arFilter["price_id"])){
			if(is_array($arFilter["price_id"])){
				$ar = array();
				foreach($arFilter["price_id"] as $item){
					$ar[$item] = $item;
				}
				if(count($ar) > 0){
					$filter[] = "price_id IN (" . implode(",", $ar).")";
				}
			}else{
				$filter[] = "price_id = '" . $arFilter["price_id"] . "'";
			}
		}
		/* по id */
		if(isset($arFilter["id"])){
			if(is_array($arFilter["id"])){
				$ar = array();
				foreach($arFilter["id"] as &$item){
					$item = intval($item);
					if($item > 0)
						$ar[$item] = $item;
				}
				if(count($ar) > 0){
					$filter[] = "id IN (" . implode(",", $ar).")";
				}
			}else{
				$item = intval($arFilter["id"]);
				if($item > 0)
					$filter[] = "id = '" . $item . "'";
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
		$strSql .= " ORDER BY id asc";

		$results = $DB->Query($strSql, false, $err_mess.__LINE__);
		while ($row = $results->Fetch()){
			$arr[] = $row;
		}
		return $arr;
	}

	private function add($supplier_id, $arBrand, $data, $optins = array()){
		$res["true"] = $res["bad"] = 0;
		if($supplier_id < 0 || count($arBrand) <= 0 || count($data) <= 0)
			return $res;
		//удаляем старые связки
		if($optins["price_no_clear"] != "on"){
			foreach($arBrand as $key => $arItem){
				$this->db->delete('ci_price', array('supplier_id' => $supplier_id, 'brand_id'=> $arItem["id"]));
				$this->db->delete('ci_pricelist', array('supplier_id' => $supplier_id, 'brand_id'=> $arItem["id"]));
	//			$res["true"] = $res["bad"] = 0;
			}
		}

		//пишем
		foreach($data as $key => $arItem){
			if($optins["price_r"] == "on")
				$arItem["price"] = 0;
			$in = array(
				"model" => $arItem["article"],
				"brand_id" => $arItem["brand_id"],
				"supplier_id" => $arItem["supplier_id"],
				"store_id" => 3,
				"price" => $arItem["price"]
			);
			//$this->db->insert('ci_price', $in);
			if($this->db->insert('ci_price', $in))
				$res["true"]++;
			else
				$res["bad"]++;
		}
		unset($arItem);
		if($optins["price_no_clear"] != "on"){
			foreach($arBrand as $key => $arItem){
				$in = array(
					"brand_id" 		=> $arItem["id"],
					"supplier_id" 	=> $supplier_id,
				);
				$this->db->insert('ci_pricelist', $in);
			}
		}
		return $res;

	}

	function isPricelist( $id ){
		$id = (int)$id;
		$res = $this->db->query("SELECT id FROM ci_pricelist WHERE id = {$id} LIMIT 1");
		if( $res->num_rows() == 1 ){
			if( $res->row()->id > 0 ) return true;
			else return false;
		} else return false;
	}
	function getPricelistDetail( $id ){
		if( $res = $this->db->query("SELECT * FROM ci_pricelist WHERE id = '{$id}'") and $res->num_rows() > 0 ){
			$arr = $res->result_array();
			return $arr[0];
		} else return false;
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

		if( $supplier_id <= 0 || $brand_id <= 0 ) return false;

		if( $res = $this->db->query("SELECT * FROM ci_price WHERE supplier_id = {$supplier_id} AND brand_id = {$brand_id} ORDER BY model asc") and $res->num_rows() > 0 ){
			$arr = array();
			foreach( $res->result_array() as $price )
				$arr[] = $price;
			return $arr;
		} else return false;
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
	function getPriceByFilter($arFilter = array()){
		global $DB;
		$arr = array();
		$strSql = "SELECT * FROM ci_price";
		$filter = array();
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
					$filter[] = "supplier_id IN (" . implode(",", $ar).")";
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
					$filter[] = "brand_id IN (" . implode(",", $ar).")";
				}
			}else{
				$item = intval($arFilter["brand_id"]);
				if($item > 0)
					$filter[] = "brand_id = '" . $item . "'";
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
		$strSql .= " ORDER BY model asc";

		$results = $DB->Query($strSql, false, $err_mess.__LINE__);
		while ($row = $results->Fetch()){
			$arr[] = $row;
		}
		return $arr;
	}


}
