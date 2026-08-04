<?php
class CPanelCurrency{
	function __construct(){

	}
	function getList(){
		global $DB;
		$arr = array();
		$strSql = "SELECT * FROM ci_currency ORDER BY sort asc";
		$results = $DB->Query($strSql, false, $err_mess.__LINE__);
		while ($row = $results->Fetch()){
			$arr[$row["id"]] = $row;
		}
		return $arr;
	}

	function getDetail( $curr_id ){
		global $DB;
		$strSql = "SELECT * FROM ci_currency WHERE id = '{$curr_id}'";
		$results = $DB->Query($strSql, false, $err_mess.__LINE__);
		if ($row = $results->Fetch()){
			return $row;
		}
		return false;
	}
	function apply( $arr = null ){
		$arCurrency = array();
		if(isset($arr["currency"]) && count($arr["currency"]) > 0){
			foreach($arr["currency"] as $curr_id => $rate){
				if($this->getDetail( $curr_id )){
					$rate = str_replace(',', '.', $rate);
					$arCurrency[$curr_id] = array(
						"id" 			=> $curr_id,
						"rate"			=> (float) $rate
					);
				}
			}
		}
		$err = false;
		foreach($arCurrency as $key => $arItem){
			if($this->setCurrency( $arItem["id"], $arItem["rate"] ) === false){
				$err = true;
			}
		}
		if($err === false) return true; else return false;

	}
	function setCurrency( $curr_id,  $rate ){
		global $DB;
		if( $this->isCurr( $curr_id ) ){
			$DB->Update("ci_currency", array("rate" => "'".$rate."'"), "WHERE id='".$curr_id."'", $err_mess.__LINE__);
			return true;
		} else return false;
	}

	function isCurr( $curr_id ){
		global $DB;
		$strSql = "SELECT `id` FROM ci_currency WHERE `id` = '".$curr_id."' LIMIT 1";
		$results = $DB->Query($strSql, false, $err_mess.__LINE__);
		if ($row = $results->Fetch()){
			return true;
		}
		return false;
	}

}
