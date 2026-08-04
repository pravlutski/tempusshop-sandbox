<?php
class CPanelSupplier{
	function __construct(){

	}

	function getList($arFilter = array(), $arSort = []){
		global $DB;
		$strSql = "SELECT * FROM ci_suppliers";
		if($arFilter){
			$filter = [];
			foreach($arFilter as $col => $val){
				if(is_array($val)){

					$filter[] = "{$col} IN ('" . implode("','", $val)."')";
				}else{
					$filter[] = "{$col} = '" . $val . "'";
				}
			}
			if($filter)
				$strSql .= " WHERE " . implode(" AND ", $filter);
			//prent($strSql2);prent($filter);
		}
		if($arSort){

		}else{
			$strSql .= " ORDER BY sort ASC";
		}
		$results = $DB->Query($strSql, false, $err_mess.__LINE__);
		$arr = array();
		while ($row = $results->Fetch()){
			$arr[$row["id"]] = $row;
		}
		return $arr;
	}

	function getDetail( $supp_id ){
		global $DB;
		$supp_id = (int)$supp_id;
		$strSql = "SELECT * FROM ci_suppliers WHERE id = '{$supp_id}'";
		$results = $DB->Query($strSql, false, $err_mess.__LINE__);
		if ($row = $results->Fetch()){
			return $row;
		}
		return false;
	}

	function getName( $supp_id ){
		global $DB;
		$supp_id = (int)$supp_id;
		$strSql = "SELECT name FROM ci_suppliers WHERE id = '{$supp_id}'";
		$results = $DB->Query($strSql, false, $err_mess.__LINE__);
		if ($row = $results->Fetch()){
			return $row["name"];
		}
		return false;
	}

	function apply( $arr = null ){
		global $DB;
		$obj_brand = new CPanelBrand;
		$obj_pricelist = new CPanelPricelist;
		$arSettings = $arSettingsPricelist = $arBrand = array();
		$supp_id = intval($arr["supplier-id"]);
		if(isset($arr["brand"]) && count($arr["brand"]) > 0){
			foreach($arr["brand"] as $key => $brand){
				$brand_id = intval($brand["id"]);
				if($obj_brand->getDetail( $brand_id )){
					$arBrand[$brand_id] = array(
						"id" 			=> $brand_id,
						"priority"		=> intval($brand["priority"]),
						"sale"			=> $brand["sale"],
						"active_ru"		=> ($brand["active_ru"] == "on" ? "Y" : "N"),
						"active_by"		=> ($brand["active_by"] == "on" ? "Y" : "N"),
						"active_pl"		=> ($brand["active_pl"] == "on" ? "Y" : "N"),
						"active_wb"		=> ($brand["active_wb"] == "on" ? "Y" : "N"),
						"active_wbtl"	=> ($brand["active_wbtl"] == "on" ? "Y" : "N"),
						"active_wbby"	=> ($brand["active_wbby"] == "on" ? "Y" : "N"),
						"active_sb"		=> ($brand["active_sb"] == "on" ? "Y" : "N"),
						"active_opt"	=> ($brand["active_opt"] == "on" ? "Y" : "N"),
						"active_ya"		=> ($brand["active_ya"] == "on" ? "Y" : "N"),
						"active_os"		=> ($brand["active_os"] == "on" ? "Y" : "N"),
						"active_av"		=> ($brand["active_av"] == "on" ? "Y" : "N"),
						"active_kz"		=> ($brand["active_kz"] == "on" ? "Y" : "N"),
						"active_ozkz"		=> ($brand["active_ozkz"] == "on" ? "Y" : "N"),
						"active_ozti"		=> ($brand["active_ozti"] == "on" ? "Y" : "N"),
					);
				}
			}
		}
		$arSettings["brand"] = $arBrand;
		$arSettings["currency"] = $arr["currency"];
		$arSettings["currency_list"] = $arr["currency_list"];
		$arSettings["mc_name"] = $arr["mc_name"];
		$arSettings["mc_return"] = $arr["mc_return"];
		
		$arSettings["correct_price"] = $arr["correct_price"] ?? [];
    //file_put_contents("/var/www/bitrix/data/www/tempusshop.ru/bitrix/modules/panel.manager/classes/models/test.txt", print_r($arr["type"], true).PHP_EOL,);
		if(isset($arr["type"]) && count($arr["type"]) > 0){
			foreach($arr["type"] as $key => $value){
				$arTypes[] = $value;
			}
		}
		//
		$arSettingsPricelist["col_price"] = (int)$arr["col_price"];
		$arSettingsPricelist["col_count"] = (int)$arr["col_count"];
		$arSettingsPricelist["col_multiplicity"] = (int)$arr["col_multiplicity"];
		$arSettingsPricelist["col_article"] = (int)$arr["col_article"];
		$arSettingsPricelist["col_brand"] = (int)$arr["col_brand"];
		$arSettingsPricelist["start_row"] = (int)$arr["start_row"];
		$arSettingsPricelist["count_default"] = (int)$arr["count_default"];
		if (isset($arr["priority_default"]) && $arr["priority_default"] >= 0) {
			$arSettingsPricelist["priority_default"] = (int)$arr["priority_default"];
		}
		$arSettingsPricelist["filename"] = $arr["filename"];
		$arSettingsPricelist["margin"] = $arr["margin"];
		$arSettingsPricelist["margin_round"] = $arr["margin_round"];
		$arSettingsPricelist["num_lists"] = $arr["num_lists"];
		if($arr["clear_space"] == "on")
			$arSettingsPricelist["clear_space"] = "Y";
		else
			$arSettingsPricelist["clear_space"] = "N";

		if($arr["brand_from_list"] == "on")
			$arSettingsPricelist["brand_from_list"] = "Y";
		else
			$arSettingsPricelist["brand_from_list"] = "N";

		if($arr["opt_price"] == "on")
			$arSettingsPricelist["opt_price"] = "Y";
		else
			$arSettingsPricelist["opt_price"] = "N";

		if(in_array($arr["quntity_flag"], array("str", "int"))){
			$arSettingsPricelist["quntity_flag"] = $arr["quntity_flag"];
			$arSettingsPricelist["quntity_value"] = $arr["quntity_value"];
		}

		//if(in_array($arr["day_delivery"], array("1", "7", "30"))){
		//	$arSettingsPricelist["day_delivery"] = $arr["day_delivery"];
		//}

		$arSettingsPricelist["day_delivery"] = (int)$arr["day_delivery"];
		//$arSettingsPricelist["day_delivery_by"] = (int)$arr["day_delivery_by"];
		//$arSettingsPricelist["day_delivery_pl"] = (int)$arr["day_delivery_pl"];
		
		if($arr["location"] && in_array($arr["location"], ["moscow", "minsk"])){
			$arSettingsPricelist["location"] = $arr["location"];
		}
		
		$arSettingsPricelist["working_time"] = (int)$arr["working_time"];
		if($arr["working_week"]){
			$arSettingsPricelist["working_week"] = $arr["working_week"];
		}
		if(isset($arr["price_r"]) && $arr["price_r"] == "on") $arSettings["price_r"] = "Y"; else $arSettings["price_r"] = "N";

		if($this->isSupp($supp_id)){
			$name = trim(addslashes($arr["name"]));
			$sort = intval($arr["sort"]);
			if(strlen($name) > 0)
				$DB->Update("ci_suppliers", array("name" => "'".$name."'"), "WHERE id='".$supp_id."'", $err_mess.__LINE__);

			if($arr["active_ru"] == "on")
				$status = "Y";
			else
				$status = "N";
			if($arr["active_by"] == "on")
				$status_by = "Y";
			else
				$status_by = "N";

			if($arr["active_pl"] == "on")
				$status_pl = "Y";
			else
				$status_pl = "N";

			if($arr["active_kz"] == "on")
				$status_kz = "Y";
			else
				$status_kz = "N";

			if($arr["active_ozkz"] == "on")
				$status_ozkz = "Y";
			else
				$status_ozkz = "N";

			if($arr["active_ozti"] == "on")
				$status_ozti = "Y";
			else
				$status_ozti = "N";

			if($arr["active_wb"] == "on")
				$status_wb = "Y";
			else
				$status_wb = "N";

			if($arr["active_wbtl"] == "on")
				$status_wbtl = "Y";
			else
				$status_wbtl = "N";
			
			if($arr["active_wbby"] == "on")
				$status_wbby = "Y";
			else
				$status_wbby = "N";

			if($arr["active_sb"] == "on")
				$status_sb = "Y";
			else
				$status_sb = "N";

			if($arr["active_av"] == "on")
				$status_av = "Y";
			else
				$status_av = "N";

			if($arr["active_opt"] == "on")
				$status_opt = "Y";
			else
				$status_opt = "N";

			if($arr["active_ya"] == "on")
				$status_v1 = "Y";
			else
				$status_v1 = "N";

			if($arr["active_os"] == "on")
				$status_v2 = "Y";
			else
				$status_v2 = "N";

			if($arr["opt_supplier"] == "on")
				$opt_supplier = "Y";
			else
				$opt_supplier = "N";
			
			if($arr["nds"] == "on")
				$nds = "Y";
			else
				$nds = "N";

			if($arr["is_warehouse"] == "on")
				$is_warehouse = "Y";
			else
				$is_warehouse = "N";
			
			$store_id = (int)$arr["store_id"];
			$DB->Update("ci_suppliers", array(
				"active_ru" => "'".$status."'",
				"active_by" => "'".$status_by."'",
				"active_pl" => "'".$status_pl."'",
				"active_wb" => "'".$status_wb."'",
				"active_wbtl" => "'".$status_wbtl."'",
				"active_wbby" => "'".$status_wbby."'",
				"active_sb" => "'".$status_sb."'",
				"active_av" => "'".$status_av."'",
				"active_opt" => "'".$status_opt."'",
				"active_kz" => "'".$status_kz."'",
				"active_ozkz" => "'".$status_ozkz."'",
				"active_ozti" => "'".$status_ozti."'",
				"active_ya" => "'".$status_v1."'",
				"active_os" => "'".$status_v2."'",
				"opt_supplier" => "'".$opt_supplier."'",
				"nds" => "'".$nds."'", 
				"store_id" => "'".$store_id."'", 
				"is_warehouse" => "'".$is_warehouse."'", 
				"sort" => "'".$sort."'"
			), "WHERE id='".$supp_id."'", $err_mess.__LINE__);
			
			$this->setSettings($supp_id, $arSettings);
			$this->setSettingsTypeSklad($supp_id, $arTypes);
			$this->setSettingsPricelist($supp_id, $arSettingsPricelist);
			/*
			//активируем/деактивируем прайслисты и товары в нём
			$arStatus = array(
				"active" => $status,
				"active_by" => $status_by,
				"active_pl" => $status_pl,
			);
			$obj_pricelist->changeActivityAll($supp_id, $arStatus, $arSettings["brand"]);
			*/

			$supplier = $this->getDetail( $supp_id );
			$supplier["settings"] = json_decode( $supplier["settings"], true );
			$supplier["settings_pricelist"] = json_decode( $supplier["settings_pricelist"], true );

			$obj_pricelist->changeActivityAll($supplier);
			/* запускаем обновление каталога */
			$this->updateCatalog();
			
			CProSet::setOption("UPDATE_PRICE_ANALISYS", "NEED_START");
			return true;
		}elseif(isset($arr["name"])){
			$name = trim($arr["name"]);
			$sort = intval($arr["sort"]);
			if($arr["active_ru"] == "on")
				$status = "Y";
			else
				$status = "N";
			if($arr["active_by"] == "on")
				$status_by = "Y";
			else
				$status_by = "N";

			if($arr["active_pl"] == "on")
				$status_pl = "Y";
			else
				$status_pl = "N";

			if($arr["active_kz"] == "on")
				$status_kz = "Y";
			else
				$status_kz = "N";

			if($arr["active_ozkz"] == "on")
				$status_ozkz = "Y";
			else
				$status_ozkz = "N";

			if($arr["active_ozti"] == "on")
				$status_ozti = "Y";
			else
				$status_ozti = "N";


			if($arr["active_wb"] == "on")
				$status_wb = "Y";
			else
				$status_wb = "N";

			if($arr["active_wbtl"] == "on")
				$status_wbtl = "Y";
			else
				$status_wbtl = "N";
			
			if($arr["active_wbby"] == "on")
				$status_wbby = "Y";
			else
				$status_wbby = "N";

			if($arr["active_sb"] == "on")
				$status_sb = "Y";
			else
				$status_sb = "N";

			if($arr["active_av"] == "on")
				$status_av = "Y";
			else
				$status_av = "N";

			if($arr["active_opt"] == "on")
				$status_opt = "Y";
			else
				$status_opt = "N";

			if($arr["active_ya"] == "on")
				$status_v1 = "Y";
			else
				$status_v1 = "N";

			if($arr["active_os"] == "on")
				$status_v2 = "Y";
			else
				$status_v2 = "N";

			$id = $this->addSupp(
				$name, 
				$status, 
				$status_by, 
				$status_pl, 
				$status_wb, 
				$status_wbtl, 
				$status_wbby, 
				$status_sb, 
				$status_av,
				$status_kz,
				$status_ozkz,
				$status_ozti, 
				$status_opt, 
				$status_v1, 
				$status_v2, 
				$sort
			);
			
			if($id !== false){
				$this->setSettings($id, $arSettings);
				$this->setSettingsPricelist($id, $arSettingsPricelist);
				
				CProSet::setOption("UPDATE_PRICE_ANALISYS", "NEED_START");
				return $id;
			}
		}
		return false;

	}

	function applyBrandSale($arr = []){
		global $DB;

		$supp_id = intval($arr["supplier-id"]);
		$brand_id = intval($arr["brand-id"]);

		$arSupplier = $this->getDetail($supp_id);
		if(!$supp_id || !$brand_id || !$arSupplier) return false;

		//$brandSale = json_decode($arSupplier["settings_brand_sale"], true);
		$brandSale = unserialize($arSupplier["settings_brand_sale"]);

		$brandSale[$brand_id] = $arr["brand"];

		$in = [
			"settings_brand_sale" => "'".addslashes(json_encode($brandSale, JSON_UNESCAPED_UNICODE))."'"
			//"settings_brand_sale" => "'".serialize($brandSale)."'"
		];

		$DB->Update("ci_suppliers", $in, "WHERE id='".$supp_id."'", $err_mess.__LINE__);
		
		CProSet::setOption("UPDATE_PRICE_ANALISYS", "NEED_START");
		return true;
	}

	function updateCatalog(){
		//ищем процессы и убиваем если они есть
		exec("pgrep -f /var/www/bitrix/data/www/tempusshop.ru/local/cron/catalog/update_catalog_all.php",$output,$code);
		if(count($output) > 0){
			foreach($output as $pid)
				exec("kill -9 {$pid}");
		}
		CProSet::setOption("UPDATE_CATALOG_PER", "100");
		CProSet::setOption("UPDATE_CATALOG", "Y");
		system("/usr/bin/php -f /var/www/bitrix/data/www/tempusshop.ru/local/cron/catalog/update_catalog_all.php >/dev/null 2>&1 &");

	}
	//сохранение настроек прайслиста отдельно
	function apply_price( $arr = null ){
		global $DB;
		$obj_brand = new CPanelBrand;
		$obj_pricelist = new CPanelPricelist;
		$arPriceDetail = array();
		$supp_id = intval($arr["supplier-id"]);

		foreach($arr["list"] as $list => $arItem){
			$arPriceDetail[$list]["col_price"] = (int)$arItem["col_price"];
			$arPriceDetail[$list]["col_count"] = (int)$arItem["col_count"];
			$arPriceDetail[$list]["col_multiplicity"] = (int)$arItem["col_multiplicity"];
			$arPriceDetail[$list]["col_article"] = (int)$arItem["col_article"];
			$arPriceDetail[$list]["col_brand"] = (int)$arItem["col_brand"];
			$arPriceDetail[$list]["start_row"] = (int)$arItem["start_row"];
			$arPriceDetail[$list]["count_default"] = (int)$arItem["count_default"];
			if (isset($arItem["priority_default"]) && $arItem["priority_default"] >= 0) {
				$arPriceDetail[$list]["priority_default"] = (int)$arItem["priority_default"];
			}

			if($arItem["clear_space"] == "on")
				$arPriceDetail[$list]["clear_space"] = "Y";
			else
				$arPriceDetail[$list]["clear_space"] = "N";

			if($arItem["brand_from_list"] == "on")
				$arPriceDetail[$list]["brand_from_list"] = "Y";
			else
				$arPriceDetail[$list]["brand_from_list"] = "N";


			if(in_array($arItem["quntity_flag"], array("str", "int"))){
				$arPriceDetail[$list]["quntity_flag"] = $arItem["quntity_flag"];
				$arPriceDetail[$list]["quntity_value"] = $arItem["quntity_value"];
			}

			if($arItem["active"] == "on")
				$arPriceDetail[$list]["active"] = "Y";
			else
				$arPriceDetail[$list]["active"] = "N";
		}

		//AddMessage2Log($arPriceDetail);

		if($this->isSupp($supp_id)){

			$ar = json_encode( $arPriceDetail, JSON_UNESCAPED_UNICODE );
			$DB->Update("ci_suppliers", array("settings_pricelist_detail" => "'".$ar."'"), "WHERE id='".$supp_id."'", $err_mess.__LINE__);
			
			CProSet::setOption("UPDATE_PRICE_ANALISYS", "NEED_START");
			return true;

		}
		return false;

	}
	function setSettings( $supp_id,  $arr ){
		global $DB;
		$supp_id = (int)$supp_id;
		if( $this->isSupp($supp_id) ){
			$arr = json_encode( $arr, JSON_UNESCAPED_UNICODE );
			$DB->Update("ci_suppliers", array("settings" => "'".$arr."'"), "WHERE id='".$supp_id."'", $err_mess.__LINE__);
			
			CProSet::setOption("UPDATE_PRICE_ANALISYS", "NEED_START");
			return true;
		} else return false;
	}

	function setSettingsTypeSklad( $supp_id,  $arr ){
		global $DB;
		$supp_id = (int)$supp_id;
		if( $this->isSupp($supp_id) ){
			$arr = json_encode( $arr, JSON_UNESCAPED_UNICODE );
			$DB->Update("ci_suppliers", array("settings_type_sklad" => "'".$arr."'"), "WHERE id='".$supp_id."'", $err_mess.__LINE__);
			return true;
		} else return false;
	}

	function setSettingsPricelist( $supp_id,  $arr ){
		global $DB;
		$supp_id = (int)$supp_id;
		if( $this->isSupp($supp_id) ){
			$arr = json_encode( $arr, JSON_UNESCAPED_UNICODE );
			$DB->Update("ci_suppliers", array("settings_pricelist" => "'".$arr."'"), "WHERE id='".$supp_id."'", $err_mess.__LINE__);
			
			CProSet::setOption("UPDATE_PRICE_ANALISYS", "NEED_START");
			return true;
		} else return false;
	}

	function isSupp( $id ){
		global $DB;
		$id = (int)$id;
		$strSql = "SELECT id FROM ci_suppliers WHERE id = {$id} LIMIT 1";
		$results = $DB->Query($strSql, false, $err_mess.__LINE__);
		if ($row = $results->Fetch()){
			return true;
		}
		return false;
	}

	function isSuppSystem( $id ){
		global $DB;
		$id = (int)$id;
		$strSql = "SELECT is_system FROM ci_suppliers WHERE id = {$id} LIMIT 1";
		$results = $DB->Query($strSql, false, $err_mess.__LINE__);
		if ($row = $results->Fetch()){
			if( $row["is_system"] == "Y" ) return true;
		}
		return false;
	}

	function addSupp(
		$name, 
		$status = "Y", 
		$status_by = "Y", 
		$status_pl = "Y", 
		$status_sb = "Y", 
		$status_wb = "Y",
		$status_wbtl = "Y",
		$status_wbby = "Y",
		$status_av = "Y",
		$status_kz = "N",
		$status_ozkz = "N", 
		$status_ozti = "N", 
		$status_opt = "Y", 
		$status_v1 = "Y", 
		$status_v2 = "Y", 
		$sort
	){

		global $DB;
		$name = addslashes($name);
		if(strlen($name) >= 1 && strlen($name) < 255){
			if(!in_array($status, array("N","Y")))
				$status = "Y";
			if(!in_array($status_by, array("N","Y")))
				$status_by = "Y";
			if(!in_array($status_pl, array("N","Y")))
				$status_pl = "Y";
			$in = array(
				"name" => "'".$name."'",
				"sort" => "'".$sort."'",
				"active_ru" => "'".$status."'",
				"active_by" => "'".$status_by."'",
				"active_pl" => "'".$status_pl."'",
				"active_wb" => "'".$status_wb."'",
				"active_wbtl" => "'".$status_wbtl."'",
				"active_wbby" => "'".$status_wbby."'",
				"active_opt" => "'".$status_opt."'",
				"active_ya" => "'".$status_v1."'",
				"active_os" => "'".$status_v2."'",
				"active_sb" => "'".$status_sb."'",
				"active_av" => "'".$status_av."'",
				"active_kz" => "'".$status_oz."'",
				"active_ozkz" => "'".$status_ozkz."'",
				"active_ozti" => "'".$status_ozti."'",
			);
			$ID = $DB->Insert("ci_suppliers", $in, $err_mess.__LINE__);
			if($ID > 0) {
				CProSet::setOption("UPDATE_PRICE_ANALISYS", "NEED_START");
				return $ID;
			}
			else return false;
		} else return false;
	}

	function delete( $id ){
		global $DB;
		$id = intval( $id );
		if( !$this->isSuppSystem( $id ) ){
			$DB->Query("DELETE from ci_suppliers WHERE id = '".$id."'", false, $err_mess.__LINE__);
			
			CProSet::setOption("UPDATE_PRICE_ANALISYS", "NEED_START");
			return true;
		} else return false;
	}

}
