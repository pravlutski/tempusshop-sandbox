<?php
class CPanelCourier{
	function __construct(){
		
	}
	
	function getList(){
		global $DB;
		$arr = array();
		
		$strSql = "SELECT * FROM ci_couriers ORDER BY sort";
		$results = $DB->Query($strSql, false, $err_mess.__LINE__);
		while ($row = $results->Fetch()){
			$arr[] = $row;
		}
		return $arr;
	}

	function getDetail( $brand_id ){
		global $DB;
		$brand_id = (int)$brand_id;
		$strSql = "SELECT * FROM ci_couriers WHERE id = '{$brand_id}'";
		$results = $DB->Query($strSql, false, $err_mess.__LINE__);
		if ($row = $results->Fetch()){
			return $row;
		}
		return false;
		
	}
	
	function getName( $brand_id ){
		global $DB;
		$brand_id = (int)$brand_id;
		$strSql = "SELECT name FROM ci_couriers WHERE id = '{$brand_id}'";
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
		$full_name = addslashes($arr["full_name"]);
		$sort = abs(intval($arr["sort"]));
		if(strlen($name) < 3 || strlen($name) > 255) return false;
		$in = array(
			"name" => "'".$name."'",
			"full_name" => "'".$full_name."'",
			"sort" => "'".$sort."'",
		);
		if($this->isCourier($id)){
			$DB->Update("ci_couriers", $in, "WHERE id='".$id."'", $err_mess.__LINE__);
			return true;
		}else{
			$ID = $DB->Insert("ci_couriers", $in, $err_mess.__LINE__);
			if($ID > 0) return true;
		}
		return false;

	}
	function isCourier( $id ){
		global $DB;
		$id = (int)$id;
		if($id <= 0) return;
		$strSql = "SELECT id FROM ci_couriers WHERE id = {$id} LIMIT 1";
		$results = $DB->Query($strSql, false, $err_mess.__LINE__);
		if ($row = $results->Fetch()){
			return true;
		}
		return false;
	}
	function delete( $id ){
		global $DB;
		$id = intval( $id );
		if( $this->isCourier( $id ) ){
			$DB->Query("DELETE FROM ci_couriers WHERE id = '".$id."'", false, $err_mess.__LINE__);
			return true;
		} else return false;
	}
}