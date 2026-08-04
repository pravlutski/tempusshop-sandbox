<?php

class AiContentBootstrap
{
	public static function init(): void
	{
		static $done = false;
		if ($done) {
			return;
		}
		$base = __DIR__;
		require_once $base . '/AiContentConfig.php';
		require_once $base . '/AiContentAccess.php';
		require_once $base . '/OpenAiClient.php';
		require_once $base . '/AiContentImageFetcher.php';
		require_once $base . '/AiContentRepository.php';
		require_once $base . '/AiContentResearcher.php';
		require_once $base . '/AiContentPublisher.php';
		$done = true;
	}

	public static function jsonResponse(array $payload, int $code = 200): void
	{
		http_response_code($code);
		header('Content-Type: application/json; charset=utf-8');
		echo json_encode($payload, JSON_UNESCAPED_UNICODE);
		die();
	}

	public static function requirePost(): void
	{
		if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
			self::jsonResponse(['ok' => false, 'error' => 'POST required'], 405);
		}
	}
}
