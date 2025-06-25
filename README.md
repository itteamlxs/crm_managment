# 🏢 Sistema CRM - Gestión de Relaciones con Clientes

Un sistema completo de gestión de relaciones con clientes (CRM) desarrollado en PHP con MySQL, diseñado para empresas que necesitan administrar clientes, productos, cotizaciones y generar reportes detallados.

![PHP](https://img.shields.io/badge/PHP-7.4%2B-blue)
![MySQL](https://img.shields.io/badge/MySQL-5.7%2B-orange)
![TailwindCSS](https://img.shields.io/badge/TailwindCSS-2.2-green)
![License](https://img.shields.io/badge/License-MIT-yellow)

## 📋 Características Principales

### 🎯 Funcionalidades Core
- **Gestión de Clientes**: Registro completo con datos de contacto y actividad
- **Catálogo de Productos**: Inventario con categorías, precios e impuestos
- **Sistema de Cotizaciones**: Creación, edición y seguimiento de cotizaciones
- **Generación de PDFs**: Cotizaciones profesionales con logo de empresa
- **Envío de Emails**: Sistema SMTP integrado para envío de cotizaciones
- **Dashboard Interactivo**: Estadísticas en tiempo real y gráficos
- **Generador de Reportes**: Exportación personalizada en CSV
- **Gestión de Usuarios**: Roles de administrador y vendedor

### 🔧 Características Técnicas
- **Arquitectura MVC**: Separación clara de responsabilidades
- **Seguridad Avanzada**: CSRF protection, SQL injection prevention, XSS protection
- **Responsive Design**: Interfaz adaptable a dispositivos móviles
- **Multi-idioma**: Soporte para Español e Inglés
- **Configuración Flexible**: Panel de administración completo
- **APIs RESTful**: Preparado para integraciones futuras

## 🎨 Capturas de Pantalla

### Dashboard Principal
![Dashboard](docs/images/dashboard.png)

### Gestión de Cotizaciones
![Cotizaciones](docs/images/quotes.png)

### Panel de Configuración
![Configuración](docs/images/settings.png)

## 📋 Requisitos del Sistema

### Requisitos Mínimos
- **PHP**: 7.4 o superior
- **MySQL**: 5.7 o superior (o MariaDB 10.2+)
- **Apache/Nginx**: Servidor web con mod_rewrite
- **Extensiones PHP Requeridas**:
  - `pdo_mysql`
  - `openssl`
  - `mbstring`
  - `fileinfo`
  - `gd` (para manejo de imágenes)
  - `curl` (para notificaciones)

### Requisitos Recomendados
- **PHP**: 8.0 o superior
- **MySQL**: 8.0 o superior
- **Memoria**: 256MB o más
- **Espacio en Disco**: 500MB mínimo
- **SSL**: Certificado SSL para producción

## 🚀 Instalación

### Opción 1: Instalación en Producción (Servidor Linux)

#### 1. Preparar el Servidor

```bash
# Ubuntu/Debian
sudo apt update
sudo apt install apache2 mysql-server php8.0 php8.0-mysql php8.0-mbstring php8.0-xml php8.0-curl php8.0-gd php8.0-zip unzip curl

# CentOS/RHEL
sudo yum install httpd mysql-server php php-mysql php-mbstring php-xml php-curl php-gd php-zip unzip curl

# Habilitar servicios
sudo systemctl enable apache2 mysql
sudo systemctl start apache2 mysql
```

#### 2. Configurar Base de Datos

```bash
# Asegurar MySQL
sudo mysql_secure_installation

# Crear base de datos y usuario
sudo mysql -u root -p
```

```sql
CREATE DATABASE crm_managment CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'crm_user'@'localhost' IDENTIFIED BY 'tu_password_segura';
GRANT ALL PRIVILEGES ON crm_managment.* TO 'crm_user'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

#### 3. Descargar e Instalar el Sistema

```bash
# Ir al directorio web
cd /var/www/html

# Descargar el sistema (ajustar URL según tu repositorio)
sudo git clone https://github.com/tu-usuario/crm-sistema.git crm
cd crm

# Configurar permisos
sudo chown -R www-data:www-data /var/www/html/crm
sudo chmod -R 755 /var/www/html/crm
sudo chmod -R 777 /var/www/html/crm/uploads
sudo chmod -R 777 /var/www/html/crm/logs
sudo chmod -R 777 /var/www/html/crm/temp
```

#### 4. Instalar Dependencias

```bash
# Instalar Composer (si no está instalado)
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer

# Instalar dependencias PHP
composer install --no-dev --optimize-autoloader
```

#### 5. Configurar el Sistema

```bash
# Copiar archivo de configuración
cp .env.example .env

# Editar configuración
nano .env
```

```env
# Configuración de base de datos
DB_HOST=localhost
DB_NAME=crm_managment
DB_USER=crm_user
DB_PASS=tu_password_segura
DB_CHARSET=utf8mb4
```

#### 6. Configurar Apache

```bash
# Crear virtual host
sudo nano /etc/apache2/sites-available/crm.conf
```

```apache
<VirtualHost *:80>
    ServerName tu-dominio.com
    DocumentRoot /var/www/html/crm
    
    <Directory /var/www/html/crm>
        AllowOverride All
        Require all granted
    </Directory>
    
    ErrorLog ${APACHE_LOG_DIR}/crm_error.log
    CustomLog ${APACHE_LOG_DIR}/crm_access.log combined
</VirtualHost>
```

```bash
# Habilitar el sitio y mod_rewrite
sudo a2ensite crm.conf
sudo a2enmod rewrite
sudo systemctl reload apache2
```

#### 7. Configurar SSL (Recomendado)

```bash
# Instalar Certbot para Let's Encrypt
sudo apt install certbot python3-certbot-apache

# Obtener certificado SSL
sudo certbot --apache -d tu-dominio.com

# Auto-renovación
sudo crontab -e
# Agregar: 0 12 * * * /usr/bin/certbot renew --quiet
```

#### 8. Importar Base de Datos

```bash
# Importar esquema inicial
mysql -u crm_user -p crm_managment < db/schema.sql
```

### Opción 2: Instalación en XAMPP (Desarrollo)

#### 1. Descargar XAMPP
- Descargar desde [https://www.apachefriends.org](https://www.apachefriends.org)
- Instalar con Apache, MySQL y PHP

#### 2. Configurar el Proyecto

```bash
# Ir al directorio htdocs
cd C:\xampp\htdocs  # Windows
cd /Applications/XAMPP/htdocs  # macOS
cd /opt/lampp/htdocs  # Linux

# Clonar o extraer el proyecto
git clone https://github.com/tu-usuario/crm-sistema.git crm
cd crm
```

#### 3. Configurar Base de Datos

- Abrir phpMyAdmin: `http://localhost/phpmyadmin`
- Crear base de datos: `crm_managment`
- Importar: `db/schema.sql`

#### 4. Configurar .env

```env
DB_HOST=localhost
DB_NAME=crm_managment
DB_USER=root
DB_PASS=
DB_CHARSET=utf8mb4
```

#### 5. Instalar Dependencias

```bash
# Si tienes Composer instalado
composer install
```

#### 6. Acceder al Sistema
- URL: `http://localhost/crm`

### Opción 3: Docker (Avanzado)

#### 1. Crear docker-compose.yml

```yaml
version: '3.8'

services:
  web:
    image: php:8.0-apache
    ports:
      - "80:80"
    volumes:
      - .:/var/www/html
    depends_on:
      - db
    environment:
      - DB_HOST=db
      - DB_NAME=crm_managment
      - DB_USER=crm_user
      - DB_PASS=secure_password

  db:
    image: mysql:8.0
    environment:
      MYSQL_ROOT_PASSWORD: root_password
      MYSQL_DATABASE: crm_managment
      MYSQL_USER: crm_user
      MYSQL_PASSWORD: secure_password
    volumes:
      - db_data:/var/lib/mysql
      - ./db/schema.sql:/docker-entrypoint-initdb.d/schema.sql

volumes:
  db_data:
```

#### 2. Ejecutar

```bash
docker-compose up -d
```

## ⚙️ Configuración Inicial

### 1. Primer Acceso

- **URL**: `http://tu-dominio.com` o `http://localhost/crm`
- **Usuario por defecto**: `admin`
- **Contraseña por defecto**: `admin123`

> ⚠️ **IMPORTANTE**: Cambiar las credenciales por defecto inmediatamente

### 2. Configuración Básica

1. **Información de la Empresa**
   - Ir a `Configuración > General`
   - Completar datos de la empresa
   - Subir logo corporativo

2. **Configuración Regional**
   - Seleccionar zona horaria
   - Configurar moneda
   - Establecer formato de fecha

3. **Configuración de Email SMTP**
   - Ir a `Configuración > Correo`
   - Configurar servidor SMTP
   - Probar conexión

4. **Crear Usuarios**
   - Ir a `Usuarios`
   - Crear vendedores y administradores

## 📧 Configuración de Email

### Gmail

```
Servidor SMTP: smtp.gmail.com
Puerto: 587
Seguridad: TLS
Usuario: tu-email@gmail.com
Contraseña: Tu App Password (no la contraseña normal)
```

### Outlook/Hotmail

```
Servidor SMTP: smtp-mail.outlook.com
Puerto: 587
Seguridad: STARTTLS
Usuario: tu-email@outlook.com
Contraseña: Tu contraseña
```

### Otros Proveedores

| Proveedor | SMTP | Puerto | Seguridad |
|-----------|------|---------|-----------|
| Yahoo | smtp.mail.yahoo.com | 587 | TLS |
| Zoho | smtp.zoho.com | 587 | TLS |
| Mailgun | smtp.mailgun.org | 587 | TLS |

## 🔐 Seguridad

### Medidas Implementadas

- **Validación CSRF**: Tokens en todos los formularios
- **Prepared Statements**: Prevención de SQL Injection
- **Escape de Salida**: Prevención de XSS
- **Gestión de Sesiones**: Timeout automático
- **Hash de Contraseñas**: Bcrypt con salt
- **Validación de Entrada**: Sanitización de datos

### Recomendaciones Adicionales

```apache
# .htaccess para mayor seguridad
RewriteEngine On

# Ocultar archivos sensibles
<Files ".env">
    Order allow,deny
    Deny from all
</Files>

<Files "composer.json">
    Order allow,deny
    Deny from all
</Files>

# Headers de seguridad
Header always set X-Content-Type-Options nosniff
Header always set X-Frame-Options DENY
Header always set X-XSS-Protection "1; mode=block"
```

## 📊 Uso del Sistema

### Flujo de Trabajo Típico

1. **Configurar Categorías de Productos**
   - Crear categorías lógicas
   - Ejemplo: Electrónicos, Servicios, Oficina

2. **Agregar Productos**
   - Definir precios base
   - Configurar impuestos por producto
   - Gestionar stock (opcional)

3. **Registrar Clientes**
   - Datos completos de contacto
   - Información de facturación

4. **Crear Cotizaciones**
   - Seleccionar cliente y productos
   - Aplicar descuentos
   - Generar PDF professional

5. **Seguimiento**
   - Enviar por email
   - Marcar estados (enviada, aprobada, etc.)
   - Generar reportes

### Roles de Usuario

#### Administrador
- Acceso completo al sistema
- Gestión de usuarios
- Configuración del sistema
- Todos los módulos

#### Vendedor
- Gestión de clientes
- Creación de cotizaciones
- Gestión de productos
- Dashboard y reportes

## 📈 Generación de Reportes

### Tipos de Reportes Disponibles

1. **Cotizaciones**
   - Estados y seguimiento
   - Análisis temporal
   - Conversión de ventas

2. **Clientes**
   - Actividad y valor
   - Segmentación
   - Historial de compras

3. **Productos**
   - Performance de ventas
   - Rotación de inventario
   - Análisis de categorías

4. **Ventas**
   - Ingresos por período
   - Tendencias y proyecciones
   - Análisis de descuentos

### Exportación

- Formato CSV personalizable
- Selección de campos específicos
- Filtros por fecha y estado
- Límites configurables

## 🛠️ Mantenimiento

### Backup de Base de Datos

```bash
# Backup automático diario
#!/bin/bash
DATE=$(date +%Y%m%d_%H%M%S)
mysqldump -u crm_user -p crm_managment > backup_crm_$DATE.sql
gzip backup_crm_$DATE.sql

# Agregar a crontab
# 0 2 * * * /path/to/backup-script.sh
```

### Logs del Sistema

```bash
# Ubicación de logs
/var/www/html/crm/logs/

# Rotar logs automáticamente
/etc/logrotate.d/crm:
/var/www/html/crm/logs/*.log {
    weekly
    rotate 52
    compress
    delaycompress
    missingok
    notifempty
    create 644 www-data www-data
}
```

### Actualizaciones

```bash
# Backup antes de actualizar
cp -r /var/www/html/crm /var/www/html/crm_backup_$(date +%Y%m%d)

# Descargar nueva versión
git pull origin main

# Ejecutar migraciones si las hay
php scripts/migrate.php

# Limpiar cache si existe
rm -rf temp/cache/*
```

## 🐛 Solución de Problemas

### Problemas Comunes

#### Error: "No se puede conectar a la base de datos"
```bash
# Verificar servicio MySQL
sudo systemctl status mysql

# Verificar credenciales en .env
cat .env

# Probar conexión manual
mysql -u crm_user -p crm_managment
```

#### Error: "Permisos insuficientes"
```bash
# Corregir permisos
sudo chown -R www-data:www-data /var/www/html/crm
sudo chmod -R 755 /var/www/html/crm
sudo chmod -R 777 /var/www/html/crm/uploads
sudo chmod -R 777 /var/www/html/crm/logs
```

#### Error: "No se pueden enviar emails"
- Verificar configuración SMTP
- Probar conexión desde panel de administración
- Revisar logs de error
- Verificar firewall del servidor

#### Error 500 - Internal Server Error
```bash
# Revisar logs de Apache
sudo tail -f /var/log/apache2/error.log

# Revisar logs de PHP
sudo tail -f /var/log/php/error.log

# Verificar .htaccess
cat /var/www/html/crm/.htaccess
```

### Debugging

```php
// Habilitar debug en development
// En config/constants.php
define('DEBUG_MODE', true);
define('DISPLAY_ERRORS', true);
```

## 📞 Soporte

### Documentación
- [Wiki del Proyecto](wiki/)
- [API Documentation](docs/api/)
- [FAQ](docs/faq.md)

### Reportar Bugs
- [Issues en GitHub](https://github.com/tu-usuario/crm-sistema/issues)
- Email: soporte@tu-empresa.com

### Contribuir
1. Fork del repositorio
2. Crear feature branch
3. Commit cambios
4. Push al branch
5. Crear Pull Request

## 📄 Licencia

Este proyecto está bajo la Licencia MIT. Ver [LICENSE](LICENSE) para más detalles.

## 🤝 Contribuidores

- **Desarrollador Principal**: Tu Nombre
- **Colaboradores**: [Lista de colaboradores](CONTRIBUTORS.md)

## 🗓️ Roadmap

### Versión 2.0 (Próxima)
- [ ] API RESTful completa
- [ ] Integración con WhatsApp Business
- [ ] Dashboard avanzado con más gráficos
- [ ] Sistema de plantillas de email
- [ ] Módulo de inventario avanzado
- [ ] Integración con sistemas de pago
- [ ] App móvil (React Native)

### Versión 2.1
- [ ] Módulo de facturación
- [ ] Integración contable
- [ ] Sistema de tickets de soporte
- [ ] Automatización de seguimiento
- [ ] Integración con redes sociales

## 📸 Galería

| Dashboard | Cotizaciones | Productos |
|-----------|-------------|-----------|
| ![Dashboard](docs/images/dashboard-thumb.png) | ![Quotes](docs/images/quotes-thumb.png) | ![Products](docs/images/products-thumb.png) |

| Clientes | Reportes | Configuración |
|----------|----------|---------------|
| ![Clients](docs/images/clients-thumb.png) | ![Reports](docs/images/reports-thumb.png) | ![Settings](docs/images/settings-thumb.png) |

---

**¿Necesitas ayuda?** 📧 Contacta con nosotros en soporte@tu-empresa.com

**¡Dale una estrella!** ⭐ Si este proyecto te es útil, no olvides darle una estrella en GitHub.
