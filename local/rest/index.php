<?php

require_once($_SERVER['DOCUMENT_ROOT'] . "/bitrix/modules/main/include/prolog_admin.php"); ?>

<div class="adm-workarea">
<?php $APPLICATION->IncludeComponent(
    "bitrix:rest.hook",
    ".default",
    [
        "SEF_MODE" => "Y",
        "SEF_FOLDER" => "/local/rest/",
        "COMPONENT_TEMPLATE" => ".default",
        "SEF_URL_TEMPLATES" => [
            "list" => "",
            "event_list" => "event/",
            "event_edit" => "event/#id#/",
            "ap_list" => "ap/",
            "ap_edit" => "ap/#id#/",
        ]
    ],
    false
); ?>

	<a href="javascript:;" class="adm-btn adm-btn-green"
		onclick="BX.PopupMenu.show('rest_hook_menu', this, [{
			'href':'/local/rest/event/0/',
			'text':'Исходящий вебхук'
		},{
			'href':'/local/rest/ap/0/',
			'text':'Входящий вебхук'
		}])">
            Добавить вебхук
</a>
</div>

<?php require($_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/main/include/epilog_admin.php");