<?php

class OpenAiClient
{
	private string $apiKey;
	private string $model;
	private string $proxy;
	private string $proxyType;

	public function __construct(?array $config = null)
	{
		$config = $config ?: AiContentConfig::get();
		$this->apiKey = $config['api_key'];
		$this->model = $config['model'];
		$this->proxy = (string)($config['proxy'] ?? '');
		$this->proxyType = AiContentConfig::normalizeProxyType((string)($config['proxy_type'] ?? 'http'));
	}

	/**
	 * Call Responses API with web_search. Returns decoded payload + raw text.
	 */
	public function researchJson(string $system, string $user): array
	{
		$body = [
			'model' => $this->model,
			'tools' => [
				['type' => 'web_search'],
			],
			'tool_choice' => 'auto',
			'input' => [
				[
					'role' => 'system',
					'content' => [
						['type' => 'input_text', 'text' => $system],
					],
				],
				[
					'role' => 'user',
					'content' => [
						['type' => 'input_text', 'text' => $user],
					],
				],
			],
		];

		$raw = $this->request('https://api.openai.com/v1/responses', $body, 150);
		$text = $this->extractOutputText($raw);
		$json = $this->extractJson($text);

		return [
			'raw' => $raw,
			'text' => $text,
			'json' => $json,
		];
	}

	public function ping(): array
	{
		$parsed = AiContentConfig::parseProxy($this->proxy, $this->proxyType);
		if (!$parsed) {
			throw new RuntimeException('Прокси не разобран. Пример: user:pass@host:port + type http');
		}

		$usedType = $this->proxyType;
		$ipRes = $this->curlWithFallback('https://api.ipify.org?format=json', [
			CURLOPT_HTTPGET => true,
			CURLOPT_TIMEOUT => 20,
			CURLOPT_CONNECTTIMEOUT => 10,
		], $usedType);

		if ($ipRes['errno']) {
			throw new RuntimeException(
				'Прокси не отвечает (ipify): ' . $ipRes['error']
				. ' | proxy=' . $parsed['hostport']
				. ' | type_tried=' . implode(',', $ipRes['types_tried'] ?? [$usedType])
				. ' | curl_errno=' . $ipRes['errno']
			);
		}

		$ipJson = json_decode((string)$ipRes['body'], true);
		$exit = is_array($ipJson) ? (string)($ipJson['ip'] ?? '') : trim((string)$ipRes['body']);
		if ($exit === '') {
			throw new RuntimeException('Пустой ответ ipify, HTTP ' . $ipRes['code']);
		}

		$oaiType = $ipRes['type_used'] ?? $usedType;
		$oai = $this->curlWithFallback('https://api.openai.com/v1/models', [
			CURLOPT_HTTPGET => true,
			CURLOPT_HTTPHEADER => [
				'Authorization: Bearer ' . $this->apiKey,
			],
			CURLOPT_TIMEOUT => 30,
			CURLOPT_CONNECTTIMEOUT => 12,
		], $oaiType);

		if ($oai['errno']) {
			throw new RuntimeException(
				'OpenAI через прокси: ' . $oai['error']
				. ' | exit_ip=' . $exit
				. ' | type_tried=' . implode(',', $oai['types_tried'] ?? [])
			);
		}

		$decoded = json_decode((string)$oai['body'], true);
		if ($oai['code'] >= 400) {
			$msg = is_array($decoded) ? ($decoded['error']['message'] ?? ('HTTP ' . $oai['code'])) : ('HTTP ' . $oai['code']);
			throw new RuntimeException(
				'OpenAI API error: ' . $msg
				. ' | exit_ip=' . $exit
				. ' | type=' . ($oai['type_used'] ?? '?')
			);
		}

		return [
			'ok' => true,
			'http_code' => $oai['code'],
			'proxy' => true,
			'proxy_host' => $parsed['hostport'],
			'proxy_type' => $oai['type_used'] ?? $parsed['type_name'],
			'exit_ip' => $exit,
			'primary_ip' => $oai['primary_ip'] ?? '',
			'note' => !empty($oai['type_used']) && $oai['type_used'] !== $this->proxyType
				? "Сработал type={$oai['type_used']} (в настройках было {$this->proxyType}). Сохрани http."
				: null,
		];
	}

	/** Step diagnostics for settings UI */
	public function diagnose(): array
	{
		$steps = [];
		$parsed = AiContentConfig::parseProxy($this->proxy, $this->proxyType);
		$steps[] = [
			'step' => 'parse',
			'ok' => (bool)$parsed,
			'detail' => $parsed ?: 'parse failed',
			'saved_type' => $this->proxyType,
		];
		if (!$parsed) {
			return ['ok' => false, 'steps' => $steps];
		}

		$usedType = $this->proxyType;
		$ip = $this->curlWithFallback('https://api.ipify.org?format=json', [
			CURLOPT_HTTPGET => true,
			CURLOPT_TIMEOUT => 20,
			CURLOPT_CONNECTTIMEOUT => 10,
		], $usedType);
		$ipJson = json_decode((string)$ip['body'], true);
		$exit = is_array($ipJson) ? (string)($ipJson['ip'] ?? '') : '';
		$steps[] = [
			'step' => 'ipify_via_proxy',
			'ok' => !$ip['errno'] && $exit !== '',
			'exit_ip' => $exit,
			'error' => $ip['error'],
			'errno' => $ip['errno'],
			'http' => $ip['code'],
			'type_used' => $ip['type_used'] ?? null,
			'types_tried' => $ip['types_tried'] ?? [],
			'ms' => $ip['ms'] ?? null,
		];

		$oai = $this->curlWithFallback('https://api.openai.com/v1/models', [
			CURLOPT_HTTPGET => true,
			CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $this->apiKey],
			CURLOPT_TIMEOUT => 30,
			CURLOPT_CONNECTTIMEOUT => 12,
		], $ip['type_used'] ?? 'http');
		$decoded = json_decode((string)$oai['body'], true);
		$oaiMsg = is_array($decoded) ? ($decoded['error']['message'] ?? null) : null;
		$steps[] = [
			'step' => 'openai_models',
			'ok' => !$oai['errno'] && $oai['code'] > 0 && $oai['code'] < 500 && $oai['code'] !== 403,
			'http' => $oai['code'],
			'error' => $oai['error'] ?: $oaiMsg,
			'errno' => $oai['errno'],
			'type_used' => $oai['type_used'] ?? null,
			'types_tried' => $oai['types_tried'] ?? [],
			'ms' => $oai['ms'] ?? null,
			'geo_blocked' => is_string($oaiMsg) && (stripos($oaiMsg, 'Country') !== false || stripos($oaiMsg, 'territory') !== false),
		];

		$ok = !empty($steps[1]['ok']) && !empty($steps[2]['ok']);
		return ['ok' => $ok, 'steps' => $steps];
	}

	private function request(string $url, array $body, int $timeout = 120): array
	{
		$res = $this->curlWithFallback($url, [
			CURLOPT_POST => true,
			CURLOPT_HTTPHEADER => [
				'Content-Type: application/json',
				'Authorization: Bearer ' . $this->apiKey,
			],
			CURLOPT_POSTFIELDS => json_encode($body, JSON_UNESCAPED_UNICODE),
			CURLOPT_TIMEOUT => $timeout,
			CURLOPT_CONNECTTIMEOUT => 20,
		], $this->proxyType);

		if ($res['errno']) {
			throw new RuntimeException(
				'OpenAI curl error: ' . $res['error']
				. ' | types_tried=' . implode(',', $res['types_tried'] ?? [])
			);
		}
		$decoded = json_decode((string)$res['body'], true);
		if (!is_array($decoded)) {
			throw new RuntimeException('OpenAI invalid JSON response, HTTP ' . $res['code']);
		}
		if ($res['code'] >= 400) {
			$msg = $decoded['error']['message'] ?? ('HTTP ' . $res['code']);
			throw new RuntimeException('OpenAI API error: ' . $msg);
		}
		return $decoded;
	}

	/**
	 * Try requested proxy type, then HTTP fallback (common mislabel as SOCKS5).
	 */
	private function curlWithFallback(string $url, array $opts, string $preferredType): array
	{
		$types = [];
		$preferredType = AiContentConfig::normalizeProxyType($preferredType);
		$types[] = $preferredType;
		foreach (['http', 'socks5h'] as $alt) {
			if (!in_array($alt, $types, true)) {
				$types[] = $alt;
			}
		}

		$tried = [];
		$last = [
			'body' => '',
			'errno' => 1,
			'error' => 'no attempts',
			'code' => 0,
			'primary_ip' => '',
			'types_tried' => [],
			'ms' => 0,
		];

		foreach ($types as $type) {
			$tried[] = $type;
			$start = microtime(true);
			$res = $this->curlRaw($url, $opts, $type);
			$res['ms'] = (int)round((microtime(true) - $start) * 1000);
			$res['type_used'] = $type;
			$res['types_tried'] = $tried;
			$last = $res;

			if (!$res['errno'] && $res['code'] > 0) {
				return $res;
			}
			// Connection timeout / proxy failures → try next type
			if (in_array((int)$res['errno'], [6, 7, 28, 56, 97], true)) {
				continue;
			}
			// Other errors: still try http once if not yet
			continue;
		}

		$last['types_tried'] = $tried;
		return $last;
	}

	private function curlRaw(string $url, array $opts, string $proxyType): array
	{
		$ch = curl_init($url);
		$base = [
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_FOLLOWLOCATION => true,
			CURLOPT_SSL_VERIFYPEER => true,
			CURLOPT_SSL_VERIFYHOST => 2,
			CURLOPT_TIMEOUT => 30,
			CURLOPT_CONNECTTIMEOUT => 12,
			CURLOPT_NOSIGNAL => true,
		];
		if (defined('CURL_IPRESOLVE_V4')) {
			$base[CURLOPT_IPRESOLVE] = CURL_IPRESOLVE_V4;
		}
		foreach ($opts as $k => $v) {
			$base[$k] = $v;
		}
		$this->applyProxy($base, $proxyType);
		curl_setopt_array($ch, $base);
		$body = curl_exec($ch);
		$errno = curl_errno($ch);
		$error = curl_error($ch);
		$code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
		$primaryIp = (string)curl_getinfo($ch, CURLINFO_PRIMARY_IP);
		curl_close($ch);

		return [
			'body' => $body === false ? '' : $body,
			'errno' => $errno,
			'error' => $error,
			'code' => $code,
			'primary_ip' => $primaryIp,
		];
	}

	private function applyProxy(array &$opts, string $proxyType): void
	{
		$parsed = AiContentConfig::parseProxy($this->proxy, $proxyType);
		if (!$parsed) {
			return;
		}

		$isSocks = !empty($parsed['is_socks']) || str_starts_with((string)$parsed['type_name'], 'socks');
		$userpwd = (string)($parsed['userpwd'] ?? '');
		$hostport = (string)$parsed['hostport'];

		if ($isSocks) {
			$opts[CURLOPT_PROXY] = $hostport;
			$opts[CURLOPT_PROXYTYPE] = defined('CURLPROXY_SOCKS5_HOSTNAME')
				? CURLPROXY_SOCKS5_HOSTNAME
				: (defined('CURLPROXY_SOCKS5') ? CURLPROXY_SOCKS5 : 5);
			if ($userpwd !== '') {
				$opts[CURLOPT_PROXYUSERPWD] = $userpwd;
			}
		} else {
			// Full URL form is most reliable for HTTP CONNECT to HTTPS targets
			if ($userpwd !== '') {
				$opts[CURLOPT_PROXY] = 'http://' . $userpwd . '@' . $hostport;
			} else {
				$opts[CURLOPT_PROXY] = 'http://' . $hostport;
			}
			$opts[CURLOPT_PROXYTYPE] = defined('CURLPROXY_HTTP') ? CURLPROXY_HTTP : 0;
			$opts[CURLOPT_HTTPPROXYTUNNEL] = true;
			$opts[CURLOPT_PROXYAUTH] = CURLAUTH_ANY;
			// Also set USERPWD for libcurls that ignore creds in PROXY URL
			if ($userpwd !== '') {
				$opts[CURLOPT_PROXYUSERPWD] = $userpwd;
			}
		}
	}

	private function extractOutputText(array $raw): string
	{
		if (!empty($raw['output_text']) && is_string($raw['output_text'])) {
			return $raw['output_text'];
		}
		$parts = [];
		foreach (($raw['output'] ?? []) as $item) {
			if (($item['type'] ?? '') !== 'message') {
				continue;
			}
			foreach (($item['content'] ?? []) as $content) {
				if (($content['type'] ?? '') === 'output_text' && !empty($content['text'])) {
					$parts[] = $content['text'];
				}
			}
		}
		return trim(implode("\n", $parts));
	}

	private function extractJson(string $text): ?array
	{
		$text = trim($text);
		if ($text === '') {
			return null;
		}
		$decoded = json_decode($text, true);
		if (is_array($decoded)) {
			return $decoded;
		}
		if (preg_match('/\{.*\}/s', $text, $m)) {
			$decoded = json_decode($m[0], true);
			if (is_array($decoded)) {
				return $decoded;
			}
		}
		return null;
	}
}
