<?
$_SERVER["DOCUMENT_ROOT"] = "/var/www/bitrix/data/www/tempusshop.ru";

define("NO_KEEP_STATISTIC", true);
define("NOT_CHECK_PERMISSIONS", true);

require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");

global $DB;
$start = microtime();
$unique = uniqid();

$workerChecker = new WorkersChecker();
$triggers = new TsTriggers();
$logger = new TsLogger("/checkWorkers/");

$arTasks = [];
exec("ps -U bitrix -u bitrix -u root u", $arTasks, $result); // получить все задачи на сервере
//exec("ps aux | grep php", $arTasks, $result); // получить все задачи на сервере
//exec('ps -U bitrix -u bitrix -u root --format="uid uname cmd time"', $arTasks, $result); // получить все задачи на сервере
//print_r($arTasks);
//die;
$arData = $workerChecker->check();

foreach ($arData as $worker => &$data) {
    if(!$data["TIME_CHECK"] || !$data["PATH_SCRIPT"]) continue;

    if($data["TIMEOUT"] <= 0){
        $data["TIMEOUT"] = 60;
    }

    if(!$data["NAME"]){
        $data["NAME"] = "Обработчик " . $data["WORKER_ID"];
    }

    $timeStampStart = strtotime($data["TIME_START"]);
    $timeStampCheck = strtotime($data["TIME_CHECK"]);
    $timeStampEnd = strtotime($data["TIME_END"]);

    $scriptActive = false;
    foreach ($arTasks as $line) {
        if (strstr($line, $data["PATH_SCRIPT"])) {
            $scriptActive = true;
        }
    }

    //if($scriptActive === true) continue;

    $scriptHung = false;
    if ($scriptActive === false && $data["IS_BUSY"] == "Y") {
        $scriptHung = true;
    }
    //$data["TIMEOUT"] = 1;
    if((((time() - $timeStampCheck) / 60) > $data["TIMEOUT"]) || $scriptHung === true) {

        // скрипт все еще работает
        if ($scriptActive) {
            $logger->log("LOG", "scriptActive - " . $scriptActive);
            $logger->log("LOG", "scriptHung - " . $scriptHung);
            $logger->log("LOG", "timeStampStart - " . $timeStampStart);
            $logger->log("LOG", "timeStampCheck - " . $timeStampCheck);
            $logger->log("LOG", "timeStampEnd - " . $timeStampEnd);
            $logger->log("LOG", "time - " . time());
            $logger->log("LOG", "data", $data);

			$realWork = $workerChecker->getWorker($data["WORKER_ID"]);
            $logger->log("LOG", "realWork - " . print_r($realWork, true));

            $hours = ceil($data["TIMEOUT"] / 60);

            $in = array(
                "TIME_CHECK" => "'".addslashes(date('Y-m-d H:i:s', strtotime("+{$hours} hours", $timeStampCheck)))."'",
            );

            $DB->Update("ci_worker_busy", $in, "WHERE ID = '{$data["ID"]}'", $err_mess.__LINE__);

            $workMinutes = round((time() - $timeStampStart) / 60);
            $triggers->SetError(["Возможно проблемы с обработчиком '{$data["NAME"]}'! Скрипт работает {$workMinutes} минут. Изменена дата проверки на {$hours} час(ов)."]);

        } else {
            // скрипт не работает

            if ($data["IS_BUSY"] == "Y") {
                // стоит флаг занятости обработчика

                $in = array(
                    "IS_BUSY" => "'N'",
                );

                $DB->Update("ci_worker_busy", $in, "WHERE ID = '{$data["ID"]}'", $err_mess.__LINE__);

                $triggers->SetError(["Проблемы с обработчиком. Флаг стоит, процесса нет. '".$data["NAME"]."'! Последний запуск был больше ".$data["TIMEOUT"]." минут назад. Убрали флаг запуска."]);
				$logger->log("LOG", "Проблемы с обработчиком. Флаг стоит, процесса нет. - ", $data);
            } else {
                // флага нет, нужно смотреть в ручную почему не работает

                //if($timeStampEnd >= $timeStampStart) continue;

                $triggers->SetError(["Проблемы с обработчиком 3 '".$data["NAME"]."'! Последний запуск был больше ".$data["TIMEOUT"]." минут назад."]);
                $triggers->SetError(["Обработчик не занят, нужно разработчикам разобраться в чем ошибка!!! Ссылка на обработчик https://tempusshop.ru/bitrix/admin/perfmon_row_edit.php?lang=ru&table_name=ci_worker_busy&pk[ID]={$data["ID"]}"]);


				$hours = ceil($data["TIMEOUT"] / 60);

				$in = array(
					"TIME_CHECK" => "'".addslashes(date('Y-m-d H:i:s', strtotime("+{$hours} hours", $timeStampCheck)))."'",
				);

				$DB->Update("ci_worker_busy", $in, "WHERE ID = '{$data["ID"]}'", $err_mess.__LINE__);

				$triggers->SetError(["Изменена дата проверки на {$hours} час(ов)."]);


				$logger->log("LOG", "Обработчик не занят, нужно разработчикам разобраться в чем ошибка!!! - " . $data);
            }
        }

        if (!empty($triggers->GetErrors()) && $data["SEND_MESSAGE"] == "Y") {echo "<pre>" . print_r($data, true) . "</pre>";
			//echo "<pre>" . print_r($data, true) . "</pre>";
            $triggers->SendTriggerErrors();
        }
    }

}
unset($data);

// запускаем скрипты на которые установлен флаг запуска NEED_START = Y
$arData = $workerChecker->getForceWorker();
foreach ($arData as $worker => $data) {
    if (!$data["PATH_SCRIPT"]) continue;
    file_put_contents("/home/bitrix/logs/checkWorkers.txt", date("Y-m-d H:i:s") . " Запускаем - " . $data["PATH_SCRIPT"] . "\r\n", FILE_APPEND);
    system("/usr/bin/{$data["PATH_SCRIPT"]} >/dev/null 2>&1 &");
}

$end = microtime() - $start;
file_put_contents('/var/www/bitrix/data/www/tempusshop.ru/local/cron/checkWorkers.txt', print_r(['date' => date('Y-m-d H:i:s'), 'end' => $end, 'unique' => $unique, ], true), 8);