# CapTrack Vault - Capstone Management System

A comprehensive web-based management system for capstone projects, designed to streamline the entire capstone process from proposal submission to final defense and manuscript review.

## Overview

CapTrack Vault is a multi-role management system that facilitates the complete capstone project lifecycle. It supports students, faculty, advisers, deans, panelists, grammarians, and administrators in managing capstone projects from initial proposal to final manuscript submission.

## Key Features

### Student Features
- **Project Submission**: Submit capstone project proposals and working titles
- **Progress Tracking**: Real-time progress tracking through all capstone phases
- **Document Management**: Upload and manage project documents
- **Defense Management**: Schedule and manage title and final defenses
- **Manuscript Upload**: Submit final manuscripts for grammar review
- **Research Repository**: Browse and search completed capstone projects
- **Bookmark System**: Save interesting research for future reference

### Faculty Features
- **Advisory Management**: Manage assigned student groups
- **Project Review**: Review and approve student proposals
- **Grade Management**: Submit grades and feedback
- **Progress Monitoring**: Track student progress through capstone phases

### Adviser Features
- **Technical Review**: Review technical aspects of student projects
- **Grade Submission**: Submit technical grades and feedback
- **Student Management**: Manage assigned student groups
- **Defense Participation**: Participate in defense evaluations

### Dean Features
- **Panel Assignment**: Assign panelists to student defenses
- **Adviser Assignment**: Assign technical advisers to student groups
- **Grammarian Assignment**: Assign grammarians for manuscript review
- **Project Oversight**: Monitor overall project progress

### Panelist Features
- **Defense Evaluation**: Participate in title and final defense evaluations
- **Grade Submission**: Submit defense grades and feedback
- **Project Review**: Review student project presentations

### Grammarian Features
- **Manuscript Review**: Review student manuscripts for grammar and style
- **Feedback Management**: Provide detailed grammar feedback
- **File Management**: Upload reviewed manuscripts with corrections

### Admin Features
- **User Management**: Manage all system users and roles
- **Data Import/Export**: Bulk import/export student and research data
- **System Configuration**: Configure year sections and system settings
- **Verification Management**: Verify student accounts and research submissions
- **Analytics Dashboard**: View system statistics and reports

## System Architecture

### User Roles
- **Admin**: Full system access and management
- **Student**: Project submission and management
- **Faculty**: Advisory and review functions
- **Adviser**: Technical review and grading
- **Dean**: Assignment and oversight functions
- **Panelist**: Defense evaluation
- **Grammarian**: Manuscript review

### Technology Stack
- **Backend**: PHP 7.4+
- **Database**: MySQL
- **Frontend**: HTML5, CSS3, JavaScript
- **Libraries**: 
  - PHPMailer (Email functionality)
  - FPDF (PDF generation)
  - PHPWord (Document processing)
- **File Upload**: Secure file handling with validation

## Project Structure

```
management_system/
├── admin/                    # Admin panel functionality
│   ├── api/                 # API endpoints
│   ├── add_*.php           # User creation forms
│   ├── update_*.php        # User update forms
│   ├── delete_*.php        # User deletion
│   ├── *_list.php          # Data listing pages
│   ├── import_*.php        # Data import functionality
│   ├── export_*.php        # Data export functionality
│   └── dashboard.php       # Admin dashboard
├── adviser/                 # Adviser functionality
├── assets/                  # Static assets
│   ├── css/                # Stylesheets
│   ├── js/                 # JavaScript files
│   ├── img/                # Images
│   ├── uploads/            # File uploads
│   └── includes/           # Shared PHP includes
├── config/                  # Configuration files
│   ├── database.php        # Database configuration
│   ├── email.php           # Email configuration
│   └── retention.php       # Data retention settings
├── cron/                    # Scheduled tasks
├── dean/                    # Dean functionality
├── faculty/                 # Faculty functionality
├── grammarian/              # Grammarian functionality
├── panel/                   # Panelist functionality
├── student/                 # Student functionality
├── users/                   # Authentication system
└── uploads/                 # Global uploads directory
```

## Installation

### Prerequisites
- **Web Server**: Apache/Nginx
- **PHP**: Version 7.4 or higher
- **MySQL**: Version 5.7 or higher
- **Extensions**: mysqli, fileinfo, curl, openssl

### Setup Instructions

1. **Clone the Repository**
   ```bash
   git clone <repository-url>
   cd management_system
   ```

2. **Database Setup**
   - Create a MySQL database named `capstone_management`
   - Import the database schema (if available)
   - Update database credentials in `config/database.php`

3. **Configuration**
   - Update `config/database.php` with your database credentials
   - Configure email settings in `config/email.php`
   - Set proper file permissions for upload directories

4. **Web Server Configuration**
   - Point your web server document root to the project directory
   - Ensure mod_rewrite is enabled (for clean URLs)
   - Set appropriate file upload limits in PHP configuration

5. **Initial Setup**
   - Access the system through your web browser
   - Register the first admin account
   - Configure year sections and system settings

## Usage Guide

### For Students

1. **Registration & Login**
   - Register with your institutional email
   - Wait for admin verification
   - Login with your credentials

2. **Project Submission**
   - Navigate to "Submit Research" from the dashboard
   - Fill in project details and upload documents
   - Submit for faculty review

3. **Progress Tracking**
   - View your project status on the dashboard
   - Track progress through different phases
   - Upload additional documents as needed

4. **Defense Management**
   - Schedule title and final defenses
   - Upload defense materials
   - View defense results and feedback

### For Faculty/Advisers

1. **Student Management**
   - View assigned student groups
   - Review submitted projects
   - Provide feedback and grades

2. **Project Review**
   - Review project proposals
   - Approve or request revisions
   - Track student progress

### For Administrators

1. **User Management**
   - Create and manage user accounts
   - Assign roles and permissions
   - Verify student accounts

2. **Data Management**
   - Import/export student data
   - Manage research repository
   - Configure system settings

## 🔧 Configuration

### Database Configuration
Update `config/database.php`:
```php
$servername = "localhost";
$username = "your_username";
$password = "your_password";
$database = "capstone_management";
```

### Email Configuration
Configure SMTP settings in `config/email.php` for notification emails.

### File Upload Settings
Ensure PHP configuration allows adequate file uploads:
```ini
upload_max_filesize = 50M
post_max_size = 50M
max_execution_time = 300
```

## Data Import/Export

The system supports comprehensive data import/export functionality:

### Student Data
- **Export**: Download all student data as Excel files
- **Import**: Bulk import students from CSV/Excel files
- **Template**: Download sample templates for proper formatting

### Research Data
- **Export**: Export all research projects as Excel files
- **Import**: Bulk import research data
- **Validation**: Comprehensive data validation during import

See `admin/IMPORT_EXPORT_README.md` for detailed documentation.

## Security Features

- **Role-based Access Control**: Multi-role authentication system
- **SQL Injection Prevention**: Prepared statements throughout
- **XSS Protection**: Input sanitization and output escaping
- **File Upload Security**: Type validation and secure handling
- **Session Management**: Secure session handling
- **Password Security**: Bcrypt password hashing

## Responsive Design

The system features a modern, responsive design that works across:
- Desktop computers
- Tablets
- Mobile devices
- Various screen sizes and orientations

## Automated Tasks

The system includes several automated maintenance tasks:

- **Account Cleanup**: Remove graduated student accounts
- **Notification Cleanup**: Clean old notifications
- **Year Rollover**: Automatic academic year transitions
- **Data Retention**: Automated data archiving

## Troubleshooting

### Common Issues

1. **Database Connection Errors**
   - Verify database credentials
   - Ensure MySQL service is running
   - Check database exists

2. **File Upload Issues**
   - Check PHP upload limits
   - Verify directory permissions
   - Ensure sufficient disk space

3. **Email Not Working**
   - Verify SMTP configuration
   - Check firewall settings
   - Test with simple email first

### Debug Mode
Enable debug mode by setting:
```php
error_reporting(E_ALL);
ini_set('display_errors', 1);
```

## Contributing

1. Fork the repository
2. Create a feature branch
3. Make your changes
4. Test thoroughly
5. Submit a pull request

## License

This project is licensed under the MIT License - see the LICENSE file for details.

## Support

For support and questions:
- Create an issue in the repository
- Contact the development team
- Check the documentation in individual module README files

## Version History

- **v1.0**: Initial release with basic functionality
- **v1.1**: Added import/export features
- **v1.2**: Enhanced grammarian module
- **v1.3**: Improved UI/UX and mobile responsiveness

## Future Enhancements

- Mobile application
- Advanced analytics and reporting
- Integration with learning management systems
- Enhanced collaboration features
- Automated plagiarism detection
- Advanced document management

---

**CapTrack Vault** - Streamlining capstone project management for educational institutions.
