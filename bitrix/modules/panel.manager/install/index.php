<?
global $MESS;
$strPath2Lang = str_replace("\\", "/", __FILE__);
$strPath2Lang = substr($strPath2Lang, 0, strlen($strPath2Lang) - 18);
include(GetLangFileName($strPath2Lang . "/lang/", "/install/index.php"));

class PanelManagerInstall extends CModule
{
    var $MODULE_ID = "panel.manager";
    var $MODULE_VERSION;
    var $MODULE_VERSION_DATE;
    var $MODULE_NAME;
    var $MODULE_DESCRIPTION;
    var $MODULE_CSS;

    function __construct()
    {
        $arModuleVersion = array();

        $path = str_replace("\\", "/", __FILE__);
        $path = substr($path, 0, strlen($path) - strlen("/index.php"));
        include($path . "/version.php");

        if (is_array($arModuleVersion) && array_key_exists("VERSION", $arModuleVersion)) {
            $this->MODULE_VERSION = $arModuleVersion["VERSION"];
            $this->MODULE_VERSION_DATE = $arModuleVersion["VERSION_DATE"];
        } else {
            $this->MODULE_VERSION = NS_CATALOG_VERSION;
            $this->MODULE_VERSION_DATE = NS_CATALOG_VERSION_DATE;
        }

        $this->MODULE_NAME = GetMessage("PANEL_MANEGER_MODULE_NAME");
        $this->MODULE_DESCRIPTION = GetMessage("PANEL_MANEGER_MODULE_DESC");
    }

    function InstallDB($arParams = array())
    {
        RegisterModule("panel.manager");
        return true;
    }

    function UnInstallDB($arParams = array())
    {
        UnRegisterModule("panel.manager");
        return true;
    }


    function DoInstall()
    {
        global $DOCUMENT_ROOT, $APPLICATION;
        if ($this->InstallDB()) {
            $APPLICATION->IncludeAdminFile(GetMessage("PANEL_MANEGER_INSTALL_TITLE'"), $DOCUMENT_ROOT . "/bitrix/modules/panel.manager/install/step.php");
        }

    }

    function DoUninstall()
    {
        global $DOCUMENT_ROOT, $APPLICATION;
        $this->UnInstallDB();
        $APPLICATION->IncludeAdminFile(GetMessage("PANEL_MANEGER_UNINSTALL_TITLE"), $DOCUMENT_ROOT . "/bitrix/modules/panel.manager/install/unstep.php");
    }
}
