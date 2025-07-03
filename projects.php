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
        if ($action === 'create_project') {
            $data = [
                'name' => trim($_POST['name'] ?? ''),
                'description' => trim($_POST['description'] ?? ''),
                'manager_id' => $_POST['managerId'] ?? null,
                'color' => $_POST['color'] ?? '#3B82F6',
                'start_date' => $_POST['startDate'] ?? null,
                'end_date' => $_POST['endDate'] ?? null,
                'team_members' => $_POST['teamMembers'] ?? [],
                'status' => 'active'
            ];
            
            if (empty($data['name'])) {
                throw new Exception('اسم المشروع مطلوب');
            }
            
            // إدراج المشروع
            $stmt = $conn->prepare("
                INSERT INTO projects (name, description, manager_id, color, start_date, end_date, status, created_by, created_at, updated_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
            ");
            $stmt->execute([$data['name'], $data['description'], $data['manager_id'], $data['color'], $data['start_date'], $data['end_date'], $data['status'], $current_user_id]);
            
            $project_id = $conn->lastInsertId();
            
            // إضافة أعضاء الفريق
            if (!empty($data['team_members']) && is_array($data['team_members'])) {
                $team_stmt = $conn->prepare("INSERT INTO project_members (project_id, user_id) VALUES (?, ?)");
                foreach ($data['team_members'] as $member_id) {
                    if (!empty($member_id)) {
                        $team_stmt->execute([$project_id, $member_id]);
                    }
                }
            }
            
            header('Location: projects.php?success=project_created');
            exit();
        }
        
        if ($action === 'update_project') {
            $project_id = $_POST['project_id'] ?? null;
            $data = [
                'name' => trim($_POST['name'] ?? ''),
                'description' => trim($_POST['description'] ?? ''),
                'manager_id' => $_POST['managerId'] ?? null,
                'color' => $_POST['color'] ?? '#3B82F6',
                'start_date' => $_POST['startDate'] ?? null,
                'end_date' => $_POST['endDate'] ?? null,
                'team_members' => $_POST['teamMembers'] ?? [],
                'status' => $_POST['status'] ?? 'active'
            ];
            
            if (!$project_id || empty($data['name'])) {
                throw new Exception('اسم المشروع مطلوب');
            }
            
            $stmt = $conn->prepare("UPDATE projects SET name = ?, description = ?, manager_id = ?, color = ?, start_date = ?, end_date = ?, status = ?, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$data['name'], $data['description'], $data['manager_id'], $data['color'], $data['start_date'], $data['end_date'], $data['status'], $project_id]);
            
            // تحديث أعضاء الفريق
            $conn->prepare("DELETE FROM project_members WHERE project_id = ?")->execute([$project_id]);
            if (!empty($data['team_members']) && is_array($data['team_members'])) {
                $team_stmt = $conn->prepare("INSERT INTO project_members (project_id, user_id) VALUES (?, ?)");
                foreach ($data['team_members'] as $member_id) {
                    if (!empty($member_id)) {
                        $team_stmt->execute([$project_id, $member_id]);
                    }
                }
            }
            
            header('Location: projects.php?success=project_updated');
            exit();
        }
        
        if ($action === 'delete_project') {
            $project_id = $_POST['project_id'] ?? null;
            
            if (!$project_id) {
                throw new Exception('معرف المشروع مطلوب');
            }
            
            // حذف أعضاء الفريق أولاً
            $conn->prepare("DELETE FROM project_members WHERE project_id = ?")->execute([$project_id]);
            
            // حذف المشروع
            $stmt = $conn->prepare("DELETE FROM projects WHERE id = ?");
            $stmt->execute([$project_id]);
            
            header('Location: projects.php?success=project_deleted');
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
    'project_created' => 'تم إنشاء المشروع بنجاح!',
    'project_updated' => 'تم تحديث المشروع بنجاح!',
    'project_deleted' => 'تم حذف المشروع بنجاح!'
];
if (isset($_GET['success']) && isset($success_messages[$_GET['success']])) {
    $success_message = $success_messages[$_GET['success']];
}

// معاملات البحث والفلترة
$search = $_GET['search'] ?? '';
$status_filter = $_GET['status'] ?? '';
$manager_filter = $_GET['manager'] ?? '';

// بناء الاستعلام
$where_conditions = ['1=1'];
$params = [];

if (!empty($search)) {
    $where_conditions[] = "(p.name LIKE ? OR p.description LIKE ?)";
    $params = array_merge($params, ["%$search%", "%$search%"]);
}

foreach (['status' => $status_filter, 'manager_id' => $manager_filter] as $field => $value) {
    if (!empty($value)) {
        $where_conditions[] = "p.$field = ?";
        $params[] = $value;
    }
}

$where_clause = 'WHERE ' . implode(' AND ', $where_conditions);

try {
    // جلب المشاريع مع معلومات المدير وعدد المهام
    $projects_sql = "SELECT p.*, 
                            manager.fullname as manager_name,
                            COUNT(DISTINCT t.id) as total_tasks,
                            COUNT(DISTINCT CASE WHEN t.status = 'completed' THEN t.id END) as completed_tasks,
                            COUNT(DISTINCT pm.user_id) as team_count
                     FROM projects p
                     LEFT JOIN users manager ON p.manager_id = manager.id
                     LEFT JOIN tasks t ON p.id = t.project_id
                     LEFT JOIN project_members pm ON p.id = pm.project_id
                     $where_clause 
                     GROUP BY p.id
                     ORDER BY p.created_at DESC";
    
    $stmt = $conn->prepare($projects_sql);
    $stmt->execute($params);
    $projects = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // جلب المديرين للفلاتر
    $managers_stmt = $conn->prepare("SELECT id, fullname FROM users WHERE role IN ('admin', 'manager') ORDER BY fullname");
    $managers_stmt->execute();
    $managers = $managers_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // جلب جميع المستخدمين لأعضاء الفريق
    $users_stmt = $conn->prepare("SELECT id, fullname, role FROM users ORDER BY fullname");
    $users_stmt->execute();
    $users = $users_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // إحصائيات عامة
    $stats_stmt = $conn->prepare("
        SELECT 
            COUNT(*) as total_projects,
            COUNT(CASE WHEN status = 'active' THEN 1 END) as active_count,
            COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed_count,
            COUNT(CASE WHEN status = 'on_hold' THEN 1 END) as on_hold_count,
            COUNT(CASE WHEN status = 'cancelled' THEN 1 END) as cancelled_count
        FROM projects
    ");
    $stats_stmt->execute();
    $stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);
    
    // جلب عدد المهام الإجمالي
    $tasks_count_stmt = $conn->prepare("SELECT COUNT(*) as total_tasks FROM tasks");
    $tasks_count_stmt->execute();
    $total_tasks = $tasks_count_stmt->fetch(PDO::FETCH_ASSOC)['total_tasks'];
    
} catch (PDOException $e) {
    $error_message = 'خطأ في جلب البيانات';
    $projects = [];
    $managers = [];
    $users = [];
    $stats = ['total_projects' => 0, 'active_count' => 0, 'completed_count' => 0, 'on_hold_count' => 0, 'cancelled_count' => 0];
    $total_tasks = 0;
    error_log("DB Error: " . $e->getMessage());
}

// دوال مساعدة
function formatDate($date) {
    return $date ? date('d/m/Y', strtotime($date)) : 'غير محدد';
}

function getStatusDisplayName($status) {
    $statuses = [
        'active' => 'نشط',
        'completed' => 'مكتمل',
        'on_hold' => 'معلق',
        'cancelled' => 'ملغي'
    ];
    return $statuses[$status] ?? $status;
}

function getStatusColor($status) {
    $colors = [
        'active' => 'green',
        'completed' => 'blue',
        'on_hold' => 'yellow',
        'cancelled' => 'red'
    ];
    return $colors[$status] ?? 'gray';
}

function getRoleDisplayName($role) {
    $roles = [
        'admin' => 'مدير النظام',
        'manager' => 'مدير',
        'employee' => 'موظف',
        'client' => 'عميل'
    ];
    return $roles[$role] ?? $role;
}

// جلب أعضاء الفريق لمشروع معين
function getProjectTeamMembers($project_id, $conn) {
    $stmt = $conn->prepare("
        SELECT u.id, u.fullname, u.role 
        FROM project_members pm 
        JOIN users u ON pm.user_id = u.id 
        WHERE pm.project_id = ?
        ORDER BY u.fullname
    ");
    $stmt->execute([$project_id]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إدارة المشاريع - كوريان كاسيل</title>
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
        .project-card { transition: all 0.3s ease; }
        .project-card:hover { transform: translateY(-4px); box-shadow: 0 20px 40px rgba(0,0,0,0.1); }
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
                        <p class="text-sm text-gray-500 dark:text-gray-400">إدارة المشاريع</p>
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
                        <p class="text-sm text-gray-600 dark:text-gray-400"><?= getRoleDisplayName($current_user_role) ?></p>
                    </div>
                </div>
            </div>

            <nav class="space-y-2">
                <a href="dashboard.php" class="nav-item flex items-center gap-3 p-3 rounded-xl transition-all">
                    <i data-lucide="layout-dashboard" class="w-5 h-5"></i><span>الرئيسية</span>
                </a>
                <a href="tasks.php" class="nav-item flex items-center gap-3 p-3 rounded-xl transition-all">
                    <i data-lucide="check-square" class="w-5 h-5"></i><span>المهام</span>
                </a>
                <a href="#" class="nav-item active flex items-center gap-3 p-3 rounded-xl transition-all bg-primary-sand/10 text-primary-sand">
                    <i data-lucide="folder" class="w-5 h-5"></i><span>المشاريع</span>
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
                <h1 class="text-xl font-bold text-gray-900 dark:text-white">إدارة المشاريع</h1>
            </div>

            <div class="flex items-center gap-4">
                <button id="theme-toggle" class="p-2 hover:bg-gray-100 dark:hover:bg-gray-800 rounded-lg transition-colors">
                    <i data-lucide="sun" class="w-5 h-5 text-gray-600 dark:text-gray-400"></i>
                </button>
                <button onclick="openCreateProjectModal()" class="flex items-center gap-2 px-4 py-2 bg-primary-sand text-white rounded-lg hover:bg-primary-dark transition-colors">
                    <i data-lucide="plus" class="w-4 h-4"></i><span>مشروع جديد</span>
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
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-6">
                <div class="bg-white dark:bg-gray-900 rounded-2xl p-6 border border-gray-200 dark:border-gray-700">
                    <div class="flex items-center gap-4">
                        <div class="w-16 h-16 bg-blue-100 dark:bg-blue-900/20 rounded-2xl flex items-center justify-center">
                            <i data-lucide="folder" class="w-8 h-8 text-blue-600"></i>
                        </div>
                        <div>
                            <p class="text-3xl font-bold text-gray-900 dark:text-white"><?= $stats['total_projects'] ?></p>
                            <p class="text-sm text-gray-500 dark:text-gray-400">إجمالي المشاريع</p>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white dark:bg-gray-900 rounded-2xl p-6 border border-gray-200 dark:border-gray-700">
                    <div class="flex items-center gap-4">
                        <div class="w-16 h-16 bg-green-100 dark:bg-green-900/20 rounded-2xl flex items-center justify-center">
                            <i data-lucide="play-circle" class="w-8 h-8 text-green-600"></i>
                        </div>
                        <div>
                            <p class="text-2xl font-bold text-green-600"><?= $stats['active_count'] ?></p>
                            <p class="text-sm text-gray-500 dark:text-gray-400">مشاريع نشطة</p>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white dark:bg-gray-900 rounded-2xl p-6 border border-gray-200 dark:border-gray-700">
                    <div class="flex items-center gap-4">
                        <div class="w-16 h-16 bg-blue-100 dark:bg-blue-900/20 rounded-2xl flex items-center justify-center">
                            <i data-lucide="check-circle" class="w-8 h-8 text-blue-600"></i>
                        </div>
                        <div>
                            <p class="text-2xl font-bold text-blue-600"><?= $stats['completed_count'] ?></p>
                            <p class="text-sm text-gray-500 dark:text-gray-400">مشاريع مكتملة</p>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white dark:bg-gray-900 rounded-2xl p-6 border border-gray-200 dark:border-gray-700">
                    <div class="flex items-center gap-4">
                        <div class="w-16 h-16 bg-primary-sand/20 rounded-2xl flex items-center justify-center">
                            <i data-lucide="list-checks" class="w-8 h-8 text-primary-sand"></i>
                        </div>
                        <div>
                            <p class="text-2xl font-bold text-primary-sand"><?= $total_tasks ?></p>
                            <p class="text-sm text-gray-500 dark:text-gray-400">إجمالي المهام</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Search and Filter -->
            <div class="bg-white dark:bg-gray-900 rounded-2xl border border-gray-200 dark:border-gray-700 p-6 mb-6">
                <form method="GET" class="grid grid-cols-1 md:grid-cols-4 gap-4 items-end">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">البحث</label>
                        <div class="relative">
                            <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="ابحث عن مشروع..." class="w-full pl-10 pr-4 py-3 border border-gray-300 dark:border-gray-600 rounded-xl focus:ring-2 focus:ring-primary-sand focus:border-transparent bg-white dark:bg-gray-800 text-gray-900 dark:text-white">
                            <i data-lucide="search" class="absolute left-3 top-1/2 transform -translate-y-1/2 w-4 h-4 text-gray-400"></i>
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">الحالة</label>
                        <select name="status" class="w-full px-4 py-3 border border-gray-300 dark:border-gray-600 rounded-xl focus:ring-2 focus:ring-primary-sand focus:border-transparent bg-white dark:bg-gray-800 text-gray-900 dark:text-white">
                            <option value="">جميع الحالات</option>
                            <option value="active" <?= $status_filter === 'active' ? 'selected' : '' ?>>نشط</option>
                            <option value="completed" <?= $status_filter === 'completed' ? 'selected' : '' ?>>مكتمل</option>
                            <option value="on_hold" <?= $status_filter === 'on_hold' ? 'selected' : '' ?>>معلق</option>
                            <option value="cancelled" <?= $status_filter === 'cancelled' ? 'selected' : '' ?>>ملغي</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">المدير</label>
                        <select name="manager" class="w-full px-4 py-3 border border-gray-300 dark:border-gray-600 rounded-xl focus:ring-2 focus:ring-primary-sand focus:border-transparent bg-white dark:bg-gray-800 text-gray-900 dark:text-white">
                            <option value="">جميع المديرين</option>
                            <?php foreach ($managers as $manager): ?>
                                <option value="<?= $manager['id'] ?>" <?= $manager_filter == $manager['id'] ? 'selected' : '' ?>><?= htmlspecialchars($manager['fullname']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="flex gap-2">
                        <button type="submit" class="flex-1 px-4 py-3 bg-primary-sand text-white rounded-xl hover:bg-primary-dark transition-colors">
                            <i data-lucide="filter" class="w-4 h-4 inline ml-1"></i>فلترة
                        </button>
                        <a href="projects.php" class="px-4 py-3 bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded-xl hover:bg-gray-300 dark:hover:bg-gray-600 transition-colors">
                            <i data-lucide="refresh-cw" class="w-4 h-4"></i>
                        </a>
                    </div>
                </form>
            </div>

            <!-- Projects Grid -->
            <?php if (empty($projects)): ?>
                <div class="bg-white dark:bg-gray-900 rounded-2xl border border-gray-200 dark:border-gray-700 p-12 text-center">
                    <div class="w-20 h-20 bg-gray-100 dark:bg-gray-800 rounded-full flex items-center justify-center mx-auto mb-4">
                        <i data-lucide="folder-plus" class="w-10 h-10 text-gray-400"></i>
                    </div>
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-2">لا توجد مشاريع</h3>
                    <p class="text-gray-500 dark:text-gray-400 mb-4">لم يتم العثور على مشاريع تطابق معايير البحث</p>
                    <button onclick="openCreateProjectModal()" class="px-6 py-3 bg-primary-sand text-white rounded-xl hover:bg-primary-dark transition-colors">إنشاء مشروع جديد</button>
                </div>
            <?php else: ?>
                <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-6">
                    <?php foreach ($projects as $project): ?>
                        <?php 
                        $team_members = getProjectTeamMembers($project['id'], $conn);
                        $progress_percentage = $project['total_tasks'] > 0 ? round(($project['completed_tasks'] / $project['total_tasks']) * 100) : 0;
                        ?>
                        <div class="project-card bg-white dark:bg-gray-900 rounded-3xl border border-gray-200 dark:border-gray-700 p-6 fade-in">
                            <div class="flex items-start justify-between mb-4">
                                <div class="flex-1">
                                    <div class="flex items-center gap-3 mb-2">
                                        <div class="w-3 h-3 rounded-full" style="background-color: <?= htmlspecialchars($project['color']) ?>"></div>
                                        <h3 class="text-lg font-bold text-gray-900 dark:text-white"><?= htmlspecialchars($project['name']) ?></h3>
                                    </div>
                                    <?php if ($project['description']): ?>
                                        <p class="text-gray-600 dark:text-gray-400 text-sm line-clamp-2"><?= htmlspecialchars($project['description']) ?></p>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="relative">
                                    <button onclick="toggleProjectMenu(<?= $project['id'] ?>)" class="p-2 hover:bg-gray-100 dark:hover:bg-gray-800 rounded-lg transition-colors">
                                        <i data-lucide="more-vertical" class="w-4 h-4 text-gray-500"></i>
                                    </button>
                                    <div id="project-menu-<?= $project['id'] ?>" class="absolute left-0 mt-2 w-48 bg-white dark:bg-gray-800 rounded-xl shadow-lg border border-gray-200 dark:border-gray-700 hidden z-10">
                                        <button onclick="viewProject(<?= $project['id'] ?>)" class="w-full text-right px-4 py-2 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 rounded-t-xl">
                                            <i data-lucide="eye" class="w-4 h-4 inline ml-2"></i>عرض التفاصيل
                                        </button>
                                        <button onclick="editProject(<?= $project['id'] ?>)" class="w-full text-right px-4 py-2 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700">
                                            <i data-lucide="edit" class="w-4 h-4 inline ml-2"></i>تعديل
                                        </button>
                                        <button onclick="deleteProject(<?= $project['id'] ?>, '<?= htmlspecialchars($project['name']) ?>')" class="w-full text-right px-4 py-2 text-red-600 hover:bg-red-50 dark:hover:bg-red-900/20 rounded-b-xl">
                                            <i data-lucide="trash-2" class="w-4 h-4 inline ml-2"></i>حذف
                                        </button>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mb-4">
                                <div class="flex items-center justify-between mb-2">
                                    <span class="text-sm font-medium text-gray-700 dark:text-gray-300">التقدم</span>
                                    <span class="text-sm font-bold text-gray-900 dark:text-white"><?= $progress_percentage ?>%</span>
                                </div>
                                <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-2">
                                    <div class="h-2 rounded-full transition-all duration-300" style="width: <?= $progress_percentage ?>%; background-color: <?= htmlspecialchars($project['color']) ?>"></div>
                                </div>
                            </div>
                            
                            <div class="flex items-center justify-between mb-4">
                                <div class="flex items-center gap-2 text-sm text-gray-600 dark:text-gray-400">
                                    <i data-lucide="list-checks" class="w-4 h-4"></i>
                                    <span><?= $project['completed_tasks'] ?>/<?= $project['total_tasks'] ?> مهام</span>
                                </div>
                                
                                <span class="px-3 py-1 text-xs font-medium rounded-full bg-<?= getStatusColor($project['status']) ?>-100 text-<?= getStatusColor($project['status']) ?>-800 dark:bg-<?= getStatusColor($project['status']) ?>-900/20 dark:text-<?= getStatusColor($project['status']) ?>-400">
                                    <?= getStatusDisplayName($project['status']) ?>
                                </span>
                            </div>
                            
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-2">
                                    <div class="w-6 h-6 rounded-full bg-gradient-to-br from-primary-sand to-primary-light flex items-center justify-center text-white text-xs font-bold">
                                        <?= $project['manager_name'] ? mb_substr($project['manager_name'], 0, 1, 'UTF-8') : '؟' ?>
                                    </div>
                                    <span class="text-sm text-gray-600 dark:text-gray-400">
                                        <?= $project['manager_name'] ? htmlspecialchars($project['manager_name']) : 'غير محدد' ?>
                                    </span>
                                </div>
                                
                                <?php if (!empty($team_members)): ?>
                                    <div class="flex items-center gap-1">
                                        <div class="flex -space-x-2">
                                            <?php foreach (array_slice($team_members, 0, 3) as $member): ?>
                                                <div class="w-6 h-6 rounded-full bg-gradient-to-br from-blue-500 to-purple-500 flex items-center justify-center text-white text-xs font-bold border-2 border-white dark:border-gray-900" title="<?= htmlspecialchars($member['fullname']) ?>">
                                                    <?= mb_substr($member['fullname'], 0, 1, 'UTF-8') ?>
                                                </div>
                                            <?php endforeach; ?>
                                            <?php if (count($team_members) > 3): ?>
                                                <div class="w-6 h-6 rounded-full bg-gray-400 dark:bg-gray-600 flex items-center justify-center text-white text-xs font-bold border-2 border-white dark:border-gray-900">
                                                    +<?= count($team_members) - 3 ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <?php if ($project['end_date']): ?>
                                <div class="mt-4 pt-4 border-t border-gray-200 dark:border-gray-700">
                                    <div class="flex items-center gap-2 text-sm text-gray-500 dark:text-gray-400">
                                        <i data-lucide="calendar" class="w-4 h-4"></i>
                                        <span>ينتهي في <?= formatDate($project['end_date']) ?></span>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </main>
    </div>

    <!-- Create Project Modal -->
    <div id="createProjectModal" class="fixed inset-0 bg-black bg-opacity-50 modal hidden z-50">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="bg-white dark:bg-gray-900 rounded-3xl p-6 w-full max-w-2xl border border-gray-200 dark:border-gray-700 max-h-[90vh] overflow-y-auto">
                <div class="flex items-center justify-between mb-6">
                    <h3 id="modal-title" class="text-xl font-semibold text-gray-900 dark:text-white">إنشاء مشروع جديد</h3>
                    <button onclick="closeCreateProjectModal()" class="p-2 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 rounded-lg">
                        <i data-lucide="x" class="w-6 h-6"></i>
                    </button>
                </div>
                
                <form method="POST" id="project-form" class="space-y-6">
                    <input type="hidden" name="action" value="create_project" id="form-action">
                    <input type="hidden" name="project_id" id="project-id">
                    
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-3">
                            اسم المشروع *
                        </label>
                        <input
                            type="text"
                            name="name"
                            id="project-name"
                            required
                            placeholder="أدخل اسم المشروع..."
                            class="w-full px-4 py-3 rounded-2xl border-2 border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-white placeholder-gray-500 dark:placeholder-gray-400 focus:border-primary-sand focus:outline-none transition-colors"
                        />
                    </div>
                    
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-3">
                            وصف المشروع
                        </label>
                        <textarea
                            name="description"
                            id="project-description"
                            rows="3"
                            placeholder="اكتب وصفاً للمشروع..."
                            class="w-full px-4 py-3 rounded-2xl border-2 border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-white placeholder-gray-500 dark:placeholder-gray-400 focus:border-primary-sand focus:outline-none transition-colors resize-none"
                        ></textarea>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-3">
                                مدير المشروع *
                            </label>
                            <select
                                name="managerId"
                                id="project-manager"
                                required
                                class="w-full px-4 py-3 rounded-2xl border-2 border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-white focus:border-primary-sand focus:outline-none transition-colors"
                            >
                                <option value="">اختر المدير</option>
                                <?php foreach ($managers as $manager): ?>
                                    <option value="<?= $manager['id'] ?>"><?= htmlspecialchars($manager['fullname']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-3">
                                لون المشروع
                            </label>
                            <div class="flex gap-2">
                                <input
                                    type="color"
                                    name="color"
                                    id="project-color"
                                    value="#3B82F6"
                                    class="w-12 h-12 rounded-lg border-2 border-gray-300 dark:border-gray-600 cursor-pointer"
                                />
                                <input
                                    type="text"
                                    id="project-color-text"
                                    value="#3B82F6"
                                    class="flex-1 px-4 py-3 rounded-2xl border-2 border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-white focus:border-primary-sand focus:outline-none transition-colors"
                                />
                            </div>
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-3">
                                تاريخ البداية
                            </label>
                            <input
                                type="date"
                                name="startDate"
                                id="project-start-date"
                                class="w-full px-4 py-3 rounded-2xl border-2 border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-white focus:border-primary-sand focus:outline-none transition-colors"
                            />
                        </div>
                        
                        <div>
                            <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-3">
                                تاريخ الانتهاء المتوقع
                            </label>
                            <input
                                type="date"
                                name="endDate"
                                id="project-end-date"
                                class="w-full px-4 py-3 rounded-2xl border-2 border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-white focus:border-primary-sand focus:outline-none transition-colors"
                            />
                        </div>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-semibold text-gray-700 dark:text-gray-300 mb-3">
                            أعضاء الفريق
                        </label>
                        <div id="team-members-container" class="space-y-2 max-h-40 overflow-y-auto">
                            <?php foreach ($users as $user): ?>
                                <label class="flex items-center gap-3 p-3 rounded-lg border border-gray-200 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-800 cursor-pointer">
                                    <input type="checkbox" name="teamMembers[]" value="<?= $user['id'] ?>" class="rounded">
                                    <div class="flex items-center gap-2">
                                        <div class="w-8 h-8 rounded-full bg-gradient-to-br from-primary-sand to-primary-light flex items-center justify-center text-white text-sm font-bold">
                                            <?= mb_substr($user['fullname'], 0, 1, 'UTF-8') ?>
                                        </div>
                                        <div>
                                            <p class="font-medium text-gray-900 dark:text-white"><?= htmlspecialchars($user['fullname']) ?></p>
                                            <p class="text-sm text-gray-500 dark:text-gray-400"><?= getRoleDisplayName($user['role']) ?></p>
                                        </div>
                                    </div>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    
                    <div class="flex justify-end gap-3 pt-4 border-t border-gray-200 dark:border-gray-700">
                        <button 
                            type="button"
                            onclick="closeCreateProjectModal()"
                            class="px-6 py-3 bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300 rounded-2xl hover:bg-gray-200 dark:hover:bg-gray-700 transition-colors"
                        >
                            إلغاء
                        </button>
                        
                        <button 
                            type="submit"
                            class="px-6 py-3 bg-gradient-to-r from-primary-sand to-primary-light text-white rounded-2xl hover:from-primary-light hover:to-primary-sand transition-all duration-300"
                        >
                            <div class="flex items-center gap-2">
                                <i data-lucide="save" class="w-5 h-5"></i>
                                <span id="submit-text">حفظ المشروع</span>
                            </div>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- View Project Modal -->
    <div id="viewProjectModal" class="fixed inset-0 bg-black bg-opacity-50 modal hidden z-50">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="bg-white dark:bg-gray-900 rounded-3xl p-6 w-full max-w-4xl border border-gray-200 dark:border-gray-700 max-h-[90vh] overflow-y-auto">
                <div class="flex items-center justify-between mb-6">
                    <h3 id="view-modal-title" class="text-xl font-semibold text-gray-900 dark:text-white">تفاصيل المشروع</h3>
                    <button onclick="closeViewProjectModal()" class="p-2 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 rounded-lg">
                        <i data-lucide="x" class="w-6 h-6"></i>
                    </button>
                </div>
                
                <div id="project-details-content">
                    <!-- سيتم ملء المحتوى بواسطة JavaScript -->
                </div>
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
            if (!e.target.closest('[onclick^="toggleProjectMenu"]') && !e.target.closest('[id^="project-menu-"]')) {
                document.querySelectorAll('[id^="project-menu-"]').forEach(menu => menu.classList.add('hidden'));
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

        // Modal functions
        function openCreateProjectModal() {
            document.getElementById('createProjectModal').classList.remove('hidden');
            document.getElementById('modal-title').textContent = 'إنشاء مشروع جديد';
            document.getElementById('form-action').value = 'create_project';
            document.getElementById('submit-text').textContent = 'حفظ المشروع';
            document.getElementById('project-form').reset();
            document.getElementById('project-color').value = '#3B82F6';
            document.getElementById('project-color-text').value = '#3B82F6';
        }

        function closeCreateProjectModal() {
            document.getElementById('createProjectModal').classList.add('hidden');
        }

        function openViewProjectModal() {
            document.getElementById('viewProjectModal').classList.remove('hidden');
        }

        function closeViewProjectModal() {
            document.getElementById('viewProjectModal').classList.add('hidden');
        }

        function toggleProjectMenu(projectId) {
            const menu = document.getElementById(`project-menu-${projectId}`);
            document.querySelectorAll('[id^="project-menu-"]').forEach(m => {
                if (m.id !== `project-menu-${projectId}`) m.classList.add('hidden');
            });
            menu.classList.toggle('hidden');
        }

        function editProject(projectId) {
            document.getElementById(`project-menu-${projectId}`).classList.add('hidden');
            
            // جلب بيانات المشروع من PHP
            const projects = <?= json_encode($projects) ?>;
            const project = projects.find(p => p.id == projectId);
            
            if (project) {
                document.getElementById('modal-title').textContent = 'تعديل المشروع';
                document.getElementById('form-action').value = 'update_project';
                document.getElementById('project-id').value = project.id;
                document.getElementById('submit-text').textContent = 'تحديث المشروع';
                
                document.getElementById('project-name').value = project.name;
                document.getElementById('project-description').value = project.description || '';
                document.getElementById('project-manager').value = project.manager_id || '';
                document.getElementById('project-color').value = project.color || '#3B82F6';
                document.getElementById('project-color-text').value = project.color || '#3B82F6';
                document.getElementById('project-start-date').value = project.start_date || '';
                document.getElementById('project-end-date').value = project.end_date || '';
                
                // جلب أعضاء الفريق للمشروع
                fetch(`get_project_team.php?id=${projectId}`)
                    .then(response => response.json())
                    .then(teamMembers => {
                        const checkboxes = document.querySelectorAll('input[name="teamMembers[]"]');
                        checkboxes.forEach(checkbox => {
                            checkbox.checked = teamMembers.includes(checkbox.value);
                        });
                    })
                    .catch(error => console.error('Error:', error));
                
                openCreateProjectModal();
            }
        }

        function viewProject(projectId) {
            document.getElementById(`project-menu-${projectId}`).classList.add('hidden');
            
            const projects = <?= json_encode($projects) ?>;
            const project = projects.find(p => p.id == projectId);
            
            if (project) {
                document.getElementById('view-modal-title').textContent = project.name;
                
                // إنشاء محتوى التفاصيل
                const progressPercentage = project.total_tasks > 0 ? Math.round((project.completed_tasks / project.total_tasks) * 100) : 0;
                
                fetch(`get_project_details.php?id=${projectId}`)
                    .then(response => response.json())
                    .then(data => {
                        const content = `
                            <div class="space-y-6">
                                <div class="bg-gray-50 dark:bg-gray-800 rounded-2xl p-6">
                                    <div class="flex items-start gap-4 mb-4">
                                        <div class="w-4 h-4 rounded-full" style="background-color: ${project.color}"></div>
                                        <div class="flex-1">
                                            <h4 class="text-lg font-bold text-gray-900 dark:text-white mb-2">${project.name}</h4>
                                            ${project.description ? `<p class="text-gray-600 dark:text-gray-400">${project.description}</p>` : ''}
                                        </div>
                                        <span class="px-3 py-1 text-sm font-medium rounded-full bg-${getStatusColor(project.status)}-100 text-${getStatusColor(project.status)}-800">
                                            ${getStatusDisplayName(project.status)}
                                        </span>
                                    </div>
                                </div>
                                
                                <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                                    <div class="bg-blue-50 dark:bg-blue-900/20 rounded-xl p-4 text-center">
                                        <p class="text-2xl font-bold text-blue-600">${project.total_tasks}</p>
                                        <p class="text-sm text-blue-600">إجمالي المهام</p>
                                    </div>
                                    <div class="bg-green-50 dark:bg-green-900/20 rounded-xl p-4 text-center">
                                        <p class="text-2xl font-bold text-green-600">${project.completed_tasks}</p>
                                        <p class="text-sm text-green-600">مكتملة</p>
                                    </div>
                                    <div class="bg-yellow-50 dark:bg-yellow-900/20 rounded-xl p-4 text-center">
                                        <p class="text-2xl font-bold text-yellow-600">${project.total_tasks - project.completed_tasks}</p>
                                        <p class="text-sm text-yellow-600">متبقية</p>
                                    </div>
                                    <div class="bg-purple-50 dark:bg-purple-900/20 rounded-xl p-4 text-center">
                                        <p class="text-2xl font-bold text-purple-600">${project.team_count}</p>
                                        <p class="text-sm text-purple-600">أعضاء الفريق</p>
                                    </div>
                                </div>
                                
                                <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-2xl p-6">
                                    <h5 class="font-semibold text-gray-900 dark:text-white mb-4">تقدم المشروع</h5>
                                    <div class="flex items-center gap-4 mb-2">
                                        <div class="flex-1 bg-gray-200 dark:bg-gray-700 rounded-full h-3">
                                            <div class="h-3 rounded-full transition-all duration-500" style="width: ${progressPercentage}%; background-color: ${project.color}"></div>
                                        </div>
                                        <span class="text-lg font-bold text-gray-900 dark:text-white">${progressPercentage}%</span>
                                    </div>
                                    <p class="text-sm text-gray-500 dark:text-gray-400">تم إنجاز ${project.completed_tasks} من ${project.total_tasks} مهام</p>
                                </div>
                                
                                ${data.teamMembers && data.teamMembers.length > 0 ? `
                                    <div class="bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-2xl p-6">
                                        <h5 class="font-semibold text-gray-900 dark:text-white mb-4">فريق العمل</h5>
                                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                            ${data.teamMembers.map(member => `
                                                <div class="flex items-center gap-3">
                                                    <div class="w-10 h-10 rounded-full bg-gradient-to-br from-blue-500 to-purple-500 flex items-center justify-center text-white font-bold">
                                                        ${member.fullname.charAt(0)}
                                                    </div>
                                                    <div>
                                                        <p class="font-medium text-gray-900 dark:text-white">${member.fullname}</p>
                                                        <p class="text-sm text-gray-500 dark:text-gray-400">${getRoleDisplayName(member.role)}</p>
                                                    </div>
                                                </div>
                                            `).join('')}
                                        </div>
                                    </div>
                                ` : ''}
                            </div>
                        `;
                        
                        document.getElementById('project-details-content').innerHTML = content;
                        lucide.createIcons();
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        document.getElementById('project-details-content').innerHTML = '<p class="text-red-500">خطأ في تحميل التفاصيل</p>';
                    });
                
                openViewProjectModal();
            }
        }

        function deleteProject(projectId, projectName) {
            document.getElementById(`project-menu-${projectId}`).classList.add('hidden');
            
            if (confirm(`هل أنت متأكد من حذف المشروع "${projectName}"؟\n\nسيتم حذف جميع المهام المرتبطة بالمشروع.\n\nلا يمكن التراجع عن هذا الإجراء.`)) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.style.display = 'none';
                form.innerHTML = `
                    <input type="hidden" name="action" value="delete_project">
                    <input type="hidden" name="project_id" value="${projectId}">
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

        // Color picker synchronization
        document.addEventListener('DOMContentLoaded', function() {
            const colorPicker = document.getElementById('project-color');
            const colorText = document.getElementById('project-color-text');
            
            if (colorPicker && colorText) {
                colorPicker.addEventListener('change', (e) => {
                    colorText.value = e.target.value;
                });
                
                colorText.addEventListener('change', (e) => {
                    if (/^#[0-9A-F]{6}$/i.test(e.target.value)) {
                        colorPicker.value = e.target.value;
                    }
                });
            }
        });

        // Helper functions for JavaScript
        function getStatusColor(status) {
            const colors = {
                'active': 'green',
                'completed': 'blue',
                'on_hold': 'yellow',
                'cancelled': 'red'
            };
            return colors[status] || 'gray';
        }

        function getStatusDisplayName(status) {
            const statuses = {
                'active': 'نشط',
                'completed': 'مكتمل',
                'on_hold': 'معلق',
                'cancelled': 'ملغي'
            };
            return statuses[status] || status;
        }

        function getRoleDisplayName(role) {
            const roles = {
                'admin': 'مدير النظام',
                'manager': 'مدير',
                'employee': 'موظف',
                'client': 'عميل'
            };
            return roles[role] || role;
        }

        // Close modals when clicking outside
        window.addEventListener('click', function(event) {
            const createModal = document.getElementById('createProjectModal');
            const viewModal = document.getElementById('viewProjectModal');
            
            if (event.target === createModal) {
                closeCreateProjectModal();
            }
            if (event.target === viewModal) {
                closeViewProjectModal();
            }
        });

        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeCreateProjectModal();
                closeViewProjectModal();
            }
            
            if (e.ctrlKey && e.key === 'n') {
                e.preventDefault();
                openCreateProjectModal();
            }
        });

        // Form validation
        document.getElementById('project-form').addEventListener('submit', function(e) {
            const name = document.getElementById('project-name').value.trim();
            const manager = document.getElementById('project-manager').value;
            
            if (!name) {
                e.preventDefault();
                alert('اسم المشروع مطلوب');
                return false;
            }
            
            if (!manager) {
                e.preventDefault();
                alert('مدير المشروع مطلوب');
                return false;
            }
            
            // Add loading state
            const submitBtn = e.target.querySelector('button[type="submit"]');
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<div class="flex items-center gap-2"><i data-lucide="loader-2" class="w-5 h-5 animate-spin"></i><span>جاري الحفظ...</span></div>';
            lucide.createIcons();
        });
    </script>
</body>
</html>