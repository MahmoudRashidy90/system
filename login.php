<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
session_start();

// إذا كان المستخدم مسجل الدخول بالفعل، وجهه للداشبورد
if (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit;
}

require_once 'api/config.php'; // الاتصال بقاعدة البيانات

$error_message = '';
$success_message = '';

// التحقق من رسائل النظام
if (isset($_GET['logout']) && $_GET['logout'] === 'success') {
    $success_message = 'تم تسجيل الخروج بنجاح';
}

if (isset($_GET['session_expired'])) {
    $error_message = 'انتهت صلاحية الجلسة، يرجى تسجيل الدخول مرة أخرى';
}

if (isset($_GET['login_required'])) {
    $error_message = 'يرجى تسجيل الدخول للوصول لهذه الصفحة';
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $remember = isset($_POST['remember']);

    if (!empty($email) && !empty($password)) {
        try {
            // البحث عن المستخدم - تم تغيير $conn إلى $pdo
            $stmt = $pdo->prepare("SELECT * FROM users WHERE email = :email");
            $stmt->bindParam(':email', $email);
            $stmt->execute();
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user && password_verify($password, $user['password'])) {
                // تسجيل الدخول ناجح - حفظ بيانات المستخدم في الجلسة
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_name'] = $user['fullname'] ?? $user['name'] ?? $user['username'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['user_role'] = $user['role'];
                $_SESSION['user_email'] = $user['email'];
                $_SESSION['last_activity'] = time();

                // إذا اختار المستخدم "تذكرني"
                if ($remember) {
                    $token = bin2hex(random_bytes(32));
                    setcookie('remember_token', $token, time() + (30 * 24 * 60 * 60), '/'); // 30 يوم
                    
                    // حفظ التوكن في قاعدة البيانات - تم تغيير $conn إلى $pdo
                    try {
                        $stmt_token = $pdo->prepare("UPDATE users SET remember_token = :token WHERE id = :id");
                        $stmt_token->bindParam(':token', $token);
                        $stmt_token->bindParam(':id', $user['id']);
                        $stmt_token->execute();
                    } catch (PDOException $e) {
                        // إذا فشل حفظ التوكن، تابع بدونه
                        error_log("خطأ في حفظ remember token: " . $e->getMessage());
                    }
                }

                // تحديث آخر تسجيل دخول - تم تغيير $conn إلى $pdo
                try {
                    $stmt_update = $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = :id");
                    $stmt_update->bindParam(':id', $user['id']);
                    $stmt_update->execute();
                } catch (PDOException $e) {
                    // إذا فشل التحديث، تابع
                    error_log("خطأ في تحديث last_login: " . $e->getMessage());
                }

                // التوجيه للداشبورد
                header("Location: dashboard.php");
                exit;
            } else {
                $error_message = "خطأ في البريد الإلكتروني أو كلمة المرور";
            }
        } catch (PDOException $e) {
            $error_message = "حدث خطأ في النظام، يرجى المحاولة مرة أخرى";
            error_log("خطأ في تسجيل الدخول: " . $e->getMessage());
        }
    } else {
        $error_message = "يرجى ملء كل الحقول";
    }
}

// التحقق من Remember Me عند تحميل الصفحة - تم تغيير $conn إلى $pdo
if (isset($_COOKIE['remember_token']) && empty($_SESSION['user_id'])) {
    try {
        $token = $_COOKIE['remember_token'];
        $stmt = $pdo->prepare("SELECT * FROM users WHERE remember_token = :token");
        $stmt->bindParam(':token', $token);
        $stmt->execute();
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user) {
            // تسجيل دخول تلقائي
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_name'] = $user['fullname'] ?? $user['name'] ?? $user['username'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['user_role'] = $user['role'];
            $_SESSION['user_email'] = $user['email'];
            $_SESSION['last_activity'] = time();
            
            header("Location: dashboard.php");
            exit;
        }
    } catch (PDOException $e) {
        // حذف الكوكي إذا كان غير صالح
        setcookie('remember_token', '', time() - 3600, '/');
    }
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تسجيل الدخول - كوريان كاسيل</title>
    <meta name="description" content="تسجيل الدخول لنظام إدارة كوريان كاسيل">
    <meta name="robots" content="noindex, nofollow">
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;600;700;900&display=swap" rel="stylesheet">
    
    <!-- Lucide Icons -->
    <script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>
    
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Cairo', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
            position: relative;
            overflow-x: hidden;
        }

        /* Background Animation */
        body::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><defs><pattern id="grain" width="100" height="100" patternUnits="userSpaceOnUse"><circle cx="25" cy="25" r="1" fill="rgba(255,255,255,0.1)"/><circle cx="75" cy="75" r="1" fill="rgba(255,255,255,0.05)"/><circle cx="50" cy="10" r="0.5" fill="rgba(255,255,255,0.1)"/><circle cx="20" cy="80" r="0.5" fill="rgba(255,255,255,0.05)"/></pattern></defs><rect width="100" height="100" fill="url(%23grain)"/></svg>');
            pointer-events: none;
        }

        .login-container {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 24px;
            box-shadow: 0 25px 50px rgba(0, 0, 0, 0.15);
            padding: 40px;
            width: 100%;
            max-width: 420px;
            text-align: center;
            border: 1px solid rgba(255, 255, 255, 0.2);
            position: relative;
            animation: fadeInUp 0.6s ease-out;
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .logo {
            background: linear-gradient(135deg, #f3b229 0%, #dca424 100%);
            width: 90px;
            height: 90px;
            border-radius: 22px;
            margin: 0 auto 25px;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 15px 35px rgba(243, 178, 41, 0.4);
            overflow: hidden;
            position: relative;
            animation: logoFloat 3s ease-in-out infinite;
        }

        @keyframes logoFloat {
            0%, 100% { transform: translateY(0px); }
            50% { transform: translateY(-5px); }
        }

        .logo::after {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: linear-gradient(45deg, transparent, rgba(255,255,255,0.2), transparent);
            animation: shimmer 2s infinite;
        }

        @keyframes shimmer {
            0% { transform: translateX(-100%) translateY(-100%) rotate(45deg); }
            100% { transform: translateX(100%) translateY(100%) rotate(45deg); }
        }

        .logo img {
            width: 100%;
            height: 100%;
            object-fit: contain;
            border-radius: 18px;
            transition: transform 0.3s ease;
        }

        .logo img:hover {
            transform: scale(1.05);
        }

        h1 {
            color: #2d3748;
            font-size: 32px;
            font-weight: 800;
            margin-bottom: 10px;
            background: linear-gradient(135deg, #2d3748, #4a5568);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .subtitle {
            color: #718096;
            font-size: 16px;
            margin-bottom: 35px;
            font-weight: 500;
        }

        .form-group {
            position: relative;
            margin-bottom: 22px;
            text-align: right;
        }

        .form-group i {
            position: absolute;
            right: 18px;
            top: 50%;
            transform: translateY(-50%);
            color: #a0aec0;
            z-index: 2;
            transition: color 0.3s ease;
        }

        input[type="email"],
        input[type="password"] {
            width: 100%;
            padding: 18px 50px 18px 18px;
            border: 2px solid #e2e8f0;
            border-radius: 14px;
            font-size: 16px;
            font-family: 'Cairo', sans-serif;
            transition: all 0.3s ease;
            background: #f7fafc;
            font-weight: 500;
            color: #2d3748;
        }

        input[type="password"] {
            font-size: 20px;
            letter-spacing: 3px;
        }

        input[type="email"]:focus,
        input[type="password"]:focus {
            outline: none;
            border-color: #f3b229;
            background: #fff;
            box-shadow: 0 0 0 4px rgba(243, 178, 41, 0.1);
            transform: translateY(-1px);
        }

        input[type="email"]::placeholder,
        input[type="password"]::placeholder {
            color: #a0aec0;
            font-size: 16px;
            letter-spacing: normal;
        }

        input[type="email"]:focus + i,
        input[type="password"]:focus + i {
            color: #f3b229;
        }

        .remember-forgot {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 28px;
            font-size: 14px;
        }

        .remember-forgot label {
            display: flex;
            align-items: center;
            gap: 10px;
            cursor: pointer;
            color: #4a5568;
            font-weight: 500;
            transition: color 0.3s ease;
        }

        .remember-forgot label:hover {
            color: #2d3748;
        }

        .remember-forgot input[type="checkbox"] {
            width: 18px;
            height: 18px;
            margin: 0;
            accent-color: #f3b229;
        }

        .remember-forgot a {
            color: #f3b229;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .remember-forgot a:hover {
            color: #dca424;
            text-decoration: underline;
        }

        .login-btn {
            background: linear-gradient(135deg, #f3b229 0%, #dca424 100%);
            color: #fff;
            padding: 18px;
            border: none;
            border-radius: 14px;
            width: 100%;
            font-size: 17px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s ease;
            font-family: 'Cairo', sans-serif;
            box-shadow: 0 6px 20px rgba(243, 178, 41, 0.4);
            position: relative;
            overflow: hidden;
        }

        .login-btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            transition: left 0.5s;
        }

        .login-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 30px rgba(243, 178, 41, 0.5);
        }

        .login-btn:hover::before {
            left: 100%;
        }

        .login-btn:active {
            transform: translateY(-1px);
        }

        .footer {
            margin-top: 35px;
            font-size: 13px;
            color: #a0aec0;
            font-weight: 500;
        }

        .alert {
            padding: 14px 18px;
            border-radius: 12px;
            margin-bottom: 22px;
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 14px;
            font-weight: 600;
            animation: slideInDown 0.4s ease-out;
        }

        @keyframes slideInDown {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .alert-error {
            background: linear-gradient(135deg, #fed7d7, #feb2b2);
            border: 1px solid #f56565;
            color: #c53030;
        }

        .alert-success {
            background: linear-gradient(135deg, #c6f6d5, #9ae6b4);
            border: 1px solid #48bb78;
            color: #2f855a;
        }

        .loading {
            display: none;
            align-items: center;
            justify-content: center;
            gap: 12px;
            margin-top: 15px;
            color: #718096;
            font-weight: 600;
        }

        .spinner {
            width: 22px;
            height: 22px;
            border: 3px solid #e2e8f0;
            border-top: 3px solid #f3b229;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        /* Mobile Responsiveness */
        @media (max-width: 480px) {
            .login-container {
                padding: 35px 25px;
                margin: 15px;
                border-radius: 20px;
            }
            
            h1 {
                font-size: 28px;
            }

            .logo {
                width: 80px;
                height: 80px;
            }

            input[type="email"],
            input[type="password"] {
                padding: 16px 45px 16px 16px;
                font-size: 16px;
            }

            .login-btn {
                padding: 16px;
                font-size: 16px;
            }
        }

        /* Dark theme support */
        @media (prefers-color-scheme: dark) {
            .login-container {
                background: rgba(26, 32, 44, 0.95);
                border: 1px solid rgba(255, 255, 255, 0.1);
            }

            h1 {
                color: #f7fafc;
                background: linear-gradient(135deg, #f7fafc, #e2e8f0);
                -webkit-background-clip: text;
                -webkit-text-fill-color: transparent;
                background-clip: text;
            }

            .subtitle {
                color: #a0aec0;
            }

            input[type="email"],
            input[type="password"] {
                background: #2d3748;
                border-color: #4a5568;
                color: #f7fafc;
            }

            input[type="email"]::placeholder,
            input[type="password"]::placeholder {
                color: #a0aec0;
            }

            input[type="password"] {
                color: #f7fafc !important;
            }

            .remember-forgot label {
                color: #e2e8f0;
            }

            .footer {
                color: #718096;
            }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="logo">
            <!-- لوجو كوريان كاسيل الحقيقي -->
            <img src="assets/images/logo Finish.png" alt="كوريان كاسيل">
        </div>
        
        <h1>كوريان كاسيل</h1>
        <p class="subtitle">نظام إدارة الفريق الذكي</p>

        <!-- رسائل النجاح والخطأ -->
        <?php if (!empty($success_message)): ?>
            <div class="alert alert-success">
                <i data-lucide="check-circle" style="width: 20px; height: 20px;"></i>
                <?= htmlspecialchars($success_message) ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($error_message)): ?>
            <div class="alert alert-error">
                <i data-lucide="alert-circle" style="width: 20px; height: 20px;"></i>
                <?= htmlspecialchars($error_message) ?>
            </div>
        <?php endif; ?>

        <form id="loginForm" action="login.php" method="POST">
            <div class="form-group">
                <input 
                    type="email" 
                    name="email" 
                    placeholder="البريد الإلكتروني" 
                    required 
                    value="<?= isset($_POST['email']) ? htmlspecialchars($_POST['email']) : '' ?>"
                    autocomplete="email"
                >
                <i data-lucide="mail"></i>
            </div>

            <div class="form-group">
                <input 
                    type="password" 
                    name="password" 
                    placeholder="كلمة المرور" 
                    required
                    autocomplete="current-password"
                >
                <i data-lucide="lock"></i>
            </div>

            <div class="remember-forgot">
                <label>
                    <input type="checkbox" name="remember" <?= isset($_POST['remember']) ? 'checked' : '' ?>>
                    تذكرني
                </label>
                <a href="forgot-password.php">نسيت كلمة المرور؟</a>
            </div>

            <button type="submit" class="login-btn">
                تسجيل الدخول
            </button>

            <div class="loading" id="loading">
                <div class="spinner"></div>
                <span>جاري تسجيل الدخول...</span>
            </div>
        </form>

        <p class="footer">© 2025 كوريان كاسيل. جميع الحقوق محفوظة.</p>
    </div>

    <script>
        // تهيئة الأيقونات
        document.addEventListener('DOMContentLoaded', function() {
            lucide.createIcons();
            
            // معالجة خطأ تحميل اللوجو
            const logoImg = document.querySelector('.logo img');
            if (logoImg) {
                logoImg.addEventListener('error', function() {
                    this.style.display = 'none';
                    this.parentElement.innerHTML = '<span style="color: white; font-size: 28px; font-weight: 900;">CC</span>';
                });
            }
            
            // إضافة تأثيرات تفاعلية للحقول
            const inputs = document.querySelectorAll('input[type="email"], input[type="password"]');
            inputs.forEach(input => {
                input.addEventListener('focus', function() {
                    this.parentElement.style.transform = 'scale(1.02)';
                    this.parentElement.style.transition = 'transform 0.2s ease';
                });
                
                input.addEventListener('blur', function() {
                    this.parentElement.style.transform = 'scale(1)';
                });

                // تأثير الكتابة
                input.addEventListener('input', function() {
                    if (this.value.length > 0) {
                        this.style.borderColor = '#48bb78';
                        const icon = this.nextElementSibling;
                        if (icon) icon.style.color = '#48bb78';
                    } else {
                        this.style.borderColor = '#e2e8f0';
                        const icon = this.nextElementSibling;
                        if (icon) icon.style.color = '#a0aec0';
                    }
                });
            });
        });

        // معالجة إرسال النموذج
        document.getElementById('loginForm').addEventListener('submit', function(e) {
            const submitBtn = this.querySelector('.login-btn');
            const loading = document.getElementById('loading');
            
            // التحقق من صحة البيانات
            const email = this.querySelector('input[name="email"]').value;
            const password = this.querySelector('input[name="password"]').value;
            
            if (!email || !password) {
                e.preventDefault();
                return;
            }
            
            // إظهار حالة التحميل
            submitBtn.style.display = 'none';
            loading.style.display = 'flex';
            
            // السماح للنموذج بالإرسال
            return true;
        });

        // تحسين تجربة المستخدم - التركيز على الحقل الأول
        window.addEventListener('load', function() {
            const emailInput = document.querySelector('input[name="email"]');
            if (emailInput && !emailInput.value) {
                setTimeout(() => {
                    emailInput.focus();
                }, 500);
            }
        });

        // إضافة keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' && (e.ctrlKey || e.metaKey)) {
                document.getElementById('loginForm').submit();
            }
        });

        // تأثير الماوس على الكونتينر
        const container = document.querySelector('.login-container');
        document.addEventListener('mousemove', function(e) {
            const rect = container.getBoundingClientRect();
            const x = e.clientX - rect.left;
            const y = e.clientY - rect.top;
            
            if (x >= 0 && x <= rect.width && y >= 0 && y <= rect.height) {
                const xPercent = (x / rect.width - 0.5) * 2;
                const yPercent = (y / rect.height - 0.5) * 2;
                
                container.style.transform = `perspective(1000px) rotateY(${xPercent * 5}deg) rotateX(${-yPercent * 5}deg)`;
            } else {
                container.style.transform = 'perspective(1000px) rotateY(0deg) rotateX(0deg)';
            }
        });
    </script>
</body>
</html>