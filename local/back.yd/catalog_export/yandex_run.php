<?
//<title>Yandex</title>
/** @global CUser $USER */
/** @global CMain $APPLICATION */
/** @var int $IBLOCK_ID */
/** @var string $SETUP_SERVER_NAME */
/** @var string $SETUP_FILE_NAME */
/** @var array $V */
/** @var array|string $XML_DATA */
/** @var bool $firstStep */
/** @var int $CUR_ELEMENT_ID */
/** @var bool $finalExport */
/** @var bool $boolNeedRootSection */
/** @var int $intMaxSectionID */

//use Bitrix\Currency,
//	Bitrix\Iblock,
//	Bitrix\Catalog;
use Bitrix\Main,
	Bitrix\Main\Loader,
	Bitrix\Currency,
	Bitrix\Iblock,
	Bitrix\Catalog,
	Bitrix\Sale;

IncludeModuleLangFile($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/catalog/export_yandex.php');
IncludeModuleLangFile($_SERVER['DOCUMENT_ROOT'].'/bitrix/php_interface/include/catalog_export/export_lang.php');
$MAX_EXECUTION_TIME = (isset($MAX_EXECUTION_TIME) ? (int)$MAX_EXECUTION_TIME : 0);
if ($MAX_EXECUTION_TIME <= 0)
	$MAX_EXECUTION_TIME = 0;
if (defined('BX_CAT_CRON') && BX_CAT_CRON == true)
{
	$MAX_EXECUTION_TIME = 0;
	$firstStep = true;
}
if (defined("CATALOG_EXPORT_NO_STEP") && CATALOG_EXPORT_NO_STEP == true)
{
	$MAX_EXECUTION_TIME = 0;
	$firstStep = true;
}
if ($MAX_EXECUTION_TIME == 0)
	set_time_limit(0);
if (!isset($firstStep))
	$firstStep = true;

$pageSize = 5000;
$navParams = array('nTopCount' => $pageSize);

$SETUP_VARS_LIST = 'IBLOCK_ID,V,XML_DATA,SETUP_SERVER_NAME,SETUP_FILE_NAME,USE_HTTPS,FILTER_AVAILABLE,DISABLE_REFERERS,MAX_EXECUTION_TIME';
$INTERNAL_VARS_LIST = 'intMaxSectionID,boolNeedRootSection,arSectionIDs,arAvailGroups';
if (!function_exists("stripInvalidXml")){
	function stripInvalidXml($value){
		$ret = "";
		$current;
		if (empty($value))
		{
			return $ret;
		}

		$length = strlen($value);
		for ($i=0; $i < $length; $i++)
		{
			$current = ord($value[$i]);
			if (($current == 0x9) ||
				($current == 0xA) ||
				($current == 0xD) ||
				(($current >= 0x20) && ($current <= 0xD7FF)) ||
				(($current >= 0xE000) && ($current <= 0xFFFD)) ||
				(($current >= 0x10000) && ($current <= 0x10FFFF)))
			{
				$ret .= chr($current);
			}
			else
			{
				$ret .= " ";
			}
		}
		return $ret;
	}
}
global $USER, $APPLICATION;
$bTmpUserCreated = false;
if (!CCatalog::IsUserExists())
{
	$bTmpUserCreated = true;
	if (isset($USER))
		$USER_TMP = $USER;
	$USER = new CUser();
}

CCatalogDiscountSave::Disable();
/** @noinspection PhpDeprecationInspection */
CCatalogDiscountCoupon::ClearCoupon();
if ($USER->IsAuthorized())
{
	/** @noinspection PhpDeprecationInspection */
	CCatalogDiscountCoupon::ClearCouponsByManage($USER->GetID());
}

$arYandexFields = array(
	'typePrefix', 'vendor', 'vendorCode', 'model',
	'author', 'name', 'publisher', 'series', 'year',
	'ISBN', 'volume', 'part', 'language', 'binding',
	'page_extent', 'table_of_contents', 'performed_by', 'performance_type',
	'storage', 'format', 'recording_length', 'artist', 'title', 'year', 'media',
	'starring', 'director', 'originalName', 'country', 'aliases',
	'description', 'sales_notes', 'promo', 'provider', 'tarifplan',
	'xCategory', 'additional', 'worldRegion', 'region', 'days', 'dataTour',
	'hotel_stars', 'room', 'meal', 'included', 'transport', 'price_min', 'price_max',
	'options', 'manufacturer_warranty', 'country_of_origin', 'downloadable', 'adult', 'param',
	'place', 'hall', 'hall_part', 'is_premiere', 'is_kids', 'date'
);

$formatList = array(
	'none' => array(
		'vendor', 'vendorCode', 'sales_notes', 'manufacturer_warranty', 'country_of_origin',
		'adult'
	),
	'vendor.model' => array(
		'typePrefix', 'vendor', 'vendorCode', 'model', 'sales_notes', 'manufacturer_warranty', 'country_of_origin',
		'adult'
	),
	'book' => array(
		'author', 'publisher', 'series', 'year', 'ISBN', 'volume', 'part', 'language', 'binding',
		'page_extent', 'table_of_contents', 'sales_notes'
	),
	'audiobook' => array(
		'author', 'publisher', 'series', 'year', 'ISBN', 'performed_by', 'performance_type',
		'language', 'volume', 'part', 'format', 'storage', 'recording_length', 'table_of_contents'
	),
	'artist.title' => array(
		'title', 'artist', 'director', 'starring', 'originalName', 'country', 'year', 'media', 'adult'
	)
);

if (!function_exists("yandex_replace_special"))
{
	function yandex_replace_special($arg)
	{
		if (in_array($arg[0], array("&quot;", "&amp;", "&lt;", "&gt;")))
			return $arg[0];
		else
			return " ";
	}
}

if (!function_exists("yandex_text2xml"))
{
	function yandex_text2xml($text, $bHSC = false, $bDblQuote = false)
	{
		global $APPLICATION;

		$bHSC = (true == $bHSC ? true : false);
		$bDblQuote = (true == $bDblQuote ? true: false);

		if ($bHSC)
		{
			$text = htmlspecialcharsbx($text);
			if ($bDblQuote)
				$text = str_replace('&quot;', '"', $text);
		}
		$text = preg_replace("/[\x1-\x8\xB-\xC\xE-\x1F]/", "", $text);
		$text = str_replace("'", "&apos;", $text);
		$text = $APPLICATION->ConvertCharset($text, LANG_CHARSET, 'windows-1251');
		return $text;
	}
}

if (!function_exists('yandex_get_value'))
{
function yandex_get_value($arOffer, $param, $PROPERTY, $arProperties, $arUserTypeFormat, $usedProtocol)
{
	global $iblockServerName;

	$strProperty = '';
	$bParam = (strncmp($param, 'PARAM_', 6) == 0);
	if (isset($arProperties[$PROPERTY]) && !empty($arProperties[$PROPERTY]))
	{
		$iblockProperty = $arProperties[$PROPERTY];
		$PROPERTY_CODE = $iblockProperty['CODE'];
		if (!isset($arOffer['PROPERTIES'][$PROPERTY_CODE]) && !isset($arOffer['PROPERTIES'][$PROPERTY]))
			return $strProperty;
		$arProperty = (
			isset($arOffer['PROPERTIES'][$PROPERTY_CODE])
			? $arOffer['PROPERTIES'][$PROPERTY_CODE]
			: $arOffer['PROPERTIES'][$PROPERTY]
		);
		if ($arProperty['ID'] != $PROPERTY)
			return $strProperty;

		$value = '';
		$description = '';
		switch ($iblockProperty['PROPERTY_TYPE'])
		{
			case 'USER_TYPE':
				if ($iblockProperty['MULTIPLE'] == 'Y')
				{
					if (!empty($arProperty['~VALUE']))
					{
						$arValues = array();
						foreach($arProperty["~VALUE"] as $oneValue)
						{
							$isArray = is_array($oneValue);
							if (
								($isArray && !empty($oneValue))
								|| (!$isArray && $oneValue != '')
							)
							{
								$arValues[] = call_user_func_array($arUserTypeFormat[$PROPERTY],
									array(
										$iblockProperty,
										array("VALUE" => $oneValue),
										array('MODE' => 'SIMPLE_TEXT'),
									)
								);
							}
						}
						$value = implode(', ', $arValues);
					}
				}
				else
				{
					$isArray = is_array($arProperty['~VALUE']);
					if (
						($isArray && !empty($arProperty['~VALUE']))
						|| (!$isArray && $arProperty['~VALUE'] != '')
					)
					{
						$value = call_user_func_array($arUserTypeFormat[$PROPERTY],
							array(
								$iblockProperty,
								array("VALUE" => $arProperty["~VALUE"]),
								array('MODE' => 'SIMPLE_TEXT'),
							)
						);
					}
				}
				break;
			case Iblock\PropertyTable::TYPE_ELEMENT:
				if (!empty($arProperty['VALUE']))
				{
					$arCheckValue = array();
					if (!is_array($arProperty['VALUE']))
					{
						$arProperty['VALUE'] = (int)$arProperty['VALUE'];
						if ($arProperty['VALUE'] > 0)
							$arCheckValue[] = $arProperty['VALUE'];
					}
					else
					{
						foreach ($arProperty['VALUE'] as $intValue)
						{
							$intValue = (int)$intValue;
							if ($intValue > 0)
								$arCheckValue[] = $intValue;
						}
						unset($intValue);
					}
					if (!empty($arCheckValue))
					{
						$filter = array(
							'@ID' => $arCheckValue
						);
						if ($iblockProperty['LINK_IBLOCK_ID'] > 0)
							$filter['=IBLOCK_ID'] = $iblockProperty['LINK_IBLOCK_ID'];

						$iterator = Iblock\ElementTable::getList(array(
							'select' => array('ID', 'NAME'),
							'filter' => array($filter)
						));
						while ($row = $iterator->fetch())
						{
							$value .= ($value ? ', ' : '').$row['NAME'];
						}
						unset($row, $iterator);
					}
				}
				break;
			case Iblock\PropertyTable::TYPE_SECTION:
				if (!empty($arProperty['VALUE']))
				{
					$arCheckValue = array();
					if (!is_array($arProperty['VALUE']))
					{
						$arProperty['VALUE'] = (int)$arProperty['VALUE'];
						if ($arProperty['VALUE'] > 0)
							$arCheckValue[] = $arProperty['VALUE'];
					}
					else
					{
						foreach ($arProperty['VALUE'] as $intValue)
						{
							$intValue = (int)$intValue;
							if ($intValue > 0)
								$arCheckValue[] = $intValue;
						}
						unset($intValue);
					}
					if (!empty($arCheckValue))
					{
						$filter = array(
							'@ID' => $arCheckValue
						);
						if ($iblockProperty['LINK_IBLOCK_ID'] > 0)
							$filter['=IBLOCK_ID'] = $iblockProperty['LINK_IBLOCK_ID'];

						$iterator = Iblock\SectionTable::getList(array(
							'select' => array('ID', 'NAME'),
							'filter' => array($filter)
						));
						while ($row = $iterator->fetch())
						{
							$value .= ($value ? ', ' : '').$row['NAME'];
						}
						unset($row, $iterator);
					}
				}
				break;
			case Iblock\PropertyTable::TYPE_LIST:
				if (!empty($arProperty['~VALUE']))
				{
					if (is_array($arProperty['~VALUE']))
						$value .= implode(', ', $arProperty['~VALUE']);
					else
						$value .= $arProperty['~VALUE'];
				}
				break;
			case Iblock\PropertyTable::TYPE_FILE:
				if (!empty($arProperty['VALUE']))
				{
					if (is_array($arProperty['VALUE']))
					{
						foreach ($arProperty['VALUE'] as $intValue)
						{
							$intValue = (int)$intValue;
							if ($intValue > 0)
							{
								if ($ar_file = CFile::GetFileArray($intValue))
								{
									if(substr($ar_file["SRC"], 0, 1) == "/")
										$strFile = $usedProtocol.$iblockServerName.CHTTP::urnEncode($ar_file['SRC'], 'utf-8');
									else
										$strFile = $ar_file["SRC"];
									$value .= ($value ? ', ' : '').$strFile;
								}
							}
						}
						unset($intValue);
					}
					else
					{
						$arProperty['VALUE'] = (int)$arProperty['VALUE'];
						if ($arProperty['VALUE'] > 0)
						{
							if ($ar_file = CFile::GetFileArray($arProperty['VALUE']))
							{
								if(substr($ar_file["SRC"], 0, 1) == "/")
									$strFile = $usedProtocol.$iblockServerName.CHTTP::urnEncode($ar_file['SRC'], 'utf-8');
								else
									$strFile = $ar_file["SRC"];
								$value = $strFile;
							}
						}
					}
				}
				break;
			default:
				if ($bParam && $iblockProperty['WITH_DESCRIPTION'] == 'Y')
				{
					$description = $arProperty['~DESCRIPTION'];
					$value = $arProperty['~VALUE'];
				}
				else
				{
					$value = is_array($arProperty['~VALUE']) ? implode(', ', $arProperty['~VALUE']) : $arProperty['~VALUE'];
				}
		}

		// !!!! check multiple properties and properties like CML2_ATTRIBUTES

		if ($bParam)
		{
			if (is_array($description))
			{
				foreach ($value as $key => $val)
				{
					$strProperty .= $strProperty ? "\n" : "";
					$strProperty .= '<param name="'.yandex_text2xml($description[$key], true).'">'.
						yandex_text2xml($val, true).'</param>';
				}
			}
			else
			{
				$strProperty .= '<param name="'.yandex_text2xml($iblockProperty['NAME'], true).'">'.
					yandex_text2xml($value, true).'</param>';
			}
		}
		else
		{
			$param_h = yandex_text2xml($param, true);
			$strProperty .= '<'.$param_h.'>'.yandex_text2xml($value, true).'</'.$param_h.'>';
		}

		unset($iblockProperty);
	}

	return $strProperty;
}
}

if (!function_exists('yandexPrepareItems'))
{
	function yandexPrepareItems(array &$list, array $options)
	{
		foreach (array_keys($list) as $index)
		{
			$row = &$list[$index];

			$row['DETAIL_PAGE_URL'] = (string)$row['DETAIL_PAGE_URL'];
			if ($row['DETAIL_PAGE_URL'] !== '')
			{
				$safeRow = array();
				foreach ($row as $field => $value)
				{
					if ($field == 'PREVIEW_TEXT' || $field == 'DETAIL_TEXT')
						continue;
					if (strncmp($field, 'CATALOG_', 8) == 0)
						continue;
					if (is_array($value))
						continue;
					if (preg_match("/[;&<>\"]/", $value))
						$safeRow[$field] = htmlspecialcharsEx($value);
					else
						$safeRow[$field] = $value;
					$safeRow['~'.$field] = $value;
				}
				unset($field, $value);
				$row['DETAIL_PAGE_URL'] = \CIBlock::ReplaceDetailUrl($safeRow['~DETAIL_PAGE_URL'], $safeRow, true, 'E');
				unset($safeRow);
			}

			if ($row['DETAIL_PAGE_URL'] == '')
				$row['DETAIL_PAGE_URL'] = '/';
			else
				$row['DETAIL_PAGE_URL'] = str_replace(' ', '%20', $row['DETAIL_PAGE_URL']);

			$row['PICTURE'] = false;
			$row['DETAIL_PICTURE'] = (int)$row['DETAIL_PICTURE'];
			$row['PREVIEW_PICTURE'] = (int)$row['PREVIEW_PICTURE'];
			if ($row['DETAIL_PICTURE'] > 0 || $row['PREVIEW_PICTURE'] > 0)
			{

				if($options["PROFILE_1"] === true){
					$pictureFile = CFile::GetFileArray($row['PREVIEW_PICTURE'] > 0 ? $row['PREVIEW_PICTURE'] : $row['DETAIL_PICTURE']);
				}else{
					$pictureFile = CFile::GetFileArray($row['DETAIL_PICTURE'] > 0 ? $row['DETAIL_PICTURE'] : $row['PREVIEW_PICTURE']);
				}
				if (!empty($pictureFile))
				{
					if (strncmp($pictureFile['SRC'], '/', 1) == 0)
						$picturePath = $options['PROTOCOL'].$options['SITE_NAME'].CHTTP::urnEncode($pictureFile['SRC'], 'utf-8');
					else
						$picturePath = $pictureFile['SRC'];
					$row['PICTURE'] = $picturePath;
					unset($picturePath);
				}
				unset($pictureFile);
			}

			$row['DESCRIPTION'] = '';
			if ($row['PREVIEW_TEXT'] !== null)
			{
				$row['DESCRIPTION'] = yandex_text2xml(
					TruncateText(
						$row['PREVIEW_TEXT_TYPE'] == 'html'
						? strip_tags(preg_replace_callback("'&[^;]*;'", 'yandex_replace_special', $row['PREVIEW_TEXT']))
						: preg_replace_callback("'&[^;]*;'", 'yandex_replace_special', $row['PREVIEW_TEXT']),
						$options['MAX_DESCRIPTION_LENGTH']
					),
					true
				);
			}

			unset($row);
		}
		unset($index);
	}
}

$arRunErrors = array();






if (isset($XML_DATA))
{
	if (is_string($XML_DATA) && CheckSerializedData($XML_DATA))
		$XML_DATA = unserialize(stripslashes($XML_DATA));
}
if (!isset($XML_DATA) || !is_array($XML_DATA))
	$arRunErrors[] = GetMessage('YANDEX_ERR_BAD_XML_DATA');



$yandexFormat = 'none';
if (isset($XML_DATA['TYPE']) && isset($formatList[$XML_DATA['TYPE']]))
	$yandexFormat = $XML_DATA['TYPE'];

$productFormat = ($yandexFormat != 'none' ? ' type="'.htmlspecialcharsbx($yandexFormat).'"' : '');

$fields = array();
$parametricFields = array();
$fieldsExist = !empty($XML_DATA['XML_DATA']) && is_array($XML_DATA['XML_DATA']);
$parametricFieldsExist = false;
if ($fieldsExist)
{
	foreach ($XML_DATA['XML_DATA'] as $key => $value)
	{
		if ($key == 'PARAMS')
			$parametricFieldsExist = (!empty($value) && is_array($value));
		if (is_array($value))
			continue;
		$value = (string)$value;
		if ($value == '')
			continue;
		$fields[$key] = $value;
	}
	unset($key, $value);
	$fieldsExist = !empty($fields);
}

if ($parametricFieldsExist)
{
	$parametricFields = $XML_DATA['XML_DATA']['PARAMS'];
	if (!empty($parametricFields))
	{
		foreach (array_keys($parametricFields) as $index)
		{
			if ((string)$parametricFields[$index] === '')
				unset($parametricFields[$index]);
		}
	}
	$parametricFieldsExist = !empty($parametricFields);
}

$needProperties = $fieldsExist || $parametricFieldsExist;
$yandexNeedPropertyIds = array();
if ($fieldsExist)
{
	foreach ($fields as $id)
		$yandexNeedPropertyIds[$id] = true;
	unset($id);
}
if ($parametricFieldsExist)
{
	foreach ($parametricFields as $id)
		$yandexNeedPropertyIds[$id] = true;
	unset($id);
}


global $DB;
/* op */
//для этой выгрузки подменяем offer id на XML_ID и цена со скидкой , без дополнительного текста в названии товара
$isTempusEx = false;
$showPrice = true;
$arSectionMain = [];
if($_REQUEST["PROFILE_ID"] == 30 || $SETUP_FILE_NAME == "/bitrix/catalog_export/yandex_277848.php" || $_REQUEST["PROFILE_ID"] == 46 || $SETUP_FILE_NAME == "/bitrix/catalog_export/yandex_277848_BY.php" || $_REQUEST["PROFILE_ID"] == 51 || $SETUP_FILE_NAME == "/bitrix/catalog_export/yandex_kz_imp.php"){
	$isTempusEx = true;
	$showPrice = false;

	if($_REQUEST["PROFILE_ID"] == 46 || $SETUP_FILE_NAME == "/bitrix/catalog_export/yandex_277848_BY.php"){
		$showPrice = true;
	}

	if($_REQUEST["PROFILE_ID"] == 51 || $SETUP_FILE_NAME == "/bitrix/catalog_export/yandex_kz_imp.php"){
		$showPrice = false;
	}
	$strSql = "SELECT * FROM ci_catalog_barcode GROUP BY PRODUCT_ID,BARCODE";

	$results = $DB->Query($strSql, false, $err_mess.__LINE__);
	while ($row = $results->Fetch()){
		if($row["PRODUCT_ID"] && $row["BARCODE"]){
			$arBarcode[$row["PRODUCT_ID"]][$row["BARCODE"]] = $row["BARCODE"];
		}

	}

	$strSql = "SELECT * FROM b_iblock_section_element WHERE IBLOCK_SECTION_ID <> 370";

	$results = $DB->Query($strSql, false, $err_mess.__LINE__);
	while ($row = $results->Fetch()){
		$arSectionMain[$row["IBLOCK_ELEMENT_ID"]] = $row["IBLOCK_SECTION_ID"];
	}
	//$yandexNeedPropertyIds[2804] = true;//AEN
	//$yandexNeedPropertyIds[2821] = true;//AEN2
	//prent($arBarcode);die;/
}

/*
global $USER;
if($USER->isAdmin()){
	prent($SETUP_FILE_NAME);die;
}*/
if($isTempusEx == false){
	$arAddText = array();//массив с дополнительным текстом
	$rsSect = \CIBlockSection::GetList(array("depth_level" => "asc"), array('IBLOCK_ID' => CProSet::IB_CATALOG), false, array("UF_TYPE_CPMARKET"));

	while($arSect = $rsSect->Fetch()){
		if(!$arSect["UF_TYPE_CPMARKET"] && isset($arAddText[$arSect["IBLOCK_SECTION_ID"]]))
			$arAddText[$arSect["ID"]] = $arAddText[$arSect["IBLOCK_SECTION_ID"]];
		else
			$arAddText[$arSect["ID"]] = $arSect["UF_TYPE_CPMARKET"];
	}
}


/* end op */

$propertyFields = array(
	'ID', 'PROPERTY_TYPE', 'MULTIPLE', 'USER_TYPE'
);

$IBLOCK_ID = (int)$IBLOCK_ID;
$db_iblock = CIBlock::GetByID($IBLOCK_ID);
if (!($ar_iblock = $db_iblock->Fetch()))
{
	$arRunErrors[] = str_replace('#ID#', $IBLOCK_ID, GetMessage('YANDEX_ERR_NO_IBLOCK_FOUND_EXT'));
}
/*elseif (!CIBlockRights::UserHasRightTo($IBLOCK_ID, $IBLOCK_ID, 'iblock_admin_display'))
{
	$arRunErrors[] = str_replace('#IBLOCK_ID#',$IBLOCK_ID,GetMessage('CET_ERROR_IBLOCK_PERM'));
} */
else
{
	if($XML_DATA['PRICE'] == 1)
		$ar_iblock["LID"] = "s1";
	elseif($XML_DATA['PRICE'] == 2)
		$ar_iblock["LID"] = "s2";
	$SETUP_SERVER_NAME = trim($SETUP_SERVER_NAME);

	if (strlen($SETUP_SERVER_NAME) <= 0)
	{
		if (strlen($ar_iblock['SERVER_NAME']) <= 0)
		{
			$b = "sort";
			$o = "asc";
			$rsSite = CSite::GetList($b, $o, array("LID" => $ar_iblock["LID"]));
			if($arSite = $rsSite->Fetch())
				$ar_iblock["SERVER_NAME"] = $arSite["SERVER_NAME"];
			if(strlen($ar_iblock["SERVER_NAME"])<=0 && defined("SITE_SERVER_NAME"))
				$ar_iblock["SERVER_NAME"] = SITE_SERVER_NAME;
			if(strlen($ar_iblock["SERVER_NAME"])<=0)
				$ar_iblock["SERVER_NAME"] = COption::GetOptionString("main", "server_name", "");
		}
	}
	else
	{
		$ar_iblock['SERVER_NAME'] = $SETUP_SERVER_NAME;
	}
	$ar_iblock['PROPERTY'] = array();
	$rsProps = \CIBlockProperty::GetList(
		array('SORT' => 'ASC', 'NAME' => 'ASC'),
		array('IBLOCK_ID' => $IBLOCK_ID, 'ACTIVE' => 'Y', 'CHECK_PERMISSIONS' => 'N')
	);
	while ($arProp = $rsProps->Fetch())
	{
		$arProp['ID'] = (int)$arProp['ID'];
		$arProp['USER_TYPE'] = (string)$arProp['USER_TYPE'];
		$arProp['CODE'] = (string)$arProp['CODE'];
		if ($arProp['CODE'] == '')
			$arProp['CODE'] = $arProp['ID'];
		$arProp['LINK_IBLOCK_ID'] = (int)$arProp['LINK_IBLOCK_ID'];
		$ar_iblock['PROPERTY'][$arProp['ID']] = $arProp;
	}
}
/******************/
$SETUP_SERVER_NAME = (isset($SETUP_SERVER_NAME) ? trim($SETUP_SERVER_NAME) : '');
$COMPANY_NAME = (isset($COMPANY_NAME) ? trim($COMPANY_NAME) : '');

$SITE_ID = (isset($SITE_ID) ? (string)$SITE_ID : '');
if ($SITE_ID === '')
	$SITE_ID = $ar_iblock['LID'];
$iterator = Main\SiteTable::getList(array(
	'select' => array('LID', 'SERVER_NAME', 'SITE_NAME', 'NAME', 'DIR'),
	'filter' => array('=LID' => $SITE_ID, '=ACTIVE' => 'Y')
));

if($SITE_ID == "s2"){
	$arYandexFields = array(
		'typePrefix', 'vendor', 'vendorCode', 'model',
		'author', 'name', 'publisher', 'series', 'year',
		'ISBN', 'volume', 'part', 'language', 'binding',
		'page_extent', 'table_of_contents', 'performed_by', 'performance_type',
		'storage', 'format', 'recording_length', 'artist', 'title', 'year', 'media',
		'starring', 'director', 'originalName', 'country', 'aliases',
		'description', 'promo', 'provider', 'tarifplan',
		'xCategory', 'additional', 'worldRegion', 'region', 'days', 'dataTour',
		'hotel_stars', 'room', 'meal', 'included', 'transport', 'price_min', 'price_max',
		'options', 'manufacturer_warranty', 'country_of_origin', 'downloadable', 'adult', 'param',
		'place', 'hall', 'hall_part', 'is_premiere', 'is_kids', 'date'
	);
}
$site = $iterator->fetch();
unset($iterator);
if (empty($site))
{
	$arRunErrors[] = GetMessage('BX_CATALOG_EXPORT_YANDEX_ERR_BAD_SITE');
}
else
{
	$site['SITE_NAME'] = (string)$site['SITE_NAME'];
	if ($site['SITE_NAME'] === '')
		$site['SITE_NAME'] = (string)Main\Config\Option::get('main', 'site_name');
	$site['COMPANY_NAME'] = $COMPANY_NAME;
	if ($site['COMPANY_NAME'] === '')
		$site['COMPANY_NAME'] = (string)$site['NAME'];
	if ($site['COMPANY_NAME'] === '')
		$site['COMPANY_NAME'] = (string)Main\Config\Option::get('main', 'site_name');
	$site['SERVER_NAME'] = (string)$site['SERVER_NAME'];
	if ($SETUP_SERVER_NAME !== '')
		$site['SERVER_NAME'] = $SETUP_SERVER_NAME;
	if ($site['SERVER_NAME'] === '')
	{
		$site['SERVER_NAME'] = (defined('SITE_SERVER_NAME')
			? SITE_SERVER_NAME
			: (string)Main\Config\Option::get('main', 'server_name')
		);
	}
	if ($site['SERVER_NAME'] === '')
	{
		$arRunErrors[] = GetMessage('BX_CATALOG_EXPORT_YANDEX_ERR_BAD_SERVER_NAME');
	}
}

/************************/
global $iblockServerName;
$iblockServerName = $ar_iblock["SERVER_NAME"];

$arProperties = array();
if (isset($ar_iblock['PROPERTY']))
	$arProperties = $ar_iblock['PROPERTY'];

$boolOffers = false;
$arOffers = false;
$arOfferIBlock = false;
$intOfferIBlockID = 0;
$offersCatalog = false;
$arSelectOfferProps = array();
$arSelectedPropTypes = array(
	Iblock\PropertyTable::TYPE_STRING,
	Iblock\PropertyTable::TYPE_NUMBER,
	Iblock\PropertyTable::TYPE_LIST,
	Iblock\PropertyTable::TYPE_ELEMENT,
	Iblock\PropertyTable::TYPE_SECTION
);
$arOffersSelectKeys = array(
	YANDEX_SKU_EXPORT_ALL,
	YANDEX_SKU_EXPORT_MIN_PRICE,
	YANDEX_SKU_EXPORT_PROP,
);
$arCondSelectProp = array(
	'ZERO',
	'NONZERO',
	'EQUAL',
	'NONEQUAL',
);
$arSKUExport = array();

$arCatalog = CCatalogSku::GetInfoByIBlock($IBLOCK_ID);
if (empty($arCatalog))
{
	$arRunErrors[] = str_replace('#ID#', $IBLOCK_ID, GetMessage('YANDEX_ERR_NO_IBLOCK_IS_CATALOG'));
}
else
{
	$arCatalog['VAT_ID'] = (int)$arCatalog['VAT_ID'];
	$arOffers = CCatalogSku::GetInfoByProductIBlock($IBLOCK_ID);
	if (!empty($arOffers['IBLOCK_ID']))
	{
		$intOfferIBlockID = $arOffers['IBLOCK_ID'];
		$rsOfferIBlocks = CIBlock::GetByID($intOfferIBlockID);
		if (($arOfferIBlock = $rsOfferIBlocks->Fetch()))
		{
			$boolOffers = true;
			$rsProps = \CIBlockProperty::GetList(
				array('SORT' => 'ASC', 'NAME' => 'ASC'),
				array('IBLOCK_ID' => $intOfferIBlockID, 'ACTIVE' => 'Y', 'CHECK_PERMISSIONS' => 'N')
			);
			while ($arProp = $rsProps->Fetch())
			{
				$arProp['ID'] = (int)$arProp['ID'];
				if ($arOffers['SKU_PROPERTY_ID'] != $arProp['ID'])
				{
					$arProp['USER_TYPE'] = (string)$arProp['USER_TYPE'];
					$arProp['CODE'] = (string)$arProp['CODE'];
					if ($arProp['CODE'] == '')
						$arProp['CODE'] = $arProp['ID'];
					$arProp['LINK_IBLOCK_ID'] = (int)$arProp['LINK_IBLOCK_ID'];

					$ar_iblock['OFFERS_PROPERTY'][$arProp['ID']] = $arProp;
					$arProperties[$arProp['ID']] = $arProp;
					if (in_array($arProp['PROPERTY_TYPE'], $arSelectedPropTypes))
						$arSelectOfferProps[] = $arProp['ID'];
				}
			}
			$arOfferIBlock['LID'] = $ar_iblock['LID'];
		}
		else
		{
			$arRunErrors[] = GetMessage('YANDEX_ERR_BAD_OFFERS_IBLOCK_ID');
		}
	}
	if ($boolOffers)
	{
		$offersCatalog = \CCatalog::GetByID($intOfferIBlockID);
		$offersCatalog['VAT_ID'] = (int)$offersCatalog['VAT_ID'];
		if (empty($XML_DATA['SKU_EXPORT']))
		{
			$arRunErrors[] = GetMessage('YANDEX_ERR_SKU_SETTINGS_ABSENT');
		}
		else
		{
			$arSKUExport = $XML_DATA['SKU_EXPORT'];
			if (empty($arSKUExport['SKU_EXPORT_COND']) || !in_array($arSKUExport['SKU_EXPORT_COND'],$arOffersSelectKeys))
			{
				$arRunErrors[] = GetMessage('YANDEX_SKU_EXPORT_ERR_CONDITION_ABSENT');
			}
			if (YANDEX_SKU_EXPORT_PROP == $arSKUExport['SKU_EXPORT_COND'])
			{
				if (empty($arSKUExport['SKU_PROP_COND']) || !is_array($arSKUExport['SKU_PROP_COND']))
				{
					$arRunErrors[] = GetMessage('YANDEX_SKU_EXPORT_ERR_PROPERTY_ABSENT');
				}
				else
				{
					if (empty($arSKUExport['SKU_PROP_COND']['PROP_ID']) || !in_array($arSKUExport['SKU_PROP_COND']['PROP_ID'],$arSelectOfferProps))
					{
						$arRunErrors[] = GetMessage('YANDEX_SKU_EXPORT_ERR_PROPERTY_ABSENT');
					}
					if (empty($arSKUExport['SKU_PROP_COND']['COND']) || !in_array($arSKUExport['SKU_PROP_COND']['COND'],$arCondSelectProp))
					{
						$arRunErrors[] = GetMessage('YANDEX_SKU_EXPORT_ERR_PROPERTY_COND_ABSENT');
					}
					else
					{
						if ($arSKUExport['SKU_PROP_COND']['COND'] == 'EQUAL' || $arSKUExport['SKU_PROP_COND']['COND'] == 'NONEQUAL')
						{
							if (empty($arSKUExport['SKU_PROP_COND']['VALUES']))
							{
								$arRunErrors[] = GetMessage('YANDEX_SKU_EXPORT_ERR_PROPERTY_VALUES_ABSENT');
							}
						}
					}
				}
			}
		}
	}
}

$propertyIdList = array_keys($arProperties);
if (empty($arRunErrors))
{
	if (
		$arCatalog['CATALOG_TYPE'] == CCatalogSku::TYPE_FULL
		|| $arCatalog['CATALOG_TYPE'] == CCatalogSku::TYPE_PRODUCT
	)
		$propertyIdList[] = $arCatalog['SKU_PROPERTY_ID'];
}

$arUserTypeFormat = array();
foreach($arProperties as $key => $arProperty)
{
	$arUserTypeFormat[$arProperty['ID']] = false;
	if ($arProperty['USER_TYPE'] == '')
		continue;

	$arUserType = \CIBlockProperty::GetUserType($arProperty['USER_TYPE']);
	if (isset($arUserType['GetPublicViewHTML']))
	{
		$arUserTypeFormat[$arProperty['ID']] = $arUserType['GetPublicViewHTML'];
		$arProperties[$key]['PROPERTY_TYPE'] = 'USER_TYPE';
	}
}

$bAllSections = false;
$arSections = array();
if (empty($arRunErrors))
{
	if (is_array($V))
	{
		foreach ($V as $key => $value)
		{
			if (trim($value)=="0")
			{
				$bAllSections = true;
				break;
			}
			$value = (int)$value;
			if ($value > 0)
			{
				$arSections[] = $value;
			}
		}
	}

	if (!$bAllSections && empty($arSections))
	{
		$arRunErrors[] = GetMessage('YANDEX_ERR_NO_SECTION_LIST');
	}
}

$selectedPriceType = 0;
if (!empty($XML_DATA['PRICE']))
{
	$XML_DATA['PRICE'] = (int)$XML_DATA['PRICE'];
	if ($XML_DATA['PRICE'] > 0)
	{
		$rsCatalogGroups = CCatalogGroup::GetGroupsList(array('CATALOG_GROUP_ID' => $XML_DATA['PRICE'],'GROUP_ID' => 2));
		if (!($arCatalogGroup = $rsCatalogGroups->Fetch()))
		{
			$arRunErrors[] = GetMessage('YANDEX_ERR_BAD_PRICE_TYPE');
		}
		else
		{
			$selectedPriceType = $XML_DATA['PRICE'];
		}
	}
	else
	{
		$arRunErrors[] = GetMessage('YANDEX_ERR_BAD_PRICE_TYPE');
	}
}

$usedProtocol = (isset($USE_HTTPS) && $USE_HTTPS == 'Y' ? 'https://' : 'http://');
$filterAvailable = (isset($FILTER_AVAILABLE) && $FILTER_AVAILABLE == 'Y');
$disableReferers = (isset($DISABLE_REFERERS) && $DISABLE_REFERERS == 'Y');

$vatExportSettings = array(
	'ENABLE' => 'N',
	'BASE_VAT' => ''
);

$vatRates = array(
	'0%' => 'VAT_0',
	'10%' => 'VAT_10',
	'18%' => 'VAT_18'
);
$vatList = array();

if (!empty($XML_DATA['VAT_EXPORT']) && is_array($XML_DATA['VAT_EXPORT']))
	$vatExportSettings = array_merge($vatExportSettings, $XML_DATA['VAT_EXPORT']);
$vatExport = $vatExportSettings['ENABLE'] == 'Y';
if ($vatExport)
{
	if ($vatExportSettings['BASE_VAT'] == '')
	{
		$vatExport = false;
	}
	else
	{
		if ($vatExportSettings['BASE_VAT'] != '-')
			$vatList[0] = 'NO_VAT';

		$filter = array('=RATE' => array_keys($vatRates));
		if (isset($vatRates[$vatExportSettings['BASE_VAT']]))
			$filter['!=RATE'] = $vatExportSettings['BASE_VAT'];
		$iterator = Catalog\VatTable::getList(array(
			'select' => array('ID', 'RATE'),
			'filter' => $filter,
			'order' => array('ID' => 'ASC')
		));
		while ($row = $iterator->fetch())
		{
			$row['ID'] = (int)$row['ID'];
			$row['RATE'] = (float)$row['RATE'];
			$index = $row['RATE'].'%';
			if (isset($vatRates[$index]))
				$vatList[$row['ID']] = $vatRates[$index];
		}
		unset($index, $row, $iterator);
	}
}


$itemOptions = array(
	'PROTOCOL' => $usedProtocol,
	'SITE_NAME' => $ar_iblock['SERVER_NAME'],
	'MAX_DESCRIPTION_LENGTH' => 3000
);
if($isTempusEx === true){
	$itemOptions["PROFILE_1"] = true;
}
$itemFileName = '';
if (strlen($SETUP_FILE_NAME) <= 0)
{
	$arRunErrors[] = GetMessage("CATI_NO_SAVE_FILE");
}
elseif (preg_match(BX_CATALOG_FILENAME_REG,$SETUP_FILE_NAME))
{
	$arRunErrors[] = GetMessage("CES_ERROR_BAD_EXPORT_FILENAME");
}
else
{
	$SETUP_FILE_NAME = Rel2Abs("/", $SETUP_FILE_NAME);
}
if (empty($arRunErrors))
{
/*	if ($GLOBALS["APPLICATION"]->GetFileAccessPermission($SETUP_FILE_NAME) < "W")
	{
		$arRunErrors[] = str_replace('#FILE#', $SETUP_FILE_NAME,GetMessage('YANDEX_ERR_FILE_ACCESS_DENIED'));
	} */
	$itemFileName = $SETUP_FILE_NAME.'_items';
}

$itemsFile = null;

$BASE_CURRENCY = Currency\CurrencyManager::getBaseCurrency();

if ($firstStep)
{
	if (empty($arRunErrors))
	{
		CheckDirPath($_SERVER["DOCUMENT_ROOT"].$SETUP_FILE_NAME);

		if (!$fp = @fopen($_SERVER["DOCUMENT_ROOT"].$SETUP_FILE_NAME, "wb"))
		{
			$arRunErrors[] = str_replace('#FILE#', $_SERVER["DOCUMENT_ROOT"].$SETUP_FILE_NAME, GetMessage('YANDEX_ERR_FILE_OPEN_WRITING'));
		}
		else
		{
			if (!@fwrite($fp, '<? $disableReferers = '.($disableReferers ? 'true' : 'false').';'."\n"))
			{
				$arRunErrors[] = str_replace('#FILE#', $_SERVER["DOCUMENT_ROOT"].$SETUP_FILE_NAME, GetMessage('YANDEX_ERR_SETUP_FILE_WRITE'));
				@fclose($fp);
			}
			else
			{
				if (!$disableReferers)
				{
					fwrite($fp, 'if (!isset($_GET["referer1"]) || strlen($_GET["referer1"])<=0) $_GET["referer1"] = "yandext";'."\n");
					fwrite($fp, '$strReferer1 = htmlspecialchars($_GET["referer1"]);'."\n");
					fwrite($fp, 'if (!isset($_GET["referer2"]) || strlen($_GET["referer2"]) <= 0) $_GET["referer2"] = "";'."\n");
					fwrite($fp, '$strReferer2 = htmlspecialchars($_GET["referer2"]);'."\n");
				}
			}
		}
	}

	if (empty($arRunErrors))
	{
		/** @noinspection PhpUndefinedVariableInspection */
		fwrite($fp, 'header("Content-Type: text/xml; charset=windows-1251");'."\n");
		fwrite($fp, 'echo "<"."?xml version=\"1.0\" encoding=\"windows-1251\"?".">"?>');
		fwrite($fp, "\n".'<!DOCTYPE yml_catalog SYSTEM "shops.dtd">'."\n");
		fwrite($fp, '<yml_catalog date="'.date("Y-m-d H:i").'">'."\n");
		fwrite($fp, '<shop>'."\n");

//		fwrite($fp, '<name>'.$APPLICATION->ConvertCharset(htmlspecialcharsbx(COption::GetOptionString('main', 'site_name', '')), LANG_CHARSET, 'windows-1251')."</name>\n");

//		fwrite($fp, '<company>'.$APPLICATION->ConvertCharset(htmlspecialcharsbx(COption::GetOptionString('main', 'site_name', '')), LANG_CHARSET, 'windows-1251')."</company>\n");
		fwrite($fp, '<name>'.$APPLICATION->ConvertCharset(htmlspecialcharsbx($site['SITE_NAME']), LANG_CHARSET, 'windows-1251')."</name>\n");

		fwrite($fp, '<company>'.$APPLICATION->ConvertCharset(htmlspecialcharsbx($site['COMPANY_NAME']), LANG_CHARSET, 'windows-1251')."</company>\n");

		fwrite($fp, '<url>'.$usedProtocol.htmlspecialcharsbx($ar_iblock['SERVER_NAME'])."</url>\n");
//		fwrite($fp, '<platform>1C-Bitrix</platform>'."\n");



		$strTmp = '<currencies>'."\n";
//prent($strTmp);die;
		$RUR = 'RUB';
		$currencyIterator = Currency\CurrencyTable::getList(array(
			'select' => array('CURRENCY'),
			'filter' => array('=CURRENCY' => 'RUR')
		));
		if ($currency = $currencyIterator->fetch())
			$RUR = 'RUR';
		unset($currency, $currencyIterator);

		$arCurrencyAllowed = array($RUR, 'USD', 'EUR', 'UAH', 'BYR', 'BYN', 'KZT');

		if (is_array($XML_DATA['CURRENCY']))
		{
			foreach ($XML_DATA['CURRENCY'] as $CURRENCY => &$arCurData)
			{

				if (in_array($CURRENCY, $arCurrencyAllowed))
				{
					if($SITE_ID == "s2") $arCurData['rate'] = 1;
					$XML_DATA['ACTIVE_CURRENCY'] = $CURRENCY;
					$strTmp .= '<currency id="'.$CURRENCY.'"'
						.' rate="'.($arCurData['rate'] == 'SITE' ? CCurrencyRates::ConvertCurrency(1, $CURRENCY, $RUR) : $arCurData['rate']).'"'
						.($arCurData['plus'] > 0 ? ' plus="'.(int)$arCurData['plus'].'"' : '')
						." />\n";
				}
			}
			unset($arCurData);

			//prent($strTmp);die;
			unset($CURRENCY, $arCurData);
		}
		else
		{
			$currencyIterator = Currency\CurrencyTable::getList(array(
				'select' => array('CURRENCY', 'SORT'),
				'filter' => array('@CURRENCY' => $arCurrencyAllowed),
				'order' => array('SORT' => 'ASC', 'CURRENCY' => 'ASC')
			));
			while ($currency = $currencyIterator->fetch())
				$strTmp .= '<currency id="'.$currency['CURRENCY'].'" rate="'.(CCurrencyRates::ConvertCurrency(1, $currency['CURRENCY'], $RUR)).'" />'."\n";
			unset($currency, $currencyIterator);
		}

		//if($SITE_ID == "s2")
		//	$strTmp .= '<currency id="RUR" rate="1"/>';

		$strTmp .= "</currencies>\n";
//prent($strTmp);die;
		fwrite($fp, $strTmp);
		unset($strTmp);

		//*****************************************//


		//*****************************************//
		$intMaxSectionID = 0;

		$strTmpCat = '';
		$strTmpOff = '';

		$arSectionIDs = array();
		$arAvailGroups = array();
		if (!$bAllSections)
		{
			for ($i = 0, $intSectionsCount = count($arSections); $i < $intSectionsCount; $i++)
			{
				$sectionIterator = CIBlockSection::GetNavChain($IBLOCK_ID, $arSections[$i], array('ID', 'IBLOCK_SECTION_ID', 'NAME', 'LEFT_MARGIN', 'RIGHT_MARGIN'));
				$curLEFT_MARGIN = 0;
				$curRIGHT_MARGIN = 0;
				while ($section = $sectionIterator->Fetch())
				{
					$section['ID'] = (int)$section['ID'];
					$section['IBLOCK_SECTION_ID'] = (int)$section['IBLOCK_SECTION_ID'];
					if ($arSections[$i] == $section['ID'])
					{
						$curLEFT_MARGIN = (int)$section['LEFT_MARGIN'];
						$curRIGHT_MARGIN = (int)$section['RIGHT_MARGIN'];
						$arSectionIDs[$section['ID']] = $section['ID'];
					}
					$arAvailGroups[$section['ID']] = array(
						'ID' => $section['ID'],
						'IBLOCK_SECTION_ID' => $section['IBLOCK_SECTION_ID'],
						'NAME' => $section['NAME']
					);
					if ($intMaxSectionID < $section['ID'])
						$intMaxSectionID = $section['ID'];
				}
				unset($section, $sectionIterator);

				$filter = array("IBLOCK_ID" => $IBLOCK_ID, ">LEFT_MARGIN" => $curLEFT_MARGIN, "<RIGHT_MARGIN" => $curRIGHT_MARGIN, "ACTIVE" => "Y", "IBLOCK_ACTIVE" => "Y", "GLOBAL_ACTIVE" => "Y");
				$sectionIterator = CIBlockSection::GetList(array("LEFT_MARGIN" => "ASC"), $filter, false, array('ID', 'IBLOCK_SECTION_ID', 'NAME'));
				while ($section = $sectionIterator->Fetch())
				{
					$section['ID'] = (int)$section['ID'];
					$section['IBLOCK_SECTION_ID'] = (int)$section['IBLOCK_SECTION_ID'];
					$arAvailGroups[$section['ID']] = $section;
					if ($intMaxSectionID < $section['ID'])
						$intMaxSectionID = $section['ID'];
				}
				unset($section, $sectionIterator);
			}
		}
		else
		{
			$filter = array("IBLOCK_ID" => $IBLOCK_ID, "ACTIVE" => "Y", "IBLOCK_ACTIVE" => "Y", "GLOBAL_ACTIVE" => "Y");
			$sectionIterator = CIBlockSection::GetList(array("LEFT_MARGIN" => "ASC"), $filter, false, array('ID', 'IBLOCK_SECTION_ID', 'NAME'));
			while ($section = $sectionIterator->Fetch())
			{
				$section['ID'] = (int)$section['ID'];
				$section['IBLOCK_SECTION_ID'] = (int)$section['IBLOCK_SECTION_ID'];
				$arAvailGroups[$section['ID']] = $section;
				$arSectionIDs[$section['ID']] = $section['ID'];
				if ($intMaxSectionID < $section['ID'])
					$intMaxSectionID = $section['ID'];
			}
			unset($section, $sectionIterator);
		}

		foreach ($arAvailGroups as $value)
			$strTmpCat .= '<category id="'.$value['ID'].'"'.($value['IBLOCK_SECTION_ID'] > 0 ? ' parentId="'.$value['IBLOCK_SECTION_ID'].'"' : '').'>'.yandex_text2xml($value['NAME'], true).'</category>'."\n";
		unset($value);

		$intMaxSectionID += 100000000;
	}

	fwrite($fp, "<categories>\n");
	fwrite($fp, $strTmpCat);

	fclose($fp);
	unset($strTmpCat);
//die;
	$boolNeedRootSection = false;

	$itemsFile = @fopen($_SERVER["DOCUMENT_ROOT"].$itemFileName, 'wb');

	if (!$itemsFile)
	{
		$arRunErrors[] = str_replace('#FILE#', $_SERVER['DOCUMENT_ROOT'].$itemFileName, GetMessage('YANDEX_ERR_FILE_OPEN_WRITING'));
	}
}
else
{
	$itemsFile = @fopen($_SERVER["DOCUMENT_ROOT"].$itemFileName, 'ab');
	if (!$itemsFile)
	{
		$arRunErrors[] = str_replace('#FILE#', $_SERVER['DOCUMENT_ROOT'].$itemFileName, GetMessage('YANDEX_ERR_FILE_OPEN_WRITING'));
	}
}

if (empty($arRunErrors))
{
	if($XML_DATA["ACTIVE_CURRENCY"])$BASE_CURRENCY = $XML_DATA["ACTIVE_CURRENCY"];
	chmod($_SERVER["DOCUMENT_ROOT"].$itemFileName, 0777);
	//*****************************************//
	\CCatalogProduct::setPriceVatIncludeMode(true);
	\CCatalogProduct::setUsedCurrency($BASE_CURRENCY);
	\CCatalogProduct::setUseDiscount(true);

	if ($selectedPriceType > 0)
	{
		$priceTypeList = array($selectedPriceType);
	}
	else
	{
		$priceTypeList = array();
		$priceIterator = Catalog\GroupAccessTable::getList(array(
			'select' => array('CATALOG_GROUP_ID'),
			'filter' => array('@GROUP_ID' => 2),
			'order' => array('CATALOG_GROUP_ID' => 'ASC')
		));
		while ($priceType = $priceIterator->fetch())
		{
			$priceTypeId = (int)$priceType['CATALOG_GROUP_ID'];
			$priceTypeList[$priceTypeId] = $priceTypeId;
			unset($priceTypeId);
		}
		unset($priceType, $priceIterator);
	}

	$needDiscountCache = \CIBlockPriceTools::SetCatalogDiscountCache($priceTypeList, array(2), $ar_iblock['LID']);

	$itemFields = array(
		'ID', 'LID', 'IBLOCK_ID', 'IBLOCK_SECTION_ID', 'NAME',
		'PREVIEW_PICTURE', 'PREVIEW_TEXT', 'PREVIEW_TEXT_TYPE', 'DETAIL_PICTURE', 'DETAIL_PAGE_URL',
		'CATALOG_AVAILABLE', 'CATALOG_TYPE'
	);
	$offerFields = array(
		'ID', 'LID', 'IBLOCK_ID', 'IBLOCK_SECTION_ID', 'NAME',
		'PREVIEW_PICTURE', 'PREVIEW_TEXT', 'PREVIEW_TEXT_TYPE', 'DETAIL_PICTURE', 'DETAIL_PAGE_URL'
	);

	$allowedTypes = array();
	switch ($arCatalog['CATALOG_TYPE'])
	{
		case CCatalogSku::TYPE_CATALOG:
			$allowedTypes = array(
				Catalog\ProductTable::TYPE_PRODUCT => true,
				Catalog\ProductTable::TYPE_SET => true
			);
			break;
		case CCatalogSku::TYPE_OFFERS:
			$allowedTypes = array(
				Catalog\ProductTable::TYPE_OFFER => true
			);
			break;
		case CCatalogSku::TYPE_FULL:
			$allowedTypes = array(
				Catalog\ProductTable::TYPE_PRODUCT => true,
				Catalog\ProductTable::TYPE_SET => true,
				Catalog\ProductTable::TYPE_SKU => true
			);
			break;
		case CCatalogSku::TYPE_PRODUCT:
			$allowedTypes = array(
				Catalog\ProductTable::TYPE_SKU => true
			);
			break;
	}

	$filter = array('IBLOCK_ID' => $IBLOCK_ID);
	if (!$bAllSections && !empty($arSectionIDs))
	{
		$filter['INCLUDE_SUBSECTIONS'] = 'Y';
		$filter['SECTION_ID'] = $arSectionIDs;
	}
	//wdhs комменты
	$filter['ACTIVE'] = 'Y';
	$filter['ACTIVE_DATE'] = 'Y';
	if ($filterAvailable)
		$filter['CATALOG_AVAILABLE'] = 'Y';


	//prent($SITE_ID);
	if($isTempusEx === false){
		if($SITE_ID == "s1"){
			$filter[">CATALOG_QUANTITY"] = '0';
			$filter["PROPERTY_AVAILABILITY_RU"] = 512;
		}elseif($SITE_ID == "s2"){
			$filter[">CATALOG_QUANTITY"] = '0';
			$filter["PROPERTY_AVAILABILITY_BY"] = array(492,493);
		}
	}else{
		//unset($filter["CATALOG_AVAILABLE"]);
		//prent($filter);die;
		//$filter[">CATALOG_QUANTITY"] = '0';
		//$filter["PROPERTY_AVAILABILITY_BY"] = array(492,493);
	}
  // file_put_contents("/var/www/bitrix/data/www/tempusshop.ru/bitrix/php_interface/include/catalog_export/checkBY.txt", print_r('###START###', true).PHP_EOL, FILE_APPEND);
	// file_put_contents("/var/www/bitrix/data/www/tempusshop.ru/bitrix/php_interface/include/catalog_export/checkBY.txt", print_r($_REQUEST["PROFILE_ID"], true).PHP_EOL, FILE_APPEND);
	if($_REQUEST["PROFILE_ID"] == 39 || $SETUP_FILE_NAME == "/bitrix/catalog_export/yandex_421494.php"){
		$filter["PROPERTY_AVAILABILITY_RU"] = 512;
	}

	//prent($filter);die;
	//PROPERTY_AVAILABILITY_BY
	//PROPERTY_AVAILABILITY_RU
	$offersFilter = array('ACTIVE' => 'Y', 'ACTIVE_DATE' => 'Y');
	if ($filterAvailable)
		$offersFilter['CATALOG_AVAILABLE'] = 'Y';
	if (isset($allowedTypes[Catalog\ProductTable::TYPE_SKU]))
	{
		if ($arSKUExport['SKU_EXPORT_COND'] == YANDEX_SKU_EXPORT_PROP)
		{
			$strExportKey = '';
			$mxValues = false;
			if ($arSKUExport['SKU_PROP_COND']['COND'] == 'NONZERO' || $arSKUExport['SKU_PROP_COND']['COND'] == 'NONEQUAL')
				$strExportKey = '!';
			$strExportKey .= 'PROPERTY_'.$arSKUExport['SKU_PROP_COND']['PROP_ID'];
			if ($arSKUExport['SKU_PROP_COND']['COND'] == 'EQUAL' || $arSKUExport['SKU_PROP_COND']['COND'] == 'NONEQUAL')
				$mxValues = $arSKUExport['SKU_PROP_COND']['VALUES'];
			$offersFilter[$strExportKey] = $mxValues;
		}
	}

	do
	{
		//if($isTempusEx === true){
		//	$CUR_ELEMENT_ID = 89051;
		//}

		if (isset($CUR_ELEMENT_ID) && $CUR_ELEMENT_ID > 0)
			$filter['>ID'] = $CUR_ELEMENT_ID;

		$existItems = false;

		$itemIdsList = array();
		$items = array();

		$skuIdsList = array();
		$simpleIdsList = array();

		$iterator = CIBlockElement::GetList(
			array('ID' => 'ASC'),
			$filter,
			false,
			$navParams,
			$itemFields
		);

		// file_put_contents("/var/www/bitrix/data/www/tempusshop.ru/bitrix/php_interface/include/catalog_export/checkBY.txt", print_r('->ФИЛЬТР', true).PHP_EOL, FILE_APPEND);
		// file_put_contents("/var/www/bitrix/data/www/tempusshop.ru/bitrix/php_interface/include/catalog_export/checkBY.txt", print_r($filter, true).PHP_EOL, FILE_APPEND);
		// file_put_contents("/var/www/bitrix/data/www/tempusshop.ru/bitrix/php_interface/include/catalog_export/checkBY.txt", print_r('->ИТЕМ ФИЛДС', true).PHP_EOL, FILE_APPEND);
		// file_put_contents("/var/www/bitrix/data/www/tempusshop.ru/bitrix/php_interface/include/catalog_export/checkBY.txt", print_r($itemFields, true).PHP_EOL, FILE_APPEND);
		while ($row = $iterator->Fetch())
		{
			//file_put_contents("/var/www/bitrix/data/www/tempusshop.ru/bitrix/php_interface/include/catalog_export/checkBY.txt", print_r($row, true).PHP_EOL, FILE_APPEND);
			$finalExport = false; // items exist
			$existItems = true;

			$id = (int)$row['ID'];
			$CUR_ELEMENT_ID = $id;

			$row['CATALOG_TYPE'] = (int)$row['CATALOG_TYPE'];
			$elementType = $row['CATALOG_TYPE'];
			if (!isset($allowedTypes[$elementType]))
				continue;

			$row['SECTIONS'] = array();
			if ($needProperties || $needDiscountCache)
				$row['PROPERTIES'] = array();
			$row['PRICES'] = array();

			$items[$id] = $row;
			$itemIdsList[$id] = $id;

			if ($elementType == Catalog\ProductTable::TYPE_SKU)
				$skuIdsList[$id] = $id;
			else
				$simpleIdsList[$id] = $id;
		}
		unset($row, $iterator);

		if (!empty($items))
		{
			yandexPrepareItems($items, $itemOptions);

			foreach (array_chunk($itemIdsList, 500) as $pageIds)
			{
				$iterator = Iblock\SectionElementTable::getList(array(
					'select' => array('IBLOCK_ELEMENT_ID', 'IBLOCK_SECTION_ID'),
					'filter' => array('@IBLOCK_ELEMENT_ID' => $pageIds, '==ADDITIONAL_PROPERTY_ID' => null),
					'order' => array('IBLOCK_ELEMENT_ID' => 'ASC')
				));
				while ($row = $iterator->fetch())
				{
					$id = (int)$row['IBLOCK_ELEMENT_ID'];
					$sectionId = (int)$row['IBLOCK_SECTION_ID'];
					$items[$id]['SECTIONS'][$sectionId] = $sectionId;
					unset($sectionId, $id);
				}
				unset($row, $iterator);
			}
			unset($pageIds);

			if ($needProperties || $needDiscountCache)
			{
				if (!empty($propertyIdList))
				{
					\CIBlockElement::GetPropertyValuesArray(
						$items,
						$IBLOCK_ID,
						array(
							'ID' => $itemIdsList,
							'IBLOCK_ID' => $IBLOCK_ID
						),
						array('ID' => $propertyIdList),
						array('USE_PROPERTY_ID' => 'Y', 'PROPERTY_FIELDS' => $propertyFields)
					);
				}
/*
	неправильная скидка!!!!!!
				if ($needDiscountCache)
				{
					foreach ($itemIdsList as $id)
						\CCatalogDiscount::SetProductPropertiesCache($id, $items[$id]['PROPERTIES']);
					unset($id);
				}
*/
				if (!$needProperties)
				{
					foreach ($itemIdsList as $id)
						$items[$id]['PROPERTIES'] = array();
					unset($id);
				}
				else
				{
					foreach ($itemIdsList as $id)
					{
						if (empty($items[$id]['PROPERTIES']))
							continue;
						foreach (array_keys($items[$id]['PROPERTIES']) as $index)
						{
							$propertyId = $items[$id]['PROPERTIES'][$index]['ID'];
							if (!isset($yandexNeedPropertyIds[$propertyId]))
								unset($items[$id]['PROPERTIES'][$index]);
						}
						unset($propertyId, $index);
					}
					unset($id);
				}
			}

			if ($needDiscountCache)
			{
				\CCatalogDiscount::SetProductSectionsCache($itemIdsList);
				\CCatalogDiscount::SetDiscountProductCache($itemIdsList, array('IBLOCK_ID' => $IBLOCK_ID, 'GET_BY_ID' => 'Y'));
			}

			if (!empty($skuIdsList))
			{
				$offerPropertyFilter = array();
				if ($needProperties || $needDiscountCache)
				{
					if (!empty($propertyIdList))
						$offerPropertyFilter = array('ID' => $propertyIdList);
				}

				$offers = \CCatalogSku::getOffersList(
					$skuIdsList,
					$IBLOCK_ID,
					$offersFilter,
					$offerFields,
					$offerPropertyFilter,
					array('USE_PROPERTY_ID' => 'Y', 'PROPERTY_FIELDS' => $propertyFields)
				);
				unset($offerPropertyFilter);

				if (!empty($offers))
				{
					$offerLinks = array();
					$offerIdsList = array();
					foreach (array_keys($offers) as $productId)
					{
						unset($skuIdsList[$productId]);
						$items[$productId]['OFFERS'] = array();
						foreach (array_keys($offers[$productId]) as $offerId)
						{
							$productOffer = $offers[$productId][$offerId];

							$productOffer['PRICES'] = array();
							if ($needDiscountCache)
								\CCatalogDiscount::SetProductPropertiesCache($offerId, $productOffer['PROPERTIES']);
							if (!$needProperties)
							{
								$productOffer['PROPERTIES'] = array();
							}
							else
							{
								if (!empty($productOffer['PROPERTIES']))
								{
									foreach (array_keys($productOffer['PROPERTIES']) as $index)
									{
										$propertyId = $productOffer['PROPERTIES'][$index]['ID'];
										if (!isset($yandexNeedPropertyIds[$propertyId]))
											unset($productOffer['PROPERTIES'][$index]);
									}
									unset($propertyId, $index);
								}
							}
							$items[$productId]['OFFERS'][$offerId] = $productOffer;
							unset($productOffer);

							$offerLinks[$offerId] = &$items[$productId]['OFFERS'][$offerId];
							$offerIdsList[$offerId] = $offerId;
						}
						unset($offerId);
					}
					if (!empty($offerIdsList))
					{
						yandexPrepareItems($offerLinks, $itemOptions);

						foreach (array_chunk($offerIdsList, 500) as $pageIds)
						{
							if ($needDiscountCache)
							{
								\CCatalogDiscount::SetProductSectionsCache($pageIds);
								\CCatalogDiscount::SetDiscountProductCache(
									$pageIds,
									array('IBLOCK_ID' => $arCatalog['IBLOCK_ID'], 'GET_BY_ID' => 'Y')
								);
							}

							if (!$filterAvailable)
							{
								$iterator = Catalog\ProductTable::getList(array(
									'select' => ($vatExport ? array('ID', 'AVAILABLE', 'VAT_ID', 'VAT_INCLUDED') : array('ID', 'AVAILABLE')),
									'filter' => array('@ID' => $pageIds)
								));
								while ($row = $iterator->fetch())
								{
									$id = (int)$row['ID'];
									$offerLinks[$id]['CATALOG_AVAILABLE'] = $row['AVAILABLE'];
									if ($vatExport)
									{
										$row['VAT_ID'] = (int)$row['VAT_ID'];
										$offerLinks[$id]['CATALOG_VAT_ID'] = ($row['VAT_ID'] > 0 ? $row['VAT_ID'] : $offersCatalog['VAT_ID']);
										$offerLinks[$id]['CATALOG_VAT_INCLUDED'] = $row['VAT_INCLUDED'];
									}
								}
								unset($id, $row, $iterator);
							}

							$priceFilter = array(
								'@PRODUCT_ID' => $pageIds,
								'+<=QUANTITY_FROM' => 1,
								'+>=QUANTITY_TO' => 1,
							);
							if ($selectedPriceType > 0)
								$priceFilter['CATALOG_GROUP_ID'] = $selectedPriceType;
							else
								$priceFilter['@CATALOG_GROUP_ID'] = $priceTypeList;

							$priceIterator = \CPrice::GetListEx(
								array(),
								$priceFilter,
								false,
								false,
								array('ID', 'PRODUCT_ID', 'CATALOG_GROUP_ID', 'PRICE', 'CURRENCY')
							);
							while ($price = $priceIterator->Fetch())
							{
								$id = (int)$price['PRODUCT_ID'];
								$priceTypeId = (int)$price['CATALOG_GROUP_ID'];
								$offerLinks[$id]['PRICES'][$priceTypeId] = $price;
								unset($priceTypeId, $id);
							}
							unset($price, $priceIterator);
						}
						unset($pageIds);
					}

					unset($offerIdsList, $offerLinks);
				}
				unset($offers);

				if (!empty($skuIdsList))
				{
					foreach ($skuIdsList as $id)
					{
						unset($items[$id]);
						unset($itemIdsList[$id]);
					}
					unset($id);
				}
			}

			if (!empty($simpleIdsList))
			{
				foreach (array_chunk($simpleIdsList, 500) as $pageIds)
				{
					$priceFilter = array(
						'@PRODUCT_ID' => $pageIds,
						'+<=QUANTITY_FROM' => 1,
						'+>=QUANTITY_TO' => 1,
					);
					if ($selectedPriceType > 0)
						$priceFilter['CATALOG_GROUP_ID'] = $selectedPriceType;
					else
						$priceFilter['@CATALOG_GROUP_ID'] = $priceTypeList;

					$priceIterator = \CPrice::GetListEx(
						array(),
						$priceFilter,
						false,
						false,
						array('ID', 'PRODUCT_ID', 'CATALOG_GROUP_ID', 'PRICE', 'CURRENCY')
					);
					while ($price = $priceIterator->Fetch())
					{
						$id = (int)$price['PRODUCT_ID'];
						$priceTypeId = (int)$price['CATALOG_GROUP_ID'];
						$items[$id]['PRICES'][$priceTypeId] = $price;
						unset($priceTypeId, $id);
					}
					unset($price, $priceIterator);
				}
				unset($pageIds);
			}
		}
//prent($items);die;
		$itemsContent = '';
		if (!empty($items))
		{
			foreach ($itemIdsList as $id)
			{
				$CUR_ELEMENT_ID = $id;

				$row = $items[$id];
				/*
				if($isTempusEx === true){
					$row["ID"] = $row["EXTERNAL_ID"];
					if(isset($row["PRICES"][2])){
						$arS2 = AHCatalog::OnGetOptimalPrice($row["PRICES"][2]["PRODUCT_ID"], 1, array(), "N", array(), "s2");
						$b_price = CCurrencyRates::ConvertCurrency($arS2["PRICE"]["PRICE"], $arS2["PRICE"]["CURRENCY"], "BYN");
						$b_price = round($b_price, 0);
						$b_price_dis = CCurrencyRates::ConvertCurrency($arS2["RESULT_PRICE"]["DISCOUNT_PRICE"], $arS2["RESULT_PRICE"]["CURRENCY"], "BYN");
						$b_price_dis = round($b_price_dis, 0);

					//	if($b_price_dis) $row["PRICES"][2]["PRICE"] = $b_price_dis;
					}
					//prent($row);die;
				}*/

				if (!empty($row['SECTIONS']))
				{
					foreach ($row['SECTIONS'] as $sectionId)
					{
						if (!isset($arAvailGroups[$sectionId]))
							continue;
						$row['CATEGORY_ID'] = $sectionId;
					}
					unset($sectionId);
				}
				else
				{
					$boolNeedRootSection = true;
					$row['CATEGORY_ID'] = $intMaxSectionID;
				}
				if (!isset($row['CATEGORY_ID']))
					continue;

				if ($row['CATALOG_TYPE'] == Catalog\ProductTable::TYPE_SKU && !empty($row['OFFERS']))
				{
					$minOfferId = null;
					$minOfferPrice = null;

					foreach (array_keys($row['OFFERS']) as $offerId)
					{
						if (empty($row['OFFERS'][$offerId]['PRICES']))
						{
							unset($row['OFFERS'][$offerId]);
							continue;
						}

						$fullPrice = 0;
						$minPrice = 0;
						$minPriceCurrency = '';

						$calculatePrice = CCatalogProduct::GetOptimalPrice(
							$row['OFFERS'][$offerId]['ID'],
							1,
							array(2),
							'N',
							$row['OFFERS'][$offerId]['PRICES'],
							$ar_iblock['LID'],
							array()
						);

						if (!empty($calculatePrice))
						{
							$minPrice = $calculatePrice['RESULT_PRICE']['DISCOUNT_PRICE'];
							$fullPrice = $calculatePrice['RESULT_PRICE']['BASE_PRICE'];
							$minPriceCurrency = $calculatePrice['RESULT_PRICE']['CURRENCY'];
						}
						unset($calculatePrice);
						if ($minPrice <= 0)
						{
							unset($row['OFFERS'][$offerId]);
							continue;
						}
						$row['OFFERS'][$offerId]['RESULT_PRICE'] = array(
							'MIN_PRICE' => $minPrice,
							'FULL_PRICE' => $fullPrice,
							'CURRENCY' => $minPriceCurrency
						);
						if ($minOfferPrice === null || $minOfferPrice > $minPrice)
						{
							$minOfferId = $offerId;
							$minOfferPrice = $minPrice;
						}
					}
					unset($offerId);

					if ($arSKUExport['SKU_EXPORT_COND'] == YANDEX_SKU_EXPORT_MIN_PRICE)
					{
						if ($minOfferId === null)
							$row['OFFERS'] = array();
						else
							$row['OFFERS'] = array($minOfferId => $row['OFFERS'][$minOfferId]);
					}
					if (empty($row['OFFERS']))
						continue;

					foreach ($row['OFFERS'] as $offer)
					{

						$available = ' available="'.($offer['CATALOG_AVAILABLE'] == 'Y' ? 'true' : 'false').'"';
						$itemsContent .= '<offer id="'.$offer['ID'].'"'.$productFormat.$available.">\n";
						unset($available);

						$referer = '';
						if (!$disableReferers)
							$referer = (strpos($offer['DETAIL_PAGE_URL'], '?') === false ? '?' : '&amp;').'r1=<?=$strReferer1; ?>&amp;r2=<?=$strReferer2; ?>';

						$referer .= (strpos($referer, '?') === false ? '?' : '&amp;').'utm_source=market&amp;utm_term=' . $offer['ID'];

						$itemsContent .= "<url>".$usedProtocol.$ar_iblock['SERVER_NAME'].htmlspecialcharsbx($offer['DETAIL_PAGE_URL']).$referer."</url>\n";
						unset($referer);

						$minPrice = $offer['RESULT_PRICE']['MIN_PRICE'];
						$fullPrice = $offer['RESULT_PRICE']['FULL_PRICE'];
						$itemsContent .= "<price>".$minPrice."</price>\n";
						if ($minPrice < $fullPrice)
							$itemsContent .= "<oldprice>".$fullPrice."</oldprice>\n";
						$itemsContent .= "<currencyId>".$offer['RESULT_PRICE']['CURRENCY']."</currencyId>\n";
						if ($vatExport && isset($vatList[$offer['CATALOG_VAT_ID']]))
							$itemsContent .= "<vat>".$vatList[$offer['CATALOG_VAT_ID']]."</vat>\n";

						$itemsContent .= "<categoryId>".$row['CATEGORY_ID']."</categoryId>\n";

						$picture = (!empty($offer['PICTURE']) ? $offer['PICTURE'] : $row['PICTURE']);
						if (!empty($picture))
							$itemsContent .= "<picture>".$picture."</picture>\n";
						unset($picture);

						$y = 0;
						foreach ($arYandexFields as $key)
						{
							switch ($key)
							{
								case 'name':
									if ($yandexFormat == 'vendor.model' || $yandexFormat == 'artist.title')
										continue;

									$itemsContent .= "<name>".yandex_text2xml($offer['NAME'], true)."</name>\n";
									break;
								case 'description':
									$itemsContent .= "<description>".
										($offer['DESCRIPTION'] !== '' ? $offer['DESCRIPTION'] : $row['DESCRIPTION']).
										"</description>\n";
									break;
								case 'param':
									if ($parametricFieldsExist)
									{
										foreach ($parametricFields as $paramKey => $prop_id)
										{
											$value = yandex_get_value(
												$offer,
												'PARAM_'.$paramKey,
												$prop_id,
												$arProperties,
												$arUserTypeFormat,
												$usedProtocol
											);
											if ($value == '')
											{
												$value = yandex_get_value(
													$row,
													'PARAM_'.$paramKey,
													$prop_id,
													$arProperties,
													$arUserTypeFormat,
													$usedProtocol
												);
											}
											if ($value != '')
												$itemsContent .= $value."\n";
											unset($value);
										}
										unset($paramKey, $prop_id);
									}
									break;
								case 'model':
								case 'title':
									if (!$fieldsExist || !isset($fields[$key]))
									{
										if (
											$key == 'model' && $yandexFormat == 'vendor.model'
											||
											$key == 'title' && $yandexFormat == 'artist.title'
										)
											$itemsContent .= "<".$key.">".yandex_text2xml($offer['NAME'], true)."</".$key.">\n";
									}
									else
									{
										$value = yandex_get_value(
											$offer,
											$key,
											$fields[$key],
											$arProperties,
											$arUserTypeFormat,
											$usedProtocol
										);
										if ($value == '')
										{
											$value = yandex_get_value(
												$row,
												$key,
												$fields[$key],
												$arProperties,
												$arUserTypeFormat,
												$usedProtocol
											);
										}
										if ($value != '')
											$itemsContent .= $value."\n";
										unset($value);
									}
									break;
								case 'year':
								default:
									if ($key == 'year')
									{
										$y++;
										if ($yandexFormat == 'artist.title')
										{
											if ($y == 1)
												continue;
										}
										else
										{
											if ($y > 1)
												continue;
										}
									}
									if ($fieldsExist && isset($fields[$key]))
									{
										$value = yandex_get_value(
											$offer,
											$key,
											$fields[$key],
											$arProperties,
											$arUserTypeFormat,
											$usedProtocol
										);
										if ($value == '')
										{
											$value = yandex_get_value(
												$row,
												$key,
												$fields[$key],
												$arProperties,
												$arUserTypeFormat,
												$usedProtocol
											);
										}
										if ($value != '')
											$itemsContent .= $value."\n";
										unset($value);
									}
							}
						}

						$itemsContent .= '</offer>'."\n";
					}

					unset($offer);
				}
				elseif (isset($simpleIdsList[$id]) && (!empty($row['PRICES']) || $isTempusEx === true))
				{

					$row['CATALOG_VAT_ID'] = (int)$row['CATALOG_VAT_ID'];
					if ($row['CATALOG_VAT_ID'] == 0)
						$row['CATALOG_VAT_ID'] = $arCatalog['VAT_ID'];

					$fullPrice = 0;
					$minPrice = 0;
					$minPriceCurrency = '';

					$calculatePrice = CCatalogProduct::GetOptimalPrice(
						$row['ID'],
						1,
						array(2),
						'N',
						$row['PRICES'],
						$ar_iblock['LID'],
						array()
					);
//prent($calculatePrice);die;
					if (!empty($calculatePrice))
					{
						$minPrice = $calculatePrice['RESULT_PRICE']['DISCOUNT_PRICE'];
						$fullPrice = $calculatePrice['RESULT_PRICE']['BASE_PRICE'];
						$minPriceCurrency = $calculatePrice['RESULT_PRICE']['CURRENCY'];
					}
					unset($calculatePrice);
//	if($isTempusEx === true && $id == 89052){
//		prent($minPrice);die;
//	}
					if ($minPrice <= 0 && $isTempusEx === false)
						continue;

					$available = ' available="'.($row['CATALOG_AVAILABLE'] == 'Y' ? 'true' : 'false').'"';

					if($isTempusEx === true){

						$itemsContent .= '<offer id="'.$row['EXTERNAL_ID'].'" productId="'.$row['EXTERNAL_ID'].'" quantity="'.$row['CATALOG_QUANTITY'].'">'."\n";
					}else{
						$itemsContent .= '<offer id="'.$row['ID'].'"'.$productFormat.$available.">\n";
					}
					//prent($itemsContent);die;
					unset($available);

					$referer = '';
					if (!$disableReferers)
						$referer = (strpos($row['DETAIL_PAGE_URL'], '?') === false ? '?' : '&amp;').'r1=<?=$strReferer1; ?>&amp;r2=<?=$strReferer2; ?>';

					if($isTempusEx === true){
						$referer .= (strpos($referer, '?') === false ? '?' : '&amp;').'utm_source=market&amp;utm_term=' . $row['EXTERNAL_ID'];
					}else{
						$referer .= (strpos($referer, '?') === false ? '?' : '&amp;').'utm_source=market&amp;utm_term=' . $row['ID'];
					}


					$itemsContent .= "<url>".$usedProtocol.$ar_iblock['SERVER_NAME'].htmlspecialcharsbx($row['DETAIL_PAGE_URL']).$referer."</url>\n";
					unset($referer);

					if($showPrice === true){
						$itemsContent .= "<price>".$minPrice."</price>\n";
					}
					if ($minPrice < $fullPrice)
						$itemsContent .= "<oldprice>".$fullPrice."</oldprice>\n";
					$itemsContent .= "<currencyId>".$minPriceCurrency."</currencyId>\n";
					if ($vatExport && isset($vatList[$row['CATALOG_VAT_ID']])){
						if($isTempusEx)
							$itemsContent .= "<vat>VAT_20</vat>\n";
						else
							$itemsContent .= "<vat>".$vatList[$row['CATALOG_VAT_ID']]."</vat>\n";
					}


					if($isTempusEx === true && $row['CATEGORY_ID'] == 370 && isset($arSectionMain[$row['ID']])){
						$itemsContent .= "<categoryId>".$arSectionMain[$row['ID']]."</categoryId>\n";
					}else{
						$itemsContent .= "<categoryId>".$row['CATEGORY_ID']."</categoryId>\n";
					}



					if (!empty($row['PICTURE']))
						$itemsContent .= "<picture>".$row['PICTURE']."</picture>\n";

					$y = 0;
					foreach ($arYandexFields as $key)
					{
						switch ($key)
						{
							case 'name':
								if ($yandexFormat == 'vendor.model' || $yandexFormat == 'artist.title')
									continue;
									if(isset($arAddText[$row["IBLOCK_SECTION_ID"]]) && strlen($arAddText[$row["IBLOCK_SECTION_ID"]]) > 0)
										$name = $arAddText[$row["IBLOCK_SECTION_ID"]] . " " . $row["NAME"];
									else
										$name = $row["NAME"];
								$itemsContent .= "<name>".yandex_text2xml($name, true)."</name>\n";
								//$itemsContent .= "<name>".yandex_text2xml($row['NAME'], true)."</name>\n";
								break;
							case 'description':
								$itemsContent .= "<description>".$row['DESCRIPTION']."</description>\n";
								break;
							case 'param':
								if ($parametricFieldsExist)
								{
									foreach ($parametricFields as $paramKey => $prop_id)
									{
										$value = yandex_get_value(
											$row,
											'PARAM_'.$paramKey,
											$prop_id,
											$arProperties,
											$arUserTypeFormat,
											$usedProtocol
										);
										if ($value != '')
											$itemsContent .= $value."\n";
										unset($value);
									}
									unset($paramKey, $prop_id);
								}
								break;
							case 'model':
							case 'title':
								if (!$fieldsExist || !isset($fields[$key]))
								{
									if (
										$key == 'model' && $yandexFormat == 'vendor.model'
										||
										$key == 'title' && $yandexFormat == 'artist.title'
									)
										$itemsContent .= "<".$key.">".yandex_text2xml($row['NAME'], true)."</".$key.">\n";
								}
								else
								{
									$value = yandex_get_value(
										$row,
										$key,
										$fields[$key],
										$arProperties,
										$arUserTypeFormat,
										$usedProtocol
									);
									if ($value != '')
										$itemsContent .= $value."\n";
									unset($value);
								}
								break;
							case 'year':
							default:
								if ($key == 'year')
								{
									$y++;
									if ($yandexFormat == 'artist.title')
									{
										if ($y == 1)
											continue;
									}
									else
									{
										if ($y > 1)
											continue;
									}
								}
								if ($fieldsExist && isset($fields[$key]))
								{
									$value = yandex_get_value(
										$row,
										$key,
										$fields[$key],
										$arProperties,
										$arUserTypeFormat,
										$usedProtocol
									);
									if ($value != '')
										$itemsContent .= $value."\n";
									unset($value);
								}
						}
					}
					if($isTempusEx){

						if($arBarcode[$row["ID"]]){
							foreach($arBarcode[$row["ID"]] as $k => $barcode){
								$itemsContent .= "<barcode>".$barcode."</barcode>\n";
							}
						}

						if($row["PROPERTIES"][2804]["VALUE"]){
							$itemsContent .= "<barcode>".$row["PROPERTIES"][2804]["VALUE"]."</barcode>\n";
						}
						if($row["PROPERTIES"][2821]["VALUE"]){
							$itemsContent .= "<barcode>".$row["PROPERTIES"][2821]["VALUE"]."</barcode>\n";
						}

						$itemsContent .= "<param name=\"" . GetMessage('OP_EXPORT_1') . "\">" . GetMessage('OP_EXPORT_2') . "</param>\n";


						//prent($itemsContent);die;
					}
					$itemsContent .= "</offer>\n";
				}

				unset($row);
				/*	if($isTempusEx === true){
						prent($itemsContent);die;
					}*/
				if ($MAX_EXECUTION_TIME > 0 && (getmicrotime() - START_EXEC_TIME) >= $MAX_EXECUTION_TIME)
					break;
			}
			unset($id);

			\CCatalogDiscount::ClearDiscountCache(array(
				'PRODUCT' => true,
				'SECTIONS' => true,
				'PROPERTIES' => true
			));
		}

		if ($itemsContent !== '')
			fwrite($itemsFile, $itemsContent);
		unset($itemsContent);

		unset($simpleIdsList, $skuIdsList);
		unset($items, $itemIdsList);
	}
	while ($MAX_EXECUTION_TIME == 0 && $existItems);
}

if (empty($arRunErrors))
{
	if (is_resource($itemsFile))
		@fclose($itemsFile);
	unset($itemsFile);
}

if (empty($arRunErrors))
{
	if ($MAX_EXECUTION_TIME == 0)
		$finalExport = true;
	if ($finalExport)
	{
		$content = '';
		if ($boolNeedRootSection)
			$content .= '<category id="'.$intMaxSectionID.'">'.yandex_text2xml(GetMessage('YANDEX_ROOT_DIRECTORY'), true).'</category>'."\n";
		$content .= "</categories>\n";
		$content .= "<offers>\n";

		$items = file_get_contents($_SERVER["DOCUMENT_ROOT"].$itemFileName);
		if ($items === false)
		{
			$arRunErrors[] = GetMessage('YANDEX_STEP_ERR_DATA_FILE_NOT_READ');
		}
		else
		{
			$content .= $items;
			unset($items);

			$content .= "</offers>\n";

			/* promos */
			//, "s2"
			if(in_array($SITE_ID, array("s1"))){
				$content .= '<promos>'."\n";

				if($SITE_ID == "s1"){
					$content .= '<promo id="yandex2019" type="promo code">'."\n";
					$content .= '<description>';
					$content .= '<![CDATA[';
					$content .= iconv("utf-8", "windows-1251", "Скидка 5% на это предложение магазина tempusshop.ru");
					$content .= ']]>';
					$content .= '</description>'."\n";
					$content .= '<promo-code>yandex2019</promo-code>'."\n";
					$content .= '<discount unit="percent">5</discount>'."\n";
				}else{
					$content .= '<promo id="yandexBY" type="promo code">'."\n";
					$content .= '<description>';
					$content .= '<![CDATA[';
					$content .= iconv("utf-8", "windows-1251", "Скидка 5% на это предложение магазина Tempus.by");
					$content .= ']]>';
					$content .= '</description>'."\n";
					$content .= '<promo-code>yandexBY</promo-code>'."\n";
					$content .= '<discount unit="percent">5</discount>'."\n";
				}


				$content .= '<purchase>'."\n";

				foreach ($arAvailGroups as $value)
					$strTmpPromos .= '<product category-id="'.$value['ID'].'"/>'."\n";
				unset($value);
				$content .= $strTmpPromos;
				$content .= '</purchase>'."\n";
				$content .= '</promo>'."\n";
				$content .= '</promos>'."\n";

				unset($strTmpPromos);
			}

			/* end promos */

			$content .= "</shop>\n"."</yml_catalog>\n";

			file_put_contents($_SERVER["DOCUMENT_ROOT"].$SETUP_FILE_NAME, $content, FILE_APPEND);

			sleep(5);
			/* пишем файл отдельно */
			$ch = curl_init();    // инициализация
			curl_setopt($ch, CURLOPT_URL, "http://tempusshop.ru/{$SETUP_FILE_NAME}");
			curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER,1);
			$result = curl_exec($ch);
			curl_close($ch);

			require_once($_SERVER['DOCUMENT_ROOT'].'/bitrix/php_interface/include/classes/opXml2Array.php');
			$array = XML2Array::createArray($result);
			if(empty($array["error"])){
				$_info = pathinfo($_SERVER["DOCUMENT_ROOT"] . "{$SETUP_FILE_NAME}");
				$_filename = basename($_SERVER["DOCUMENT_ROOT"] . "{$SETUP_FILE_NAME}", "." . $_info['extension']);
				//$result = iconv("WINDOWS-1251", "UTF-8//IGNORE", $result);
				chmod($_SERVER["DOCUMENT_ROOT"]."/prices/{$_filename}.xml", 0777);
				file_put_contents($_SERVER["DOCUMENT_ROOT"] . "/prices/{$_filename}.xml", $result, LOCK_EX);
				//[error] => [XML2Array] Error parsing the XML string.
			}


			//$asd = file_get_contents($_SERVER["DOCUMENT_ROOT"].$SETUP_FILE_NAME);
			//$fpp = @fopen($_SERVER["DOCUMENT_ROOT"].$SETUP_FILE_NAME . "_tmp", 'wb');
			//file_put_contents($_SERVER["DOCUMENT_ROOT"]."op_yandex.php", stripInvalidXml($asd));
		}
		unlink($_SERVER["DOCUMENT_ROOT"].$itemFileName);
	}
}

CCatalogDiscountSave::Enable();


if (!empty($arRunErrors))
	$strExportErrorMessage = implode('<br />',$arRunErrors);

if ($bTmpUserCreated)
{
	if (isset($USER_TMP))
	{
		$USER = $USER_TMP;
		unset($USER_TMP);
	}
}
