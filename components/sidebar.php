<?php
// ملف: components/sidebar.php
// القائمة الجانبية المحدثة مع رابط التقارير

if (!isset($_SESSION['user_id'])) {
    return;
}

$current_page = basename($_SERVER['PHP_SELF']);
?>

<div class="fixed inset-y-0 right-0 z-50 w-64 bg-white dark:bg-gray-900 border-l border-gray-200 dark:border-gray-700 transform translate-x-full lg:translate-x-0 transition-transform duration-300 ease-in-out" id="sidebar">
    
    <!-- Logo -->
    <div class="flex items-center justify-center h-16 px-4 bg-gradient-to-r from-primary-sand to-primary-light">
        <h1 class="text-xl font-bold text-white">كوريان كاسيل</h1>
    </div>
    
    <!-- Navigation -->
    <nav class="flex-1 px-4 py-6 space-y-2">
        
        <!-- الرئيسية -->
        <a href="dashboard.php" class="nav-item <?= $current_page === 'dashboard.php' ? 'active' : '' ?> flex items-center gap-3 px-4 py-3 text-gray-700 dark:text-gray-300 rounded-xl hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors">
            <i data-lucide="home" class="w-5 h-5"></i>
            <span>الرئيسية</span>
        </a>
        
        <!-- المهام -->
        <a href="tasks.php" class="nav-item <?= $current_page === 'tasks.php' ? 'active' : '' ?> flex items-center gap-3 px-4 py-3 text-gray-700 dark:text-gray-300 rounded-xl hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors">
            <i data-lucide="check-square" class="w-5 h-5"></i>
            <span>إدارة المهام</span>
        </a>
        
        <!-- المشاريع -->
        <a href="projects.php" class="nav-item <?= $current_page === 'projects.php' ? 'active' : '' ?> flex items-center gap-3 px-4 py-3 text-gray-700 dark:text-gray-300 rounded-xl hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors">
            <i data-lucide="folder" class="w-5 h-5"></i>
            <span>المشاريع</span>
        </a>
        
        <!-- التقارير -->
        <a href="reports.php" class="nav-item <?= $current_page === 'reports.php' ? 'active' : '' ?> flex items-center gap-3 px-4 py-3 text-gray-700 dark:text-gray-300 rounded-xl hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors">
            <i data-lucide="bar-chart-3" class="w-5 h-5"></i>
            <span>التقارير والإحصائيات</span>
        </a>
        
        <!-- المستخدمين -->
        <?php if ($current_user['role'] === 'admin'): ?>
        <a href="users.php" class="nav-item <?= $current_page === 'users.php' ? 'active' : '' ?> flex items-center gap-3 px-4 py-3 text-gray-700 dark:text-gray-300 rounded-xl hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors">
            <i data-lucide="users" class="w-5 h-5"></i>
            <span>إدارة المستخدمين</span>
        </a>
        <?php endif; ?>
        
        <!-- الإعدادات -->
        <a href="settings.php" class="nav-item <?= $current_page === 'settings.php' ? 'active' : '' ?> flex items-center gap-3 px-4 py-3 text-gray-700 dark:text-gray-300 rounded-xl hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors">
            <i data-lucide="settings" class="w-5 h-5"></i>
            <span>الإعدادات</span>
        </a>
        
        <!-- خط فاصل -->
        <div class="border-t border-gray-200 dark:border-gray-700 my-4"></div>
        
        <!-- الملف الشخصي -->
        <a href="profile.php" class="nav-item <?= $current_page === 'profile.php' ? 'active' : '' ?> flex items-center gap-3 px-4 py-3 text-gray-700 dark:text-gray-300 rounded-xl hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors">
            <i data-lucide="user" class="w-5 h-5"></i>
            <span>الملف الشخصي</span>
        </a>
        
        <!-- تسجيل الخروج -->
        <a href="logout.php" class="nav-item flex items-center gap-3 px-4 py-3 text-red-600 dark:text-red-400 rounded-xl hover:bg-red-50 dark:hover:bg-red-900/20 transition-colors">
            <i data-lucide="log-out" class="w-5 h-5"></i>
            <span>تسجيل الخروج</span>
        </a>
    </nav>
    
    <!-- معلومات المستخدم -->
    <div class="p-4 border-t border-gray-200 dark:border-gray-700">
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-full bg-gradient-to-br from-primary-sand to-primary-light flex items-center justify-center text-white font-bold">
                <?= mb_substr($current_user['fullname'], 0, 1) ?>
            </div>
            <div class="flex-1">
                <p class="text-sm font-semibold text-gray-900 dark:text-white"><?= htmlspecialchars($current_user['fullname']) ?></p>
                <p class="text-xs text-gray-500 dark:text-gray-400"><?= htmlspecialchars($current_user['role'] ?? 'عضو') ?></p>
            </div>
        </div>
    </div>
</div>

<!-- Overlay للموبايل -->
<div class="fixed inset-0 bg-black bg-opacity-50 z-40 lg:hidden hidden" id="sidebar-overlay"></div>

<style>
.nav-item.active {
    background: linear-gradient(135deg, #A68F51, #FADC9B);
    color: white;
}

.nav-item.active i {
    color: white;
}
</style>

<script>
// تبديل القائمة الجانبية في الموبايل
function toggleSidebar() {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebar-overlay');
    
    sidebar.classList.toggle('translate-x-full');
    overlay.classList.toggle('hidden');
}

// إغلاق القائمة عند الضغط على الخلفية
document.getElementById('sidebar-overlay')?.addEventListener('click', toggleSidebar);

// تحديث الأيقونات
if (window.lucide) {
    lucide.createIcons();
}
</script>