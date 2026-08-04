<?php
class CPanelBrand{
	function __construct(){

	}

	function getList(){
		global $DB;
		$arr = array();

		$strSql = "SELECT * FROM ci_brands ORDER BY sort";
		$results = $DB->Query($strSql, false, $err_mess.__LINE__);
		while ($row = $results->Fetch()){
			$arr[] = $row;
		}
		return $arr;
	}

	function getListCollection($id){
		global $DB;
		$arr = '';

		$strSql = "SELECT * FROM `ci_section` WHERE `id` = '{$id}'";
		$results = $DB->Query($strSql, false, $err_mess.__LINE__);
		while ($row = $results->Fetch()){
			$arr = $row["name"];
		}
		return $arr;
	}

	function getDetail( $brand_id ){
		global $DB;
		$brand_id = (int)$brand_id;
		$strSql = "SELECT * FROM ci_brands WHERE id = '{$brand_id}'";
		$results = $DB->Query($strSql, false, $err_mess.__LINE__);
		if ($row = $results->Fetch()){
			return $row;
		}
		return false;

	}

	function getName( $brand_id ){
		global $DB;
		$brand_id = (int)$brand_id;
		$strSql = "SELECT name FROM ci_brands WHERE id = '{$brand_id}'";
		$results = $DB->Query($strSql, false, $err_mess.__LINE__);
		if ($row = $results->Fetch()){
			return $row["name"];
		}
		return false;
	}

	function apply( $arr = null ){
		global $DB;
		$id = intval($arr["id"]);
		$name = addslashes($arr["name"]);
		$alt_name = addslashes($arr["alt_name"]);
		$regular = addslashes($arr["regular"]);
		$sort = abs(intval($arr["sort"]));
		$bitrix_id = abs(intval($arr["bitrix_id"]));

		$margin_ru = addslashes($arr["margin_ru"]);
		$margin_by = addslashes($arr["margin_by"]);
		$margin_pl = addslashes($arr["margin_pl"]);
		$margin_ya = addslashes($arr["margin_ya"]);
		$margin_os = addslashes($arr["margin_os"]);
		$margin_wb = addslashes($arr["margin_wb"]);
		$margin_sb = addslashes($arr["margin_sb"]);

		$regular_search = addslashes($arr["regular_search"]);
		$regular_replace = addslashes($arr["regular_replace"]);

		if($bitrix_id == 0) $bitrix_id = "";
		if(strlen($name) < 2 || strlen($name) > 255) return false;
		$in = array(
			"name" => "'".$name."'",
			"alt_name" => "'".$alt_name."'",
			"regular" => "'".$regular."'",
			"sort" => "'".$sort."'",
			"bitrix_id" => "'".$bitrix_id."'",
			"margin_ru" => "'".$margin_ru."'",
			"margin_by" => "'".$margin_by."'",
			"margin_pl" => "'".$margin_pl."'",
			"margin_ya" => "'".$margin_ya."'",
			"margin_os" => "'".$margin_os."'",
			"margin_wb" => "'".$margin_wb."'",
			"margin_sb" => "'".$margin_sb."'",
			"regular_search" => "'".$regular_search."'",
			"regular_replace" => "'".$regular_replace."'",
		);

		if($this->isBrand($id)){
			//prent($in);
			$DB->Update("ci_brands", $in, "WHERE id='".$id."'", $err_mess.__LINE__);
			return true;
		}else{
			//return true;
			$ID = $DB->Insert("ci_brands", $in, $err_mess.__LINE__);
			if($ID > 0) return $ID;
		}
		return false;

	}
	function isBrand( $id ){
		global $DB;
		$id = (int)$id;
		if($id <= 0) return;
		$strSql = "SELECT id FROM ci_brands WHERE id = {$id} LIMIT 1";
		$results = $DB->Query($strSql, false, $err_mess.__LINE__);
		if ($row = $results->Fetch()){
			return true;
		}
		return false;
	}
	function delete( $id ){
		global $DB;
		$id = intval( $id );
		if( $this->isBrand( $id ) ){
			$DB->Query("DELETE from ci_brands WHERE id = '".$id."'", false, $err_mess.__LINE__);
			return true;
		} else return false;
	}

	function getCollections( $brand_id = null ): array
	{
		global $DB;
		$arr = array();
		$strSql = "SELECT * FROM ci_section WHERE parent_id IS NOT NULL".($brand_id ? " AND parent_id = '".$brand_id."'":'');
		$results = $DB->Query($strSql, false, $err_mess.__LINE__);
		while ($row = $results->Fetch()){
			$arr[] = $row;
		}
		return $arr;
	}

	function searchBrands( $nameBrand = null )
	{
		global $DB;
		$arr = '';

		$strSql = "SELECT * FROM `ci_section` WHERE CONVERT(`name` USING utf8mb4) = 'Наручные часы / {$nameBrand}'";
		$results = $DB->Query($strSql, false, $err_mess.__LINE__);

		while ($row = $results->Fetch()){
			$arr = $row;
		}

		return $arr["id"];
	}
}
