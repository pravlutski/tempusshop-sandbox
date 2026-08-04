<?php

class OpenAiClient
{
	private string $apiKey;
	private string $model;

	public function __construct(?array $config = null)
	{
		$config = $config ?: AiContentConfig::get();
		$this->apiKey = $config['api_key'];
		$this->model = $config['model'];
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

		$raw = $this->request('https://api.openai.com/v1/responses', $body);
		$text = $this->extractOutputText($raw);
		$json = $this->extractJson($text);

		return [
			'raw' => $raw,
			'text' => $text,
			'json' => $json,
		];
	}

	private function request(string $url, array $body): array
	{
		$ch = curl_init($url);
		curl_setopt_array($ch, [
			CURLOPT_POST => true,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_HTTPHEADER => [
				'Content-Type: application/json',
				'Authorization: Bearer ' . $this->apiKey,
			],
			CURLOPT_POSTFIELDS => json_encode($body, JSON_UNESCAPED_UNICODE),
			CURLOPT_TIMEOUT => 180,
		]);
		$response = curl_exec($ch);
		$errno = curl_errno($ch);
		$error = curl_error($ch);
		$code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
		curl_close($ch);

		if ($errno) {
			throw new RuntimeException('OpenAI curl error: ' . $error);
		}
		$decoded = json_decode((string)$response, true);
		if (!is_array($decoded)) {
			throw new RuntimeException('OpenAI invalid JSON response, HTTP ' . $code);
		}
		if ($code >= 400) {
			$msg = $decoded['error']['message'] ?? ('HTTP ' . $code);
			throw new RuntimeException('OpenAI API error: ' . $msg);
		}
		return $decoded;
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
