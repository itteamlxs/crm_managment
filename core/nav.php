<?php
// Componente de navegación principal - nav.php
// Guardar en: /core/nav.php

// Verificar si hay sesión activa
$isLoggedIn = false;
$userRole = null;
$userName = '';

try {
    if (class_exists('Session')) {
        $session = new Session();
        $isLoggedIn = $session->isLoggedIn();
        
        if ($isLoggedIn) {
            $userRole = $session->hasRole(ROLE_ADMIN) ? 'admin' : 'seller';
            $userName = $session->getUserName() ?? 'Usuario';
        }
    }
} catch (Exception $e) {
    error_log("Error checking session in nav: " . $e->getMessage());
}

// Determinar página activa
$currentPage = basename($_SERVER['PHP_SELF'], '.php');
$currentDir = basename(dirname($_SERVER['PHP_SELF']));

function isActive($page, $dir = null) {
    global $currentPage, $currentDir;
    
    if ($dir) {
        return $currentDir === $dir ? 'bg-blue-700 text-white' : 'text-blue-100 hover:bg-blue-700 hover:text-white';
    }
    
    return $currentPage === $page ? 'bg-blue-700 text-white' : 'text-blue-100 hover:bg-blue-700 hover:text-white';
}

// Solo mostrar navegación si hay sesión activa
if (!$isLoggedIn) {
    return;
}
?>

<!-- Navegación Principal -->
<nav class="bg-blue-600 shadow-lg">
    <div class="container mx-auto px-4">
        <div class="flex justify-between items-center py-4">
            
            <!-- Logo y Título -->
            <div class="flex items-center space-x-4">
                <a href="<?php echo BASE_URL; ?>/modules/dashboard/dashboardView.php" 
                   class="flex items-center space-x-2 text-white font-bold text-xl">
                    <div class="w-8 h-8 bg-white rounded-lg flex items-center justify-center">
                        <span class="text-blue-600 font-bold">#</span>
                    </div>
                    <span>CRM Sistema</span>
                </a>
                
                <!-- Navegación Desktop -->
                <div class="hidden md:flex space-x-1 ml-8">
                    
                    <!-- Dashboard -->
                    <a href="<?php echo BASE_URL; ?>/modules/dashboard/dashboardView.php" 
                       class="px-3 py-2 rounded-md text-sm font-medium transition-colors <?php echo isActive('dashboardView'); ?>">
                        Dashboard
                    </a>
                    
                    <!-- Clientes -->
                    <a href="<?php echo BASE_URL; ?>/modules/clients/clientList.php" 
                       class="px-3 py-2 rounded-md text-sm font-medium transition-colors <?php echo isActive('', 'clients'); ?>">
                        Clientes
                    </a>
                    
                    <!-- Productos -->
                    <a href="<?php echo BASE_URL; ?>/modules/products/productList.php" 
                       class="px-3 py-2 rounded-md text-sm font-medium transition-colors <?php echo isActive('', 'products'); ?>">
                        Productos
                    </a>
                    
                    <!-- Cotizaciones -->
                    <a href="<?php echo BASE_URL; ?>/modules/quotes/quoteList.php" 
                       class="px-3 py-2 rounded-md text-sm font-medium transition-colors <?php echo isActive('', 'quotes'); ?>">
                        Cotizaciones
                    </a>
                    
                    <!-- Reportes -->
                    <a href="<?php echo BASE_URL; ?>/modules/reports/reportView.php" 
                       class="px-3 py-2 rounded-md text-sm font-medium transition-colors <?php echo isActive('', 'reports'); ?>">
                        Reportes
                    </a>
                    
                    <!-- Configuración (Solo Admin) -->
                    <?php if ($userRole === 'admin'): ?>
                        <a href="<?php echo BASE_URL; ?>/modules/settings/settingsView.php" 
                           class="px-3 py-2 rounded-md text-sm font-medium transition-colors <?php echo isActive('', 'settings'); ?>">
                            Config
                        </a>
                        
                        <!-- Usuarios (Solo Admin) -->
                        <a href="<?php echo BASE_URL; ?>/modules/users/userManagement.php" 
                           class="px-3 py-2 rounded-md text-sm font-medium transition-colors <?php echo isActive('userManagement'); ?>">
                            Usuarios
                        </a>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Usuario y Logout -->
            <div class="flex items-center space-x-4">
                <!-- Info del Usuario -->
                <div class="hidden md:flex items-center space-x-2 text-white">
                    <span class="text-sm">
                        <?php echo Security::escape($userName); ?>
                        <span class="text-blue-200 text-xs">
                            (<?php echo $userRole === 'admin' ? 'Admin' : 'Vendedor'; ?>)
                        </span>
                    </span>
                </div>
                
                <!-- Botón de Logout -->
                <a href="<?php echo BASE_URL; ?>/logout.php" 
                   onclick="return confirm('¿Está seguro de cerrar sesión?')"
                   class="bg-blue-700 hover:bg-blue-800 text-white px-3 py-2 rounded-md text-sm font-medium transition-colors">
                    Salir
                </a>
                
                <!-- Botón Menú Móvil -->
                <button id="mobile-menu-button" 
                        class="md:hidden text-white hover:text-blue-200 p-2">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
                    </svg>
                </button>
            </div>
        </div>
        
        <!-- Menú Móvil -->
        <div id="mobile-menu" class="md:hidden hidden pb-4">
            <div class="space-y-1">
                
                <!-- Usuario Info Móvil -->
                <div class="px-3 py-2 text-white border-b border-blue-500 mb-2">
                    <div class="font-medium"><?php echo Security::escape($userName); ?></div>
                    <div class="text-blue-200 text-sm">
                        <?php echo $userRole === 'admin' ? 'Administrador' : 'Vendedor'; ?>
                    </div>
                </div>
                
                <!-- Enlaces Móvil -->
                <a href="<?php echo BASE_URL; ?>/modules/dashboard/dashboardView.php" 
                   class="block px-3 py-2 rounded-md text-white hover:bg-blue-700">
                    Dashboard
                </a>
                
                <a href="<?php echo BASE_URL; ?>/modules/clients/clientList.php" 
                   class="block px-3 py-2 rounded-md text-white hover:bg-blue-700">
                    Clientes
                </a>
                
                <a href="<?php echo BASE_URL; ?>/modules/products/productList.php" 
                   class="block px-3 py-2 rounded-md text-white hover:bg-blue-700">
                    Productos
                </a>
                
                <a href="<?php echo BASE_URL; ?>/modules/quotes/quoteList.php" 
                   class="block px-3 py-2 rounded-md text-white hover:bg-blue-700">
                    Cotizaciones
                </a>
                
                <a href="<?php echo BASE_URL; ?>/modules/reports/reportView.php" 
                   class="block px-3 py-2 rounded-md text-white hover:bg-blue-700">
                    Reportes
                </a>
                
                <?php if ($userRole === 'admin'): ?>
                    <a href="<?php echo BASE_URL; ?>/modules/settings/settingsView.php" 
                       class="block px-3 py-2 rounded-md text-white hover:bg-blue-700">
                        Configuración
                    </a>
                    
                    <a href="<?php echo BASE_URL; ?>/modules/users/userManagement.php" 
                       class="block px-3 py-2 rounded-md text-white hover:bg-blue-700">
                        Usuarios
                    </a>
                <?php endif; ?>
                
                <!-- Logout Móvil -->
                <a href="<?php echo BASE_URL; ?>/logout.php" 
                   onclick="return confirm('¿Está seguro de cerrar sesión?')"
                   class="block px-3 py-2 rounded-md bg-blue-700 text-white hover:bg-blue-800 mt-4">
                    Cerrar Sesión
                </a>
            </div>
        </div>
    </div>
</nav>

<!-- JavaScript para Menú Móvil -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    const mobileMenuButton = document.getElementById('mobile-menu-button');
    const mobileMenu = document.getElementById('mobile-menu');
    
    if (mobileMenuButton && mobileMenu) {
        mobileMenuButton.addEventListener('click', function() {
            mobileMenu.classList.toggle('hidden');
        });
        
        // Cerrar menú al hacer clic fuera
        document.addEventListener('click', function(event) {
            if (!mobileMenuButton.contains(event.target) && !mobileMenu.contains(event.target)) {
                mobileMenu.classList.add('hidden');
            }
        });
        
        // Cerrar menú al cambiar tamaño de pantalla a desktop
        window.addEventListener('resize', function() {
            if (window.innerWidth >= 768) {
                mobileMenu.classList.add('hidden');
            }
        });
    }
});
</script>