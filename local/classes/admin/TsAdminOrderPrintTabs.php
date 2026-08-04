<?
class TsAdminOrderPrintTabs
{
    public static function onInit()
    {
        return array(
            "TABSET" => "OrderPrintTabs",
            "GetTabs" => array("TsAdminOrderPrintTabs", "getTabs"),
            "ShowTab" => array("TsAdminOrderPrintTabs", "showTabs"),
            "Action" => array("TsAdminOrderPrintTabs", "onSave"),
            "Check" => array("TsAdminOrderPrintTabs", "onBeforeSave"),
        );
    }

    /*
    Возвращает массив вкладок
    */
    public static function getTabs($args)
    {
        return array(
            array(
                "DIV" => "order_print_tab", 
                "TAB" => "Печать стикеров", 
                "TITLE" => "История печати стикеров для заказа",
                "SORT" => 100
            )
        );
    }

    /*
    Выводит вкладку
    */
    public static function showTabs($tabName, $args, $varsFromForm)
    {
        if ($tabName == "order_print_tab") {
			require_once $_SERVER['DOCUMENT_ROOT'] . '/local/classes/admin/order_print_tab.php';
		}
    }

    /*
    Вызывается перед onSave
    Для формы просмотра бесполезно, написано для примера
    */
    public static function onBeforeSave($args)
    {
        return true;
    }

    /*
    Вызывается после onBeforeSave при отправке формы
    Для формы просмотра бесполезно, написано для примера
    */
    public static function onSave($args)
    {
        return true;
    }
}
?>