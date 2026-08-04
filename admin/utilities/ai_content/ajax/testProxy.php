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

@set_time_limit(45);

$typesToTry = [];
$requested = AiContentConfig::normalizeProxyType((string)$cfg['proxy_type']);
$typesToTry[] = $requested;
// Vendors often label HTTP proxies as "SOCKS5". If SOCKS hangs/fails — try HTTP.
foreach (['http', 'socks5h', 'socks5'] as $alt) {
	if (!in_array($alt, $typesToTry, true)) {
		$typesToTry[] = $alt;
	}
}

$errors = [];
foreach ($typesToTry as $type) {
	try {
		$tryCfg = $cfg;
		$tryCfg['proxy_type'] = $type;
		$client = new OpenAiClient($tryCfg);
		$result = $client->ping();
		$result['tried_type'] = $type;
		$result['requested_type'] = $requested;
		if ($type !== $requested) {
			$result['note'] = "Тип '{$requested}' не сработал, автоматически подошёл '{$type}'. Сохрани settings с type={$type}.";
		}
		AiContentBootstrap::jsonResponse($result);
	} catch (Throwable $e) {
		$errors[] = $type . ': ' . $e->getMessage();
		// continue to next type
	}
}

AiContentBootstrap::jsonResponse([
	'ok' => false,
	'error' => implode(' || ', $errors),
], 500);
