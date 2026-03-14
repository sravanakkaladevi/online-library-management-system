# Online Library Management System

A **PHP + MySQL based web application** that allows users to browse books, request book issues, manage orders, and process returns through an admin dashboard.

This project has been updated to run smoothly with **modern PHP 8+ environments on Windows** using PDO for MySQL connectivity.

---

# Features

### User Features

* User registration and login
* Browse available books
* View book details
* Add books to cart
* Checkout with demo payment flow
* View order history
* Cancel eligible orders
* View issued books
* Request book issue
* Request book return
* View issue request history
* Preview books using preview links

---

### Admin Features

* Admin authentication
* Manage categories
* Manage authors
* Add and edit books
* Add preview links for books
* Manage registered students
* Activate or block students
* Delete students when no issued book or order history exists
* Issue books manually
* Manage issued books
* Process return requests and fines
* Manage book issue requests
* Manage orders
* Update order status:

  * Placed
  * Packed
  * In Transit
  * Out For Delivery
  * Delivered
  * Cancelled
* Update payment status:

  * Paid
  * Refund Pending
  * Refunded

---

# Project Structure

```
online-library-management-system
│
├── library/                 Application source code
│   ├── admin/
│   ├── includes/
│   ├── css/
│   ├── js/
│   ├── index.php
│   └── adminlogin.php
│
├── database/
│   └── library.sql          Database dump file
│
└── README.md
```

---

# Requirements

* PHP **8.0 or newer**
* MySQL **8+** or MariaDB
* PHP extension **pdo_mysql**
* Web browser

---

# PHP Configuration

Ensure the **pdo_mysql** extension is enabled in `php.ini`.

Example configuration:

```
extension_dir="ext"
extension=pdo_mysql
```

Restart PHP or restart the PHP built-in server after making changes.

---

# Database Setup

### Step 1 — Create Database

```
CREATE DATABASE library;
```

### Step 2 — Import Database

Example command:

```
mysql -u root -p library < database/library.sql
```

---

# Database Configuration

Update database credentials in the following files:

```
library/includes/config.php
library/admin/includes/config.php
```

Example configuration:

```php
$host="localhost";
$dbname="library";
$username="root";
$password="";
```

---

# Run Project Locally

Open PowerShell or Command Prompt:

```
cd C:\Users\srava\Downloads\Online-Library-Management-System-PHP\Online-Library-Management-System-PHP\library
php -S localhost:8000
```

Then open the application in your browser.

User Panel:

```
http://localhost:8000/
```

Admin Login:

```
http://localhost:8000/adminlogin.php
```

---

# Demo Login Credentials

### Admin

```
Username: admin
Password: Test@123
```

### User

```
Email: test@gmail.com
Password: Test@123
```

⚠ These credentials are for **demo purposes only**.

---

# Book Preview Feature

Admins can attach preview links to books.

Supported links:

* Google Drive preview links
* Direct document preview links

Example:

```
https://drive.google.com/file/d/FILE_ID/view
```

When a preview link exists:

* Users can click **Preview Book** from the book list
* Users can preview from the book details page
* The preview opens in an embedded viewer

---

# Order Workflow

### User

1. Add books to cart
2. Checkout with demo payment
3. View orders in **My Orders**
4. Cancel orders while status is **Placed** or **Packed**
5. Cancelled orders show **Refund Pending**

---

### Admin

1. Open **Manage Orders**
2. Update order status
3. Update payment status
4. Add order notes if required

---

# Return Workflow

### User

1. Open **Issued Books**
2. Click **Request Return**
3. Status becomes **Return Requested**

---

### Admin

1. Open **Manage Issued Books**
2. View issued book details
3. Enter fine if required
4. Click **Return Book**

---

# Important Notes

* Payment flow is **demo only**
* No real payment gateway integration
* Order cancellation marks payment as **Refund Pending**
* Book stock is calculated using issued and sold copies

---

# Future Improvements

- Replace demo payment with Razorpay or Stripe
- Replace MD5 password hashing with `password_hash()` and `password_verify()`
- Add email notifications for order and return status changes
- Add upload support for local PDF previews

