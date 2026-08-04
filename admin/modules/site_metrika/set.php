<?php

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");


global $DB;

class customPrice
{
	public static function getList()
	{
		global $DB;

		$strSql = "SELECT * FROM `ci_site_metrika`";

		return $DB->Query($strSql, false);
	}

	public static function saveMetric($page, $domain, $get, $ip, $time)
	{
		global $DB;

		$date = date('Y-m-d');
		$timeFormatted = date('H:i:s', $time);

		$sql = "INSERT INTO `ci_site_metrika` (`page`, `domain`, `get`, `ip`, `time`, `date`) 
                VALUES ('" . $DB->ForSQL($page) . "', 
                        '" . $DB->ForSQL($domain) . "',
                        '" . $DB->ForSQL($get) . "',
                        '" . $DB->ForSQL($ip) . "',
                        '" . $timeFormatted . "',
                        '" . $date . "')";

		return $DB->Query($sql, false, "File: " . __FILE__ . "<br>Line: " . __LINE__);
	}
}

// Пример использования
$page = $_SERVER['REQUEST_URI'];
$domain = $_SERVER['HTTP_HOST'];
$get = $_SERVER['QUERY_STRING'];
$ip = $_SERVER['REMOTE_ADDR'];
$time = $_SERVER['REQUEST_TIME_FLOAT'];

customPrice::saveMetric($page, $domain, $get, $ip, $time);