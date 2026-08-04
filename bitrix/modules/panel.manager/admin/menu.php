<?php
AddEventHandler("main", "OnBuildGlobalMenu", "BuildMarketConfigMenu");

function BuildMarketConfigMenu(&$aGlobalMenu, &$aModuleMenu)
{
    $aModuleMenu[] = [
        "parent_menu" => "global_menu_store",
        "sort" => 100,
        "text" => "Управление типами цен",
        "title" => "Управление типами цен и приоритетами складов",
        "url" => "/bitrix/admin/market_config_admin.php",
        "icon" => "sale_menu_icon",
        "page_icon" => "sale_page_icon",
        "items_id" => "market_config_menu",
        "more_url" => [],
    ];
}