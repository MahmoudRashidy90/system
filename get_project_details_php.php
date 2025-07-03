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
    // جلب تفاصيل المشروع
    $stmt = $conn->prepare("
        SELECT p.*, u.fullname as manager_name 
        FROM projects p 
        LEFT JOIN users u ON p.manager_id = u.id 
        WHERE p.id = ?
    ");
    $stmt->execute([$project_id]);
    $project = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$project) {
        http_response_code(404);
        echo json_encode(['error' => 'المشروع غير موجود']);
        exit;
    }
    
    // جلب أعضاء الفريق
    $team_stmt = $conn->prepare("
        SELECT u.id, u.fullname, u.role, u.email 
        FROM project_members pm 
        JOIN users u ON pm.user_id = u.id 
        WHERE pm.project_id = ?
        ORDER BY u.fullname
    ");
    $team_stmt->execute([$project_id]);
    $team_members = $team_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // جلب المهام المرتبطة بالمشروع
    $tasks_stmt = $conn->prepare("
        SELECT t.*, u.fullname as assigned_name 
        FROM tasks t 
        LEFT JOIN users u ON t.assigned_to = u.id 
        WHERE t.project_id = ? 
        ORDER BY t.created_at DESC 
        LIMIT 5
    ");
    $tasks_stmt->execute([$project_id]);
    $recent_tasks = $tasks_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // إحصائيات المهام
    $stats_stmt = $conn->prepare("
        SELECT 
            COUNT(*) as total_tasks,
            COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed_tasks,
            COUNT(CASE WHEN status = 'in_progress' THEN 1 END) as in_progress_tasks,
            COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending_tasks
        FROM tasks 
        WHERE project_id = ?
    ");
    $stats_stmt->execute([$project_id]);
    $task_stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);
    
    $response = [
        'project' => $project,
        'teamMembers' => $team_members,
        'recentTasks' => $recent_tasks,
        'taskStats' => $task_stats
    ];
    
    echo json_encode($response);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'خطأ في قاعدة البيانات']);
    error_log("DB Error: " . $e->getMessage());
}
?>