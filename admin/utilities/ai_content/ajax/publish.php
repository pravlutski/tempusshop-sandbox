<?php
require __DIR__ . '/_init.php';
AiContentBootstrap::requirePost();

@set_time_limit(180);

$raw = file_get_contents('php://input');
$payload = json_decode((string)$raw, true);
if (!is_array($payload)) {
	$payload = $_POST;
	if (isset($payload['props']) && is_string($payload['props'])) {
		$payload['props'] = json_decode($payload['props'], true) ?: [];
	}
	if (isset($payload['selected_photos']) && is_string($payload['selected_photos'])) {
		$payload['selected_photos'] = json_decode($payload['selected_photos'], true) ?: [];
	}
}

$taskId = (int)($payload['task_id'] ?? $_POST['task_id'] ?? 0);
if ($taskId <= 0) {
	AiContentBootstrap::jsonResponse(['ok' => false, 'error' => 'task_id required'], 400);
}

// Save latest edits before publish
if (is_array($payload)) {
	try {
		(new AiContentPublisher())->saveDraft($taskId, $payload);
	} catch (Throwable $e) {
		AiContentBootstrap::jsonResponse(['ok' => false, 'error' => 'Save before publish failed: ' . $e->getMessage()], 500);
	}
}

try {
	$result = (new AiContentPublisher())->publish($taskId);
	AiContentBootstrap::jsonResponse($result);
} catch (Throwable $e) {
	AiContentBootstrap::jsonResponse(['ok' => false, 'error' => $e->getMessage()], 500);
}
