# Installation Guide

## Quick Setup (5 minutes)

### Step 1: Start XAMPP
1. Open XAMPP Control Panel
2. Start **Apache**
3. Start **MySQL**

### Step 2: Setup Database

**Option A: Using setup.php (Recommended)**
1. Open browser: `http://localhost/ai delivery/setup.php`
2. Wait for "Setup Complete!" message
3. Done!

**Option B: Using phpMyAdmin**
1. Open phpMyAdmin: `http://localhost/phpmyadmin`
2. Click "Import" tab
3. Choose file: `database/schema.sql`
4. Click "Go"
5. Done!

**Option C: Manual SQL**
1. Open phpMyAdmin
2. Create new database: `ai_delivery_system`
3. Copy and paste contents of `database/schema.sql`
4. Execute

### Step 3: Configure (if needed)

Edit `config/database.php` only if your MySQL credentials are different:
```php
define('DB_HOST', 'localhost');  // Usually 'localhost'
define('DB_USER', 'root');        // Your MySQL username
define('DB_PASS', '');           // Your MySQL password
define('DB_NAME', 'ai_delivery_system');
```

### Step 4: Access Application

Open browser: `http://localhost/ai delivery/`

## Login Credentials

### Admin
- Username: `admin`
- Password: `admin123`

### Driver
- Username: `driver` (or `driver1`, `driver2`, `driver3`, `driver4`, `driver5`)
- Password: `driver123`

## Troubleshooting

### "Database connection failed"
- Check if MySQL is running in XAMPP
- Verify database credentials in `config/database.php`
- Make sure database `ai_delivery_system` exists

### "404 Not Found" or "Page not found"
- Check Apache is running
- Verify file path: `C:\xampp\htdocs\ai delivery\`
- Try: `http://localhost/ai%20delivery/` (with %20 for space)

### "Session not working"
- Check PHP session directory is writable
- Clear browser cookies
- Check `.htaccess` file exists

### "API calls failing"
- Check browser console for errors
- Verify `api/` folder exists
- Check PHP error logs in XAMPP

## File Permissions

Make sure these folders are writable (if needed):
- PHP session directory
- No special permissions needed for this application

## Testing

1. Login as Admin
2. View packages
3. Assign a package to a driver
4. Login as Driver
5. View assigned packages
6. Update package status

## Support

Check `README.md` for more details.

