<?php
require __DIR__ . '/_init.php';
AiContentBootstrap::requirePost();

global $USER;
if (!$USER->IsAdmin()) {
	AiContentBootstrap::jsonResponse(['ok' => false, 'error' => 'Admin only'], 403);
}

$proxy = trim((string)($_POST['proxy'] ?? ''));
$proxyType = trim((string)($_POST['proxy_type'] ?? 'http'));
$model = trim((string)($_POST['model'] ?? ''));
$apiKey = trim((string)($_POST['api_key'] ?? ''));

try {
	$cfg = AiContentConfig::get();
} catch (Throwable $e) {
	$cfg = ['api_key' => '', 'model' => 'gpt-4.1', 'proxy' => '', 'proxy_type' => 'http'];
}

if ($apiKey !== '') {
	$cfg['api_key'] = $apiKey;
}
if ($model !== '') {
	$cfg['model'] = $model;
}
if ($proxy !== '' || array_key_exists('proxy', $_POST)) {
	$cfg['proxy'] = $proxy;
}
if ($proxyType !== '') {
	$cfg['proxy_type'] = AiContentConfig::normalizeProxyType($proxyType);
}

if ($cfg['api_key'] === '') {
	AiContentBootstrap::jsonResponse(['ok' => false, 'error' => 'Нет API key'], 400);
}
if (trim((string)$cfg['proxy']) === '') {
	AiContentBootstrap::jsonResponse(['ok' => false, 'error' => 'Укажите прокси — без него OpenAI из РФ/BY недоступен'], 400);
}

try {
	$client = new OpenAiClient($cfg);
	$result = $client->ping();
	AiContentBootstrap::jsonResponse($result);
} catch (Throwable $e) {
	AiContentBootstrap::jsonResponse(['ok' => false, 'error' => $e->getMessage()], 500);
}
