# Grammarian Module

This module provides functionality for grammarians to review student manuscripts after final defense approval.

## Features

### For Grammarian
- **Dashboard**: View all submitted manuscripts with status filtering
- **Manuscript Review**: Review student manuscripts and provide grammar feedback
- **File Upload**: Upload reviewed/corrected manuscripts
- **Status Management**: Approve, reject, or mark manuscripts as under review
- **Notes**: Add detailed grammar feedback and suggestions

### For Students
- **Manuscript Upload**: Upload final manuscript after final defense approval
- **Status Tracking**: View review status and grammarian feedback
- **File Download**: Download reviewed manuscripts with grammar corrections

## Database Schema

### manuscript_reviews Table
- `id`: Primary key
- `project_id`: References project_working_titles(id)
- `student_id`: References users(id)
- `manuscript_file`: Path to student's uploaded manuscript
- `grammarian_reviewed_file`: Path to grammarian's reviewed file
- `status`: pending, under_review, approved, rejected
- `grammarian_notes`: Grammarian's feedback and suggestions
- `date_submitted`: When student submitted manuscript
- `date_reviewed`: When grammarian completed review
- `reviewed_by`: References users(id) of grammarian
- `created_at`, `updated_at`: Timestamps

## File Structure

```
grammarian/
├── home.php                 # Grammarian dashboard
├── review_manuscript.php    # Manuscript review interface
├── setup_database.php       # Database setup script
└── README.md               # This file

student/
└── manuscript_upload.php   # Student manuscript upload page
```

## Setup Instructions

1. **Run Database Setup**:
   - Navigate to `grammarian/setup_database.php` in your browser
   - This will create the necessary database table

2. **Create Grammarian User**:
   - Use the admin panel to create a user with 'grammarian' role
   - Or add 'grammarian' to an existing user's roles

3. **Upload Directories**:
   - Ensure `assets/uploads/manuscripts/` exists for student uploads
   - Ensure `assets/uploads/grammarian_reviews/` exists for grammarian uploads

## Workflow

1. **Student completes final defense** → Final defense status becomes 'approved'
2. **Student uploads manuscript** → Status: 'pending'
3. **Grammarian reviews manuscript** → Status: 'under_review'
4. **Grammarian provides feedback** → Status: 'approved' or 'rejected'
5. **Student receives notification** → Can download reviewed manuscript

## Integration Points

- **Progress Bar**: Updated to include manuscript upload as step 4
- **Role System**: Added 'grammarian' role support
- **Notifications**: Integrated with existing notification system
- **Final Defense**: Added manuscript upload button after approval

## Security Features

- Role-based access control
- File type validation (PDF only)
- Secure file upload handling
- SQL injection prevention with prepared statements
- XSS protection with htmlspecialchars()

## UI Features

- Responsive design
- Modern gradient styling
- Status badges and progress indicators
- File preview and download functionality
- Real-time notifications
- Mobile-friendly interface
