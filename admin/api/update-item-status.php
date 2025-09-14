<?php
// admin/api/update-item-status.php
require_once '../../config/config.php';
require_once '../../config/database.php';
require_once '../../config/auth.php';
require_once '../../models/Order.php';

$auth = new Auth();
$auth->requirePermission('kitchen');

header('Content-Type: application/json');

$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['item_id']) || !isset($input['status'])) {
    echo json_encode(['success' => false, 'message' => 'Datos incompletos']);
    exit();
}

$item_id = intval($input['item_id']);
$status = $input['status'];

$orderModel = new Order();
$result = $orderModel->updateItemStatus($item_id, $status);

echo json_encode(['success' => $result]);

?>