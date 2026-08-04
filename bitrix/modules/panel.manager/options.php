<?
IncludeModuleLangFile($_SERVER["DOCUMENT_ROOT"] . BX_ROOT . "/modules/main/options.php");
IncludeModuleLangFile(__FILE__);

$arPriceID = ["RU","BY","PL","YA","OS","WB","WBTL","AV", "SB", "KZ", "OZKZ","OZTI"];
$arTabPrice = [];
foreach($arPriceID as $ID){
	$arTabPrice["PRICELIST_MARGIN_{$ID}"] = array(GetMessage("PM_OPTION_PRICELIST_MARGIN") . " " . $ID, Array("text", "10"));
	$arTabPrice["PRICELIST_AUTO_SET_{$ID}"] = array(GetMessage("PM_OPTION_PRICELIST_AUTO_SET") . " " . $ID, Array("checkbox", "10"));
	$arTabPrice["PRICELIST_REQUIRED_RRC_{$ID}"] = array(GetMessage("PM_OPTION_REQUIRED_RRC") . " " . $ID, Array("checkbox", "10"));
	$arTabPrice["PRICEUPDATE_TAKE_MARKET_PRICES_{$ID}"] = array(GetMessage("PM_OPTION_TAKE_MARKET_PRICES") . " " . $ID, Array("checkbox", "10"));
	$arTabPrice["PRICELIST_REQUIRED_MARKET_{$ID}"] = array(GetMessage("PM_OPTION_REQUIRED_MARKET") . " " . $ID, Array("checkbox", "10"));
	$arTabPrice["PRICEUPDATE_REV_MIN_{$ID}"] = array(GetMessage("PM_OPTION_PRICEUPDATE_REV_MIN") . " " . $ID, Array("text", "10"));
	$arTabPrice["PRICEUPDATE_MIN_PER_{$ID}"] = array(GetMessage("PM_OPTION_PRICEUPDATE_MIN_PER") . " " . $ID, Array("text", "10"));

	$arTabPrice["PRICEUPDATE_APPLY_RRP_{$ID}"] = array(GetMessage("PM_OPTION_PRICEUPDATE_APPLY_RRP") . " " . $ID, Array("checkbox", "10"));
	$arTabPrice["PRICEUPDATE_APPLY_MIN_MARGIN_{$ID}"] = array(GetMessage("PM_OPTION_PRICEUPDATE_APPLY_MIN_MARGIN") . " " . $ID, Array("checkbox", "10"));
	$arTabPrice["PRICEUPDATE_MIN_MARGIN_FAIL_PER_{$ID}"] = array(GetMessage("PM_OPTION_PRICEUPDATE_MIN_MARGIN_FAIL_PER") . " " . $ID, Array("text", "10"));

	if (in_array($ID, ['YA', 'OS', 'WB', 'WBTL', 'AV', 'SB', 'OZKZ', 'OZTI'])) {
		$arTabPrice["PRICEUPDATE_MAX_PER_{$ID}"] = array(GetMessage("PM_OPTION_PRICEUPDATE_MAX_PER") . " " . $ID, Array("text", "10"));
		$arTabPrice["PRICEUPDATE_CO_INVEST_{$ID}"] = array(GetMessage("PM_OPTION_PRICEUPDATE_CO_INVEST") . " " . $ID, Array("text", "10"));
		$arTabPrice["PRICEUPDATE_MP_COMMISSION_{$ID}"] = array(GetMessage("PM_OPTION_PRICEUPDATE_MP_COMMISSION") . " " . $ID, Array("text", "10"), "break" => "Y");
	} else {
		$arTabPrice["PRICEUPDATE_MAX_PER_{$ID}"] = array(GetMessage("PM_OPTION_PRICEUPDATE_MAX_PER") . " " . $ID, Array("text", "10"), "break" => "Y");
	}
}

$aTabs = array(
    array(
        "DIV" => "main",
        "TAB" => GetMessage("PANEL_MANAGER_TAB_MAIN"),
        "ICON" => "dblocker_settings",
        "TITLE" => GetMessage("PANEL_MANAGER_TAB_MAIN"),
        "OPTIONS" => Array(
//            "server" => Array(GetMessage("PANEL_MANAGER_OPTIONS_SERVER"), Array("text", "86.57.159.86")),
        )
    ),
    array(
        "DIV" => "code_yandex",
        "TAB" => GetMessage("PANEL_MANAGER_TAB_YANDEX"),
        "ICON" => "dblocker_settings",
        "TITLE" => GetMessage("PANEL_MANAGER_TAB_YANDEX"),
        "OPTIONS" => Array(
            "YANDEX_PATH_PARSER" => Array(GetMessage("PANEL_MANAGER_OPTION_YANDEX_PATH_PARSER"), Array("text", "50")),
        )
    ),
    array(
        "DIV" => "code_onliner",
        "TAB" => GetMessage("PANEL_MANAGER_TAB_ONLINER"),
        "ICON" => "dblocker_settings",
        "TITLE" => GetMessage("PANEL_MANAGER_TAB_ONLINER"),
        "OPTIONS" => Array(
            "ONLINER_DEFAULT_TEXT" => Array(GetMessage("PANEL_MANAGER_OPTION_ONLINER_DEFAULT_TEXT"), Array("text", "50")),
			"ONLINER_CLIENT_ID" => Array(GetMessage("PANEL_MANAGER_OPTION_ONLINER_CLIENT_ID"), Array("text", "25")),
			"ONLINER_CLIENT_SECRET" => Array(GetMessage("PANEL_MANAGER_OPTION_ONLINER_CLIENT_SECRET"), Array("text", "25")),
			"ONLINER_CART_CLIENT_ID" => Array(GetMessage("PANEL_MANAGER_OPTION_ONLINER_CART_CLIENT_ID"), Array("text", "25")),
			"ONLINER_CART_CLIENT_SECRET" => Array(GetMessage("PANEL_MANAGER_OPTION_ONLINER_CART_CLIENT_SECRET"), Array("text", "25")),
        )
    ),
    array(
        "DIV" => "code_price",
        "TAB" => GetMessage("PANEL_MANAGER_TAB_PRICELIST"),
        "ICON" => "dblocker_settings",
        "TITLE" => GetMessage("PANEL_MANAGER_TAB_PRICELIST"),
        "OPTIONS" => $arTabPrice
    ),
    array(
        "DIV" => "code_other",
        "TAB" => GetMessage("PANEL_MANAGER_TAB_OTHER"),
        "ICON" => "dblocker_settings",
        "TITLE" => GetMessage("PANEL_MANAGER_TAB_OTHER"),
        "OPTIONS" => Array(
            "SMS_ORDER_ACCEPT_s2" => Array(GetMessage("PM_OPTION_SMS_ORDER_ACCEPT_s2"), Array("text", "100")),
			"SMS_ORDER_DELIVERY_s2" => Array(GetMessage("PM_OPTION_SMS_ORDER_DELIVERY_s2"), Array("text", "100")),
        )
    )

);

$tabControl = new CAdminTabControl("tabControl", $aTabs);


if ($REQUEST_METHOD == "POST" && strlen($Update . $Apply . $RestoreDefaults) > 0 && check_bitrix_sessid()) {
    if (strlen($RestoreDefaults) > 0) {
        COption::RemoveOption("panel.manager");
    } else {
        foreach ($aTabs as $i => $aTab) {
            foreach ($aTab["OPTIONS"] as $name => $arOption) {
                $val = $_POST[$name];

                if ($arOption[1][0] == "multiselectbox") {

                    $val = @implode(",", $val);
                }
                COption::SetOptionString("panel.manager", $name, $val, $arOption[0]);
            }
        }
    }

    if (strlen($Update) > 0 && strlen($_REQUEST["back_url_settings"]) > 0)
        LocalRedirect($_REQUEST["back_url_settings"]);
    else
        LocalRedirect($APPLICATION->GetCurPage() . "?mid=" . urlencode($mid) . "&lang=" . urlencode(LANGUAGE_ID) . "&back_url_settings=" . urlencode($_REQUEST["back_url_settings"]) . "&" . $tabControl->ActiveTabParam());
}

$tabControl->Begin();
?>
<form method="post"
      action="<? echo $APPLICATION->GetCurPage() ?>?mid=<?= urlencode($mid) ?>&amp;lang=<?= LANGUAGE_ID ?>">
    <?
    foreach ($aTabs as $aTab):
        $tabControl->BeginNextTab();
        foreach ($aTab["OPTIONS"] as $name => $arOption):
            $val = COption::GetOptionString("panel.manager", $name);
            $type = $arOption[1];
			//prent($arOption);
            ?>

            <tr>

                <td valign="top" width="50%"><?if ($type[0] == "checkbox")
                        echo "<label for=\"" . htmlspecialchars($name) . "\">" . $arOption[0] . "</label>";
                    else
                        echo $arOption[0];?></td>
                <td valign="top" width="50%">
                    <? if ($type[0] == "checkbox"): ?>
                        <input type="checkbox" name="<? echo htmlspecialchars($name) ?>"
                               id="<? echo htmlspecialchars($name) ?>" value="Y"<? if ($val == "Y") echo " checked"; ?>>
                    <? elseif ($type[0] == "text"): ?>
                        <input type="text" size="<? echo $type[1] ?>" maxlength="255"
                               value="<? echo htmlspecialchars($val) ?>" name="<? echo htmlspecialchars($name) ?>">
                    <?
                    elseif ($type[0] == "textarea"): ?>
                        <textarea rows="<? echo $type[1] ?>" cols="<? echo $type[2] ?>"
                                  name="<? echo htmlspecialchars($name) ?>"><? echo htmlspecialchars($val) ?></textarea>
                    <?
                    elseif ($type[0] == "multiselectbox"): ?>
                        <? $arVals = explode(",", $val); ?>
                        <select multiple="multiple" name="<? echo htmlspecialchars($name) ?>[]">
                            <? foreach ($arOption[1][1] as $arVal): ?>
                                <option
                                    value="<?= $arVal["ID"] ?>" <?= (in_array($arVal["ID"], $arVals) || $arVal["ID"] == 1) ? "selected='selected'" : "" ?>>
                                    [<?= $arVal["ID"] ?>] <?= $arVal["NAME"] ?></option>
                            <? endforeach ?>
                        </select>
                    <?endif ?>
                </td>
            </tr>
			<?if($arOption["break"] == "Y"):?>
				<tr><td colspan="2"><hr></td></tr>
			<?endif?>
        <?endforeach;
    endforeach;?>

    <? $tabControl->Buttons(); ?>
    <input type="submit" name="Update" value="<?= GetMessage("MAIN_SAVE") ?>"
           title="<?= GetMessage("MAIN_OPT_SAVE_TITLE") ?>">
    <input type="submit" name="Apply" value="<?= GetMessage("MAIN_OPT_APPLY") ?>"
           title="<?= GetMessage("MAIN_OPT_APPLY_TITLE") ?>">
    <? if (strlen($_REQUEST["back_url_settings"]) > 0): ?>
        <input type="button" name="Cancel" value="<?= GetMessage("MAIN_OPT_CANCEL") ?>"
               title="<?= GetMessage("MAIN_OPT_CANCEL_TITLE") ?>"
               onclick="window.location='<? echo htmlspecialchars(CUtil::addslashes($_REQUEST["back_url_settings"])) ?>'">
        <input type="hidden" name="back_url_settings" value="<?= htmlspecialchars($_REQUEST["back_url_settings"]) ?>">
    <? endif ?>
    <input type="submit" name="RestoreDefaults" title="<? echo GetMessage("MAIN_HINT_RESTORE_DEFAULTS") ?>"
           OnClick="return confirm('<? echo AddSlashes(GetMessage("MAIN_HINT_RESTORE_DEFAULTS_WARNING")) ?>')"
           value="<? echo GetMessage("MAIN_RESTORE_DEFAULTS") ?>">
    <?= bitrix_sessid_post(); ?>
    <? $tabControl->End(); ?>
</form>
