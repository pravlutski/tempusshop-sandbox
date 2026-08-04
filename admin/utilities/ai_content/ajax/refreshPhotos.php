<?php
require __DIR__ . '/_init.php';
AiContentBootstrap::requirePost();

@set_time_limit(240);

$taskId = (int)($_POST['task_id'] ?? 0);
if ($taskId <= 0) {
	AiContentBootstrap::jsonResponse(['ok' => false, 'error' => 'task_id required'], 400);
}

try {
	$result = (new AiContentResearcher())->refreshPhotos($taskId);
	AiContentBootstrap::jsonResponse($result);
} catch (Throwable $e) {
	AiContentBootstrap::jsonResponse(['ok' => false, 'error' => $e->getMessage()], 500);
}
