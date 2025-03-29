# MCQ Exam System Deployment Guide

## Prerequisites
1. AWS Account with Lightsail access
2. Domain name (optional but recommended)
3. SSH key pair for secure connection
4. Local backup of your database

## Project Structure
```
mcq-exam/
├── admin/                 # Admin panel files
├── assets/               # Static assets (CSS, JS)
├── auth/                 # Authentication handlers
├── backups/              # Database backups
├── config/               # Configuration files
├── database/             # Database schema and migrations
├── deploy/               # Deployment scripts
├── includes/             # Shared PHP includes
├── src/                  # Source files
├── student/              # Student panel files
├── uploads/              # File uploads directory
├── vendor/               # Composer dependencies
├── .env                  # Environment configuration
├── .env.example          # Example environment file
├── .gitignore           # Git ignore rules
├── index.php            # Main entry point
├── install.php          # Installation script
└── README.md            # Project documentation
```

## Deployment Steps

### 1. AWS Lightsail Setup
1. Create a new Lightsail instance
   - Choose Linux/Unix platform
   - Select LAMP (PHP 8.1) blueprint
   - Choose your instance plan (2GB RAM minimum recommended)
   - Create instance

2. Configure Network Settings
   - Open ports: HTTP (80), HTTPS (443), MySQL (3306)
   - Attach static IP
   - Configure domain (if available)

### 2. Database Migration
1. Access phpMyAdmin on Lightsail instance
2. Create new database 'mcq_exam_db'
3. Import database schema from `database/schema.sql`
4. Import any existing data backups

### 3. File Deployment
1. Connect to instance via SSH
2. Navigate to web root: `/opt/bitnami/apache2/htdocs/`
3. Upload project files using SFTP or Git
4. Set proper permissions:
   ```bash
   sudo chown -R bitnami:daemon /opt/bitnami/apache2/htdocs/mcq-exam
   sudo chmod -R 750 /opt/bitnami/apache2/htdocs/mcq-exam
   sudo chmod -R 770 /opt/bitnami/apache2/htdocs/mcq-exam/uploads
   sudo chmod -R 770 /opt/bitnami/apache2/htdocs/mcq-exam/backups
   ```

### 4. Environment Configuration
1. Copy `.env.example` to `.env`
2. Update database credentials and app settings
3. Set environment to production:
   ```
   APP_ENV=production
   APP_DEBUG=false
   APP_URL=your-domain-or-ip
   ```

### 5. Security Measures
1. Delete or secure install.php after setup
2. Enable HTTPS using Let's Encrypt
3. Configure Apache security headers
4. Set up regular database backups

### 6. Post-Deployment Checks
1. Test admin login
2. Verify file upload functionality
3. Check exam creation and taking process
4. Validate backup system
5. Test email notifications

### 7. Maintenance
1. Regular backups using provided scripts
2. Monitor error logs
3. Keep PHP and dependencies updated
4. Regular security audits

## Troubleshooting
- Check PHP error logs: `/opt/bitnami/php/logs/error.log`
- Apache logs: `/opt/bitnami/apache2/logs/`
- Database logs: `/opt/bitnami/mysql/logs/`

## Backup and Restore
Use the provided backup scripts in `admin/backup_db.php`