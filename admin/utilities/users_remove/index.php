<?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/header.php");
$APPLICATION->SetPageProperty("description", "Удаление пользователей");
$APPLICATION->SetPageProperty("title", "Удаление пользователей");
?>
<?if(!CModule::IncludeModule('panel.manager'))return;?>
<div id="settings-main" class="col-sm-12 row">
<?$APPLICATION->IncludeComponent(
    "admin:users.remove",
    ".default",
    [
        "CHUNK_SIZE" => 100,
    ]
);?>
</div>
<?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/footer.php");?>