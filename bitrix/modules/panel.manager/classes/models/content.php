<?php
class CPanelContent{
	function __construct(){
		
	}
	function init(){

	}
	function getTasks( $status = null ){
		global $DB;
		global $USER;
		$arr = array();
		$now = time();
		
		if( $status !== null )
			$strSql = "SELECT * FROM ci_task WHERE (`status`='{$status}' and `taken` + 900 < '{$now}') OR user_id = '".$USER->getID()."'";
		else $strSql = 'SELECT * FROM ci_task';

		$results = $DB->Query($strSql, false, $err_mess.__LINE__);
		$arPar = array();
		$param_item = array();
		while ($row = $results->Fetch()){
			$arr[] = $row;
		}
		return $arr;
	}
	function getTask( $id ){
		global $DB;
		$id = (int)$id;
		if($id <= 0) return;
		$strSql = "SELECT * FROM ci_task WHERE id = '$id' LIMIT 1";
		$results = $DB->Query($strSql, false, $err_mess.__LINE__);
		if ($row = $results->Fetch()){
			return $row;
		}
		return false;
	}
	function takeTask( $id, $user_id ){
		global $DB;
		$task = $this->getTask($id);
		$now = time();
	//	if( ($task['status'] == 1 || $task['status'] == 2) && $task['taken'] + 900 < $now ){
		if( ($task['status'] == 1 || $task['status'] == 2) ){
			$id = (int)$id;
			$user_id = (int)$user_id;
			$DB->Update("ci_task", array("user_id" => "'".$user_id."'", "taken" => "'".$now."'"), "WHERE id='".$id."'", $err_mess.__LINE__);
			return true;
		} else {
			$strSql = "SELECT * FROM ci_task WHERE id = '{$id}' AND user_id = '{$user_id}'";
			$results = $DB->Query($strSql, false, $err_mess.__LINE__);
			if ($row = $results->Fetch()){
				return true;
			}
		}
		return false;
	}
	function removeTask( $id ){
		global $DB;
		$id = (int)$id;
		if( $id > 0 )
			$DB->Query("DELETE from ci_task WHERE id = '".$id."'", false, $err_mess.__LINE__);
	}
	function removeAllTask(){
		global $DB;
		$DB->Query("TRUNCATE TABLE ci_task", false, $err_mess.__LINE__);
	}
	function addTasks( $models, $brand ){
		global $DB;
		$stat['count'] = count_( $models );
		$res = $this->getTasks();
		foreach( $res as $row )
			$tasks[] = $row['model'];
		$models = array_unique($models);
		$stat['count_uniq'] = count_( $models );
		foreach( $models as $key => $value ){
			$model = trim( $value );
			if( $model != '' && !CPanelProduct::findArticle($model)){
				if( isset( $tasks ) && in_array_($model, $tasks) ) continue;
				$arr[] = array(
					'brand_id'  => "'".intval($brand)."'",
					'model'     => "'".addslashes($model)."'"
				);
			}
		}
		if( isset( $arr ) && count_( $arr ) > 0 ){
			$stat['count_new'] = count_( $arr );
			//prent($arr);die;
			//VR35JB14Y
			foreach($arr as $in){
				$strSql = "SELECT * FROM ci_task WHERE brand_id = ".$in["brand_id"]." AND model = ".$in["model"]."";
				$results = $DB->Query($strSql, false, $err_mess.__LINE__);
				if (!$row = $results->Fetch()){
					$DB->Insert("ci_task", $in, $err_mess.__LINE__);
				}
			}
			

		} else $stat['count_new'] = 0;
		return $stat;
	}
	function returnTask( $id ){
		global $DB;
		$id = (int)$id;
		if($this->isTask($id)){
			$strSql = "SELECT * FROM ci_application WHERE id='".$id."'";
			$results = $DB->Query($strSql, false, $err_mess.__LINE__);
			if ($row = $results->Fetch()){
				//$fields = json_decode($row["fields"], true );
				// !временно / потом оставить json_decode
				if(isJSON($row["fields"])){
					$fields = json_decode($row["fields"], true );
				}else{
					$fields = unserialize($row["fields"]);
				}
				if($fields["brand"] > 0 && strlen($fields["artnumber"]) > 0){
					$strSql2 = "SELECT * FROM ci_task WHERE brand_id = '{$fields["brand"]}' AND model = '{$fields["artnumber"]}'";
					$results2 = $DB->Query($strSql2, false, $err_mess.__LINE__);
					if ($row2 = $results2->Fetch()){
						$in = array(
							"status"	=> "2",
							"app_id"	=> $row["id"],
						);
						$DB->Update("ci_task", $in, "WHERE id='".$row2["id"]."'", $err_mess.__LINE__);
						return "exists";
					}else{
						$in = array(
							'brand_id'  => "'".$fields["brand"]."'",
							'model'     => "'".$fields["artnumber"]."'",
							"status"	=> "2",
							"app_id"	=> $row["id"],
						);
						$DB->Insert("ci_task", $in, $err_mess.__LINE__);
						return "ok";
					}

				}

			}
		}
		return false;
	}
	function isTask( $id ){
		global $DB;
		$id = (int)$id;
		if($id > 0){
			$strSql = "SELECT * FROM ci_application WHERE id='".$id."'";
			$results = $DB->Query($strSql, false, $err_mess.__LINE__);
			if ($row = $results->Fetch()){
				return $row;
			}
		}
	}
	function getSaveProps( $id ){
		global $DB;
		$id = (int)$id;
		$def = array();
		$strSql = "SELECT * FROM ci_application WHERE id='".$id."'";
		$results = $DB->Query($strSql, false, $err_mess.__LINE__);
		if ($row = $results->Fetch()){
			//$def1 = json_decode( $row["fields"], true );
			// !временно / потом оставить json_decode
			if(isJSON($row["fields"])){
				$def1 = json_decode($row["fields"], true );
			}else{
				$def1 = unserialize($row["fields"]);
			}
			//if(is_array($def1)){
			//	unset($def1["brand"], $def1["collection"], $def1["artnumber"]);
			//}
			$def2 = json_decode( $row["props"], true );
			$def = array_merge($def1, $def2);
			$def["DETAIL_TEXT_TYPE"] = $row["detail_text_type"];
			$def["DETAIL_TEXT"] = $row["detail_text"];
		}
		return $def;
	}
	function getBrands(){
		global $DB;
		$arr = array();
		$strSql = "SELECT * FROM ci_section WHERE parent_id IS NULL";
		$results = $DB->Query($strSql, false, $err_mess.__LINE__);
		while ($row = $results->Fetch()){
			$arr[] = $row;
		}
		return $arr;
	}
	function getCollections( $brand_id = null ){
		global $DB;
		$arr = array();
		$strSql = "SELECT * FROM ci_section WHERE parent_id IS NOT NULL".($brand_id ? " AND parent_id = '".$brand_id."'":'');
		$results = $DB->Query($strSql, false, $err_mess.__LINE__);
		while ($row = $results->Fetch()){
			$arr[] = $row;
		}
		return $arr;
	}
	function getSection( $id ){
		global $DB;
		$id = (int)$id;
		$strSql = "SELECT * FROM ci_section WHERE id='".$id."' LIMIT 1";
		$results = $DB->Query($strSql, false, $err_mess.__LINE__);
		if ($row = $results->Fetch()){
			return $row;
		}
		return false;
	}
	
	function getParentSection( $id ){
		global $DB;
		$id = (int)$id;
		if($id <= 0) return false;
		$strSql = "SELECT IBLOCK_SECTION_ID FROM b_iblock_section WHERE ID='".$id."' LIMIT 1";
		$results = $DB->Query($strSql, false, $err_mess.__LINE__);
		if ($row = $results->Fetch()){
			return $row["IBLOCK_SECTION_ID"];
		}
		return false;
	}
	
	function updateSections( $arr ){
		global $DB;
		$arIDs = array();
		foreach( $arr['brands'] as $brand_id => $brand ){
			$DB->Query("INSERT INTO ci_section (`id`, `name`) VALUES ('".$brand_id."','".$brand."')
                    ON DUPLICATE KEY UPDATE name = '".$brand."'", false, $err_mess.__LINE__);
					
			$arIDs[$brand_id] = $brand_id;
		}			
		foreach( $arr['col'] as $parent_id => $col ){
			foreach( $col as $col_id => $col_name ){
				$strSql = "SELECT id FROM ci_section WHERE id = '{$col_id}'";
				$results = $DB->Query($strSql, false, $err_mess.__LINE__);
				if (!$row = $results->Fetch()){
					$in = array(
						"id" => "'".$col_id."'",
						"name" => "'".$col_name."'",
						"parent_id" => "'".$parent_id."'",
					);
					$DB->Insert("ci_section", $in, $err_mess.__LINE__);
				}else{
					$in = array(
						"name" => "'".$col_name."'",
						"parent_id" => "'".$parent_id."'",
					);
					$DB->Update("ci_section", $in, "WHERE id='".$col_id."'", $err_mess.__LINE__);
				}
				$arIDs[$col_id] = $col_id;
			}
		}
		//выбираем все записи и удаляем если старый
		$strSql = "SELECT * FROM ci_section WHERE id NOT IN ('".implode("','",$arIDs)."')";
		$results = $DB->Query($strSql, false, $err_mess.__LINE__);
		while ($row = $results->Fetch()){
			$ar[$row['id']] = $row['id'];
		}
		foreach($ar as $col_id){
			$DB->Query("DELETE from ci_section WHERE id = '".$col_id."'", false, $err_mess.__LINE__);
		}
		//prent($ar);die;
	}
	function getStructure(){
		global $DB;
		$arr = array();
		$strSql = "SELECT * FROM ci_section";
		$results = $DB->Query($strSql, false, $err_mess.__LINE__);
		while ($row = $results->Fetch()){
			if( $row['parent_id'] != '' )
				$arr[ $row['parent_id'] ] [ $row['id'] ] = $row['name'];
		}
		return $arr;
	}
	function getStructureAr(){
		global $DB;
		$arr = array();
		$strSql = "SELECT * FROM ci_section";
		$results = $DB->Query($strSql, false, $err_mess.__LINE__);
		while ($row = $results->Fetch()){
			if($row['parent_id'] != '')
				$arr[$row['parent_id']][] = [
					"ID" => $row['id'],
					"NAME" => $row['name'],
				];
		}
		return $arr;
	}
	function getProps(){
		global $DB;
		$arr = array();
		$strSql = "SELECT * FROM ci_property ORDER BY sort";
		$results = $DB->Query($strSql, false, $err_mess.__LINE__);
		while ($row = $results->Fetch()){
			$arr[$row['code']] = array(
				'id'    => $row['code'],
				'name'  => $row['name'],
				'values'=> json_decode($row['value'], true),
				'is_multiple'=> $row['is_multiple'],
				'sort'=> $row['sort'],
				'sort2'=> $row['sort2'],
				'property_type'=> $row['property_type'],
			);
		}
		return $arr;
	}
	function getProfile($profile_id = 0){
		global $DB;
		$profile_id = intval($profile_id);
		if($profile_id <= 0) return false;

		$strSql = "SELECT * FROM ci_profile WHERE ID = '{$profile_id}'";
		$results = $DB->Query($strSql, false, $err_mess.__LINE__);
		if ($row = $results->Fetch()){
			$arr = array(
				'ID'    => $row['ID'],
				'PROPS'=> json_decode($row['PROPS'], true),

			);
			return $arr;
		}
		return false;
	}
	function setProfile($profile_id = 0, $props = array()){
		global $DB;
		$profile_id = intval($profile_id);
		if($profile_id <= 0) return false;
		
		$props = json_encode($props, JSON_UNESCAPED_UNICODE);
		
		$strSql = "SELECT * FROM ci_profile WHERE ID = '{$profile_id}'";
		$results = $DB->Query($strSql, false, $err_mess.__LINE__);
		if ($row = $results->Fetch()){
			$DB->Update("ci_profile", array("PROPS" => "'".addslashes($props)."'"), "WHERE ID='".$row["ID"]."'", $err_mess.__LINE__);
		}else{
			$in = array(
				'ID'	=> "'".addslashes($profile_id)."'",
				'PROPS'	=> "'".addslashes($props)."'",
			);
			$DB->Insert("ci_profile", $in, $err_mess.__LINE__);
		}

	}
	function updateProps( $arr ){
		global $DB;
		$props = $this->getProps();
		foreach( $props as $code => $prop )
			if( array_key_exists($code, $arr) ){
				$values = json_encode($arr[$code], JSON_UNESCAPED_UNICODE );
				$DB->Update("ci_property", array("value" => "'".$values."'"), "WHERE code='".$code."'", $err_mess.__LINE__);
				//TODO Объеденить в один запрос
			}
	}
	
	function addUpdateProps( $props ){
		global $DB;
		$arCodes = [];
		foreach( $props as $key => $prop ){
			$strSql = "SELECT * FROM ci_property WHERE code = '".$prop["CODE"]."' LIMIT 1";
			$results = $DB->Query($strSql, false, $err_mess.__LINE__);
			if ($row = $results->Fetch()){
				$in = array(
					'name'  		=> "'".addslashes($prop["NAME"])."'",
					'sort'     		=> "'".addslashes($prop["SORT"])."'",
					'property_type'	=> "'".addslashes($prop["PROPERTY_TYPE"])."'",
					'is_multiple'	=> "'".addslashes($prop["MULTIPLE"])."'",
				);
				$DB->Update("ci_property", $in, "WHERE code='".$row["code"]."'", $err_mess.__LINE__);
				
				$arCodes[] = $prop["CODE"];
			}else{
				$in = array(
					'name'  		=> "'".addslashes($prop["NAME"])."'",
					'code'  		=> "'".addslashes($prop["CODE"])."'",
					'sort'     		=> "'".addslashes($prop["SORT"])."'",
					'property_type'	=> "'".addslashes($prop["PROPERTY_TYPE"])."'",
					'is_multiple'	=> "'".addslashes($prop["MULTIPLE"])."'",
				);
				$insert_id = $DB->Insert("ci_property", $in, $err_mess.__LINE__);
				$arCodes[] = $prop["CODE"];
			}

		}
		
		// удаляем чего нет в массиве
		if(count($arCodes) > 0){
			$sql = "DELETE from ci_property WHERE code NOT IN ('" . implode("','", $arCodes) . "')";
			$DB->Query($sql, false, $err_mess.__LINE__);
		}
	}
	function isBrand( $id ){
		global $DB;
		$id = (int)$id;
		$strSql = "SELECT parent_id FROM ci_section WHERE id = '".$id."' LIMIT 1";
		$results = $DB->Query($strSql, false, $err_mess.__LINE__);
		if ($row = $results->Fetch()){
			if( $row["parent_id"] == '' ) return true;
		}
		return false;
	}
	function getBrandPrice( $id ){
		global $DB;
		$id = (int)$id;
		if( $this->isBrand( $id ) ){
			$strSql = "SELECT defaults FROM ci_section WHERE id = '".$id."' LIMIT 1";
			$results = $DB->Query($strSql, false, $err_mess.__LINE__);
			if ($row = $results->Fetch()){
				$def = json_decode( $row["defaults"], true );
				if( is_array($def) and array_key_exists('price', $def) )
					return $def['price'];
			}
		}
		return 0;
	}
	function getDefaults( $id ){
		global $DB;
		$id = (int)$id;
		$def = array();
		$strSql = "SELECT defaults FROM ci_section WHERE id = '".$id."' LIMIT 1";
		$results = $DB->Query($strSql, false, $err_mess.__LINE__);
		if ($row = $results->Fetch()){
			$def = json_decode( $row["defaults"], true );
			if( is_array($def) ){
				unset($def['price']);
			}
		}
		return $def;
	}

	function addApply( $arr, $task_id = 0 ){
		global $DB, $USER;
		$user_id = $USER->getID();

		foreach( $arr as $code => $value ){
			if ($code[0] == '_'){
				$props[substr($code, 1, strlen($code) - 1)] = $value;
			} elseif(in_array_($code, array("DETAIL_TEXT", "DETAIL_TEXT_TYPE"))){
				${$code} = $value;
			}elseif($code == "img_watch"){
				foreach($value as $k => $v){
					if($v){
						$fields[$code][] = $v;
					}
				}
				
			}else{
				$fields[$code] = addslashes($value);
			}
		}

		if($USER->getID() == 587){
			//prent($DETAIL_TEXT);prent($DETAIL_TEXT);prent($DETAIL_TEXT_TYPE);prent($arr);die;
		}
		//prent($fields["DETAIL_TEXT"]);
		$DETAIL_TEXT = self::closeTags($DETAIL_TEXT);
		//prent($fields["DETAIL_TEXT"]); 
		//prent($props);
		//prent($fields);
		if( isset( $props ) && isset( $fields ) && count_($fields) >= 6 ){
			$price = $this->getBrandPrice( $fields['brand'] );
			$fields = json_encode($fields, JSON_UNESCAPED_UNICODE);
			$props  = json_encode($props, JSON_UNESCAPED_UNICODE);
			
			$status = ($USER->isAdmin() ? 'W' : 'N');
			
			$in = array(
				"user_id" => "'".$user_id."'",
				"fields" => "'".$fields."'",
				"props" => "'".$props."'",
				"status" => "'".$status."'",
				"price" => "'".$price."'",
				"detail_text" => "'".addslashes($DETAIL_TEXT)."'",
				"detail_text_type" => "'".addslashes($DETAIL_TEXT_TYPE)."'",
			);
			
			$app = $this->getTask($task_id);
			if($rs = $this->isTask($app["app_id"])){
				$DB->Update("ci_application", $in, "WHERE id='".$rs["id"]."'", $err_mess.__LINE__);
				return $rs["id"];
			}else{
				$insert_id = $DB->Insert("ci_application", $in, $err_mess.__LINE__);
			}
			if( intval($insert_id) > 0 )
				return $insert_id;
		} else return false;
	}
	function setDefaults( $brand_id,  $arr ){
		global $DB;
		$brand_id = (int)$brand_id;
		$profile_settings = ($arr["profile_settings"] == "Y" ? "Y" : "N");
		//prent($arr);die;
		unset($arr["profile_settings"]);
		if( $this->isBrand($brand_id) ){
			$arr = json_encode($arr, JSON_UNESCAPED_UNICODE );
			$DB->Update("ci_section", array("defaults" => "'".$arr."'", "profile_settings" => "'".$profile_settings."'"), "WHERE id='".$brand_id."'", $err_mess.__LINE__);
			return true;
		} else return false;
	}
	function getApplies( $status = null,  $limit = 500 ){
		global $DB;
		$limit = (int)$limit;
		if( $limit > 0 ){
			$arr = array();
			if( $status == null )
				$strSql = "SELECT * FROM ci_application ORDER BY id DESC LIMIT " . $limit;
			else $strSql = "SELECT * FROM ci_application WHERE status = '".$status."' ORDER BY id DESC LIMIT " . $limit;
			
			$results = $DB->Query($strSql, false, $err_mess.__LINE__);
			while ($row = $results->Fetch()){
				$arr[] = $row;
			}
			return $arr;
		}
        return false;
	}
	
	public static function closeTags($content){
        $position = 0;
        $open_tags = array();
        //теги для игнорирования
        $ignored_tags = array('br', 'hr', 'img');

        while (($position = strpos($content, '<', $position)) !== FALSE)
        {
            //забираем все теги из контента
            if (preg_match("|^<(/?)([a-z\d]+)\b[^>]*>|i", substr($content, $position), $match))
            {
                $tag = strtolower($match[2]);
                //игнорируем все одиночные теги
                if (in_array_($tag, $ignored_tags) == FALSE)
                {
                    //тег открыт
                    if (isset($match[1]) AND $match[1] == '')
                    {
                        if (isset($open_tags[$tag]))
                            $open_tags[$tag]++;
                        else
                            $open_tags[$tag] = 1;
                    }
                    //тег закрыт
                    if (isset($match[1]) AND $match[1] == '/')
                    {
                        if (isset($open_tags[$tag]))
                            $open_tags[$tag]--;
                    }
                }
                $position += strlen($match[0]);
            }
            else
                $position++;
        }
        //закрываем все теги
        foreach ($open_tags as $tag => $count_not_closed)
        {
            $content .= str_repeat("</{$tag}>", $count_not_closed);
        }

        return $content;
	}
/*	*/
/*	function getApply( $id ){
		$id = (int)$id;
		if( $id > 0 ){
			$strSql = "SELECT * FROM ci_application WHERE id = '".$id."' LIMIT 1";
			$results = $DB->Query($strSql, false, $err_mess.__LINE__);
			if ($row = $results->Fetch()){
				return $row;
			}
			/************************************
                $res = $this->db->query("SELECT * FROM ci_application WHERE id = $id LIMIT 1");
                if( $res->num_rows() == 1 )
                    return new CApplication( $res->row_array() );
                else return false;
				******************************************/
/*		}
		return false;
	}*/

/*	function getBalance( $user_id = null ){
		if( $user_id !== null ) $user_id = (int)$user_id;
		$qa = "SELECT SUM(price) FROM ci_application WHERE (status = 'F' or status = 'P')".( is_int($user_id) ? " and user_id = '".$user_id."'" : '');
		$qp = "SELECT SUM(summary) FROM ci_cash_pay".( is_int($user_id) ? " WHERE user_id = '".$user_id."'" : '');
		$ra = $DB->Query($qa, false, $err_mess.__LINE__);
		$rp = $DB->Query($qp, false, $err_mess.__LINE__);
		if ($row1 = $ra->Fetch())
			$sa = $row1['SUM(price)'];
		else $sa = 0;
			
		if ($row2 = $rp->Fetch())
			$sp = $row2['SUM(summary)'];
		else $sp = 0;
		
		return $sa - $sp;
	}*/
/*	function GetAppsByDays( $days = 30 ){
		if( is_int($days) ){
			$strSql = "SELECT COUNT(id), DATE_FORMAT(`datetime`, '%d.%m.%Y') as dat, SUM(`price`) as summary
                  FROM ci_application
                  WHERE (status = 'F' or status = 'P') and `datetime`  > CURDATE() - INTERVAL ".$days." DAY
                  GROUP BY dat ORDER BY dat DESC";
			$arr = array();
			$results = $DB->Query($strSql, false, $err_mess.__LINE__);
			while ($row = $results->Fetch()){
				$arr[] = $row;
			}
			return $arr;
		} 
		return false;
	}*/
/*	function GetPaysByDays( $days = 30 ){
		if( is_int($days) ){
			$arr = array();
			$strSql = "SELECT * FROM ci_cash_pay WHERE `datetime` > CURDATE() - INTERVAL ".$days." DAY";
			$arr = array();
			$results = $DB->Query($strSql, false, $err_mess.__LINE__);
			while ($row = $results->Fetch()){
				$arr[] = $row;
			}
			return $arr;
		}
		return false;
	}
	function addPay( $id, $sum, $comment ){
		$id = (int)$id;
		$sum = (int)$sum;
		$comment = $this->db->escape($comment);
		if( $id != 0 && $sum != 0 ){
			$arr = array(
				"user_id" => "'".$id."'",
				"summary" => "'".$sum."'",
				"way" => "'1'",
				"comment" => "'".$comment."'",
			);
			$insert_id = $DB->Insert("ci_cash_pay", $arr, $err_mess.__LINE__);
			if( $insert_id > 0 ) return true;
		}
		return false;
	}
	function countApplies( $user_id = null ){
		if( $user_id == null )
			$strSql = "SELECT COUNT(id) as c FROM ci_application";
		else{
			$user_id = (int)$user_id;
			$strSql = "SELECT COUNT(id) as c FROM ci_application WHERE user_id = '".$user_id."' LIMIT 1";
		}
		$results = $DB->Query($strSql, false, $err_mess.__LINE__);
		if ($row = $results->Fetch()){
			return $row["c"];
		}
		return 0;
		
	}
	function getLastApply( $user_id = null )
        {

	}*/

	public function syncProps(){
		global $USER;
		if(!CModule::IncludeModule('iblock')) return;
		
		$arStructure = false;

		$res = CIBlockSection::GetList(array(), array('IBLOCK_ID' => CProSet::IB_CATALOG, 'DEPTH_LEVEL' => '1') );//, 'ACTIVE' => 'Y'
		while($section = $res->Fetch()){
			$arSection[$section['ID']] = $section['NAME'];
		}

		$res = CIBlockSection::GetList(array(), array('IBLOCK_ID' => CProSet::IB_CATALOG, 'DEPTH_LEVEL' => '2') );//, 'ACTIVE' => 'Y'
		while($brand = $res->Fetch()){
			$arBrand[$brand['ID']] = $arSection[$brand['IBLOCK_SECTION_ID']] . " / " . $brand['NAME'];
		}
		$res = CIBlockSection::GetList(array(), array('IBLOCK_ID' => CProSet::IB_CATALOG, 'DEPTH_LEVEL' => '3') );//, 'ACTIVE' => 'Y'
		while($col = $res->Fetch()){
			$arCollection[] = array(
				'id'    => $col['ID'],
				'name'  => trim($col['NAME']),
				'brand_id'=> $col['IBLOCK_SECTION_ID']
			);
		}
		foreach( $arCollection as $col ){
			if( array_key_exists($col['brand_id'], $arBrand) )
				$ar[ $col['brand_id'] ][$col['id']] = $col['name'];
		}
		if(count($ar) > 0 && count($arBrand) > 0)
			$arStructure = array('col' => $ar, 'brands' => $arBrand);

		if($arStructure){
			$this->updateSections($arStructure);

			//добавляем обновляем свойства для админки
			$properties = CIBlockProperty::GetList(Array("sort"=>"asc", "name"=>"asc"), Array("ACTIVE"=>"Y", "IBLOCK_ID"=>CProSet::IB_CATALOG));//"FILTRABLE" => "Y", 
			$arAddProp = array();
			while ($prop_fields = $properties->GetNext()){
				//
				$arAddProp[] = $prop_fields;
			}
			$this->addUpdateProps($arAddProp);

			$res = CIBlockPropertyEnum::GetList(Array("SORT"=>"ASC", "VALUE"=>"ASC"), Array("IBLOCK_ID"=>CProSet::IB_CATALOG));
			while($pr = $res->Fetch())
				$arProp[$pr['PROPERTY_CODE']][$pr['ID']] = $pr['VALUE'];
			
			if($arProp){
				$this->updateProps( $arProp );
				$status = [
					"status" => "ok",
				];
			}else{
				$status = [
					"status" => "error",
					"text" => "Ошибка обновления свойств. Бренды обновлены.",
				];
			}
			//LocalRedirect("/admin/content/settings/");
		} else {
			$status = [
				"status" => "error",
				"text" => "Ошибка обновления брендов. Синхронизация остановлена.",
			];
		}
		return $status;
	}
	
}