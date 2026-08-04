<?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/header.php");?>
<?if(!CModule::IncludeModule('panel.manager'))return;?>
<script src="/bitrix/templates/admin_panel/js/clipboard.min.js"></script>
<div id="settings-main" class="col-sm-12 row">
<?php
$APPLICATION->IncludeComponent(
    'tempus:rabbitmq.status',
    '',
    [
        'HOST' => 'localhost',
        'USER' => 'bitrix_user',
        'PASSWORD' => 'WOwgsZB46GR2ibL',
        'VHOST' => 'bitrix_sync',
        'TIMEOUT' => 3
    ]
);
?>
</div>
<?require($_SERVER["DOCUMENT_ROOT"]."/bitrix/footer.php");?>