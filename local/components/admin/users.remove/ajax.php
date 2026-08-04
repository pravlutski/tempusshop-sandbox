<?php
require_once($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");

use Bitrix\Main\Loader;
use Bitrix\Main\UserTable;

if (!$USER->IsAdmin()) {
    die(json_encode(["error" => "Доступ запрещен"]));
}
$logger = new TsLogger("/utils/users_remove/");

$userIds = $_POST["user_ids"] ?? "";
$chunkSize = intval($_POST["chunk_size"] ?? 100);
$offset = intval($_POST["offset"] ?? 0);

if (empty($userIds)) {
	$logger->log("ERROR", "Список ID пользователей пуст"); 
    die(json_encode(["error" => "Список ID пользователей пуст"]));
}

$ids = array_filter(array_map("intval", explode("\n", trim($userIds))));
$total = count($ids);
$chunk = array_slice($ids, $offset, $chunkSize);

$result = [
    "deleted" => 0,
    "errors" => [],
    "offset" => $offset + count($chunk),
    "total" => $total,
    "complete" => false,
];
$logger->log("LOG", "Список", $result); 
foreach ($chunk as $userId) {
    if ($userId <= 1) {
		$logger->log("ERROR", "ID {$userId}: Нельзя удалить администратора"); 
        $result["errors"][] = "ID {$userId}: Нельзя удалить администратора";
        continue;
    }
    
    $user = new CUser();
    $deleteResult = $user->Delete($userId);
    
    if ($deleteResult) {
		$logger->log("LOG", "Удалили ID {$userId}"); 
        $result["deleted"]++;
    } else {
		$logger->log("ERROR", "Ошибка удаления пользователя {$userId}", $user->LAST_ERROR); 
        $errors = $user->LAST_ERROR;
        $result["errors"][] = "ID {$userId}: {$errors}";
    }
}

if ($result["offset"] >= $result["total"]) {
    $result["complete"] = true;
}

$logger->log("LOG", "Конец итерации", $result); 

header("Content-Type: application/json");
echo json_encode($result, JSON_UNESCAPED_UNICODE);