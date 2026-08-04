<?
$aMenuLinks = Array(
	Array(
		"Распределенные",
		"/admin/c/storekeeper.php",
		Array(),
		Array(),
		'in_array("6",$USER->GetUserGroupArray()) || in_array("12",$USER->GetUserGroupArray()) || in_array(19,$USER->GetUserGroupArray()) || in_array("21",$USER->GetUserGroupArray()) || in_array("24",$USER->GetUserGroupArray()) ||  $USER->isAdmin()'
	),
	Array(
		"Доступные",
		"/admin/c/",
		Array(),
		Array(),
		'in_array("17",$USER->GetUserGroupArray()) || in_array("24",$USER->GetUserGroupArray()) || in_array("19",$USER->GetUserGroupArray()) || $USER->isAdmin()'
	),
	Array(
		"Активные",
		"/admin/c/order_accept.php",
		Array(),
		Array(),
		'in_array("17",$USER->GetUserGroupArray()) || in_array("24",$USER->GetUserGroupArray()) || $USER->isAdmin()'
	),
	Array(
		"Отчет",
		"/admin/c/report.php",
		Array(),
		Array(),
		'in_array("17",$USER->GetUserGroupArray()) || in_array("24",$USER->GetUserGroupArray()) || $USER->isAdmin()'
	),
	Array(
		"Отчет руководитель",
		"/admin/c/report_manager.php",
		Array(),
		Array(),
		'in_array("6",$USER->GetUserGroupArray()) || in_array("24",$USER->GetUserGroupArray()) || $USER->isAdmin()'
	),

);
?>
