<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

file_put_contents('debug.log', date('Y-m-d H:i:s') . " - Request received\n", FILE_APPEND);

header('Content-Type: application/json');

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Method not allowed', 405);
    }

    $input = file_get_contents('php://input');
    $data = json_decode($input, true);

    if (empty($data) && isset($_POST['warehouses'])) {
        $data = ['warehouses' => $_POST['warehouses']];
    }

    if (!isset($data['warehouses']) || !is_array($data['warehouses'])) {
        throw new Exception('Invalid data format: warehouses array expected', 400);
    }

    $warehouses = $data['warehouses'];

    file_put_contents('debug.log', date('Y-m-d H:i:s') . " - Warehouses: " . implode(', ', $warehouses) . "\n", FILE_APPEND);

    $result = file_put_contents('selected_warehouses.txt', implode(', ', $warehouses));

    if ($result === false) {
        throw new Exception('Failed to write to file', 500);
    }

    http_response_code(200);
    echo json_encode(['status' => 'success', 'message' => 'Warehouses saved successfully']);

} catch (Exception $e) {
    http_response_code($e->getCode() ?: 500);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage(),
        'code' => $e->getCode()
    ]);

    file_put_contents('debug.log', date('Y-m-d H:i:s') . " - Error: " . $e->getMessage() . "\n", FILE_APPEND);
}
?>
