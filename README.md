# Online Library Management System

This is a PHP + MySQL library management project upgraded to work better with modern local development on Windows and PHP 8+.

It now supports:
- Book issue requests
- Return requests
- Cart and checkout
- Demo payment flow
- User order history
- Admin order management
- Book preview links
- PHP 8+ PDO MySQL compatibility

## Project Structure

- App root: `library/`
- Database dump: `library/library.sql`
- User entry page: `library/index.php`
- Admin entry page: `library/adminlogin.php`

## Requirements

- PHP 8.0 or newer
- MySQL 8+ or MariaDB
- `pdo_mysql` enabled in PHP
- Web browser

## PHP Configuration

Make sure `pdo_mysql` is enabled in `php.ini`.

Example:

```ini
extension_dir = "ext"
extension=pdo_mysql
```

Then restart PHP or restart the built-in server.

## Database Setup

### Fresh install

1. Create a database named `library`
2. Import `library/library.sql`

Example:

```powershell
mysql -u root -p -e "CREATE DATABASE IF NOT EXISTS library;"
cmd /c "mysql -u root -p library < C:\path\to\library\library.sql"
```

### If you already imported an older copy of the project

Run these upgrades on the existing database:

```sql
ALTER TABLE tblbooks
ADD COLUMN PreviewLink VARCHAR(500) DEFAULT NULL AFTER bookQty;

ALTER TABLE tblissuedbookdetails
ADD COLUMN ReturnRequestStatus TINYINT(1) NOT NULL DEFAULT 0 AFTER fine,
ADD COLUMN ReturnRequestDate TIMESTAMP NULL DEFAULT NULL AFTER ReturnRequestStatus,
ADD COLUMN ReturnProcessedDate TIMESTAMP NULL DEFAULT NULL AFTER ReturnRequestDate;

ALTER TABLE tblorders
ADD COLUMN StatusNote MEDIUMTEXT DEFAULT NULL AFTER OrderStatus;
```

## Database Config Files

Update database credentials in:

- `library/includes/config.php`
- `library/admin/includes/config.php`

## Run Locally

Open PowerShell:

```powershell
cd "C:\Users\srava\Downloads\Online-Library-Management-System-PHP\Online-Library-Management-System-PHP\library"
php -S localhost:8000
```

Then open:

- User site: `http://localhost:8000/`
- Admin login: `http://localhost:8000/adminlogin.php`

## Default Login Details

### User

- Email: `test@gmail.com`
- Password: `Test@123`

### Admin

- Username: `admin`
- Password: `Test@123`

## Main Features

### User Features

- Sign in and manage profile
- Browse listed books
- Open book details
- Add books to cart
- Buy books through demo checkout
- View order history
- Cancel eligible orders
- See refund pending status after cancellation
- Request book issue
- View issue request history
- View issued books
- Request return for issued books
- Open preview links for books when available

### Admin Features

- Manage categories
- Manage authors
- Add and edit books
- Add preview links for books
- Manage registered students
- Activate or block students
- Delete students when no issued-book or order history exists
- Issue books manually
- Manage issued books
- Process return requests and fines
- Manage book issue requests
- Manage orders
- Update order status to:
  - `Placed`
  - `Packed`
  - `In Transit`
  - `Out For Delivery`
  - `Delivered`
  - `Cancelled`
- Update payment status to:
  - `Paid`
  - `Refund Pending`
  - `Refunded`

## Book Preview Links

Admin can add a preview link from the book form.

Supported use cases:
- Google Drive file share links
- Direct preview/document links

Example Google Drive link:

```text
https://drive.google.com/file/d/FILE_ID/view?usp=sharing
```

When a preview link exists:
- Users see `Preview Book` on the book list
- Users see `Preview Book` on the book details page
- The app opens an embedded preview page

## Order Flow

### User side

1. Add a book to cart
2. Checkout with demo payment
3. View order in `My Orders`
4. Cancel while the order is still in `Placed` or `Packed`
5. See message: `Your money will be refunded shortly.`

### Admin side

1. Open `Manage Orders`
2. Select an order
3. Update order status and payment status
4. Add a note such as `Packed and ready for dispatch`

## Return Flow

### User side

1. Open `Issued Books`
2. Click `Request Return`
3. Status becomes `Return Requested`

### Admin side

1. Open `Manage Issued Books`
2. Open the issued-book record
3. Enter fine if needed
4. Click `Return Book`
5. Status becomes returned

## Important Notes

- Payment gateway is currently a demo flow, not a real online payment integration
- Order cancellation marks payment as `Refund Pending`
- Preview button only appears when a preview link is added
- Book stock is now calculated using issued copies and sold copies

## Recommended Next Improvements

- Replace demo payment with Razorpay or Stripe
- Replace MD5 password hashing with `password_hash()` and `password_verify()`
- Add email notifications for order and return status changes
- Add upload support for local PDF previews

