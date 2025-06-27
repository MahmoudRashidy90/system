<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

// بدء الجلسة
session_start();

// تسجيل عملية تسجيل الخروج في قاعدة البيانات (اختياري)
if (isset($_SESSION['user_id'])) {
    try {
        require_once 'api/config.php';
        
        $user_id = $_SESSION['user_id'];
        $logout_time = date('Y-m-d H:i:s');
        
        // تحديث آخر نشاط للمستخدم
        $stmt = $conn->prepare("UPDATE users SET last_login = :logout_time WHERE id = :user_id");
        $stmt->bindParam(':logout_time', $logout_time);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->execute();
        
        // تسجيل عملية تسجيل الخروج في سجل النشاطات (إذا كان لديك جدول للسجلات)
        /*
        $stmt_log = $conn->prepare("INSERT INTO activity_logs (user_id, action, created_at) VALUES (:user_id, 'logout', :created_at)");
        $stmt_log->bindParam(':user_id', $user_id);
        $stmt_log->bindParam(':created_at', $logout_time);
        $stmt_log->execute();
        */
        
    } catch (PDOException $e) {
        // في حالة حدوث خطأ في قاعدة البيانات، تجاهل الخطأ وقم بتسجيل الخروج
        error_log("خطأ في تسجيل الخروج: " . $e->getMessage());
    }
}

// إتلاف جميع متغيرات الجلسة
$_SESSION = array();

// حذف ملف تعريف الارتباط للجلسة إذا كان موجود
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// إنهاء الجلسة
session_destroy();

// حذف أي ملفات تعريف ارتباط خاصة بالنظام
setcookie('remember_user', '', time() - 3600, '/');
setcookie('user_preferences', '', time() - 3600, '/');

// التوجيه إلى صفحة تسجيل الدخول مع رسالة نجاح
header("Location: login.php?logout=success");
exit();
?>