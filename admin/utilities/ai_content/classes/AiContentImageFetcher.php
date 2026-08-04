<?php

class AiContentImageFetcher
{
	/**
	 * Download URL and return image bytes if it is a real image.
	 */
	public function fetch(string $url): array
	{
		$results = $this->fetchMany([$url]);
		return $results[$url] ?? ['ok' => false, 'error' => 'fetch failed'];
	}

	/**
	 * Keep only URLs that download as real images; cache under /upload/.
	 */
	public function filterValidPhotos(array $photos, int $limit = 12): array
	{
		$candidates = [];
		foreach ($photos as $ph) {
			$url = is_array($ph) ? (string)($ph['url'] ?? '') : (string)$ph;
			$url = trim($url);
			if ($url === '' || !filter_var($url, FILTER_VALIDATE_URL)) {
				continue;
			}
			if (preg_match('#https?://[^\s\)\"\']+#i', $url, $m)) {
				$url = rtrim($m[0], '.,;)');
			}
			$candidates[$url] = is_array($ph) ? ($ph + ['url' => $url]) : ['url' => $url, 'source' => 'other', 'rank' => 99];
		}
		if (!$candidates) {
			return [];
		}

		// Cap attempts to avoid long hangs
		$urls = array_slice(array_keys($candidates), 0, 20);
		$fetched = $this->fetchMany($urls);

		$out = [];
		foreach ($urls as $url) {
			$res = $fetched[$url] ?? null;
			if (!$res || empty($res['ok'])) {
				continue;
			}
			$local = $this->cacheLocal($url, $res['body'], $res['content_type']);
			$item = $candidates[$url];
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

	/**
	 * Parallel fetch via curl_multi. Tries direct then HTTP proxy per URL.
	 */
	public function fetchMany(array $urls): array
	{
		$urls = array_values(array_unique(array_filter(array_map('strval', $urls))));
		$result = [];
		foreach ($urls as $url) {
			$result[$url] = ['ok' => false, 'error' => 'pending'];
		}
		if (!$urls) {
			return $result;
		}

		$proxyOpts = $this->proxyOpts();

		// Pass 1: direct (parallel)
		$direct = $this->multiRequest($urls, []);
		$needProxy = [];
		foreach ($urls as $url) {
			$parsed = $this->acceptImage($direct[$url] ?? null, $url);
			if ($parsed) {
				$result[$url] = $parsed;
			} else {
				$needProxy[] = $url;
			}
		}

		// Pass 2: via HTTP proxy for failures
		if ($needProxy && $proxyOpts) {
			$viaProxy = $this->multiRequest($needProxy, $proxyOpts);
			foreach ($needProxy as $url) {
				$parsed = $this->acceptImage($viaProxy[$url] ?? null, $url, 'http_proxy');
				if ($parsed) {
					$result[$url] = $parsed;
				} else {
					$a = $direct[$url] ?? [];
					$b = $viaProxy[$url] ?? [];
					$result[$url] = [
						'ok' => false,
						'error' => 'direct:http=' . ($a['code'] ?? 0) . '; proxy:http=' . ($b['code'] ?? 0),
					];
				}
			}
		} else {
			foreach ($needProxy as $url) {
				$a = $direct[$url] ?? [];
				$result[$url] = [
					'ok' => false,
					'error' => 'direct:http=' . ($a['code'] ?? 0) . ' ' . ($a['error'] ?? ''),
				];
			}
		}

		return $result;
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

	private function proxyOpts(): array
	{
		try {
			$cfg = AiContentConfig::get();
			$parsed = AiContentConfig::parseProxy((string)($cfg['proxy'] ?? ''), 'http');
			if (!$parsed || !empty($parsed['is_socks'])) {
				return [];
			}
			$userpwd = (string)($parsed['userpwd'] ?? '');
			$hostport = (string)$parsed['hostport'];
			$opts = [
				CURLOPT_PROXY => $userpwd !== '' ? ('http://' . $userpwd . '@' . $hostport) : ('http://' . $hostport),
				CURLOPT_HTTPPROXYTUNNEL => true,
				CURLOPT_PROXYTYPE => defined('CURLPROXY_HTTP') ? CURLPROXY_HTTP : 0,
			];
			if ($userpwd !== '') {
				$opts[CURLOPT_PROXYUSERPWD] = $userpwd;
			}
			return $opts;
		} catch (Throwable $e) {
			return [];
		}
	}

	private function multiRequest(array $urls, array $extraOpts): array
	{
		$mh = curl_multi_init();
		$handles = [];
		$out = [];

		foreach ($urls as $url) {
			$parts = parse_url($url);
			$origin = strtolower((string)($parts['scheme'] ?? 'https')) . '://' . strtolower((string)($parts['host'] ?? '')) . '/';
			$ch = curl_init($url);
			$opts = [
				CURLOPT_RETURNTRANSFER => true,
				CURLOPT_FOLLOWLOCATION => true,
				CURLOPT_MAXREDIRS => 2,
				CURLOPT_TIMEOUT => 12,
				CURLOPT_CONNECTTIMEOUT => 6,
				CURLOPT_SSL_VERIFYPEER => false,
				CURLOPT_SSL_VERIFYHOST => 0,
				CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.0.0 Safari/537.36',
				CURLOPT_HTTPHEADER => [
					'Accept: image/avif,image/webp,image/apng,image/*,*/*;q=0.8',
					'Referer: ' . $origin,
				],
				CURLOPT_ENCODING => '',
				CURLOPT_NOSIGNAL => true,
			];
			if (defined('CURL_IPRESOLVE_V4')) {
				$opts[CURLOPT_IPRESOLVE] = CURL_IPRESOLVE_V4;
			}
			foreach ($extraOpts as $k => $v) {
				$opts[$k] = $v;
			}
			curl_setopt_array($ch, $opts);
			curl_multi_add_handle($mh, $ch);
			$handles[(int)$ch] = ['ch' => $ch, 'url' => $url];
		}

		$running = null;
		do {
			$code = curl_multi_exec($mh, $running);
			if ($running) {
				curl_multi_select($mh, 1.0);
			}
		} while ($running && $code === CURLM_OK);

		foreach ($handles as $item) {
			$ch = $item['ch'];
			$url = $item['url'];
			$body = curl_multi_getcontent($ch);
			$out[$url] = [
				'body' => is_string($body) ? $body : '',
				'errno' => curl_errno($ch),
				'error' => curl_error($ch),
				'code' => (int)curl_getinfo($ch, CURLINFO_HTTP_CODE),
				'ctype' => (string)curl_getinfo($ch, CURLINFO_CONTENT_TYPE),
				'finalUrl' => (string)curl_getinfo($ch, CURLINFO_EFFECTIVE_URL),
			];
			curl_multi_remove_handle($mh, $ch);
			curl_close($ch);
		}
		curl_multi_close($mh);
		return $out;
	}

	private function acceptImage(?array $a, string $url, string $mode = 'direct'): ?array
	{
		if (!$a || !empty($a['errno']) || (int)$a['code'] >= 400 || empty($a['body'])) {
			return null;
		}
		$ctype = strtolower((string)$a['ctype']);
		if (str_contains($ctype, 'text/html')) {
			return null;
		}
		$kind = $this->detectImage((string)$a['body'], $ctype);
		if (!$kind) {
			return null;
		}
		// Reject tiny bodies / homepage hijacks
		if (strlen($a['body']) < 2000 && !str_contains($kind, 'gif')) {
			// allow small but real images >= 1KB
			if (strlen($a['body']) < 1000) {
				return null;
			}
		}
		$finalHost = strtolower((string)(parse_url((string)$a['finalUrl'], PHP_URL_HOST) ?? ''));
		$origHost = strtolower((string)(parse_url($url, PHP_URL_HOST) ?? ''));
		if ($finalHost && $origHost && $finalHost !== $origHost && str_contains($ctype, 'html')) {
			return null;
		}
		return [
			'ok' => true,
			'body' => $a['body'],
			'content_type' => $kind,
			'mode' => $mode,
			'final_url' => $a['finalUrl'],
			'bytes' => strlen($a['body']),
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
