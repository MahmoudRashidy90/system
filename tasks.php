<?php
session_start();

// التحقق من وجود جلسة نشطة
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php?login_required=1");
    exit;
}

// التحقق من انتهاء صلاحية الجلسة (30 دقيقة من عدم النشاط)
if (isset($_SESSION['last_activity'])) {
    $inactive_time = time() - $_SESSION['last_activity'];
    $session_timeout = 30 * 60; // 30 دقيقة
    
    if ($inactive_time > $session_timeout) {
        session_destroy();
        header("Location: login.php?session_expired=1");
        exit;
    }
}

// تحديث وقت آخر نشاط
$_SESSION['last_activity'] = time();

require_once 'api/config.php';

$current_user_id = $_SESSION['user_id'];
$current_user_role = $_SESSION['user_role'] ?? 'employee';
$success_message = $error_message = '';

// معالجة POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    try {
        if ($action === 'add_task') {
            $data = [
                'title' => trim($_POST['title'] ?? ''),
                'description' => trim($_POST['description'] ?? ''),
                'assigned_to' => $_POST['assigned_to'] ?? null,
                'priority' => $_POST['priority'] ?? 'medium',
                'due_date' => $_POST['due_date'] ?? null,
                'category' => $_POST['category'] ?? 'general'
            ];
            
            if (empty($data['title'])) {
                throw new Exception('عنوان المهمة مطلوب');
            }
            
            // إدراج المهمة
            $stmt = $conn->prepare("
                INSERT INTO tasks (title, description, assigned_to, created_by, priority, due_date, category, status, created_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, 'pending', NOW())
            ");
            $stmt->execute([$data['title'], $data['description'], $data['assigned_to'], $current_user_id, $data['priority'], $data['due_date'], $data['category']]);
            
            // إضافة إشعار للمستخدم المكلف
            if ($data['assigned_to'] && $data['assigned_to'] != $current_user_id) {
                $notification_stmt = $conn->prepare("
                    INSERT INTO notifications (user_id, title, message, type, created_at) 
                    VALUES (?, ?, ?, 'task', NOW())
                ");
                $notification_stmt->execute([
                    $data['assigned_to'], 
                    'مهمة جديدة مكلف بها',
                    "تم تكليفك بمهمة جديدة: {$data['title']}"
                ]);
            }
            
            header('Location: tasks.php?success=task_added');
            exit();
        }
        
        if ($action === 'update_task') {
            $task_id = $_POST['task_id'] ?? null;
            $data = [
                'title' => trim($_POST['title'] ?? ''),
                'description' => trim($_POST['description'] ?? ''),
                'assigned_to' => $_POST['assigned_to'] ?? null,
                'priority' => $_POST['priority'] ?? 'medium',
                'due_date' => $_POST['due_date'] ?? null,
                'category' => $_POST['category'] ?? 'general'
            ];
            
            if (!$task_id || empty($data['title'])) {
                throw new Exception('عنوان المهمة مطلوب');
            }
            
            $stmt = $conn->prepare("UPDATE tasks SET title = ?, description = ?, assigned_to = ?, priority = ?, due_date = ?, category = ?, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$data['title'], $data['description'], $data['assigned_to'], $data['priority'], $data['due_date'], $data['category'], $task_id]);
            
            header('Location: tasks.php?success=task_updated');
            exit();
        }
        
        if ($action === 'update_status') {
            $task_id = $_POST['task_id'] ?? '';
            $new_status = $_POST['status'] ?? '';
            
            if (empty($task_id) || empty($new_status)) {
                throw new Exception('بيانات غير مكتملة');
            }
            
            // تحديث حالة المهمة
            $completed_date = ($new_status === 'completed') ? 'NOW()' : 'NULL';
            $stmt = $conn->prepare("
                UPDATE tasks 
                SET status = ?, completed_date = {$completed_date}, updated_at = NOW() 
                WHERE id = ?
            ");
            $stmt->execute([$new_status, $task_id]);
            
            // إضافة إشعار عند إكمال المهمة
            if ($new_status === 'completed') {
                $task_stmt = $conn->prepare("SELECT title, created_by FROM tasks WHERE id = ?");
                $task_stmt->execute([$task_id]);
                $task = $task_stmt->fetch();
                
                if ($task && $task['created_by'] != $current_user_id) {
                    $notification_stmt = $conn->prepare("
                        INSERT INTO notifications (user_id, title, message, type, created_at) 
                        VALUES (?, ?, ?, 'success', NOW())
                    ");
                    $notification_stmt->execute([
                        $task['created_by'], 
                        'تم إكمال مهمة',
                        "تم إكمال المهمة: {$task['title']}"
                    ]);
                }
            }
            
            header('Location: tasks.php?success=status_updated');
            exit();
        }
        
        if ($action === 'delete_task') {
            $task_id = $_POST['task_id'] ?? '';
            
            if (empty($task_id)) {
                throw new Exception('معرف المهمة مطلوب');
            }
            
            // حذف المهمة
            $stmt = $conn->prepare("DELETE FROM tasks WHERE id = ?");
            $stmt->execute([$task_id]);
            
            header('Location: tasks.php?success=task_deleted');
            exit();
        }
        
    } catch (Exception $e) {
        $error_message = $e->getMessage();
    } catch (PDOException $e) {
        $error_message = 'حدث خطأ في قاعدة البيانات';
        error_log("DB Error: " . $e->getMessage());
    }
}

// رسائل النجاح
$success_messages = [
    'task_added' => 'تم إضافة المهمة بنجاح!',
    'task_updated' => 'تم تحديث المهمة بنجاح!',
    'status_updated' => 'تم تحديث حالة المهمة بنجاح!',
    'task_deleted' => 'تم حذف المهمة بنجاح!'
];
if (isset($_GET['success']) && isset($success_messages[$_GET['success']])) {
    $success_message = $success_messages[$_GET['success']];
}

// معاملات البحث والفلترة
$search = $_GET['search'] ?? '';
$status_filter = $_GET['status'] ?? '';
$priority_filter = $_GET['priority'] ?? '';
$assigned_filter = $_GET['assigned'] ?? '';
$category_filter = $_GET['category'] ?? '';

// بناء الاستعلام
$where_conditions = ['1=1'];
$params = [];

if (!empty($search)) {
    $where_conditions[] = "(t.title LIKE ? OR t.description LIKE ?)";
    $params = array_merge($params, ["%$search%", "%$search%"]);
}

foreach (['status' => $status_filter, 'priority' => $priority_filter, 'assigned_to' => $assigned_filter, 'category' => $category_filter] as $field => $value) {
    if (!empty($value)) {
        $where_conditions[] = "t.$field = ?";
        $params[] = $value;
    }
}

$where_clause = 'WHERE ' . implode(' AND ', $where_conditions);

try {
    // جلب المهام مع معلومات المستخدمين
    $tasks_sql = "SELECT t.*, 
                         assigned_user.fullname as assigned_name,
                         created_user.fullname as created_name,
                         p.name as project_name
                  FROM tasks t
                  LEFT JOIN users assigned_user ON t.assigned_to = assigned_user.id
                  LEFT JOIN users created_user ON t.created_by = created_user.id
                  LEFT JOIN projects p ON t.project_id = p.id
                  $where_clause 
                  ORDER BY 
                      CASE t.priority WHEN 'urgent' THEN 1 WHEN 'high' THEN 2 WHEN 'medium' THEN 3 WHEN 'low' THEN 4 END,
                      t.created_at DESC";
    
    $stmt = $conn->prepare($tasks_sql);
    $stmt->execute($params);
    $tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // جلب المستخدمين للفلاتر والتكليف
    $users_stmt = $conn->prepare("SELECT id, fullname FROM users ORDER BY fullname");
    $users_stmt->execute();
    $users = $users_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // إحصائيات عامة
    $stats_stmt = $conn->prepare("
        SELECT 
            COUNT(*) as total_tasks,
            COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending_count,
            COUNT(CASE WHEN status = 'in_progress' THEN 1 END) as in_progress_count,
            COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed_count,
            COUNT(CASE WHEN status = 'cancelled' THEN 1 END) as cancelled_count,
            COUNT(CASE WHEN due_date < CURDATE() AND status != 'completed' THEN 1 END) as overdue_count
        FROM tasks
    ");
    $stats_stmt->execute();
    $stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);
    
    // جلب القوائم للفلترة
    $priorities = ['urgent', 'high', 'medium', 'low'];
    $categories = ['general', 'design', 'development', 'marketing', 'meeting', 'documentation'];
    
} catch (PDOException $e) {
    $error_message = 'خطأ في جلب البيانات';
    $tasks = [];
    $users = [];
    $stats = ['total_tasks' => 0, 'pending_count' => 0, 'in_progress_count' => 0, 'completed_count' => 0, 'cancelled_count' => 0, 'overdue_count' => 0];
    $priorities = $categories = [];
    error_log("DB Error: " . $e->getMessage());
}

// دوال مساعدة
function formatDate($date) { return $date ? date('d/m/Y', strtotime($date)) : 'غير محدد'; }
function formatDateTime($datetime) { return $datetime ? date('d/m/Y H:i', strtotime($datetime)) : 'غير محدد'; }

function getStatusDisplayName($status) {
    $statuses = ['pending' => 'قيد الانتظار', 'in_progress' => 'قيد التنفيذ', 'completed' => 'مكتملة', 'cancelled' => 'ملغية'];
    return $statuses[$status] ?? $status;
}

function getStatusColor($status) {
    $colors = ['pending' => 'yellow', 'in_progress' => 'blue', 'completed' => 'green', 'cancelled' => 'red'];
    return $colors[$status] ?? 'gray';
}

function getPriorityDisplayName($priority) {
    $priorities = ['urgent' => 'عاجل', 'high' => 'عالي', 'medium' => 'متوسط', 'low' => 'منخفض'];
    return $priorities[$priority] ?? $priority;
}

function getPriorityColor($priority) {
    $colors = ['urgent' => 'red', 'high' => 'orange', 'medium' => 'blue', 'low' => 'green'];
    return $colors[$priority] ?? 'gray';
}

function getCategoryDisplayName($category) {
    $categories = ['general' => 'عام', 'design' => 'تصميم', 'development' => 'تطوير', 'marketing' => 'تسويق', 'meeting' => 'اجتماع', 'documentation' => 'وثائق'];
    return $categories[$category] ?? $category;
}

function isOverdue($due_date, $status) {
    if (!$due_date || $status === 'completed' || $status === 'cancelled') return false;
    return strtotime($due_date) < strtotime('today');
}

// خيارات الأولويات والفئات
$priorities_options = [
    'low' => 'منخفض',
    'medium' => 'متوسط', 
    'high' => 'عالي',
    'urgent' => 'عاجل'
];

$categories_options = [
    'general' => 'عام',
    'design' => 'تصميم',
    'development' => 'تطوير',
    'marketing' => 'تسويق',
    'meeting' => 'اجتماع',
    'documentation' => 'وثائق'
];
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إدارة المهام - كوريان كاسيل</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;600;700;900&display=swap" rel="stylesheet">
    
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: { primary: { light: '#FADC9B', sand: '#A68F51', dark: '#8B7355' } },
                    fontFamily: { arabic: ['Cairo', 'sans-serif'] }
                }
            }
        }
    </script>

    <style>
        body { font-family: 'Cairo', sans-serif; }
        .task-card { transition: all 0.3s ease; }
        .task-card:hover { transform: translateY(-2px); box-shadow: 0 10px 30px rgba(0,0,0,0.1); }
        .fade-in { animation: fadeIn 0.5s ease-in; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
        .modal { backdrop-filter: blur(8px); }
        .nav-item { @apply text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white hover:bg-gray-100 dark:hover:bg-gray-800; }
        .nav-item.active { @apply text-primary-sand bg-primary-sand/10; }
    </style>
</head>
<body class="font-arabic bg-gray-50 dark:bg-gray-950 transition-colors duration-300">

    <!-- Sidebar -->
    <nav id="sidebar" class="fixed right-0 top-0 h-full w-80 bg-white dark:bg-gray-900 border-l border-gray-200 dark:border-gray-700 transform transition-transform duration-300 z-50 shadow-2xl translate-x-full">
        <div class="p-6">
            <div class="flex items-center justify-between mb-8">
                <div class="flex items-center gap-3">
                    <div class="w-12 h-12 bg-gradient-to-br from-primary-sand to-primary-light rounded-2xl flex items-center justify-center text-white font-bold text-lg">
                        <svg viewBox="0 0 200 200" style="width: 32px; height: 32px;">
                            <g fill="#fff">
                                <polygon points="20,120 20,180 60,180 60,140 20,140"/>
                                <polygon points="140,140 140,180 180,180 180,120 160,120 160,140"/>
                                <rect x="60" y="80" width="80" height="100"/>
                                <ellipse cx="100" cy="150" rx="15" ry="25" fill="#f59e0b"/>
                                <ellipse cx="80" cy="120" rx="8" ry="12" fill="#f59e0b"/>
                                <ellipse cx="120" cy="120" rx="8" ry="12" fill="#f59e0b"/>
                                <ellipse cx="40" cy="150" rx="6" ry="10" fill="#f59e0b"/>
                                <ellipse cx="160" cy="150" rx="6" ry="10" fill="#f59e0b"/>
                                <rect x="65" y="60" width="70" height="20"/>
                                <rect x="90" y="40" width="20" height="40"/>
                                <rect x="108" y="30" width="2" height="20" fill="#333"/>
                                <polygon points="110,30 110,40 125,35" fill="#dc2626"/>
                            </g>
                        </svg>
                    </div>
                    <div>
                        <h2 class="font-bold text-gray-900 dark:text-white">كوريان كاسيل</h2>
                        <p class="text-sm text-gray-500 dark:text-gray-400">إدارة المهام</p>
                    </div>
                </div>
                <button id="close-sidebar" class="p-2 hover:bg-gray-100 dark:hover:bg-gray-800 rounded-lg transition-colors">
                    <i data-lucide="x" class="w-5 h-5 text-gray-500"></i>
                </button>
            </div>

            <div class="bg-gradient-to-r from-primary-light/20 to-primary-sand/20 rounded-2xl p-4 mb-6">
                <div class="flex items-center gap-3">
                    <div class="w-12 h-12 bg-primary-sand rounded-xl flex items-center justify-center text-white font-bold text-lg">
                        <?= mb_substr($_SESSION['user_name'] ?? 'م', 0, 1, 'UTF-8') ?>
                    </div>
                    <div>
                        <h3 class="font-semibold text-gray-900 dark:text-white"><?= htmlspecialchars($_SESSION['user_name'] ?? 'مستخدم') ?></h3>
                        <p class="text-sm text-gray-600 dark:text-gray-400"><?= htmlspecialchars($current_user_role) ?></p>
                    </div>
                </div>
            </div>

            <nav class="space-y-2">
                <a href="dashboard.php" class="nav-item flex items-center gap-3 p-3 rounded-xl transition-all">
                    <i data-lucide="layout-dashboard" class="w-5 h-5"></i><span>الرئيسية</span>
                </a>
                <a href="#" class="nav-item active flex items-center gap-3 p-3 rounded-xl transition-all bg-primary-sand/10 text-primary-sand">
                    <i data-lucide="check-square" class="w-5 h-5"></i><span>المهام</span>
                </a>
                <a href="users.php" class="nav-item flex items-center gap-3 p-3 rounded-xl transition-all">
                    <i data-lucide="users" class="w-5 h-5"></i><span>المستخدمين</span>
                </a>
                <a href="profile.php" class="nav-item flex items-center gap-3 p-3 rounded-xl transition-all">
                    <i data-lucide="user" class="w-5 h-5"></i><span>الملف الشخصي</span>
                </a>
                <a href="reports.php" class="nav-item flex items-center gap-3 p-3 rounded-xl transition-all">
                    <i data-lucide="bar-chart-3" class="w-5 h-5"></i><span>التقارير</span>
                </a>
                <a href="settings.php" class="nav-item flex items-center gap-3 p-3 rounded-xl transition-all">
                    <i data-lucide="settings" class="w-5 h-5"></i><span>الإعدادات</span>
                </a>
            </nav>

            <div class="mt-8 pt-6 border-t border-gray-200 dark:border-gray-700">
                <button onclick="confirmLogout()" class="w-full flex items-center gap-3 p-3 text-red-600 hover:bg-red-50 dark:hover:bg-red-900/20 rounded-xl transition-all">
                    <i data-lucide="log-out" class="w-5 h-5"></i><span>تسجيل الخروج</span>
                </button>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <div id="main-content" class="mr-0 min-h-screen transition-all duration-300">
        <!-- Header -->
        <header class="bg-white dark:bg-gray-900 border-b border-gray-200 dark:border-gray-700 px-6 py-4 flex items-center justify-between">
            <div class="flex items-center gap-4">
                <button id="menu-toggle" class="p-2 hover:bg-gray-100 dark:hover:bg-gray-800 rounded-lg transition-colors">
                    <i data-lucide="menu" class="w-6 h-6 text-gray-600 dark:text-gray-400"></i>
                </button>
                <h1 class="text-xl font-bold text-gray-900 dark:text-white">إدارة المهام</h1>
            </div>

            <div class="flex items-center gap-4">
                <button id="theme-toggle" class="p-2 hover:bg-gray-100 dark:hover:bg-gray-800 rounded-lg transition-colors">
                    <i data-lucide="sun" class="w-5 h-5 text-gray-600 dark:text-gray-400"></i>
                </button>
                <button onclick="openAddTaskModal()" class="flex items-center gap-2 px-4 py-2 bg-primary-sand text-white rounded-lg hover:bg-primary-dark transition-colors">
                    <i data-lucide="plus" class="w-4 h-4"></i><span>إضافة مهمة</span>
                </button>
            </div>
        </header>

        <main class="p-6">
            <!-- Messages -->
            <?php if ($success_message): ?>
                <div class="mb-6 p-4 rounded-2xl border bg-green-50 border-green-200 text-green-800 dark:bg-green-900/20 dark:border-green-800 dark:text-green-400 fade-in">
                    <div class="flex items-center gap-3">
                        <i data-lucide="check-circle" class="w-5 h-5"></i>
                        <span><?= htmlspecialchars($success_message) ?></span>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($error_message): ?>
                <div class="mb-6 p-4 rounded-2xl border bg-red-50 border-red-200 text-red-800 dark:bg-red-900/20 dark:border-red-800 dark:text-red-400 fade-in">
                    <div class="flex items-center gap-3">
                        <i data-lucide="alert-circle" class="w-5 h-5"></i>
                        <span><?= htmlspecialchars($error_message) ?></span>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Statistics -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-6 gap-6 mb-6">
                <?php 
                $stat_cards = [
                    ['title' => 'إجمالي المهام', 'value' => $stats['total_tasks'], 'icon' => 'check-square', 'color' => 'blue'],
                    ['title' => 'قيد الانتظار', 'value' => $stats['pending_count'], 'icon' => 'clock', 'color' => 'yellow'],
                    ['title' => 'قيد التنفيذ', 'value' => $stats['in_progress_count'], 'icon' => 'play-circle', 'color' => 'blue'],
                    ['title' => 'مكتملة', 'value' => $stats['completed_count'], 'icon' => 'check-circle', 'color' => 'green'],
                    ['title' => 'ملغية', 'value' => $stats['cancelled_count'], 'icon' => 'x-circle', 'color' => 'red'],
                    ['title' => 'متأخرة', 'value' => $stats['overdue_count'], 'icon' => 'alert-triangle', 'color' => 'red']
                ];
                foreach ($stat_cards as $card): ?>
                    <div class="bg-white dark:bg-gray-900 rounded-2xl p-6 border border-gray-200 dark:border-gray-700">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-gray-500 dark:text-gray-400 text-sm"><?= $card['title'] ?></p>
                                <p class="text-2xl font-bold text-gray-900 dark:text-white"><?= $card['value'] ?></p>
                            </div>
                            <div class="w-12 h-12 bg-<?= $card['color'] ?>-100 dark:bg-<?= $card['color'] ?>-900/30 rounded-xl flex items-center justify-center">
                                <i data-lucide="<?= $card['icon'] ?>" class="w-6 h-6 text-<?= $card['color'] ?>-600"></i>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Search and Filter -->
            <div class="bg-white dark:bg-gray-900 rounded-2xl border border-gray-200 dark:border-gray-700 p-6 mb-6">
                <form method="GET" class="grid grid-cols-1 md:grid-cols-6 gap-4 items-end">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">البحث</label>
                        <div class="relative">
                            <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="ابحث عن مهمة..." class="w-full pl-10 pr-4 py-3 border border-gray-300 dark:border-gray-600 rounded-xl focus:ring-2 focus:ring-primary-sand focus:border-transparent bg-white dark:bg-gray-800 text-gray-900 dark:text-white">
                            <i data-lucide="search" class="absolute left-3 top-1/2 transform -translate-y-1/2 w-4 h-4 text-gray-400"></i>
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">الحالة</label>
                        <select name="status" class="w-full px-4 py-3 border border-gray-300 dark:border-gray-600 rounded-xl focus:ring-2 focus:ring-primary-sand focus:border-transparent bg-white dark:bg-gray-800 text-gray-900 dark:text-white">
                            <option value="">جميع الحالات</option>
                            <option value="pending" <?= $status_filter === 'pending' ? 'selected' : '' ?>>قيد الانتظار</option>
                            <option value="in_progress" <?= $status_filter === 'in_progress' ? 'selected' : '' ?>>قيد التنفيذ</option>
                            <option value="completed" <?= $status_filter === 'completed' ? 'selected' : '' ?>>مكتملة</option>
                            <option value="cancelled" <?= $status_filter === 'cancelled' ? 'selected' : '' ?>>ملغية</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">الأولوية</label>
                        <select name="priority" class="w-full px-4 py-3 border border-gray-300 dark:border-gray-600 rounded-xl focus:ring-2 focus:ring-primary-sand focus:border-transparent bg-white dark:bg-gray-800 text-gray-900 dark:text-white">
                            <option value="">جميع الأولويات</option>
                            <?php foreach ($priorities_options as $value => $label): ?>
                                <option value="<?= $value ?>" <?= $priority_filter === $value ? 'selected' : '' ?>><?= $label ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">المكلف</label>
                        <select name="assigned" class="w-full px-4 py-3 border border-gray-300 dark:border-gray-600 rounded-xl focus:ring-2 focus:ring-primary-sand focus:border-transparent bg-white dark:bg-gray-800 text-gray-900 dark:text-white">
                            <option value="">جميع المكلفين</option>
                            <?php foreach ($users as $user): ?>
                                <option value="<?= $user['id'] ?>" <?= $assigned_filter == $user['id'] ? 'selected' : '' ?>><?= htmlspecialchars($user['fullname']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">الفئة</label>
                        <select name="category" class="w-full px-4 py-3 border border-gray-300 dark:border-gray-600 rounded-xl focus:ring-2 focus:ring-primary-sand focus:border-transparent bg-white dark:bg-gray-800 text-gray-900 dark:text-white">
                            <option value="">جميع الفئات</option>
                            <?php foreach ($categories_options as $value => $label): ?>
                                <option value="<?= $value ?>" <?= $category_filter === $value ? 'selected' : '' ?>><?= $label ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="flex gap-2">
                        <button type="submit" class="flex-1 px-4 py-3 bg-primary-sand text-white rounded-xl hover:bg-primary-dark transition-colors">
                            <i data-lucide="filter" class="w-4 h-4 inline ml-1"></i>فلترة
                        </button>
                        <a href="tasks.php" class="px-4 py-3 bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded-xl hover:bg-gray-300 dark:hover:bg-gray-600 transition-colors">
                            <i data-lucide="refresh-cw" class="w-4 h-4"></i>
                        </a>
                    </div>
                </form>
            </div>

            <!-- Tasks Grid -->
            <?php if (empty($tasks)): ?>
                <div class="bg-white dark:bg-gray-900 rounded-2xl border border-gray-200 dark:border-gray-700 p-12 text-center">
                    <div class="w-20 h-20 bg-gray-100 dark:bg-gray-800 rounded-full flex items-center justify-center mx-auto mb-4">
                        <i data-lucide="check-square" class="w-10 h-10 text-gray-400"></i>
                    </div>
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-2">لا توجد مهام</h3>
                    <p class="text-gray-500 dark:text-gray-400 mb-4">لم يتم العثور على مهام تطابق معايير البحث</p>
                    <button onclick="openAddTaskModal()" class="px-6 py-3 bg-primary-sand text-white rounded-xl hover:bg-primary-dark transition-colors">إضافة مهمة جديدة</button>
                </div>
            <?php else: ?>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    <?php foreach ($tasks as $task): ?>
                        <div class="task-card bg-white dark:bg-gray-900 rounded-2xl border border-gray-200 dark:border-gray-700 p-6 fade-in">
                            <div class="flex items-start justify-between mb-4">
                                <div class="flex-1">
                                    <h3 class="font-bold text-gray-900 dark:text-white text-lg mb-2 <?= ($task['status'] === 'completed' || $task['status'] === 'cancelled') ? 'line-through opacity-70' : '' ?>">
                                        <?= htmlspecialchars($task['title']) ?>
                                        <?php if (isOverdue($task['due_date'], $task['status'])): ?>
                                            <i data-lucide="alert-triangle" class="w-4 h-4 text-red-600 inline mr-1"></i>
                                        <?php endif; ?>
                                    </h3>
                                    <?php if ($task['description']): ?>
                                        <p class="text-gray-600 dark:text-gray-400 text-sm mb-3 line-clamp-2">
                                            <?= htmlspecialchars(mb_substr($task['description'], 0, 100)) ?>
                                            <?= mb_strlen($task['description']) > 100 ? '...' : '' ?>
                                        </p>
                                    <?php endif; ?>
                                </div>
                                <div class="relative">
                                    <button onclick="toggleTaskMenu(<?= $task['id'] ?>)" class="p-2 hover:bg-gray-100 dark:hover:bg-gray-800 rounded-lg transition-colors">
                                        <i data-lucide="more-vertical" class="w-4 h-4 text-gray-500"></i>
                                    </button>
                                    <div id="task-menu-<?= $task['id'] ?>" class="absolute left-0 mt-2 w-48 bg-white dark:bg-gray-800 rounded-xl shadow-lg border border-gray-200 dark:border-gray-700 hidden z-10">
                                        <?php if ($task['status'] !== 'completed' && $task['status'] !== 'cancelled'): ?>
                                            <button onclick="editTask(<?= $task['id'] ?>)" class="w-full text-right px-4 py-2 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 rounded-t-xl">
                                                <i data-lucide="edit" class="w-4 h-4 inline ml-2"></i>تعديل
                                            </button>
                                        <?php endif; ?>
                                        <button onclick="deleteTask(<?= $task['id'] ?>, '<?= htmlspecialchars($task['title']) ?>')" class="w-full text-right px-4 py-2 text-red-600 hover:bg-red-50 dark:hover:bg-red-900/20 rounded-b-xl">
                                            <i data-lucide="trash-2" class="w-4 h-4 inline ml-2"></i>حذف
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <div class="space-y-3 mb-4">
                                <div class="flex items-center gap-2 flex-wrap">
                                    <span class="px-3 py-1 text-xs font-medium rounded-full bg-<?= getStatusColor($task['status']) ?>-100 text-<?= getStatusColor($task['status']) ?>-800 dark:bg-<?= getStatusColor($task['status']) ?>-900/20 dark:text-<?= getStatusColor($task['status']) ?>-400">
                                        <?= getStatusDisplayName($task['status']) ?>
                                    </span>
                                    
                                    <span class="px-3 py-1 text-xs font-medium rounded-full bg-<?= getPriorityColor($task['priority']) ?>-100 text-<?= getPriorityColor($task['priority']) ?>-800 dark:bg-<?= getPriorityColor($task['priority']) ?>-900/20 dark:text-<?= getPriorityColor($task['priority']) ?>-400">
                                        <i data-lucide="<?= $task['priority'] === 'urgent' ? 'alert-triangle' : ($task['priority'] === 'high' ? 'arrow-up' : ($task['priority'] === 'low' ? 'arrow-down' : 'minus')) ?>" class="w-3 h-3 inline ml-1"></i>
                                        <?= getPriorityDisplayName($task['priority']) ?>
                                    </span>
                                    
                                    <span class="px-3 py-1 text-xs font-medium rounded-full bg-purple-100 text-purple-800 dark:bg-purple-900/20 dark:text-purple-400">
                                        <?= getCategoryDisplayName($task['category']) ?>
                                    </span>
                                </div>

                                <?php if ($task['assigned_name']): ?>
                                    <div class="flex items-center gap-2 text-sm text-gray-600 dark:text-gray-400">
                                        <i data-lucide="user" class="w-4 h-4"></i>
                                        <span>مكلف: <?= htmlspecialchars($task['assigned_name']) ?></span>
                                    </div>
                                <?php endif; ?>

                                <?php if ($task['created_name'] && $task['created_name'] !== $task['assigned_name']): ?>
                                    <div class="flex items-center gap-2 text-sm text-gray-600 dark:text-gray-400">
                                        <i data-lucide="user-plus" class="w-4 h-4"></i>
                                        <span>أنشأها: <?= htmlspecialchars($task['created_name']) ?></span>
                                    </div>
                                <?php endif; ?>

                                <?php if ($task['due_date']): ?>
                                    <div class="flex items-center gap-2 text-sm text-gray-600 dark:text-gray-400 <?= isOverdue($task['due_date'], $task['status']) ? 'text-red-600 font-semibold' : '' ?>">
                                        <i data-lucide="calendar" class="w-4 h-4"></i>
                                        <span>موعد الانتهاء: <?= formatDate($task['due_date']) ?></span>
                                    </div>
                                <?php endif; ?>

                                <div class="flex items-center gap-2 text-sm text-gray-600 dark:text-gray-400">
                                    <i data-lucide="clock" class="w-4 h-4"></i>
                                    <span>
                                        <?php if ($task['status'] === 'completed' && $task['completed_date']): ?>
                                            اكتملت: <?= formatDateTime($task['completed_date']) ?>
                                        <?php else: ?>
                                            أنشئت: <?= formatDateTime($task['created_at']) ?>
                                        <?php endif; ?>
                                    </span>
                                </div>
                            </div>

                            <!-- أزرار تغيير الحالة -->
                            <div class="border-t border-gray-200 dark:border-gray-700 pt-4">
                                <div class="flex gap-2">
                                    <?php if ($task['status'] === 'pending'): ?>
                                        <button onclick="changeTaskStatus(<?= $task['id'] ?>, 'in_progress')" class="flex-1 px-3 py-2 text-xs bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
                                            <i data-lucide="play" class="w-3 h-3 inline ml-1"></i>بدء التنفيذ
                                        </button>
                                        <button onclick="changeTaskStatus(<?= $task['id'] ?>, 'cancelled')" class="px-3 py-2 text-xs bg-red-600 text-white rounded-lg hover:bg-red-700 transition-colors">
                                            <i data-lucide="x" class="w-3 h-3 inline ml-1"></i>إلغاء
                                        </button>
                                        
                                    <?php elseif ($task['status'] === 'in_progress'): ?>
                                        <button onclick="changeTaskStatus(<?= $task['id'] ?>, 'completed')" class="flex-1 px-3 py-2 text-xs bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors">
                                            <i data-lucide="check" class="w-3 h-3 inline ml-1"></i>إكمال
                                        </button>
                                        <button onclick="changeTaskStatus(<?= $task['id'] ?>, 'pending')" class="px-3 py-2 text-xs bg-yellow-600 text-white rounded-lg hover:bg-yellow-700 transition-colors">
                                            <i data-lucide="pause" class="w-3 h-3 inline ml-1"></i>إيقاف
                                        </button>
                                        
                                    <?php elseif ($task['status'] === 'completed'): ?>
                                        <button onclick="changeTaskStatus(<?= $task['id'] ?>, 'in_progress')" class="w-full px-3 py-2 text-xs bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
                                            <i data-lucide="rotate-ccw" class="w-3 h-3 inline ml-1"></i>إعادة فتح
                                        </button>
                                        
                                    <?php elseif ($task['status'] === 'cancelled'): ?>
                                        <button onclick="changeTaskStatus(<?= $task['id'] ?>, 'pending')" class="w-full px-3 py-2 text-xs bg-yellow-600 text-white rounded-lg hover:bg-yellow-700 transition-colors">
                                            <i data-lucide="refresh-cw" class="w-3 h-3 inline ml-1"></i>إعادة تفعيل
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </main>
    </div>

    <!-- Add Task Modal -->
    <div id="addTaskModal" class="fixed inset-0 bg-black bg-opacity-50 modal hidden z-50">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="bg-white dark:bg-gray-900 rounded-3xl p-6 w-full max-w-lg border border-gray-200 dark:border-gray-700">
                <div class="flex items-center justify-between mb-6">
                    <h3 class="text-xl font-bold text-gray-900 dark:text-white">إضافة مهمة جديدة</h3>
                    <button onclick="closeAddTaskModal()" class="p-2 hover:bg-gray-100 dark:hover:bg-gray-800 rounded-lg transition-colors">
                        <i data-lucide="x" class="w-5 h-5 text-gray-500"></i>
                    </button>
                </div>

                <form method="POST" class="space-y-4">
                    <input type="hidden" name="action" value="add_task">
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">عنوان المهمة *</label>
                        <input type="text" name="title" required class="w-full px-4 py-3 border border-gray-300 dark:border-gray-600 rounded-xl focus:ring-2 focus:ring-primary-sand focus:border-transparent bg-white dark:bg-gray-800 text-gray-900 dark:text-white" placeholder="أدخل عنوان المهمة">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">وصف المهمة</label>
                        <textarea name="description" rows="3" class="w-full px-4 py-3 border border-gray-300 dark:border-gray-600 rounded-xl focus:ring-2 focus:ring-primary-sand focus:border-transparent bg-white dark:bg-gray-800 text-gray-900 dark:text-white" placeholder="وصف تفصيلي للمهمة (اختياري)"></textarea>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">المكلف بالمهمة</label>
                            <select name="assigned_to" class="w-full px-4 py-3 border border-gray-300 dark:border-gray-600 rounded-xl focus:ring-2 focus:ring-primary-sand focus:border-transparent bg-white dark:bg-gray-800 text-gray-900 dark:text-white">
                                <option value="">اختر المكلف</option>
                                <?php foreach ($users as $user): ?>
                                    <option value="<?= $user['id'] ?>" <?= $user['id'] == $current_user_id ? 'selected' : '' ?>><?= htmlspecialchars($user['fullname']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">الأولوية</label>
                            <select name="priority" class="w-full px-4 py-3 border border-gray-300 dark:border-gray-600 rounded-xl focus:ring-2 focus:ring-primary-sand focus:border-transparent bg-white dark:bg-gray-800 text-gray-900 dark:text-white">
                                <?php foreach ($priorities_options as $value => $label): ?>
                                    <option value="<?= $value ?>" <?= $value === 'medium' ? 'selected' : '' ?>><?= $label ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">تاريخ الانتهاء</label>
                            <input type="date" name="due_date" min="<?= date('Y-m-d') ?>" class="w-full px-4 py-3 border border-gray-300 dark:border-gray-600 rounded-xl focus:ring-2 focus:ring-primary-sand focus:border-transparent bg-white dark:bg-gray-800 text-gray-900 dark:text-white">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">الفئة</label>
                            <select name="category" class="w-full px-4 py-3 border border-gray-300 dark:border-gray-600 rounded-xl focus:ring-2 focus:ring-primary-sand focus:border-transparent bg-white dark:bg-gray-800 text-gray-900 dark:text-white">
                                <?php foreach ($categories_options as $value => $label): ?>
                                    <option value="<?= $value ?>" <?= $value === 'general' ? 'selected' : '' ?>><?= $label ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="flex gap-3 pt-4">
                        <button type="submit" class="flex-1 px-6 py-3 bg-primary-sand text-white rounded-xl hover:bg-primary-dark transition-colors">إضافة المهمة</button>
                        <button type="button" onclick="closeAddTaskModal()" class="px-6 py-3 bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded-xl hover:bg-gray-300 dark:hover:bg-gray-600 transition-colors">إلغاء</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Task Modal -->
    <div id="editTaskModal" class="fixed inset-0 bg-black bg-opacity-50 modal hidden z-50">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="bg-white dark:bg-gray-900 rounded-3xl p-6 w-full max-w-lg border border-gray-200 dark:border-gray-700">
                <div class="flex items-center justify-between mb-6">
                    <h3 class="text-xl font-bold text-gray-900 dark:text-white">تعديل المهمة</h3>
                    <button onclick="closeEditTaskModal()" class="p-2 hover:bg-gray-100 dark:hover:bg-gray-800 rounded-lg transition-colors">
                        <i data-lucide="x" class="w-5 h-5 text-gray-500"></i>
                    </button>
                </div>

                <form method="POST" id="editTaskForm" class="space-y-4">
                    <input type="hidden" name="action" value="update_task">
                    <input type="hidden" name="task_id" id="edit_task_id">
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">عنوان المهمة *</label>
                        <input type="text" name="title" id="edit_title" required class="w-full px-4 py-3 border border-gray-300 dark:border-gray-600 rounded-xl focus:ring-2 focus:ring-primary-sand focus:border-transparent bg-white dark:bg-gray-800 text-gray-900 dark:text-white">
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">وصف المهمة</label>
                        <textarea name="description" id="edit_description" rows="3" class="w-full px-4 py-3 border border-gray-300 dark:border-gray-600 rounded-xl focus:ring-2 focus:ring-primary-sand focus:border-transparent bg-white dark:bg-gray-800 text-gray-900 dark:text-white"></textarea>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">المكلف بالمهمة</label>
                            <select name="assigned_to" id="edit_assigned_to" class="w-full px-4 py-3 border border-gray-300 dark:border-gray-600 rounded-xl focus:ring-2 focus:ring-primary-sand focus:border-transparent bg-white dark:bg-gray-800 text-gray-900 dark:text-white">
                                <option value="">اختر المكلف</option>
                                <?php foreach ($users as $user): ?>
                                    <option value="<?= $user['id'] ?>"><?= htmlspecialchars($user['fullname']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">الأولوية</label>
                            <select name="priority" id="edit_priority" class="w-full px-4 py-3 border border-gray-300 dark:border-gray-600 rounded-xl focus:ring-2 focus:ring-primary-sand focus:border-transparent bg-white dark:bg-gray-800 text-gray-900 dark:text-white">
                                <?php foreach ($priorities_options as $value => $label): ?>
                                    <option value="<?= $value ?>"><?= $label ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">تاريخ الانتهاء</label>
                            <input type="date" name="due_date" id="edit_due_date" class="w-full px-4 py-3 border border-gray-300 dark:border-gray-600 rounded-xl focus:ring-2 focus:ring-primary-sand focus:border-transparent bg-white dark:bg-gray-800 text-gray-900 dark:text-white">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">الفئة</label>
                            <select name="category" id="edit_category" class="w-full px-4 py-3 border border-gray-300 dark:border-gray-600 rounded-xl focus:ring-2 focus:ring-primary-sand focus:border-transparent bg-white dark:bg-gray-800 text-gray-900 dark:text-white">
                                <?php foreach ($categories_options as $value => $label): ?>
                                    <option value="<?= $value ?>"><?= $label ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="flex gap-3 pt-4">
                        <button type="submit" class="flex-1 px-6 py-3 bg-primary-sand text-white rounded-xl hover:bg-primary-dark transition-colors">تحديث المهمة</button>
                        <button type="button" onclick="closeEditTaskModal()" class="px-6 py-3 bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded-xl hover:bg-gray-300 dark:hover:bg-gray-600 transition-colors">إلغاء</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            lucide.createIcons();
            initTheme();
            
            // Auto-hide messages
            const messages = document.querySelectorAll('.fade-in');
            messages.forEach(message => {
                if (message.classList.contains('bg-green-50') || message.classList.contains('bg-red-50')) {
                    setTimeout(() => {
                        message.style.opacity = '0';
                        setTimeout(() => message.remove(), 300);
                    }, 5000);
                }
            });
        });

        let sidebarOpen = false;
        const menuToggle = document.getElementById('menu-toggle');
        const sidebar = document.getElementById('sidebar');
        const closeSidebar = document.getElementById('close-sidebar');
        const mainContent = document.getElementById('main-content');
        const themeToggle = document.getElementById('theme-toggle');

        menuToggle.addEventListener('click', toggleSidebar);
        closeSidebar.addEventListener('click', closeSidebarFunc);

        function toggleSidebar() {
            sidebarOpen = !sidebarOpen;
            sidebar.classList.toggle('translate-x-full', !sidebarOpen);
            if (window.innerWidth >= 768) {
                mainContent.classList.toggle('mr-80', sidebarOpen);
            }
        }

        function closeSidebarFunc() {
            sidebar.classList.add('translate-x-full');
            mainContent.classList.remove('mr-80');
            sidebarOpen = false;
        }

        document.addEventListener('click', (e) => {
            if (sidebarOpen && !sidebar.contains(e.target) && !menuToggle.contains(e.target)) {
                closeSidebarFunc();
            }
            if (!e.target.closest('[onclick^="toggleTaskMenu"]') && !e.target.closest('[id^="task-menu-"]')) {
                document.querySelectorAll('[id^="task-menu-"]').forEach(menu => menu.classList.add('hidden'));
            }
        });

        function initTheme() {
            const savedTheme = localStorage.getItem('theme');
            const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
            const isDark = savedTheme === 'dark' || (!savedTheme && prefersDark);
            
            document.documentElement.classList.toggle('dark', isDark);
            updateThemeIcon(isDark);
        }

        themeToggle.addEventListener('click', () => {
            const isDark = document.documentElement.classList.contains('dark');
            document.documentElement.classList.toggle('dark', !isDark);
            localStorage.setItem('theme', isDark ? 'light' : 'dark');
            updateThemeIcon(!isDark);
        });

        function updateThemeIcon(isDark) {
            const icon = themeToggle.querySelector('i');
            icon.setAttribute('data-lucide', isDark ? 'sun' : 'moon');
            lucide.createIcons();
        }

        function openAddTaskModal() { document.getElementById('addTaskModal').classList.remove('hidden'); }
        function closeAddTaskModal() { document.getElementById('addTaskModal').classList.add('hidden'); }
        function openEditTaskModal() { document.getElementById('editTaskModal').classList.remove('hidden'); }
        function closeEditTaskModal() { document.getElementById('editTaskModal').classList.add('hidden'); }

        function toggleTaskMenu(taskId) {
            const menu = document.getElementById(`task-menu-${taskId}`);
            document.querySelectorAll('[id^="task-menu-"]').forEach(m => {
                if (m.id !== `task-menu-${taskId}`) m.classList.add('hidden');
            });
            menu.classList.toggle('hidden');
        }

        function editTask(taskId) {
            document.getElementById(`task-menu-${taskId}`).classList.add('hidden');
            const tasks = <?= json_encode($tasks) ?>;
            const task = tasks.find(t => t.id == taskId);
            
            if (task) {
                document.getElementById('edit_task_id').value = task.id;
                document.getElementById('edit_title').value = task.title;
                document.getElementById('edit_description').value = task.description || '';
                document.getElementById('edit_assigned_to').value = task.assigned_to || '';
                document.getElementById('edit_priority').value = task.priority;
                document.getElementById('edit_due_date').value = task.due_date || '';
                document.getElementById('edit_category').value = task.category;
                openEditTaskModal();
            }
        }

        function deleteTask(taskId, taskTitle) {
            document.getElementById(`task-menu-${taskId}`).classList.add('hidden');
            if (confirm(`هل أنت متأكد من حذف المهمة "${taskTitle}"؟\n\nلا يمكن التراجع عن هذا الإجراء.`)) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.style.display = 'none';
                form.innerHTML = `
                    <input type="hidden" name="action" value="delete_task">
                    <input type="hidden" name="task_id" value="${taskId}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        function changeTaskStatus(taskId, newStatus) {
            const statusMessages = {
                'pending': 'إرجاع المهمة لقائمة الانتظار',
                'in_progress': 'بدء تنفيذ المهمة',
                'completed': 'إكمال المهمة',
                'cancelled': 'إلغاء المهمة'
            };
            
            const message = statusMessages[newStatus] || 'تغيير حالة المهمة';
            
            if (confirm(`هل أنت متأكد من ${message}؟`)) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.style.display = 'none';
                form.innerHTML = `
                    <input type="hidden" name="action" value="update_status">
                    <input type="hidden" name="task_id" value="${taskId}">
                    <input type="hidden" name="status" value="${newStatus}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        function confirmLogout() {
            if (confirm('هل أنت متأكد من تسجيل الخروج؟')) {
                window.location.href = 'logout.php';
            }
        }

        // Close modals when clicking outside
        window.addEventListener('click', function(event) {
            const addModal = document.getElementById('addTaskModal');
            const editModal = document.getElementById('editTaskModal');
            
            if (event.target === addModal) {
                closeAddTaskModal();
            }
            if (event.target === editModal) {
                closeEditTaskModal();
            }
        });

        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            // Escape key to close modals
            if (e.key === 'Escape') {
                closeAddTaskModal();
                closeEditTaskModal();
            }
            
            // Ctrl+N to add new task
            if (e.ctrlKey && e.key === 'n') {
                e.preventDefault();
                openAddTaskModal();
            }
        });

        // Auto refresh every 5 minutes to keep data fresh
        setInterval(() => {
            // Only refresh if no modal is open
            const addModal = document.getElementById('addTaskModal');
            const editModal = document.getElementById('editTaskModal');
            
            if (addModal.classList.contains('hidden') && editModal.classList.contains('hidden')) {
                window.location.reload();
            }
        }, 300000); // 5 minutes

        // Format dates in Arabic locale
        function formatArabicDate(dateString) {
            if (!dateString) return 'غير محدد';
            const date = new Date(dateString);
            return date.toLocaleDateString('ar-EG');
        }

        // Update time display every minute for relative time
        setInterval(() => {
            const timeElements = document.querySelectorAll('[data-time]');
            timeElements.forEach(element => {
                const time = element.getAttribute('data-time');
                if (time) {
                    element.textContent = formatRelativeTime(time);
                }
            });
        }, 60000);

        function formatRelativeTime(dateString) {
            const date = new Date(dateString);
            const now = new Date();
            const diffInSeconds = Math.floor((now - date) / 1000);
            
            if (diffInSeconds < 60) return 'الآن';
            if (diffInSeconds < 3600) return `${Math.floor(diffInSeconds / 60)} دقيقة`;
            if (diffInSeconds < 86400) return `${Math.floor(diffInSeconds / 3600)} ساعة`;
            if (diffInSeconds < 2592000) return `${Math.floor(diffInSeconds / 86400)} يوم`;
            
            return formatArabicDate(dateString);
        }

        // Handle network connectivity
        window.addEventListener('online', () => {
            console.log('الاتصال بالإنترنت متاح');
        });

        window.addEventListener('offline', () => {
            console.log('لا يوجد اتصال بالإنترنت');
        });

        // Initialize tooltips and enhanced UX
        document.addEventListener('DOMContentLoaded', function() {
            // Add loading states to buttons
            const buttons = document.querySelectorAll('button[type="submit"]');
            buttons.forEach(button => {
                button.addEventListener('click', function() {
                    if (this.form && this.form.checkValidity()) {
                        this.disabled = true;
                        this.innerHTML = '<i data-lucide="loader-2" class="w-4 h-4 animate-spin inline ml-1"></i>جاري الحفظ...';
                    }
                });
            });

            // Add focus management for modals
            const modals = document.querySelectorAll('.modal');
            modals.forEach(modal => {
                modal.addEventListener('keydown', function(e) {
                    if (e.key === 'Tab') {
                        // Handle tab navigation within modal
                        const focusableElements = modal.querySelectorAll('button, input, select, textarea');
                        const firstElement = focusableElements[0];
                        const lastElement = focusableElements[focusableElements.length - 1];

                        if (e.shiftKey && document.activeElement === firstElement) {
                            e.preventDefault();
                            lastElement.focus();
                        } else if (!e.shiftKey && document.activeElement === lastElement) {
                            e.preventDefault();
                            firstElement.focus();
                        }
                    }
                });
            });

            // Auto-save draft functionality for task forms
            const titleInput = document.querySelector('input[name="title"]');
            const descriptionInput = document.querySelector('textarea[name="description"]');
            
            if (titleInput && descriptionInput) {
                [titleInput, descriptionInput].forEach(input => {
                    input.addEventListener('input', function() {
                        localStorage.setItem(`draft_${this.name}`, this.value);
                    });
                });

                // Restore drafts
                titleInput.value = localStorage.getItem('draft_title') || '';
                descriptionInput.value = localStorage.getItem('draft_description') || '';

                // Clear drafts on successful submit
                const forms = document.querySelectorAll('form');
                forms.forEach(form => {
                    form.addEventListener('submit', function() {
                        localStorage.removeItem('draft_title');
                        localStorage.removeItem('draft_description');
                    });
                });
            }
        });
    </script>
</body>
</html>