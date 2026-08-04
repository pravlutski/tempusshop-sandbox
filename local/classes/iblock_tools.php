<?php

/**
 * IBlock tools service
 */
class CIBlockTools
{
    private static $tools = null;
    private static $cacheKey = 'CIBlockTools';

    private $arIBlockIds;
	private $arIBlockNames;
    private $arPropertyIds;
    private $arPropertyValueIds;
	private $arSectionIsAvail;
	private $arSectionURL;
	private $arSectionCode;

    /**
     * Init service
     * @return CIBlockTools
     */
    private static function Init(){
        if(!self::$tools) self::$tools = new CIBlockTools();
        return self::$tools;
    }

    /**
     * Get IBlock ID
     * @param string $iblockCode IBlock CODE
     * @return integer|null
     */
    public static function GetIBlockId($iblockCode){
        return self::Init()->GetIBlockIdPr($iblockCode);
    }
	/**
     * Get IBlock Name
     * @param string $iblockCode IBlock CODE
     * @return integer|null
     */
    public static function GetIBlockName($iblockCode){
        return self::Init()->GetIBlockNamePr($iblockCode);
    }
    /**
     * Get IBlock property ID
     * @param string $iblockCode IBlock CODE
     * @param string $propCode Property CODE
     * @return integer|null
     */
    public static function GetPropertyId($iblockCode, $propCode){
        return self::Init()->GetPropertyIdPr($iblockCode, $propCode);
    }

	/* доступен данный раздел для сайта */
	public static function GetSectionIsAvail($site_id, $section_id){
        return self::Init()->GetSectionIsAvailPr($site_id, $section_id);
    }
	/* id раздела по url */
	public static function GetSectionIdByURL($url){
        return self::Init()->GetSectionIdByUrlPr($url);
    }
	/* id раздела по коду */
	public static function GetSectionIdByCode($code){
        return self::Init()->GetSectionIdByCodePr($code);
    }

    /**
     * Get IBlock property enum value ID
     * @param string $iblockCode IBlock CODE
     * @param string $propCode Property CODE
     * @param string $xmlId Property value XML_ID
     * @return integer|null
     */
    public static function GetPropertyEnumValueId($iblockCode, $propCode, $xmlId){
        return self::Init()->GetPropertyEnumValueIdPr($iblockCode, $propCode, $xmlId);
    }

    /**
     * Clear service cache
     * @return boolean
     */
    public static function Update(){
        DeleteDirFilesEx('/bitrix/cache/' . self::$cacheKey);
        return true;
    }

    private function __construct() {
        $cache = new CPHPCache();
        $cache_time = 2592000; // month
        $cache_id = self::$cacheKey;
        $cache_path = '/'.self::$cacheKey.'/';

        if($cache->InitCache($cache_time, $cache_id, $cache_path))
        {
            $vars = $cache->GetVars();
            $this->arIBlockIds = $vars['arIBlockIds'];
			$this->arIBlockNames = $vars['arIBlockNames'];
            $this->arPropertyIds = $vars['arPropertyIds'];
            $this->arPropertyValueIds = $vars['arPropertyValueIds'];
			$this->arSectionIsAvail = $vars['arSectionIsAvail'];
			$this->arSectionURL = $vars['arSectionURL'];
			$this->arSectionCode = $vars['arSectionCode'];
        }
        else
        {
            $cache->StartDataCache($cache_time, $cache_id, $cache_path);

            $this->SetIBlocks();
            $this->SetProperties();
			
			$this->SetCatalog();

            $cache->EndDataCache(array(
                'arIBlockIds' => $this->arIBlockIds,
				'arIBlockNames' => $this->arIBlockNames,
                'arPropertyIds'  => $this->arPropertyIds,
                'arPropertyValueIds' => $this->arPropertyValueIds,
				'arSectionIsAvail' => $this->arSectionIsAvail,
				'arSectionURL' => $this->arSectionURL,
				'arSectionCode' => $this->arSectionCode,
				
            ));
        }
    }

    private function SetIBlocks(){
        $this->arIBlockIds = array();
		$this->arIBlockNames = array();
        if(!CModule::IncludeModule("iblock")) return;

        $db = CIBlock::GetList(
            array('ID' => 'ASC'),
            array(
                'ACTIVE' => 'Y',
            )
        );
        while($arr = $db->Fetch()){
            if($arr['CODE']){
                $this->arIBlockIds[$arr['CODE']] = intval($arr['ID']);
				$this->arIBlockNames[$arr['CODE']] = $arr['NAME'];
            }
        }
    }
	private function SetCatalog(){
        $this->arSectionIsAvail = array();
		$this->arSectionURL = array();
		$this->arSectionCode = array();
        if(!CModule::IncludeModule("iblock")) return;
		$db = CIBlockSection::GetList(array("SORT" => "ASC"), $arFilter = array("IBLOCK_ID"=>CProSet::IB_CATALOG), true, array("ID", "CODE", "SECTION_PAGE_URL", "UF_SITE_ID")); 
		while($arr = $db->GetNext()){   
			foreach($arr["UF_SITE_ID"] as $PROP){
				$rsEnum = CUserFieldEnum::GetList(array(), array("ID" =>$PROP)); 
				$arEnum = $rsEnum->GetNext(); 
				$this->arSectionIsAvail[$arEnum["XML_ID"]][$arr["ID"]] = true;
			}
			if(empty($this->arSectionIsAvail["s1"][$arr["ID"]]))
				$this->arSectionIsAvail["s1"][$arr["ID"]] = false;
			if(empty($this->arSectionIsAvail["s2"][$arr["ID"]]))
				$this->arSectionIsAvail["s2"][$arr["ID"]] = false;

			$this->arSectionURL[$arr["SECTION_PAGE_URL"]] = $arr["ID"];
			$this->arSectionCode[$arr["CODE"]] = $arr["ID"];
		}
    }
    private function SetProperties(){
        $this->arPropertyIds = array();
        $this->arPropertyValueIds = array();

        if(!CModule::IncludeModule("iblock")) return;

        $db = CIBlockProperty::GetList(
            array(),
            array('ACTIVE' => 'Y')
        );
        while($arr = $db->Fetch()){
            if(is_null($this->arPropertyIds[$arr['IBLOCK_ID']]))
                $this->arPropertyIds[$arr['IBLOCK_ID']] = array();

            if($arr['CODE']){
                $this->arPropertyIds[$arr['IBLOCK_ID']][$arr['CODE']] = intval($arr['ID']);

                if($arr['PROPERTY_TYPE'] == 'L'){
                    if(is_null($this->arPropertyValueIds[$arr['ID']]))
                        $this->arPropertyValueIds[$arr['ID']] = array();

                    $resProp = CIBlockPropertyEnum::GetList(
                        array(),
                        array('PROPERTY_ID' => $arr['ID'])
                    );
                    while($arrProp=$resProp->Fetch()){
                        if($arrProp['XML_ID']){
                            $this->arPropertyValueIds[$arr['ID']][$arrProp['XML_ID']] = intval($arrProp['ID']);
                        }
                    }
                }
            }
        }
    }

    private function GetIBlockIdPr($iblockCode){
        if(isset($this->arIBlockIds[$iblockCode]))
            return $this->arIBlockIds[$iblockCode];

        return null;
    }
	private function GetIBlockNamePr($iblockCode){
        if(isset($this->arIBlockNames[$iblockCode]))
            return $this->arIBlockNames[$iblockCode];

        return null;
    }
	private function GetSectionIsAvailPr($site_id, $section_id){
		if(!$site_id || !$section_id) return null;
        if(isset($this->arSectionIsAvail[$site_id][$section_id]))
            return $this->arSectionIsAvail[$site_id][$section_id];

        return null;
    }
	private function GetSectionIdByUrlPr($url){
		if(!$url) return null;
        if(isset($this->arSectionURL[$url]))
            return $this->arSectionURL[$url];

        return null;
    }
	private function GetSectionIdByCodePr($code){
		if(!$code) return null;
        if(isset($this->arSectionCode[$code]))
            return $this->arSectionCode[$code];

        return null;
    }
	
    private function GetPropertyIdPr($iblockCode, $propCode){
        $iblockId = $this->GetIBlockId($iblockCode);
        if(!$iblockId) return null;

        if(isset($this->arPropertyIds[$iblockId]) && isset($this->arPropertyIds[$iblockId][$propCode]))
            return $this->arPropertyIds[$iblockId][$propCode];

        return null;
    }

    private function GetPropertyEnumValueIdPr($iblockCode, $propCode, $xmlId){
        $propId = $this->GetPropertyId($iblockCode, $propCode);
        if(!$propId) return null;

        if(isset($this->arPropertyValueIds[$propId]) && isset($this->arPropertyValueIds[$propId][$xmlId]))
            return $this->arPropertyValueIds[$propId][$xmlId];

        return null;
    }
}

// IBlock events
AddEventHandler('iblock', 'OnAfterIBlockAdd', array('CIBlockTools', 'Update'));
AddEventHandler('iblock', 'OnAfterIBlockUpdate', array('CIBlockTools', 'Update'));
AddEventHandler('iblock', 'OnBeforeIBlockDelete', array('CIBlockTools', 'Update'));

// IBlock property events
AddEventHandler('iblock', 'OnAfterIBlockPropertyAdd', array('CIBlockTools', 'Update'));
AddEventHandler('iblock', 'OnAfterIBlockPropertyUpdate', array('CIBlockTools', 'Update'));
AddEventHandler('iblock', 'OnBeforeIBlockPropertyDelete', array('CIBlockTools', 'Update'));