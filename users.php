<?php
session_start();

// التحقق من الجلسة
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php?login_required=1");
    exit;
}

// التحقق من انتهاء الجلسة (30 دقيقة)
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > 1800)) {
    session_destroy();
    header("Location: login.php?session_expired=1");
    exit;
}
$_SESSION['last_activity'] = time();

require_once 'api/config.php';

$current_user_id = $_SESSION['user_id'];
$current_user_role = $_SESSION['user_role'] ?? 'employee';
$success_message = $error_message = '';

// معالجة POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    try {
        if ($action === 'add_user') {
            $data = [
                'fullname' => trim($_POST['fullname'] ?? ''),
                'email' => trim($_POST['email'] ?? ''),
                'password' => $_POST['password'] ?? '',
                'role' => $_POST['role'] ?? 'employee',
                'department' => trim($_POST['department'] ?? ''),
                'specialization' => trim($_POST['specialization'] ?? ''),
                'phone' => trim($_POST['phone'] ?? '')
            ];
            
            if (empty($data['fullname']) || empty($data['email']) || empty($data['password'])) {
                throw new Exception('جميع الحقول المطلوبة يجب ملؤها');
            }
            
            if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
                throw new Exception('صيغة البريد الإلكتروني غير صحيحة');
            }
            
            $check_stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
            $check_stmt->execute([$data['email']]);
            if ($check_stmt->fetch()) {
                throw new Exception('البريد الإلكتروني موجود مسبقاً');
            }
            
            $stmt = $conn->prepare("INSERT INTO users (fullname, email, password, role, department, specialization, phone, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())");
            $stmt->execute([$data['fullname'], $data['email'], password_hash($data['password'], PASSWORD_DEFAULT), $data['role'], $data['department'], $data['specialization'], $data['phone']]);
            
            header('Location: users.php?success=user_added');
            exit();
        }
        
        if ($action === 'update_user') {
            $user_id = $_POST['user_id'] ?? null;
            $data = [
                'fullname' => trim($_POST['fullname'] ?? ''),
                'email' => trim($_POST['email'] ?? ''),
                'role' => $_POST['role'] ?? 'employee',
                'department' => trim($_POST['department'] ?? ''),
                'specialization' => trim($_POST['specialization'] ?? ''),
                'phone' => trim($_POST['phone'] ?? '')
            ];
            
            if (!$user_id || empty($data['fullname']) || empty($data['email'])) {
                throw new Exception('جميع الحقول المطلوبة يجب ملؤها');
            }
            
            $check_stmt = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
            $check_stmt->execute([$data['email'], $user_id]);
            if ($check_stmt->fetch()) {
                throw new Exception('البريد الإلكتروني موجود مع مستخدم آخر');
            }
            
            $stmt = $conn->prepare("UPDATE users SET fullname = ?, email = ?, role = ?, department = ?, specialization = ?, phone = ?, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$data['fullname'], $data['email'], $data['role'], $data['department'], $data['specialization'], $data['phone'], $user_id]);
            
            header('Location: users.php?success=user_updated');
            exit();
        }
        
        if ($action === 'delete_user') {
            $user_id = $_POST['user_id'] ?? null;
            if (!$user_id || $user_id == $current_user_id) {
                throw new Exception($user_id ? 'لا يمكنك حذف حسابك الشخصي' : 'معرف المستخدم مطلوب');
            }
            
            $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            
            header('Location: users.php?success=user_deleted');
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
    'user_added' => 'تم إضافة المستخدم بنجاح!',
    'user_updated' => 'تم تحديث بيانات المستخدم بنجاح!',
    'user_deleted' => 'تم حذف المستخدم بنجاح!'
];
if (isset($_GET['success']) && isset($success_messages[$_GET['success']])) {
    $success_message = $success_messages[$_GET['success']];
}

// معاملات البحث
$search = $_GET['search'] ?? '';
$role_filter = $_GET['role'] ?? '';
$department_filter = $_GET['department'] ?? '';
$specialization_filter = $_GET['specialization'] ?? '';

// بناء الاستعلام
$where_conditions = ['1=1'];
$params = [];

if (!empty($search)) {
    $where_conditions[] = "(fullname LIKE ? OR email LIKE ?)";
    $params = array_merge($params, ["%$search%", "%$search%"]);
}

foreach (['role' => $role_filter, 'department' => $department_filter, 'specialization' => $specialization_filter] as $field => $value) {
    if (!empty($value)) {
        $where_conditions[] = "$field = ?";
        $params[] = $value;
    }
}

$where_clause = 'WHERE ' . implode(' AND ', $where_conditions);

try {
    // جلب المستخدمين مع إحصائيات المهام
    $users_sql = "SELECT u.*, COUNT(t.id) as total_tasks, COUNT(CASE WHEN t.status = 'completed' THEN 1 END) as completed_tasks 
                  FROM users u LEFT JOIN tasks t ON u.id = t.assigned_to 
                  $where_clause GROUP BY u.id ORDER BY u.created_at DESC";
    
    $stmt = $conn->prepare($users_sql);
    $stmt->execute($params);
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // إحصائيات عامة
    $stats_stmt = $conn->prepare("SELECT COUNT(*) as total_users, COUNT(CASE WHEN role = 'admin' THEN 1 END) as admin_count, COUNT(CASE WHEN role = 'manager' THEN 1 END) as manager_count, COUNT(CASE WHEN role = 'employee' THEN 1 END) as employee_count, COUNT(CASE WHEN role = 'client' THEN 1 END) as client_count FROM users");
    $stats_stmt->execute();
    $stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);
    
    // جلب القوائم للفلترة
    $departments = $conn->query("SELECT DISTINCT department FROM users WHERE department IS NOT NULL AND department != '' ORDER BY department")->fetchAll(PDO::FETCH_COLUMN);
    $specializations = $conn->query("SELECT DISTINCT specialization FROM users WHERE specialization IS NOT NULL AND specialization != '' ORDER BY specialization")->fetchAll(PDO::FETCH_COLUMN);
    
} catch (PDOException $e) {
    $error_message = 'خطأ في جلب البيانات';
    $users = [];
    $stats = ['total_users' => 0, 'admin_count' => 0, 'manager_count' => 0, 'employee_count' => 0, 'client_count' => 0];
    $departments = $specializations = [];
    error_log("DB Error: " . $e->getMessage());
}

// دوال مساعدة
function formatDate($date) { return $date ? date('d/m/Y', strtotime($date)) : 'غير محدد'; }
function getRoleDisplayName($role) {
    $roles = ['admin' => 'مدير النظام', 'manager' => 'مدير', 'employee' => 'موظف', 'client' => 'عميل'];
    return $roles[$role] ?? $role;
}
function getRoleColor($role) {
    $colors = ['admin' => 'red', 'manager' => 'blue', 'employee' => 'green', 'client' => 'purple'];
    return $colors[$role] ?? 'gray';
}

// خيارات الأقسام والتخصصات
$departments_options = [
    'الإدارة العامة', 'الموارد البشرية', 'المالية والمحاسبة', 'تقنية المعلومات', 
    'التسويق والمبيعات', 'خدمة العملاء', 'العمليات', 'التطوير', 'الجودة', 'الأمن'
];

$specializations_options = [
    'إدارة الأعمال', 'المحاسبة', 'الموارد البشرية', 'التسويق الرقمي', 'تطوير الويب', 
    'البرمجة', 'الشبكات', 'الأمن السيبراني', 'التصميم الجرافيكي', 'إدارة المشاريع',
    'تحليل البيانات', 'خدمة العملاء', 'المبيعات', 'الترجمة', 'الكتابة والمحتوى',
    'التصوير', 'المونتاج', 'الهندسة', 'الطب', 'القانون', 'التعليم', 'الاستشارات'
];
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إدارة المستخدمين - كوريان كاسيل</title>
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
        .user-card { transition: all 0.3s ease; }
        .user-card:hover { transform: translateY(-2px); box-shadow: 0 10px 30px rgba(0,0,0,0.1); }
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
                    <div class="w-12 h-12 bg-gradient-to-br from-primary-sand to-primary-light rounded-2xl flex items-center justify-center text-white font-bold text-lg">ك</div>
                    <div>
                        <h2 class="font-bold text-gray-900 dark:text-white">كوريان كاسيل</h2>
                        <p class="text-sm text-gray-500 dark:text-gray-400">إدارة المستخدمين</p>
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
                    <i data-lucide="users" class="w-5 h-5"></i><span>المستخدمين</span>
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
                <h1 class="text-xl font-bold text-gray-900 dark:text-white">إدارة المستخدمين</h1>
            </div>

            <div class="flex items-center gap-4">
                <button id="theme-toggle" class="p-2 hover:bg-gray-100 dark:hover:bg-gray-800 rounded-lg transition-colors">
                    <i data-lucide="sun" class="w-5 h-5 text-gray-600 dark:text-gray-400"></i>
                </button>
                <button onclick="openAddUserModal()" class="flex items-center gap-2 px-4 py-2 bg-primary-sand text-white rounded-lg hover:bg-primary-dark transition-colors">
                    <i data-lucide="user-plus" class="w-4 h-4"></i><span>إضافة مستخدم</span>
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
                <?php 
                $stat_cards = [
                    ['title' => 'إجمالي المستخدمين', 'value' => $stats['total_users'], 'icon' => 'users', 'color' => 'blue'],
                    ['title' => 'المديرين', 'value' => $stats['admin_count'] + $stats['manager_count'], 'icon' => 'shield', 'color' => 'red'],
                    ['title' => 'الموظفين', 'value' => $stats['employee_count'], 'icon' => 'briefcase', 'color' => 'green'],
                    ['title' => 'العملاء', 'value' => $stats['client_count'], 'icon' => 'user-check', 'color' => 'purple']
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
                <form method="GET" class="grid grid-cols-1 md:grid-cols-5 gap-4 items-end">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">البحث</label>
                        <div class="relative">
                            <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="ابحث عن مستخدم..." class="w-full pl-10 pr-4 py-3 border border-gray-300 dark:border-gray-600 rounded-xl focus:ring-2 focus:ring-primary-sand focus:border-transparent bg-white dark:bg-gray-800 text-gray-900 dark:text-white">
                            <i data-lucide="search" class="absolute left-3 top-1/2 transform -translate-y-1/2 w-4 h-4 text-gray-400"></i>
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">الدور</label>
                        <select name="role" class="w-full px-4 py-3 border border-gray-300 dark:border-gray-600 rounded-xl focus:ring-2 focus:ring-primary-sand focus:border-transparent bg-white dark:bg-gray-800 text-gray-900 dark:text-white">
                            <option value="">جميع الأدوار</option>
                            <?php foreach (['admin' => 'مدير النظام', 'manager' => 'مدير', 'employee' => 'موظف', 'client' => 'عميل'] as $value => $label): ?>
                                <option value="<?= $value ?>" <?= $role_filter === $value ? 'selected' : '' ?>><?= $label ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">القسم</label>
                        <select name="department" class="w-full px-4 py-3 border border-gray-300 dark:border-gray-600 rounded-xl focus:ring-2 focus:ring-primary-sand focus:border-transparent bg-white dark:bg-gray-800 text-gray-900 dark:text-white">
                            <option value="">جميع الأقسام</option>
                            <?php foreach ($departments as $dept): ?>
                                <option value="<?= htmlspecialchars($dept) ?>" <?= $department_filter === $dept ? 'selected' : '' ?>><?= htmlspecialchars($dept) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">التخصص</label>
                        <select name="specialization" class="w-full px-4 py-3 border border-gray-300 dark:border-gray-600 rounded-xl focus:ring-2 focus:ring-primary-sand focus:border-transparent bg-white dark:bg-gray-800 text-gray-900 dark:text-white">
                            <option value="">جميع التخصصات</option>
                            <?php foreach ($specializations as $spec): ?>
                                <option value="<?= htmlspecialchars($spec) ?>" <?= $specialization_filter === $spec ? 'selected' : '' ?>><?= htmlspecialchars($spec) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="flex gap-2">
                        <button type="submit" class="flex-1 px-4 py-3 bg-primary-sand text-white rounded-xl hover:bg-primary-dark transition-colors">
                            <i data-lucide="filter" class="w-4 h-4 inline ml-1"></i>فلترة
                        </button>
                        <a href="users.php" class="px-4 py-3 bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded-xl hover:bg-gray-300 dark:hover:bg-gray-600 transition-colors">
                            <i data-lucide="refresh-cw" class="w-4 h-4"></i>
                        </a>
                    </div>
                </form>
            </div>

            <!-- Users Grid -->
            <?php if (empty($users)): ?>
                <div class="bg-white dark:bg-gray-900 rounded-2xl border border-gray-200 dark:border-gray-700 p-12 text-center">
                    <div class="w-20 h-20 bg-gray-100 dark:bg-gray-800 rounded-full flex items-center justify-center mx-auto mb-4">
                        <i data-lucide="users" class="w-10 h-10 text-gray-400"></i>
                    </div>
                    <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-2">لا توجد مستخدمين</h3>
                    <p class="text-gray-500 dark:text-gray-400 mb-4">لم يتم العثور على مستخدمين يطابقون معايير البحث</p>
                    <button onclick="openAddUserModal()" class="px-6 py-3 bg-primary-sand text-white rounded-xl hover:bg-primary-dark transition-colors">إضافة مستخدم جديد</button>
                </div>
            <?php else: ?>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    <?php foreach ($users as $user): ?>
                        <div class="user-card bg-white dark:bg-gray-900 rounded-2xl border border-gray-200 dark:border-gray-700 p-6 fade-in">
                            <div class="flex items-center gap-4 mb-4">
                                <div class="w-16 h-16 bg-gradient-to-br from-primary-sand to-primary-light rounded-2xl flex items-center justify-center text-white font-bold text-xl">
                                    <?= mb_substr($user['fullname'], 0, 1, 'UTF-8') ?>
                                </div>
                                <div class="flex-1">
                                    <h3 class="font-bold text-gray-900 dark:text-white text-lg"><?= htmlspecialchars($user['fullname']) ?></h3>
                                    <p class="text-gray-500 dark:text-gray-400 text-sm"><?= htmlspecialchars($user['email']) ?></p>
                                </div>
                                <div class="relative">
                                    <button onclick="toggleUserMenu(<?= $user['id'] ?>)" class="p-2 hover:bg-gray-100 dark:hover:bg-gray-800 rounded-lg transition-colors">
                                        <i data-lucide="more-vertical" class="w-4 h-4 text-gray-500"></i>
                                    </button>
                                    <div id="user-menu-<?= $user['id'] ?>" class="absolute left-0 mt-2 w-48 bg-white dark:bg-gray-800 rounded-xl shadow-lg border border-gray-200 dark:border-gray-700 hidden z-10">
                                        <button onclick="editUser(<?= $user['id'] ?>)" class="w-full text-right px-4 py-2 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 rounded-t-xl">
                                            <i data-lucide="edit" class="w-4 h-4 inline ml-2"></i>تعديل
                                        </button>
                                        <?php if ($user['id'] != $current_user_id): ?>
                                            <button onclick="deleteUser(<?= $user['id'] ?>, '<?= htmlspecialchars($user['fullname']) ?>')" class="w-full text-right px-4 py-2 text-red-600 hover:bg-red-50 dark:hover:bg-red-900/20 rounded-b-xl">
                                                <i data-lucide="trash-2" class="w-4 h-4 inline ml-2"></i>حذف
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>

                            <div class="space-y-3 mb-4">
                                <div class="flex items-center gap-2">
                                    <span class="px-3 py-1 text-xs font-medium rounded-full bg-<?= getRoleColor($user['role']) ?>-100 text-<?= getRoleColor($user['role']) ?>-800 dark:bg-<?= getRoleColor($user['role']) ?>-900/20 dark:text-<?= getRoleColor($user['role']) ?>-400">
                                        <?= getRoleDisplayName($user['role']) ?>
                                    </span>
                                </div>

                                <?php if ($user['department']): ?>
                                    <div class="flex items-center gap-2 text-sm text-gray-600 dark:text-gray-400">
                                        <i data-lucide="building" class="w-4 h-4"></i>
                                        <span><?= htmlspecialchars($user['department']) ?></span>
                                    </div>
                                <?php endif; ?>

                                <?php if ($user['specialization']): ?>
                                    <div class="flex items-center gap-2 text-sm text-gray-600 dark:text-gray-400">
                                        <i data-lucide="award" class="w-4 h-4"></i>
                                        <span><?= htmlspecialchars($user['specialization']) ?></span>
                                    </div>
                                <?php endif; ?>

                                <?php if ($user['phone']): ?>
                                    <div class="flex items-center gap-2 text-sm text-gray-600 dark:text-gray-400">
                                        <i data-lucide="phone" class="w-4 h-4"></i>
                                        <span><?= htmlspecialchars($user['phone']) ?></span>
                                    </div>
                                <?php endif; ?>

                                <div class="flex items-center gap-2 text-sm text-gray-600 dark:text-gray-400">
                                    <i data-lucide="calendar" class="w-4 h-4"></i>
                                    <span>انضم في <?= formatDate($user['created_at']) ?></span>
                                </div>
                            </div>

                            <div class="border-t border-gray-200 dark:border-gray-700 pt-4">
                                <div class="grid grid-cols-2 gap-4 text-center">
                                    <div>
                                        <p class="text-2xl font-bold text-gray-900 dark:text-white"><?= $user['total_tasks'] ?></p>
                                        <p class="text-xs text-gray-500 dark:text-gray-400">إجمالي المهام</p>
                                    </div>
                                    <div>
                                        <p class="text-2xl font-bold text-green-600"><?= $user['completed_tasks'] ?></p>
                                        <p class="text-xs text-gray-500 dark:text-gray-400">مهام مكتملة</p>
                                    </div>
                                </div>
                                
                                <?php if ($user['total_tasks'] > 0): ?>
                                    <div class="mt-3">
                                        <div class="flex justify-between text-xs text-gray-600 dark:text-gray-400 mb-1">
                                            <span>معدل الإنجاز</span>
                                            <span><?= round(($user['completed_tasks'] / $user['total_tasks']) * 100) ?>%</span>
                                        </div>
                                        <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-2">
                                            <div class="bg-primary-sand h-2 rounded-full" style="width: <?= round(($user['completed_tasks'] / $user['total_tasks']) * 100) ?>%"></div>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </main>
    </div>

    <!-- Add User Modal -->
    <div id="addUserModal" class="fixed inset-0 bg-black bg-opacity-50 modal hidden z-50">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="bg-white dark:bg-gray-900 rounded-3xl p-6 w-full max-w-lg border border-gray-200 dark:border-gray-700">
                <div class="flex items-center justify-between mb-6">
                    <h3 class="text-xl font-bold text-gray-900 dark:text-white">إضافة مستخدم جديد</h3>
                    <button onclick="closeAddUserModal()" class="p-2 hover:bg-gray-100 dark:hover:bg-gray-800 rounded-lg transition-colors">
                        <i data-lucide="x" class="w-5 h-5 text-gray-500"></i>
                    </button>
                </div>

                <form method="POST" class="space-y-4">
                    <input type="hidden" name="action" value="add_user">
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">الاسم الكامل *</label>
                            <input type="text" name="fullname" required class="w-full px-4 py-3 border border-gray-300 dark:border-gray-600 rounded-xl focus:ring-2 focus:ring-primary-sand focus:border-transparent bg-white dark:bg-gray-800 text-gray-900 dark:text-white">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">البريد الإلكتروني *</label>
                            <input type="email" name="email" required class="w-full px-4 py-3 border border-gray-300 dark:border-gray-600 rounded-xl focus:ring-2 focus:ring-primary-sand focus:border-transparent bg-white dark:bg-gray-800 text-gray-900 dark:text-white">
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">كلمة المرور *</label>
                            <input type="password" name="password" required class="w-full px-4 py-3 border border-gray-300 dark:border-gray-600 rounded-xl focus:ring-2 focus:ring-primary-sand focus:border-transparent bg-white dark:bg-gray-800 text-gray-900 dark:text-white">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">الدور *</label>
                            <select name="role" required class="w-full px-4 py-3 border border-gray-300 dark:border-gray-600 rounded-xl focus:ring-2 focus:ring-primary-sand focus:border-transparent bg-white dark:bg-gray-800 text-gray-900 dark:text-white">
                                <option value="employee">موظف</option>
                                <option value="manager">مدير</option>
                                <option value="admin">مدير النظام</option>
                                <option value="client">عميل</option>
                            </select>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">القسم</label>
                            <select name="department" class="w-full px-4 py-3 border border-gray-300 dark:border-gray-600 rounded-xl focus:ring-2 focus:ring-primary-sand focus:border-transparent bg-white dark:bg-gray-800 text-gray-900 dark:text-white">
                                <option value="">اختر القسم</option>
                                <?php foreach ($departments_options as $dept): ?>
                                    <option value="<?= $dept ?>"><?= $dept ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">التخصص</label>
                            <select name="specialization" class="w-full px-4 py-3 border border-gray-300 dark:border-gray-600 rounded-xl focus:ring-2 focus:ring-primary-sand focus:border-transparent bg-white dark:bg-gray-800 text-gray-900 dark:text-white">
                                <option value="">اختر التخصص</option>
                                <?php foreach ($specializations_options as $spec): ?>
                                    <option value="<?= $spec ?>"><?= $spec ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">رقم الهاتف</label>
                        <input type="tel" name="phone" class="w-full px-4 py-3 border border-gray-300 dark:border-gray-600 rounded-xl focus:ring-2 focus:ring-primary-sand focus:border-transparent bg-white dark:bg-gray-800 text-gray-900 dark:text-white">
                    </div>

                    <div class="flex gap-3 pt-4">
                        <button type="submit" class="flex-1 px-6 py-3 bg-primary-sand text-white rounded-xl hover:bg-primary-dark transition-colors">إضافة المستخدم</button>
                        <button type="button" onclick="closeAddUserModal()" class="px-6 py-3 bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded-xl hover:bg-gray-300 dark:hover:bg-gray-600 transition-colors">إلغاء</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit User Modal -->
    <div id="editUserModal" class="fixed inset-0 bg-black bg-opacity-50 modal hidden z-50">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="bg-white dark:bg-gray-900 rounded-3xl p-6 w-full max-w-lg border border-gray-200 dark:border-gray-700">
                <div class="flex items-center justify-between mb-6">
                    <h3 class="text-xl font-bold text-gray-900 dark:text-white">تعديل بيانات المستخدم</h3>
                    <button onclick="closeEditUserModal()" class="p-2 hover:bg-gray-100 dark:hover:bg-gray-800 rounded-lg transition-colors">
                        <i data-lucide="x" class="w-5 h-5 text-gray-500"></i>
                    </button>
                </div>

                <form method="POST" id="editUserForm" class="space-y-4">
                    <input type="hidden" name="action" value="update_user">
                    <input type="hidden" name="user_id" id="edit_user_id">
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">الاسم الكامل *</label>
                            <input type="text" name="fullname" id="edit_fullname" required class="w-full px-4 py-3 border border-gray-300 dark:border-gray-600 rounded-xl focus:ring-2 focus:ring-primary-sand focus:border-transparent bg-white dark:bg-gray-800 text-gray-900 dark:text-white">
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">البريد الإلكتروني *</label>
                            <input type="email" name="email" id="edit_email" required class="w-full px-4 py-3 border border-gray-300 dark:border-gray-600 rounded-xl focus:ring-2 focus:ring-primary-sand focus:border-transparent bg-white dark:bg-gray-800 text-gray-900 dark:text-white">
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">الدور *</label>
                        <select name="role" id="edit_role" required class="w-full px-4 py-3 border border-gray-300 dark:border-gray-600 rounded-xl focus:ring-2 focus:ring-primary-sand focus:border-transparent bg-white dark:bg-gray-800 text-gray-900 dark:text-white">
                            <option value="employee">موظف</option>
                            <option value="manager">مدير</option>
                            <option value="admin">مدير النظام</option>
                            <option value="client">عميل</option>
                        </select>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">القسم</label>
                            <select name="department" id="edit_department" class="w-full px-4 py-3 border border-gray-300 dark:border-gray-600 rounded-xl focus:ring-2 focus:ring-primary-sand focus:border-transparent bg-white dark:bg-gray-800 text-gray-900 dark:text-white">
                                <option value="">اختر القسم</option>
                                <?php foreach ($departments_options as $dept): ?>
                                    <option value="<?= $dept ?>"><?= $dept ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">التخصص</label>
                            <select name="specialization" id="edit_specialization" class="w-full px-4 py-3 border border-gray-300 dark:border-gray-600 rounded-xl focus:ring-2 focus:ring-primary-sand focus:border-transparent bg-white dark:bg-gray-800 text-gray-900 dark:text-white">
                                <option value="">اختر التخصص</option>
                                <?php foreach ($specializations_options as $spec): ?>
                                    <option value="<?= $spec ?>"><?= $spec ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">رقم الهاتف</label>
                        <input type="tel" name="phone" id="edit_phone" class="w-full px-4 py-3 border border-gray-300 dark:border-gray-600 rounded-xl focus:ring-2 focus:ring-primary-sand focus:border-transparent bg-white dark:bg-gray-800 text-gray-900 dark:text-white">
                    </div>

                    <div class="flex gap-3 pt-4">
                        <button type="submit" class="flex-1 px-6 py-3 bg-primary-sand text-white rounded-xl hover:bg-primary-dark transition-colors">تحديث البيانات</button>
                        <button type="button" onclick="closeEditUserModal()" class="px-6 py-3 bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded-xl hover:bg-gray-300 dark:hover:bg-gray-600 transition-colors">إلغاء</button>
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
            if (!e.target.closest('[onclick^="toggleUserMenu"]') && !e.target.closest('[id^="user-menu-"]')) {
                document.querySelectorAll('[id^="user-menu-"]').forEach(menu => menu.classList.add('hidden'));
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

        function openAddUserModal() { document.getElementById('addUserModal').classList.remove('hidden'); }
        function closeAddUserModal() { document.getElementById('addUserModal').classList.add('hidden'); }
        function openEditUserModal() { document.getElementById('editUserModal').classList.remove('hidden'); }
        function closeEditUserModal() { document.getElementById('editUserModal').classList.add('hidden'); }

        function toggleUserMenu(userId) {
            const menu = document.getElementById(`user-menu-${userId}`);
            document.querySelectorAll('[id^="user-menu-"]').forEach(m => {
                if (m.id !== `user-menu-${userId}`) m.classList.add('hidden');
            });
            menu.classList.toggle('hidden');
        }

        function editUser(userId) {
            document.getElementById(`user-menu-${userId}`).classList.add('hidden');
            const users = <?= json_encode($users) ?>;
            const user = users.find(u => u.id == userId);
            
            if (user) {
                document.getElementById('edit_user_id').value = user.id;
                document.getElementById('edit_fullname').value = user.fullname;
                document.getElementById('edit_email').value = user.email;
                document.getElementById('edit_role').value = user.role;
                document.getElementById('edit_department').value = user.department || '';
                document.getElementById('edit_specialization').value = user.specialization || '';
                document.getElementById('edit_phone').value = user.phone || '';
                openEditUserModal();
            }
        }

        function deleteUser(userId, userName) {
            document.getElementById(`user-menu-${userId}`).classList.add('hidden');
            if (confirm(`هل أنت متأكد من حذف المستخدم "${userName}"؟\n\nلا يمكن التراجع عن هذا الإجراء.`)) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.style.display = 'none';
                form.innerHTML = `
                    <input type="hidden" name="action" value="delete_user">
                    <input type="hidden" name="user_id" value="${userId}">
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
    </script>
</body>
</html>