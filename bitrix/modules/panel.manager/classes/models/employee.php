<?php
class CPanelEmployee{
	function __construct(){
		
	}
	
	function getList(){
		global $DB;
		$arr = array();
		
		$strSql = "SELECT * FROM ci_employees ORDER BY sort";
		$results = $DB->Query($strSql, false, $err_mess.__LINE__);
		while ($row = $results->Fetch()){
			$arr[] = $row;
		}
		return $arr;
	}

	function getDetail( $id ){
		global $DB;
		$id = (int)$id;
		$strSql = "SELECT * FROM ci_employees WHERE id = '{$id}'";
		$results = $DB->Query($strSql, false, $err_mess.__LINE__);
		if ($row = $results->Fetch()){
			return $row;
		}
		return false;
		
	}
	
	function getName( $id ){
		global $DB;
		$id = (int)$id;
		$strSql = "SELECT name FROM ci_employees WHERE id = '{$id}'";
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
			$DB->Update("ci_employees", $in, "WHERE id='".$id."'", $err_mess.__LINE__);
			return true;
		}else{
			$ID = $DB->Insert("ci_employees", $in, $err_mess.__LINE__);
			if($ID > 0) return true;
		}
		return false;

	}
	function isCourier( $id ){
		global $DB;
		$id = (int)$id;
		if($id <= 0) return;
		$strSql = "SELECT id FROM ci_employees WHERE id = {$id} LIMIT 1";
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
			$DB->Query("DELETE FROM ci_employees WHERE id = '".$id."'", false, $err_mess.__LINE__);
			return true;
		} else return false;
	}
}