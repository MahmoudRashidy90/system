<?php
header('Content-Type: application/json');
session_start();

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'غير مصرح']);
    exit;
}

require_once 'api/config.php';

$project_id = $_GET['id'] ?? null;

if (!$project_id) {
    http_response_code(400);
    echo json_encode(['error' => 'معرف المشروع مطلوب']);
    exit;
}

try {
    $stmt = $conn->prepare("SELECT user_id FROM project_members WHERE project_id = ?");
    $stmt->execute([$project_id]);
    $team_members = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    echo json_encode($team_members);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'خطأ في قاعدة البيانات']);
    error_log("DB Error: " . $e->getMessage());
}
?>