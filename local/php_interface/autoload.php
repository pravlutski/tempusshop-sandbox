<?php
use Bitrix\Main\Loader;
//Автозагрузка наших классов
Loader::registerAutoLoadClasses(null, [
		'CIBlockTools'					=> '/bitrix/php_interface/include/classes/iblock_tools.php',
		'CProSet'						=> '/bitrix/php_interface/include/classes/settings.php',
		'CExchange'						=> '/bitrix/php_interface/include/classes/exchange.php',
		'CLog'							=> '/bitrix/php_interface/include/classes/logger.php',
		'Pict'							=> '/bitrix/php_interface/include/classes/classPict.php',
		'CSyncPW'						=> '/bitrix/php_interface/include/classes/sync_pw.php',
		'WorkersChecker'				=> '/local/classes/WorkersChecker.php',
		'TsLogger'						=> '/local/classes/TsLogger.php',
		'TsTriggers'					=> '/local/classes/TsTriggers.php',
		'TsTriggersEdit'				=> '/bitrix/php_interface/include/classes/TsTriggersEdit.php',
		'HighloadApi'					=> '/local/classes/HighloadApi.php',
		//		'CCatalogProductProviderCustom'	=> '/bitrix/php_interface/providers/product.php',
		"TsAdminOrderPrintTabs"			=> '/local/classes/admin/TsAdminOrderPrintTabs.php',
		"TsIblock" 						=> '/local/classes/handlers/iblock.php',
		"TsMain" 						=> '/local/classes/handlers/main.php',
		'RedisCache'					=> '/local/classes/RedisCache.php',
	]
);
