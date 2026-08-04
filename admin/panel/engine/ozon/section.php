<?php
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
    die();
}

use Bitrix\Main\Context;
use Imedia\Main\Helper\Catalog\Section;

/** @var array $arParams */
/** @var array $arResult */
/** @global CMain $APPLICATION */
/** @global CUser $USER */
/** @global CDatabase $DB */
/** @var CBitrixComponentTemplate $this */
/** @var string $templateName */
/** @var string $templateFile */
/** @var string $templateFolder */
/** @var string $componentPath */
/** @var CBitrixComponent $component */

$this->setFrameMode(true);

$APPLICATION->SetPageProperty('classes--page', 'catalog-page');
$APPLICATION->SetPageProperty('classes--page-top', 'has-subtext');
$APPLICATION->SetPageProperty('classes--title', 'h1');
// $APPLICATION->SetPageProperty('NOT_SHOW_NAV_CHAIN', 'Y');

$request = Context::getCurrent()->getRequest();


$isPagenPage = false;
$queryList = $request->getQueryList()->toArray();
foreach ($queryList as $key => $value) {

    if (strpos($key, 'PAGEN_') === 0) {
        $isPagenPage = true;
        break;
    }

}

$isFilterPage = strpos($request->getRequestedPageDirectory(), '/filter/');

$hideDescription = $isPagenPage || $isFilterPage;

$arCurSection = Section::getCurrent(
    [
        'SECTION_CODE' => $arResult['VARIABLES']['SECTION_CODE'],
        'SECTION_ID' => $arResult['VARIABLES']['SECTION_ID'],
    ],
    $arParams['IBLOCK_ID']
);

$filterNameCatalogBanner = 'arFilterCatalogBanner';
if ($arCurSection['id'] > 0) {
    $GLOBALS[$filterNameCatalogBanner] = [
        [
            'LOGIC' => 'OR',
            ['=SECTION.VALUE' => $arCurSection['id']],
            ['=SECTION.VALUE' => false]
        ]
    ];
} else {
    $GLOBALS[$filterNameCatalogBanner] = [
        '=SECTION.VALUE' => false
    ];
}

$arSort = Section::getSort();
$arParams = array_merge($arParams, $arSort['PARAMS']);

$APPLICATION->showViewContent('breadcrumbs');

$sefRule = $arResult['FOLDER'];
if ($arCurSection['id'] > 0) {
    $sefRule .= $arResult['URL_TEMPLATES']['smart_filter'];
} elseif ($arResult['VARIABLES']['SECTION_VIRTUAL']) {
    $sefRule .= $arResult['URL_TEMPLATES']['smart_filter_' . $arResult['VARIABLES']['SECTION_VIRTUAL']];
} elseif ($arResult['VARIABLES']['QUERY']) {
    $sefRule .= $arResult['URL_TEMPLATES']['smart_filter_search'] . '?q=' . $arResult['VARIABLES']['QUERY'];
} else {
    $sefRule .= $arResult['URL_TEMPLATES']['smart_filter_root'];
}

global $USER;
//if($USER->isAdmin()){
if($USER->isAdmin()){
	$comp = 'tempus:catalog.smart.filter';
}else{
	$comp = 'imedia:catalog.smart.filter';
}
if($_REQUEST["DEBUG"]) {
    echo 1;
} else {
    $frame =new \Bitrix\Main\Page\FrameBuffered("smart_filter_frame");
    $frame->begin();
    global $smartPreFilter;
    $smartPreFilter = [
        '!PROPERTY_AVAILABILITY_BY' => 'Нет в наличии',
    ];
    $APPLICATION->IncludeComponent(
        $comp,
        '',
        array(
            'IBLOCK_TYPE' => $arParams['IBLOCK_TYPE'],
            'IBLOCK_ID' => $arParams['IBLOCK_ID'],
            'SECTION_ID' => ($arCurSection['id'] > 0) ? $arCurSection['id'] : 0,
            'SHOW_ALL_WO_SECTION' => ($arCurSection['id'] > 0) ? 'N' : 'Y',
            'FILTER_NAME' => $arParams['FILTER_NAME'],
            "PREFILTER_NAME" => "smartPreFilter",
            'PRICE_CODE' => $arParams['PRICE_CODE'],
            'CACHE_TYPE' => 'A',
            'CACHE_TIME' => '360000000',
            'CACHE_GROUPS' => $arParams['CACHE_GROUPS'],
            'SAVE_IN_SESSION' => 'N',
            'FILTER_VIEW_MODE' => $arParams['FILTER_VIEW_MODE'],
            'XML_EXPORT' => 'N',
            'SECTION_TITLE' => 'NAME',
            'SECTION_DESCRIPTION' => 'DESCRIPTION',
            'HIDE_NOT_AVAILABLE' => $arParams['HIDE_NOT_AVAILABLE'],
            'TEMPLATE_THEME' => $arParams['TEMPLATE_THEME'],
            'CONVERT_CURRENCY' => $arParams['CONVERT_CURRENCY'],
            'CURRENCY_ID' => $arParams['CURRENCY_ID'],
            'SEF_MODE' => $arParams['SEF_MODE'],
            'SEF_RULE' => $sefRule,
            'SMART_FILTER_PATH' => $arResult['VARIABLES']['SMART_FILTER_PATH'],
            'PAGER_PARAMS_NAME' => $arParams['PAGER_PARAMS_NAME'],
            'INSTANT_RELOAD' => $arParams['INSTANT_RELOAD'],
            'PREFILTER_NAME' => $arParams['PREFILTER_NAME'],
            'SORT_LIST' => $arSort['LIST'],
            'SORT_SELECTED' => $arSort['SELECTED'],
        ),
        $component,
        ['HIDE_ICONS' => 'N']
    );

    $frame->end();
}

if ($arCurSection['id'] > 0) {
    if ($arCurSection['id'] != 14) {
        $APPLICATION->IncludeComponent(
            "bitrix:catalog.section.list",
            ".default",
            array(
                "COMPONENT_TEMPLATE" => ".default",
                "IBLOCK_TYPE" => "catalog",
                "IBLOCK_ID" => "12",
                "SECTION_ID" => $arCurSection['id'],
                "SECTION_CODE" => "",
                "COUNT_ELEMENTS" => "Y",
                "COUNT_ELEMENTS_FILTER" => "CNT_ACTIVE",
                "ADDITIONAL_COUNT_ELEMENTS_FILTER" => "additionalCountFilter",
                "HIDE_SECTIONS_WITH_ZERO_COUNT_ELEMENTS" => "N",
                "TOP_DEPTH" => "2",
                "SECTION_FIELDS" => array(
                    0 => "",
                    1 => "",
                ),
                "SECTION_USER_FIELDS" => array(
                    0 => "",
                    1 => "",
                ),
                "FILTER_NAME" => "sectionsFilter",
                "VIEW_MODE" => "LINE",
                "SHOW_PARENT_NAME" => "Y",
                "SECTION_URL" => "",
                "CACHE_TYPE" => "A",
                "CACHE_TIME" => "36000000",
                "CACHE_GROUPS" => "Y",
                "CACHE_FILTER" => "N",
                "ADD_SECTIONS_CHAIN" => "Y",
                "COMPOSITE_FRAME_MODE" => "A",
                "COMPOSITE_FRAME_TYPE" => "AUTO"
            ),
            false
        );
    }
}


//$APPLICATION->IncludeComponent(
//    'imedia:section.list',
//    'catalog',
//    [
//        'IBLOCK_ID' => $arParams['IBLOCK_ID']
//    ],
//    false,
//    ['HIDE_ICONS' => 'Y']
//);

if ($_REQUEST["DEBUG"]) {
    echo 1;
} else {
	//prent($GLOBALS[$arParams['FILTER_NAME']]);
	if($USER->getID() == 12677){
		//$arResult['VARIABLES']['SECTION_ID'] = 1;
	}

    $APPLICATION->IncludeComponent(
        'imedia:catalog.section',
        '',
        array(
            'IBLOCK_TYPE' => $arParams['IBLOCK_TYPE'],
            'IBLOCK_ID' => $arParams['IBLOCK_ID'],
            'ELEMENT_SORT_FIELD' => $arParams['ELEMENT_SORT_FIELD'],
            'ELEMENT_SORT_ORDER' => $arParams['ELEMENT_SORT_ORDER'],
            'ELEMENT_SORT_FIELD2' => $arParams['ELEMENT_SORT_FIELD2'],
            'ELEMENT_SORT_ORDER2' => $arParams['ELEMENT_SORT_ORDER2'],
            'PROPERTY_CODE' => $arParams['LIST_PROPERTY_CODE'] ?? [],
            'PROPERTY_CODE_MOBILE' => $arParams['LIST_PROPERTY_CODE_MOBILE'],
            'INCLUDE_SUBSECTIONS' => $arParams['INCLUDE_SUBSECTIONS'],
            'BASKET_URL' => $arParams['BASKET_URL'],
            'ACTION_VARIABLE' => $arParams['ACTION_VARIABLE'],
            'PRODUCT_ID_VARIABLE' => $arParams['PRODUCT_ID_VARIABLE'],
            'SECTION_ID_VARIABLE' => $arParams['SECTION_ID_VARIABLE'],
            'PRODUCT_QUANTITY_VARIABLE' => $arParams['PRODUCT_QUANTITY_VARIABLE'],
            'PRODUCT_PROPS_VARIABLE' => $arParams['PRODUCT_PROPS_VARIABLE'],
            'FILTER_NAME' => $arParams['FILTER_NAME'],
            'CACHE_TYPE' => "A",
            'CACHE_TIME' => 36000000,
            'CACHE_FILTER' => $arParams['CACHE_FILTER'],
            'CACHE_GROUPS' => $arParams['CACHE_GROUPS'],
            'DISPLAY_COMPARE' => $arParams['USE_COMPARE'],
            'PAGE_ELEMENT_COUNT' => $arParams['PAGE_ELEMENT_COUNT'],
            'LINE_ELEMENT_COUNT' => $arParams['LINE_ELEMENT_COUNT'],
            'PRICE_CODE' => $arParams['~PRICE_CODE'],
            'USE_PRICE_COUNT' => $arParams['USE_PRICE_COUNT'],
            'SHOW_PRICE_COUNT' => $arParams['SHOW_PRICE_COUNT'],

            'PRICE_VAT_INCLUDE' => $arParams['PRICE_VAT_INCLUDE'],
            'USE_PRODUCT_QUANTITY' => $arParams['USE_PRODUCT_QUANTITY'],
            'ADD_PROPERTIES_TO_BASKET' => ($arParams['ADD_PROPERTIES_TO_BASKET'] ?? ''),
            'PARTIAL_PRODUCT_PROPERTIES' => ($arParams['PARTIAL_PRODUCT_PROPERTIES'] ?? ''),
            'PRODUCT_PROPERTIES' => ($arParams['PRODUCT_PROPERTIES'] ?? []),

            'DISPLAY_TOP_PAGER' => $arParams['DISPLAY_TOP_PAGER'],
            'DISPLAY_BOTTOM_PAGER' => $arParams['DISPLAY_BOTTOM_PAGER'],
            'PAGER_TITLE' => $arParams['PAGER_TITLE'],
            'PAGER_SHOW_ALWAYS' => $arParams['PAGER_SHOW_ALWAYS'],
            'PAGER_TEMPLATE' => $arParams['PAGER_TEMPLATE'],
            'PAGER_DESC_NUMBERING' => $arParams['PAGER_DESC_NUMBERING'],
            'PAGER_DESC_NUMBERING_CACHE_TIME' => $arParams['PAGER_DESC_NUMBERING_CACHE_TIME'],
            'PAGER_SHOW_ALL' => $arParams['PAGER_SHOW_ALL'],
            'PAGER_BASE_LINK_ENABLE' => $arParams['PAGER_BASE_LINK_ENABLE'],
            'PAGER_BASE_LINK' => $arParams['PAGER_BASE_LINK'],
            'PAGER_PARAMS_NAME' => $arParams['PAGER_PARAMS_NAME'],
            'LAZY_LOAD' => $arParams['LAZY_LOAD'],
            'MESS_BTN_LAZY_LOAD' => $arParams['~MESS_BTN_LAZY_LOAD'],
            'LOAD_ON_SCROLL' => $arParams['LOAD_ON_SCROLL'],

            'OFFERS_CART_PROPERTIES' => ($arParams['OFFERS_CART_PROPERTIES'] ?? []),
            'OFFERS_FIELD_CODE' => $arParams['LIST_OFFERS_FIELD_CODE'],
            'OFFERS_PROPERTY_CODE' => ($arParams['LIST_OFFERS_PROPERTY_CODE'] ?? []),
            'OFFERS_SORT_FIELD' => $arParams['OFFERS_SORT_FIELD'],
            'OFFERS_SORT_ORDER' => $arParams['OFFERS_SORT_ORDER'],
            'OFFERS_SORT_FIELD2' => $arParams['OFFERS_SORT_FIELD2'],
            'OFFERS_SORT_ORDER2' => $arParams['OFFERS_SORT_ORDER2'],
            'OFFERS_LIMIT' => ($arParams['LIST_OFFERS_LIMIT'] ?? 0),

            'SECTION_ID' => $arResult['VARIABLES']['SECTION_ID'],
            //'SECTION_ID' => !empty($GLOBALS[$arParams['FILTER_NAME']]['SECTION_ID']) ? '' : $arResult["VARIABLES"]["SECTION_ID"],

            //'SECTION_CODE' => $arResult['VARIABLES']['SECTION_CODE'],
            'SECTION_CODE' => !empty($GLOBALS[$arParams['FILTER_NAME']]['SECTION_ID']) ? '' : $arResult["VARIABLES"]["SECTION_CODE"],
            'SHOW_ALL_WO_SECTION' => ($arCurSection['id'] > 0) ? 'N' : 'Y',
            //'SHOW_ALL_WO_SECTION' => 'Y',
            'SECTION_URL' => '',
            'DETAIL_URL' => '',
            'USE_MAIN_ELEMENT_SECTION' => $arParams['USE_MAIN_ELEMENT_SECTION'],
            'CONVERT_CURRENCY' => $arParams['CONVERT_CURRENCY'],
            'CURRENCY_ID' => $arParams['CURRENCY_ID'],
            'HIDE_NOT_AVAILABLE' => $arParams['HIDE_NOT_AVAILABLE'],
            'HIDE_NOT_AVAILABLE_OFFERS' => $arParams['HIDE_NOT_AVAILABLE_OFFERS'],

            'LABEL_PROP' => $arParams['LABEL_PROP'],
            'LABEL_PROP_MOBILE' => $arParams['LABEL_PROP_MOBILE'],
            'LABEL_PROP_POSITION' => $arParams['LABEL_PROP_POSITION'],
            'ADD_PICT_PROP' => $arParams['ADD_PICT_PROP'],
            'PRODUCT_DISPLAY_MODE' => $arParams['PRODUCT_DISPLAY_MODE'],
            'PRODUCT_BLOCKS_ORDER' => $arParams['LIST_PRODUCT_BLOCKS_ORDER'],
            'PRODUCT_ROW_VARIANTS' => $arParams['LIST_PRODUCT_ROW_VARIANTS'],
            'ENLARGE_PRODUCT' => $arParams['LIST_ENLARGE_PRODUCT'],
            'ENLARGE_PROP' => ($arParams['LIST_ENLARGE_PROP'] ?? ''),
            'SHOW_SLIDER' => $arParams['LIST_SHOW_SLIDER'],
            'SLIDER_INTERVAL' => ($arParams['LIST_SLIDER_INTERVAL'] ?? ''),
            'SLIDER_PROGRESS' => ($arParams['LIST_SLIDER_PROGRESS'] ?? ''),

            'OFFER_ADD_PICT_PROP' => $arParams['OFFER_ADD_PICT_PROP'],
            'OFFER_TREE_PROPS' => ($arParams['OFFER_TREE_PROPS'] ?? []),
            'PRODUCT_SUBSCRIPTION' => $arParams['PRODUCT_SUBSCRIPTION'],
            'SHOW_DISCOUNT_PERCENT' => $arParams['SHOW_DISCOUNT_PERCENT'],
            'DISCOUNT_PERCENT_POSITION' => $arParams['DISCOUNT_PERCENT_POSITION'],
            'SHOW_OLD_PRICE' => $arParams['SHOW_OLD_PRICE'],
            'SHOW_MAX_QUANTITY' => $arParams['SHOW_MAX_QUANTITY'],
            'MESS_SHOW_MAX_QUANTITY' => ($arParams['~MESS_SHOW_MAX_QUANTITY'] ?? ''),
            'RELATIVE_QUANTITY_FACTOR' => ($arParams['RELATIVE_QUANTITY_FACTOR'] ?? ''),
            'MESS_RELATIVE_QUANTITY_MANY' => ($arParams['~MESS_RELATIVE_QUANTITY_MANY'] ?? ''),
            'MESS_RELATIVE_QUANTITY_FEW' => ($arParams['~MESS_RELATIVE_QUANTITY_FEW'] ?? ''),
            'MESS_BTN_BUY' => ($arParams['~MESS_BTN_BUY'] ?? ''),
            'MESS_BTN_ADD_TO_BASKET' => ($arParams['~MESS_BTN_ADD_TO_BASKET'] ?? ''),
            'MESS_BTN_SUBSCRIBE' => ($arParams['~MESS_BTN_SUBSCRIBE'] ?? ''),
            'MESS_BTN_DETAIL' => ($arParams['~MESS_BTN_DETAIL'] ?? ''),
            'MESS_NOT_AVAILABLE' => ($arParams['~MESS_NOT_AVAILABLE'] ?? ''),
            'MESS_BTN_COMPARE' => ($arParams['~MESS_BTN_COMPARE'] ?? ''),

            'USE_ENHANCED_ECOMMERCE' => ($arParams['USE_ENHANCED_ECOMMERCE'] ?? ''),
            'DATA_LAYER_NAME' => ($arParams['DATA_LAYER_NAME'] ?? ''),
            'BRAND_PROPERTY' => ($arParams['BRAND_PROPERTY'] ?? ''),

            'TEMPLATE_THEME' => ($arParams['TEMPLATE_THEME'] ?? ''),
            'ADD_SECTIONS_CHAIN' => 'Y',
            'ADD_TO_BASKET_ACTION' => '',
            'SHOW_CLOSE_POPUP' => ($arParams['COMMON_SHOW_CLOSE_POPUP'] ?? ''),
            'COMPARE_PATH' => $arResult['FOLDER'] . $arResult['URL_TEMPLATES']['compare'],
            'COMPARE_NAME' => $arParams['COMPARE_NAME'],
            'USE_COMPARE_LIST' => 'Y',
            'BACKGROUND_IMAGE' => ($arParams['SECTION_BACKGROUND_IMAGE'] ?? ''),
            'COMPATIBLE_MODE' => ($arParams['COMPATIBLE_MODE'] ?? ''),
            'DISABLE_INIT_JS_IN_COMPONENT' => ($arParams['DISABLE_INIT_JS_IN_COMPONENT'] ?? ''),

            'DISCOUNT_OFFERS_PRIORITY' => $arParams['DISCOUNT_OFFERS_PRIORITY'],

            'META_KEYWORDS' => $arParams['LIST_META_KEYWORDS'],
            'META_DESCRIPTION' => $arParams['LIST_META_DESCRIPTION'],
            'BROWSER_TITLE' => $arParams['LIST_BROWSER_TITLE'],
            'SET_LAST_MODIFIED' => $arParams['SET_LAST_MODIFIED'],
            'SET_TITLE' => $arParams['SET_TITLE'],
            'MESSAGE_404' => $arParams['~MESSAGE_404'],
            'SET_STATUS_404' => $arParams['SET_STATUS_404'],
            'SHOW_404' => $arParams['SHOW_404'],
            'FILE_404' => $arParams['FILE_404'],
            'HIDE_SECTION_DESCRIPTION' => ($hideDescription) ? 'Y' : 'N',

            'FILTER_NAME_CATALOG_BANNER' => $filterNameCatalogBanner,
            'SECTION_VIRTUAL' => $arResult['VARIABLES']['SECTION_VIRTUAL'],
            'SECTION_VIRTUAL_PATH' => $arResult['VARIABLES']['SECTION_VIRTUAL_PATH'],
            'SECTION_USER_FIELDS' => ['UF_DESCRIPTION_TOP']
        ),
        $component,
        ['HIDE_ICONS' => 'Y']
    );
}

//$APPLICATION->IncludeComponent(
//    'imedia:viewed.product.list',
//    '.default',
//    [
//        'SIZE' => 12
//    ],
//    false,
//    ['HIDE_ICONS' => true]
//);

//$APPLICATION->IncludeComponent(
//    'imedia:social.feed',
//    '.default',
//    [],
//    false,
//    ['HIDE_ICONS' => true]
//);
