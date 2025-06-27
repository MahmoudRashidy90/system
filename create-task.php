<?php
session_start();
if (!isset($_SESSION['user'])) {
    header("Location: login.php");
    exit;
}
require_once __DIR__ . '/api/config.php';

$user = $_SESSION['user'];

// جلب كل المستخدمين لإسناد المهام (لو المستخدم Admin أو Manager)
$assignableUsers = [];
if (in_array($user['role'], ['admin', 'factory_manager'])) {
    $stmt = $conn->query("SELECT id, full_name FROM users WHERE status = 'active'");
    $assignableUsers = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>إضافة مهمة جديدة - كوريان كاسيل</title>
    <link rel="stylesheet" href="assets/main.css">
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 font-arabic">
    <div class="container mx-auto px-4 py-8">
        <h1 class="text-2xl font-bold mb-6">📌 إضافة مهمة جديدة</h1>

        <form action="api/tasks.php" method="POST" class="space-y-6 bg-white p-6 rounded-xl shadow">
            <input type="hidden" name="action" value="create">

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">عنوان المهمة</label>
                <input type="text" name="title" required class="w-full border border-gray-300 rounded-lg px-4 py-2">
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">وصف المهمة</label>
                <textarea name="description" rows="4" class="w-full border border-gray-300 rounded-lg px-4 py-2"></textarea>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">تاريخ التسليم</label>
                <input type="date" name="due_date" required class="w-full border border-gray-300 rounded-lg px-4 py-2">
            </div>

            <?php if (!empty($assignableUsers)): ?>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">إسناد إلى</label>
                <select name="assigned_to" class="w-full border border-gray-300 rounded-lg px-4 py-2">
                    <?php foreach ($assignableUsers as $u): ?>
                        <option value="<?= $u['id'] ?>"><?= htmlspecialchars($u['full_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php else: ?>
                <input type="hidden" name="assigned_to" value="<?= $user['id'] ?>">
            <?php endif; ?>

            <button type="submit" class="bg-yellow-600 hover:bg-yellow-700 text-white px-6 py-2 rounded-lg font-semibold">
                حفظ المهمة
            </button>
        </form>
    </div>
</body>
</html>
