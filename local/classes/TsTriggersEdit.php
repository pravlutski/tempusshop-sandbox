<?
/**
 * Send errors in telegram
 */
class TsTriggersEdit {
    private $Logger; // logger
    private $adminTelegram = [171773786, 2044238, 1840836434, 344031842]; // users id whom will be send message
    private $token = '5530466584:AAGb6Zcxow_PliiJFMrH_4PqCIVOvbrpZI8'; // telegram bot token
    private $arData = []; // all data
    private $site = "tempus.by";
    private $timeout = 10;
    private $totalTimeout = 30;

    public function __construct () {
        //$this->Logger = new TsLogger("/triggers/", false);
    }

    /**
     * set the errors
     * @param array $error error messages
     *
     * @return bool
     */
    public function SetError($errors = []) {
        //$this->Logger->log("LOG", "Try to set trigger errors");

        if (empty($errors)) {
            //$this->Logger->log("ERROR", "Error array is empty");
            return false;
        }

        foreach ($errors as $error) {
			if($error){
				$this->arData["ERRORS"][] = $this->site.": ".$error;
				//$this->Logger->log("LOG", "Set new error '".$error."'");
			}
        }

        //$this->Logger->log("LOG", "All errors are setted");

        return true;
    }

    public function SetMessage($arMessage = []) {
        //$this->Logger->log("LOG", "Try to set trigger message");

        if (empty($arMessage)) {
            //$this->Logger->log("ERROR", "Empty message");
            return false;
        }

        foreach ($arMessage as $message) {
			if($message){
				$this->arData["MESSAGES"][] = $this->site.": ".$message;
				//$this->Logger->log("LOG", "Set new message '".$message."'");
			}
        }

        //$this->Logger->log("LOG", "All messages are setted");

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
        //$this->Logger->log("LOG", "Try to send errors in telegram");

        if (empty($this->arData["ERRORS"])) {
            //$this->Logger->log("ERROR", "Error array is empty");
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

                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, 'https://api.telegram.org/bot' . $this->token . '/sendMessage');
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_HEADER, false);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, 'chat_id=' . $telegramId . '&text=' . urlencode($text));

				curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $this->timeout);
				curl_setopt($ch, CURLOPT_TIMEOUT, $this->totalTimeout);
				curl_setopt($ch, CURLOPT_DNS_CACHE_TIMEOUT, 300);

                $curlResult = curl_exec($ch);
                curl_close($ch);
                $responsive = json_decode($curlResult, true);

                //$this->Logger->log("LOG", "Responsive ".print_r($responsive, true));

                if (!strlen($curlResult)) {
                    //$this->Logger->log("ERROR", "Message not send!");
                    return false;
                } elseif (!empty($responsive["error_code"]) && $responsive["error_code"] == 400) {
                    $firstError = $this->arData["ERRORS"][0];
                    $this->arData["ERRORS"] = null;
                    $this->SetError([$firstError]);
                    $this->SendTriggerErrors();
                }

                //$this->Logger->log("LOG", "Message send successfully");
                $this->arData["ERRORS"] = null;
            }
        }

        return true;
    }

    public function SendAll($userId = 0) {
        //$this->Logger->log("LOG", "Try to send messages in telegram");

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

                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, 'https://api.telegram.org/bot' . $this->token . '/sendMessage');
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_HEADER, false);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, 'chat_id=' . $telegramId . '&text=' . urlencode($text));

				curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $this->timeout);
				curl_setopt($ch, CURLOPT_TIMEOUT, $this->totalTimeout);
				curl_setopt($ch, CURLOPT_DNS_CACHE_TIMEOUT, 300);

                $curlResult = curl_exec($ch);
                curl_close($ch);
                $responsive = json_decode($curlResult, true);

                //$this->Logger->log("LOG", "Responsive ".print_r($responsive, true));

                if (!strlen($curlResult)) {
                    //$this->Logger->log("ERROR", "Message not send!");
                    return false;
                } elseif (!empty($responsive["error_code"]) && $responsive["error_code"] == 400) {
                    $firstError = $this->arData["ERRORS"][0];
                    $this->arData["ERRORS"] = null;
                    $this->SetError([$firstError]);
                    $this->SendTriggerErrors();
                }

                //$this->Logger->log("LOG", "Message send successfully");
                $this->arData["ERRORS"] = null;
            }
        }

        return true;
    }
}
