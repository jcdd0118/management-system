# Import/Export Functionality Documentation

## Overview
This document describes the Excel import/export functionality implemented for the Student List and Research List in the admin panel.

## Features Implemented

### 1. Student List Import/Export
- **Export**: Download all students data as Excel (.xlsx) file
- **Import**: Upload Excel/CSV file to bulk import students
- **Template**: Download sample template with proper format

### 2. Research List Import/Export
- **Export**: Download all research data as Excel (.xlsx) file
- **Import**: Upload Excel/CSV file to bulk import research entries
- **Template**: Download sample template with proper format

## File Structure

### Export Files
- `export_students.php` - Exports student data to Excel
- `export_research.php` - Exports research data to Excel

### Import Files
- `import_students.php` - Imports student data from Excel/CSV
- `import_research.php` - Imports research data from Excel/CSV

### Template Files
- `download_template.php` - Downloads sample templates
- `sample_students.csv` - Sample student data for testing
- `sample_research.csv` - Sample research data for testing

## Usage Instructions

### Exporting Data
1. Navigate to Student List or Research List
2. Click the "Export Excel" button (green button with download icon)
3. The system will generate and download an Excel file with all current data

### Importing Data
1. Navigate to Student List or Research List
2. Click the "Import Excel" button (blue button with upload icon)
3. Download the template using the "Download Template" link
4. Fill in your data following the template format
5. Upload your Excel/CSV file
6. Review the import results

## Data Format Requirements

### Student Import Format
Required columns (in order):
- ID (ignored during import)
- First Name (required)
- Middle Name (optional)
- Last Name (required)
- Email (required, must be unique and valid)
- Gender (required: "Male" or "Female")
- Year Section (required: must be a valid year section from the system)
- Group Code (required: format like "3A-G1", "4B-G2")
- Status (optional: "verified" or "not verified", defaults to "not verified")

### Research Import Format
Required columns (in order):
- ID (ignored during import)
- Title (required)
- Author (required) - Can be simple format like "John Doe, Jane Smith" or complex STUDENT_DATA format
- Year (required: valid year between 1900 and current year + 5)
- Abstract (required)
- Keywords (required)
- Document Path (optional)
- User ID (optional: must exist in students table if provided)
- Status (optional: "verified" or "nonverified", defaults to "nonverified")

**Author Format Notes:**
- Simple format: "John Doe, Jane Smith" (comma-separated names)
- Complex format: "STUDENT_DATA:John|Carl|Doe|@@Jane|Marie|Smith|@@|DISPLAY:John Carl Doe, Jane Marie Smith"
- The system automatically converts simple format to complex format during import
- Export always shows the clean display format regardless of internal storage format

## Validation Rules

### Student Validation
- Email must be unique and valid format
- Gender must be "Male" or "Female"
- Year Section must be a valid year section from the system
- Group Code must match pattern: [3-4][AB]-G[number]
- Default password is set to "password123" for imported students

### Research Validation
- Year must be numeric and between 1900 and current year + 5
- User ID must exist in students table if provided
- All required fields must not be empty

## Error Handling
- Import process shows detailed error messages for each failed row
- Success and error counts are displayed after import
- Individual row errors are listed with specific validation messages
- Import continues even if some rows fail

## Technical Implementation
- Uses PHP's built-in CSV reading functions
- Generates proper Excel XML format (.xlsx) for export
- Implements comprehensive data validation
- Provides user-friendly error messages
- Maintains data integrity with foreign key checks
- Uses Microsoft Excel XML Spreadsheet format for compatibility

## Sample Files
- `sample_students.csv` - Contains 4 sample student records
- `sample_research.csv` - Contains 3 sample research records

## Security Features
- Admin authentication required for all operations
- File type validation (only .xlsx and .csv allowed)
- SQL injection prevention with prepared statements
- Input sanitization and validation

## Browser Compatibility
- Works with all modern browsers
- Excel files (.xlsx) can be opened in Microsoft Excel, LibreOffice, Google Sheets
- CSV files are universally compatible
- No more Excel format warnings or compatibility issues

## Troubleshooting

### Common Issues
1. **Import fails**: Check file format and column headers
2. **Email already exists**: Ensure unique email addresses
3. **Invalid year**: Use valid year format (e.g., 2024)
4. **Group code format**: Follow pattern like "3A-G1"

### File Size Limits
- Default PHP upload limits apply
- Large files may need server configuration adjustment
- Recommended: Import in batches of 100-500 records

## Future Enhancements
- Bulk update functionality
- Advanced validation rules
- Import preview before execution
- Export with custom date ranges
- Support for additional file formats
