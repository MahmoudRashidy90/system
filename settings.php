<?php
session_start();
require_once 'api/config.php';

// التحقق من تسجيل الدخول
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$success_message = '';
$error_message = '';

// معالجة حفظ الإعدادات
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (isset($_POST['save_general'])) {
            // حفظ الإعدادات العامة
            $company_name = $_POST['company_name'] ?? '';
            $company_email = $_POST['company_email'] ?? '';
            $company_phone = $_POST['company_phone'] ?? '';
            $company_website = $_POST['company_website'] ?? '';
            $company_address = $_POST['company_address'] ?? '';
            $language = $_POST['language'] ?? 'ar';
            $timezone = $_POST['timezone'] ?? 'Asia/Riyadh';
            $date_format = $_POST['date_format'] ?? 'Y-m-d';
            $time_format = $_POST['time_format'] ?? 'H:i';
            
            $stmt = $pdo->prepare("
                INSERT INTO system_settings (setting_key, setting_value, user_id) 
                VALUES (?, ?, ?) 
                ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)
            ");
            
            $settings = [
                'company_name' => $company_name,
                'company_email' => $company_email,
                'company_phone' => $company_phone,
                'company_website' => $company_website,
                'company_address' => $company_address,
                'language' => $language,
                'timezone' => $timezone,
                'date_format' => $date_format,
                'time_format' => $time_format
            ];
            
            foreach ($settings as $key => $value) {
                $stmt->execute([$key, $value, $user_id]);
            }
            
            $success_message = 'تم حفظ الإعدادات العامة بنجاح!';
        }
        
        if (isset($_POST['save_theme'])) {
            // حفظ إعدادات المظهر
            $theme = $_POST['theme'] ?? 'light';
            $color_scheme = $_POST['color_scheme'] ?? 'blue';
            $font_family = $_POST['font_family'] ?? 'Cairo';
            $font_size = $_POST['font_size'] ?? 'medium';
            
            $stmt = $pdo->prepare("
                INSERT INTO system_settings (setting_key, setting_value, user_id) 
                VALUES (?, ?, ?) 
                ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)
            ");
            
            $stmt->execute(['theme', $theme, $user_id]);
            $stmt->execute(['color_scheme', $color_scheme, $user_id]);
            $stmt->execute(['font_family', $font_family, $user_id]);
            $stmt->execute(['font_size', $font_size, $user_id]);
            
            $success_message = 'تم حفظ إعدادات المظهر بنجاح!';
        }
        
        if (isset($_POST['save_notifications'])) {
            // حفظ إعدادات الإشعارات
            $task_notifications = isset($_POST['task_notifications']) ? 1 : 0;
            $email_notifications = isset($_POST['email_notifications']) ? 1 : 0;
            $reminder_notifications = isset($_POST['reminder_notifications']) ? 1 : 0;
            $comment_notifications = isset($_POST['comment_notifications']) ? 1 : 0;
            $notification_sound = isset($_POST['notification_sound']) ? 1 : 0;
            $email_frequency = $_POST['email_frequency'] ?? 'daily';
            
            $stmt = $pdo->prepare("
                INSERT INTO system_settings (setting_key, setting_value, user_id) 
                VALUES (?, ?, ?) 
                ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)
            ");
            
            $notifications = [
                'task_notifications' => $task_notifications,
                'email_notifications' => $email_notifications,
                'reminder_notifications' => $reminder_notifications,
                'comment_notifications' => $comment_notifications,
                'notification_sound' => $notification_sound,
                'email_frequency' => $email_frequency
            ];
            
            foreach ($notifications as $key => $value) {
                $stmt->execute([$key, $value, $user_id]);
            }
            
            $success_message = 'تم حفظ إعدادات الإشعارات بنجاح!';
        }
        
        if (isset($_POST['change_password'])) {
            // تغيير كلمة المرور
            $current_password = $_POST['current_password'] ?? '';
            $new_password = $_POST['new_password'] ?? '';
            $confirm_password = $_POST['confirm_password'] ?? '';
            
            if ($new_password !== $confirm_password) {
                throw new Exception('كلمات المرور الجديدة غير متطابقة');
            }
            
            // التحقق من كلمة المرور الحالية
            $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
            $stmt->execute([$user_id]);
            $user = $stmt->fetch();
            
            if (!password_verify($current_password, $user['password'])) {
                throw new Exception('كلمة المرور الحالية غير صحيحة');
            }
            
            // تحديث كلمة المرور
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
            $stmt->execute([$hashed_password, $user_id]);
            
            $success_message = 'تم تغيير كلمة المرور بنجاح!';
        }
        
    } catch (Exception $e) {
        $error_message = $e->getMessage();
    }
}

// جلب الإعدادات الحالية
$current_settings = [];
$stmt = $pdo->prepare("SELECT setting_key, setting_value FROM system_settings WHERE user_id = ?");
$stmt->execute([$user_id]);
while ($row = $stmt->fetch()) {
    $current_settings[$row['setting_key']] = $row['setting_value'];
}

// جلب معلومات المستخدم
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user_info = $stmt->fetch();
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>الإعدادات - نظام إدارة المهام</title>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        'cairo': ['Cairo', 'sans-serif'],
                    }
                }
            }
        }
    </script>
    
    <style>
        body { font-family: 'Cairo', sans-serif; }
        .settings-section { display: none; }
        .settings-section.active { display: block; }
        
        .sidebar-item {
            @apply flex items-center gap-3 px-4 py-3 rounded-2xl transition-all duration-300 cursor-pointer;
        }
        
        .sidebar-item:hover {
            @apply bg-blue-50 text-blue-600 transform translate-x-1;
        }
        
        .sidebar-item.active {
            @apply bg-blue-600 text-white shadow-lg;
        }
        
        .form-input {
            @apply w-full px-4 py-3 border border-gray-300 rounded-2xl focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all duration-300;
        }
        
        .btn-primary {
            @apply bg-blue-600 hover:bg-blue-700 text-white px-6 py-3 rounded-2xl transition-all duration-300 transform hover:scale-105 shadow-lg;
        }
        
        .btn-secondary {
            @apply bg-gray-200 hover:bg-gray-300 text-gray-700 px-6 py-3 rounded-2xl transition-all duration-300;
        }
    </style>
</head>

<body class="bg-gradient-to-br from-blue-50 to-indigo-100 min-h-screen">
    <!-- تنبيهات النجاح والخطأ -->
    <?php if ($success_message): ?>
    <div id="success-alert" class="fixed top-4 right-4 bg-green-500 text-white px-6 py-3 rounded-lg shadow-lg z-50">
        <i class="fas fa-check-circle mr-2"></i>
        <?php echo $success_message; ?>
    </div>
    <?php endif; ?>

    <?php if ($error_message): ?>
    <div id="error-alert" class="fixed top-4 right-4 bg-red-500 text-white px-6 py-3 rounded-lg shadow-lg z-50">
        <i class="fas fa-exclamation-circle mr-2"></i>
        <?php echo $error_message; ?>
    </div>
    <?php endif; ?>

    <div class="flex h-screen">
        <!-- الشريط الجانبي -->
        <div class="w-80 bg-white shadow-2xl">
            <div class="p-6 border-b border-gray-200">
                <h1 class="text-2xl font-bold text-gray-800 flex items-center gap-3">
                    <i class="fas fa-cog text-blue-600"></i>
                    الإعدادات
                </h1>
                <p class="text-gray-500 mt-2">إدارة وتخصيص النظام</p>
            </div>
            
            <nav class="p-4 space-y-2">
                <div class="sidebar-item active" onclick="showSection('general-section')">
                    <i class="fas fa-building w-5 h-5"></i>
                    <span>الإعدادات العامة</span>
                </div>
                
                <div class="sidebar-item" onclick="showSection('theme-section')">
                    <i class="fas fa-palette w-5 h-5"></i>
                    <span>المظهر والثيم</span>
                </div>
                
                <div class="sidebar-item" onclick="showSection('notifications-section')">
                    <i class="fas fa-bell w-5 h-5"></i>
                    <span>الإشعارات</span>
                </div>
                
                <div class="sidebar-item" onclick="showSection('security-section')">
                    <i class="fas fa-shield-alt w-5 h-5"></i>
                    <span>الأمان والخصوصية</span>
                </div>
                
                <div class="sidebar-item" onclick="showSection('system-section')">
                    <i class="fas fa-server w-5 h-5"></i>
                    <span>إعدادات النظام</span>
                </div>
                
                <div class="sidebar-item" onclick="showSection('backup-section')">
                    <i class="fas fa-download w-5 h-5"></i>
                    <span>النسخ الاحتياطي</span>
                </div>
                
                <div class="sidebar-item" onclick="showSection('about-section')">
                    <i class="fas fa-info-circle w-5 h-5"></i>
                    <span>حول النظام</span>
                </div>
                
                <hr class="my-4">
                
                <div class="sidebar-item" onclick="window.location.href='dashboard.php'">
                    <i class="fas fa-arrow-right w-5 h-5"></i>
                    <span>العودة للداشبورد</span>
                </div>
            </nav>
        </div>

        <!-- المحتوى الرئيسي -->
        <div class="flex-1 overflow-y-auto">
            <div class="p-8">
                <!-- الإعدادات العامة -->
                <div id="general-section" class="settings-section active">
                    <div class="bg-white rounded-3xl shadow-lg border border-gray-200 p-8">
                        <div class="flex items-center gap-3 mb-8">
                            <div class="p-3 bg-blue-100 rounded-2xl">
                                <i class="fas fa-building text-blue-600 text-xl"></i>
                            </div>
                            <div>
                                <h2 class="text-2xl font-bold text-gray-800">الإعدادات العامة</h2>
                                <p class="text-gray-500">معلومات الشركة والتفضيلات الأساسية</p>
                            </div>
                        </div>

                        <form method="POST" class="space-y-6">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">اسم الشركة</label>
                                    <input type="text" name="company_name" class="form-input" 
                                           value="<?php echo htmlspecialchars($current_settings['company_name'] ?? 'كوريان كاسيل'); ?>" 
                                           placeholder="اسم الشركة">
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">البريد الإلكتروني</label>
                                    <input type="email" name="company_email" class="form-input" 
                                           value="<?php echo htmlspecialchars($current_settings['company_email'] ?? ''); ?>" 
                                           placeholder="info@company.com">
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">رقم الهاتف</label>
                                    <input type="tel" name="company_phone" class="form-input" 
                                           value="<?php echo htmlspecialchars($current_settings['company_phone'] ?? ''); ?>" 
                                           placeholder="+966 12 345 6789">
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">الموقع الإلكتروني</label>
                                    <input type="url" name="company_website" class="form-input" 
                                           value="<?php echo htmlspecialchars($current_settings['company_website'] ?? ''); ?>" 
                                           placeholder="https://company.com">
                                </div>
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">العنوان</label>
                                <textarea name="company_address" rows="3" class="form-input" 
                                          placeholder="عنوان الشركة الكامل"><?php echo htmlspecialchars($current_settings['company_address'] ?? ''); ?></textarea>
                            </div>
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">اللغة</label>
                                    <select name="language" class="form-input">
                                        <option value="ar" <?php echo ($current_settings['language'] ?? 'ar') === 'ar' ? 'selected' : ''; ?>>العربية</option>
                                        <option value="en" <?php echo ($current_settings['language'] ?? 'ar') === 'en' ? 'selected' : ''; ?>>English</option>
                                    </select>
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">المنطقة الزمنية</label>
                                    <select name="timezone" class="form-input">
                                        <option value="Asia/Riyadh" <?php echo ($current_settings['timezone'] ?? 'Asia/Riyadh') === 'Asia/Riyadh' ? 'selected' : ''; ?>>الرياض</option>
                                        <option value="Asia/Dubai" <?php echo ($current_settings['timezone'] ?? 'Asia/Riyadh') === 'Asia/Dubai' ? 'selected' : ''; ?>>دبي</option>
                                        <option value="Africa/Cairo" <?php echo ($current_settings['timezone'] ?? 'Asia/Riyadh') === 'Africa/Cairo' ? 'selected' : ''; ?>>القاهرة</option>
                                    </select>
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">تنسيق التاريخ</label>
                                    <select name="date_format" class="form-input">
                                        <option value="Y-m-d" <?php echo ($current_settings['date_format'] ?? 'Y-m-d') === 'Y-m-d' ? 'selected' : ''; ?>>2024-12-31</option>
                                        <option value="d/m/Y" <?php echo ($current_settings['date_format'] ?? 'Y-m-d') === 'd/m/Y' ? 'selected' : ''; ?>>31/12/2024</option>
                                        <option value="d-m-Y" <?php echo ($current_settings['date_format'] ?? 'Y-m-d') === 'd-m-Y' ? 'selected' : ''; ?>>31-12-2024</option>
                                    </select>
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">تنسيق الوقت</label>
                                    <select name="time_format" class="form-input">
                                        <option value="H:i" <?php echo ($current_settings['time_format'] ?? 'H:i') === 'H:i' ? 'selected' : ''; ?>>24 ساعة (15:30)</option>
                                        <option value="g:i A" <?php echo ($current_settings['time_format'] ?? 'H:i') === 'g:i A' ? 'selected' : ''; ?>>12 ساعة (3:30 PM)</option>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="flex justify-end">
                                <button type="submit" name="save_general" class="btn-primary">
                                    <i class="fas fa-save mr-2"></i>
                                    حفظ الإعدادات العامة
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- إعدادات المظهر -->
                <div id="theme-section" class="settings-section">
                    <div class="bg-white rounded-3xl shadow-lg border border-gray-200 p-8">
                        <div class="flex items-center gap-3 mb-8">
                            <div class="p-3 bg-purple-100 rounded-2xl">
                                <i class="fas fa-palette text-purple-600 text-xl"></i>
                            </div>
                            <div>
                                <h2 class="text-2xl font-bold text-gray-800">المظهر والثيم</h2>
                                <p class="text-gray-500">تخصيص شكل ومظهر النظام</p>
                            </div>
                        </div>

                        <form method="POST" class="space-y-8">
                            <div>
                                <label class="block text-lg font-medium text-gray-700 mb-4">اختيار الثيم</label>
                                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                                    <div class="theme-option <?php echo ($current_settings['theme'] ?? 'light') === 'light' ? 'selected' : ''; ?>" onclick="selectTheme('light')">
                                        <input type="radio" name="theme" value="light" <?php echo ($current_settings['theme'] ?? 'light') === 'light' ? 'checked' : ''; ?> class="hidden">
                                        <div class="p-6 border-2 border-gray-200 rounded-2xl hover:border-blue-500 cursor-pointer transition-all duration-300">
                                            <div class="w-full h-20 bg-gradient-to-r from-blue-50 to-white rounded-lg mb-3"></div>
                                            <h3 class="font-medium text-gray-800">الثيم الفاتح</h3>
                                            <p class="text-sm text-gray-500">مظهر كلاسيكي ومريح للعين</p>
                                        </div>
                                    </div>
                                    
                                    <div class="theme-option <?php echo ($current_settings['theme'] ?? 'light') === 'dark' ? 'selected' : ''; ?>" onclick="selectTheme('dark')">
                                        <input type="radio" name="theme" value="dark" <?php echo ($current_settings['theme'] ?? 'light') === 'dark' ? 'checked' : ''; ?> class="hidden">
                                        <div class="p-6 border-2 border-gray-200 rounded-2xl hover:border-blue-500 cursor-pointer transition-all duration-300">
                                            <div class="w-full h-20 bg-gradient-to-r from-gray-800 to-gray-600 rounded-lg mb-3"></div>
                                            <h3 class="font-medium text-gray-800">الثيم الداكن</h3>
                                            <p class="text-sm text-gray-500">مظهر أنيق ومريح في الإضاءة المنخفضة</p>
                                        </div>
                                    </div>
                                    
                                    <div class="theme-option <?php echo ($current_settings['theme'] ?? 'light') === 'auto' ? 'selected' : ''; ?>" onclick="selectTheme('auto')">
                                        <input type="radio" name="theme" value="auto" <?php echo ($current_settings['theme'] ?? 'light') === 'auto' ? 'checked' : ''; ?> class="hidden">
                                        <div class="p-6 border-2 border-gray-200 rounded-2xl hover:border-blue-500 cursor-pointer transition-all duration-300">
                                            <div class="w-full h-20 bg-gradient-to-r from-blue-50 via-gray-100 to-gray-600 rounded-lg mb-3"></div>
                                            <h3 class="font-medium text-gray-800">تلقائي</h3>
                                            <p class="text-sm text-gray-500">يتبع إعدادات النظام</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div>
                                <label class="block text-lg font-medium text-gray-700 mb-4">نظام الألوان</label>
                                <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                                    <div class="color-option" onclick="selectColor('blue')">
                                        <input type="radio" name="color_scheme" value="blue" <?php echo ($current_settings['color_scheme'] ?? 'blue') === 'blue' ? 'checked' : ''; ?> class="hidden">
                                        <div class="p-4 border-2 border-gray-200 rounded-2xl hover:border-blue-500 cursor-pointer transition-all duration-300">
                                            <div class="w-full h-8 bg-blue-500 rounded-lg mb-2"></div>
                                            <p class="text-sm font-medium text-center">أزرق</p>
                                        </div>
                                    </div>
                                    
                                    <div class="color-option" onclick="selectColor('green')">
                                        <input type="radio" name="color_scheme" value="green" <?php echo ($current_settings['color_scheme'] ?? 'blue') === 'green' ? 'checked' : ''; ?> class="hidden">
                                        <div class="p-4 border-2 border-gray-200 rounded-2xl hover:border-green-500 cursor-pointer transition-all duration-300">
                                            <div class="w-full h-8 bg-green-500 rounded-lg mb-2"></div>
                                            <p class="text-sm font-medium text-center">أخضر</p>
                                        </div>
                                    </div>
                                    
                                    <div class="color-option" onclick="selectColor('purple')">
                                        <input type="radio" name="color_scheme" value="purple" <?php echo ($current_settings['color_scheme'] ?? 'blue') === 'purple' ? 'checked' : ''; ?> class="hidden">
                                        <div class="p-4 border-2 border-gray-200 rounded-2xl hover:border-purple-500 cursor-pointer transition-all duration-300">
                                            <div class="w-full h-8 bg-purple-500 rounded-lg mb-2"></div>
                                            <p class="text-sm font-medium text-center">بنفسجي</p>
                                        </div>
                                    </div>
                                    
                                    <div class="color-option" onclick="selectColor('orange')">
                                        <input type="radio" name="color_scheme" value="orange" <?php echo ($current_settings['color_scheme'] ?? 'blue') === 'orange' ? 'checked' : ''; ?> class="hidden">
                                        <div class="p-4 border-2 border-gray-200 rounded-2xl hover:border-orange-500 cursor-pointer transition-all duration-300">
                                            <div class="w-full h-8 bg-orange-500 rounded-lg mb-2"></div>
                                            <p class="text-sm font-medium text-center">برتقالي</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">نوع الخط</label>
                                    <select name="font_family" class="form-input">
                                        <option value="Cairo" <?php echo ($current_settings['font_family'] ?? 'Cairo') === 'Cairo' ? 'selected' : ''; ?>>Cairo</option>
                                        <option value="Amiri" <?php echo ($current_settings['font_family'] ?? 'Cairo') === 'Amiri' ? 'selected' : ''; ?>>Amiri</option>
                                        <option value="Tajawal" <?php echo ($current_settings['font_family'] ?? 'Cairo') === 'Tajawal' ? 'selected' : ''; ?>>Tajawal</option>
                                    </select>
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">حجم الخط</label>
                                    <select name="font_size" class="form-input">
                                        <option value="small" <?php echo ($current_settings['font_size'] ?? 'medium') === 'small' ? 'selected' : ''; ?>>صغير</option>
                                        <option value="medium" <?php echo ($current_settings['font_size'] ?? 'medium') === 'medium' ? 'selected' : ''; ?>>متوسط</option>
                                        <option value="large" <?php echo ($current_settings['font_size'] ?? 'medium') === 'large' ? 'selected' : ''; ?>>كبير</option>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="flex justify-end">
                                <button type="submit" name="save_theme" class="btn-primary">
                                    <i class="fas fa-save mr-2"></i>
                                    حفظ إعدادات المظهر
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- إعدادات الإشعارات -->
                <div id="notifications-section" class="settings-section">
                    <div class="bg-white rounded-3xl shadow-lg border border-gray-200 p-8">
                        <div class="flex items-center gap-3 mb-8">
                            <div class="p-3 bg-yellow-100 rounded-2xl">
                                <i class="fas fa-bell text-yellow-600 text-xl"></i>
                            </div>
                            <div>
                                <h2 class="text-2xl font-bold text-gray-800">إعدادات الإشعارات</h2>
                                <p class="text-gray-500">إدارة التنبيهات والإشعارات</p>
                            </div>
                        </div>

                        <form method="POST" class="space-y-6">
                            <div class="space-y-4">
                                <div class="flex items-center justify-between p-4 bg-gray-50 rounded-xl">
                                    <div>
                                        <h4 class="font-medium text-gray-800">إشعارات المهام</h4>
                                        <p class="text-sm text-gray-500">تلقي إشعار عند تكليفك بمهمة جديدة</p>
                                    </div>
                                    <label class="relative inline-flex items-center cursor-pointer">
                                        <input type="checkbox" name="task_notifications" value="1" <?php echo ($current_settings['task_notifications'] ?? '1') == '1' ? 'checked' : ''; ?> class="sr-only peer">
                                        <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300 dark:peer-focus:ring-blue-800 rounded-full peer dark:bg-gray-700 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all dark:border-gray-600 peer-checked:bg-blue-600"></div>
                                    </label>
                                </div>
                                
                                <div class="flex items-center justify-between p-4 bg-gray-50 rounded-xl">
                                    <div>
                                        <h4 class="font-medium text-gray-800">إشعارات التذكير</h4>
                                        <p class="text-sm text-gray-500">تذكيرات بالمهام المستحقة</p>
                                    </div>
                                    <label class="relative inline-flex items-center cursor-pointer">
                                        <input type="checkbox" name="reminder_notifications" value="1" <?php echo ($current_settings['reminder_notifications'] ?? '1') == '1' ? 'checked' : ''; ?> class="sr-only peer">
                                        <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300 dark:peer-focus:ring-blue-800 rounded-full peer dark:bg-gray-700 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all dark:border-gray-600 peer-checked:bg-blue-600"></div>
                                    </label>
                                </div>
                                
                                <div class="flex items-center justify-between p-4 bg-gray-50 rounded-xl">
                                    <div>
                                        <h4 class="font-medium text-gray-800">إشعارات التعليقات</h4>
                                        <p class="text-sm text-gray-500">إشعار عند إضافة تعليق على مهامك</p>
                                    </div>
                                    <label class="relative inline-flex items-center cursor-pointer">
                                        <input type="checkbox" name="comment_notifications" value="1" <?php echo ($current_settings['comment_notifications'] ?? '1') == '1' ? 'checked' : ''; ?> class="sr-only peer">
                                        <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300 dark:peer-focus:ring-blue-800 rounded-full peer dark:bg-gray-700 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all dark:border-gray-600 peer-checked:bg-blue-600"></div>
                                    </label>
                                </div>
                                
                                <div class="flex items-center justify-between p-4 bg-gray-50 rounded-xl">
                                    <div>
                                        <h4 class="font-medium text-gray-800">صوت الإشعارات</h4>
                                        <p class="text-sm text-gray-500">تشغيل صوت عند وصول إشعار</p>
                                    </div>
                                    <label class="relative inline-flex items-center cursor-pointer">
                                        <input type="checkbox" name="notification_sound" value="1" <?php echo ($current_settings['notification_sound'] ?? '0') == '1' ? 'checked' : ''; ?> class="sr-only peer">
                                        <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300 dark:peer-focus:ring-blue-800 rounded-full peer dark:bg-gray-700 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all dark:border-gray-600 peer-checked:bg-blue-600"></div>
                                    </label>
                                </div>
                            </div>
                            
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">تكرار الإشعارات البريدية</label>
                                <select name="email_frequency" class="form-input">
                                    <option value="instant" <?php echo ($current_settings['email_frequency'] ?? 'daily') === 'instant' ? 'selected' : ''; ?>>فوري</option>
                                    <option value="daily" <?php echo ($current_settings['email_frequency'] ?? 'daily') === 'daily' ? 'selected' : ''; ?>>يومي</option>
                                    <option value="weekly" <?php echo ($current_settings['email_frequency'] ?? 'daily') === 'weekly' ? 'selected' : ''; ?>>أسبوعي</option>
                                    <option value="never" <?php echo ($current_settings['email_frequency'] ?? 'daily') === 'never' ? 'selected' : ''; ?>>أبداً</option>
                                </select>
                            </div>
                            
                            <div class="flex justify-end">
                                <button type="submit" name="save_notifications" class="btn-primary">
                                    <i class="fas fa-save mr-2"></i>
                                    حفظ إعدادات الإشعارات
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- إعدادات الأمان -->
                <div id="security-section" class="settings-section">
                    <div class="bg-white rounded-3xl shadow-lg border border-gray-200 p-8">
                        <div class="flex items-center gap-3 mb-8">
                            <div class="p-3 bg-red-100 rounded-2xl">
                                <i class="fas fa-shield-alt text-red-600 text-xl"></i>
                            </div>
                            <div>
                                <h2 class="text-2xl font-bold text-gray-800">الأمان والخصوصية</h2>
                                <p class="text-gray-500">إدارة إعدادات الأمان وحماية البيانات</p>
                            </div>
                        </div>

                        <form method="POST" class="space-y-6">
                            <div class="bg-gray-50 rounded-xl p-6">
                                <h3 class="text-lg font-semibold mb-4">تغيير كلمة المرور</h3>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">كلمة المرور الحالية</label>
                                        <input type="password" name="current_password" class="form-input" placeholder="كلمة المرور الحالية">
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">كلمة المرور الجديدة</label>
                                        <input type="password" name="new_password" class="form-input" placeholder="كلمة المرور الجديدة">
                                    </div>
                                    <div class="md:col-span-2">
                                        <label class="block text-sm font-medium text-gray-700 mb-2">تأكيد كلمة المرور</label>
                                        <input type="password" name="confirm_password" class="form-input" placeholder="تأكيد كلمة المرور الجديدة">
                                    </div>
                                </div>
                                <div class="mt-4">
                                    <button type="submit" name="change_password" class="bg-red-600 hover:bg-red-700 text-white px-6 py-2 rounded-xl">
                                        <i class="fas fa-key mr-2"></i>
                                        تغيير كلمة المرور
                                    </button>
                                </div>
                            </div>
                            
                            <div class="space-y-4">
                                <div class="flex items-center justify-between p-4 bg-gray-50 rounded-xl">
                                    <div>
                                        <h4 class="font-medium text-gray-800">تسجيل الخروج التلقائي</h4>
                                        <p class="text-sm text-gray-500">تسجيل خروج تلقائي بعد فترة عدم نشاط</p>
                                    </div>
                                    <label class="relative inline-flex items-center cursor-pointer">
                                        <input type="checkbox" name="auto_logout" value="1" <?php echo ($current_settings['auto_logout'] ?? '1') == '1' ? 'checked' : ''; ?> class="sr-only peer">
                                        <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300 dark:peer-focus:ring-blue-800 rounded-full peer dark:bg-gray-700 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all dark:border-gray-600 peer-checked:bg-blue-600"></div>
                                    </label>
                                </div>
                                
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">مدة الجلسة (بالدقائق)</label>
                                    <select name="session_timeout" class="form-input max-w-xs">
                                        <option value="15" <?php echo ($current_settings['session_timeout'] ?? '30') === '15' ? 'selected' : ''; ?>>15 دقيقة</option>
                                        <option value="30" <?php echo ($current_settings['session_timeout'] ?? '30') === '30' ? 'selected' : ''; ?>>30 دقيقة</option>
                                        <option value="60" <?php echo ($current_settings['session_timeout'] ?? '30') === '60' ? 'selected' : ''; ?>>ساعة واحدة</option>
                                        <option value="120" <?php echo ($current_settings['session_timeout'] ?? '30') === '120' ? 'selected' : ''; ?>>ساعتان</option>
                                        <option value="480" <?php echo ($current_settings['session_timeout'] ?? '30') === '480' ? 'selected' : ''; ?>>8 ساعات</option>
                                    </select>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- إعدادات النظام -->
                <div id="system-section" class="settings-section">
                    <div class="bg-white rounded-3xl shadow-lg border border-gray-200 p-8">
                        <div class="flex items-center gap-3 mb-8">
                            <div class="p-3 bg-indigo-100 rounded-2xl">
                                <i class="fas fa-server text-indigo-600 text-xl"></i>
                            </div>
                            <div>
                                <h2 class="text-2xl font-bold text-gray-800">إعدادات النظام</h2>
                                <p class="text-gray-500">إدارة إعدادات النظام المتقدمة</p>
                            </div>
                        </div>

                        <div class="space-y-6">
                            <div class="bg-gray-50 rounded-xl p-6">
                                <h3 class="text-lg font-semibold mb-4">إعدادات الأداء</h3>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">عدد المهام المعروضة في الصفحة</label>
                                        <select name="tasks_per_page" class="form-input">
                                            <option value="10" <?php echo ($current_settings['tasks_per_page'] ?? '25') === '10' ? 'selected' : ''; ?>>10 مهام</option>
                                            <option value="25" <?php echo ($current_settings['tasks_per_page'] ?? '25') === '25' ? 'selected' : ''; ?>>25 مهمة</option>
                                            <option value="50" <?php echo ($current_settings['tasks_per_page'] ?? '25') === '50' ? 'selected' : ''; ?>>50 مهمة</option>
                                            <option value="100" <?php echo ($current_settings['tasks_per_page'] ?? '25') === '100' ? 'selected' : ''; ?>>100 مهمة</option>
                                        </select>
                                    </div>
                                    
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">فترة تحديث البيانات (بالثواني)</label>
                                        <select name="refresh_interval" class="form-input">
                                            <option value="30" <?php echo ($current_settings['refresh_interval'] ?? '60') === '30' ? 'selected' : ''; ?>>30 ثانية</option>
                                            <option value="60" <?php echo ($current_settings['refresh_interval'] ?? '60') === '60' ? 'selected' : ''; ?>>دقيقة واحدة</option>
                                            <option value="300" <?php echo ($current_settings['refresh_interval'] ?? '60') === '300' ? 'selected' : ''; ?>>5 دقائق</option>
                                            <option value="0" <?php echo ($current_settings['refresh_interval'] ?? '60') === '0' ? 'selected' : ''; ?>>يدوي</option>
                                        </select>
                                    </div>
                                </div>
                                
                                <div class="mt-4 space-y-4">
                                    <div class="flex items-center justify-between">
                                        <div>
                                            <h4 class="font-medium text-gray-800">الحفظ التلقائي</h4>
                                            <p class="text-sm text-gray-500">حفظ التغييرات تلقائياً</p>
                                        </div>
                                        <label class="relative inline-flex items-center cursor-pointer">
                                            <input type="checkbox" name="auto_save" value="1" <?php echo ($current_settings['auto_save'] ?? '1') == '1' ? 'checked' : ''; ?> class="sr-only peer">
                                            <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300 dark:peer-focus:ring-blue-800 rounded-full peer dark:bg-gray-700 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all dark:border-gray-600 peer-checked:bg-blue-600"></div>
                                        </label>
                                    </div>
                                    
                                    <div class="flex items-center justify-between">
                                        <div>
                                            <h4 class="font-medium text-gray-800">الرسوم المتحركة</h4>
                                            <p class="text-sm text-gray-500">تفعيل التأثيرات البصرية</p>
                                        </div>
                                        <label class="relative inline-flex items-center cursor-pointer">
                                            <input type="checkbox" name="enable_animations" value="1" <?php echo ($current_settings['enable_animations'] ?? '1') == '1' ? 'checked' : ''; ?> class="sr-only peer">
                                            <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300 dark:peer-focus:ring-blue-800 rounded-full peer dark:bg-gray-700 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all dark:border-gray-600 peer-checked:bg-blue-600"></div>
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- النسخ الاحتياطي -->
                <div id="backup-section" class="settings-section">
                    <div class="bg-white rounded-3xl shadow-lg border border-gray-200 p-8">
                        <div class="flex items-center gap-3 mb-8">
                            <div class="p-3 bg-green-100 rounded-2xl">
                                <i class="fas fa-download text-green-600 text-xl"></i>
                            </div>
                            <div>
                                <h2 class="text-2xl font-bold text-gray-800">النسخ الاحتياطي</h2>
                                <p class="text-gray-500">إدارة النسخ الاحتياطية للبيانات</p>
                            </div>
                        </div>

                        <div class="space-y-6">
                            <div class="bg-gray-50 rounded-xl p-6">
                                <h3 class="text-lg font-semibold mb-4">النسخ التلقائي</h3>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">تكرار النسخ</label>
                                        <select name="backup_frequency" class="form-input">
                                            <option value="daily" <?php echo ($current_settings['backup_frequency'] ?? 'daily') === 'daily' ? 'selected' : ''; ?>>يومي</option>
                                            <option value="weekly" <?php echo ($current_settings['backup_frequency'] ?? 'daily') === 'weekly' ? 'selected' : ''; ?>>أسبوعي</option>
                                            <option value="monthly" <?php echo ($current_settings['backup_frequency'] ?? 'daily') === 'monthly' ? 'selected' : ''; ?>>شهري</option>
                                        </select>
                                    </div>
                                    
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-2">وقت النسخ</label>
                                        <input type="time" name="backup_time" class="form-input" value="<?php echo htmlspecialchars($current_settings['backup_time'] ?? '02:00'); ?>">
                                    </div>
                                </div>
                                
                                <div class="mt-4">
                                    <div class="flex items-center justify-between">
                                        <div>
                                            <h4 class="font-medium text-gray-800">تفعيل النسخ التلقائي</h4>
                                            <p class="text-sm text-gray-500">إنشاء نسخة احتياطية تلقائياً</p>
                                        </div>
                                        <label class="relative inline-flex items-center cursor-pointer">
                                            <input type="checkbox" name="backup_enabled" value="1" <?php echo ($current_settings['backup_enabled'] ?? '1') == '1' ? 'checked' : ''; ?> class="sr-only peer">
                                            <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300 dark:peer-focus:ring-blue-800 rounded-full peer dark:bg-gray-700 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all dark:border-gray-600 peer-checked:bg-blue-600"></div>
                                        </label>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="bg-gray-50 rounded-xl p-6">
                                <h3 class="text-lg font-semibold mb-4">النسخ اليدوي</h3>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <button type="button" onclick="createBackup('full')" class="bg-green-600 hover:bg-green-700 text-white px-6 py-3 rounded-xl transition-colors">
                                        <i class="fas fa-database mr-2"></i>
                                        نسخة احتياطية كاملة
                                    </button>
                                    
                                    <button type="button" onclick="createBackup('data')" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-3 rounded-xl transition-colors">
                                        <i class="fas fa-file-export mr-2"></i>
                                        نسخ البيانات فقط
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- حول النظام -->
                <div id="about-section" class="settings-section">
                    <div class="bg-white rounded-3xl shadow-lg border border-gray-200 p-8">
                        <div class="flex items-center gap-3 mb-8">
                            <div class="p-3 bg-blue-100 rounded-2xl">
                                <i class="fas fa-info-circle text-blue-600 text-xl"></i>
                            </div>
                            <div>
                                <h2 class="text-2xl font-bold text-gray-800">حول النظام</h2>
                                <p class="text-gray-500">معلومات النظام والدعم</p>
                            </div>
                        </div>

                        <div class="space-y-6">
                            <div class="bg-gray-50 rounded-xl p-6">
                                <h3 class="text-lg font-semibold mb-4">معلومات النظام</h3>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <div class="bg-white rounded-xl p-4">
                                        <h4 class="font-medium text-gray-800 mb-2">إصدار النظام</h4>
                                        <p class="text-2xl font-bold text-blue-600">v2.1.4</p>
                                        <p class="text-sm text-gray-500">تاريخ الإصدار: 15 يونيو 2025</p>
                                    </div>
                                    
                                    <div class="bg-white rounded-xl p-4">
                                        <h4 class="font-medium text-gray-800 mb-2">نوع الترخيص</h4>
                                        <p class="text-lg font-semibold text-green-600">Professional</p>
                                        <p class="text-sm text-gray-500">ينتهي في: 15 ديسمبر 2025</p>
                                    </div>
                                    
                                    <div class="bg-white rounded-xl p-4">
                                        <h4 class="font-medium text-gray-800 mb-2">عدد المستخدمين</h4>
                                        <p class="text-lg font-semibold text-purple-600">5 مستخدمين</p>
                                        <p class="text-sm text-gray-500">من أصل 50 مستخدم</p>
                                    </div>
                                    
                                    <div class="bg-white rounded-xl p-4">
                                        <h4 class="font-medium text-gray-800 mb-2">وقت التشغيل</h4>
                                        <p class="text-lg font-semibold text-indigo-600">45 يوم</p>
                                        <p class="text-sm text-gray-500">آخر إعادة تشغيل: 1 يونيو</p>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="bg-gray-50 rounded-xl p-6">
                                <h3 class="text-lg font-semibold mb-4">الدعم والمساعدة</h3>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <a href="#" class="bg-white rounded-xl p-4 hover:bg-gray-50 transition-colors flex items-center gap-3">
                                        <div class="w-10 h-10 bg-blue-100 rounded-lg flex items-center justify-center">
                                            <i class="fas fa-book text-blue-600"></i>
                                        </div>
                                        <div>
                                            <h4 class="font-medium text-gray-800">دليل المستخدم</h4>
                                            <p class="text-sm text-gray-500">دليل شامل لاستخدام النظام</p>
                                        </div>
                                    </a>
                                    
                                    <a href="#" class="bg-white rounded-xl p-4 hover:bg-gray-50 transition-colors flex items-center gap-3">
                                        <div class="w-10 h-10 bg-green-100 rounded-lg flex items-center justify-center">
                                            <i class="fas fa-question-circle text-green-600"></i>
                                        </div>
                                        <div>
                                            <h4 class="font-medium text-gray-800">الأسئلة الشائعة</h4>
                                            <p class="text-sm text-gray-500">إجابات للأسئلة المتكررة</p>
                                        </div>
                                    </a>
                                    
                                    <a href="#" class="bg-white rounded-xl p-4 hover:bg-gray-50 transition-colors flex items-center gap-3">
                                        <div class="w-10 h-10 bg-purple-100 rounded-lg flex items-center justify-center">
                                            <i class="fas fa-headset text-purple-600"></i>
                                        </div>
                                        <div>
                                            <h4 class="font-medium text-gray-800">تواصل مع الدعم</h4>
                                            <p class="text-sm text-gray-500">احصل على مساعدة مباشرة</p>
                                        </div>
                                    </a>
                                    
                                    <a href="#" class="bg-white rounded-xl p-4 hover:bg-gray-50 transition-colors flex items-center gap-3">
                                        <div class="w-10 h-10 bg-yellow-100 rounded-lg flex items-center justify-center">
                                            <i class="fas fa-bug text-yellow-600"></i>
                                        </div>
                                        <div>
                                            <h4 class="font-medium text-gray-800">الإبلاغ عن خطأ</h4>
                                            <p class="text-sm text-gray-500">أرسل تقرير عن مشكلة</p>
                                        </div>
                                    </a>
                                </div>
                            </div>
                            
                            <div class="bg-gray-50 rounded-xl p-6">
                                <h3 class="text-lg font-semibold mb-4">التحديثات الأخيرة</h3>
                                <div class="space-y-4">
                                    <div class="bg-white rounded-xl p-4">
                                        <div class="flex items-start gap-3">
                                            <div class="w-8 h-8 bg-green-100 rounded-lg flex items-center justify-center mt-1">
                                                <i class="fas fa-plus text-green-600 text-sm"></i>
                                            </div>
                                            <div class="flex-1">
                                                <div class="flex items-center gap-2 mb-1">
                                                    <h4 class="font-medium text-gray-800">الإصدار 2.1.4</h4>
                                                    <span class="text-xs bg-green-100 text-green-700 px-2 py-1 rounded-full">جديد</span>
                                                </div>
                                                <p class="text-sm text-gray-600 mb-2">15 يونيو 2025</p>
                                                <ul class="text-sm text-gray-600 space-y-1">
                                                    <li>• تحسينات في الأداء والسرعة</li>
                                                    <li>• إضافة ميزة النسخ الاحتياطي التلقائي</li>
                                                    <li>• إصلاح مشاكل في واجهة الهاتف المحمول</li>
                                                    <li>• تحديث الترجمة العربية</li>
                                                </ul>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="bg-white rounded-xl p-4">
                                        <div class="flex items-start gap-3">
                                            <div class="w-8 h-8 bg-blue-100 rounded-lg flex items-center justify-center mt-1">
                                                <i class="fas fa-sync text-blue-600 text-sm"></i>
                                            </div>
                                            <div class="flex-1">
                                                <h4 class="font-medium text-gray-800 mb-1">الإصدار 2.1.3</h4>
                                                <p class="text-sm text-gray-600 mb-2">1 يونيو 2025</p>
                                                <ul class="text-sm text-gray-600 space-y-1">
                                                    <li>• إضافة التقارير التفاعلية</li>
                                                    <li>• تحسين أمان البيانات</li>
                                                    <li>• واجهة إعدادات محدثة</li>
                                                </ul>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="bg-gray-50 rounded-xl p-6">
                                <h3 class="text-lg font-semibold mb-4">المعلومات القانونية</h3>
                                <div class="space-y-3">
                                    <div class="flex items-center justify-between py-2 border-b border-gray-200">
                                        <span class="text-gray-700">شروط الاستخدام</span>
                                        <a href="#" class="text-blue-600 hover:text-blue-800">عرض</a>
                                    </div>
                                    <div class="flex items-center justify-between py-2 border-b border-gray-200">
                                        <span class="text-gray-700">سياسة الخصوصية</span>
                                        <a href="#" class="text-blue-600 hover:text-blue-800">عرض</a>
                                    </div>
                                    <div class="flex items-center justify-between py-2 border-b border-gray-200">
                                        <span class="text-gray-700">اتفاقية الترخيص</span>
                                        <a href="#" class="text-blue-600 hover:text-blue-800">عرض</a>
                                    </div>
                                </div>
                                <div class="pt-4 text-center">
                                    <p class="text-sm text-gray-500">
                                        © 2025 شركة المستقبل للتقنية. جميع الحقوق محفوظة.
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- زر الحفظ العام -->
                <div class="mt-8 flex justify-end space-x-4 space-x-reverse">
                    <button type="button" onclick="resetSettings()" class="btn-secondary">
                        <i class="fas fa-undo mr-2"></i>
                        إعادة تعيين
                    </button>
                    <button type="button" onclick="saveAllSettings()" class="btn-primary">
                        <i class="fas fa-save mr-2"></i>
                        حفظ جميع الإعدادات
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
        // تهيئة الأيقونات
        document.addEventListener('DOMContentLoaded', function() {
            if (typeof lucide !== 'undefined') {
                lucide.createIcons();
            }
        });

        // إدارة التنقل بين الأقسام
        function showSection(sectionId, clickedElement = null) {
            // إخفاء جميع الأقسام
            const sections = document.querySelectorAll('.settings-section');
            sections.forEach(section => {
                section.classList.remove('active');
                section.style.display = 'none';
            });
            
            // إظهار القسم المحدد
            const targetSection = document.getElementById(sectionId);
            if (targetSection) {
                targetSection.classList.add('active');
                targetSection.style.display = 'block';
            }
            
            // تحديث القائمة الجانبية
            const navBtns = document.querySelectorAll('.settings-nav-btn');
            navBtns.forEach(btn => {
                btn.classList.remove('active', 'bg-blue-600', 'text-white');
                btn.classList.add('text-gray-700', 'hover:bg-gray-100');
            });
            
            // تفعيل العنصر المحدد
            if (clickedElement) {
                clickedElement.classList.add('active', 'bg-blue-600', 'text-white');
                clickedElement.classList.remove('text-gray-700', 'hover:bg-gray-100');
            }
        }

        // إضافة مستمعات الأحداث للقائمة الجانبية
        document.addEventListener('DOMContentLoaded', function() {
            const navBtns = document.querySelectorAll('.settings-nav-btn');
            navBtns.forEach(btn => {
                btn.addEventListener('click', function() {
                    const section = this.getAttribute('data-section');
                    showSection(section + '-section', this);
                });
            });
        });

        // اختيار الثيم
        const themeOptions = document.querySelectorAll('.theme-option');
        themeOptions.forEach(option => {
            option.addEventListener('click', function() {
                // إزالة التحديد من جميع الخيارات
                themeOptions.forEach(opt => {
                    opt.classList.remove('active', 'border-blue-500');
                    opt.classList.add('border-gray-200');
                });
                
                // تفعيل الخيار المحدد
                this.classList.add('active', 'border-blue-500');
                this.classList.remove('border-gray-200');
                
                // تطبيق الثيم فوراً
                const theme = this.getAttribute('data-theme');
                const html = document.documentElement;
                if (theme === 'light') {
                    html.classList.remove('dark');
                } else if (theme === 'dark') {
                    html.classList.add('dark');
                } else {
                    // Auto theme
                    const systemPrefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
                    html.classList.toggle('dark', systemPrefersDark);
                }
                
                // تحديث قيمة الحقل المخفي
                const themeInput = document.querySelector('input[name="theme"]');
                if (themeInput) themeInput.value = theme;
            });
        });

        // اختيار نظام الألوان
        const colorSchemes = document.querySelectorAll('.color-scheme');
        colorSchemes.forEach(scheme => {
            scheme.addEventListener('click', function() {
                // إزالة التحديد من جميع الخيارات
                colorSchemes.forEach(cs => {
                    cs.classList.remove('active', 'border-blue-500', 'border-green-500', 'border-purple-500', 'border-orange-500');
                    cs.classList.add('border-gray-200');
                });
                
                // تفعيل الخيار المحدد
                const color = this.getAttribute('data-color');
                this.classList.add('active');
                this.classList.remove('border-gray-200');
                
                // إضافة لون الحدود المناسب
                switch(color) {
                    case 'sand':
                        this.classList.add('border-yellow-500');
                        break;
                    case 'blue':
                        this.classList.add('border-blue-500');
                        break;
                    case 'green':
                        this.classList.add('border-green-500');
                        break;
                    case 'purple':
                        this.classList.add('border-purple-500');
                        break;
                }
                
                // تحديث قيمة الحقل المخفي
                const colorInput = document.querySelector('input[name="color_scheme"]');
                if (colorInput) colorInput.value = color;
            });
        });

        // إنشاء نسخة احتياطية
        function createBackup(type) {
            const buttons = document.querySelectorAll('button[onclick*="createBackup"]');
            let targetButton = null;
            
            buttons.forEach(btn => {
                if (btn.onclick && btn.onclick.toString().includes(`'${type}'`)) {
                    targetButton = btn;
                }
            });
            
            if (targetButton) {
                const originalText = targetButton.innerHTML;
                
                targetButton.disabled = true;
                targetButton.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>جاري الإنشاء...';
                
                // محاكاة عملية النسخ
                setTimeout(() => {
                    targetButton.disabled = false;
                    targetButton.innerHTML = originalText;
                    
                    if (type === 'full') {
                        showToast('تم إنشاء النسخة الاحتياطية الكاملة بنجاح', 'success');
                    } else {
                        showToast('تم إنشاء نسخة البيانات بنجاح', 'success');
                    }
                }, 3000);
            }
        }

        // إضافة مستمعات الأحداث لأزرار النسخ الاحتياطي
        document.addEventListener('DOMContentLoaded', function() {
            const backupButtons = document.querySelectorAll('button[onclick*="createBackup"]');
            backupButtons.forEach(btn => {
                btn.removeAttribute('onclick'); // إزالة onclick القديم
                
                btn.addEventListener('click', function() {
                    const btnText = this.textContent.trim();
                    if (btnText.includes('كاملة')) {
                        createBackup('full');
                    } else if (btnText.includes('البيانات')) {
                        createBackup('data');
                    }
                });
            });
        });

        // حفظ جميع الإعدادات
        async function saveAllSettings() {
            const formData = new FormData();
            formData.append('action', 'save_all_settings');
            
            // جمع جميع البيانات من النماذج
            const inputs = document.querySelectorAll('input, select, textarea');
            inputs.forEach(input => {
                if (input.type === 'checkbox') {
                    formData.append(input.name, input.checked ? '1' : '0');
                } else if (input.name && input.value !== undefined) {
                    formData.append(input.name, input.value);
                }
            });
            
            try {
                const response = await fetch(window.location.href, {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                
                if (result.success) {
                    showToast('تم حفظ جميع الإعدادات بنجاح!', 'success');
                } else {
                    showToast(result.message || 'حدث خطأ أثناء الحفظ', 'error');
                }
            } catch (error) {
                console.error('Save error:', error);
                showToast('حدث خطأ أثناء حفظ الإعدادات', 'error');
            }
        }

        // إعادة تعيين الإعدادات
        function resetSettings() {
            if (confirm('هل أنت متأكد من إعادة تعيين جميع الإعدادات للقيم الافتراضية؟')) {
                showToast('جاري إعادة تحميل الصفحة...', 'success');
                setTimeout(() => {
                    location.reload();
                }, 1000);
            }
        }

        // إضافة مستمعات الأحداث للأزرار الرئيسية
        document.addEventListener('DOMContentLoaded', function() {
            // زر حفظ جميع الإعدادات
            const saveAllBtn = document.querySelector('button[onclick*="saveAllSettings"]');
            if (saveAllBtn) {
                saveAllBtn.removeAttribute('onclick');
                saveAllBtn.addEventListener('click', saveAllSettings);
            }
            
            // زر إعادة التعيين
            const resetBtn = document.querySelector('button[onclick*="resetSettings"]');
            if (resetBtn) {
                resetBtn.removeAttribute('onclick');
                resetBtn.addEventListener('click', resetSettings);
            }
        });

        // عرض التنبيهات
        function showToast(message, type = 'success') {
            const toast = document.createElement('div');
            toast.className = `fixed top-4 right-4 p-4 rounded-lg shadow-lg z-50 transition-all transform translate-x-full`;
            
            if (type === 'success') {
                toast.classList.add('bg-green-500', 'text-white');
                toast.innerHTML = `<i class="fas fa-check-circle mr-2"></i>${message}`;
            } else {
                toast.classList.add('bg-red-500', 'text-white');
                toast.innerHTML = `<i class="fas fa-exclamation-circle mr-2"></i>${message}`;
            }
            
            document.body.appendChild(toast);
            
            // تحريك التنبيه للداخل
            setTimeout(() => {
                toast.classList.remove('translate-x-full');
            }, 100);
            
            // إزالة التنبيه تلقائياً
            setTimeout(() => {
                toast.classList.add('translate-x-full');
                setTimeout(() => {
                    if (document.body.contains(toast)) {
                        document.body.removeChild(toast);
                    }
                }, 300);
            }, 3000);
        }

        // إظهار القسم الأول عند التحميل
        document.addEventListener('DOMContentLoaded', function() {
            showSection('general-section');
        });

        // حفظ تلقائي كل 30 ثانية
        let autoSaveInterval;
        let hasUnsavedChanges = false;

        function startAutoSave() {
            autoSaveInterval = setInterval(() => {
                if (hasUnsavedChanges) {
                    console.log('Auto-saving settings...');
                    // يمكن تنفيذ الحفظ التلقائي هنا
                }
            }, 30000);
        }

        // مراقبة التغييرات
        document.addEventListener('change', function(e) {
            if (e.target.matches('input, select, textarea')) {
                hasUnsavedChanges = true;
            }
        });

        // تحذير عند مغادرة الصفحة مع تغييرات غير محفوظة
        window.addEventListener('beforeunload', function(e) {
            if (hasUnsavedChanges) {
                e.preventDefault();
                e.returnValue = 'لديك تغييرات غير محفوظة. هل تريد المغادرة؟';
            }
        });

        // بدء الحفظ التلقائي
        startAutoSave();

        // اختصارات لوحة المفاتيح
        document.addEventListener('keydown', function(e) {
            if (e.ctrlKey || e.metaKey) {
                switch (e.key) {
                    case 's':
                        e.preventDefault();
                        saveAllSettings();
                        break;
                }
            }
        });
    </script>
</body>
</html>