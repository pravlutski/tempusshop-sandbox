<?
class OzonApiService
{
    private $apiKey;
    private $clientId;
    
    public function __construct($apiKey, $clientId)
    {
        $this->apiKey = $apiKey;
        $this->clientId = $clientId;
    }
    
    public function getSupplies()
    {
        $url = "https://api-seller.ozon.ru/v2/supply/list";
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_HTTPHEADER => [
                'Client-Id: ' . $this->clientId,
                'Api-Key: ' . $this->apiKey,
                'Content-Type: application/json'
            ],
            CURLOPT_RETURNTRANSFER => true
        ]);
        
        $response = curl_exec($ch);
        $data = json_decode($response, true);
        
        return $data['result']['supplies'] ?? [];
    }
    
    public function sendBoxContent($supplyId, $boxes)
    {
        // Логика отправки состава коробов
    }
}
?>