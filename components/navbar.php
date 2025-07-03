<?php
// components/navbar.php - شريط التنقل الموحد
?>

<nav class="bg-white border-b border-gray-200 sticky top-0 z-40">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between h-16">
            <!-- الشعار والتنقل الرئيسي -->
            <div class="flex items-center">
                <!-- الشعار -->
                <div class="flex-shrink-0 flex items-center">
                    <img class="h-8 w-auto" src="https://via.placeholder.com/120x40/A68F51/ffffff?text=كوريان+كاسيل" alt="كوريان كاسيل">
                </div>
                
                <!-- قائمة التنقل -->
                <div class="hidden md:ml-6 md:flex md:space-x-8 md:space-x-reverse">
                    <a href="dashboard.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'border-blue-500 text-gray-900' : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700'; ?> inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium transition-colors duration-200">
                        <i class="fas fa-home ml-2"></i>
                        الداشبورد
                    </a>
                    
                    <a href="projects.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'projects.php' ? 'border-blue-500 text-gray-900' : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700'; ?> inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium transition-colors duration-200">
                        <i class="fas fa-project-diagram ml-2"></i>
                        المشاريع
                    </a>
                    
                    <a href="reports.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'reports.php' ? 'border-blue-500 text-gray-900' : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700'; ?> inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium transition-colors duration-200">
                        <i class="fas fa-chart-bar ml-2"></i>
                        التقارير
                    </a>
                    
                    <a href="settings.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'settings.php' ? 'border-blue-500 text-gray-900' : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700'; ?> inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium transition-colors duration-200">
                        <i class="fas fa-cog ml-2"></i>
                        الإعدادات
                    </a>
                </div>
            </div>
            
            <!-- أزرار التحكم -->
            <div class="flex items-center space-x-4 space-x-reverse">
                <!-- زر التنبيهات -->
                <div class="relative">
                    <button type="button" class="p-2 rounded-full text-gray-400 hover:text-gray-500 hover:bg-gray-100 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors duration-200">
                        <span class="sr-only">عرض التنبيهات</span>
                        <i class="fas fa-bell h-5 w-5"></i>
                        <!-- نقطة التنبيه -->
                        <span class="absolute top-1 right-1 block h-2 w-2 rounded-full bg-red-400 ring-2 ring-white"></span>
                    </button>
                </div>
                
                <!-- زر تبديل الثيم -->
                <button type="button" id="theme-toggle" class="p-2 rounded-full text-gray-400 hover:text-gray-500 hover:bg-gray-100 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors duration-200">
                    <span class="sr-only">تبديل الثيم</span>
                    <i class="fas fa-moon h-5 w-5 dark:hidden"></i>
                    <i class="fas fa-sun h-5 w-5 hidden dark:block"></i>
                </button>
                
                <!-- قائمة المستخدم -->
                <div class="relative">
                    <div>
                        <button type="button" id="user-menu-button" class="max-w-xs bg-white flex items-center text-sm rounded-full focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors duration-200" aria-expanded="false" aria-haspopup="true">
                            <span class="sr-only">فتح قائمة المستخدم</span>
                            <div class="h-8 w-8 rounded-full bg-gradient-to-r from-blue-500 to-blue-600 flex items-center justify-center">
                                <span class="text-white text-sm font-medium">
                                    <?php echo mb_substr($user['fullname'] ?? 'مستخدم', 0, 1, 'UTF-8'); ?>
                                </span>
                            </div>
                            <span class="mr-2 text-gray-700 text-sm font-medium hidden sm:block">
                                <?php echo htmlspecialchars($user['fullname'] ?? 'مستخدم'); ?>
                            </span>
                            <i class="fas fa-chevron-down mr-1 h-3 w-3 text-gray-400"></i>
                        </button>
                    </div>
                    
                    <!-- قائمة المستخدم المنسدلة -->
                    <div id="user-menu" class="hidden origin-top-right absolute right-0 mt-2 w-48 rounded-md shadow-lg bg-white ring-1 ring-black ring-opacity-5 focus:outline-none" role="menu" aria-orientation="vertical" aria-labelledby="user-menu-button" tabindex="-1">
                        <div class="py-1" role="none">
                            <div class="px-4 py-2 text-sm text-gray-500 border-b border-gray-100">
                                <div class="font-medium text-gray-900"><?php echo htmlspecialchars($user['fullname'] ?? 'مستخدم'); ?></div>
                                <div class="text-xs"><?php echo htmlspecialchars($user['email'] ?? ''); ?></div>
                            </div>
                            
                            <a href="profile.php" class="group flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 hover:text-gray-900 transition-colors duration-200" role="menuitem">
                                <i class="fas fa-user ml-3 h-4 w-4 text-gray-400 group-hover:text-gray-500"></i>
                                الملف الشخصي
                            </a>
                            
                            <a href="settings.php" class="group flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 hover:text-gray-900 transition-colors duration-200" role="menuitem">
                                <i class="fas fa-cog ml-3 h-4 w-4 text-gray-400 group-hover:text-gray-500"></i>
                                الإعدادات
                            </a>
                            
                            <div class="border-t border-gray-100"></div>
                            
                            <a href="logout.php" class="group flex items-center px-4 py-2 text-sm text-red-700 hover:bg-red-50 hover:text-red-900 transition-colors duration-200" role="menuitem">
                                <i class="fas fa-sign-out-alt ml-3 h-4 w-4 text-red-400 group-hover:text-red-500"></i>
                                تسجيل الخروج
                            </a>
                        </div>
                    </div>
                </div>
                
                <!-- زر القائمة للهاتف المحمول -->
                <div class="md:hidden">
                    <button type="button" id="mobile-menu-button" class="inline-flex items-center justify-center p-2 rounded-md text-gray-400 hover:text-gray-500 hover:bg-gray-100 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors duration-200" aria-controls="mobile-menu" aria-expanded="false">
                        <span class="sr-only">فتح القائمة الرئيسية</span>
                        <i class="fas fa-bars h-5 w-5"></i>
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- القائمة المحمولة -->
    <div class="md:hidden hidden" id="mobile-menu">
        <div class="pt-2 pb-3 space-y-1 border-t border-gray-200">
            <a href="dashboard.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'bg-blue-50 border-blue-500 text-blue-700' : 'border-transparent text-gray-600 hover:bg-gray-50 hover:border-gray-300 hover:text-gray-800'; ?> block pl-3 pr-4 py-2 border-r-4 text-base font-medium transition-colors duration-200">
                <i class="fas fa-home ml-2"></i>
                الداشبورد
            </a>
            
            <a href="projects.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'projects.php' ? 'bg-blue-50 border-blue-500 text-blue-700' : 'border-transparent text-gray-600 hover:bg-gray-50 hover:border-gray-300 hover:text-gray-800'; ?> block pl-3 pr-4 py-2 border-r-4 text-base font-medium transition-colors duration-200">
                <i class="fas fa-project-diagram ml-2"></i>
                المشاريع
            </a>
            
            <a href="reports.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'reports.php' ? 'bg-blue-50 border-blue-500 text-blue-700' : 'border-transparent text-gray-600 hover:bg-gray-50 hover:border-gray-300 hover:text-gray-800'; ?> block pl-3 pr-4 py-2 border-r-4 text-base font-medium transition-colors duration-200">
                <i class="fas fa-chart-bar ml-2"></i>
                التقارير
            </a>
            
            <a href="settings.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'settings.php' ? 'bg-blue-50 border-blue-500 text-blue-700' : 'border-transparent text-gray-600 hover:bg-gray-50 hover:border-gray-300 hover:text-gray-800'; ?> block pl-3 pr-4 py-2 border-r-4 text-base font-medium transition-colors duration-200">
                <i class="fas fa-cog ml-2"></i>
                الإعدادات
            </a>
        </div>
    </div>
</nav>

<script>
    // إدارة قائمة المستخدم
    document.addEventListener('DOMContentLoaded', function() {
        const userMenuButton = document.getElementById('user-menu-button');
        const userMenu = document.getElementById('user-menu');
        const mobileMenuButton = document.getElementById('mobile-menu-button');
        const mobileMenu = document.getElementById('mobile-menu');
        const themeToggle = document.getElementById('theme-toggle');
        
        // قائمة المستخدم
        if (userMenuButton && userMenu) {
            userMenuButton.addEventListener('click', function(e) {
                e.stopPropagation();
                userMenu.classList.toggle('hidden');
            });
            
            // إغلاق القائمة عند النقر خارجها
            document.addEventListener('click', function(e) {
                if (!userMenuButton.contains(e.target) && !userMenu.contains(e.target)) {
                    userMenu.classList.add('hidden');
                }
            });
        }
        
        // القائمة المحمولة
        if (mobileMenuButton && mobileMenu) {
            mobileMenuButton.addEventListener('click', function() {
                mobileMenu.classList.toggle('hidden');
                const isExpanded = !mobileMenu.classList.contains('hidden');
                mobileMenuButton.setAttribute('aria-expanded', isExpanded);
            });
        }
        
        // تبديل الثيم
        if (themeToggle) {
            // التحقق من الثيم المحفوظ
            const savedTheme = localStorage.getItem('theme');
            const systemPrefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
            
            if (savedTheme === 'dark' || (!savedTheme && systemPrefersDark)) {
                document.documentElement.classList.add('dark');
            }
            
            themeToggle.addEventListener('click', function() {
                const isDark = document.documentElement.classList.toggle('dark');
                localStorage.setItem('theme', isDark ? 'dark' : 'light');
            });
        }
    });
</script><?php
// components/navbar.php - شريط التنقل الموحد
?>

<nav class="bg-white border-b border-gray-200 sticky top-0 z-40">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between h-16">
            <!-- الشعار والتنقل الرئيسي -->
            <div class="flex items-center">
                <!-- الشعار -->
                <div class="flex-shrink-0 flex items-center">
                    <img class="h-8 w-auto" src="https://via.placeholder.com/120x40/A68F51/ffffff?text=كوريان+كاسيل" alt="كوريان كاسيل">
                </div>
                
                <!-- قائمة التنقل -->
                <div class="hidden md:ml-6 md:flex md:space-x-8 md:space-x-reverse">
                    <a href="dashboard.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'border-blue-500 text-gray-900' : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700'; ?> inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium transition-colors duration-200">
                        <i class="fas fa-home ml-2"></i>
                        الداشبورد
                    </a>
                    
                    <a href="projects.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'projects.php' ? 'border-blue-500 text-gray-900' : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700'; ?> inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium transition-colors duration-200">
                        <i class="fas fa-project-diagram ml-2"></i>
                        المشاريع
                    </a>
                    
                    <a href="reports.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'reports.php' ? 'border-blue-500 text-gray-900' : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700'; ?> inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium transition-colors duration-200">
                        <i class="fas fa-chart-bar ml-2"></i>
                        التقارير
                    </a>
                    
                    <a href="settings.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'settings.php' ? 'border-blue-500 text-gray-900' : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700'; ?> inline-flex items-center px-1 pt-1 border-b-2 text-sm font-medium transition-colors duration-200">
                        <i class="fas fa-cog ml-2"></i>
                        الإعدادات
                    </a>
                </div>
            </div>
            
            <!-- أزرار التحكم -->
            <div class="flex items-center space-x-4 space-x-reverse">
                <!-- زر التنبيهات -->
                <div class="relative">
                    <button type="button" class="p-2 rounded-full text-gray-400 hover:text-gray-500 hover:bg-gray-100 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors duration-200">
                        <span class="sr-only">عرض التنبيهات</span>
                        <i class="fas fa-bell h-5 w-5"></i>
                        <!-- نقطة التنبيه -->
                        <span class="absolute top-1 right-1 block h-2 w-2 rounded-full bg-red-400 ring-2 ring-white"></span>
                    </button>
                </div>
                
                <!-- زر تبديل الثيم -->
                <button type="button" id="theme-toggle" class="p-2 rounded-full text-gray-400 hover:text-gray-500 hover:bg-gray-100 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors duration-200">
                    <span class="sr-only">تبديل الثيم</span>
                    <i class="fas fa-moon h-5 w-5 dark:hidden"></i>
                    <i class="fas fa-sun h-5 w-5 hidden dark:block"></i>
                </button>
                
                <!-- قائمة المستخدم -->
                <div class="relative">
                    <div>
                        <button type="button" id="user-menu-button" class="max-w-xs bg-white flex items-center text-sm rounded-full focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors duration-200" aria-expanded="false" aria-haspopup="true">
                            <span class="sr-only">فتح قائمة المستخدم</span>
                            <div class="h-8 w-8 rounded-full bg-gradient-to-r from-blue-500 to-blue-600 flex items-center justify-center">
                                <span class="text-white text-sm font-medium">
                                    <?php echo mb_substr($user['fullname'] ?? 'مستخدم', 0, 1, 'UTF-8'); ?>
                                </span>
                            </div>
                            <span class="mr-2 text-gray-700 text-sm font-medium hidden sm:block">
                                <?php echo htmlspecialchars($user['fullname'] ?? 'مستخدم'); ?>
                            </span>
                            <i class="fas fa-chevron-down mr-1 h-3 w-3 text-gray-400"></i>
                        </button>
                    </div>
                    
                    <!-- قائمة المستخدم المنسدلة -->
                    <div id="user-menu" class="hidden origin-top-right absolute right-0 mt-2 w-48 rounded-md shadow-lg bg-white ring-1 ring-black ring-opacity-5 focus:outline-none" role="menu" aria-orientation="vertical" aria-labelledby="user-menu-button" tabindex="-1">
                        <div class="py-1" role="none">
                            <div class="px-4 py-2 text-sm text-gray-500 border-b border-gray-100">
                                <div class="font-medium text-gray-900"><?php echo htmlspecialchars($user['fullname'] ?? 'مستخدم'); ?></div>
                                <div class="text-xs"><?php echo htmlspecialchars($user['email'] ?? ''); ?></div>
                            </div>
                            
                            <a href="profile.php" class="group flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 hover:text-gray-900 transition-colors duration-200" role="menuitem">
                                <i class="fas fa-user ml-3 h-4 w-4 text-gray-400 group-hover:text-gray-500"></i>
                                الملف الشخصي
                            </a>
                            
                            <a href="settings.php" class="group flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 hover:text-gray-900 transition-colors duration-200" role="menuitem">
                                <i class="fas fa-cog ml-3 h-4 w-4 text-gray-400 group-hover:text-gray-500"></i>
                                الإعدادات
                            </a>
                            
                            <div class="border-t border-gray-100"></div>
                            
                            <a href="logout.php" class="group flex items-center px-4 py-2 text-sm text-red-700 hover:bg-red-50 hover:text-red-900 transition-colors duration-200" role="menuitem">
                                <i class="fas fa-sign-out-alt ml-3 h-4 w-4 text-red-400 group-hover:text-red-500"></i>
                                تسجيل الخروج
                            </a>
                        </div>
                    </div>
                </div>
                
                <!-- زر القائمة للهاتف المحمول -->
                <div class="md:hidden">
                    <button type="button" id="mobile-menu-button" class="inline-flex items-center justify-center p-2 rounded-md text-gray-400 hover:text-gray-500 hover:bg-gray-100 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors duration-200" aria-controls="mobile-menu" aria-expanded="false">
                        <span class="sr-only">فتح القائمة الرئيسية</span>
                        <i class="fas fa-bars h-5 w-5"></i>
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- القائمة المحمولة -->
    <div class="md:hidden hidden" id="mobile-menu">
        <div class="pt-2 pb-3 space-y-1 border-t border-gray-200">
            <a href="dashboard.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'dashboard.php' ? 'bg-blue-50 border-blue-500 text-blue-700' : 'border-transparent text-gray-600 hover:bg-gray-50 hover:border-gray-300 hover:text-gray-800'; ?> block pl-3 pr-4 py-2 border-r-4 text-base font-medium transition-colors duration-200">
                <i class="fas fa-home ml-2"></i>
                الداشبورد
            </a>
            
            <a href="projects.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'projects.php' ? 'bg-blue-50 border-blue-500 text-blue-700' : 'border-transparent text-gray-600 hover:bg-gray-50 hover:border-gray-300 hover:text-gray-800'; ?> block pl-3 pr-4 py-2 border-r-4 text-base font-medium transition-colors duration-200">
                <i class="fas fa-project-diagram ml-2"></i>
                المشاريع
            </a>
            
            <a href="reports.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'reports.php' ? 'bg-blue-50 border-blue-500 text-blue-700' : 'border-transparent text-gray-600 hover:bg-gray-50 hover:border-gray-300 hover:text-gray-800'; ?> block pl-3 pr-4 py-2 border-r-4 text-base font-medium transition-colors duration-200">
                <i class="fas fa-chart-bar ml-2"></i>
                التقارير
            </a>
            
            <a href="settings.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'settings.php' ? 'bg-blue-50 border-blue-500 text-blue-700' : 'border-transparent text-gray-600 hover:bg-gray-50 hover:border-gray-300 hover:text-gray-800'; ?> block pl-3 pr-4 py-2 border-r-4 text-base font-medium transition-colors duration-200">
                <i class="fas fa-cog ml-2"></i>
                الإعدادات
            </a>
        </div>
    </div>
</nav>

<script>
    // إدارة قائمة المستخدم
    document.addEventListener('DOMContentLoaded', function() {
        const userMenuButton = document.getElementById('user-menu-button');
        const userMenu = document.getElementById('user-menu');
        const mobileMenuButton = document.getElementById('mobile-menu-button');
        const mobileMenu = document.getElementById('mobile-menu');
        const themeToggle = document.getElementById('theme-toggle');
        
        // قائمة المستخدم
        if (userMenuButton && userMenu) {
            userMenuButton.addEventListener('click', function(e) {
                e.stopPropagation();
                userMenu.classList.toggle('hidden');
            });
            
            // إغلاق القائمة عند النقر خارجها
            document.addEventListener('click', function(e) {
                if (!userMenuButton.contains(e.target) && !userMenu.contains(e.target)) {
                    userMenu.classList.add('hidden');
                }
            });
        }
        
        // القائمة المحمولة
        if (mobileMenuButton && mobileMenu) {
            mobileMenuButton.addEventListener('click', function() {
                mobileMenu.classList.toggle('hidden');
                const isExpanded = !mobileMenu.classList.contains('hidden');
                mobileMenuButton.setAttribute('aria-expanded', isExpanded);
            });
        }
        
        // تبديل الثيم
        if (themeToggle) {
            // التحقق من الثيم المحفوظ
            const savedTheme = localStorage.getItem('theme');
            const systemPrefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
            
            if (savedTheme === 'dark' || (!savedTheme && systemPrefersDark)) {
                document.documentElement.classList.add('dark');
            }
            
            themeToggle.addEventListener('click', function() {
                const isDark = document.documentElement.classList.toggle('dark');
                localStorage.setItem('theme', isDark ? 'dark' : 'light');
            });
        }
    });
</script>