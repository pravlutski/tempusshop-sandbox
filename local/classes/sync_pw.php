<?php
/**
 * CSyncPW синхронизация с PW
 */
class CSyncPW{

	public function  __construct(){
	}
    function addSync($in = array()) {
		global $USER;
		$in["XML_ID"] = intval($in["XML_ID"]);

		if($in["XML_ID"] <= 0) return false;
		//if(!$in["site_id"]) $in["site_id"] = SITE_ID;
		self::add($in);
    }
	private function add($arr){
		global $DB;
		$in = array(
			"XML_ID" => "'".addslashes($arr["XML_ID"])."'",
			"TYPE" => "'".addslashes($arr["TYPE"])."'",
			"ACTION" => "'".addslashes($arr["ACTION"])."'",
		);
		$DB->Insert("ci_sync", $in, $err_mess.__LINE__);
    }

	public function GetList($arSort = array(), $arFilter = array(), $limit = false) {
        global $DB;
        $s = array();
        $w = array("1=1");
		
        // sorting
        if(!empty($arSort)) {
            foreach($arSort as $field => $order) {
                $field = strtoupper($field);

                if(in_array($field, self::$arAvailFields)) {
                    $order = $order == "DESC" ? "DESC" : "ASC";
                    $s[] = " ".$field." ".$order." ";
                }
            }
        }
        if(empty($s))
            $s[] = " ID ASC ";

        // filtering
        foreach($arFilter as $field => $val) {
            $field = strtoupper($field);

            if($val === null) {
                $w[] = " ".$field." IS NULL ";
            } elseif(is_array($val)) {
                $w[] = " " . $field . " IN ('" . implode("','", $val) . "') ";
            } else {
                $w[] = " ".$field." = '".$val."' ";
            }
        }

        // executing
        $sql = "SELECT * FROM ci_sync WHERE ".implode(" AND ", $w)." ORDER BY ".implode(", ", $s) . ($limit > 0 ? " LIMIT 0,{$limit}" : "");
		$results = $DB->Query($sql, false, $err_mess.__LINE__);
		$ar = array();
		while ($row = $results->Fetch()){
			$ar[] = $row;
		}
		return $ar;
    }
	
	public function setStatus($ar = array(), $status = "new") {
		if(count($ar) <= 0 || !in_array($status, array("new", "processed", "finished"))) return false;
        global $DB;
		$in = array(
			"STATUS"  => "'$status'",
		);
		$DB->Update("ci_sync", $in, "WHERE ID IN ('" . implode("','", $ar) . "')", $err_mess.__LINE__);
	}
}