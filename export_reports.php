<?php
// ملف: export_reports.php
// تصدير التقارير بصيغ مختلفة

session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

require_once 'api/config.php';

$format = $_GET['format'] ?? 'json';
$type = $_GET['type'] ?? 'full';

// جلب البيانات (نفس اللوجيك من get_reports_data.php)
$period = $_GET['period'] ?? 'week';
$project_filter = $_GET['project'] ?? 'all';
$user_filter = $_GET['user'] ?? 'all';

try {
    // حساب نطاق التواريخ
    function getDateRange($period, $start_date = '', $end_date = '') {
        $now = new DateTime();
        $start = new DateTime();
        $end = new DateTime();
        
        switch ($period) {
            case 'today':
                $start = new DateTime('today');
                $end = new DateTime('tomorrow');
                break;
            
        case 'excel':
            header('Content-Type: application/vnd.ms-excel; charset=utf-8');
            header('Content-Disposition: attachment; filename="تقرير-شامل-' . date('Y-m-d') . '.xls"');
            
            echo "\xEF\xBB\xBF"; // BOM for UTF-8
            
            // بداية ملف Excel
            echo '<table border="1">';
            echo '<tr><th colspan="4" style="background-color: #A68F51; color: white; text-align: center;">تقرير الأداء الشامل - ' . date('Y-m-d') . '</th></tr>';
            echo '<tr><th>اسم العضو</th><th>إجمالي المهام</th><th>المهام المكتملة</th><th>معدل الإنجاز</th></tr>';
            
            foreach ($user_performance as $performance) {
                echo '<tr>';
                echo '<td>' . htmlspecialchars($performance['user']['fullname']) . '</td>';
                echo '<td>' . $performance['total_tasks'] . '</td>';
                echo '<td>' . $performance['completed_tasks'] . '</td>';
                echo '<td>' . $performance['completion_rate'] . '%</td>';
                echo '</tr>';
            }
            
            echo '</table>';
            break;
            
        case 'pdf':
            // مؤقتاً - يمكن إضافة مكتبة PDF لاحقاً
            header('Content-Type: text/plain; charset=utf-8');
            echo "تصدير PDF سيتم إضافته قريباً مع مكتبة TCPDF أو mPDF";
            break;
            
        default:
            throw new Exception('صيغة غير مدعومة');
    }
    
} catch (Exception $e) {
    header('Content-Type: text/plain; charset=utf-8');
    echo "خطأ في التصدير: " . $e->getMessage();
}
?>
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

    $date_range = getDateRange($period);

    // جلب المهام
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

    $stmt = $pdo->prepare($task_query);
    $stmt->execute($params);
    $tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // حساب الإحصائيات
    $total_tasks = count($tasks);
    $completed_tasks = count(array_filter($tasks, fn($t) => $t['status'] === 'completed'));
    $overall_completion_rate = $total_tasks > 0 ? round(($completed_tasks / $total_tasks) * 100) : 0;

    // جلب أداء الأعضاء
    $stmt = $pdo->prepare("SELECT id, fullname FROM users WHERE is_active = 1");
    $stmt->execute();
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

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

    switch ($format) {
        case 'json':
            header('Content-Type: application/json; charset=utf-8');
            header('Content-Disposition: attachment; filename="تقرير-' . date('Y-m-d') . '.json"');
            
            $export_data = [
                'generated_at' => date('Y-m-d H:i:s'),
                'period' => $period,
                'date_range' => $date_range,
                'filters' => [
                    'project' => $project_filter,
                    'user' => $user_filter
                ],
                'summary' => [
                    'total_tasks' => $total_tasks,
                    'completed_tasks' => $completed_tasks,
                    'completion_rate' => $overall_completion_rate
                ],
                'user_performance' => $user_performance,
                'tasks' => $tasks
            ];
            
            echo json_encode($export_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            break;
            
        case 'csv':
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="تقرير-أداء-الفريق-' . date('Y-m-d') . '.csv"');
            
            // إضافة BOM للدعم العربي
            echo "\xEF\xBB\xBF";
            
            // العناوين
            echo "اسم العضو,إجمالي المهام,المهام المكتملة,معدل الإنجاز,تاريخ التقرير\n";
            
            // البيانات
            foreach ($user_performance as $performance) {
                echo '"' . $performance['user']['fullname'] . '",' .
                     $performance['total_tasks'] . ',' .
                     $performance['completed_tasks'] . ',' .
                     $performance['completion_rate'] . '%,' .
                     date('Y-m-d') . "\n";
            }
            break;