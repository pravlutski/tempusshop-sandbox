<?php
class CProSet {
	const IB_CATALOG = 16;// Инфоблок "Наручные часы"
	const IB_CATALOG_SKU = 17;// Инфоблок "Наручные часы" торговые предложения
	
	const IB_BRANDS = 11;// Инфоблок "Бренды"
	const ID_SupplierGoogleDocs = 44;// ID поставщика google docs
	const ID_SupplierGoogleDocsRU = 47;// ID поставщика google docs ru
	const ID_SupplierGoogleDocsPL = 71;// ID поставщика Польша
	const IB_INFO_ONLINER = 37;// Инфоблок Инфо для onliner
	const IB_SEO_FILTER = 38;
	const IB_SUBSCRIBE_PRODUCT = 39;// Инфоблок Подписки на товар
	const IB_REVIEWS = 194;// Инфоблок Отзывы на товар
	
	const PROP_ID_ARTICLE = 123;// ID свойства артикулу
	const PROP_ID_ARTICLE_SKU = 121;// ID свойства артикулу у торгового предложения
	function getSexPropID($code){
		switch($code){
			case in_array($code, array("lady","Женские")): $id_prop = 40; break;
			case in_array($code, array("mens","Мужские")): $id_prop = 39; break;
			case in_array($code, array("unisex","Унисекс")): $id_prop = 41; break;
			default: $id_prop = false; break;
		}
		return $id_prop;
	} 
	function getSexGroupPropID($code){
		switch($code){
			case in_array($code, array("lady","Женские")): 
				$id_prop[] = self::getSexPropID("unisex");
				$id_prop[] = self::getSexPropID("lady");
				break;
			case in_array($code, array("mens","Мужские")): 
				$id_prop[] = self::getSexPropID("mens");
				$id_prop[] = self::getSexPropID("unisex"); 
				break;
			case in_array($code, array("unisex","Унисекс")): 
				$id_prop[] = self::getSexPropID("unisex"); 
				break;
			default: $id_prop = false; break;
		}
		return $id_prop;
	}
	
	
	function getPopularPropID($code){
		switch($code){
			case in_array($code, array("smart","Умные часы")): $id_prop = 810; break;
			case in_array($code, array("p_kids","Детские часы")): $id_prop = 811; break;
			case in_array($code, array("sport","Спортивные часы")): $id_prop = 812; break;
			case in_array($code, array("tourism","Часы для туризма")): $id_prop = 813; break;
			case in_array($code, array("mechanical","Механические часы")): $id_prop = 814; break;
			case in_array($code, array("quartz","Кварцевые часы")): $id_prop = 815; break;
			case in_array($code, array("digital","Электронные часы")): $id_prop = 816; break;
			case in_array($code, array("waterproof","Водонепроницаемые часы")): $id_prop = 817; break;
			case in_array($code, array("shockproof","Противоударные часы")): $id_prop = 818; break;
			case in_array($code, array("skeleton","Часы скелетоны")): $id_prop = 819; break;
			case in_array($code, array("heart_rate","Часы с пульсометром")): $id_prop = 820; break;
			case in_array($code, array("chronograph_watches","Часы хронограф")): $id_prop = 821; break;
			case in_array($code, array("cheap","Недорогие часы")): $id_prop = 822; break;
			case in_array($code, array("diver","Дайверские часы")): $id_prop = 823; break;
			default: $id_prop = false; break;
		}
		return $id_prop;
	} 
	function getPopularGroupPropID($code){
		switch($code){
			case in_array($code, array("smart","Умные часы")): 
				$id_prop[] = self::getPopularPropID("smart");
				break;
			case in_array($code, array("forkids","Детские часы")): 
				$id_prop[] = self::getPopularPropID("p_kids");
				break;
			case in_array($code, array("sport","Спортивные часы")): 
				$id_prop[] = self::getPopularPropID("sport"); 
				break;
			case in_array($code, array("tourism","Часы для туризма")): 
				$id_prop[] = self::getPopularPropID("tourism"); 
				break;
			case in_array($code, array("mechanical","Механические часы")): 
				$id_prop[] = self::getPopularPropID("mechanical"); 
				break;
			case in_array($code, array("quartz","Кварцевые часы")): 
				$id_prop[] = self::getPopularPropID("quartz"); 
				break;
			case in_array($code, array("digital","Электронные часы")): 
				$id_prop[] = self::getPopularPropID("digital"); 
				break;
			case in_array($code, array("waterproof","Водонепроницаемые часы")): 
				$id_prop[] = self::getPopularPropID("waterproof"); 
				break;
			case in_array($code, array("shockproof","Противоударные часы")): 
				$id_prop[] = self::getPopularPropID("shockproof"); 
				break;
			case in_array($code, array("skeleton","Часы скелетоны")): 
				$id_prop[] = self::getPopularPropID("skeleton"); 
				break;
			case in_array($code, array("heart_rate","Часы с пульсометром")): 
				$id_prop[] = self::getPopularPropID("heart_rate"); 
				break;
			case in_array($code, array("chronograph_watches","Часы хронограф")): 
				$id_prop[] = self::getPopularPropID("chronograph_watches"); 
				break;
			case in_array($code, array("cheap","Недорогие часы")): 
				$id_prop[] = self::getPopularPropID("cheap"); 
				break;
			case in_array($code, array("diver","Дайверские часы")): 
				$id_prop[] = self::getPopularPropID("diver"); 
				break;
			default: $id_prop = false; break;
		}
		return $id_prop;
	}
	function getOption($code){
		global $DB;
		if(!$code) return false;
		$strSql = "SELECT value FROM ci_options WHERE code = '" . $code . "'";
		$results = $DB->Query($strSql, false, $err_mess.__LINE__);
		if ($row = $results->Fetch()){
			return $row["value"];
		}
		return false;
	}
	function getFullOption($code){
		global $DB;
		if(!$code) return false;
		$strSql = "SELECT * FROM ci_options WHERE code = '" . $code . "'";
		$results = $DB->Query($strSql, false, $err_mess.__LINE__);
		if ($row = $results->Fetch()){
			return $row;
		}
		return false;
	}
	function setOption($code, $value){
		global $DB;
		$code = trim($code);
		$value = trim($value);
		//if(!$code || strlen($value) == 0) return false;
		if(!$code) return false;
		
		$strSql = "SELECT id FROM ci_options WHERE code = '" . $code . "'";
		$results = $DB->Query($strSql, false, $err_mess.__LINE__);
		if ($row = $results->Fetch()){
			$arFields = array(
				"value" => "'". addslashes($value) ."'",
				"timestamp" => $DB->GetNowFunction()
			);
			$DB->Update("ci_options", $arFields, "WHERE id='".$row["id"]."'", $err_mess.__LINE__);
		}
		return false;
	}
	//список свойств участвующих в цмном фильтре
	/*
Минимальная цена		84	168	
Минимальная цена РБ		246		492
Минимальная цена PL		371		
Тип						126		252
Бренд					87		174
Размер корпуса, мм		124		248
Толщина, мм				125		250
Вид механизма			127		254
Материал корпуса		128		256
Материал браслета		129		258
Стекло					130		260
Плавный ход				2742		5484
Отображение даты		131		162
Подсветка				132		164
Светомасса				133		166
Водостойкость			134		168
Солнечная батарея		135		170
Особенности				2741	5482	
Радиосинхронизация		141		282
Индикатор запаса хода	143		286
Происхождение бренда	144		288
Вставка					264		528
Популярные категории	948		1896
	protected function getFacetFilter(array $facetTypes)
	{
		$ar = array(252,174,248,250,254,256,258,260,5484,162,164,166,168,170,5482,282,286,288,528,1896);
		if(SITE_ID == "s1") $ar[] = 168;
		if(SITE_ID == "s2") $ar[] = 492;
		if(SITE_ID == "s3") $ar[] = 742;
		return $ar;
		C:\Users\Олег\AppData\Local\Temp\scp00700\royaltime%2Ftempus_2020\home\bitrix\ext_www\tempusshop.ru\bitrix\modules\iblock\lib\propertyindex\facet.php
	*/
	function getPropSF(){
		
		$ar = array(252,174,248,250,254,256,258,260,5484,162,164,166,168,170,5482,282,286,288,528,1896);
		if(SITE_ID == "s1") $ar[] = 168;
		if(SITE_ID == "s2") $ar[] = 492;
		if(SITE_ID == "s3") $ar[] = 742;
		return $ar;
	}
}

