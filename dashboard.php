<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
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

// جلب بيانات المستخدم من الجلسة والتأكد من صحتها
$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'] ?? $_SESSION['username'] ?? 'مستخدم';
$user_role = $_SESSION['user_role'] ?? $_SESSION['role'] ?? 'employee';

// جلب البيانات من قاعدة البيانات للتأكد من صحتها
try {
    $stmt_user = $conn->prepare("SELECT username, fullname, name, role FROM users WHERE id = :user_id");
    $stmt_user->bindParam(':user_id', $user_id);
    $stmt_user->execute();
    $user_data = $stmt_user->fetch(PDO::FETCH_ASSOC);
    
    if ($user_data) {
        // أولوية للـ fullname ثم name ثم username
        $user_name = $user_data['fullname'] ?? $user_data['name'] ?? $user_data['username'] ?? 'مستخدم';
        $user_role = $user_data['role'] ?? $user_role;
        
        // تحديث بيانات الجلسة للمرات القادمة
        $_SESSION['user_name'] = $user_name;
        $_SESSION['user_role'] = $user_role;
    }
} catch (PDOException $e) {
    error_log("خطأ في جلب بيانات المستخدم: " . $e->getMessage());
}

// تحديد أسماء الأدوار بالعربية
$role_names = [
    'admin' => 'مدير النظام',
    'manager' => 'مدير',
    'employee' => 'موظف',
    'client' => 'عميل'
];

$user_role_arabic = $role_names[$user_role] ?? $user_role;

// جلب الإحصائيات من قاعدة البيانات
try {
    // إجمالي المهام
    $stmt_total_tasks = $conn->prepare("SELECT COUNT(*) as total FROM tasks");
    $stmt_total_tasks->execute();
    $total_tasks = $stmt_total_tasks->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

    // المهام المكتملة
    $stmt_completed = $conn->prepare("SELECT COUNT(*) as completed FROM tasks WHERE status = 'completed'");
    $stmt_completed->execute();
    $completed_tasks = $stmt_completed->fetch(PDO::FETCH_ASSOC)['completed'] ?? 0;

    // المهام قيد التنفيذ
    $stmt_in_progress = $conn->prepare("SELECT COUNT(*) as in_progress FROM tasks WHERE status = 'in-progress'");
    $stmt_in_progress->execute();
    $in_progress_tasks = $stmt_in_progress->fetch(PDO::FETCH_ASSOC)['in_progress'] ?? 0;

    // المهام المعلقة
    $stmt_pending = $conn->prepare("SELECT COUNT(*) as pending FROM tasks WHERE status = 'pending'");
    $stmt_pending->execute();
    $pending_tasks = $stmt_pending->fetch(PDO::FETCH_ASSOC)['pending'] ?? 0;

    // إجمالي المستخدمين
    $stmt_users = $conn->prepare("SELECT COUNT(*) as total FROM users");
    $stmt_users->execute();
    $total_users = $stmt_users->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;

    // حساب معدل الإنجاز
    $completion_rate = $total_tasks > 0 ? round(($completed_tasks / $total_tasks) * 100) : 0;

} catch (PDOException $e) {
    // في حالة خطأ في قاعدة البيانات، استخدم قيم افتراضية
    $total_tasks = 0;
    $completed_tasks = 0;
    $in_progress_tasks = 0;
    $pending_tasks = 0;
    $total_users = 0;
    $completion_rate = 0;
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>الداشبورد - كوريان كاسيل</title>
    <meta name="description" content="لوحة تحكم نظام إدارة كوريان كاسيل">
    <meta name="robots" content="noindex, nofollow">
    
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    
    <!-- Lucide Icons -->
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;600;700;900&display=swap" rel="stylesheet">
    
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
                        },
                        background: {
                            dark: '#0F0F0F',
                            card: '#1A1A1A',
                            light: '#FFFFFF'
                        }
                    },
                    fontFamily: {
                        arabic: ['Cairo', 'sans-serif']
                    }
                }
            }
        }
    </script>
</head>
<body class="font-arabic bg-gray-50 dark:bg-gray-950 transition-colors duration-300">
    
    <!-- Navigation Sidebar -->
    <nav id="sidebar" class="fixed right-0 top-0 h-full w-80 bg-white dark:bg-gray-900 border-l border-gray-200 dark:border-gray-700 transform transition-transform duration-300 z-50 shadow-2xl translate-x-full">
        <div class="p-6">
            <!-- Header -->
            <div class="flex items-center justify-between mb-8">
                <div class="flex items-center gap-3">
                    <div class="w-12 h-12 bg-gradient-to-br from-yellow-400 to-yellow-600 rounded-2xl flex items-center justify-center text-white font-bold text-lg">
                        <!-- اللوجو SVG -->
                        <svg viewBox="0 0 200 200" style="width: 32px; height: 32px;">
                            <g fill="#fff">
                                <!-- الجدران الجانبية -->
                                <polygon points="20,120 20,180 60,180 60,140 20,140"/>
                                <polygon points="140,140 140,180 180,180 180,120 160,120 160,140"/>
                                
                                <!-- البرج الرئيسي -->
                                <rect x="60" y="80" width="80" height="100"/>
                                
                                <!-- البوابة -->
                                <ellipse cx="100" cy="150" rx="15" ry="25" fill="#f59e0b"/>
                                
                                <!-- النوافذ -->
                                <ellipse cx="80" cy="120" rx="8" ry="12" fill="#f59e0b"/>
                                <ellipse cx="120" cy="120" rx="8" ry="12" fill="#f59e0b"/>
                                <ellipse cx="40" cy="150" rx="6" ry="10" fill="#f59e0b"/>
                                <ellipse cx="160" cy="150" rx="6" ry="10" fill="#f59e0b"/>
                                
                                <!-- قمة البرج -->
                                <rect x="65" y="60" width="70" height="20"/>
                                <rect x="90" y="40" width="20" height="40"/>
                                
                                <!-- الراية -->
                                <rect x="108" y="30" width="2" height="20" fill="#333"/>
                                <polygon points="110,30 110,40 125,35" fill="#dc2626"/>
                            </g>
                        </svg>
                    </div>
                    <div>
                        <h2 class="font-bold text-gray-900 dark:text-white">كوريان كاسيل</h2>
                        <p class="text-sm text-gray-500 dark:text-gray-400">نظام الإدارة</p>
                    </div>
                </div>
                <button id="close-sidebar" class="p-2 hover:bg-gray-100 dark:hover:bg-gray-800 rounded-lg transition-colors">
                    <i data-lucide="x" class="w-5 h-5 text-gray-500"></i>
                </button>
            </div>

            <!-- User Info - مع رابط للملف الشخصي -->
            <a href="profile.php" class="block bg-gradient-to-r from-yellow-50 to-yellow-100 dark:from-yellow-900/20 dark:to-yellow-800/20 rounded-2xl p-4 mb-6 hover:shadow-md transition-all duration-300 group">
                <div class="flex items-center gap-3">
                    <div class="w-12 h-12 bg-yellow-500 rounded-xl flex items-center justify-center text-white font-bold text-lg group-hover:scale-105 transition-transform">
                        <?= mb_substr($user_name, 0, 1, 'UTF-8') ?>
                    </div>
                    <div class="flex-1">
                        <h3 class="font-semibold text-gray-900 dark:text-white group-hover:text-yellow-600 dark:group-hover:text-yellow-400 transition-colors"><?= htmlspecialchars($user_name) ?></h3>
                        <p class="text-sm text-gray-600 dark:text-gray-400"><?= htmlspecialchars($user_role_arabic) ?></p>
                    </div>
                    <i data-lucide="arrow-left" class="w-4 h-4 text-gray-400 group-hover:text-yellow-500 transition-colors"></i>
                </div>
            </a>

            <!-- Navigation Menu -->
            <nav class="space-y-2">
                <a href="#dashboard" class="nav-item active flex items-center gap-3 p-3 rounded-xl transition-all">
                    <i data-lucide="layout-dashboard" class="w-5 h-5"></i>
                    <span>الرئيسية</span>
                </a>
                <a href="tasks.php" class="nav-item flex items-center gap-3 p-3 rounded-xl transition-all">
                    <i data-lucide="check-square" class="w-5 h-5"></i>
                    <span>المهام</span>
                </a>
                <a href="orders.php" class="nav-item flex items-center gap-3 p-3 rounded-xl transition-all">
                    <i data-lucide="shopping-bag" class="w-5 h-5"></i>
                    <span>الطلبات</span>
                </a>
                <a href="files.php" class="nav-item flex items-center gap-3 p-3 rounded-xl transition-all">
                    <i data-lucide="folder" class="w-5 h-5"></i>
                    <span>الملفات</span>
                </a>
                <a href="users.php" class="nav-item flex items-center gap-3 p-3 rounded-xl transition-all">
                    <i data-lucide="users" class="w-5 h-5"></i>
                    <span>الفريق</span>
                </a>
                <a href="profile.php" class="nav-item flex items-center gap-3 p-3 rounded-xl transition-all">
                    <i data-lucide="user" class="w-5 h-5"></i>
                    <span>الملف الشخصي</span>
                </a>
                <a href="reports.php" class="nav-item flex items-center gap-3 p-3 rounded-xl transition-all">
                    <i data-lucide="bar-chart-3" class="w-5 h-5"></i>
                    <span>التقارير</span>
                </a>
                <a href="settings.php" class="nav-item flex items-center gap-3 p-3 rounded-xl transition-all">
                    <i data-lucide="settings" class="w-5 h-5"></i>
                    <span>الإعدادات</span>
                </a>
            </nav>

            <!-- Logout Button -->
            <div class="mt-8 pt-6 border-t border-gray-200 dark:border-gray-700">
                <button id="logout-btn" onclick="confirmLogout()" class="w-full flex items-center gap-3 p-3 text-red-600 hover:bg-red-50 dark:hover:bg-red-900/20 rounded-xl transition-all">
                    <i data-lucide="log-out" class="w-5 h-5"></i>
                    <span>تسجيل الخروج</span>
                </button>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <div id="main-content" class="mr-0 min-h-screen transition-all duration-300">
        <!-- Top Header -->
        <header class="bg-white dark:bg-gray-900 border-b border-gray-200 dark:border-gray-700 px-6 py-4 flex items-center justify-between">
            <div class="flex items-center gap-4">
                <button id="menu-toggle" class="p-2 hover:bg-gray-100 dark:hover:bg-gray-800 rounded-lg transition-colors">
                    <i data-lucide="menu" class="w-6 h-6 text-gray-600 dark:text-gray-400"></i>
                </button>
                <h1 id="page-title" class="text-xl font-bold text-gray-900 dark:text-white">الرئيسية</h1>
            </div>

            <div class="flex items-center gap-4">
                <!-- Theme Toggle -->
                <button id="theme-toggle" class="p-2 hover:bg-gray-100 dark:hover:bg-gray-800 rounded-lg transition-colors">
                    <i data-lucide="sun" class="w-5 h-5 text-gray-600 dark:text-gray-400"></i>
                </button>

                <!-- User Menu -->
                <div class="relative">
                    <button onclick="toggleUserMenu()" class="flex items-center gap-2 p-2 hover:bg-gray-100 dark:hover:bg-gray-800 rounded-lg transition-colors">
                        <div class="w-8 h-8 bg-yellow-500 rounded-lg flex items-center justify-center text-white font-bold text-sm">
                            <?= mb_substr($user_name, 0, 1, 'UTF-8') ?>
                        </div>
                        <i data-lucide="chevron-down" class="w-4 h-4 text-gray-500"></i>
                    </button>
                    
                    <div id="user-dropdown" class="hidden absolute left-0 mt-2 w-48 bg-white dark:bg-gray-800 rounded-xl shadow-lg border border-gray-200 dark:border-gray-700 z-10">
                        <a href="profile.php" class="flex items-center gap-3 px-4 py-3 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 rounded-t-xl transition-colors">
                            <i data-lucide="user" class="w-4 h-4"></i>
                            الملف الشخصي
                        </a>
                        <a href="settings.php" class="flex items-center gap-3 px-4 py-3 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors">
                            <i data-lucide="settings" class="w-4 h-4"></i>
                            الإعدادات
                        </a>
                        <hr class="border-gray-200 dark:border-gray-700">
                        <button onclick="confirmLogout()" class="w-full flex items-center gap-3 px-4 py-3 text-red-600 hover:bg-red-50 dark:hover:bg-red-900/20 rounded-b-xl transition-colors">
                            <i data-lucide="log-out" class="w-4 h-4"></i>
                            تسجيل الخروج
                        </button>
                    </div>
                </div>

                <!-- Notifications -->
                <button class="relative p-2 hover:bg-gray-100 dark:hover:bg-gray-800 rounded-lg transition-colors">
                    <i data-lucide="bell" class="w-5 h-5 text-gray-600 dark:text-gray-400"></i>
                    <span class="absolute -top-1 -right-1 w-3 h-3 bg-red-500 rounded-full"></span>
                </button>
            </div>
        </header>

        <!-- Page Content -->
        <main id="page-content" class="p-6">
            <!-- Dashboard Overview -->
            <div id="dashboard-content" class="space-y-6">
                <!-- Welcome Section -->
                <div class="bg-gradient-to-r from-yellow-500 to-yellow-600 rounded-3xl p-8 text-white">
                    <?php
                    // تحديد التحية حسب الوقت
                    $hour = date('H');
                    if ($hour < 12) {
                        $greeting = 'صباح الخير';
                    } elseif ($hour < 18) {
                        $greeting = 'مساء الخير';
                    } else {
                        $greeting = 'مساء الخير';
                    }
                    ?>
                    <h2 class="text-3xl font-bold mb-2"><?= $greeting ?> <?= htmlspecialchars($user_name) ?></h2>
                    <p class="text-yellow-100 text-lg">مرحباً بك في نظام إدارة كوريان كاسيل</p>
                </div>

                <!-- Stats Cards -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                    <!-- إجمالي المهام -->
                    <div class="bg-white dark:bg-gray-900 rounded-2xl p-6 border border-gray-200 dark:border-gray-700">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-gray-500 dark:text-gray-400 text-sm">إجمالي المهام</p>
                                <p class="text-2xl font-bold text-gray-900 dark:text-white"><?= $total_tasks ?></p>
                            </div>
                            <div class="w-12 h-12 bg-blue-100 dark:bg-blue-900/30 rounded-xl flex items-center justify-center">
                                <i data-lucide="check-square" class="w-6 h-6 text-blue-600"></i>
                            </div>
                        </div>
                    </div>

                    <!-- المهام المكتملة -->
                    <div class="bg-white dark:bg-gray-900 rounded-2xl p-6 border border-gray-200 dark:border-gray-700">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-gray-500 dark:text-gray-400 text-sm">المهام المكتملة</p>
                                <p class="text-2xl font-bold text-gray-900 dark:text-white"><?= $completed_tasks ?></p>
                            </div>
                            <div class="w-12 h-12 bg-green-100 dark:bg-green-900/30 rounded-xl flex items-center justify-center">
                                <i data-lucide="check-circle" class="w-6 h-6 text-green-600"></i>
                            </div>
                        </div>
                    </div>

                    <!-- المهام قيد التنفيذ -->
                    <div class="bg-white dark:bg-gray-900 rounded-2xl p-6 border border-gray-200 dark:border-gray-700">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-gray-500 dark:text-gray-400 text-sm">قيد التنفيذ</p>
                                <p class="text-2xl font-bold text-gray-900 dark:text-white"><?= $in_progress_tasks ?></p>
                            </div>
                            <div class="w-12 h-12 bg-orange-100 dark:bg-orange-900/30 rounded-xl flex items-center justify-center">
                                <i data-lucide="clock" class="w-6 h-6 text-orange-600"></i>
                            </div>
                        </div>
                    </div>

                    <!-- معدل الإنجاز -->
                    <div class="bg-white dark:bg-gray-900 rounded-2xl p-6 border border-gray-200 dark:border-gray-700">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-gray-500 dark:text-gray-400 text-sm">معدل الإنجاز</p>
                                <p class="text-2xl font-bold text-gray-900 dark:text-white"><?= $completion_rate ?>%</p>
                            </div>
                            <div class="w-12 h-12 bg-purple-100 dark:bg-purple-900/30 rounded-xl flex items-center justify-center">
                                <i data-lucide="trending-up" class="w-6 h-6 text-purple-600"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Recent Activities -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <!-- Recent Tasks -->
                    <div class="bg-white dark:bg-gray-900 rounded-2xl border border-gray-200 dark:border-gray-700">
                        <div class="p-6 border-b border-gray-200 dark:border-gray-700">
                            <h3 class="text-lg font-bold text-gray-900 dark:text-white">المهام الأخيرة</h3>
                        </div>
                        <div class="p-6 space-y-4">
                            <?php
                            try {
                                // جلب آخر 3 مهام
                                $stmt_recent = $conn->prepare("SELECT title, status, created_at FROM tasks ORDER BY created_at DESC LIMIT 3");
                                $stmt_recent->execute();
                                $recent_tasks = $stmt_recent->fetchAll(PDO::FETCH_ASSOC);

                                if (!empty($recent_tasks)) {
                                    foreach ($recent_tasks as $task) {
                                        $status_colors = [
                                            'pending' => 'yellow',
                                            'in-progress' => 'blue',
                                            'completed' => 'green',
                                            'cancelled' => 'red'
                                        ];
                                        
                                        $status_names = [
                                            'pending' => 'في الانتظار',
                                            'in-progress' => 'قيد التنفيذ',
                                            'completed' => 'مكتملة',
                                            'cancelled' => 'ملغية'
                                        ];

                                        $color = $status_colors[$task['status']] ?? 'gray';
                                        $status_name = $status_names[$task['status']] ?? $task['status'];
                                        $time_ago = date('d/m/Y', strtotime($task['created_at']));
                                        ?>
                                        <div class="flex items-center gap-3 p-3 hover:bg-gray-50 dark:hover:bg-gray-800 rounded-xl transition-colors cursor-pointer">
                                            <div class="w-2 h-2 bg-<?= $color ?>-500 rounded-full"></div>
                                            <div class="flex-1">
                                                <p class="font-medium text-gray-900 dark:text-white"><?= htmlspecialchars($task['title']) ?></p>
                                                <p class="text-sm text-gray-500 dark:text-gray-400"><?= $status_name ?></p>
                                            </div>
                                            <span class="text-xs text-gray-400"><?= $time_ago ?></span>
                                        </div>
                                        <?php
                                    }
                                } else {
                                    ?>
                                    <div class="text-center py-8">
                                        <i data-lucide="clipboard" class="w-12 h-12 text-gray-400 mx-auto mb-3"></i>
                                        <p class="text-gray-500 dark:text-gray-400">لا توجد مهام حتى الآن</p>
                                    </div>
                                    <?php
                                }
                            } catch (PDOException $e) {
                                ?>
                                <div class="text-center py-8">
                                    <p class="text-red-500">خطأ في تحميل المهام</p>
                                </div>
                                <?php
                            }
                            ?>
                        </div>
                    </div>

                    <!-- User Stats -->
                    <div class="bg-white dark:bg-gray-900 rounded-2xl border border-gray-200 dark:border-gray-700">
                        <div class="p-6 border-b border-gray-200 dark:border-gray-700">
                            <h3 class="text-lg font-bold text-gray-900 dark:text-white">إحصائيات الفريق</h3>
                        </div>
                        <div class="p-6 space-y-4">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 bg-blue-500 rounded-xl flex items-center justify-center text-white font-bold">
                                    <i data-lucide="users" class="w-5 h-5"></i>
                                </div>
                                <div class="flex-1">
                                    <p class="font-medium text-gray-900 dark:text-white">إجمالي الأعضاء</p>
                                    <p class="text-sm text-gray-500 dark:text-gray-400"><?= $total_users ?> عضو</p>
                                </div>
                            </div>
                            
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 bg-green-500 rounded-xl flex items-center justify-center text-white font-bold">
                                    <i data-lucide="check-circle" class="w-5 h-5"></i>
                                </div>
                                <div class="flex-1">
                                    <p class="font-medium text-gray-900 dark:text-white">المهام المنجزة</p>
                                    <p class="text-sm text-gray-500 dark:text-gray-400"><?= $completed_tasks ?> مهمة</p>
                                </div>
                            </div>
                            
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 bg-yellow-500 rounded-xl flex items-center justify-center text-white font-bold">
                                    <i data-lucide="clock" class="w-5 h-5"></i>
                                </div>
                                <div class="flex-1">
                                    <p class="font-medium text-gray-900 dark:text-white">المهام المعلقة</p>
                                    <p class="text-sm text-gray-500 dark:text-gray-400"><?= $pending_tasks ?> مهمة</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="bg-white dark:bg-gray-900 rounded-2xl border border-gray-200 dark:border-gray-700 p-6">
                    <h3 class="text-lg font-bold text-gray-900 dark:text-white mb-4">إجراءات سريعة</h3>
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                        <a href="tasks.php" class="flex flex-col items-center p-4 hover:bg-gray-50 dark:hover:bg-gray-800 rounded-xl transition-colors">
                            <div class="w-12 h-12 bg-blue-100 dark:bg-blue-900/30 rounded-xl flex items-center justify-center mb-3">
                                <i data-lucide="plus" class="w-6 h-6 text-blue-600"></i>
                           </div>
                           <span class="text-sm font-medium text-gray-900 dark:text-white">إضافة مهمة</span>
                       </a>
                       
                       <a href="users.php" class="flex flex-col items-center p-4 hover:bg-gray-50 dark:hover:bg-gray-800 rounded-xl transition-colors">
                           <div class="w-12 h-12 bg-green-100 dark:bg-green-900/30 rounded-xl flex items-center justify-center mb-3">
                               <i data-lucide="user-plus" class="w-6 h-6 text-green-600"></i>
                           </div>
                           <span class="text-sm font-medium text-gray-900 dark:text-white">إضافة عضو</span>
                       </a>
                       
                       <a href="profile.php" class="flex flex-col items-center p-4 hover:bg-gray-50 dark:hover:bg-gray-800 rounded-xl transition-colors">
                           <div class="w-12 h-12 bg-purple-100 dark:bg-purple-900/30 rounded-xl flex items-center justify-center mb-3">
                               <i data-lucide="user" class="w-6 h-6 text-purple-600"></i>
                           </div>
                           <span class="text-sm font-medium text-gray-900 dark:text-white">الملف الشخصي</span>
                       </a>
                       
                       <a href="settings.php" class="flex flex-col items-center p-4 hover:bg-gray-50 dark:hover:bg-gray-800 rounded-xl transition-colors">
                           <div class="w-12 h-12 bg-yellow-100 dark:bg-yellow-900/30 rounded-xl flex items-center justify-center mb-3">
                               <i data-lucide="settings" class="w-6 h-6 text-yellow-600"></i>
                           </div>
                           <span class="text-sm font-medium text-gray-900 dark:text-white">الإعدادات</span>
                       </a>
                   </div>
               </div>
           </div>
       </main>
   </div>

   <!-- Scripts -->
   <script>
       // تهيئة الأيقونات
       document.addEventListener('DOMContentLoaded', function() {
           lucide.createIcons();
       });

       // متغيرات الحالة
       let sidebarOpen = false;

       // عناصر DOM
       const menuToggle = document.getElementById('menu-toggle');
       const sidebar = document.getElementById('sidebar');
       const closeSidebar = document.getElementById('close-sidebar');
       const mainContent = document.getElementById('main-content');
       const themeToggle = document.getElementById('theme-toggle');

       // تفعيل القائمة الجانبية
       menuToggle.addEventListener('click', () => {
           toggleSidebar();
       });

       closeSidebar.addEventListener('click', () => {
           closeSidebarFunc();
       });

       // تبديل القائمة الجانبية
       function toggleSidebar() {
           sidebarOpen = !sidebarOpen;
           
           if (sidebarOpen) {
               sidebar.classList.remove('translate-x-full');
               if (window.innerWidth >= 768) {
                   mainContent.classList.add('mr-80');
               }
           } else {
               sidebar.classList.add('translate-x-full');
               mainContent.classList.remove('mr-80');
           }
       }

       function closeSidebarFunc() {
           sidebar.classList.add('translate-x-full');
           mainContent.classList.remove('mr-80');
           sidebarOpen = false;
       }

       // إغلاق القائمة عند النقر خارجها
       document.addEventListener('click', (e) => {
           if (sidebarOpen && !sidebar.contains(e.target) && !menuToggle.contains(e.target)) {
               closeSidebarFunc();
           }
       });

       // تهيئة الثيم
       function initTheme() {
           const savedTheme = localStorage.getItem('theme');
           const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
           
           if (savedTheme === 'dark' || (!savedTheme && prefersDark)) {
               document.documentElement.classList.add('dark');
               updateThemeIcon(true);
           } else {
               updateThemeIcon(false);
           }
       }

       // تبديل الثيم
       themeToggle.addEventListener('click', () => {
           const isDark = document.documentElement.classList.contains('dark');
           
           if (isDark) {
               document.documentElement.classList.remove('dark');
               localStorage.setItem('theme', 'light');
               updateThemeIcon(false);
           } else {
               document.documentElement.classList.add('dark');
               localStorage.setItem('theme', 'dark');
               updateThemeIcon(true);
           }
       });

       // تحديث أيقونة الثيم
       function updateThemeIcon(isDark) {
           const icon = themeToggle.querySelector('i');
           icon.setAttribute('data-lucide', isDark ? 'sun' : 'moon');
           lucide.createIcons();
       }

       // قائمة المستخدم المنسدلة
       function toggleUserMenu() {
           const dropdown = document.getElementById('user-dropdown');
           dropdown.classList.toggle('hidden');
       }

       // إغلاق قائمة المستخدم عند النقر خارجها
       document.addEventListener('click', (e) => {
           const userDropdown = document.getElementById('user-dropdown');
           const userButton = e.target.closest('[onclick="toggleUserMenu()"]');
           
           if (!userButton && userDropdown && !userDropdown.contains(e.target)) {
               userDropdown.classList.add('hidden');
           }
       });

       // تأكيد تسجيل الخروج
       function confirmLogout() {
           if (confirm('هل أنت متأكد من تسجيل الخروج؟')) {
               // إظهار رسالة تحميل
               const loadingDiv = document.createElement('div');
               loadingDiv.innerHTML = `
                   <div class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
                       <div class="bg-white dark:bg-gray-800 p-6 rounded-xl">
                           <div class="flex items-center gap-3">
                               <div class="animate-spin rounded-full h-5 w-5 border-b-2 border-yellow-600"></div>
                               <span class="text-gray-900 dark:text-white">جاري تسجيل الخروج...</span>
                           </div>
                       </div>
                   </div>
               `;
               document.body.appendChild(loadingDiv);
               
               // إنتظار قليل ثم التوجيه
               setTimeout(() => {
                   window.location.href = 'logout.php';
               }, 500);
           }
       }

       // تهيئة التطبيق
       initTheme();
   </script>

   <style>
       /* Navigation Styles */
       .nav-item {
           @apply text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white hover:bg-gray-100 dark:hover:bg-gray-800;
       }
       
       .nav-item.active {
           @apply text-yellow-600 bg-yellow-50 dark:bg-yellow-900/20 dark:text-yellow-400;
       }

       /* Responsive adjustments */
       @media (max-width: 768px) {
           #main-content.mr-80 {
               margin-right: 0;
           }
       }

       /* Smooth transitions */
       * {
           transition: all 0.3s ease;
       }

       /* Custom scrollbar */
       ::-webkit-scrollbar {
           width: 8px;
       }

       ::-webkit-scrollbar-track {
           @apply bg-gray-100 dark:bg-gray-800;
       }

       ::-webkit-scrollbar-thumb {
           @apply bg-gray-300 dark:bg-gray-600 rounded-full;
       }

       ::-webkit-scrollbar-thumb:hover {
           @apply bg-gray-400 dark:bg-gray-500;
       }
   </style>
</body>
</html>