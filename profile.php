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

// جلب بيانات المستخدم الحالي
$current_user_id = $_SESSION['user_id'];
$current_user_role = $_SESSION['user_role'] ?? 'employee';

// معالجة العمليات POST
$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    try {
        if ($action === 'update_profile') {
            $fullname = trim($_POST['fullname'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $phone = trim($_POST['phone'] ?? '');
            $department = trim($_POST['department'] ?? '');
            $specialization = trim($_POST['specialization'] ?? '');
            $bio = trim($_POST['bio'] ?? '');
            
            if (empty($fullname) || empty($email)) {
                throw new Exception('الاسم والبريد الإلكتروني مطلوبان');
            }
            
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new Exception('صيغة البريد الإلكتروني غير صحيحة');
            }
            
            // التحقق من عدم وجود البريد مع مستخدم آخر
            $check_stmt = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
            $check_stmt->execute([$email, $current_user_id]);
            if ($check_stmt->fetch()) {
                throw new Exception('البريد الإلكتروني موجود مع مستخدم آخر');
            }
            
            $stmt = $conn->prepare("UPDATE users SET fullname = ?, email = ?, phone = ?, department = ?, specialization = ?, bio = ?, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$fullname, $email, $phone, $department, $specialization, $bio, $current_user_id]);
            
            // تحديث الجلسة
            $_SESSION['user_name'] = $fullname;
            $_SESSION['user_email'] = $email;
            
            header('Location: profile.php?success=profile_updated');
            exit();
        }
        
        if ($action === 'change_password') {
            $current_password = $_POST['current_password'] ?? '';
            $new_password = $_POST['new_password'] ?? '';
            $confirm_password = $_POST['confirm_password'] ?? '';
            
            if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
                throw new Exception('جميع حقول كلمة المرور مطلوبة');
            }
            
            if ($new_password !== $confirm_password) {
                throw new Exception('كلمات المرور الجديدة غير متطابقة');
            }
            
            if (strlen($new_password) < 6) {
                throw new Exception('كلمة المرور يجب أن تكون 6 أحرف على الأقل');
            }
            
            // التحقق من كلمة المرور الحالية
            $stmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
            $stmt->execute([$current_user_id]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$user || !password_verify($current_password, $user['password'])) {
                throw new Exception('كلمة المرور الحالية غير صحيحة');
            }
            
            // تحديث كلمة المرور
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE users SET password = ?, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$hashed_password, $current_user_id]);
            
            header('Location: profile.php?success=password_changed');
            exit();
        }
        
        if ($action === 'upload_avatar') {
            if (!isset($_FILES['avatar']) || $_FILES['avatar']['error'] !== UPLOAD_ERR_OK) {
                throw new Exception('يرجى اختيار صورة صحيحة');
            }
            
            $file = $_FILES['avatar'];
            $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
            $max_size = 5 * 1024 * 1024; // 5MB
            
            if (!in_array($file['type'], $allowed_types)) {
                throw new Exception('نوع الملف غير مدعوم. يرجى استخدام JPG أو PNG أو GIF');
            }
            
            if ($file['size'] > $max_size) {
                throw new Exception('حجم الملف كبير جداً. الحد الأقصى 5 ميجابايت');
            }
            
            // إنشاء مجلد التحميل إذا لم يكن موجوداً
            $upload_dir = 'uploads/avatars/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            
            // إنشاء اسم ملف فريد
            $file_extension = pathinfo($file['name'], PATHINFO_EXTENSION);
            $filename = 'avatar_' . $current_user_id . '_' . time() . '.' . $file_extension;
            $filepath = $upload_dir . $filename;
            
            if (move_uploaded_file($file['tmp_name'], $filepath)) {
                // حذف الصورة القديمة إن وجدت
                $stmt = $conn->prepare("SELECT avatar FROM users WHERE id = ?");
                $stmt->execute([$current_user_id]);
                $old_avatar = $stmt->fetchColumn();
                
                if ($old_avatar && file_exists($old_avatar)) {
                    unlink($old_avatar);
                }
                
                // تحديث قاعدة البيانات
                $stmt = $conn->prepare("UPDATE users SET avatar = ?, updated_at = NOW() WHERE id = ?");
                $stmt->execute([$filepath, $current_user_id]);
                
                header('Location: profile.php?success=avatar_uploaded');
                exit();
            } else {
                throw new Exception('فشل في تحميل الصورة');
            }
        }
        
    } catch (Exception $e) {
        $error_message = $e->getMessage();
    } catch (PDOException $e) {
        $error_message = 'حدث خطأ في قاعدة البيانات';
        error_log("Database Error: " . $e->getMessage());
    }
}

// رسائل النجاح
if (isset($_GET['success'])) {
    switch ($_GET['success']) {
        case 'profile_updated':
            $success_message = 'تم تحديث الملف الشخصي بنجاح!';
            break;
        case 'password_changed':
            $success_message = 'تم تغيير كلمة المرور بنجاح!';
            break;
        case 'avatar_uploaded':
            $success_message = 'تم تحديث الصورة الشخصية بنجاح!';
            break;
    }
}

// تهيئة المتغيرات بقيم افتراضية
$user = null;
$recent_activities = [];
$team_members = [];
$notifications = [];

try {
    // جلب بيانات المستخدم الحالي مع مقاومة الأخطاء
    $stmt = $conn->prepare("SELECT * FROM users WHERE id = ? LIMIT 1");
    $stmt->execute([$current_user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        session_destroy();
        header("Location: login.php?error=user_not_found");
        exit;
    }
    
    // التحقق من وجود جدول tasks قبل الاستعلام
    $tables_check = $conn->query("SHOW TABLES LIKE 'tasks'");
    $tasks_table_exists = $tables_check->rowCount() > 0;
    
    if ($tasks_table_exists) {
        // جلب إحصائيات المهام
        $stats_stmt = $conn->prepare("
            SELECT 
                COUNT(*) as total_tasks,
                COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed_tasks,
                COUNT(CASE WHEN status = 'in_progress' THEN 1 END) as in_progress_tasks,
                AVG(CASE WHEN status = 'completed' AND completed_date IS NOT NULL AND created_at IS NOT NULL 
                         THEN DATEDIFF(completed_date, created_at) END) as avg_completion_days
            FROM tasks 
            WHERE assigned_to = ?
        ");
        $stats_stmt->execute([$current_user_id]);
        $task_stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);
        
        // دمج إحصائيات المهام مع بيانات المستخدم
        $user = array_merge($user, $task_stats);
        
        // جلب النشاط الأخير
        $activity_stmt = $conn->prepare("
            SELECT 
                'task_completed' as type, 
                title as description, 
                completed_date as activity_date,
                'تم إكمال مهمة' as action_text
            FROM tasks 
            WHERE assigned_to = ? AND status = 'completed' AND completed_date IS NOT NULL
            
            UNION ALL
            
            SELECT 
                'task_created' as type, 
                title as description, 
                created_at as activity_date,
                'تم إنشاء مهمة' as action_text
            FROM tasks 
            WHERE created_by = ? AND created_at IS NOT NULL
            
            ORDER BY activity_date DESC 
            LIMIT 10
        ");
        $activity_stmt->execute([$current_user_id, $current_user_id]);
        $recent_activities = $activity_stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        // إذا لم يوجد جدول المهام، استخدم قيم افتراضية
        $user['total_tasks'] = 0;
        $user['completed_tasks'] = 0;
        $user['in_progress_tasks'] = 0;
        $user['avg_completion_days'] = 0;
    }
    
    // جلب أعضاء الفريق (إذا كان هناك قسم)
    if (!empty($user['department'])) {
        $team_stmt = $conn->prepare("
            SELECT id, fullname, role, avatar,
                   CASE WHEN updated_at > DATE_SUB(NOW(), INTERVAL 5 MINUTE) THEN 1 ELSE 0 END as is_online
            FROM users 
            WHERE department = ? AND id != ? 
            ORDER BY fullname ASC 
            LIMIT 10
        ");
        $team_stmt->execute([$user['department'], $current_user_id]);
        $team_members = $team_stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // التحقق من وجود جدول الإشعارات
    $notifications_check = $conn->query("SHOW TABLES LIKE 'notifications'");
    $notifications_table_exists = $notifications_check->rowCount() > 0;
    
    if ($notifications_table_exists) {
        $notifications_stmt = $conn->prepare("
            SELECT id, title, message, type, is_read, created_at
            FROM notifications 
            WHERE user_id = ? 
            ORDER BY created_at DESC 
            LIMIT 5
        ");
        $notifications_stmt->execute([$current_user_id]);
        $notifications = $notifications_stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
} catch (PDOException $e) {
    $error_message = 'خطأ في جلب البيانات: ' . $e->getMessage();
    error_log("Database Error in profile.php: " . $e->getMessage());
    
    // في حالة الخطأ، تأكد من وجود بيانات المستخدم الأساسية
    if (!$user) {
        $user = [
            'id' => $current_user_id,
            'fullname' => $_SESSION['user_name'] ?? 'مستخدم',
            'email' => $_SESSION['user_email'] ?? '',
            'role' => $current_user_role,
            'phone' => '',
            'department' => '',
            'specialization' => '',
            'bio' => '',
            'avatar' => '',
            'created_at' => date('Y-m-d'),
            'total_tasks' => 0,
            'completed_tasks' => 0,
            'in_progress_tasks' => 0,
            'avg_completion_days' => 0
        ];
    }
}

// دوال مساعدة
function formatDate($date) {
    if (!$date) return 'غير محدد';
    return date('d/m/Y', strtotime($date));
}

function timeAgo($date) {
    if (!$date) return 'غير محدد';
    
    $time = time() - strtotime($date);
    
    if ($time < 60) return 'منذ لحظات';
    if ($time < 3600) return 'منذ ' . floor($time/60) . ' دقيقة';
    if ($time < 86400) return 'منذ ' . floor($time/3600) . ' ساعة';
    if ($time < 2592000) return 'منذ ' . floor($time/86400) . ' يوم';
    
    return formatDate($date);
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

function getActivityIcon($type) {
    $icons = [
        'task_completed' => 'check-circle',
        'task_created' => 'plus-circle',
        'comment_added' => 'message-circle',
        'file_uploaded' => 'upload'
    ];
    return $icons[$type] ?? 'activity';
}

function getActivityColor($type) {
    $colors = [
        'task_completed' => 'green',
        'task_created' => 'blue',
        'comment_added' => 'purple',
        'file_uploaded' => 'yellow'
    ];
    return $colors[$type] ?? 'gray';
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

// جلب الأهداف الشخصية
$goals = [
    [
        'title' => 'إكمال 30 مهمة هذا الشهر',
        'current' => $user['total_tasks'] ?? 0,
        'target' => 30,
        'color' => 'green'
    ],
    [
        'title' => 'تحسين معدل الإنجاز',
        'current' => ($user['total_tasks'] ?? 0) > 0 ? round((($user['completed_tasks'] ?? 0) / $user['total_tasks']) * 100) : 0,
        'target' => 85,
        'color' => 'blue'
    ],
    [
        'title' => 'تقليل وقت الإنجاز',
        'current' => ($user['avg_completion_days'] ?? 0) ? round($user['avg_completion_days']) : 0,
        'target' => 5,
        'color' => 'purple',
        'reverse' => true
    ]
];
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>الملف الشخصي - كوريان كاسيل</title>
    <meta name="description" content="الملف الشخصي في نظام كوريان كاسيل">
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
        
        .profile-card {
            transition: all 0.3s ease;
        }
        .profile-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }
        
        .achievement-badge {
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.05); }
        }
        
        .activity-item {
            transition: all 0.2s ease;
        }
        .activity-item:hover {
            background-color: rgba(166, 143, 81, 0.05);
        }
        
        .stats-card {
            background: linear-gradient(135deg, rgba(166, 143, 81, 0.1) 0%, rgba(250, 220, 155, 0.05) 100%);
        }
        
        .fade-in {
            animation: fadeIn 0.5s ease-in;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* Navigation Styles */
        .nav-item {
            @apply text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white hover:bg-gray-100 dark:hover:bg-gray-800;
        }
        
        .nav-item.active {
            @apply text-primary-sand bg-primary-sand/10;
        }

        /* Responsive adjustments */
        @media (max-width: 768px) {
            #main-content.mr-80 {
                margin-right: 0;
            }
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
</head>
<body class="font-arabic bg-gray-50 dark:bg-gray-950 transition-colors duration-300">

    <!-- Navigation Sidebar -->
    <nav id="sidebar" class="fixed right-0 top-0 h-full w-80 bg-white dark:bg-gray-900 border-l border-gray-200 dark:border-gray-700 transform transition-transform duration-300 z-50 shadow-2xl translate-x-full">
        <div class="p-6">
            <!-- Header -->
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
                        <p class="text-sm text-gray-500 dark:text-gray-400">الملف الشخصي</p>
                    </div>
                </div>
                <button id="close-sidebar" class="p-2 hover:bg-gray-100 dark:hover:bg-gray-800 rounded-lg transition-colors">
                    <i data-lucide="x" class="w-5 h-5 text-gray-500"></i>
                </button>
            </div>

            <!-- User Info -->
            <div class="bg-gradient-to-r from-primary-light/20 to-primary-sand/20 rounded-2xl p-4 mb-6">
                <div class="flex items-center gap-3">
                    <div class="w-12 h-12 bg-primary-sand rounded-xl flex items-center justify-center text-white font-bold text-lg">
                        <?= mb_substr($user['fullname'], 0, 1, 'UTF-8') ?>
                    </div>
                    <div>
                        <h3 class="font-semibold text-gray-900 dark:text-white"><?= htmlspecialchars($user['fullname']) ?></h3>
                        <p class="text-sm text-gray-600 dark:text-gray-400"><?= getRoleDisplayName($user['role']) ?></p>
                    </div>
                </div>
            </div>

            <!-- Navigation Menu -->
            <nav class="space-y-2">
                <a href="dashboard.php" class="nav-item flex items-center gap-3 p-3 rounded-xl transition-all">
                    <i data-lucide="layout-dashboard" class="w-5 h-5"></i>
                    <span>الرئيسية</span>
                </a>
                <a href="tasks.php" class="nav-item flex items-center gap-3 p-3 rounded-xl transition-all">
                    <i data-lucide="check-square" class="w-5 h-5"></i>
                    <span>المهام</span>
                </a>
                <a href="users.php" class="nav-item flex items-center gap-3 p-3 rounded-xl transition-all">
                    <i data-lucide="users" class="w-5 h-5"></i>
                    <span>المستخدمين</span>
                </a>
                <a href="#" class="nav-item active flex items-center gap-3 p-3 rounded-xl transition-all bg-primary-sand/10 text-primary-sand">
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
                <button onclick="confirmLogout()" class="w-full flex items-center gap-3 p-3 text-red-600 hover:bg-red-50 dark:hover:bg-red-900/20 rounded-xl transition-all">
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
                <h1 class="text-xl font-bold text-gray-900 dark:text-white">الملف الشخصي</h1>
            </div>

            <div class="flex items-center gap-4">
                <!-- Theme Toggle -->
                <button id="theme-toggle" class="p-2 hover:bg-gray-100 dark:hover:bg-gray-800 rounded-lg transition-colors">
                    <i data-lucide="sun" class="w-5 h-5 text-gray-600 dark:text-gray-400"></i>
                </button>
            </div>
        </header>

        <!-- Page Content -->
        <main class="p-6">
            <!-- Success/Error Messages -->
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

            <!-- Profile Header -->
            <div class="bg-gradient-to-r from-primary-sand to-primary-light rounded-3xl p-8 mb-8 text-white fade-in">
                <div class="flex flex-col md:flex-row items-center gap-6">
                    <div class="relative">
                        <div class="w-32 h-32 bg-white bg-opacity-20 rounded-3xl flex items-center justify-center text-6xl font-bold backdrop-blur-sm">
                            <?php if (!empty($user['avatar']) && file_exists($user['avatar'])): ?>
                                <img src="<?= htmlspecialchars($user['avatar']) ?>" alt="صورة شخصية" class="w-full h-full object-cover rounded-3xl">
                            <?php else: ?>
                                <?= mb_substr($user['fullname'], 0, 1, 'UTF-8') ?>
                            <?php endif; ?>
                        </div>
                        <button onclick="openAvatarModal()" class="absolute -bottom-2 -right-2 w-10 h-10 bg-white text-primary-sand rounded-full flex items-center justify-center shadow-lg hover:shadow-xl transition-all transform hover:scale-105">
                            <i data-lucide="camera" class="w-5 h-5"></i>
                        </button>
                    </div>
                    
                    <div class="text-center md:text-right flex-1">
                        <h2 class="text-3xl font-bold mb-2"><?= htmlspecialchars($user['fullname']) ?></h2>
                        <p class="text-xl opacity-90 mb-3"><?= getRoleDisplayName($user['role']) ?></p>
                        <?php if (!empty($user['department'])): ?>
                            <p class="opacity-75 mb-4"><?= htmlspecialchars($user['department']) ?></p>
                        <?php endif; ?>
                        
                        <div class="flex flex-wrap justify-center md:justify-start gap-4">
                            <div class="flex items-center gap-2">
                                <i data-lucide="mail" class="w-4 h-4"></i>
                                <span><?= htmlspecialchars($user['email']) ?></span>
                            </div>
                            <?php if (!empty($user['phone'])): ?>
                                <div class="flex items-center gap-2">
                                    <i data-lucide="phone" class="w-4 h-4"></i>
                                    <span><?= htmlspecialchars($user['phone']) ?></span>
                                </div>
                            <?php endif; ?>
                            <div class="flex items-center gap-2">
                                <i data-lucide="calendar" class="w-4 h-4"></i>
                                <span>انضم في <?= formatDate($user['created_at']) ?></span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="flex flex-col gap-3 mt-4">
                        <button onclick="openEditProfileModal()" class="px-6 py-3 bg-white text-primary-sand rounded-2xl hover:bg-gray-50 transition-colors font-medium">
                            تعديل الملف الشخصي
                        </button>
                        <button onclick="openChangePasswordModal()" class="px-6 py-3 bg-white bg-opacity-20 text-white rounded-2xl hover:bg-opacity-30 transition-colors font-medium">
                            تغيير كلمة المرور
                        </button>
                    </div>
                </div>
            </div>

            <!-- Profile Content -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                
                <!-- Left Column -->
                <div class="lg:col-span-2 space-y-8">
                    
                    <!-- Performance Stats -->
                    <div class="profile-card bg-white dark:bg-gray-900 rounded-3xl p-8 border border-gray-200 dark:border-gray-700 fade-in">
                        <h3 class="text-2xl font-bold text-gray-900 dark:text-white mb-6">إحصائيات الأداء</h3>
                        
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                            <div class="stats-card rounded-2xl p-6 text-center">
                                <div class="w-16 h-16 bg-gradient-to-br from-primary-sand to-primary-light rounded-2xl flex items-center justify-center mx-auto mb-4">
                                    <i data-lucide="check-circle" class="w-8 h-8 text-white"></i>
                                </div>
                                <p class="text-3xl font-bold text-gray-900 dark:text-white"><?= $user['completed_tasks'] ?? 0 ?></p>
                                <p class="text-gray-600 dark:text-gray-400">مهام مكتملة</p>
                            </div>
                            
                            <div class="stats-card rounded-2xl p-6 text-center">
                                <div class="w-16 h-16 bg-gradient-to-br from-blue-500 to-blue-600 rounded-2xl flex items-center justify-center mx-auto mb-4">
                                    <i data-lucide="clock" class="w-8 h-8 text-white"></i>
                                </div>
                                <p class="text-3xl font-bold text-gray-900 dark:text-white"><?= $user['in_progress_tasks'] ?? 0 ?></p>
                                <p class="text-gray-600 dark:text-gray-400">مهام قيد التنفيذ</p>
                            </div>
                            
                            <div class="stats-card rounded-2xl p-6 text-center">
                                <div class="w-16 h-16 bg-gradient-to-br from-green-500 to-green-600 rounded-2xl flex items-center justify-center mx-auto mb-4">
                                    <i data-lucide="target" class="w-8 h-8 text-white"></i>
                                </div>
                                <p class="text-3xl font-bold text-gray-900 dark:text-white">
                                    <?= ($user['total_tasks'] ?? 0) > 0 ? round((($user['completed_tasks'] ?? 0) / $user['total_tasks']) * 100) : 0 ?>%
                                </p>
                                <p class="text-gray-600 dark:text-gray-400">معدل الإنجاز</p>
                            </div>
                        </div>
                        
                        <!-- Progress Chart -->
                        <div class="bg-gray-50 dark:bg-gray-800 rounded-2xl p-6">
                            <h4 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">التقدم الشهري</h4>
                            <div class="flex items-center justify-between mb-4">
                                <span class="text-gray-600 dark:text-gray-400">معدل إكمال المهام هذا الشهر</span>
                                <span class="text-2xl font-bold text-primary-sand">
                                    <?= ($user['total_tasks'] ?? 0) > 0 ? round((($user['completed_tasks'] ?? 0) / $user['total_tasks']) * 100) : 0 ?>%
                                </span>
                            </div>
                            <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-3">
                                <div class="bg-gradient-to-r from-primary-sand to-primary-light h-3 rounded-full" 
                                     style="width: <?= ($user['total_tasks'] ?? 0) > 0 ? round((($user['completed_tasks'] ?? 0) / $user['total_tasks']) * 100) : 0 ?>%"></div>
                            </div>
                        </div>
                    </div>

                    <!-- Recent Activity -->
                    <div class="profile-card bg-white dark:bg-gray-900 rounded-3xl p-8 border border-gray-200 dark:border-gray-700 fade-in">
                        <div class="flex items-center justify-between mb-6">
                            <h3 class="text-2xl font-bold text-gray-900 dark:text-white">النشاط الأخير</h3>
                            <a href="tasks.php" class="text-primary-sand hover:text-primary-dark text-sm font-medium">عرض الكل</a>
                        </div>
                        
                        <div class="space-y-4">
                            <?php if (!empty($recent_activities)): ?>
                                <?php foreach ($recent_activities as $activity): ?>
                                    <div class="activity-item flex items-center gap-4 p-4 rounded-2xl">
                                        <div class="w-10 h-10 bg-<?= getActivityColor($activity['type']) ?>-100 dark:bg-<?= getActivityColor($activity['type']) ?>-900/20 rounded-xl flex items-center justify-center">
                                            <i data-lucide="<?= getActivityIcon($activity['type']) ?>" class="w-5 h-5 text-<?= getActivityColor($activity['type']) ?>-600"></i>
                                        </div>
                                        <div class="flex-1">
                                            <p class="font-medium text-gray-900 dark:text-white"><?= $activity['action_text'] ?> "<?= htmlspecialchars($activity['description']) ?>"</p>
                                            <p class="text-sm text-gray-500 dark:text-gray-400"><?= timeAgo($activity['activity_date']) ?></p>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="text-center py-8">
                                    <div class="w-16 h-16 bg-gray-100 dark:bg-gray-800 rounded-full flex items-center justify-center mx-auto mb-4">
                                        <i data-lucide="activity" class="w-8 h-8 text-gray-400"></i>
                                    </div>
                                    <p class="text-gray-500 dark:text-gray-400">لا يوجد نشاط حتى الآن</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Skills & Bio -->
                    <div class="profile-card bg-white dark:bg-gray-900 rounded-3xl p-8 border border-gray-200 dark:border-gray-700 fade-in">
                        <h3 class="text-2xl font-bold text-gray-900 dark:text-white mb-6">معلومات إضافية</h3>
                        
                        <?php if (!empty($user['specialization'])): ?>
                            <div class="mb-6">
                                <h4 class="text-lg font-semibold text-gray-900 dark:text-white mb-3">التخصص</h4>
                                <span class="inline-block px-4 py-2 bg-primary-sand/10 text-primary-sand rounded-xl font-medium">
                                    <?= htmlspecialchars($user['specialization']) ?>
                                </span>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($user['bio'])): ?>
                            <div class="mb-6">
                                <h4 class="text-lg font-semibold text-gray-900 dark:text-white mb-3">نبذة شخصية</h4>
                                <p class="text-gray-600 dark:text-gray-400 leading-relaxed"><?= htmlspecialchars($user['bio']) ?></p>
                            </div>
                        <?php endif; ?>
                        
                        <div>
                            <h4 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">الشارات والإنجازات</h4>
                            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                                <!-- Achievement badges based on performance -->
                                <?php if (($user['completed_tasks'] ?? 0) >= 10): ?>
                                    <div class="achievement-badge text-center p-4 bg-gradient-to-br from-yellow-100 to-yellow-200 dark:from-yellow-900/20 dark:to-yellow-800/20 rounded-2xl">
                                        <div class="w-12 h-12 bg-yellow-500 rounded-xl flex items-center justify-center mx-auto mb-2">
                                            <i data-lucide="star" class="w-6 h-6 text-white"></i>
                                        </div>
                                        <p class="text-xs font-medium text-yellow-800 dark:text-yellow-200">نجم مشرق</p>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if (($user['total_tasks'] ?? 0) > 0 && (($user['completed_tasks'] ?? 0) / $user['total_tasks']) >= 0.8): ?>
                                    <div class="achievement-badge text-center p-4 bg-gradient-to-br from-green-100 to-green-200 dark:from-green-900/20 dark:to-green-800/20 rounded-2xl">
                                        <div class="w-12 h-12 bg-green-500 rounded-xl flex items-center justify-center mx-auto mb-2">
                                            <i data-lucide="zap" class="w-6 h-6 text-white"></i>
                                        </div>
                                        <p class="text-xs font-medium text-green-800 dark:text-green-200">منجز سريع</p>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if ($user['role'] == 'manager' || $user['role'] == 'admin'): ?>
                                    <div class="achievement-badge text-center p-4 bg-gradient-to-br from-blue-100 to-blue-200 dark:from-blue-900/20 dark:to-blue-800/20 rounded-2xl">
                                        <div class="w-12 h-12 bg-blue-500 rounded-xl flex items-center justify-center mx-auto mb-2">
                                            <i data-lucide="users" class="w-6 h-6 text-white"></i>
                                        </div>
                                        <p class="text-xs font-medium text-blue-800 dark:text-blue-200">قائد فريق</p>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="achievement-badge text-center p-4 bg-gradient-to-br from-purple-100 to-purple-200 dark:from-purple-900/20 dark:to-purple-800/20 rounded-2xl">
                                    <div class="w-12 h-12 bg-purple-500 rounded-xl flex items-center justify-center mx-auto mb-2">
                                        <i data-lucide="award" class="w-6 h-6 text-white"></i>
                                    </div>
                                    <p class="text-xs font-medium text-purple-800 dark:text-purple-200">عضو محترف</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Right Column -->
                <div class="space-y-8">
                    
                    <!-- Quick Actions -->
                    <div class="profile-card bg-white dark:bg-gray-900 rounded-3xl p-6 border border-gray-200 dark:border-gray-700 fade-in">
                        <h3 class="text-xl font-bold text-gray-900 dark:text-white mb-6">إجراءات سريعة</h3>
                        
                        <div class="space-y-3">
                            <a href="tasks.php?action=create" class="w-full flex items-center gap-3 p-4 text-right hover:bg-gray-50 dark:hover:bg-gray-800 rounded-2xl transition-colors">
                                <div class="w-10 h-10 bg-blue-100 dark:bg-blue-900/20 rounded-xl flex items-center justify-center">
                                    <i data-lucide="plus" class="w-5 h-5 text-blue-600"></i>
                                </div>
                                <span class="font-medium text-gray-900 dark:text-white">إنشاء مهمة جديدة</span>
                            </a>
                            
                            <a href="tasks.php" class="w-full flex items-center gap-3 p-4 text-right hover:bg-gray-50 dark:hover:bg-gray-800 rounded-2xl transition-colors">
                                <div class="w-10 h-10 bg-green-100 dark:bg-green-900/20 rounded-xl flex items-center justify-center">
                                    <i data-lucide="calendar" class="w-5 h-5 text-green-600"></i>
                                </div>
                                <span class="font-medium text-gray-900 dark:text-white">عرض جدول المهام</span>
                            </a>
                            
                            <a href="reports.php" class="w-full flex items-center gap-3 p-4 text-right hover:bg-gray-50 dark:hover:bg-gray-800 rounded-2xl transition-colors">
                                <div class="w-10 h-10 bg-purple-100 dark:bg-purple-900/20 rounded-xl flex items-center justify-center">
                                    <i data-lucide="bar-chart" class="w-5 h-5 text-purple-600"></i>
                                </div>
                                <span class="font-medium text-gray-900 dark:text-white">تقارير الأداء</span>
                            </a>
                            
                            <button onclick="openEditProfileModal()" class="w-full flex items-center gap-3 p-4 text-right hover:bg-gray-50 dark:hover:bg-gray-800 rounded-2xl transition-colors">
                                <div class="w-10 h-10 bg-yellow-100 dark:bg-yellow-900/20 rounded-xl flex items-center justify-center">
                                    <i data-lucide="settings" class="w-5 h-5 text-yellow-600"></i>
                                </div>
                                <span class="font-medium text-gray-900 dark:text-white">تعديل الملف الشخصي</span>
                            </button>
                        </div>
                    </div>

                    <!-- Personal Goals -->
                    <div class="profile-card bg-white dark:bg-gray-900 rounded-3xl p-6 border border-gray-200 dark:border-gray-700 fade-in">
                        <h3 class="text-xl font-bold text-gray-900 dark:text-white mb-6">الأهداف الشخصية</h3>
                        
                        <div class="space-y-4">
                            <?php foreach ($goals as $goal): ?>
                                <?php 
                                $percentage = $goal['target'] > 0 ? round(($goal['current'] / $goal['target']) * 100) : 0;
                                if (isset($goal['reverse']) && $goal['reverse']) {
                                    $percentage = $goal['current'] > 0 ? max(0, 100 - round(($goal['current'] / $goal['target']) * 100)) : 100;
                                }
                                $percentage = min(100, $percentage);
                                ?>
                                <div class="p-4 bg-gradient-to-r from-<?= $goal['color'] ?>-50 to-<?= $goal['color'] ?>-100 dark:from-<?= $goal['color'] ?>-900/10 dark:to-<?= $goal['color'] ?>-800/10 rounded-2xl border border-<?= $goal['color'] ?>-200 dark:border-<?= $goal['color'] ?>-800">
                                    <div class="flex items-center justify-between mb-2">
                                        <span class="font-medium text-<?= $goal['color'] ?>-800 dark:text-<?= $goal['color'] ?>-400"><?= $goal['title'] ?></span>
                                        <span class="text-<?= $goal['color'] ?>-600 font-bold"><?= $goal['current'] ?>/<?= $goal['target'] ?></span>
                                    </div>
                                    <div class="w-full bg-<?= $goal['color'] ?>-200 dark:bg-<?= $goal['color'] ?>-800 rounded-full h-2">
                                        <div class="bg-<?= $goal['color'] ?>-500 h-2 rounded-full transition-all duration-500" style="width: <?= $percentage ?>%"></div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Team Members -->
                    <?php if (!empty($team_members)): ?>
                        <div class="profile-card bg-white dark:bg-gray-900 rounded-3xl p-6 border border-gray-200 dark:border-gray-700 fade-in">
                            <h3 class="text-xl font-bold text-gray-900 dark:text-white mb-6">زملاء الفريق</h3>
                            
                            <div class="space-y-4">
                                <?php foreach ($team_members as $member): ?>
                                    <div class="flex items-center gap-3">
                                        <div class="w-10 h-10 bg-gradient-to-br from-blue-500 to-blue-600 rounded-xl flex items-center justify-center text-white font-bold">
                                            <?php if (!empty($member['avatar']) && file_exists($member['avatar'])): ?>
                                                <img src="<?= htmlspecialchars($member['avatar']) ?>" alt="<?= htmlspecialchars($member['fullname']) ?>" class="w-full h-full object-cover rounded-xl">
                                            <?php else: ?>
                                                <?= mb_substr($member['fullname'], 0, 1, 'UTF-8') ?>
                                            <?php endif; ?>
                                        </div>
                                        <div class="flex-1">
                                            <p class="font-medium text-gray-900 dark:text-white"><?= htmlspecialchars($member['fullname']) ?></p>
                                            <p class="text-sm text-gray-500 dark:text-gray-400"><?= getRoleDisplayName($member['role']) ?></p>
                                        </div>
                                        <div class="w-3 h-3 bg-<?= ($member['is_online'] ?? 0) ? 'green' : 'gray' ?>-500 rounded-full" title="<?= ($member['is_online'] ?? 0) ? 'متصل الآن' : 'غير متصل' ?>"></div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            
                            <a href="users.php" class="w-full mt-4 py-3 text-primary-sand hover:text-primary-dark font-medium text-sm text-center block">
                                عرض جميع أعضاء الفريق
                            </a>
                        </div>
                    <?php endif; ?>

                    <!-- Notifications -->
                    <div class="profile-card bg-white dark:bg-gray-900 rounded-3xl p-6 border border-gray-200 dark:border-gray-700 fade-in">
                        <div class="flex items-center justify-between mb-6">
                            <h3 class="text-xl font-bold text-gray-900 dark:text-white">الإشعارات</h3>
                            <span class="w-6 h-6 bg-red-500 text-white rounded-full flex items-center justify-center text-xs font-bold">
                                <?= count(array_filter($notifications, function($n) { return !($n['is_read'] ?? true); })) ?>
                            </span>
                        </div>
                        
                        <div class="space-y-4">
                            <?php if (!empty($notifications)): ?>
                                <?php foreach (array_slice($notifications, 0, 3) as $notification): ?>
                                    <div class="flex items-start gap-3 p-3 bg-<?= ($notification['is_read'] ?? true) ? 'gray' : 'blue' ?>-50 dark:bg-<?= ($notification['is_read'] ?? true) ? 'gray' : 'blue' ?>-900/10 rounded-2xl border border-<?= ($notification['is_read'] ?? true) ? 'gray' : 'blue' ?>-200 dark:border-<?= ($notification['is_read'] ?? true) ? 'gray' : 'blue' ?>-800">
                                        <div class="w-8 h-8 bg-<?= ($notification['is_read'] ?? true) ? 'gray' : 'blue' ?>-500 rounded-lg flex items-center justify-center mt-1">
                                            <i data-lucide="bell" class="w-4 h-4 text-white"></i>
                                        </div>
                                        <div class="flex-1">
                                            <p class="text-sm font-medium text-gray-900 dark:text-white"><?= htmlspecialchars($notification['title']) ?></p>
                                            <?php if (!empty($notification['message'])): ?>
                                                <p class="text-xs text-gray-600 dark:text-gray-400 mt-1"><?= htmlspecialchars($notification['message']) ?></p>
                                            <?php endif; ?>
                                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1"><?= timeAgo($notification['created_at']) ?></p>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="text-center py-8">
                                    <div class="w-16 h-16 bg-gray-100 dark:bg-gray-800 rounded-full flex items-center justify-center mx-auto mb-4">
                                        <i data-lucide="bell-off" class="w-8 h-8 text-gray-400"></i>
                                    </div>
                                    <p class="text-gray-500 dark:text-gray-400">لا توجد إشعارات</p>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <?php if (!empty($notifications)): ?>
                            <button class="w-full mt-4 py-3 text-primary-sand hover:text-primary-dark font-medium text-sm">
                                عرض جميع الإشعارات
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Edit Profile Modal -->
    <div id="editProfileModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center p-4 hidden z-50">
        <div class="bg-white dark:bg-gray-900 rounded-3xl p-6 w-full max-w-2xl border border-gray-200 dark:border-gray-700 max-h-[90vh] overflow-y-auto">
            <div class="flex items-center justify-between mb-6">
                <h3 class="text-xl font-semibold text-gray-900 dark:text-white">تعديل الملف الشخصي</h3>
                <button onclick="closeEditProfileModal()" class="p-2 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 rounded-lg">
                    <i data-lucide="x" class="w-6 h-6"></i>
                </button>
            </div>
            
            <form method="POST" class="space-y-6">
                <input type="hidden" name="action" value="update_profile">
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">الاسم الكامل *</label>
                        <input type="text" name="fullname" required value="<?= htmlspecialchars($user['fullname']) ?>" class="w-full px-4 py-3 rounded-2xl border-2 border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-white focus:border-primary-sand focus:outline-none transition-colors">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">البريد الإلكتروني *</label>
                        <input type="email" name="email" required value="<?= htmlspecialchars($user['email']) ?>" class="w-full px-4 py-3 rounded-2xl border-2 border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-white focus:border-primary-sand focus:outline-none transition-colors">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">رقم الهاتف</label>
                        <input type="tel" name="phone" value="<?= htmlspecialchars($user['phone'] ?? '') ?>" class="w-full px-4 py-3 rounded-2xl border-2 border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-white focus:border-primary-sand focus:outline-none transition-colors">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">القسم</label>
                        <select name="department" class="w-full px-4 py-3 rounded-2xl border-2 border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-white focus:border-primary-sand focus:outline-none transition-colors">
                            <option value="">اختر القسم</option>
                            <?php foreach ($departments_options as $dept): ?>
                                <option value="<?= $dept ?>" <?= ($user['department'] ?? '') == $dept ? 'selected' : '' ?>><?= $dept ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">التخصص</label>
                    <select name="specialization" class="w-full px-4 py-3 rounded-2xl border-2 border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-white focus:border-primary-sand focus:outline-none transition-colors">
                        <option value="">اختر التخصص</option>
                        <?php foreach ($specializations_options as $spec): ?>
                            <option value="<?= $spec ?>" <?= ($user['specialization'] ?? '') == $spec ? 'selected' : '' ?>><?= $spec ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">نبذة شخصية</label>
                    <textarea name="bio" rows="4" placeholder="اكتب نبذة عن نفسك..." class="w-full px-4 py-3 rounded-2xl border-2 border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-white focus:border-primary-sand focus:outline-none transition-colors resize-none"><?= htmlspecialchars($user['bio'] ?? '') ?></textarea>
                </div>
                
                <div class="flex justify-end gap-4 pt-6 border-t border-gray-200 dark:border-gray-700">
                    <button type="button" onclick="closeEditProfileModal()" class="px-6 py-3 bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300 rounded-2xl hover:bg-gray-200 dark:hover:bg-gray-700 transition-colors">
                        إلغاء
                    </button>
                    <button type="submit" class="px-6 py-3 bg-gradient-to-r from-primary-sand to-primary-light text-white rounded-2xl hover:from-primary-light hover:to-primary-sand transition-all duration-300">
                        حفظ التغييرات
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Change Password Modal -->
    <div id="changePasswordModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center p-4 hidden z-50">
        <div class="bg-white dark:bg-gray-900 rounded-3xl p-6 w-full max-w-md border border-gray-200 dark:border-gray-700">
            <div class="flex items-center justify-between mb-6">
                <h3 class="text-xl font-semibold text-gray-900 dark:text-white">تغيير كلمة المرور</h3>
                <button onclick="closeChangePasswordModal()" class="p-2 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 rounded-lg">
                    <i data-lucide="x" class="w-6 h-6"></i>
                </button>
            </div>
            
            <form method="POST" class="space-y-6">
                <input type="hidden" name="action" value="change_password">
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">كلمة المرور الحالية *</label>
                    <input type="password" name="current_password" required class="w-full px-4 py-3 rounded-2xl border-2 border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-white focus:border-primary-sand focus:outline-none transition-colors">
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">كلمة المرور الجديدة *</label>
                    <input type="password" name="new_password" required minlength="6" class="w-full px-4 py-3 rounded-2xl border-2 border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-white focus:border-primary-sand focus:outline-none transition-colors">
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">تأكيد كلمة المرور الجديدة *</label>
                    <input type="password" name="confirm_password" required minlength="6" class="w-full px-4 py-3 rounded-2xl border-2 border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-white focus:border-primary-sand focus:outline-none transition-colors">
                </div>
                
                <div class="flex justify-end gap-4 pt-6 border-t border-gray-200 dark:border-gray-700">
                    <button type="button" onclick="closeChangePasswordModal()" class="px-6 py-3 bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300 rounded-2xl hover:bg-gray-200 dark:hover:bg-gray-700 transition-colors">
                        إلغاء
                    </button>
                    <button type="submit" class="px-6 py-3 bg-gradient-to-r from-primary-sand to-primary-light text-white rounded-2xl hover:from-primary-light hover:to-primary-sand transition-all duration-300">
                        تغيير كلمة المرور
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Avatar Upload Modal -->
    <div id="avatarModal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center p-4 hidden z-50">
        <div class="bg-white dark:bg-gray-900 rounded-3xl p-6 w-full max-w-md border border-gray-200 dark:border-gray-700">
            <div class="flex items-center justify-between mb-6">
                <h3 class="text-xl font-semibold text-gray-900 dark:text-white">تحديث الصورة الشخصية</h3>
                <button onclick="closeAvatarModal()" class="p-2 text-gray-400 hover:text-gray-600 dark:hover:text-gray-300 rounded-lg">
                    <i data-lucide="x" class="w-6 h-6"></i>
                </button>
            </div>
            
            <form method="POST" enctype="multipart/form-data" class="space-y-6">
                <input type="hidden" name="action" value="upload_avatar">
                
                <div class="text-center">
                    <div class="w-32 h-32 bg-gray-100 dark:bg-gray-800 rounded-full flex items-center justify-center mx-auto mb-4 overflow-hidden">
                        <?php if (!empty($user['avatar']) && file_exists($user['avatar'])): ?>
                            <img src="<?= htmlspecialchars($user['avatar']) ?>" alt="صورة شخصية" class="w-full h-full object-cover">
                        <?php else: ?>
                            <span class="text-4xl font-bold text-gray-400"><?= mb_substr($user['fullname'], 0, 1, 'UTF-8') ?></span>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">اختر صورة جديدة</label>
                    <input type="file" name="avatar" accept="image/*" required class="w-full px-4 py-3 rounded-2xl border-2 border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-gray-900 dark:text-white focus:border-primary-sand focus:outline-none transition-colors">
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-2">الحد الأقصى: 5 ميجابايت. الصيغ المدعومة: JPG, PNG, GIF</p>
                </div>
                
                <div class="flex justify-end gap-4 pt-6 border-t border-gray-200 dark:border-gray-700">
                    <button type="button" onclick="closeAvatarModal()" class="px-6 py-3 bg-gray-100 dark:bg-gray-800 text-gray-700 dark:text-gray-300 rounded-2xl hover:bg-gray-200 dark:hover:bg-gray-700 transition-colors">
                        إلغاء
                    </button>
                    <button type="submit" class="px-6 py-3 bg-gradient-to-r from-primary-sand to-primary-light text-white rounded-2xl hover:from-primary-light hover:to-primary-sand transition-all duration-300">
                        تحديث الصورة
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Scripts -->
    <script>
        // تهيئة الأيقونات
        document.addEventListener('DOMContentLoaded', function() {
            lucide.createIcons();
            initTheme();
            
            // إخفاء الرسائل تلقائياً
            const messages = document.querySelectorAll('.fade-in');
            messages.forEach(message => {
                if (message.classList.contains('bg-green-50') || message.classList.contains('bg-red-50')) {
                    setTimeout(() => {
                        message.style.opacity = '0';
                        setTimeout(() => {
                            message.remove();
                        }, 300);
                    }, 5000);
                }
            });
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
            const isDark = document.documentElement.classList.toggle('dark');
            localStorage.setItem('theme', isDark ? 'dark' : 'light');
            updateThemeIcon(isDark);
        });

        // تحديث أيقونة الثيم
        function updateThemeIcon(isDark) {
            const icon = themeToggle.querySelector('i');
            icon.setAttribute('data-lucide', isDark ? 'sun' : 'moon');
            lucide.createIcons();
        }

        // إدارة النماذج
        function openEditProfileModal() {
            document.getElementById('editProfileModal').classList.remove('hidden');
            document.body.style.overflow = 'hidden';
        }

        function closeEditProfileModal() {
            document.getElementById('editProfileModal').classList.add('hidden');
            document.body.style.overflow = 'auto';
        }

        function openChangePasswordModal() {
            document.getElementById('changePasswordModal').classList.remove('hidden');
            document.body.style.overflow = 'hidden';
        }

        function closeChangePasswordModal() {
            document.getElementById('changePasswordModal').classList.add('hidden');
            document.body.style.overflow = 'auto';
            // مسح النموذج
            document.querySelector('#changePasswordModal form').reset();
        }

        function openAvatarModal() {
            document.getElementById('avatarModal').classList.remove('hidden');
            document.body.style.overflow = 'hidden';
        }

        function closeAvatarModal() {
            document.getElementById('avatarModal').classList.add('hidden');
            document.body.style.overflow = 'auto';
        }

        // تأكيد تسجيل الخروج
        function confirmLogout() {
            if (confirm('هل أنت متأكد من تسجيل الخروج؟')) {
                window.location.href = 'logout.php';
            }
        }

        // التحقق من تطابق كلمات المرور
        document.addEventListener('DOMContentLoaded', function() {
            const newPasswordInput = document.querySelector('input[name="new_password"]');
            const confirmPasswordInput = document.querySelector('input[name="confirm_password"]');
            
            if (newPasswordInput && confirmPasswordInput) {
                confirmPasswordInput.addEventListener('input', function() {
                    if (newPasswordInput.value !== confirmPasswordInput.value) {
                        confirmPasswordInput.setCustomValidity('كلمات المرور غير متطابقة');
                    } else {
                        confirmPasswordInput.setCustomValidity('');
                    }
                });
            }
        });

        // إغلاق النماذج بمفتاح Escape
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                closeEditProfileModal();
                closeChangePasswordModal();
                closeAvatarModal();
            }
        });

        // إغلاق النماذج عند النقر خارجها
        document.addEventListener('click', (e) => {
            const modals = ['editProfileModal', 'changePasswordModal', 'avatarModal'];
            modals.forEach(modalId => {
                const modal = document.getElementById(modalId);
                if (modal && !modal.classList.contains('hidden') && e.target === modal) {
                    modal.classList.add('hidden');
                    document.body.style.overflow = 'auto';
                }
            });
        });
    </script>
</body>
</html>