<?
/**
 * Send errors in telegram
 */
class TsTriggers {
    private $Logger; // logger
    private $adminTelegram = [171773786, 2044238, 1840836434, 957953262, -4636535496]; // users id whom will be send message
    private $token = '5530466584:AAGb6Zcxow_PliiJFMrH_4PqCIVOvbrpZI8'; // telegram bot token
    private $arData = []; // all data
    private $site = "tempus.by";
    private $timeout = 10;
    private $totalTimeout = 30;
	private $useProxy = true; // если разблокируют телегу то false
	private $proxyUrl = 'http://45.135.234.186/api/send_tg.php';
    
	private $tokenType = 'notify'; // чат TempusNotify
	
    public function __construct () {
        $this->Logger = new TsLogger("/triggers/");
    }

    /**
     * set the errors
     * @param array $error error messages
     *
     * @return bool
     */
    public function SetError($errors = []) {
        $this->Logger->log("LOG", "Try to set trigger errors");

        if (empty($errors)) {
            $this->Logger->log("ERROR", "Error array is empty");
            return false;
        }

        foreach ($errors as $error) {
			if($error){
				$this->arData["ERRORS"][] = $this->site.": ".$error;
				$this->Logger->log("LOG", "Set new error '".$error."'");
			}
        }

        $this->Logger->log("LOG", "All errors are setted");

        return true;
    }

    public function SetMessage($arMessage = []) {
        $this->Logger->log("LOG", "Try to set trigger message");

        if (empty($arMessage)) {
            $this->Logger->log("ERROR", "Empty message");
            return false;
        }

        foreach ($arMessage as $message) {
			if($message){
				$this->arData["MESSAGES"][] = $this->site.": ".$message;
				$this->Logger->log("LOG", "Set new message '".$message."'");
			}
        }

        $this->Logger->log("LOG", "All messages are setted");

        return true;
    }

    /**
     * return all current errors
     * @return mixed
     */
    public function GetErrors() {
        return $this->arData["ERRORS"];
    }


    /**
     * send all messages and delete
     * @return bool
     */
    public function SendTriggerErrors($userId = 0) {

        $this->Logger->log("LOG", "Try to send errors in telegram");

        if (empty($this->arData["ERRORS"])) {
            $this->Logger->log("ERROR", "Error array is empty");
            return false;
        }

        $text = '';

        foreach ($this->arData["ERRORS"] as $error) {
            if (!empty(trim($error))) {
                $text .= $error."\r\n";
            }
        }

        if (!empty($text)) {
            foreach ($this->adminTelegram as $telegramId) {
                if (!empty($userId) && $userId != $telegramId) {
                    continue;
                }

                $response = $this->sendTelegramRequest($telegramId, $text);
                
                if (!empty($response['error'])) {
                    $this->Logger->log("ERROR", "CURL Error: " . $response['error'] . " (Errno: " . $response['errno'] . ")");
                    
                    if (in_array($response['errno'], [CURLE_OPERATION_TIMEDOUT, CURLE_COULDNT_CONNECT])) {
                        $this->Logger->log("ERROR", "Connection timeout or failed. Skipping chat_id: " . $telegramId);
                        continue;
                    }
                    
                    return false;
                }

                if (!strlen($response['result'])) {
                    $this->Logger->log("ERROR", "Empty response from proxy/telegram for chat_id: " . $telegramId);
                    $this->Logger->log("ERROR", "HTTP Code: " . $response['http_code']);
                    continue;
                }

                $responsive = json_decode($response['result'], true);
                
                if (json_last_error() !== JSON_ERROR_NONE) {
                    $this->Logger->log("ERROR", "Invalid JSON response: " . json_last_error_msg());
                    continue;
                }

                $this->Logger->log("LOG", "Response: " . print_r($responsive, true));

                if (isset($responsive['success']) && $responsive['success'] === true) {
                    $this->Logger->log("LOG", "Message sent successfully to chat_id: " . $telegramId);
                } elseif (isset($responsive['ok']) && $responsive['ok'] === true) {
                    $this->Logger->log("LOG", "Message sent successfully to chat_id: " . $telegramId);
                } else {
                    $this->Logger->log("ERROR", "API error: " . ($responsive['description'] ?? $responsive['error'] ?? 'Unknown error'));
                }
            }
			$this->arData["ERRORS"] = null;
        }

        return true;
    }

    public function SendAll($userId = 0) {
        $this->Logger->log("LOG", "Try to send messages in telegram");

        $text = '';

        if ($this->arData["ERRORS"]) {
            foreach ($this->arData["ERRORS"] as $error) {
                if (!empty(trim($error))) {
                    $text .= $error."\r\n";
                }
            }
        }

        if ($this->arData["MESSAGES"]) {
            foreach ($this->arData["MESSAGES"] as $message) {
                if (!empty(trim($message))) {
                    $text .= $message."\r\n";
                }
            }
        }

        if (!empty($text)) {
            foreach ($this->adminTelegram as $telegramId) {
                if (!empty($userId) && $userId != $telegramId) {
                    continue;
                }

				$response = $this->sendTelegramRequest($telegramId, $text);
                
                if (!empty($response['error'])) {
                    $this->Logger->log("ERROR", "CURL Error: " . $response['error'] . " (Errno: " . $response['errno'] . ")");
                    
                    if (in_array($response['errno'], [CURLE_OPERATION_TIMEDOUT, CURLE_COULDNT_CONNECT])) {
                        $this->Logger->log("ERROR", "Connection timeout or failed. Skipping chat_id: " . $telegramId);
                        continue;
                    }
                    
                    return false;
                }

                if (!strlen($response['result'])) {
                    $this->Logger->log("ERROR", "Empty response from proxy/telegram for chat_id: " . $telegramId);
                    $this->Logger->log("ERROR", "HTTP Code: " . $response['http_code']);
                    continue;
                }

                $responsive = json_decode($response['result'], true);
                
                if (json_last_error() !== JSON_ERROR_NONE) {
                    $this->Logger->log("ERROR", "Invalid JSON response: " . json_last_error_msg());
                    continue;
                }

                $this->Logger->log("LOG", "Response: " . print_r($responsive, true));

                if (isset($responsive['success']) && $responsive['success'] === true) {
                    $this->Logger->log("LOG", "Message sent successfully to chat_id: " . $telegramId);
                } elseif (isset($responsive['ok']) && $responsive['ok'] === true) {
                    $this->Logger->log("LOG", "Message sent successfully to chat_id: " . $telegramId);
                } else {
                    $this->Logger->log("ERROR", "API error: " . ($responsive['description'] ?? $responsive['error'] ?? 'Unknown error'));
                }
            }        
			
            $this->arData["ERRORS"] = null;
            $this->arData["MESSAGES"] = null;
        }

        return true;
    }
    
	private function sendTelegramRequest($chatId, $text) {
        if ($this->useProxy) {
            return $this->sendViaProxy($chatId, $text);
        } else {
            return $this->sendDirect($chatId, $text);
        }
    }
	
    private function sendDirect($chatId, $text) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://api.telegram.org/bot' . $this->directToken . '/sendMessage');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, 'chat_id=' . $chatId . '&text=' . urlencode($text));
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $this->timeout);
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->totalTimeout);
        
        $curlResult = curl_exec($ch);
        $curlError = curl_error($ch);
        $curlErrno = curl_errno($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        return [
            'result' => $curlResult,
            'error' => $curlError,
            'errno' => $curlErrno,
            'http_code' => $httpCode
        ];
    }
	
	private function sendViaProxy($chatId, $text) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->proxyUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
            'token' => $this->tokenType,
            'chat_id' => $chatId,
            'text' => $text
        ]));
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $this->timeout);
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->totalTimeout);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/x-www-form-urlencoded'
        ]);
        
        $curlResult = curl_exec($ch);
        $curlError = curl_error($ch);
        $curlErrno = curl_errno($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        $this->Logger->log("DEBUG", "Proxy request to {$this->proxyUrl}, token_type: {$tokenType}, chat_id: {$chatId}");
        
        return [
            'result' => $curlResult,
            'error' => $curlError,
            'errno' => $curlErrno,
            'http_code' => $httpCode
        ];
    }
	
    public function getUpdates()
    {
        if ($this->useProxy) {
            $this->Logger->log("ERROR", "getUpdates not supported");
            return false;
        }
		$ch = curl_init("https://api.telegram.org/bot{$this->token}/getUpdates");
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($ch, CURLOPT_HEADER, false);
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $this->timeout);
		curl_setopt($ch, CURLOPT_TIMEOUT, $this->totalTimeout);

		$result = curl_exec($ch);
		curl_close($ch);

		return $result;
    }
}
