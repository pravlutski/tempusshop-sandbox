<?php
require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php';

global $USER;
if (!$USER || !$USER->IsAuthorized()) {
	http_response_code(401);
	die('Unauthorized');
}

$url = trim((string)($_GET['u'] ?? ''));
if ($url === '' || !filter_var($url, FILTER_VALIDATE_URL)) {
	http_response_code(400);
	die('Bad url');
}

$parts = parse_url($url);
$scheme = strtolower((string)($parts['scheme'] ?? ''));
if (!in_array($scheme, ['http', 'https'], true)) {
	http_response_code(400);
	die('Bad scheme');
}

// Basic SSRF guard: block obvious local targets
$host = strtolower((string)($parts['host'] ?? ''));
if ($host === '' || $host === 'localhost' || str_ends_with($host, '.local') || preg_match('/^(127\.|10\.|192\.168\.|172\.(1[6-9]|2\d|3[0-1])\.|0\.|169\.254\.)/', $host)) {
	http_response_code(400);
	die('Host not allowed');
}

require_once dirname(__DIR__) . '/classes/AiContentBootstrap.php';
AiContentBootstrap::init();

$cacheDir = rtrim((string)$_SERVER['DOCUMENT_ROOT'], '/') . '/upload/ai_content_cache';
if (!is_dir($cacheDir)) {
	@mkdir($cacheDir, 0775, true);
}
$cacheKey = md5($url);
$cacheFile = $cacheDir . '/' . $cacheKey . '.bin';
$metaFile = $cacheDir . '/' . $cacheKey . '.json';

if (is_file($cacheFile) && is_file($metaFile) && (time() - filemtime($cacheFile) < 86400 * 7)) {
	$meta = json_decode((string)@file_get_contents($metaFile), true) ?: [];
	$ctype = (string)($meta['content_type'] ?? 'image/jpeg');
	header('Content-Type: ' . $ctype);
	header('Cache-Control: public, max-age=86400');
	readfile($cacheFile);
	die();
}

$ch = curl_init($url);
$opts = [
	CURLOPT_RETURNTRANSFER => true,
	CURLOPT_FOLLOWLOCATION => true,
	CURLOPT_MAXREDIRS => 5,
	CURLOPT_TIMEOUT => 25,
	CURLOPT_CONNECTTIMEOUT => 10,
	CURLOPT_SSL_VERIFYPEER => true,
	CURLOPT_SSL_VERIFYHOST => 2,
	CURLOPT_USERAGENT => 'Mozilla/5.0 (compatible; TempusAiContent/1.0)',
	CURLOPT_HTTPHEADER => [
		'Accept: image/avif,image/webp,image/apng,image/*,*/*;q=0.8',
	],
];
if (defined('CURL_IPRESOLVE_V4')) {
	$opts[CURLOPT_IPRESOLVE] = CURL_IPRESOLVE_V4;
}

// Reuse OpenAI HTTP proxy for image fetch when available (helps with geo/CDN blocks)
try {
	$cfg = AiContentConfig::get();
	$parsed = AiContentConfig::parseProxy((string)($cfg['proxy'] ?? ''), (string)($cfg['proxy_type'] ?? 'http'));
	if ($parsed && empty($parsed['is_socks'])) {
		$userpwd = (string)($parsed['userpwd'] ?? '');
		$hostport = (string)$parsed['hostport'];
		$opts[CURLOPT_PROXY] = $userpwd !== '' ? ('http://' . $userpwd . '@' . $hostport) : ('http://' . $hostport);
		$opts[CURLOPT_HTTPPROXYTUNNEL] = true;
		if ($userpwd !== '') {
			$opts[CURLOPT_PROXYUSERPWD] = $userpwd;
		}
	}
} catch (Throwable $e) {
	// proxy optional for images
}

curl_setopt_array($ch, $opts);
$body = curl_exec($ch);
$errno = curl_errno($ch);
$code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
$ctype = (string)curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
curl_close($ch);

if ($errno || $code >= 400 || $body === false || $body === '') {
	http_response_code(502);
	header('Content-Type: text/plain; charset=utf-8');
	die('Image fetch failed HTTP ' . $code);
}

if ($ctype === '' || stripos($ctype, 'image/') === false) {
	// sniff
	$finfo = new finfo(FILEINFO_MIME_TYPE);
	$ctype = $finfo->buffer($body) ?: 'image/jpeg';
}
if (stripos($ctype, 'image/') === false) {
	http_response_code(415);
	die('Not an image');
}

@file_put_contents($cacheFile, $body);
@file_put_contents($metaFile, json_encode(['content_type' => $ctype, 'url' => $url], JSON_UNESCAPED_UNICODE));

header('Content-Type: ' . $ctype);
header('Cache-Control: public, max-age=86400');
echo $body;
die();
