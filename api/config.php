<?php
// ملف: api/config.php
// إعداد الاتصال بقاعدة البيانات

// إعدادات قاعدة البيانات - تحديث حسب الإعدادات الصحيحة
$db_host = 'localhost';
$db_name = 'coriymfy_corian_system';

// جرب هذه الاحتمالات لاسم المستخدم:
$db_user = 'coriymfy_system_user';  // أو جرب 'coriymfy_root' أو 'coriymfy'
$db_pass = 'gotohell500811';  // ضع كلمة المرور الصحيحة هنا

try {
    // إنشاء اتصال PDO مع معالجة أفضل للأخطاء
    $pdo = new PDO(
        "mysql:host=$db_host;dbname=$db_name;charset=utf8mb4",
        $db_user,
        $db_pass,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
        ]
    );
    
    // تعيين المنطقة الزمنية
    $pdo->exec("SET time_zone = '+00:00'");
    
    // إضافة متغير للتوافق مع الملفات القديمة التي تستخدم $conn
    $conn = $pdo;
    
    // تعيين متغيرات إضافية للتوافق الكامل
    $connection = $pdo;  // بعض الملفات قد تستخدم $connection
    $db = $pdo;          // بعض الملفات قد تستخدم $db
    $database = $pdo;    // بعض الملفات قد تستخدم $database
    
    // تسجيل نجاح الاتصال (اختياري للتطوير)
    // error_log("Database connected successfully - All variables set: \$pdo, \$conn, \$connection, \$db, \$database");
    
} catch (PDOException $e) {
    // تسجيل تفاصيل الخطأ لأغراض التطوير
    error_log("Database connection failed: " . $e->getMessage());
    error_log("Connection details: Host=$db_host, DB=$db_name, User=$db_user");
    
    // تعيين متغيرات فارغة لتجنب الأخطاء
    $pdo = null;
    $conn = null;
    $connection = null;
    $db = null;
    $database = null;
    
    // عرض رسالة خطأ مفيدة
    if (isset($_SERVER['REQUEST_METHOD'])) {
        // تحديد نوع الرد حسب طلب المستخدم
        $is_ajax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
                   strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';
        $is_json = strpos($_SERVER['REQUEST_URI'], '.json') !== false || 
                   (isset($_SERVER['CONTENT_TYPE']) && strpos($_SERVER['CONTENT_TYPE'], 'application/json') !== false);
        
        if ($is_ajax || $is_json) {
            // رد JSON للطلبات AJAX
            header('Content-Type: application/json');
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => 'خطأ في الاتصال بقاعدة البيانات',
                'details' => 'يرجى التحقق من إعدادات قاعدة البيانات في ملف config.php',
                'connection_details' => [
                    'host' => $db_host,
                    'database' => $db_name,
                    'user' => $db_user,
                    'error' => $e->getMessage()
                ]
            ]);
        } else {
            // رد HTML للطلبات العادية
            header('Content-Type: text/html; charset=utf-8');
            echo '<!DOCTYPE html>
            <html lang="ar" dir="rtl">
            <head>
                <meta charset="UTF-8">
                <meta name="viewport" content="width=device-width, initial-scale=1.0">
                <title>خطأ في الاتصال - كوريان كاسيل</title>
                <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;600;700&display=swap" rel="stylesheet">
                <style>
                    body { 
                        font-family: "Cairo", sans-serif; 
                        text-align: center; 
                        margin: 0; 
                        padding: 50px 20px;
                        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                        min-height: 100vh;
                        display: flex;
                        align-items: center;
                        justify-content: center;
                    }
                    .error-container { 
                        color: #721c24; 
                        background: rgba(255, 255, 255, 0.95);
                        backdrop-filter: blur(10px);
                        padding: 40px; 
                        border-radius: 20px; 
                        box-shadow: 0 20px 40px rgba(0,0,0,0.15);
                        max-width: 500px;
                        width: 100%;
                        border: 1px solid rgba(255, 255, 255, 0.2);
                    }
                    .error-icon {
                        font-size: 4rem;
                        color: #dc3545;
                        margin-bottom: 20px;
                        animation: pulse 2s infinite;
                    }
                    @keyframes pulse {
                        0% { transform: scale(1); }
                        50% { transform: scale(1.05); }
                        100% { transform: scale(1); }
                    }
                    h2 { 
                        color: #dc3545; 
                        margin-bottom: 15px;
                        font-weight: 700;
                    }
                    p { 
                        color: #6c757d; 
                        line-height: 1.6;
                        margin-bottom: 20px;
                    }
                    .btn {
                        display: inline-block;
                        padding: 12px 24px;
                        background: linear-gradient(135deg, #f3b229 0%, #dca424 100%);
                        color: white;
                        text-decoration: none;
                        border-radius: 10px;
                        margin-top: 20px;
                        transition: all 0.3s;
                        font-weight: 600;
                        box-shadow: 0 4px 15px rgba(243, 178, 41, 0.4);
                    }
                    .btn:hover { 
                        transform: translateY(-2px);
                        box-shadow: 0 6px 20px rgba(243, 178, 41, 0.6);
                    }
                    .details {
                        background: #f8f9fa;
                        padding: 15px;
                        border-radius: 10px;
                        margin-top: 20px;
                        font-size: 0.9rem;
                        color: #495057;
                        text-align: right;
                    }
                    .error-code {
                        background: #fff3cd;
                        border: 1px solid #ffeaa7;
                        color: #856404;
                        padding: 10px;
                        border-radius: 8px;
                        margin-top: 15px;
                        font-family: monospace;
                        font-size: 0.8rem;
                        word-break: break-all;
                    }
                </style>
            </head>
            <body>
                <div class="error-container">
                    <div class="error-icon">⚠️</div>
                    <h2>خطأ في الاتصال بقاعدة البيانات</h2>
                    <p>عذراً، لا يمكن الاتصال بقاعدة البيانات في الوقت الحالي.</p>
                    
                    <div class="details">
                        <strong>للمطورين:</strong><br>
                        • تحقق من إعدادات قاعدة البيانات في ملف config.php<br>
                        • تأكد من صحة اسم المستخدم وكلمة المرور<br>
                        • تأكد من أن قاعدة البيانات موجودة ومتاحة<br>
                        • تحقق من أن الخادم يدعم PDO MySQL
                    </div>
                    
                    <div class="error-code">
                        <strong>تفاصيل الخطأ:</strong><br>
                        الخادم: ' . htmlspecialchars($db_host) . '<br>
                        قاعدة البيانات: ' . htmlspecialchars($db_name) . '<br>
                        المستخدم: ' . htmlspecialchars($db_user) . '<br>
                        الخطأ: ' . htmlspecialchars($e->getMessage()) . '
                    </div>
                    
                    <a href="login.php" class="btn">العودة لتسجيل الدخول</a>
                </div>
            </body>
            </html>';
        }
    }
    exit;
}

// التحقق من نجاح الاتصال
if (!$pdo) {
    error_log("PDO connection is null after successful try block");
    exit('Unexpected database error');
}

// إضافة دالة مساعدة للتحقق من الاتصال
function testDatabaseConnection() {
    global $pdo;
    try {
        $stmt = $pdo->query('SELECT 1');
        return true;
    } catch (PDOException $e) {
        error_log("Database test failed: " . $e->getMessage());
        return false;
    }
}

// دالة مساعدة للحصول على معلومات الاتصال
function getDatabaseInfo() {
    global $db_host, $db_name, $db_user;
    return [
        'host' => $db_host,
        'database' => $db_name,
        'user' => $db_user,
        'charset' => 'utf8mb4',
        'status' => testDatabaseConnection() ? 'connected' : 'disconnected'
    ];
}

// دالة مساعدة للتحقق من وجود الجداول المطلوبة
function checkRequiredTables() {
    global $pdo;
    $required_tables = ['users', 'tasks', 'projects', 'project_members', 'system_settings'];
    $existing_tables = [];
    $missing_tables = [];
    
    try {
        $stmt = $pdo->query("SHOW TABLES");
        $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        foreach ($required_tables as $table) {
            if (in_array($table, $tables)) {
                $existing_tables[] = $table;
            } else {
                $missing_tables[] = $table;
            }
        }
        
        return [
            'existing' => $existing_tables,
            'missing' => $missing_tables,
            'all_exist' => empty($missing_tables)
        ];
    } catch (PDOException $e) {
        error_log("Error checking tables: " . $e->getMessage());
        return [
            'existing' => [],
            'missing' => $required_tables,
            'all_exist' => false,
            'error' => $e->getMessage()
        ];
    }
}

// متغيرات إضافية للتوافق مع أنماط مختلفة من الكود
$dbh = $pdo;        // Database Handler
$mysqli = null;     // لا نستخدم MySQLi لكن نعرف المتغير لتجنب الأخطاء
$link = $pdo;       // Link (اسم قديم شائع)

// إعداد معالجة الأخطاء العامة
set_error_handler(function($severity, $message, $file, $line) {
    if (strpos($message, 'PDO') !== false || strpos($message, 'database') !== false) {
        error_log("Database Error: $message in $file on line $line");
    }
    return false; // السماح للمعالج الافتراضي بالعمل
});

// إعداد معالجة الاستثناءات العامة
set_exception_handler(function($exception) {
    if ($exception instanceof PDOException) {
        error_log("Uncaught PDO Exception: " . $exception->getMessage());
        
        // إظهار صفحة خطأ مهذبة للمستخدمين
        if (!headers_sent()) {
            header('HTTP/1.1 500 Internal Server Error');
            header('Content-Type: text/html; charset=utf-8');
            
            echo '<!DOCTYPE html>
            <html lang="ar" dir="rtl">
            <head>
                <meta charset="UTF-8">
                <title>خطأ في النظام</title>
                <style>
                    body { font-family: Arial; text-align: center; padding: 50px; }
                    .error { color: #d32f2f; }
                </style>
            </head>
            <body>
                <h1 class="error">خطأ في النظام</h1>
                <p>نعتذر، حدث خطأ مؤقت. يرجى المحاولة مرة أخرى لاحقاً.</p>
                <a href="login.php">العودة لتسجيل الدخول</a>
            </body>
            </html>';
        }
        exit;
    }
});

/*
 * ملاحظات للمطورين:
 * 
 * 1. يمكن استخدام أي من هذه المتغيرات للاتصال بقاعدة البيانات:
 *    - $pdo (الأساسي والموصى به)
 *    - $conn (للتوافق مع الكود القديم)
 *    - $connection
 *    - $db
 *    - $database
 *    - $dbh
 *    - $link
 * 
 * 2. جميع المتغيرات تشير لنفس كائن PDO
 * 
 * 3. استخدم testDatabaseConnection() للتحقق من حالة الاتصال
 * 
 * 4. استخدم getDatabaseInfo() للحصول على معلومات الاتصال
 * 
 * 5. استخدم checkRequiredTables() للتحقق من وجود الجداول المطلوبة
 */
?>