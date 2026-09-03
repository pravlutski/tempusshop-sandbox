<?php
require_once($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php');

set_time_limit(0);
header('Content-Type: application/json; charset=UTF-8');

function sendTempusJson($data)
{
	echo json_encode($data, JSON_UNESCAPED_UNICODE);
}

if (!CModule::IncludeModule('panel.manager') || !CModule::IncludeModule('iblock')) {
	sendTempusJson(['success' => false, 'error' => 'Модули недоступны']);
	exit;
}

global $USER;
$arGroups = $USER->GetUserGroupArray();
if (!$USER->IsAdmin() && !in_array(6, $arGroups) && !in_array(7, $arGroups)) {
	sendTempusJson(['success' => false, 'error' => 'Доступ запрещен']);
	exit;
}

if (!check_bitrix_sessid()) {
	sendTempusJson(['success' => false, 'error' => 'Сессия истекла, обновите страницу']);
	exit;
}

require_once(__DIR__ . '/include.php');

$logger = new TsLogger('/utils/send-tempus/');
$action = (string)($_POST['action'] ?? '');
$iblockId = SEND_TEMPUS_IBLOCK_ID;

try {
	if ($action === 'resolve') {
		$allActive = ($_POST['all_active'] ?? '') === 'Y';
		$resolved = sendTempusResolveIds(
			$_POST['product_ids'] ?? '',
			$_POST['product_articles'] ?? '',
			$iblockId,
			$allActive
		);

		$allowedProps = sendTempusGetIblockProps($iblockId);
		$allowedFields = sendTempusMainFields();
		$props = sendTempusFilterSelected($_POST['props'] ?? [], $allowedProps);
		$fields = sendTempusFilterSelected($_POST['fields'] ?? [], $allowedFields);

		if (!$resolved['ids']) {
			sendTempusJson([
				'success' => false,
				'error' => 'Не найдены товары для отправки',
				'notFound' => $resolved['notFound'],
			]);
			exit;
		}
		if (!$props && !$fields) {
			sendTempusJson([
				'success' => false,
				'error' => 'Выберите свойства или поля для передачи',
			]);
			exit;
		}

		$chunks = array_chunk($resolved['ids'], SEND_TEMPUS_CHUNK_SIZE);
		$logger->log('LOG', 'Подготовка отправки в Tempus', [
			'user' => $USER->GetID(),
			'source' => $resolved['source'],
			'count' => count($resolved['ids']),
			'notFound' => $resolved['notFound'],
			'props' => $props,
			'fields' => $fields,
		]);

		sendTempusJson([
			'success' => true,
			'ids' => $resolved['ids'],
			'chunks' => $chunks,
			'count' => count($resolved['ids']),
			'notFound' => $resolved['notFound'],
			'source' => $resolved['source'],
			'props' => $props,
			'fields' => $fields,
		]);
		exit;
	}

	if ($action === 'send') {
		$ids = $_POST['ids'] ?? [];
		if (!is_array($ids)) {
			$ids = [];
		}
		$ids = array_values(array_unique(array_filter(array_map('intval', $ids))));

		$allowedProps = sendTempusGetIblockProps($iblockId);
		$allowedFields = sendTempusMainFields();
		$props = sendTempusFilterSelected($_POST['props'] ?? [], $allowedProps);
		$fields = sendTempusFilterSelected($_POST['fields'] ?? [], $allowedFields);

		if (!$ids) {
			sendTempusJson(['success' => false, 'error' => 'Пустой список ID']);
			exit;
		}
		if (!$props && !$fields) {
			sendTempusJson(['success' => false, 'error' => 'Выберите свойства или поля для передачи']);
			exit;
		}

		$helperPath = $_SERVER['DOCUMENT_ROOT'] . '/local/classes/SyncHelper.php';
		if (!is_file($helperPath)) {
			sendTempusJson(['success' => false, 'error' => 'Файл SyncHelper.php не найден']);
			exit;
		}

		require_once($helperPath);
		$syncHelper = new SyncHelper();
		$syncHelper->sendPropProduct($ids, $props, $fields);

		$logger->log('LOG', 'Отправлен чанк в Tempus', [
			'user' => $USER->GetID(),
			'count' => count($ids),
			'ids' => $ids,
			'props' => $props,
			'fields' => $fields,
		]);

		sendTempusJson([
			'success' => true,
			'sent' => count($ids),
		]);
		exit;
	}

	sendTempusJson(['success' => false, 'error' => 'Неизвестное действие']);
} catch (Throwable $e) {
	$logger->log('ERROR', 'Ошибка отправки в Tempus', [
		'message' => $e->getMessage(),
		'file' => $e->getFile(),
		'line' => $e->getLine(),
	]);
	sendTempusJson(['success' => false, 'error' => 'Ошибка: ' . $e->getMessage()]);
}
