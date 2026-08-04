<?
//<title>Google</title>
/** @global CUser $USER */
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

use Bitrix\Main,
    Bitrix\Main\Loader,
    Bitrix\Currency,
    Bitrix\Iblock,
    Bitrix\Catalog,
    Bitrix\Sale;

IncludeModuleLangFile($_SERVER['DOCUMENT_ROOT'].'/bitrix/modules/weblst.avito/lang/ru/export_weblst.php');
IncludeModuleLangFile(__FILE__);

$module = CModule::IncludeModuleEx('weblst.avito');
if($module != 1 && $module != 2){

            return ;
}


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

$CHECK_PERMISSIONS = (isset($CHECK_PERMISSIONS) && $CHECK_PERMISSIONS == 'Y' ? 'Y' : 'N');
if ($CHECK_PERMISSIONS == 'Y')
    $permissionFilter = array('CHECK_PERMISSIONS' => 'Y', 'MIN_PERMISSION' => 'R', 'PERMISSIONS_BY' => 0);
else
    $permissionFilter = array('CHECK_PERMISSIONS' => 'N');

if (!isset($firstStep))
    $firstStep = true;

$pageSize = 100;
$navParams = array('nTopCount' => $pageSize);

$SETUP_VARS_LIST = 'IBLOCK_ID,SITE_ID,V,XML_DATA,SETUP_SERVER_NAME,COMPANY_NAME,SETUP_FILE_NAME,USE_HTTPS,FILTER_AVAILABLE,DISABLE_REFERERS,EXPORT_CHARSET,MAX_EXECUTION_TIME,CHECK_PERMISSIONS,COST,CONDITION,ADTYPE,CATEGORY,CATEGORY_TYPE,APPAREL,SIZE,ListingFee,APPAREL,ADDRESS,DOPDESCRIPTION,USE_COST,IMAGES,LOAD,SUBCATEGORY_SECTION,CATEGORY_SECTION,USE_CATEGORY,VIDEO,PROPERTY_CONDITION,FILTER,STORE_FILTER,APPAREL_SECTION,ContactPhone,ManagerName,RimDiameter,RimType,TireType,TireSectionWidth,TireAspectRatio,RimWidth,RimBolts,RimBoltsDiameter,RimOffset,RunFlat,Homologation,Brand,Model,SpeedIndex,LoadIndex,USE_TITLE,TITLE,imagelink,MAXPRICE,MINPRICE,DisplayAreas,EngineCapacity,Power,Year,Type,Make';
$INTERNAL_VARS_LIST = 'intMaxSectionID,boolNeedRootSection,arSectionIDs,arAvailGroups';

global $USER;
$bTmpUserCreated = false;
if (!CCatalog::IsUserExists())
{
    $bTmpUserCreated = true;
    if (isset($USER))
        $USER_TMP = $USER;
    $USER = new CUser();
}

$saleIncluded = Loader::includeModule('sale');
if ($saleIncluded)
    Sale\DiscountCouponsManager::freezeCouponStorage();
CCatalogDiscountSave::Disable();

$arResult["MARGIN"] = 0;
if($_REQUEST["PROFILE_ID"] == 43 || $SETUP_FILE_NAME == "/bitrix/catalog_export/weblst_avito580815.php"){
	$arResult["MARGIN"] = 0.75;
}

$arYandexFields = array(
'param','Size','Price','Image','VideoURL','Condition','RimDiameter','RimType','TireType','TireSectionWidth','TireAspectRatio','RimWidth','RimBolts','RimBoltsDiameter','RimOffset','RunFlat','Homologation','Brand','Model','SpeedIndex','LoadIndex','Make','Type','Year','Power','EngineCapacity'
);

file_put_contents("/var/www/bitrix/data/www/tempusshop.ru/bitrix/modules/weblst.avito/test.txt", print_r('ENTER', true) . "\r\n");

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
file_put_contents("/var/www/bitrix/data/www/tempusshop.ru/bitrix/modules/weblst.avito/test.txt", print_r($fields, true) . "\r\n", FILE_APPEND);
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
$parametricFieldsExist = true;
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

if($USE_COST && $COST){
    $yandexNeedPropertyIds[$COST] = true;
}
if($CONDITION == 'PROPERTY' && $PROPERTY_CONDITION){
    $yandexNeedPropertyIds[$PROPERTY_CONDITION] = true;
}
if($VIDEO){
    $yandexNeedPropertyIds[$VIDEO] = true;
}
if($IMAGES){
    $yandexNeedPropertyIds[$IMAGES] = true;
}
if($SIZE){
    $yandexNeedPropertyIds[$SIZE] = true;
}
if($TITLE){
    $yandexNeedPropertyIds[$TITLE] = true;
}


$tire = array('RimDiameter','RimType','TireType','TireSectionWidth','TireAspectRatio','RimWidth','RimBolts','RimBoltsDiameter','RimOffset','RunFlat','Homologation','Brand','Model','SpeedIndex','LoadIndex');
if(in_array($CATEGORY_TYPE,array('10-044','10-045','10-046','10-047','10-048'))) {
    foreach ($tire as $tr) {

        if (${"$tr"}) {
            $yandexNeedPropertyIds[${"$tr"}] = true;
        }

    }
}
$moto = array('Make','Model','Type','Year','Power','EngineCapacity');

if($CATEGORY== GetMessage('CAT_WERBLST_MOTO')) {
    foreach ($moto as $tr) {

        if (${"$tr"}) {
            $yandexNeedPropertyIds[${"$tr"}] = true;
        }

    }
}
$yandexNeedPropertyIds[123] = true;
$yandexNeedPropertyIds[126] = true;
$commonFields = [
    'DESCRIPTION' => 'PREVIEW_TEXT'
];
if (!empty($XML_DATA['COMMON_FIELDS']) && is_array($XML_DATA['COMMON_FIELDS']))
    $commonFields = array_merge($commonFields, $XML_DATA['COMMON_FIELDS']);
$descrField = $commonFields['DESCRIPTION'];

$propertyFields = array(
    'ID', 'PROPERTY_TYPE', 'MULTIPLE', 'USER_TYPE'
);
$DOPPROPERTIS = array();
$itemUrlConfig = [
    'USE_DOMAIN' => true,
    'REFERRER_SEPARATOR' => '?'
];
$offerUrlConfig = [
    'USE_DOMAIN' => true,
    'REFERRER_SEPARATOR' => '?'
];

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
        if(mb_strripos($DOPDESCRIPTION,'#'.$arProp['CODE'].'#')  === false){

        } else {
            $yandexNeedPropertyIds[$arProp['ID']] = true;
            $DOPPROPERTIS[$arProp['CODE']] = $arProp['ID'];
        }
    }

    unset($arProp, $rsProps);

    $ar_iblock['DETAIL_PAGE_URL'] = (string)$ar_iblock['DETAIL_PAGE_URL'];
    $itemUrlConfig['USE_DOMAIN'] = !(preg_match("/^(http|https):\\/\\//i", $ar_iblock['DETAIL_PAGE_URL']));
    $itemUrlConfig['REFERRER_SEPARATOR'] = (mb_strpos($ar_iblock['DETAIL_PAGE_URL'], '?') === false ? '?' : '&amp;');
}

$SETUP_SERVER_NAME = (isset($SETUP_SERVER_NAME) ? trim($SETUP_SERVER_NAME) : '');
$COMPANY_NAME = (isset($COMPANY_NAME) ? trim($COMPANY_NAME) : '');
$COST = (isset($COST) ? trim($COST) : '');

$SITE_ID = (isset($SITE_ID) ? (string)$SITE_ID : '');
if ($SITE_ID === '')
    $SITE_ID = $ar_iblock['LID'];
$iterator = Main\SiteTable::getList(array(
    'select' => array('LID', 'SERVER_NAME', 'SITE_NAME', 'DIR'),
    'filter' => array('=LID' => $SITE_ID, '=ACTIVE' => 'Y')
));
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
            unset($arProp, $rsProps);
            $arOfferIBlock['LID'] = $site['LID'];

            $arOfferIBlock['DETAIL_PAGE_URL'] = (string)$arOfferIBlock['DETAIL_PAGE_URL'];
            if ($arOfferIBlock['DETAIL_PAGE_URL'] == '#PRODUCT_URL#')
            {
                $offerUrlConfig = $itemUrlConfig;
            }
            else
            {
                $offerUrlConfig['USE_DOMAIN'] = !(preg_match("/^(http|https):\\/\\//i", $arOfferIBlock['DETAIL_PAGE_URL']));
                $offerUrlConfig['REFERRER_SEPARATOR'] = (mb_strpos($arOfferIBlock['DETAIL_PAGE_URL'], '?') === false ? '?' : '&amp;');
            }
        }
        else
        {
            $arRunErrors[] = GetMessage('YANDEX_ERR_BAD_OFFERS_IBLOCK_ID');
        }
        unset($rsOfferIBlocks);
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
            $arSKUExport = $XML_DATA['SKU_EXPORT'];;
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
unset($arUserType, $key, $arProperty);

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
    if (!$bAllSections && !empty($arSections) && $CHECK_PERMISSIONS == 'Y')
    {
        $clearedValues = array();
        $filter = array(
            'IBLOCK_ID' => $IBLOCK_ID,
            'ID' => $arSections
        );
        $iterator = CIBlockSection::GetList(
            array(),
            array_merge($filter, $permissionFilter),
            false,
            array('ID')
        );
        while ($row = $iterator->Fetch())
            $clearedValues[] = (int)$row['ID'];
        unset($row, $iterator);
        $arSections = $clearedValues;
        unset($clearedValues);
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
        $priceIterator = Catalog\GroupAccessTable::getList([
            'select' => ['CATALOG_GROUP_ID'],
            'filter' => ['=CATALOG_GROUP_ID' => $XML_DATA['PRICE']]
        ]);
        $priceType = $priceIterator->fetch();
        if (empty($priceType))
            $arRunErrors[] = GetMessage('YANDEX_ERR_BAD_PRICE_TYPE');
        else
            $selectedPriceType = $XML_DATA['PRICE'];
        unset($priceType, $priceIterator);
    }
    else
    {
        $arRunErrors[] = GetMessage('YANDEX_ERR_BAD_PRICE_TYPE');
    }
}

$priceTypeList = [];
if (empty($arRunErrors))
{
    if ($selectedPriceType > 0)
    {
        $priceTypeList = [$selectedPriceType];
    }
    else
    {
        $priceTypeList = [];
        $priceIterator = Catalog\GroupAccessTable::getList([
            'select' => ['CATALOG_GROUP_ID'],
        //    'filter' => ['=GROUP_ID' => 2],
            'order' => ['CATALOG_GROUP_ID' => 'ASC']
        ]);
        while ($priceType = $priceIterator->fetch())
        {
            $priceTypeId = (int)$priceType['CATALOG_GROUP_ID'];
            $priceTypeList[$priceTypeId] = $priceTypeId;
            unset($priceTypeId);
        }
        unset($priceType, $priceIterator);
        if (empty($priceTypeList))
            $arRunErrors[] = GetMessage('BX_CATALOG_EXPORT_YANDEX_ERR_NO_AVAILABLE_PRICE_TYPES');
    }
}

$usedProtocol = (isset($USE_HTTPS) && $USE_HTTPS == 'Y' ? 'https://' : 'http://');
$filterAvailable = (isset($FILTER_AVAILABLE) && $FILTER_AVAILABLE == 'Y');

//$EXPORT_CHARSET = 'UTF-8';
$exportCharset = (isset($EXPORT_CHARSET) && is_string($EXPORT_CHARSET) ? $EXPORT_CHARSET : '');
if ($exportCharset != 'UTF-8')
    $exportCharset = 'windows-1251';

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
    'CHARSET' => $exportCharset,
    'SITE_NAME' => $site['SERVER_NAME'],
    'SITE_DIR' => $site['DIR'],
    'DESCRIPTION' => $descrField,
    'MAX_DESCRIPTION_LENGTH' => 3000
);
$replaceSHArray = GetMessage('WEBLST_AVITO_SH');

$replaceSHArray = explode("\n",$replaceSHArray);

foreach($replaceSHArray as $arr) {
    $exp =  explode(';', $arr);

    $findSH[] = trim($exp[0]);
    $replaceSH[] = trim($exp[1]);
}


$sectionFileName = '';
$itemFileName = '';
if ($SETUP_FILE_NAME == '')
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
    $sectionFileName = $SETUP_FILE_NAME.'_sections';
    $itemFileName = $SETUP_FILE_NAME.'_items';
}

$itemsFile = null;

$BASE_CURRENCY = Currency\CurrencyManager::getBaseCurrency();

if ($firstStep)
{
    if (empty($arRunErrors))
    {
        CheckDirPath($_SERVER["DOCUMENT_ROOT"].$SETUP_FILE_NAME);

        if (!$fp = @fopen($_SERVER["DOCUMENT_ROOT"].$sectionFileName, "wb"))
        {
            $arRunErrors[] = str_replace('#FILE#', $sectionFileName, GetMessage('YANDEX_ERR_FILE_OPEN_WRITING'));
        }
        else
        {
            if (!@fwrite($fp, '<? $disableReferers = '.($disableReferers ? 'true' : 'false').';'."\n"))
            {
                $arRunErrors[] = str_replace('#FILE#', $sectionFileName, GetMessage('YANDEX_ERR_SETUP_FILE_WRITE'));
                @fclose($fp);
            }
            else
            {

            }
        }
    }

    if (empty($arRunErrors))
    {
        /** @noinspection PhpUndefinedVariableInspection */
        fwrite($fp, 'header("Content-Type: text/xml; charset='.$itemOptions['CHARSET'].'");'."\n");
        fwrite($fp, 'echo "<"."?xml version=\"1.0\" encoding=\"'.$itemOptions['CHARSET'].'\"?".">"?>');
        fwrite($fp, "\n"."<Ads formatVersion=\"3\" target=\"Avito.ru\">"."\n");



        $charsetError = '';





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
                $sectionIterator = CIBlockSection::GetNavChain($IBLOCK_ID, $arSections[$i], array());
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

                $filter = array(
                    'IBLOCK_ID' => $IBLOCK_ID,
                    '>LEFT_MARGIN' => $curLEFT_MARGIN,
                    '<RIGHT_MARGIN' => $curRIGHT_MARGIN,
                    'GLOBAL_ACTIVE' => 'Y'
                );
                $sectionIterator = CIBlockSection::GetList(
                    array('LEFT_MARGIN' => 'ASC'),
                    array_merge($filter, $permissionFilter),
                    false,
                    array('ID', 'IBLOCK_SECTION_ID', 'NAME')
                );
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
            $filter = array(
                'IBLOCK_ID' => $IBLOCK_ID,
                'GLOBAL_ACTIVE' => 'Y'
            );
            $sectionIterator = CIBlockSection::GetList(
                array('LEFT_MARGIN' => 'ASC'),
                array_merge($filter, $permissionFilter),
                false,
                array('ID', 'IBLOCK_SECTION_ID', 'NAME')
            );

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
        $arsect = array();
        foreach ($arAvailGroups as $value) {
            if ($value['IBLOCK_SECTION_ID'] > 0) {
                $arsect[$value['ID']] = $arsect[$value['IBLOCK_SECTION_ID']] . " > " . $value['NAME'];
            } else {
                $arsect[$value['ID']] = $ar_iblock['NAME'] . " > " . $value['NAME'];
            }


            unset($value);
        }

        $intMaxSectionID += 100000000;


        fwrite($fp, $strTmpCat);
        fclose($fp);
        unset($strTmpCat);

        $boolNeedRootSection = false;

        $itemsFile = @fopen($_SERVER["DOCUMENT_ROOT"].$itemFileName, 'wb');
        if (!$itemsFile)
        {
            $arRunErrors[] = str_replace('#FILE#', $itemFileName, GetMessage('YANDEX_ERR_FILE_OPEN_WRITING'));
        }
    }
}
else
{
    $itemsFile = @fopen($_SERVER["DOCUMENT_ROOT"].$itemFileName, 'ab');
    if (!$itemsFile)
    {
        $arRunErrors[] = str_replace('#FILE#', $itemFileName, GetMessage('YANDEX_ERR_FILE_OPEN_WRITING'));
    }
}
unset($arSections);

if (empty($arRunErrors))
{
    //*****************************************//
    $saleDiscountOnly = false;
    $calculationConfig = [
        'CURRENCY' => $BASE_CURRENCY,
        'USE_DISCOUNTS' => true,
        'RESULT_WITH_VAT' => true,
        'RESULT_MODE' => Catalog\Product\Price\Calculation::RESULT_MODE_COMPONENT
    ];
    if ($saleIncluded)
    {
        $saleDiscountOnly = (string)Main\Config\Option::get('sale', 'use_sale_discount_only') == 'Y';
        if ($saleDiscountOnly)
            $calculationConfig['PRECISION'] = (int)Main\Config\Option::get('sale', 'value_precision');
    }
    Catalog\Product\Price\Calculation::setConfig($calculationConfig);
    unset($calculationConfig);

    $needDiscountCache = \CIBlockPriceTools::SetCatalogDiscountCache($priceTypeList, array(2), $site['LID']);

    $itemFields = array(
        'ID', 'IBLOCK_ID', 'IBLOCK_SECTION_ID', 'NAME',
        'PREVIEW_PICTURE', $descrField, $descrField.'_TYPE', 'DETAIL_PICTURE', 'DETAIL_PAGE_URL',
        'AVAILABLE', 'TYPE', 'VAT_ID', 'VAT_INCLUDED'
    );
    $offerFields = array(
        'ID', 'IBLOCK_ID', 'IBLOCK_SECTION_ID', 'NAME',
        'PREVIEW_PICTURE', $descrField, $descrField.'_TYPE', 'DETAIL_PICTURE', 'DETAIL_PAGE_URL',
        'AVAILABLE', 'TYPE', 'VAT_ID', 'VAT_INCLUDED'
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
    // similar goods from property with type filter
    if(!empty($FILTER))
    {
        $cond = new WeblstAvitoCondition();
        try{
            $arTmpAssoc = unserialize($FILTER);//\Bitrix\Main\Web\Json::decode($FILTER);

            $arAssociatedFilter = $cond->parseCondition($arTmpAssoc, $arParams);
        }
        catch(\Exception $e){
            $arAssociatedFilter = array();
        }
        unset($cond);
    }

  //  $filterOffer = $arAssociatedFilter['=ID']->arFilter[0];
  //  unset($arAssociatedFilter['=ID']);
    // similar goods from property with type link


    if (!$bAllSections && !empty($arSectionIDs))
    {
        $filter['INCLUDE_SUBSECTIONS'] = 'Y';
        $filter['SECTION_ID'] = $arSectionIDs;
    }
    $filter['ACTIVE'] = 'Y';
    $filter['ACTIVE_DATE'] = 'Y';
    if ($filterAvailable)
        $filter['AVAILABLE'] = 'Y';
    $filter = array_merge($filter, $permissionFilter);
    $filter[] = $arAssociatedFilter;

    $offersFilter = array('ACTIVE' => 'Y', 'ACTIVE_DATE' => 'Y');
    if ($filterAvailable)
        $offersFilter['AVAILABLE'] = 'Y';
    $offersFilter = array_merge($offersFilter, $permissionFilter);

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
    if(!empty($STORE_FILTER)){

        $filter[] = [
            'LOGIC' => 'OR',
            [
                '=TYPE' => \Bitrix\Catalog\ProductTable::TYPE_PRODUCT,
                '=STORE_NUMBER' => $STORE_FILTER,
                '>STORE_AMOUNT' => 0
            ],
            ['!=TYPE' => \Bitrix\Catalog\ProductTable::TYPE_PRODUCT]
        ];
         $offersFilter['>STORE_AMOUNT'] =  0 ;
         $offersFilter['=STORE_NUMBER'] =  $STORE_FILTER;

        foreach ($STORE_FILTER as $store_id){
            //$filter['!STORE_AMOUNT_'.$store_id] = false;
          //  $filter['OFFERS']['>STORE_AMOUNT_'.$store_id] = 0;
           // $offersFilter['>STORE_AMOUNT_'.$store_id] = 0;
        }
    }


    do
    {
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
        while ($row = $iterator->Fetch())
        {

            $finalExport = false; // items exist
            $existItems = true;

            $id = (int)$row['ID'];
            $CUR_ELEMENT_ID = $id;

            $row['TYPE'] = (int)$row['TYPE'];
            $elementType = $row['TYPE'];
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

            weblstAvito::weblstAvito_PrepareItems($items, array(), $itemOptions);

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
            if($USE_CATEGORY == 'Y'){
                foreach ($items as $key=>$id){

                    $items[$key]['UF'] =   \CIBlockSection::GetList(array(),array('ID'=>$id['IBLOCK_SECTION_ID'],'IBLOCK_ID'=>$IBLOCK_ID),false, array('ID','UF_*'))->GetNext();

                }


            }

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



                if ($needDiscountCache)
                {
                    foreach ($itemIdsList as $id)
                        \CCatalogDiscount::SetProductPropertiesCache($id, $items[$id]['PROPERTIES']);
                    unset($id);
                }

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
                    $parentsUrl = array();
                    foreach (array_keys($offers) as $productId)
                    {
                        unset($skuIdsList[$productId]);
                        $items[$productId]['OFFERS'] = array();
                        $parentsUrl[$productId] = $items[$productId]['DETAIL_PAGE_URL'];
                        foreach (array_keys($offers[$productId]) as $offerId)
                        {
                            $productOffer = $offers[$productId][$offerId];
                            $productOffer['VAT_ID'] = (int)$productOffer['VAT_ID'];
                            if ($productOffer['VAT_ID'] == 0)
                                $productOffer['VAT_ID'] = $offersCatalog['VAT_ID'];

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
                        weblstAvito::weblstAvito_PrepareItems($offerLinks, $parentsUrl, $itemOptions);

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

                            // load vat cache
                            $vatList = CCatalogProduct::GetVATDataByIDList($pageIds);
                            unset($vatList);

                            $priceFilter = [
                                '@PRODUCT_ID' => $pageIds,
                                [
                                    'LOGIC' => 'OR',
                                    '<=QUANTITY_FROM' => 1,
                                    '=QUANTITY_FROM' => null
                                ],
                                [
                                    'LOGIC' => 'OR',
                                    '>=QUANTITY_TO' => 1,
                                    '=QUANTITY_TO' => null
                                ]
                            ];
                            if ($selectedPriceType > 0)
                                $priceFilter['=CATALOG_GROUP_ID'] = $selectedPriceType;
                            else
                                $priceFilter['@CATALOG_GROUP_ID'] = $priceTypeList;

                            $iterator = Catalog\PriceTable::getList([
                                'select' => ['ID', 'PRODUCT_ID', 'CATALOG_GROUP_ID', 'PRICE', 'CURRENCY'],
                                'filter' => $priceFilter
                            ]);

                            while ($price = $iterator->fetch())
                            {
                                $id = (int)$price['PRODUCT_ID'];
                                $priceTypeId = (int)$price['CATALOG_GROUP_ID'];
                                $offerLinks[$id]['PRICES'][$priceTypeId] = $price;
                                unset($priceTypeId, $id);
                            }
                            unset($price, $iterator);

                            if ($saleDiscountOnly)
                            {
                                Catalog\Discount\DiscountManager::preloadPriceData(
                                    $pageIds,
                                    ($selectedPriceType > 0 ? [$selectedPriceType] : $priceTypeList)
                                );
                            }
                        }
                        unset($pageIds);
                    }
                    unset($parentsUrl, $offerIdsList, $offerLinks);
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
                    // load vat cache
                    $vatList = CCatalogProduct::GetVATDataByIDList($pageIds);
                    unset($vatList);

                    $priceFilter = [
                        '@PRODUCT_ID' => $pageIds,
                        [
                            'LOGIC' => 'OR',
                            '<=QUANTITY_FROM' => 1,
                            '=QUANTITY_FROM' => null
                        ],
                        [
                            'LOGIC' => 'OR',
                            '>=QUANTITY_TO' => 1,
                            '=QUANTITY_TO' => null
                        ]
                    ];
                    if ($selectedPriceType > 0)
                        $priceFilter['=CATALOG_GROUP_ID'] = $selectedPriceType;
                    else
                        $priceFilter['@CATALOG_GROUP_ID'] = $priceTypeList;

                    $iterator = Catalog\PriceTable::getList([
                        'select' => ['ID', 'PRODUCT_ID', 'CATALOG_GROUP_ID', 'PRICE', 'CURRENCY'],
                        'filter' => $priceFilter
                    ]);

                    while ($price = $iterator->fetch())
                    {
                        $id = (int)$price['PRODUCT_ID'];
                        $priceTypeId = (int)$price['CATALOG_GROUP_ID'];
                        $items[$id]['PRICES'][$priceTypeId] = $price;
                        unset($priceTypeId, $id);
                    }
                    unset($price, $iterator);

                    if ($saleDiscountOnly)
                    {
                        Catalog\Discount\DiscountManager::preloadPriceData(
                            $pageIds,
                            ($selectedPriceType > 0 ? [$selectedPriceType] : $priceTypeList)
                        );
                    }
                }
                unset($pageIds);
            }
        }

        $itemsContent = '';

        if (!empty($items))
        {
            foreach ($itemIdsList as $id)
            {
                $CUR_ELEMENT_ID = $id;

                $row = $items[$id];

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

                if ($row['TYPE'] == Catalog\ProductTable::TYPE_SKU && !empty($row['OFFERS']))
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
                            $site['LID'],
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
                        if($MINPRICE > $minPrice && !empty($MINPRICE)){
                            unset($row['OFFERS'][$offerId]);
                            continue;
                        }
                        if($MAXPRICE < $minPrice && !empty($MAXPRICE)){
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

                        $itemsContent .= "<Ad>\n";
                        unset($available);
                        $itemsContent .= "<Id>".$offer['ID']."</Id>\n";


                        if($USE_TITLE == 'Y'){
                            $view_title = weblstAvito::weblstAvito_get_value(
                                $offer,
                                'none',
                                $TITLE,
                                $arProperties,
                                $arUserTypeFormat,
                                $itemOptions
                            );
                            if ($view_title == '')
                            {
                                $view_title = weblstAvito::weblstAvito_get_value(
                                    $row,
                                    'none',
                                    $TITLE,
                                    $arProperties,
                                    $arUserTypeFormat,
                                    $itemOptions
                                );
                            }

                        }
                        if($view_title == ''){
                            $itemsContent .= "<Title>".weblstAvito::weblstavito_text2xml(TruncateText($offer['NAME'], $LOAD), $itemOptions)."</Title>\n";
                        } else {
                            $itemsContent .= "<Title>".weblstAvito::weblstavito_text2xml(TruncateText($view_title, $LOAD), $itemOptions)."</Title>\n";
                        }

                        $referer = '';


                        $itemsContent .= "<ListingFee>".$ListingFee.'</ListingFee>';

                        $minPrice = $offer['RESULT_PRICE']['MIN_PRICE'];
                        $fullPrice = $offer['RESULT_PRICE']['FULL_PRICE'];

                        switch ($COST){
                            case 'yes_up':
                                $minPrice = ceil($minPrice);
                                $fullPrice = ceil($fullPrice);
                                break;
                            case'yes_down':

                                $minPrice = floor($minPrice);
                                $fullPrice = floor($fullPrice);
                                break;
                            default:
                                break;
                        }
                        if($USE_COST != 'Y') {
                            if ($minPrice < $fullPrice) {
                                // $minPrice = number_format($minPrice,2,'.','');
                                // $fullPrice = number_format($fullPrice,2,'.','');
                                $itemsContent .= "<Price>" . $minPrice . "</Price>\n";


                            } else {
                                // $minPrice = number_format($minPrice,2,'.','');

                                $itemsContent .= "<Price>" . $minPrice . "</Price>\n";
                            }
                        }



                        $y = 0;
                        $images_list = '';
                        $descriptionFields = '';
                        foreach ($arYandexFields as $key)
                        {
                            switch ($key)
                            {
                                case 'VideoURL':

                                    if(!empty($VIDEO)) {
                                        $value = weblstAvito::weblstAvito_get_value(
                                            $offer,
                                            $key,
                                            $VIDEO,
                                            $arProperties,
                                            $arUserTypeFormat,
                                            $itemOptions
                                        );
                                        if ($value == '')
                                        {
                                            $value = weblstAvito::weblstAvito_get_value(
                                                $row,
                                                $key,
                                                $VIDEO,
                                                $arProperties,
                                                $arUserTypeFormat,
                                                $itemOptions
                                            );
                                        }

                                        unset($value);
                                    }
                                    break;

                                case 'Image':
                                    $images_list = '';
                                    if(!empty($IMAGES)) {
                                        $value = weblstAvito::weblstAvito_get_value(
                                            $offer,
                                            $key,
                                            $IMAGES,
                                            $arProperties,
                                            $arUserTypeFormat,
                                            $itemOptions
                                        );
                                        if ($value == '')
                                        {
                                            $value = weblstAvito::weblstAvito_get_value(
                                                $row,
                                                $key,
                                                $IMAGES,
                                                $arProperties,
                                                $arUserTypeFormat,
                                                $itemOptions
                                            );
                                        }
                                        if ($value != '')
                                            $images_list .= $value . "\n";
                                        unset($value);
                                    }
                                    break;
                                case 'RimDiameter':
                                case 'RimType':
                                case 'TireType':
                                case 'TireSectionWidth':
                                case 'TireAspectRatio':
                                case 'RimWidth':
                                case 'RimBolts':
                                case 'RimBoltsDiameter':
                                case 'RimOffset':
                                case 'RunFlat':
                                case 'Homologation':
                                case 'Brand':

                                case 'SpeedIndex':
                                case 'LoadIndex':
                                if(in_array($CATEGORY_TYPE,array('10-044','10-045','10-046','10-047','10-048'))) {
                                    $value = weblstAvito::weblstAvito_get_value(
                                        $offer,
                                        $key,
                                        ${"$key"},
                                        $arProperties,
                                        $arUserTypeFormat,
                                        $itemOptions
                                    );

                                    if ($value == '') {
                                        $value = weblstAvito::weblstAvito_get_value(
                                            $row,
                                            $key,
                                            ${"$key"},
                                            $arProperties,
                                            $arUserTypeFormat,
                                            $itemOptions
                                        );
                                    }
                                    if($key == 'TireType' && $value != ''){
                                        $value =  str_ireplace($findSH,$replaceSH,$value);
                                    }
                                    if ($value != '')
                                        $itemsContent .= $value . "\n";
                                    unset($value);

                                }
                                    break;

                                case 'Model':
                                    if(in_array($CATEGORY_TYPE,array('10-044','10-045','10-046','10-047','10-048')) || $CATEGORY== GetMessage('CAT_WERBLST_MOTO')) {
                                        $value = weblstAvito::weblstAvito_get_value(
                                            $offer,
                                            $key,
                                            ${"$key"},
                                            $arProperties,
                                            $arUserTypeFormat,
                                            $itemOptions
                                        );

                                        if ($value == '') {
                                            $value = weblstAvito::weblstAvito_get_value(
                                                $row,
                                                $key,
                                                ${"$key"},
                                                $arProperties,
                                                $arUserTypeFormat,
                                                $itemOptions
                                            );
                                        }

                                        if ($value != '')
                                            $itemsContent .= $value . "\n";
                                        unset($value);

                                    }
                                    break;
                                    break;
                                case 'Make':
                                case 'Type':
                                case 'Year':
                                case 'Power':
                                case 'EngineCapacity':
                                    if($CATEGORY== GetMessage('CAT_WERBLST_MOTO')) {
                                        $value = weblstAvito::weblstAvito_get_value(
                                            $offer,
                                            $key,
                                            ${"$key"},
                                            $arProperties,
                                            $arUserTypeFormat,
                                            $itemOptions
                                        );

                                        if ($value == '') {
                                            $value = weblstAvito::weblstAvito_get_value(
                                                $row,
                                                $key,
                                                ${"$key"},
                                                $arProperties,
                                                $arUserTypeFormat,
                                                $itemOptions
                                            );
                                        }

                                        if ($value != '')
                                            $itemsContent .= $value . "\n";
                                        unset($value);

                                    }
                                    break;
                                case 'Condition':

                                    if($CONDITION && $CONDITION == 'PROPERTY')
                                    {
                                        $value = weblstAvito::weblstAvito_get_value(
                                            $offer,
                                            $key,
                                            $PROPERTY_CONDITION,
                                            $arProperties,
                                            $arUserTypeFormat,
                                            $itemOptions
                                        );

                                        if ($value == '')
                                        {
                                            $value = weblstAvito::weblstAvito_get_value(
                                                $row,
                                                $key,
                                                $PROPERTY_CONDITION,
                                                $arProperties,
                                                $arUserTypeFormat,
                                                $itemOptions
                                            );
                                        }

                                        if ($value != '')
                                            $itemsContent .= $value."\n";
                                        unset($value);
                                    }
                                    break;
                              case 'Price':

                                  if($COST && $USE_COST == 'Y')
                                    {
                                        $value = weblstAvito::weblstAvito_get_value(
                                            $offer,
                                            $key,
                                            $COST,
                                            $arProperties,
                                            $arUserTypeFormat,
                                            $itemOptions
                                        );

                                        if ($value == '')
                                        {
                                            $value = weblstAvito::weblstAvito_get_value(
                                                $row,
                                                $key,
                                                $COST,
                                                $arProperties,
                                                $arUserTypeFormat,
                                                $itemOptions
                                            );
                                        }

                                        if ($value != '')
                                            $itemsContent .= $value."\n";
                                        unset($value);
                                    }
                                    break;
                                case  'Size':

                                     if($CATEGORY != GetMessage('WEBLST_AVITO_CHILD_SHOES') || $CATEGORY != GetMessage('WEBLST_AVITO_SHOES'))
                                          {
                                              $value = weblstAvito::weblstAvito_get_value(
                                                  $offer,
                                                  $key,
                                                  $fields[$key],
                                                  $arProperties,
                                                  $arUserTypeFormat,
                                                  $itemOptions
                                              );
                                              if ($value == '')
                                              {
                                                  $value = weblstAvito::weblstAvito_get_value(
                                                      $row,
                                                      $key,
                                                      $fields[$key],
                                                      $arProperties,
                                                      $arUserTypeFormat,
                                                      $itemOptions
                                                  );
                                              }
                                              if ($value != '')
                                                  $itemsContent .= $value."\n";
                                              unset($value);
                                          }
                                          break;
                                case 'param':
                                    if ($parametricFieldsExist)
                                    {
                                        foreach ($parametricFields as $paramKey => $prop_id)
                                        {
                                            $value = weblstAvito::weblstAvito_get_value(
                                                $offer,
                                                'PARAM_'.$paramKey,
                                                $prop_id,
                                                $arProperties,
                                                $arUserTypeFormat,
                                                $itemOptions
                                            );
                                            if ($value == '')
                                            {
                                                $value = weblstAvito::weblstAvito_get_value(
                                                    $row,
                                                    'PARAM_'.$paramKey,
                                                    $prop_id,
                                                    $arProperties,
                                                    $arUserTypeFormat,
                                                    $itemOptions
                                                );
                                            }

                                            if ($value != '')
                                                $descriptionFields .= $value."\n";
                                            unset($value);
                                        }
                                        unset($paramKey, $prop_id);
                                    }
                                    break;


                                default:

                                    if ($fieldsExist && isset($fields[$key]))
                                    {
                                        $value = weblstAvito::weblstAvito_get_value(
                                            $offer,
                                            $key,
                                            $fields[$key],
                                            $arProperties,
                                            $arUserTypeFormat,
                                            $itemOptions
                                        );
                                        if ($value == '')
                                        {
                                            $value = weblstAvito::weblstAvito_get_value(
                                                $row,
                                                $key,
                                                $fields[$key],
                                                $arProperties,
                                                $arUserTypeFormat,
                                                $itemOptions
                                            );
                                        }
                                        if ($value != '')
                                            $descriptionFields .= $value."\n";
                                        unset($value);
                                    }
                            }
                        }
                        $picture = (!empty($offer['PICTURE']) ? $offer['PICTURE'] : $row['PICTURE']);
                        if(!empty($imagelink)){
                            $linkimage = "<Image url=\"".$itemOptions['PROTOCOL'].$itemOptions['SITE_NAME'].CHTTP::urnEncode($imagelink, 'utf-8')."\"/>";
                        }
                        if (!empty($picture))
                            $itemsContent .= "<Images><Image url=\"".CHTTP::urnEncode($picture, 'utf-8')."\"/>".$images_list.$linkimage."</Images>";

                        $descrdop = $DOPDESCRIPTION;
                        if($DOPPROPERTIS){
                            foreach ($DOPPROPERTIS as $code=>$id){
                                $value = weblstAvito::weblstAvito_get_value(
                                    $row,
                                    'none',
                                    $id,
                                    $arProperties,
                                    $arUserTypeFormat,
                                    $itemOptions
                                );


                                $descrdop =   str_replace('#'.$code.'#',$value,$descrdop);
                            }

                        }
                        $itemsContent .="<Description><![CDATA[".($offer['DESCRIPTION'] != '' ? $offer['DESCRIPTION'] : $row['DESCRIPTION']).$descriptionFields.$descrdop."]]></Description>\n";
                        unset($picture);


                        $apparel_type = 'Apparel';
                        $CAT = $CATEGORY;
                        $SUBCAT = $CATEGORY_TYPE;
                        $APPA = $APPAREL;
                        if($USE_CATEGORY == 'Y' && !empty($row['UF'][$CATEGORY_SECTION])) {
                            $CAT = $row['UF'][$CATEGORY_SECTION];
                            $SUBCAT = $row['UF'][$SUBCATEGORY_SECTION];
                            $APPA = $row['UF'][$APPAREL_SECTION];

                            if(in_array($row['UF'][$SUBCATEGORY_SECTION],array('10-044','10-045','10-046','10-047','10-048'))) {

                                foreach ($tire as $tr){
                                    $tr_up = mb_strtoupper($tr);
                                    if(!empty($row['UF']['UF_'.$tr_up])) {

                                        $itemsContent .= "<" . $tr . ">" . $row['UF']['UF_'.$tr_up] . "</" . $tr . ">\n";
                                    }
                                }
                            }
                        }

                        $itemsContent .= "<Category>".$CAT."</Category>\n";

                        switch ($CAT){
                            case  GetMessage('WEBLST_AVITO_CAT_VEL'):
                            case  GetMessage('WEBLST_AVITO_MOTO'):
                                $GoodsType = 'VehicleType';
                                break;

                            case  GetMessage('WEBLST_AVITO_ZAP'):
                                $GoodsType = 'TypeId';
                                break;
                            default:
                                $GoodsType = 'GoodsType';
                        }

                        $itemsContent .= "<".$GoodsType.">".$SUBCAT."</".$GoodsType.">\n";


                        if($SUBCAT == GetMessage('WEBLST_AVITO_SUBMOTO')){
                            $apparel_type = 'MotoType';
                        }elseif($SUBCAT == GetMessage('WEBLST_AVITO_TV')){
                            $apparel_type = 'ProductsType';
                        }elseif($SUBCAT == GetMessage('WEBLST_AVITO_STROY') || $SUBCAT == GetMessage('WEBLST_AVITO_STROE')){
                            $apparel_type = 'GoodsSubType';
                        }

                         if(in_array($CAT,array(GetMessage('WEBLST_AVITO_CHILD_SHOES'),GetMessage('WEBLST_AVITO_SHOES'),GetMessage('WEBLST_AVITO_MOTO'))) || in_array($SUBCAT,array(GetMessage('WEBLST_AVITO_TV'),GetMessage('WEBLST_AVITO_STROE'),GetMessage('WEBLST_AVITO_STROY') ))){

                                 $itemsContent .= "<".$apparel_type.">" . $APPA . "</".$apparel_type.">\n";
                         }


                        $itemsContent .= "<Address>".$ADDRESS."</Address>\n";
                        $areas = '';
                        foreach($DisplayAreas as $area){
                            $areas .= "<Area>".$area."</Area>";
                        }
                        $itemsContent .= "<DisplayAreas>".$areas."</DisplayAreas>\n";
                        if( $CONDITION != 'PROPERTY') {
                            if($CONDITION == 'NEW' && ($CATEGORY == GetMessage('WEBLST_AVITO_CHILD_SHOES') ||$CATEGORY  ==  GetMessage('WEBLST_AVITO_TOYS' ))){
                                $CONDITION = $CONDITION.'_CHILD';
                            }
                            $itemsContent .= "<Condition>" . GetMessage('WEBLST_AVITO_CONDITION_' . $CONDITION) . "</Condition>\n";
                        }

                        $itemsContent .= "<adType>".GetMessage('WEBLST_AVITO_ADTYPE_'.$ADTYPE)."</adType>\n";
                        if($ManagerName) {
                            $itemsContent .= "<ManagerName>" . $ManagerName . "</ManagerName>\n";
                        }
                        if($ContactPhone) {
                            $itemsContent .= "<ContactPhone>" . $ContactPhone . "</ContactPhone>\n";
                        }
						$itemsContent .= "<ProductType>Наручные или карманные</ProductType>\n";
                        $itemsContent .= '</Ad>'."\n";
                    }
                    unset($offer);
                }
                elseif (isset($simpleIdsList[$id]) && !empty($row['PRICES']))
                {
                    $row['VAT_ID'] = (int)$row['VAT_ID'];
                    if ($row['VAT_ID'] == 0)
                        $row['VAT_ID'] = $arCatalog['VAT_ID'];

                    $fullPrice = 0;
                    $minPrice = 0;
                    $minPriceCurrency = '';

                    $calculatePrice = CCatalogProduct::GetOptimalPrice(
                        $row['ID'],
                        1,
                        array(2),
                        'N',
                        $row['PRICES'],
                        $site['LID'],
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
                        continue;

                    if($MINPRICE > $minPrice && !empty($MINPRICE)){
                        continue;
                    }
                    if($MAXPRICE < $minPrice && !empty($MAXPRICE)){
                        continue;
                    }
                    $itemsContent .= "<Ad>\n";
                    unset($available);
                    $itemsContent .= "<Id>".$row['ID']."</Id>\n";


                    if($USE_TITLE == 'Y'){

                        $view_title = weblstAvito::weblstAvito_get_value(
                            $row,
                            'none',
                            $TITLE,
                            $arProperties,
                            $arUserTypeFormat,
                            $itemOptions
                        );


                    }else{
						$view_title = $row['NAME'];
					}

					$arSection = getSectionsElement($row["ID"]);

					$view_title = $arSection[0]["NAME"] . " " . $view_title;
					$itemsContent .= "<Title>".weblstAvito::weblstavito_text2xml(TruncateText($view_title, $LOAD), $itemOptions)."</Title>\n";

					/*if($view_title == ''){
                        $itemsContent .= "<Title>".weblstAvito::weblstAvito_text2xml(TruncateText($row['NAME'], $LOAD), $itemOptions)."</Title>\n";

                    } else {
                        $itemsContent .= "<Title>".weblstAvito::weblstavito_text2xml(TruncateText($view_title, $LOAD), $itemOptions)."</Title>\n";

                    }*/


                    $itemsContent .= "<ListingFee>".$ListingFee.'</ListingFee>';

                    $itemsContent .= "<Address>".$ADDRESS."</Address>\n";
                    $areas = '';
                    foreach($DisplayAreas as $area){
                        $areas .= "<Area>".$area."</Area>";
                    }
                    $itemsContent .= "<DisplayAreas>".$areas."</DisplayAreas>\n";
                    if( $CONDITION != 'PROPERTY') {
                        if($CONDITION == 'NEW' && ($CATEGORY == GetMessage('WEBLST_AVITO_CHILD_SHOES') ||$CATEGORY  ==  GetMessage('WEBLST_AVITO_TOYS' ))){
                            $CONDITION = $CONDITION.'_CHILD';
                        }
                        $itemsContent .= "<Condition>" . GetMessage('WEBLST_AVITO_CONDITION_' . $CONDITION) . "</Condition>\n";

                    }

                    if($ManagerName) {
                        $itemsContent .= "<ManagerName>" . $ManagerName . "</ManagerName>\n";
                    }
                    if($ContactPhone) {
                        $itemsContent .= "<ContactPhone>" . $ContactPhone . "</ContactPhone>\n";
                    }
					$itemsContent .= "<ProductType>Наручные или карманные</ProductType>\n";
                    $itemsContent .= "<adType>".GetMessage('WEBLST_AVITO_ADTYPE_'.$ADTYPE)."</adType>\n";
                    $apparel_type = 'Apparel';
                    $CAT = $CATEGORY;
                    $SUBCAT = $CATEGORY_TYPE;
                    $APPA = $APPAREL;

                    if($USE_CATEGORY == 'Y' && !empty($row['UF'][$CATEGORY_SECTION])) {
                        $CAT = $row['UF'][$CATEGORY_SECTION];
                        $SUBCAT = $row['UF'][$SUBCATEGORY_SECTION];
                        $APPA = $row['UF'][$APPAREL_SECTION];

                        if(in_array($row['UF'][$SUBCATEGORY_SECTION],array('10-044','10-045','10-046','10-047','10-048'))) {

                            foreach ($tire as $tr){
                                $tr_up = mb_strtoupper($tr);
                                if(!empty($row['UF']['UF_'.$tr_up])) {

                                    $itemsContent .= "<" . $tr . ">" . $row['UF']['UF_'.$tr_up] . "</" . $tr . ">\n";
                                }
                            }
                        }
                    }
                    $itemsContent .= "<Category>".$CAT."</Category>\n";

                    switch ($CAT){
                        case  GetMessage('WEBLST_AVITO_CAT_VEL'):
                        case  GetMessage('WEBLST_AVITO_MOTO'):
                            $GoodsType = 'VehicleType';
                            break;

                        case  GetMessage('WEBLST_AVITO_ZAP'):
                            $GoodsType = 'TypeId';
                            break;
                        default:
                            $GoodsType = 'GoodsType';
                    }

                    $itemsContent .= "<".$GoodsType.">".$SUBCAT."</".$GoodsType.">\n";


                    if($SUBCAT == GetMessage('WEBLST_AVITO_SUBMOTO')){
                        $apparel_type = 'MotoType';
                    }elseif($SUBCAT == GetMessage('WEBLST_AVITO_TV')){
                        $apparel_type = 'ProductsType';
                    }elseif($SUBCAT == GetMessage('WEBLST_AVITO_STROY') || $SUBCAT == GetMessage('WEBLST_AVITO_STROE')){
                        $apparel_type = 'GoodsSubType';
                    }

                    if(in_array($CAT,array(GetMessage('WEBLST_AVITO_CHILD_SHOES'),GetMessage('WEBLST_AVITO_SHOES'),GetMessage('WEBLST_AVITO_MOTO'))) || in_array($SUBCAT,array(GetMessage('WEBLST_AVITO_TV'),GetMessage('WEBLST_AVITO_STROY') ,GetMessage('WEBLST_AVITO_STROE')))){

                        $itemsContent .= "<".$apparel_type.">" . $APPA . "</".$apparel_type.">\n";
                    }

					//$minPrice = $minPrice * 0.9;
					//$minPrice = round($minPrice, -1);
					if($arResult["MARGIN"] > 0){
						$minPrice = $minPrice * $arResult["MARGIN"];
						$minPrice = round($minPrice, -1);
					}
					$itemsContent .= "<Price>" . $minPrice . "</Price>\n";
					//prent($last);
					//prent($minPrice);
                    /*if($USE_COST != 'Y') {
                        if ($minPrice < $fullPrice) {
                            // $minPrice = number_format($minPrice,2,'.','');

                            // $fullPrice = number_format($fullPrice,2,'.','');
                            $itemsContent .= "<Price>" . $minPrice . "</Price>\n";

                        } else {
                            //$minPrice = number_format($minPrice,2,'.','');

                            $itemsContent .= "<Price>" . $minPrice . "</Price>\n";
                        }
                    }*/


                    $y = 0;

                    $images_list = '';
                    $descriptionFields = '';

                    foreach ($arYandexFields as $key)
                    {
                        switch ($key)
                        {
                            case 'VideoURL':

                                if(!empty($VIDEO)) {

                                    $value = weblstAvito::weblstAvito_get_value(
                                        $row,
                                        $key,
                                        $VIDEO,
                                        $arProperties,
                                        $arUserTypeFormat,
                                        $itemOptions
                                    );

                                    if ($value != '')
                                        $itemsContent .= $value."\n";
                                    unset($value);


                                }
                                break;
                            case 'Image':


                            if(!empty($IMAGES)) {
                                $value = weblstAvito::weblstAvito_get_value(
                                    $row,
                                    $key,
                                    $IMAGES,
                                    $arProperties,
                                    $arUserTypeFormat,
                                    $itemOptions
                                );

                                if ($value != '')
                                    $images_list .= $value . "\n";
                                unset($value);
                            }
                            break;

                            case 'RimDiameter':
                            case 'RimType':
                            case 'TireType':
                            case 'TireSectionWidth':
                            case 'TireAspectRatio':
                            case 'RimWidth':
                            case 'RimBolts':
                            case 'RimBoltsDiameter':
                            case 'RimOffset':
                            case 'RunFlat':
                            case 'Homologation':
                            case 'Brand':
                            case 'SpeedIndex':
                            case 'LoadIndex':


                            if(in_array($CATEGORY_TYPE,array('10-044','10-045','10-046','10-047','10-048'))) {
                                $value = weblstAvito::weblstAvito_get_value(
                                    $row,
                                    $key,
                                    ${"$key"},
                                    $arProperties,
                                    $arUserTypeFormat,
                                    $itemOptions
                                );

                                if($key == 'TireType' && $value != ''){
                                    $value =   str_ireplace($findSH,$replaceSH,$value);
                                }

                                if ($value != '')
                                    $itemsContent .= $value . "\n";
                                unset($value);
                            }
                            break;
                            case 'Model':
                                if(in_array($CATEGORY_TYPE,array('10-044','10-045','10-046','10-047','10-048')) || $CATEGORY== GetMessage('CAT_WERBLST_MOTO')) {



                                        $value = weblstAvito::weblstAvito_get_value(
                                            $row,
                                            $key,
                                            ${"$key"},
                                            $arProperties,
                                            $arUserTypeFormat,
                                            $itemOptions
                                        );


                                    if ($value != '')
                                        $itemsContent .= $value . "\n";
                                    unset($value);

                                }
                                break;
                                break;
                            case 'Make':
                            case 'Type':
                            case 'Year':
                            case 'Power':
                            case 'EngineCapacity':
                                if($CATEGORY== GetMessage('CAT_WERBLST_MOTO')) {


                                        $value = weblstAvito::weblstAvito_get_value(
                                            $row,
                                            $key,
                                            ${"$key"},
                                            $arProperties,
                                            $arUserTypeFormat,
                                            $itemOptions
                                        );


                                    if ($value != '')
                                        $itemsContent .= $value . "\n";
                                    unset($value);

                                }
                                break;
                            case 'Condition':
                                if($CONDITION && $CONDITION == 'PROPERTY'){

                                    $value = weblstAvito::weblstAvito_get_value(
                                        $row,
                                        $key,
                                        $PROPERTY_CONDITION,
                                        $arProperties,
                                        $arUserTypeFormat,
                                        $itemOptions
                                    );

                                    if ($value != '')
                                        $itemsContent .= $value."\n";
                                    unset($value);


                                }
                                break;
                            case 'Price':
                                if($COST && $USE_COST == 'Y'){

                                    $value = weblstAvito::weblstAvito_get_value(
                                        $row,
                                        $key,
                                        $COST,
                                        $arProperties,
                                        $arUserTypeFormat,
                                        $itemOptions
                                    );

                                    if ($value != '')
                                        $itemsContent .= $value."\n";
                                    unset($value);


                                }
                                break;
                            case 'Size':

                                 if($CATEGORY != GetMessage('WEBLST_AVITO_CHILD_SHOES') || $CATEGORY != GetMessage('WEBLST_AVITO_SHOES')){

                                    $value = weblstAvito::weblstAvito_get_value(
                                        $row,
                                        $key,
                                        $SIZE,
                                        $arProperties,
                                        $arUserTypeFormat,
                                        $itemOptions
                                    );

                                    if ($value != '')
                                        $itemsContent .= $value."\n";
                                    unset($value);


                                }
                            break;
                            case 'param':
                                if ($parametricFieldsExist)
                                {
                                    foreach ($parametricFields as $paramKey => $prop_id)
                                    {
                                        $value = weblstAvito::weblstAvito_get_value(
                                            $row,
                                            'PARAM_'.$paramKey,
                                            $prop_id,
                                            $arProperties,
                                            $arUserTypeFormat,
                                            $itemOptions
                                        );
                                        if ($value != '')
                                            $descriptionFields .= $value."\n";
                                        unset($value);
                                    }

                                    unset($paramKey, $prop_id);
                                }
                                break;

                            default:

                                if ($fieldsExist && isset($fields[$key]))
                                {
                                    $value = weblstAvito::weblstAvito_get_value(
                                        $row,
                                        $key,
                                        $fields[$key],
                                        $arProperties,
                                        $arUserTypeFormat,
                                        $itemOptions
                                    );
                                    if ($value != '')
                                        //$itemsContent .= $value."\n";
                                    unset($value);
                                }
                        }
                    }
                    $descrdop = $DOPDESCRIPTION;

                    if($DOPPROPERTIS){
                        foreach ($DOPPROPERTIS as $code=>$id){
                            $value = weblstAvito::weblstAvito_get_value(
                                $row,
                                'none',
                                $id,
                                $arProperties,
                                $arUserTypeFormat,
                                $itemOptions
                            );


                            $descrdop =   str_replace('#'.$code.'#',$value,$descrdop);
                        }

                    }
                    if(!empty($imagelink)){
                        $linkimage = "<Image url=\"".$itemOptions['PROTOCOL'].$itemOptions['SITE_NAME'].CHTTP::urnEncode($imagelink, 'utf-8')."\"/>";
                    }
				   //$itemsContent .= "<Images><Image url=\"".CHTTP::urnEncode($row['PICTURE'], 'utf-8')."\"/>".$images_list.$linkimage."</Images>";
                    if (!empty($row['PICTURE']))
						$itemsContent .= "<Images>".$images_list.$linkimage."</Images>";

					/*1) Нужно перед нашим описанием вставить:

					Оригинальные часы от магазина TEMPUS

					Работаем с 2012 года, 12 тысяч отзывов на Яндекс Маркет, рейтинг 4.9/5

					Работаем с Авито Доставкой

					Гарантия 2 года

					Бесплатная доставка по Москве от 3 000 рублей, платная доставка 300р
					Отправляем заказы Почтой, СДЭК и Боксбери.


					а затем уже Оригинальные мужские часы ....

					2) В название добавить "Наручные часы".
					Получится Наручные часы Casio....*/


					$txt = "Оригинальные часы от магазина TEMPUS\r\nРаботаем с 2012 года, 12 тысяч отзывов на Яндекс Маркет, рейтинг 4.9/5\r\nГарантия 2 года\r\nБесплатная доставка по Москве от 3 000 рублей, платная доставка 300р\r\nРаботаем с Авито Доставкой\r\n\r\n";

					$txt .= "Оригинальные " . mb_strtolower($row["PROPERTIES"][126]["VALUE"][0]) . " " . mb_strtolower($arSection[0]["NAME"]) . " {$arSection[1]["NAME"]} {$arSection[2]["NAME"]} {$row["PROPERTIES"][123]["VALUE"]} \r\n ";


					$itemsContent .= "<Description><![CDATA[".$txt.$row['DESCRIPTION'].$descriptionFields.$descrdop."]]></Description>\n";
                    $itemsContent .= "</Ad>\n";
                }

                unset($row);

                if ($MAX_EXECUTION_TIME > 0 && (getmicrotime() - START_EXEC_TIME) >= $MAX_EXECUTION_TIME)
                    break;
            }
            unset($id);

            \CCatalogDiscount::ClearDiscountCache(array(
                'PRODUCT' => true,
                'SECTIONS' => true,
                'SECTION_CHAINS' => true,
                'PROPERTIES' => true
            ));
            /** @noinspection PhpDeprecationInspection */
            \CCatalogProduct::ClearCache();
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
        $process = true;
        $content = '';




        $items = file_get_contents($_SERVER["DOCUMENT_ROOT"].$itemFileName);
        if ($items === false)
        {
            $arRunErrors[] = GetMessage('YANDEX_STEP_ERR_DATA_FILE_NOT_READ');
            $process = false;
        }

        if ($process)
        {
            $content .= $items;
            unset($items);
            $content .= "</Ads>\n";

            if (file_put_contents($_SERVER["DOCUMENT_ROOT"].$sectionFileName, $content, FILE_APPEND) === false)
            {
                $arRunErrors[] = str_replace('#FILE#', $sectionFileName, GetMessage('YANDEX_ERR_SETUP_FILE_WRITE'));
                $process = false;
            }
        }
        if ($process)
        {
            unlink($_SERVER["DOCUMENT_ROOT"].$itemFileName);

            if (file_exists($_SERVER["DOCUMENT_ROOT"].$SETUP_FILE_NAME))
            {
                if (!unlink($_SERVER["DOCUMENT_ROOT"].$SETUP_FILE_NAME))
                {
                    $arRunErrors[] = str_replace('#FILE#', $SETUP_FILE_NAME, GetMessage('BX_CATALOG_EXPORT_YANDEX_ERR_UNLINK_FILE'));
                    $process = false;
                }
            }
        }
        if ($process)
        {
            if (!rename($_SERVER["DOCUMENT_ROOT"].$sectionFileName, $_SERVER["DOCUMENT_ROOT"].$SETUP_FILE_NAME))
            {
                $arRunErrors[] = str_replace('#FILE#', $sectionFileName, GetMessage('BX_CATALOG_EXPORT_YANDEX_ERR_UNLINK_FILE'));
            }
        }
        unset($process);
    }
}

CCatalogDiscountSave::Enable();
if ($saleIncluded)
    Sale\DiscountCouponsManager::unFreezeCouponStorage();

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
