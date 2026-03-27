# Online Library Management System

A PHP and MySQL demo project for browsing books, placing orders, and managing library activity from an admin panel.

## Clean Structure

```text
Online-Library-Management-System-PHP/
|-- library/                    Main PHP application
|   |-- admin/
|   |-- assets/
|   |-- includes/
|   |-- database/
|   |   |-- library.sql
|   |   `-- library-legacy.sql
|   `-- screenshots/
|-- scripts/
|   `-- start_project.bat
|-- tests/                      Saved manual test and preview files
`-- README.md
```

## Requirements

- PHP 8.0+
- MySQL or MariaDB
- `pdo_mysql` enabled in `php.ini`

## Database Setup

1. Create the database:

```sql
CREATE DATABASE library;
```

2. Import the main dump:

```bash
mysql -u root -p library < library/database/library.sql
```

`library/database/library-legacy.sql` is kept only as an older backup dump.

## Run the Project

Fastest option:

```text
scripts\start_project.bat
```

Manual option:

```powershell
cd C:\Users\srava\Downloads\Online-Library-Management-System-PHP\library
php -S localhost:8000
```

Then open:

- User panel: `http://localhost:8000/`
- Admin login: `http://localhost:8000/adminlogin.php`

## Config Files

Update database credentials in:

- `library/includes/config.php`
- `library/admin/includes/config.php`

## Demo Credentials

Admin:

```text
Username: admin
Password: Test@123
```

User:

```text
Email: test@gmail.com
Password: Test@123
```

These credentials are for demo use only.
