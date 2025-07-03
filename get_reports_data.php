<?php
// ملف: get_reports_data.php
// API endpoint لجلب بيانات التقارير عبر AJAX

header('Content-Type: application/json');
session_start();

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'غير مصرح']);
    exit;
}

require_once 'api/config.php';

$period = $_GET['period'] ?? 'week';
$project_filter = $_GET['project'] ?? 'all';
$user_filter = $_GET['user'] ?? 'all';
$start_date = $_GET['start_date'] ?? '';
$end_date = $_GET['end_date'] ?? '';

try {
    // حساب نطاق التواريخ
    function getDateRange($period, $start_date, $end_date) {
        $now = new DateTime();
        $start = new DateTime();
        $end = new DateTime();
        
        switch ($period) {
            case 'today':
                $start = new DateTime('today');
                $end = new DateTime('tomorrow');
                break;
            case 'week':
                $start = new DateTime('-7 days');
                break;
            case 'month':
                $start = new DateTime('first day of this month');
                break;
            case 'quarter':
                $quarter = ceil($now->format('n') / 3);
                $start = new DateTime($now->format('Y') . '-' . ((($quarter - 1) * 3) + 1) . '-01');
                break;
            case 'year':
                $start = new DateTime('first day of January this year');
                break;
            case 'custom':
                if ($start_date && $end_date) {
                    $start = new DateTime($start_date);
                    $end = new DateTime($end_date);
                }
                break;
            default:
                $start = new DateTime('-7 days');
        }
        
        return [
            'start' => $start->format('Y-m-d'),
            'end' => $end->format('Y-m-d')
        ];
    }

    $date_range = getDateRange($period, $start_date, $end_date);

    // بناء استعلام المهام مع الفلاتر
    $task_query = "
        SELECT t.*, u.fullname as assigned_user, p.name as project_name
        FROM tasks t
        LEFT JOIN users u ON t.assigned_to = u.id
        LEFT JOIN projects p ON t.project_id = p.id
        WHERE t.created_at >= ? AND t.created_at <= ?
    ";

    $params = [$date_range['start'], $date_range['end']];

    if ($project_filter !== 'all') {
        $task_query .= " AND t.project_id = ?";
        $params[] = $project_filter;
    }

    if ($user_filter !== 'all') {
        $task_query .= " AND t.assigned_to = ?";
        $params[] = $user_filter;
    }

    $task_query .= " ORDER BY t.created_at DESC";

    $stmt = $pdo->prepare($task_query);
    $stmt->execute($params);
    $tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // حساب الإحصائيات
    $total_tasks = count($tasks);
    $completed_tasks = count(array_filter($tasks, fn($t) => $t['status'] === 'completed'));
    $in_progress_tasks = count(array_filter($tasks, fn($t) => $t['status'] === 'in_progress'));
    $pending_tasks = count(array_filter($tasks, fn($t) => $t['status'] === 'pending'));

    // المهام المتأخرة
    $overdue_tasks = array_filter($tasks, function($task) {
        return $task['due_date'] && new DateTime($task['due_date']) < new DateTime() && $task['status'] !== 'completed';
    });

    // إحصائيات الأولويات
    $priority_stats = [
        'urgent' => count(array_filter($tasks, fn($t) => $t['priority'] === 'urgent')),
        'high' => count(array_filter($tasks, fn($t) => $t['priority'] === 'high')),
        'medium' => count(array_filter($tasks, fn($t) => $t['priority'] === 'medium')),
        'low' => count(array_filter($tasks, fn($t) => $t['priority'] === 'low'))
    ];

    // جلب المستخدمين النشطين
    $stmt = $pdo->prepare("SELECT id, fullname FROM users WHERE is_active = 1");
    $stmt->execute();
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // أداء الأعضاء
    $user_performance = [];
    foreach ($users as $user) {
        $user_tasks = array_filter($tasks, fn($t) => $t['assigned_to'] == $user['id']);
        if (count($user_tasks) > 0) {
            $user_completed = count(array_filter($user_tasks, fn($t) => $t['status'] === 'completed'));
            $completion_rate = round(($user_completed / count($user_tasks)) * 100);
            
            $user_performance[] = [
                'user' => $user,
                'total_tasks' => count($user_tasks),
                'completed_tasks' => $user_completed,
                'completion_rate' => $completion_rate
            ];
        }
    }

    // ترتيب الأداء
    usort($user_performance, fn($a, $b) => $b['completion_rate'] - $a['completion_rate']);

    // جلب المشاريع
    $stmt = $pdo->prepare("SELECT id, name FROM projects");
    $stmt->execute();
    $projects = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // إحصائيات المشاريع
    $project_stats = [];
    foreach ($projects as $project) {
        $project_tasks = array_filter($tasks, fn($t) => $t['project_id'] == $project['id']);
        if (count($project_tasks) > 0) {
            $project_completed = count(array_filter($project_tasks, fn($t) => $t['status'] === 'completed'));
            $efficiency = round(($project_completed / count($project_tasks)) * 100);
            
            $project_stats[] = [
                'project' => $project,
                'total_tasks' => count($project_tasks),
                'completed_tasks' => $project_completed,
                'efficiency' => $efficiency
            ];
        }
    }

    // بيانات التايم لاين (لآخر 30 يوم)
    $timeline_data = [];
    for ($i = 29; $i >= 0; $i--) {
        $date = new DateTime("-{$i} days");
        $date_str = $date->format('Y-m-d');
        
        $created_count = count(array_filter($tasks, function($task) use ($date_str) {
            return substr($task['created_at'], 0, 10) === $date_str;
        }));
        
        $completed_count = count(array_filter($tasks, function($task) use ($date_str) {
            return $task['updated_at'] && substr($task['updated_at'], 0, 10) === $date_str && $task['status'] === 'completed';
        }));
        
        $timeline_data[] = [
            'date' => $date_str,
            'created' => $created_count,
            'completed' => $completed_count
        ];
    }

    // معدل الإنجاز العام
    $overall_completion_rate = $total_tasks > 0 ? round(($completed_tasks / $total_tasks) * 100) : 0;

    // إرجاع البيانات
    echo json_encode([
        'success' => true,
        'data' => [
            'period' => $period,
            'date_range' => $date_range,
            'task_stats' => [
                'total' => $total_tasks,
                'completed' => $completed_tasks,
                'in_progress' => $in_progress_tasks,
                'pending' => $pending_tasks,
                'overdue' => count($overdue_tasks)
            ],
            'priority_stats' => $priority_stats,
            'user_performance' => $user_performance,
            'project_stats' => $project_stats,
            'timeline_data' => $timeline_data,
            'overall_completion_rate' => $overall_completion_rate,
            'overdue_tasks' => array_values($overdue_tasks)
        ]
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'حدث خطأ في جلب البيانات: ' . $e->getMessage()
    ]);
}
?>