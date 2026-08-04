<?php
require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");
set_time_limit(0);

CModule::IncludeModule('panel.manager');

use Bitrix\Main\Loader;
use Bitrix\Iblock\ElementTable;

Loader::includeModule('iblock');

GLOBAL $DB;

// Находим идентификатор процесса, где запущен importFBOGroup.php с аргументом TI
$output = shell_exec('ps aux | grep "importFBOGroup.php TI"');
$lines = explode("\n", trim($output));
print_r($lines);

// Получаем PID и убиваем процесс
foreach ($lines as $line) {
    $parts = preg_split('/\s+/', $line);
    $pid = $parts[1]; // PID находится во втором столбце
    shell_exec("kill -9 $pid");
    echo "Процесс с PID $pid завершен.\n";
}
?>
