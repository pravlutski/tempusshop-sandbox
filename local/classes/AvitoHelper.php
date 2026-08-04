<?
/**
 * Class AvitoHelper
 */
class AvitoHelper {
	private $settings = [
		"PROPERTY" => [
			126 => [
				"FILE" => "gender.csv",
				"PROPERTY_AVITO" => "Gender",
				"BX" => "TYPE",
				"BX_ID" => 126,
			],
			2746 => [
				"FILE" => "color.csv",
				"PROPERTY_AVITO" => "Color",
				"BX" => "dial_color",
				"BX_ID" => 2746,
			],
			87 => [
				"FILE" => "brand.csv",
				"PROPERTY_AVITO" => "Brand",
				"BX" => "BRAND",
				"BX_ID" => 87,
			],
			129 => [
				"FILE" => "straptype.csv",
				"PROPERTY_AVITO" => "StrapType",
				"BX" => "MATERIAL",
				"BX_ID" => 129,
			],
			127 => [
				"FILE" => "mechanism.csv",
				"PROPERTY_AVITO" => "Mechanism",
				"BX" => "MECHANISM",
				"BX_ID" => 127,
			],
			2748 => [
				"PROPERTY_AVITO" => "Mechanism",
				"BX" => "FACE",
				"BX_ID" => 2748,
			],
		]

	];
	private $db;
	private $matchProperty = [];
	
    public function __construct () {
		global $DB;
		$this->$db = $DB;
		
		$this->getSettings();
		
		foreach($this->settings["PROPERTY"] as $k => $v){
			$this->arMatchBX[$v["BX"]] = $v["BX_ID"];
		}
    }

    public function getElements(array $arFilter = []) {
		//prent($this->settings);
		$arSelect = ["ID", "CODE", "IBLOCK_ID", "PROPERTY_TYPE", "PROPERTY_dial_color", "PROPERTY_BRAND", "PROPERTY_MATERIAL", "PROPERTY_MECHANISM", "PROPERTY_FACE"];
		$rs = CIBlockElement::GetList([], $arFilter, false, false, $arSelect);

		$arElements = [];
		while($arItem = $rs->GetNext()) {
			$arElements[$arItem["ID"]] = [
				"TYPE" => ($arItem["PROPERTY_TYPE_PROPERTY_VALUE_ID"] ? reset($arItem["PROPERTY_TYPE_PROPERTY_VALUE_ID"]) : ""),
				"dial_color" => ($arItem["PROPERTY_DIAL_COLOR_PROPERTY_VALUE_ID"] ? reset($arItem["PROPERTY_DIAL_COLOR_PROPERTY_VALUE_ID"]) : ""),
				"BRAND" => ($arItem["PROPERTY_BRAND_VALUE"] ? $arItem["PROPERTY_BRAND_VALUE"] : ""),
				"MATERIAL" => ($arItem["PROPERTY_MATERIAL_PROPERTY_VALUE_ID"] ? reset($arItem["PROPERTY_MATERIAL_PROPERTY_VALUE_ID"]) : ""),
				"MECHANISM" => ($arItem["PROPERTY_MECHANISM_VALUE"] ? $arItem["PROPERTY_MECHANISM_VALUE"] : ""),
				"FACE" => ($arItem["PROPERTY_FACE_VALUE"] ? $arItem["PROPERTY_FACE_VALUE"] : ""),
			];
		}

		// получаем множественные и подменяем. так быстрее
		$arMultiID = array_merge(array_column($arElements, "TYPE"), array_column($arElements, "dial_color"), array_column($arElements, "MATERIAL"));
		$arMultiID = array_diff($arMultiID, array(''));
		$arMultiID = array_values(array_unique($arMultiID));
		
		if(is_array($arMultiID) && count($arMultiID) > 0){
			$strSql = "SELECT pr.IBLOCK_PROPERTY_ID as IBLOCK_PROPERTY_ID, pr.IBLOCK_ELEMENT_ID as IBLOCK_ELEMENT_ID, enum.VALUE as VALUE
				FROM
					b_iblock_element_prop_m16 pr
				LEFT JOIN
					b_iblock_property_enum enum
				ON pr.VALUE=enum.ID
				WHERE
					pr.ID IN ('".implode("','", $arMultiID)."')";
			
			$results = $this->$db->Query($strSql, false, $err_mess.__LINE__);
			
			while ($row = $results->Fetch()){
				//prent($row);
				$p_code = $this->settings["PROPERTY"][$row["IBLOCK_PROPERTY_ID"]]["BX"];
				$arElements[$row["IBLOCK_ELEMENT_ID"]][$p_code] = $row["VALUE"];
			}
			
		}
		
		// ищем названия брендов
		$arBrandIDs = array_column($arElements, "BRAND");
		$arBrandIDs = array_diff(array_values(array_unique($arBrandIDs)), array(''));
		if(is_array($arBrandIDs) && count($arBrandIDs) > 0){
			$arBrand = [];
			$arFilter = [
				"ID" => $arBrandIDs,
				"IBLOCK_ID" => CProSet::IB_BRANDS,
			];
			$arSelect = ["ID", "NAME"];
			$rs = CIBlockElement::GetList([], $arFilter, false, false, $arSelect);

			while($arItem = $rs->GetNext()) {
				$arBrand[$arItem["ID"]] = $arItem["NAME"];
			}
			
			foreach($arElements as &$arItem){
				$arItem["BRAND"] = ($arBrand[$arItem["BRAND"]] ? $arBrand[$arItem["BRAND"]] : "");
			}
			unset($arItem);
		}
		
		return $arElements;
    }
	
	// потом вернуть на обчный getElements. кто то удалил записи и в бд не все данные
    public function getElements2(array $arFilter = []) {
		//prent($this->settings);
		$arSelect = ["ID", "CODE", "IBLOCK_ID", "PROPERTY_TYPE", "PROPERTY_dial_color", "PROPERTY_BRAND", "PROPERTY_MATERIAL", "PROPERTY_MECHANISM", "PROPERTY_FACE"];
		$rs = CIBlockElement::GetList([], $arFilter, false, false, $arSelect);

		$arElements = [];
		while($ob = $rs->GetNextElement()) {
			$arFields = $ob->GetFields();
			$arProp = $ob->GetProperties();
			$arElements[$arFields["ID"]] = [
				"TYPE" => ($arProp["TYPE"]["VALUE_ENUM"] ? $arProp["TYPE"]["VALUE_ENUM"][0] : ""),
				"dial_color" => ($arProp["dial_color"]["VALUE_ENUM"] ? $arProp["dial_color"]["VALUE_ENUM"][0] : ""),
				"BRAND" => ($arProp["BRAND"]["VALUE"] ? $arProp["BRAND"]["VALUE"] : ""),
				"MATERIAL" => ($arProp["MATERIAL"]["VALUE_ENUM"] ? $arProp["MATERIAL"]["VALUE_ENUM"][0] : ""),
				"MECHANISM" => ($arProp["MECHANISM"]["VALUE"] ? $arProp["MECHANISM"]["VALUE"] : ""),
				"FACE" => ($arProp["FACE"]["VALUE"] ? $arProp["FACE"]["VALUE"] : ""),
			];
		}
		
		// ищем названия брендов
		$arBrandIDs = array_column($arElements, "BRAND");
		$arBrandIDs = array_diff(array_values(array_unique($arBrandIDs)), array(''));
		if(is_array($arBrandIDs) && count($arBrandIDs) > 0){
			$arBrand = [];
			$arFilter = [
				"ID" => $arBrandIDs,
				"IBLOCK_ID" => CProSet::IB_BRANDS,
			];
			$arSelect = ["ID", "NAME"];
			$rs = CIBlockElement::GetList([], $arFilter, false, false, $arSelect);

			while($arItem = $rs->GetNext()) {
				$arBrand[$arItem["ID"]] = $arItem["NAME"];
			}
			
			foreach($arElements as &$arItem){
				$arItem["BRAND"] = ($arBrand[$arItem["BRAND"]] ? $arBrand[$arItem["BRAND"]] : "");
			}
			unset($arItem);
		}
		
		return $arElements;
    }
	
    public function genAvitoChr(array $arFields = []) {
		
		$ar = [];
		foreach($arFields as $prop_code => $value){
			$prop_id = $this->arMatchBX[$prop_code];
			$prop_avito = $this->settings["PROPERTY"][$prop_id]["PROPERTY_AVITO"];
			if($prop_code == "FACE" && $value == "Цифровой"){
				$ar["Mechanism"] = "Электронные";
				continue;
			}
			
			if($this->matchProperty[$prop_code] && $this->matchProperty[$prop_code][$value] && !$ar[$prop_avito]){
				$ar[$prop_avito] = $this->matchProperty[$prop_code][$value];
			}
		}
		return $ar;
    }
	
	private function getSettings(){
		foreach($this->settings["PROPERTY"] as $arItem){
			$filename = $_SERVER["DOCUMENT_ROOT"] . "/upload/avito/" . $arItem["FILE"];
			$data = $this->_parseFile($filename);
			
			foreach($data as $k => $v){
				if($k == 0 || !is_array($v) || count($v) != 2) continue;
				$this->matchProperty[$arItem["BX"]][$v[0]] = $v[1];
				
			}
			//prent($this->matchProperty);
		}
	}
	
	private function _parseFile(string $filename = ""){
		$arCsv = [];
		
		if(file_exists($filename)){
			$handle = fopen($filename, "r");
				
			$array_line_full = array();
			$k = 0;
			while (($line = fgetcsv($handle, 0, ";")) !== FALSE) {
				$arCsv[] = $line;
				/*if($k > 0 && is_array($line) && count($line) == 2){
					$arCsv[] = [
						"BX" => $line[0],
						"AVITO" => $line[1],
					];
				}
				$k++;*/
			}

			fclose($handle); //Закрываем файл
		}
		return $arCsv;
	}
}
?>
