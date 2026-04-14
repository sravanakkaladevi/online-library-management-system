# Online Library Management System

A PHP, MySQL, and Python-based library platform for:

- home delivery book orders
- counter issue requests
- paid 1-year online reading access
- admin approval flows
- user ratings, reviews, and ML-assisted recommendations

## Main Features

- User login, signup, profile, cart, checkout, and order tracking
- Book listing, book details, issue request flow, and online preview access
- Admin management for books, authors, categories, students, issued books, requests, orders, and reviews
- Online 1-year rent request approval from admin
- Ratings and reviews with recommendation support
- Python training pipeline that exports ML recommendations for PHP to use

## Screenshots

### User Book List

![User Book List](library/screenshots/book-list.png)

### Book Details

![Book Details](library/screenshots/book-details.png)

### Cart Page

![Cart Page](library/screenshots/cart-page.png)

### Issued Books

![Issued Books](library/screenshots/issued-books.png)

### User Account Menu

![User Account Menu](library/screenshots/user-account-option.png)

### Admin Dashboard

![Admin Dashboard](library/screenshots/admin-dashboard.png)

## Project Structure

```text
Online-Library-Management-System-PHP/
|-- library/
|   |-- admin/
|   |-- assets/
|   |-- data/
|   |-- database/
|   |-- includes/
|   |-- screenshots/
|   |-- export_recommendation_data.php
|   |-- index.php
|   `-- adminlogin.php
|-- scripts/
|   |-- start_project.bat
|   |-- train_recommendations.bat
|   `-- train_recommendations.py
|-- tests/
`-- README.md
```

## Requirements

- PHP 8.0 or later
- MySQL or MariaDB
- Python 3.10 or later
- `pdo_mysql` enabled in PHP

## Database Setup

1. Create the database:

```sql
CREATE DATABASE library;
```

2. Import the main SQL dump:

```bash
mysql -u root -p library < library/database/library.sql
```

3. If needed, run any maintenance scripts such as:

```powershell
php library/fix_db.php
```

## Run the App

Fastest option:

```text
scripts\start_project.bat
```

Manual option:

```powershell
cd library
php -S localhost:8000
```

Open:

- User panel: `http://localhost:8000/`
- Admin login: `http://localhost:8000/adminlogin.php`

## ML Recommendation Flow

The recommendation setup uses Python for training and PHP for runtime display.

1. PHP exports review and rating data:

```powershell
php library/export_recommendation_data.php
```

2. Python trains the recommendation model:

```powershell
python scripts/train_recommendations.py
```

3. Or run both together:

```text
scripts\train_recommendations.bat
```

The trained model is saved to:

```text
library/data/ml_recommendations.json
```

PHP reads this file and uses it for recommendation screens, with fallback logic when rating data is still too small.

## Important Config Files

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

## Admin Modules

The admin panel includes:

- Manage Books
- Manage Authors
- Manage Categories
- Manage Issued Books
- Manage Book Requests
- Manage Orders
- Online Rent Requests
- Manage Reviews
- Registered Students

## Notes

- Online reading remains locked until admin approves payment.
- More user ratings improve ML recommendation quality.
- Review and recommendation data can be retrained anytime with the training script.
