# MCQ Exam System

A comprehensive Multiple Choice Question (MCQ) examination system built with PHP, MySQL, and modern front-end technologies.

## Features

- **User Authentication**
  - Secure login and registration system
  - Role-based access control (Admin/Student)
  - Password hashing with pepper
  - CSRF protection
  - Session management

- **Admin Panel**
  - Create and manage exam sets
  - Add, edit, and delete questions
  - View student results and statistics
  - Manage user accounts
  - Set exam duration and pass percentage

- **Student Features**
  - Take exams with timer
  - View results with detailed feedback
  - Track progress and performance
  - Resume incomplete exams
  - View explanation for incorrect answers
  - Profile management

- **Security Features**
  - XSS protection
  - SQL injection prevention
  - CSRF tokens
  - Rate limiting
  - Brute force protection
  - Secure session handling

## Requirements

- PHP 7.4 or higher
- MySQL 5.7 or higher
- Apache web server with mod_rewrite enabled
- PHP PDO extension
- PHP JSON extension

## Installation

1. Clone or download the repository to your web server directory
2. Create a MySQL database
3. Access the installation script through your web browser:
   ```
   http://your-domain/install.php
   ```
4. Follow the installation wizard to:
   - Configure database connection
   - Create admin account
   - Set up initial configuration
5. Delete `install.php` after successful installation

## Directory Structure

```
├── admin/             # Admin panel files
├── assets/           # Static assets (CSS, JS)
├── auth/             # Authentication handlers
├── config/           # Configuration files
├── database/         # Database schema
├── includes/         # Shared PHP includes
└── student/          # Student panel files
```

## Security Recommendations

1. Change default database credentials
2. Use HTTPS in production
3. Keep PHP and all dependencies updated
4. Enable error reporting only in development
5. Regularly backup your database
6. Set secure file permissions

## Usage

### Admin Panel

1. Login with admin credentials
2. Create exam sets from the "Manage Exams" section
3. Add questions to exam sets
4. Monitor student progress and results

### Student Interface

1. Register/Login with student account
2. View available exams on dashboard
3. Start or resume exams
4. Complete exams within time limit
5. View results and feedback immediately

## Contributing

1. Fork the repository
2. Create a feature branch
3. Commit your changes
4. Push to the branch
5. Create a Pull Request

## License

This project is open-source and available under the MIT License.

## Support

For support, please open an issue in the repository or contact the development team.