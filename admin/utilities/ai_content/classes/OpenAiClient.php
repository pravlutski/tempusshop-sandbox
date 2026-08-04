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

		$raw = $this->request('https://api.openai.com/v1/responses', $body, 120);
		$text = $this->extractOutputText($raw);
		$json = $this->extractJson($text);

		return [
			'raw' => $raw,
			'text' => $text,
			'json' => $json,
		];
	}

	/**
	 * Fast multi-step check: parse → exit IP via proxy → OpenAI models.
	 */
	public function ping(): array
	{
		$parsed = AiContentConfig::parseProxy($this->proxy, $this->proxyType);
		if (!$parsed) {
			throw new RuntimeException('Прокси не разобран. Пример: user:pass@host:port + type socks5');
		}

		// 1) Quick exit-IP check through proxy (short timeouts)
		$ipRes = $this->curlRaw('https://api.ipify.org?format=json', [
			CURLOPT_HTTPGET => true,
			CURLOPT_TIMEOUT => 15,
			CURLOPT_CONNECTTIMEOUT => 8,
		], true);

		$exit = '';
		if ($ipRes['errno']) {
			throw new RuntimeException(
				'Прокси не отвечает (ipify): ' . $ipRes['error']
				. ' | proxy=' . $parsed['hostport']
				. ' | type=' . $parsed['type_name']
				. ' | curl_errno=' . $ipRes['errno']
			);
		}
		$ipJson = json_decode((string)$ipRes['body'], true);
		$exit = is_array($ipJson) ? (string)($ipJson['ip'] ?? '') : trim((string)$ipRes['body']);
		if ($exit === '') {
			throw new RuntimeException(
				'Прокси ответил пусто на ipify, HTTP ' . $ipRes['code']
				. ' | proxy=' . $parsed['hostport']
			);
		}

		// 2) OpenAI through same proxy
		$oai = $this->curlRaw('https://api.openai.com/v1/models', [
			CURLOPT_HTTPGET => true,
			CURLOPT_HTTPHEADER => [
				'Authorization: Bearer ' . $this->apiKey,
			],
			CURLOPT_TIMEOUT => 25,
			CURLOPT_CONNECTTIMEOUT => 10,
		], true);

		if ($oai['errno']) {
			throw new RuntimeException(
				'OpenAI через прокси: curl ' . $oai['error']
				. ' | exit_ip=' . $exit
				. ' | proxy=' . $parsed['hostport']
				. ' | type=' . $parsed['type_name']
			);
		}

		$decoded = json_decode((string)$oai['body'], true);
		if ($oai['code'] >= 400) {
			$msg = is_array($decoded) ? ($decoded['error']['message'] ?? ('HTTP ' . $oai['code'])) : ('HTTP ' . $oai['code']);
			throw new RuntimeException(
				'OpenAI API error: ' . $msg
				. ' | exit_ip=' . $exit
				. ' | proxy=' . $parsed['hostport']
				. ' | type=' . $parsed['type_name']
			);
		}

		return [
			'ok' => true,
			'http_code' => $oai['code'],
			'proxy' => true,
			'proxy_host' => $parsed['hostport'],
			'proxy_type' => $parsed['type_name'],
			'exit_ip' => $exit,
			'primary_ip' => $oai['primary_ip'],
		];
	}

	private function request(string $url, array $body, int $timeout = 120): array
	{
		$res = $this->curlRaw($url, [
			CURLOPT_POST => true,
			CURLOPT_HTTPHEADER => [
				'Content-Type: application/json',
				'Authorization: Bearer ' . $this->apiKey,
			],
			CURLOPT_POSTFIELDS => json_encode($body, JSON_UNESCAPED_UNICODE),
			CURLOPT_TIMEOUT => $timeout,
			CURLOPT_CONNECTTIMEOUT => 15,
		], true);

		if ($res['errno']) {
			throw new RuntimeException('OpenAI curl error: ' . $res['error']);
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

	private function curlRaw(string $url, array $opts, bool $withProxy): array
	{
		$ch = curl_init($url);
		$base = [
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_FOLLOWLOCATION => true,
			CURLOPT_SSL_VERIFYPEER => true,
			CURLOPT_SSL_VERIFYHOST => 2,
			CURLOPT_TIMEOUT => 30,
			CURLOPT_CONNECTTIMEOUT => 10,
			CURLOPT_NOSIGNAL => true,
		];
		foreach ($opts as $k => $v) {
			$base[$k] = $v;
		}
		if ($withProxy) {
			$this->applyProxy($base);
		}
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

	private function applyProxy(array &$opts): void
	{
		$parsed = AiContentConfig::parseProxy($this->proxy, $this->proxyType);
		if (!$parsed) {
			return;
		}

		$isSocks = !empty($parsed['is_socks']) || str_starts_with((string)$parsed['type_name'], 'socks');

		if ($isSocks) {
			// Separate host/auth/type — most reliable on older libcurl
			$opts[CURLOPT_PROXY] = $parsed['hostport'];
			if (defined('CURLPROXY_SOCKS5_HOSTNAME')) {
				$opts[CURLOPT_PROXYTYPE] = CURLPROXY_SOCKS5_HOSTNAME;
			} else {
				$opts[CURLOPT_PROXYTYPE] = defined('CURLPROXY_SOCKS5') ? CURLPROXY_SOCKS5 : 5;
			}
			if (!empty($parsed['userpwd'])) {
				$opts[CURLOPT_PROXYUSERPWD] = $parsed['userpwd'];
			}
			// Do NOT set HTTPPROXYTUNNEL for SOCKS
		} else {
			$opts[CURLOPT_PROXY] = $parsed['hostport'];
			$opts[CURLOPT_PROXYTYPE] = $parsed['type'];
			$opts[CURLOPT_HTTPPROXYTUNNEL] = true;
			$opts[CURLOPT_PROXYAUTH] = CURLAUTH_ANY;
			if (!empty($parsed['userpwd'])) {
				$opts[CURLOPT_PROXYUSERPWD] = $parsed['userpwd'];
			}
			if (($parsed['type_name'] ?? '') === 'https') {
				if (defined('CURLOPT_PROXY_SSL_VERIFYPEER')) {
					$opts[CURLOPT_PROXY_SSL_VERIFYPEER] = false;
				}
				if (defined('CURLOPT_PROXY_SSL_VERIFYHOST')) {
					$opts[CURLOPT_PROXY_SSL_VERIFYHOST] = 0;
				}
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
