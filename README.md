# Medicine Reminder Web Application

A professional, secure, and feature-rich web application for tracking medication schedules and maintaining health adherence.

![Medicine Reminder](https://img.shields.io/badge/Medicine-Reminder-teal)
![PHP](https://img.shields.io/badge/PHP-8.0+-blue)
![MySQL](https://img.shields.io/badge/MySQL-5.7+-orange)
![Tailwind CSS](https://img.shields.io/badge/Tailwind%20CSS-3.0+-cyan)

## Features

### Core Functionality
- **User Authentication**: Secure registration and login with session management
- **Medication Management**: Add, edit, and track medications with detailed information
- **Schedule Management**: Create flexible dosing schedules (daily, weekly, custom intervals)
- **Smart Reminders**: Get notified of upcoming and overdue doses
- **Adherence Tracking**: Visual timeline and statistics of medication history
- **Notification Center**: Centralized alerts for all medication-related events

### Design & UX
- **Serene Healthcare Theme**: Clean, medical-grade color palette
- **Responsive Design**: Works seamlessly on desktop, tablet, and mobile
- **Modern UI**: Built with Tailwind CSS for a polished, professional look
- **Accessibility**: High-contrast typography and intuitive navigation
- **Dark Mode Support**: Toggle between light and dark themes

### Security Features
- **Password Hashing**: Bcrypt encryption for secure password storage
- **CSRF Protection**: All forms protected against cross-site request forgery
- **SQL Injection Prevention**: PDO prepared statements throughout
- **Session Security**: Secure session configuration with HTTP-only cookies
- **Input Sanitization**: All user inputs properly validated and sanitized

## Screenshots

### Dashboard
- Overview of medications and statistics
- Upcoming doses with quick actions
- Weekly adherence chart

### Medication List
- Grid view of all medications
- Color-coded medication icons
- Quick edit and delete actions

### Schedule
- Day and week calendar views
- Visual timeline of doses
- Easy schedule creation

### Alerts
- Overdue and upcoming doses
- Notification center
- Unread message indicators

### History
- Timeline view of all doses
- Filter by status (taken, missed, skipped)
- Adherence trend chart

### Settings
- Profile management
- Notification preferences
- Theme selection
- Password change

## Technical Stack

### Backend
- **PHP 8.0+** (Procedural/OOP hybrid)
- **MySQL 5.7+** with PDO
- **Apache/Nginx** web server

### Frontend
- **HTML5** semantic markup
- **Tailwind CSS** for styling
- **JavaScript** (vanilla) for interactivity
- **Chart.js** for data visualization

### Database
- Normalized schema with 6 main tables
- Foreign key constraints for data integrity
- Indexed columns for optimal performance

## Installation

### Prerequisites
- PHP 8.0 or higher
- MySQL 5.7 or higher
- Apache or Nginx web server
- Composer (optional, for dependency management)

### Step 1: Clone or Download
```bash
cd /var/www/html
git clone <repository-url> medicine-reminder
cd medicine-reminder
```

### Step 2: Database Setup
1. Create a new MySQL database:
```sql
CREATE DATABASE medicine_reminder CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

2. Import the database schema:
```bash
mysql -u root -p medicine_reminder < sql/database_setup.sql
```

Or use phpMyAdmin to import the SQL file.

### Step 3: Configuration
1. Edit the database configuration file:
```php
// includes/db_config.php

define('DB_HOST', 'localhost');
define('DB_NAME', 'medicine');
define('DB_USER', 'root');
define('DB_PASS', '');
```

2. Update the base URL in your web server configuration if needed.

### Step 4: File Permissions
Ensure the web server has read/write permissions:
```bash
chmod -R 755 /var/www/html/medicine-reminder
chown -R www-data:www-data /var/www/html/medicine-reminder
```

### Step 5: Access the Application
Open your browser and navigate to:
```
http://localhost/medicine-reminder/
```

## Directory Structure

```
medicine-reminder/
├── index.php                 # Entry point
├── README.md                 # This file
├── assets/
│   ├── css/
│   │   └── styles.css       # Custom styles
│   └── js/                  # JavaScript files (if any)
├── includes/
│   ├── db_config.php        # Database configuration
│   ├── auth.php             # Authentication functions
│   └── sidebar.php          # Sidebar navigation component
├── pages/
│   ├── login.php            # Login page
│   ├── register.php         # Registration page
│   ├── dashboard.php        # Main dashboard
│   ├── medicines.php        # Medication list
│   ├── schedule.php         # Schedule view
│   ├── alerts.php           # Notifications
│   ├── history.php          # Medication history
│   ├── settings.php         # User settings
│   ├── logout.php           # Logout handler
│   └── api/                 # API endpoints
│       ├── medicine_add.php
│       ├── medicine_edit.php
│       ├── schedule_add.php
│       └── mark_taken.php
└── sql/
    └── database_setup.sql   # Database schema
```

## Database Schema

### Tables

1. **users** - User accounts and preferences
2. **medications** - Medication information
3. **schedules** - Dosing schedules
4. **medication_logs** - History of taken/missed doses
5. **notifications** - User notifications
6. **caregiver_access** - Caregiver permissions (future feature)

### Entity Relationships
```
users (1) ----< (*) medications
users (1) ----< (*) schedules
users (1) ----< (*) medication_logs
users (1) ----< (*) notifications
medications (1) ----< (*) schedules
medications (1) ----< (*) medication_logs
schedules (1) ----< (*) medication_logs
```

## Usage Guide

### First Time Setup
1. Register a new account
2. Complete your profile in Settings
3. Add your first medication
4. Create a schedule for the medication
5. View your dashboard for upcoming doses

### Daily Workflow
1. Check the Dashboard or Alerts page for upcoming doses
2. Mark doses as taken when you take them
3. Review your History to track adherence
4. Update medication quantities as needed

### Managing Schedules
- **Once**: Single dose at a specific date/time
- **Daily**: Recurring daily at specified time(s)
- **Weekly**: Select specific days of the week
- **Custom**: Set interval in hours (e.g., every 8 hours)

## Security Considerations

### Production Deployment
1. **Change default database credentials**
2. **Enable HTTPS** for secure communication
3. **Set strong session cookie parameters**
4. **Configure proper file permissions**
5. **Regular database backups**
6. **Keep PHP and MySQL updated**

### Recommended PHP Settings
```ini
; php.ini
session.cookie_httponly = 1
session.cookie_secure = 1
session.cookie_samesite = "Strict"
display_errors = Off
log_errors = On
```

## Customization

### Color Scheme
The application uses CSS variables for easy theming:
```css
:root {
    --color-primary: #0d9488;      /* Teal */
    --color-success: #10b981;       /* Emerald */
    --color-danger: #ef4444;        /* Red */
    --color-warning: #f59e0b;       /* Amber */
}
```

### Adding New Features
1. Create new page in `pages/` directory
2. Add navigation link in `includes/sidebar.php`
3. Create API endpoints in `pages/api/` if needed
4. Update database schema if required

## Troubleshooting

### Common Issues

**Database Connection Error**
- Verify database credentials in `includes/db_config.php`
- Ensure MySQL service is running
- Check database exists

**Session Issues**
- Verify PHP session extension is enabled
- Check file permissions for session storage
- Clear browser cookies

**404 Errors**
- Ensure mod_rewrite is enabled (Apache)
- Check .htaccess configuration
- Verify file paths are correct

## API Reference

### mark_taken.php
Marks a medication dose as taken.

**Method**: POST  
**Parameters**:
- `log_id` (int) - The log entry ID
- `csrf_token` (string) - CSRF token

**Response**:
```json
{
    "success": true,
    "message": "Marked as taken"
}
```

## Contributing

1. Fork the repository
2. Create a feature branch
3. Commit your changes
4. Push to the branch
5. Create a Pull Request

## License

This project is licensed under the MIT License.

## Support

For issues and feature requests, please use the GitHub issue tracker.

## Acknowledgments

- Tailwind CSS for the utility-first CSS framework
- Chart.js for beautiful data visualization
- The PHP community for excellent documentation

---

**Built with care for better health management**
#   m e d i c i n e - r e m i n d e r  
 