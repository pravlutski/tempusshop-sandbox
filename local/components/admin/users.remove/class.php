<?php
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die();

use Bitrix\Main\Loader;
use Bitrix\Main\UserTable;

class AdminUsersRemove extends CBitrixComponent
{
    public function executeComponent()
    {
        global $USER;
        
        if (!$USER->IsAdmin()) {
            ShowError("Доступ запрещен");
            return;
        }
        
        $this->includeComponentTemplate();
    }
}