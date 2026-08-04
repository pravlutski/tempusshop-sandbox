<?php
require __DIR__ . '/_init.php';
AiContentBootstrap::requirePost();

$payload = json_decode((string)file_get_contents('php://input'), true);
if (!is_array($payload)) {
	$payload = $_POST;
}
if (isset($payload['props']) && is_string($payload['props'])) {
	$payload['props'] = json_decode($payload['props'], true) ?: [];
}
if (isset($payload['selected_photos']) && is_string($payload['selected_photos'])) {
	$payload['selected_photos'] = json_decode($payload['selected_photos'], true) ?: [];
}

$taskId = (int)($payload['task_id'] ?? 0);
if ($taskId <= 0) {
	AiContentBootstrap::jsonResponse(['ok' => false, 'error' => 'task_id required'], 400);
}

try {
	$publisher = new AiContentPublisher();
	$publisher->saveDraft($taskId, $payload);
	AiContentBootstrap::jsonResponse(['ok' => true]);
} catch (Throwable $e) {
	AiContentBootstrap::jsonResponse(['ok' => false, 'error' => $e->getMessage()], 500);
}
