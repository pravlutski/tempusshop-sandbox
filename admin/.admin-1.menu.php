<?
$aMenuLinks = Array(
	// Array(
	// 	"Купоны и ПС",
	// 	"/admin/coupon/",
	// 	Array(),
	// 	Array(),
	// 	"in_array(\"12\",\$USER->GetUserGroupArray())||in_array(\"6\",\$USER->GetUserGroupArray())||\$USER->isAdmin()"
	// ),
	// Array(
	// 	"Контент-редактор",
	// 	"/admin/content/",
	// 	Array(),
	// 	Array(),
	// 	"in_array(\"6\",\$USER->GetUserGroupArray())||in_array(\"7\",\$USER->GetUserGroupArray())||\$USER->isAdmin()"
	// ),
	Array(
		"Утилиты",
		"/admin/utilities/",
		Array(),
		Array(),
		"in_array(\"6\",\$USER->GetUserGroupArray())||in_array(\"7\",\$USER->GetUserGroupArray())||in_array(\"12\",\$USER->GetUserGroupArray())||in_array(\"13\",\$USER->GetUserGroupArray())||in_array(\"18\",\$USER->GetUserGroupArray())||in_array(\"19\",\$USER->GetUserGroupArray())||in_array(\"21\",\$USER->GetUserGroupArray())||\$USER->isAdmin()"
	),
	Array(
		"Анализ цен",
		"/admin/analiz/",
		Array(),
		Array(),
		"in_array(\"6\",\$USER->GetUserGroupArray())||in_array(\"12\",\$USER->GetUserGroupArray())||\$USER->isAdmin()"
	),
	Array(
		"Прайс-листы",
		"/admin/pricelist/",
		Array(),
		Array(),
		"in_array(\"6\",\$USER->GetUserGroupArray())||in_array(\"12\",\$USER->GetUserGroupArray())||in_array(\"18\",\$USER->GetUserGroupArray())||in_array(\"19\",\$USER->GetUserGroupArray())||\$USER->isAdmin()"
	),
	Array(
		"Заказы поставщикам",
		"/admin/purchase/",
		Array(),
		Array(),
		"in_array(\"6\",\$USER->GetUserGroupArray())||in_array(\"19\",\$USER->GetUserGroupArray())||\$USER->isAdmin()"
	),
	// Array(
	// 	"Опт прайс",
	// 	"/admin/opt/",
	// 	Array(),
	// 	Array(),
	// 	"in_array(\"6\",\$USER->GetUserGroupArray())||in_array(\"9\",\$USER->GetUserGroupArray())||\$USER->isAdmin()"
	// ),
	// Array(
	// 	"Закупки ОПТ",
	// 	"/admin/opt/purchase/",
	// 	Array(),
	// 	Array(),
	// 	"in_array(\"18\",\$USER->GetUserGroupArray())||\$USER->isAdmin()"
	// ),
	Array(
    "Настройки",
	    "/admin/settings/",
	    Array(),
	    Array(),
	    "\$USER->isAdmin()"
	),
);
?>
