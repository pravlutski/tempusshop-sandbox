<?php
define('NO_KEEP_STATISTIC', true);
define('NOT_CHECK_PERMISSIONS', true);
define('BX_SECURITY_SESSION_READONLY', true);
define('STOP_STATISTICS', true);

require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php';

global $USER;
$debug = !empty($_GET['debug']);

function imgProxyFail(int $code, string $message, bool $debug, array $extra = []): void
{
	if ($debug) {
		header('Content-Type: application/json; charset=utf-8');
		http_response_code($code);
		echo json_encode(['ok' => false, 'error' => $message] + $extra, JSON_UNESCAPED_UNICODE);
		die();
	}
	// 1x1 transparent PNG so <img> does not loop forever; UI shows "не загрузилось"
	http_response_code($code);
	header('Content-Type: image/png');
	header('X-AI-Content-Img-Error: ' . rawurlencode(mb_substr($message, 0, 180)));
	echo base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO5W5a0AAAAASUVORK5CYII=');
	die();
}

if (!$USER || !$USER->IsAuthorized()) {
	imgProxyFail(401, 'Unauthorized', $debug);
}

$url = trim((string)($_GET['u'] ?? ''));
if ($url === '') {
	imgProxyFail(400, 'Empty url', $debug);
}
// allow already-decoded or once-encoded values
if (str_contains($url, '%')) {
	$decoded = rawurldecode($url);
	if (filter_var($decoded, FILTER_VALIDATE_URL)) {
		$url = $decoded;
	}
}
if (!filter_var($url, FILTER_VALIDATE_URL)) {
	imgProxyFail(400, 'Bad url: ' . $url, $debug);
}

$parts = parse_url($url);
$scheme = strtolower((string)($parts['scheme'] ?? ''));
if (!in_array($scheme, ['http', 'https'], true)) {
	imgProxyFail(400, 'Bad scheme', $debug);
}

$host = strtolower((string)($parts['host'] ?? ''));
if ($host === '' || $host === 'localhost' || str_ends_with($host, '.local')) {
	imgProxyFail(400, 'Host not allowed', $debug);
}
$ip = gethostbyname($host);
if ($ip && preg_match('/^(127\.|10\.|192\.168\.|172\.(1[6-9]|2\d|3[0-1])\.|0\.|169\.254\.)/', $ip)) {
	imgProxyFail(400, 'Private host not allowed', $debug);
}

require_once dirname(__DIR__) . '/classes/AiContentBootstrap.php';
AiContentBootstrap::init();

$cacheDir = rtrim((string)$_SERVER['DOCUMENT_ROOT'], '/') . '/upload/ai_content_cache';
if (!is_dir($cacheDir)) {
	@mkdir($cacheDir, 0775, true);
}
$cacheKey = sha1($url);
$cacheFile = $cacheDir . '/' . $cacheKey . '.bin';
$metaFile = $cacheDir . '/' . $cacheKey . '.json';

if (!$debug && is_file($cacheFile) && is_file($metaFile) && (time() - filemtime($cacheFile) < 86400 * 7)) {
	$meta = json_decode((string)@file_get_contents($metaFile), true) ?: [];
	$ctype = (string)($meta['content_type'] ?? 'image/jpeg');
	header('Content-Type: ' . $ctype);
	header('Cache-Control: public, max-age=86400');
	header('X-AI-Content-Img-Cache: hit');
	readfile($cacheFile);
	die();
}

$origin = $scheme . '://' . $host . '/';
$attempts = [];

$fetch = static function (string $url, array $extraOpts) use ($origin): array {
	$ch = curl_init($url);
	$opts = [
		CURLOPT_RETURNTRANSFER => true,
		CURLOPT_FOLLOWLOCATION => true,
		CURLOPT_MAXREDIRS => 5,
		CURLOPT_TIMEOUT => 30,
		CURLOPT_CONNECTTIMEOUT => 12,
		CURLOPT_SSL_VERIFYPEER => false,
		CURLOPT_SSL_VERIFYHOST => 0,
		CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.0.0 Safari/537.36',
		CURLOPT_HTTPHEADER => [
			'Accept: image/avif,image/webp,image/apng,image/*,*/*;q=0.8',
			'Accept-Language: en-US,en;q=0.9,ru;q=0.8',
			'Referer: ' . $origin,
		],
		CURLOPT_ENCODING => '',
	];
	if (defined('CURL_IPRESOLVE_V4')) {
		$opts[CURLOPT_IPRESOLVE] = CURL_IPRESOLVE_V4;
	}
	foreach ($extraOpts as $k => $v) {
		$opts[$k] = $v;
	}
	curl_setopt_array($ch, $opts);
	$body = curl_exec($ch);
	$errno = curl_errno($ch);
	$error = curl_error($ch);
	$code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
	$ctype = (string)curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
	$finalUrl = (string)curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
	curl_close($ch);
	return compact('body', 'errno', 'error', 'code', 'ctype', 'finalUrl');
};

// 1) Direct
$attempts[] = ['mode' => 'direct'] + $fetch($url, []);

// 2) Via configured HTTP proxy (same as OpenAI)
try {
	$cfg = AiContentConfig::get();
	$parsed = AiContentConfig::parseProxy((string)($cfg['proxy'] ?? ''), 'http');
	if ($parsed && empty($parsed['is_socks'])) {
		$userpwd = (string)($parsed['userpwd'] ?? '');
		$hostport = (string)$parsed['hostport'];
		$proxyOpts = [
			CURLOPT_PROXY => $userpwd !== '' ? ('http://' . $userpwd . '@' . $hostport) : ('http://' . $hostport),
			CURLOPT_HTTPPROXYTUNNEL => true,
			CURLOPT_PROXYTYPE => defined('CURLPROXY_HTTP') ? CURLPROXY_HTTP : 0,
		];
		if ($userpwd !== '') {
			$proxyOpts[CURLOPT_PROXYUSERPWD] = $userpwd;
		}
		$attempts[] = ['mode' => 'http_proxy'] + $fetch($url, $proxyOpts);
	}
} catch (Throwable $e) {
	$attempts[] = ['mode' => 'http_proxy', 'errno' => -1, 'error' => $e->getMessage(), 'code' => 0, 'body' => false, 'ctype' => '', 'finalUrl' => ''];
}

$success = null;
foreach ($attempts as $a) {
	if (!empty($a['errno']) || empty($a['body']) || (int)$a['code'] >= 400) {
		continue;
	}
	$ctype = (string)$a['ctype'];
	$body = $a['body'];
	if ($ctype === '' || stripos($ctype, 'image/') === false) {
		if (class_exists('finfo')) {
			$finfo = new finfo(FILEINFO_MIME_TYPE);
			$ctype = (string)$finfo->buffer($body);
		}
	}
	// accept octet-stream if bytes look like image
	if (stripos($ctype, 'image/') === false) {
		$sig = substr($body, 0, 4);
		if ($sig === "\xFF\xD8\xFF\xE0" || $sig === "\xFF\xD8\xFF\xE1" || str_starts_with($body, "\x89PNG") || str_starts_with($body, 'GIF8') || str_starts_with($body, 'RIFF')) {
			$ctype = str_starts_with($body, "\x89PNG") ? 'image/png' : (str_starts_with($body, 'GIF8') ? 'image/gif' : 'image/jpeg');
		} else {
			continue;
		}
	}
	$success = $a + ['ctype' => $ctype];
	break;
}

if (!$success) {
	$summary = [];
	foreach ($attempts as $a) {
		$summary[] = ($a['mode'] ?? '?') . ': http=' . ($a['code'] ?? 0) . ' errno=' . ($a['errno'] ?? 0) . ' ' . ($a['error'] ?? '');
	}
	imgProxyFail(502, 'Image fetch failed: ' . implode(' | ', $summary), $debug, [
		'url' => $url,
		'attempts' => array_map(static function ($a) {
			return [
				'mode' => $a['mode'] ?? null,
				'code' => $a['code'] ?? null,
				'errno' => $a['errno'] ?? null,
				'error' => $a['error'] ?? null,
				'ctype' => $a['ctype'] ?? null,
				'finalUrl' => $a['finalUrl'] ?? null,
				'body_len' => is_string($a['body'] ?? null) ? strlen($a['body']) : 0,
			];
		}, $attempts),
	]);
}

@file_put_contents($cacheFile, $success['body']);
@file_put_contents($metaFile, json_encode([
	'content_type' => $success['ctype'],
	'url' => $url,
	'mode' => $success['mode'],
], JSON_UNESCAPED_UNICODE));

if ($debug) {
	header('Content-Type: application/json; charset=utf-8');
	echo json_encode([
		'ok' => true,
		'url' => $url,
		'mode' => $success['mode'],
		'ctype' => $success['ctype'],
		'bytes' => strlen((string)$success['body']),
		'finalUrl' => $success['finalUrl'],
	], JSON_UNESCAPED_UNICODE);
	die();
}

header('Content-Type: ' . $success['ctype']);
header('Cache-Control: public, max-age=86400');
header('X-AI-Content-Img-Cache: miss');
header('X-AI-Content-Img-Mode: ' . $success['mode']);
echo $success['body'];
die();
