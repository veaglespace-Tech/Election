# Election Voter Search & Admin Management System

A fast, responsive web application and PWA for searching voter lists (Pune English Teachers Constituency) with an integrated admin management portal.

---

## 📁 Project Structure

```text
Election/
├── database/                   # Database schemas and data dumps
│   ├── setup_db.sql            # Table definitions (admins, electors) & initial admin
│   └── vps_seed_data.sql       # Full seed data (51,000+ voter records)
│
├── scripts/                    # Maintenance & utility scripts
│   ├── clean_sql.ps1           # PowerShell script to clean null bytes & normalize SQL dumps
│   └── clean_sql.py            # Python script to clean null bytes & normalize SQL dumps
│
├── admin-dashboard.html        # Admin analytics, stats & voter record management
├── admin-login.html            # Secure admin sign-in page
├── index.html                  # Public voter search portal
├── api.php                     # Backend REST API (search, filtering, pagination)
├── manifest.json               # Progressive Web App (PWA) manifest
├── sw.js                       # Service Worker for offline caching
├── icon.png                    # App / Campaign icon
├── icon1.png                   # Candidate / App icon
├── .gitignore                  # Git ignore rules
└── README.md                   # Documentation
```

---

## 🚀 Setup & Installation

### 1. Database Setup
1. Create a MySQL database (e.g., `election_db`).
2. Run `database/setup_db.sql` in MySQL / phpMyAdmin:
   ```bash
   mysql -u root -p election_db < database/setup_db.sql
   ```
3. Import the seed data:
   ```bash
   mysql -u root -p election_db < database/vps_seed_data.sql
   ```

### 2. Configure Database Connection
Edit [api.php](file:///c:/Users/HP/Desktop/Election/api.php) to match your environment:
```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'election_db');
define('DB_USER', 'election_app');
define('DB_PASS', 'Veagle@12345');
define('DB_PORT', 3306);
```

### 3. Run Locally / Deploy
- Deploy the files to your web server docroot (Apache / Nginx / XAMPP / VPS).
- Or run a local PHP development server:
  ```bash
  php -S localhost:8000
  ```
- Open `http://localhost:8000/index.html` in your browser.

---

## 🛠️ Maintenance Scripts

If you export or receive raw SQL dumps that contain NULL bytes or BOM issues:
- **PowerShell**:
  ```powershell
  powershell -ExecutionPolicy Bypass -File scripts/clean_sql.ps1
  ```
- **Python**:
  ```bash
  python scripts/clean_sql.py [path/to/file.sql]
  ```

---

## 📱 Features
- **Public Portal (`index.html`)**: Instant search by voter name, address, part number, institute, and gender.
- **Admin Dashboard (`admin-dashboard.html`)**: Real-time voter counts, gender breakdown, PDF downloads, and search filters.
- **PWA Ready (`sw.js`, `manifest.json`)**: Offline support and home screen installable on Android/iOS.