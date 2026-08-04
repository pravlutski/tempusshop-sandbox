<?php
require __DIR__ . '/_init.php';
AiContentBootstrap::requirePost();

global $USER;
if (!$USER->IsAdmin()) {
	AiContentBootstrap::jsonResponse(['ok' => false, 'error' => 'Admin only'], 403);
}

$apiKey = trim((string)($_POST['api_key'] ?? ''));
$model = trim((string)($_POST['model'] ?? 'gpt-4.1'));
$proxy = trim((string)($_POST['proxy'] ?? ''));
$proxyType = trim((string)($_POST['proxy_type'] ?? 'http'));

try {
	if ($apiKey === '') {
		$existing = '';
		if (class_exists('COption')) {
			$existing = (string)COption::GetOptionString('panel.manager', 'ai_content_openai_key', '');
		}
		if ($existing === '') {
			AiContentBootstrap::jsonResponse(['ok' => false, 'error' => 'Укажите API key'], 400);
		}
		$apiKey = $existing;
	}

	AiContentConfig::save($apiKey, $model ?: 'gpt-4.1', $proxy, $proxyType);
	(new AiContentRepository())->log(null, 'Settings saved', [
		'proxy' => $proxy !== '',
		'proxy_type' => $proxyType,
		'model' => $model,
	]);

	AiContentBootstrap::jsonResponse([
		'ok' => true,
		'has_key' => true,
		'has_proxy' => $proxy !== '',
		'proxy_type' => AiContentConfig::normalizeProxyType($proxyType),
		'model' => $model ?: 'gpt-4.1',
	]);
} catch (Throwable $e) {
	AiContentBootstrap::jsonResponse(['ok' => false, 'error' => $e->getMessage()], 500);
}
