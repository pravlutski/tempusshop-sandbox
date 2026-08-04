<?php

class AiContentImageFetcher
{
	/**
	 * Download URL and return image bytes if it is a real image.
	 * Tries direct then HTTP proxy. Does not accept HTML/redirect-to-homepage.
	 */
	public function fetch(string $url): array
	{
		$url = trim($url);
		if (!filter_var($url, FILTER_VALIDATE_URL)) {
			return ['ok' => false, 'error' => 'bad url'];
		}

		$parts = parse_url($url);
		$origin = strtolower((string)($parts['scheme'] ?? 'https')) . '://' . strtolower((string)($parts['host'] ?? '')) . '/';

		$attempts = [];
		$attempts[] = ['mode' => 'direct'] + $this->curl($url, $origin, []);

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
				$attempts[] = ['mode' => 'http_proxy'] + $this->curl($url, $origin, $proxyOpts);
			}
		} catch (Throwable $e) {
			// ignore
		}

		foreach ($attempts as $a) {
			if (!empty($a['errno']) || (int)$a['code'] >= 400 || empty($a['body'])) {
				continue;
			}
			// Reject redirects that left original host and returned HTML homepage
			$finalHost = strtolower((string)(parse_url((string)$a['finalUrl'], PHP_URL_HOST) ?? ''));
			$origHost = strtolower((string)($parts['host'] ?? ''));
			$ctype = strtolower((string)$a['ctype']);
			if (str_contains($ctype, 'text/html')) {
				continue;
			}
			$kind = $this->detectImage($a['body'], $ctype);
			if (!$kind) {
				continue;
			}
			// If host changed AND body is tiny HTML-like, skip (already handled)
			if ($finalHost && $origHost && $finalHost !== $origHost && strlen($a['body']) < 8000 && !$kind) {
				continue;
			}
			return [
				'ok' => true,
				'body' => $a['body'],
				'content_type' => $kind,
				'mode' => $a['mode'],
				'final_url' => $a['finalUrl'],
				'bytes' => strlen($a['body']),
			];
		}

		$summary = [];
		foreach ($attempts as $a) {
			$summary[] = ($a['mode'] ?? '?') . ':http=' . ($a['code'] ?? 0);
		}
		return ['ok' => false, 'error' => implode(',', $summary), 'attempts' => $attempts];
	}

	/** Keep only URLs that download as real images; optionally cache under upload/ */
	public function filterValidPhotos(array $photos, int $limit = 12): array
	{
		$out = [];
		foreach ($photos as $ph) {
			$url = is_array($ph) ? (string)($ph['url'] ?? '') : (string)$ph;
			$url = trim($url);
			if ($url === '') {
				continue;
			}
			$res = $this->fetch($url);
			if (empty($res['ok'])) {
				continue;
			}
			$local = $this->cacheLocal($url, $res['body'], $res['content_type']);
			$item = is_array($ph) ? $ph : ['url' => $url, 'source' => 'other', 'rank' => 99];
			$item['url'] = $url;
			$item['validated'] = true;
			$item['content_type'] = $res['content_type'];
			$item['fetch_mode'] = $res['mode'];
			if ($local) {
				$item['local_url'] = $local;
			}
			$out[] = $item;
			if (count($out) >= $limit) {
				break;
			}
		}
		return $out;
	}

	public function cacheLocal(string $sourceUrl, string $body, string $contentType): ?string
	{
		$root = rtrim((string)$_SERVER['DOCUMENT_ROOT'], '/');
		$dir = $root . '/upload/ai_content_images';
		if (!is_dir($dir)) {
			@mkdir($dir, 0775, true);
		}
		$ext = 'jpg';
		if (str_contains($contentType, 'png')) {
			$ext = 'png';
		} elseif (str_contains($contentType, 'webp')) {
			$ext = 'webp';
		} elseif (str_contains($contentType, 'gif')) {
			$ext = 'gif';
		}
		$name = sha1($sourceUrl) . '.' . $ext;
		$path = $dir . '/' . $name;
		if (@file_put_contents($path, $body) === false) {
			return null;
		}
		return '/upload/ai_content_images/' . $name;
	}

	private function curl(string $url, string $origin, array $extra): array
	{
		$ch = curl_init($url);
		$opts = [
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_FOLLOWLOCATION => true,
			CURLOPT_MAXREDIRS => 3,
			CURLOPT_TIMEOUT => 25,
			CURLOPT_CONNECTTIMEOUT => 10,
			CURLOPT_SSL_VERIFYPEER => false,
			CURLOPT_SSL_VERIFYHOST => 0,
			CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.0.0 Safari/537.36',
			CURLOPT_HTTPHEADER => [
				'Accept: image/avif,image/webp,image/apng,image/*,*/*;q=0.8',
				'Referer: ' . $origin,
			],
			CURLOPT_ENCODING => '',
		];
		if (defined('CURL_IPRESOLVE_V4')) {
			$opts[CURLOPT_IPRESOLVE] = CURL_IPRESOLVE_V4;
		}
		foreach ($extra as $k => $v) {
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
		return [
			'body' => $body === false ? '' : $body,
			'errno' => $errno,
			'error' => $error,
			'code' => $code,
			'ctype' => $ctype,
			'finalUrl' => $finalUrl,
		];
	}

	private function detectImage(string $body, string $ctype): ?string
	{
		if (strlen($body) < 100) {
			return null;
		}
		if (str_contains($ctype, 'image/jpeg') || str_contains($ctype, 'image/jpg')) {
			return 'image/jpeg';
		}
		if (str_contains($ctype, 'image/png')) {
			return 'image/png';
		}
		if (str_contains($ctype, 'image/webp')) {
			return 'image/webp';
		}
		if (str_contains($ctype, 'image/gif')) {
			return 'image/gif';
		}
		if (str_starts_with($body, "\xFF\xD8\xFF")) {
			return 'image/jpeg';
		}
		if (str_starts_with($body, "\x89PNG")) {
			return 'image/png';
		}
		if (str_starts_with($body, 'GIF8')) {
			return 'image/gif';
		}
		if (str_starts_with($body, 'RIFF') && str_contains(substr($body, 0, 16), 'WEBP')) {
			return 'image/webp';
		}
		return null;
	}
}
