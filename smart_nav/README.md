# SmartNav — Setup Guide

## Step 1 — Copy Files to XAMPP
Copy the entire `smart_nav` folder to:
```
C:\xampp\htdocs\smart_nav\
```

## Step 2 — Run the SQL Patch
1. Open http://localhost/phpmyadmin
2. Click `smart_navigation_db` on the left
3. Click the **SQL** tab
4. Open `PATCH_run_in_phpmyadmin.sql` from this folder
5. Copy all contents → paste into phpMyAdmin SQL box → click **Go**

## Step 3 — Make Yourself Admin
After registering an account on the website, run this in phpMyAdmin SQL tab:
```sql
UPDATE user SET role='admin' WHERE email='your@email.com';
```

## Step 4 — Open the Website
Go to: http://localhost/smart_nav/

## Demo Admin Login (no registration needed)
- Email: admin@admin.com
- Password: admin123

---

## File Structure
```
smart_nav/
├── index.php                  ← Dashboard (home)
├── includes/
│   ├── db_connect.php         ← Database connection
│   ├── auth.php               ← Session & login helpers
│   ├── layout.php             ← Sidebar + header (shared)
│   └── layout_end.php         ← Closing HTML tags
├── pages/
│   ├── login.php              ← Login & Register
│   ├── logout.php             ← Logout
│   ├── route_finder.php       ← Smart route search
│   ├── chaos_map.php          ← Active incident map
│   ├── report.php             ← Report an incident
│   ├── traffic.php            ← Traffic analytics
│   ├── trip_history.php       ← User trip history & charts
│   ├── ratings.php            ← Transport ratings & reviews
│   ├── preferences.php        ← User preferences
│   ├── admin.php              ← Admin dashboard
│   ├── admin_routes.php       ← Add/delete routes & segments
│   ├── admin_fares.php        ← Manage fares & pricing
│   └── admin_transport.php    ← Transport modes & availability
└── PATCH_run_in_phpmyadmin.sql
```

## Features Covered
| # | Feature | Page |
|---|---------|------|
| 1 | Source & Destination Selection | route_finder.php |
| 2 | Multiple Transport Mode Support | route_finder.php |
| 3 | User Preference Management | preferences.php |
| 4 | Route Cost Estimation | route_finder.php |
| 5 | Travel Time Calculation | route_finder.php |
| 6 | Route History & Saved Routes | trip_history.php |
| 7 | Transport Network Database | admin_routes.php |
| 8 | Historical Traffic Data | traffic.php |
| 9 | User Travel History & Analytics | trip_history.php |
| 10 | Transport Fare Management | admin_fares.php |
| 11 | Crowdsourced Incident Database | chaos_map.php + report.php |
| 12 | Transport Availability Tracking | admin_transport.php |
| + | Route Caching | auto in route_finder.php |
| + | Transport Rating & Feedback | ratings.php |
| + | Admin Panel with CRUD | admin.php + sub-pages |
