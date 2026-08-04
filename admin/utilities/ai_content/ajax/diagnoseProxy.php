<?php
require __DIR__ . '/_init.php';
AiContentBootstrap::requirePost();

global $USER;
if (!$USER->IsAdmin()) {
	AiContentBootstrap::jsonResponse(['ok' => false, 'error' => 'Admin only'], 403);
}

@set_time_limit(60);

$proxy = trim((string)($_POST['proxy'] ?? ''));
$proxyType = trim((string)($_POST['proxy_type'] ?? 'http'));
$apiKey = trim((string)($_POST['api_key'] ?? ''));

try {
	$cfg = AiContentConfig::get();
} catch (Throwable $e) {
	$cfg = ['api_key' => '', 'model' => 'gpt-4.1', 'proxy' => '', 'proxy_type' => 'http'];
}

if ($apiKey !== '') {
	$cfg['api_key'] = $apiKey;
}
if ($proxy !== '' || array_key_exists('proxy', $_POST)) {
	$cfg['proxy'] = $proxy;
}
if ($proxyType !== '') {
	$cfg['proxy_type'] = AiContentConfig::normalizeProxyType($proxyType);
}

if ($cfg['api_key'] === '' || trim((string)$cfg['proxy']) === '') {
	AiContentBootstrap::jsonResponse(['ok' => false, 'error' => 'Нужны api_key и proxy'], 400);
}

try {
	$client = new OpenAiClient($cfg);
	AiContentBootstrap::jsonResponse($client->diagnose());
} catch (Throwable $e) {
	AiContentBootstrap::jsonResponse(['ok' => false, 'error' => $e->getMessage()], 500);
}
