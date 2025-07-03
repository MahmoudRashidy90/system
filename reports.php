<?php
session_start();

// التحقق من وجود جلسة نشطة
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php?login_required=1");
    exit;
}

// التحقق من انتهاء صلاحية الجلسة (30 دقيقة من عدم النشاط)
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > 1800)) {
    session_unset();
    session_destroy();
    header("Location: login.php?session_expired=1");
    exit;
}

$_SESSION['last_activity'] = time();

require_once 'api/config.php';

// جلب معلومات المستخدم الحالي
$current_user_id = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$current_user_id]);
$current_user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$current_user) {
    session_destroy();
    header("Location: login.php");
    exit;
}

// جلب المشاريع للفلترة
$stmt = $pdo->prepare("SELECT id, name FROM projects ORDER BY name");
$stmt->execute();
$projects = $stmt->fetchAll(PDO::FETCH_ASSOC);

// جلب المستخدمين للفلترة (إزالة شرط is_active لأنه غير موجود)
$stmt = $pdo->prepare("SELECT id, fullname FROM users ORDER BY fullname");
$stmt->execute();
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

// تحديد الفترة الزمنية
$period = $_GET['period'] ?? 'week';
$project_filter = $_GET['project'] ?? 'all';
$user_filter = $_GET['user'] ?? 'all';
$start_date = $_GET['start_date'] ?? '';
$end_date = $_GET['end_date'] ?? '';

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

// بناء استعلام المهام مع الفلاتر (تعديل لتتوافق مع الأعمدة الموجودة)
$task_query = "
    SELECT t.*, u.fullname as assigned_user, p.name as project_name,
           CASE 
               WHEN t.status = 'completed' THEN 'مكتملة'
               WHEN t.status = 'in_progress' THEN 'قيد التنفيذ'
               WHEN t.status = 'pending' THEN 'في الانتظار'
               ELSE 'أخرى'
           END as status_ar,
           CASE 
               WHEN t.priority = 'urgent' THEN 'عاجلة'
               WHEN t.priority = 'high' THEN 'عالية'
               WHEN t.priority = 'medium' THEN 'متوسطة'
               WHEN t.priority = 'low' THEN 'منخفضة'
               ELSE 'غير محدد'
           END as priority_ar
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

// معدل الإنجاز العام
$overall_completion_rate = $total_tasks > 0 ? round(($completed_tasks / $total_tasks) * 100) : 0;

// متوسط الأداء
$average_performance = count($user_performance) > 0 ? 
    round(array_sum(array_column($user_performance, 'completion_rate')) / count($user_performance)) : 0;

?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>التقارير والإحصائيات - كوريان كاسيل</title>
    
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    
    <!-- Lucide Icons -->
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;600;700;900&display=swap" rel="stylesheet">
    
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <!-- Tailwind Config -->
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        primary: {
                            light: '#FADC9B',
                            sand: '#A68F51',
                            dark: '#8B7355'
                        }
                    },
                    fontFamily: {
                        arabic: ['Cairo', 'sans-serif']
                    }
                }
            }
        }
    </script>
    
    <style>
        body { font-family: 'Cairo', sans-serif; }
        
        .report-card {
            transition: all 0.3s ease;
        }
        .report-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
        }
        
        .chart-container {
            position: relative;
            height: 400px;
            margin: 20px 0;
        }
        
        .stat-card {
            background: linear-gradient(135deg, rgba(166, 143, 81, 0.1) 0%, rgba(250, 220, 155, 0.05) 100%);
            transition: all 0.3s ease;
        }
        
        .stat-card:hover {
            transform: scale(1.02);
        }
        
        .progress-ring {
            transform: rotate(-90deg);
        }
        
        .progress-circle {
            transition: stroke-dashoffset 0.5s ease-in-out;
        }
        
        .fade-in {
            animation: fadeIn 0.6s ease-in;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(30px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .success-message, .error-message {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 12px 24px;
            border-radius: 12px;
            font-weight: 600;
            z-index: 1000;
            transform: translateX(400px);
            transition: transform 0.3s ease;
        }
        
        .success-message.show, .error-message.show {
            transform: translateX(0);
        }
        
        .success-message {
            background: #10B981;
            color: white;
        }
        
        .error-message {
            background: #EF4444;
            color: white;
        }
        
        @media print {
            .print-hide { display: none !important; }
            .chart-container { height: 300px !important; }
            body { background: white !important; color: black !important; }
        }
    </style>
</head>
<body class="font-arabic bg-gray-50 dark:bg-gray-950 transition-colors duration-300">

    <div class="min-h-screen p-6">
        
        <!-- Header -->
        <div class="mb-8">
            <div class="flex items-center justify-between mb-6">
                <div>
                    <h1 class="text-3xl font-bold text-gray-900 dark:text-white mb-2">التقارير والإحصائيات</h1>
                    <p class="text-gray-600 dark:text-gray-400">تحليل شامل لأداء الفريق والمشاريع</p>
                </div>
                
                <div class="flex items-center gap-3 print-hide">
                    <button 
                        onclick="exportReports()"
                        class="px-6 py-3 bg-gradient-to-r from-primary-sand to-primary-light text-white rounded-2xl hover:from-primary-light hover:to-primary-sand transition-all duration-300 transform hover:scale-105 shadow-lg hover:shadow-xl"
                    >
                        <div class="flex items-center gap-2">
                            <i data-lucide="download" class="w-5 h-5"></i>
                            <span>تصدير التقارير</span>
                        </div>
                    </button>
                    
                    <button 
                        onclick="window.print()"
                        class="px-6 py-3 bg-white dark:bg-gray-800 border-2 border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 rounded-2xl hover:bg-gray-50 dark:hover:bg-gray-700 transition-all duration-300"
                        title="طباعة"
                    >
                        <i data-lucide="printer" class="w-5 h-5"></i>
                    </button>
                    
                    <button 
                        onclick="location.reload()"
                        class="px-6 py-3 bg-white dark:bg-gray-800 border-2 border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 rounded-2xl hover:bg-gray-50 dark:hover:bg-gray-700 transition-all duration-300"
                        title="تحديث البيانات"
                    >
                        <i data-lucide="refresh-cw" class="w-5 h-5"></i>
                    </button>
                    
                    <a href="dashboard.php" class="px-6 py-3 bg-white dark:bg-gray-800 border-2 border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 rounded-2xl hover:bg-gray-50 dark:hover:bg-gray-700 transition-all duration-300">
                        <i data-lucide="home" class="w-5 h-5"></i>
                    </a>
                </div>
            </div>
            
            <!-- فلاتر الفترة الزمنية -->
            <form method="GET" class="bg-white dark:bg-gray-900 rounded-3xl border border-gray-200 dark:border-gray-700 p-6 mb-6 print-hide">
                <div class="flex flex-col lg:flex-row gap-4">
                    
                    <div class="flex items-center gap-4">
                        <label class="text-sm font-semibold text-gray-700 dark:text-gray-300">الفترة الزمنية:</label>
                        <select name="period" onchange="this.form.submit()" class="px-4 py-2 rounded-xl border-2 border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-white focus:border-primary-sand focus:outline-none transition-colors">
                            <option value="today" <?= $period === 'today' ? 'selected' : '' ?>>اليوم</option>
                            <option value="week" <?= $period === 'week' ? 'selected' : '' ?>>هذا الأسبوع</option>
                            <option value="month" <?= $period === 'month' ? 'selected' : '' ?>>هذا الشهر</option>
                            <option value="quarter" <?= $period === 'quarter' ? 'selected' : '' ?>>الربع الحالي</option>
                            <option value="year" <?= $period === 'year' ? 'selected' : '' ?>>هذا العام</option>
                            <option value="custom" <?= $period === 'custom' ? 'selected' : '' ?>>فترة مخصصة</option>
                        </select>
                    </div>
                    
                    <?php if ($period === 'custom'): ?>
                    <div class="flex items-center gap-4">
                        <div class="flex items-center gap-2">
                            <label class="text-sm text-gray-600 dark:text-gray-400">من:</label>
                            <input type="date" name="start_date" value="<?= htmlspecialchars($start_date) ?>" 
                                   onchange="this.form.submit()" 
                                   class="px-3 py-2 rounded-xl border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-white focus:border-primary-sand focus:outline-none">
                        </div>
                        <div class="flex items-center gap-2">
                            <label class="text-sm text-gray-600 dark:text-gray-400">إلى:</label>
                            <input type="date" name="end_date" value="<?= htmlspecialchars($end_date) ?>" 
                                   onchange="this.form.submit()" 
                                   class="px-3 py-2 rounded-xl border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-white focus:border-primary-sand focus:outline-none">
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <div class="flex items-center gap-4 ml-auto">
                        <select name="project" onchange="this.form.submit()" class="px-4 py-2 rounded-xl border-2 border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-white focus:border-primary-sand focus:outline-none transition-colors">
                            <option value="all">جميع المشاريع</option>
                            <?php foreach ($projects as $project): ?>
                                <option value="<?= $project['id'] ?>" <?= $project_filter == $project['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($project['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        
                        <select name="user" onchange="this.form.submit()" class="px-4 py-2 rounded-xl border-2 border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-white focus:border-primary-sand focus:outline-none transition-colors">
                            <option value="all">جميع الأعضاء</option>
                            <?php foreach ($users as $user): ?>
                                <option value="<?= $user['id'] ?>" <?= $user_filter == $user['id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($user['fullname']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </form>
        </div>

        <!-- إحصائيات سريعة -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8 fade-in">
            
            <!-- مهام مكتملة -->
            <div class="stat-card bg-white dark:bg-gray-900 rounded-3xl border border-gray-200 dark:border-gray-700 p-6">
                <div class="flex items-center gap-4">
                    <div class="w-16 h-16 bg-blue-100 dark:bg-blue-900/20 rounded-2xl flex items-center justify-center">
                        <i data-lucide="check-circle" class="w-8 h-8 text-blue-600"></i>
                    </div>
                    <div>
                        <p class="text-3xl font-bold text-gray-900 dark:text-white"><?= $completed_tasks ?></p>
                        <p class="text-sm text-gray-500 dark:text-gray-400">مهام مكتملة</p>
                        <div class="flex items-center gap-2 mt-1">
                            <div class="w-20 bg-gray-200 dark:bg-gray-700 rounded-full h-1">
                                <div class="bg-blue-500 h-1 rounded-full" style="width: <?= $overall_completion_rate ?>%"></div>
                            </div>
                            <span class="text-xs text-blue-600"><?= $overall_completion_rate ?>%</span>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- قيد التنفيذ -->
            <div class="stat-card bg-white dark:bg-gray-900 rounded-3xl border border-gray-200 dark:border-gray-700 p-6">
                <div class="flex items-center gap-4">
                    <div class="w-16 h-16 bg-yellow-100 dark:bg-yellow-900/20 rounded-2xl flex items-center justify-center">
                        <i data-lucide="clock" class="w-8 h-8 text-yellow-600"></i>
                    </div>
                    <div>
                        <p class="text-3xl font-bold text-gray-900 dark:text-white"><?= $in_progress_tasks ?></p>
                        <p class="text-sm text-gray-500 dark:text-gray-400">قيد التنفيذ</p>
                        <p class="text-xs text-yellow-600 mt-1">+<?= $pending_tasks ?> في الانتظار</p>
                    </div>
                </div>
            </div>
            
            <!-- مهام متأخرة -->
            <div class="stat-card bg-white dark:bg-gray-900 rounded-3xl border border-gray-200 dark:border-gray-700 p-6">
                <div class="flex items-center gap-4">
                    <div class="w-16 h-16 bg-red-100 dark:bg-red-900/20 rounded-2xl flex items-center justify-center">
                        <i data-lucide="alert-triangle" class="w-8 h-8 text-red-600"></i>
                    </div>
                    <div>
                        <p class="text-3xl font-bold text-gray-900 dark:text-white"><?= count($overdue_tasks) ?></p>
                        <p class="text-sm text-gray-500 dark:text-gray-400">مهام متأخرة</p>
                        <p class="text-xs text-red-600 mt-1">تحتاج متابعة فورية</p>
                    </div>
                </div>
            </div>
            
            <!-- أعضاء نشطين -->
            <div class="stat-card bg-white dark:bg-gray-900 rounded-3xl border border-gray-200 dark:border-gray-700 p-6">
                <div class="flex items-center gap-4">
                    <div class="w-16 h-16 bg-green-100 dark:bg-green-900/20 rounded-2xl flex items-center justify-center">
                        <i data-lucide="trending-up" class="w-8 h-8 text-green-600"></i>
                    </div>
                    <div>
                        <p class="text-3xl font-bold text-gray-900 dark:text-white"><?= count($user_performance) ?></p>
                        <p class="text-sm text-gray-500 dark:text-gray-400">أعضاء نشطين</p>
                        <p class="text-xs text-green-600 mt-1">معدل أداء: <?= $average_performance ?>%</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- الرسوم البيانية الرئيسية -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
            
            <!-- رسم بياني لتقدم المهام -->
            <div class="bg-white dark:bg-gray-900 rounded-3xl border border-gray-200 dark:border-gray-700 p-6">
                <div class="flex items-center justify-between mb-6">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">تقدم المهام</h3>
                </div>
                <div class="chart-container">
                    <canvas id="tasks-chart"></canvas>
                </div>
            </div>
            
            <!-- رسم بياني لأداء الفريق -->
            <div class="bg-white dark:bg-gray-900 rounded-3xl border border-gray-200 dark:border-gray-700 p-6">
                <div class="flex items-center justify-between mb-6">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">أداء الفريق</h3>
                </div>
                <div class="chart-container">
                    <canvas id="team-chart"></canvas>
                </div>
            </div>
        </div>

        <!-- تحليلات مفصلة -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
            
            <!-- أداء الأعضاء -->
            <div class="bg-white dark:bg-gray-900 rounded-3xl border border-gray-200 dark:border-gray-700 p-6">
                <div class="flex items-center justify-between mb-6">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">أداء الأعضاء</h3>
                    <button onclick="exportTeamPerformance()" class="px-4 py-2 text-sm bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300 rounded-xl hover:bg-gray-200 dark:hover:bg-gray-700 transition-colors print-hide">
                        <i data-lucide="download" class="w-4 h-4 inline ml-1"></i>
                        تصدير
                    </button>
                </div>
                
                <div class="space-y-4 max-h-96 overflow-y-auto">
                    <?php foreach (array_slice($user_performance, 0, 10) as $performance): ?>
                        <div class="flex items-center justify-between p-4 bg-gray-50 dark:bg-gray-800 rounded-2xl">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 rounded-full bg-gradient-to-br from-primary-sand to-primary-light flex items-center justify-center text-white font-bold">
                                    <?= mb_substr($performance['user']['fullname'], 0, 1) ?>
                                </div>
                                <div>
                                    <h4 class="font-semibold text-gray-900 dark:text-white"><?= htmlspecialchars($performance['user']['fullname']) ?></h4>
                                    <p class="text-sm text-gray-500 dark:text-gray-400">عضو</p>
                                </div>
                            </div>
                            
                            <div class="flex items-center gap-4">
                                <div class="text-right">
                                    <p class="text-sm font-semibold text-gray-900 dark:text-white"><?= $performance['completed_tasks'] ?>/<?= $performance['total_tasks'] ?></p>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">مهام مكتملة</p>
                                </div>
                                
                                <div class="w-16 bg-gray-200 dark:bg-gray-700 rounded-full h-2">
                                    <div class="bg-primary-sand h-2 rounded-full" style="width: <?= $performance['completion_rate'] ?>%"></div>
                                </div>
                                
                                <span class="text-sm font-bold text-primary-sand min-w-[3rem]"><?= $performance['completion_rate'] ?>%</span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <!-- المهام المتأخرة -->
            <div class="bg-white dark:bg-gray-900 rounded-3xl border border-gray-200 dark:border-gray-700 p-6">
                <div class="flex items-center justify-between mb-6">
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white">المهام المتأخرة</h3>
                    <span class="px-3 py-1 bg-red-100 dark:bg-red-900/20 text-red-800 dark:text-red-400 text-sm font-semibold rounded-full"><?= count($overdue_tasks) ?></span>
                </div>
                
                <div class="space-y-3 max-h-96 overflow-y-auto">
                    <?php if (empty($overdue_tasks)): ?>
                        <div class="text-center py-8">
                            <div class="w-16 h-16 bg-green-100 dark:bg-green-900/20 rounded-full flex items-center justify-center mx-auto mb-4">
                                <i data-lucide="check-circle" class="w-8 h-8 text-green-600"></i>
                            </div>
                            <p class="text-gray-500 dark:text-gray-400">لا توجد مهام متأخرة!</p>
                        </div>
                    <?php else: ?>
                        <?php foreach (array_slice($overdue_tasks, 0, 10) as $task): ?>
                            <?php
                                $due_date = new DateTime($task['due_date']);
                                $now = new DateTime();
                                $days_overdue = $now->diff($due_date)->days;
                            ?>
                            <div class="flex items-center justify-between p-3 bg-red-50 dark:bg-red-900/10 border border-red-200 dark:border-red-800 rounded-xl">
                                <div class="flex-1">
                                    <h4 class="font-semibold text-gray-900 dark:text-white text-sm"><?= htmlspecialchars($task['title']) ?></h4>
                                    <div class="flex items-center gap-4 mt-1">
                                        <span class="text-xs text-gray-500 dark:text-gray-400"><?= htmlspecialchars($task['assigned_user'] ?? 'غير مُعين') ?></span>
                                        <span class="text-xs text-gray-500 dark:text-gray-400"><?= htmlspecialchars($task['project_name'] ?? '') ?></span>
                                    </div>
                                </div>
                                
                                <div class="text-right">
                                    <span class="text-xs font-semibold text-red-600">متأخر <?= $days_overdue ?> يوم</span>
                                    <p class="text-xs text-gray-500 dark:text-gray-400"><?= $due_date->format('Y-m-d') ?></p>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- توزيع الأولويات -->
        <div class="bg-white dark:bg-gray-900 rounded-3xl border border-gray-200 dark:border-gray-700 p-6 mb-8">
            <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-6">توزيع الأولويات</h3>
            <div class="chart-container" style="height: 300px;">
                <canvas id="priorities-chart"></canvas>
            </div>
        </div>

        <!-- ملخص الأداء -->
        <div class="bg-gradient-to-r from-primary-sand/10 to-primary-light/5 dark:from-primary-sand/5 dark:to-primary-light/5 rounded-3xl border border-primary-sand/20 p-8">
            <h3 class="text-2xl font-bold text-gray-900 dark:text-white mb-6 text-center">ملخص الأداء العام</h3>
            
            <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                
                <!-- معدل الإنجاز -->
                <div class="text-center">
                    <div class="relative w-32 h-32 mx-auto mb-4">
                        <svg class="w-32 h-32 progress-ring" viewBox="0 0 36 36">
                            <path
                                d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="2"
                                class="text-gray-200 dark:text-gray-700"
                            />
                            <path
                                d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="3"
                                stroke-linecap="round"
                                class="text-primary-sand progress-circle"
                                stroke-dasharray="<?= $overall_completion_rate ?>, 100"
                            />
                        </svg>
                        <div class="absolute inset-0 flex items-center justify-center">
                            <span class="text-2xl font-bold text-gray-900 dark:text-white"><?= $overall_completion_rate ?>%</span>
                        </div>
                    </div>
                    <h4 class="text-lg font-semibold text-gray-900 dark:text-white mb-2">معدل الإنجاز</h4>
                    <p class="text-gray-600 dark:text-gray-400 text-sm">نسبة المهام المكتملة</p>
                </div>
                
                <!-- إجمالي المهام -->
                <div class="text-center">
                    <div class="w-32 h-32 mx-auto mb-4 bg-gradient-to-br from-blue-500/20 to-blue-600/10 rounded-full flex items-center justify-center">
                        <div class="text-center">
                            <div class="text-3xl font-bold text-blue-600"><?= $total_tasks ?></div>
                            <div class="text-sm text-blue-500">مهمة</div>
                        </div>
                    </div>
                    <h4 class="text-lg font-semibold text-gray-900 dark:text-white mb-2">إجمالي المهام</h4>
                    <p class="text-gray-600 dark:text-gray-400 text-sm">في الفترة المحددة</p>
                </div>
                
                <!-- متوسط الأداء -->
                <div class="text-center">
                    <div class="w-32 h-32 mx-auto mb-4 bg-gradient-to-br from-green-500/20 to-green-600/10 rounded-full flex items-center justify-center">
                        <div class="text-center">
                            <div class="text-3xl font-bold text-green-600"><?= $average_performance ?>%</div>
                            <div class="text-sm text-green-500">متوسط</div>
                        </div>
                    </div>
                    <h4 class="text-lg font-semibold text-gray-900 dark:text-white mb-2">متوسط الأداء</h4>
                    <p class="text-gray-600 dark:text-gray-400 text-sm">أداء الفريق العام</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Toast Messages -->
    <div id="toast-container" class="fixed top-4 right-4 z-50 space-y-2"></div>

    <script>
        // البيانات من PHP
        const reportData = {
            taskStats: {
                completed: <?= $completed_tasks ?>,
                inProgress: <?= $in_progress_tasks ?>,
                pending: <?= $pending_tasks ?>,
                overdue: <?= count($overdue_tasks) ?>
            },
            userPerformance: <?= json_encode($user_performance) ?>,
            projectStats: <?= json_encode($project_stats) ?>,
            priorityStats: <?= json_encode($priority_stats) ?>
        };

        // إنشاء الرسوم البيانية
        function createTasksChart() {
            const ctx = document.getElementById('tasks-chart').getContext('2d');
            
            new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: ['مكتملة', 'قيد التنفيذ', 'في الانتظار', 'متأخرة'],
                    datasets: [{
                        data: [
                            reportData.taskStats.completed,
                            reportData.taskStats.inProgress,
                            reportData.taskStats.pending,
                            reportData.taskStats.overdue
                        ],
                        backgroundColor: [
                            '#10B981', // أخضر للمكتملة
                            '#3B82F6', // أزرق للتنفيذ
                            '#F59E0B', // أصفر للانتظار
                            '#EF4444'  // أحمر للمتأخرة
                        ],
                        borderWidth: 3,
                        borderColor: document.documentElement.classList.contains('dark') ? '#1F2937' : '#FFFFFF'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                color: document.documentElement.classList.contains('dark') ? '#F9FAFB' : '#374151',
                                padding: 20,
                                font: { family: 'Cairo' }
                            }
                        }
                    }
                }
            });
        }

        function createTeamChart() {
            const ctx = document.getElementById('team-chart').getContext('2d');
            
            new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: reportData.userPerformance.map(user => user.user.fullname),
                    datasets: [{
                        label: 'المهام المكتملة',
                        data: reportData.userPerformance.map(user => user.completed_tasks),
                        backgroundColor: '#A68F51',
                        borderRadius: 8
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            labels: {
                                color: document.documentElement.classList.contains('dark') ? '#F9FAFB' : '#374151',
                                font: { family: 'Cairo' }
                            }
                        }
                    },
                    scales: {
                        y: {
                            ticks: {
                                color: document.documentElement.classList.contains('dark') ? '#F9FAFB' : '#374151'
                            },
                            grid: {
                                color: document.documentElement.classList.contains('dark') ? '#374151' : '#E5E7EB'
                            }
                        },
                        x: {
                            ticks: {
                                color: document.documentElement.classList.contains('dark') ? '#F9FAFB' : '#374151'
                            },
                            grid: {
                                color: document.documentElement.classList.contains('dark') ? '#374151' : '#E5E7EB'
                            }
                        }
                    }
                }
            });
        }

        function createPrioritiesChart() {
            const ctx = document.getElementById('priorities-chart').getContext('2d');
            
            new Chart(ctx, {
                type: 'pie',
                data: {
                    labels: ['عاجلة', 'عالية', 'متوسطة', 'منخفضة'],
                    datasets: [{
                        data: [
                            reportData.priorityStats.urgent,
                            reportData.priorityStats.high,
                            reportData.priorityStats.medium,
                            reportData.priorityStats.low
                        ],
                        backgroundColor: ['#EF4444', '#F59E0B', '#3B82F6', '#10B981'],
                        borderWidth: 3,
                        borderColor: document.documentElement.classList.contains('dark') ? '#1F2937' : '#FFFFFF'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                color: document.documentElement.classList.contains('dark') ? '#F9FAFB' : '#374151',
                                padding: 20,
                                font: { family: 'Cairo' }
                            }
                        }
                    }
                }
            });
        }

        // وظائف التصدير
        function exportReports() {
            const exportData = {
                period: '<?= $period ?>',
                filters: {
                    project: '<?= $project_filter ?>',
                    user: '<?= $user_filter ?>',
                    startDate: '<?= $date_range['start'] ?>',
                    endDate: '<?= $date_range['end'] ?>'
                },
                stats: reportData,
                generatedAt: new Date().toISOString()
            };
            
            const dataStr = JSON.stringify(exportData, null, 2);
            const dataBlob = new Blob([dataStr], { type: 'application/json' });
            const url = URL.createObjectURL(dataBlob);
            const link = document.createElement('a');
            
            link.href = url;
            link.download = `تقرير-شامل-${new Date().toISOString().split('T')[0]}.json`;
            link.click();
            
            URL.revokeObjectURL(url);
            showToast('تم تصدير التقارير بنجاح', 'success');
        }

        function exportTeamPerformance() {
            let csvContent = 'اسم العضو,إجمالي المهام,المهام المكتملة,معدل الإنجاز\n';
            
            reportData.userPerformance.forEach(user => {
                csvContent += `"${user.user.fullname}",${user.total_tasks},${user.completed_tasks},${user.completion_rate}%\n`;
            });
            
            const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
            const link = document.createElement('a');
            const url = URL.createObjectURL(blob);
            
            link.href = url;
            link.download = `أداء-الفريق-${new Date().toISOString().split('T')[0]}.csv`;
            link.click();
            
            URL.revokeObjectURL(url);
            showToast('تم تصدير تقرير أداء الفريق', 'success');
        }

        // إظهار الرسائل
        function showToast(message, type = 'success') {
            const toast = document.createElement('div');
            toast.className = `${type}-message show`;
            toast.textContent = message;
            
            const container = document.getElementById('toast-container');
            container.appendChild(toast);
            
            setTimeout(() => {
                toast.classList.remove('show');
                setTimeout(() => container.removeChild(toast), 300);
            }, 3000);
        }

        // تهيئة الصفحة
        document.addEventListener('DOMContentLoaded', function() {
            // إنشاء الرسوم البيانية
            createTasksChart();
            createTeamChart();
            createPrioritiesChart();
            
            // تحديث الأيقونات
            if (window.lucide) {
                lucide.createIcons();
            }
            
            console.log('✅ تم تحميل صفحة التقارير بنجاح');
        });

        // تطبيق الثيم الداكن
        if (localStorage.getItem('theme') === 'dark') {
            document.documentElement.classList.add('dark');
        }
    </script>
</body>
</html>