# AI Package Delivery System

A modern, full-stack web application for managing package deliveries with role-based access control.

## Features

### Admin Dashboard
- Package management (view, filter, assign)
- Driver management
- Package assignment interface
- Analytics dashboard
- Real-time statistics

### Driver Dashboard
- View assigned packages
- Update delivery status (Picked Up, In Transit, Delivered)
- Real-time notifications
- Package details view

## Technology Stack

- **Frontend:** HTML5, CSS3, JavaScript (ES6+)
- **Backend:** PHP 7.4+
- **Database:** MySQL 5.7+
- **Server:** Apache (XAMPP)

## Installation

### Prerequisites
- XAMPP (or similar PHP/MySQL environment)
- Web browser (Chrome, Firefox, Edge)

### Setup Steps

1. **Clone/Copy the project** to your XAMPP htdocs directory:
   ```
   C:\xampp\htdocs\ai delivery\
   ```

2. **Create the database:**
   - Open phpMyAdmin (http://localhost/phpmyadmin)
   - Import the SQL file: `database/schema.sql`
   - Or run the SQL commands manually

3. **Configure database connection:**
   - Edit `config/database.php`
   - Update database credentials if needed:
     ```php
     define('DB_HOST', 'localhost');
     define('DB_USER', 'root');
     define('DB_PASS', '');
     define('DB_NAME', 'ai_delivery_system');
     ```

4. **Start XAMPP:**
   - Start Apache
   - Start MySQL

5. **Access the application:**
   - Open browser: `http://localhost/ai delivery/`
   - Or: `http://localhost/ai%20delivery/`

## Login Credentials

### Admin
- **Username:** `admin`
- **Password:** `admin123`

### Driver
- **Username:** `driver` (or `driver1`, `driver2`, `driver3`, `driver4`, `driver5`)
- **Password:** `driver123`

## Project Structure

```
ai delivery/
├── api/                    # PHP API endpoints
│   ├── auth.php           # Authentication
│   ├── packages.php       # Package management
│   ├── drivers.php        # Driver management
│   ├── assignments.php    # Package assignments
│   └── notifications.php  # Notifications
├── config/
│   └── database.php       # Database configuration
├── css/
│   ├── style.css          # Shared styles
│   ├── admin.css          # Admin theme
│   └── driver.css         # Driver theme
├── database/
│   └── schema.sql         # Database schema
├── js/
│   ├── app.js             # Shared JavaScript
│   ├── admin.js           # Admin dashboard logic
│   └── driver.js          # Driver dashboard logic
├── index.html             # Landing page
├── admin-login.html       # Admin login
├── admin-dashboard.html   # Admin dashboard
├── driver-login.html      # Driver login
└── driver-dashboard.html  # Driver dashboard
```

## API Endpoints

### Authentication
- `POST api/auth.php?action=login` - User login
- `POST api/auth.php?action=logout` - User logout
- `GET api/auth.php?action=check` - Check session

### Packages
- `GET api/packages.php?action=list` - List packages
- `GET api/packages.php?action=get&id={id}` - Get package
- `GET api/packages.php?action=stats` - Get statistics
- `POST api/packages.php?action=create` - Create package (Admin)
- `PUT api/packages.php?action=update&id={id}` - Update package
- `DELETE api/packages.php?action=delete&id={id}` - Delete package (Admin)

### Drivers
- `GET api/drivers.php?action=list` - List drivers (Admin)
- `GET api/drivers.php?action=get` - Get driver info
- `GET api/drivers.php?action=available` - Get available drivers
- `PUT api/drivers.php?action=update&id={id}` - Update driver

### Assignments
- `POST api/assignments.php?action=assign` - Assign package to driver
- `DELETE api/assignments.php?action=unassign&id={id}` - Unassign package

### Notifications
- `GET api/notifications.php?action=list` - List notifications
- `GET api/notifications.php?action=unread` - Get unread count
- `PUT api/notifications.php?action=read` - Mark as read

## Features

- ✅ Role-based access control (Admin/Driver)
- ✅ Session management
- ✅ Real-time package tracking
- ✅ Driver assignment system
- ✅ Status updates (Pending → Assigned → Picked Up → In Transit → Delivered)
- ✅ Notification system
- ✅ Responsive design
- ✅ Modern UI/UX

## Development

### Adding New Features

1. **Database changes:** Update `database/schema.sql`
2. **API endpoints:** Add to `api/` directory
3. **Frontend:** Update JavaScript files in `js/`

### Security Notes

- Passwords are stored with password_hash() in production
- Session-based authentication
- SQL injection protection with prepared statements
- XSS protection with proper escaping

## License

This project is created for educational purposes.

## Support

For issues or questions, please check the code comments or database schema.

