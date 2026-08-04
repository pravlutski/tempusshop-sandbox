<?php
require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php';

if (!CModule::IncludeModule('panel.manager')) {
	http_response_code(500);
	header('Content-Type: application/json; charset=utf-8');
	echo json_encode(['ok' => false, 'error' => 'panel.manager missing']);
	die();
}

global $USER;
if (!$USER || !$USER->IsAuthorized()) {
	http_response_code(401);
	header('Content-Type: application/json; charset=utf-8');
	echo json_encode(['ok' => false, 'error' => 'Unauthorized']);
	die();
}

require_once dirname(__DIR__) . '/classes/AiContentBootstrap.php';
AiContentBootstrap::init();

// Ensure tables exist (safe IF NOT EXISTS)
(new AiContentRepository())->ensureSchema();
