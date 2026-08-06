# Project System Documentation: Tripura Tourism Portal

This document provides a comprehensive technical overview of the Tripura Tourism Portal, covering architecture, database schemas, API routes, mappings between admin and public pages, and production management workflows.

---

## 1. System Architecture

The Tripura Tourism Portal is built as a split-architecture web application consisting of a headless backend API and a single-page frontend application.

```
                  +-----------------------------------+
                  |        Angular Frontend           |
                  |  (Admin Panel & Public Website)   |
                  +-----------------+-----------------+
                                    |
                                    | HTTPS Requests
                                    v
                  +-----------------+-----------------+
                  |        Laravel Backend            |
                  |     (REST APIs & Mailer)          |
                  +-----------------+-----------------+
                                    |
                                    | SQL Queries
                                    v
                  +-----------------+-----------------+
                  |       MariaDB / MySQL             |
                  |         Database                  |
                  +-----------------------------------+
```

### Backend Stack
* **Framework**: Laravel 11.x (PHP 8.2+)
* **Authentication**: Laravel Sanctum (Token-based SPA & API Authentication)
  * **Session Timeout / Token Expiration**: Administrator and API tokens expire after **30 minutes** of inactivity (configured via `expiration` in [config/sanctum.php](file:///c:/Users/sagar/Documents/LaravelX/TRIPURA-TOURISM/config/sanctum.php)).
* **Email Dispatch**: Laravel Mailable (SMTP configured dynamically via Database)
* **File Upload Constraints & Locations**:
  * Filesystem storage is under `public/images/` and `public/tourism/images/` directories.
  * **Images**: Maximum allowed file size is **60 MB** (61,440 KB), enforced in controller validations (e.g. `TourController`, `AccommodationController`, `PhotoGalleryController`).
  * **Documents (PDFs)**: Maximum allowed file size is **20 MB** (20,480 KB), enforced in `DocumentController`.

### Frontend Stack
* **Framework**: Angular 18+ (SPA)
* **Styling**: Tailwind CSS & Custom CSS
* **Build Target**: Compiled static SPA bundle distributed in the Laravel `public/tourism/` directory.
* **Environment Hosting (SDC / Offline)**: Configured with `window.location.origin` fallback inside `environment.ts` to support dynamic domain resolution (e.g., automatically toggles between development `http://192.168.1.57:8008` and SDC production `https://tripuratourism.gov.in` without rebuilds).

---

## 2. Database Schema

The system uses a relational database schema. Below are the key tables and their relationships.

### `admins`
Stores the primary administrator credentials.
* `id` (bigint, Primary Key, Auto-Increment)
* `name` (varchar)
* `email` (varchar, Unique)
* `password` (varchar, MD5-hashed)
* `created_at`, `updated_at` (timestamps)

### `access_emails`
Secondary emails authorized to access the admin panel via OTP verification.
* `id` (bigint, Primary Key, Auto-Increment)
* `email` (varchar, Unique)
* `created_at`, `updated_at` (timestamps)

### `admin_otps`
Temporarily stores OTP validation tokens generated during secure logins.
* `id` (bigint, Primary Key, Auto-Increment)
* `email` (varchar)
* `otp` (varchar)
* `expires_at` (timestamp)
* `created_at`, `updated_at` (timestamps)

### `tour`
Stores packages, destinations, and festival spots.
* `docid` (bigint, Primary Key, Auto-Increment)
* `name` (varchar)
* `des` (text)
* `address` (varchar)
* `contact` (varchar)
* `email` (varchar)
* `website` (varchar)
* `displayorder` (int)
* `image`, `image2`, `image3` (varchar, file paths)
* `folder` (varchar)
* `status` (tinyint, 1 = Enabled, 2 = Disabled)
* `whatsnew` (tinyint, 1 = Yes, 2 = No)
* `tourtype` (varchar, e.g., '1' = Destination, '2' = Festival)

### `accommodations`
Stores Govt TTDCL lodges, private hotels, and homestays.
* `id` (bigint, Primary Key, Auto-Increment)
* `name` (varchar)
* `description` (text)
* `type` (varchar, '1' = Govt, '2' = Private, '3' = Lease out, '4' = Homestays)
* `contact` (varchar)
* `email` (varchar)
* `website` (varchar)
* `address` (varchar)
* `image` (varchar, filename)
* `displayorder` (int)
* `name_of_lessee` (varchar)
* `valid_upto` (varchar)
* `created_at`, `updated_at` (timestamps)

### `documents`
Stores PDFs for guidelines, circulars, tenders, and forms.
* `docid` (bigint, Primary Key, Auto-Increment)
* `des` (varchar)
* `doctype` (int, references categories like Tenders, Guidelines)
* `file` (varchar, filename)
* `status` (tinyint, 1 = Enabled, 2 = Disabled)
* `whatsnew` (tinyint, 1 = Yes, 2 = No)
* `openDate`, `closeDate`, `updateDate` (timestamps)

### `activity_logs`
Tracks admin audit logs (logins, uploads, deletions).
* `id` (bigint, Primary Key, Auto-Increment)
* `admin_id` (bigint)
* `operator_email` (varchar)
* `action_type` (varchar)
* `section` (varchar)
* `resource` (varchar)
* `details` (text)
* `ip_address` (varchar)
* `created_at`, `updated_at` (timestamps)

### `visitor`
Logs visitor IP addresses to track and guarantee uniqueness per day.
* `id` (bigint, Primary Key, Auto-Increment)
* `ip_address` (varchar)
* `created_at`, `updated_at` (timestamps)

### `visitor_counter`
Stores the cumulative unique visitor count (initialized at 11225460).
* `id` (unsigned int, Primary Key)
* `count` (bigint unsigned)
* `created_at`, `updated_at` (timestamps)

---

## 3. API Routing & Endpoint Contracts

All backend routes are prefixed with `/api`. Admin routes require an authorization bearer token.

### 3.1 Authentication APIs
No authentication headers required.

#### Send Login OTP
* **URL**: `/api/admin/send-login-otp`
* **Method**: `POST`
* **Payload**:
  ```json
  {
    "email": "operator@example.com"
  }
  ```
* **Success Response (200 OK)**:
  ```json
  {
    "message": "OTP verification code has been sent to your email."
  }
  ```

#### Password or OTP Login
* **URL**: `/api/admin/login`
* **Method**: `POST`
* **Payload (Password)**:
  ```json
  {
    "email": "admin@example.com",
    "password": "mysecretpassword"
  }
  ```
* **Payload (OTP)**:
  ```json
  {
    "email": "operator@example.com",
    "otp": "123456"
  }
  ```
* **Success Response (200 OK)**:
  ```json
  {
    "status": "success",
    "token": "1|sanctum_token_plain_text",
    "user": {
      "id": 1,
      "email": "admin@example.com"
    }
  }
  ```
* **Password Login Constraints for Secondary Operators**:
  * By default, emails in `access_emails` must use the **Email OTP** login flow. 
  * If the restriction block in `AdminAuthController.php` is commented out, secondary operators are permitted to log in with a password, using the **primary administrator's password** as a fallback credentials check.


---

### 3.2 Public API Endpoints
All public APIs are prefix-routed as `/api/public/...`.

| Endpoint | Method | Response Description |
| :--- | :--- | :--- |
| `/api/public/sliders` | `GET` | Array of home page slideshow banner assets |
| `/api/public/whats-new` | `GET` | Latest uploaded guidelines & documents flagged as what's new |
| `/api/public/tours/destinations` | `GET` | List of active tourist destinations |
| `/api/public/tours/festivals` | `GET` | List of active festival spots & seasonal locations |
| `/api/public/accommodations` | `GET` | All accommodations |
| `/api/public/accommodations/govt` | `GET` | Filtered Govt TTDCL accommodations (`type` = 1) |
| `/api/public/accommodations/private`| `GET` | Filtered Private Hotels (`type` = 2) |
| `/api/public/accommodations/leaseout`| `GET` | Filtered Leased properties (`type` = 3) |
| `/api/public/accommodations/homestays`| `GET` | Filtered Homestays (`type` = 4) |
| `/api/public/gallery` | `GET` | Album categories for the Photo/Video gallery index |
| `/api/public/photos` | `GET` | Flat list of photos matching an album |
| `/api/public/videos` | `GET` | Flat list of video items (returns dynamic play links) |
| `/api/public/documents` | `GET` | Active notifications, tenders, rules, and downloadable forms |
| `/api/public/testimonials` | `GET` | Active visitor testimonials |
| `/api/public/visitors/track` | `POST` | Logs client IP (if unique for the day) and increments/returns the total count in `visitor_counter` |
| `/api/public/visitors/count` | `GET` | Retrieves the current total visitor count from `visitor_counter` |

---

### 3.3 Protected Admin CRUD Resource Endpoints
Requires Header: `Authorization: Bearer <token>`. Payloads are structured as `FormData` to support image uploads.

#### Tours CRUD
* **List**: `GET /api/admin/tours`
* **Create**: `POST /api/admin/tours`
* **Update**: `POST /api/admin/tours/{id}` (Using POST method with payload parameters containing files)
* **Delete**: `DELETE /api/admin/tours/{id}`
* **Payload Fields**:
  * `name` (string)
  * `des` (string)
  * `tourtype` (string, '1' = Destination, '2' = Festival)
  * `image`, `image2`, `image3` (files, optional)
  * `status` (integer, 1 = Enable, 2 = Disable)
  * `displayorder` (integer)

#### Accommodations CRUD
* **List**: `GET /api/admin/accommodations`
* **Create**: `POST /api/admin/accommodations`
* **Update**: `POST /api/admin/accommodations/{id}`
* **Delete**: `DELETE /api/admin/accommodations/{id}`
* **Payload Fields**:
  * `name` (string)
  * `description` (string)
  * `type` (string, '1' = Govt, '2' = Private, '3' = Lease, '4' = Homestay)
  * `image` (file, optional)
  * `displayorder` (integer)
  * `contact`, `email`, `website`, `address` (strings)

#### Documents CRUD
* **List**: `GET /api/admin/documents`
* **Create**: `POST /api/admin/documents`
* **Update**: `POST /api/admin/documents/{id}`
* **Delete**: `DELETE /api/admin/documents/{id}`
* **Payload Fields**:
  * `des` (string, title)
  * `doctype` (integer, e.g. 1 = Notification, 2 = Tender)
  * `file` (file, PDF, optional on update)
  * `whatsnew` (integer, 1 = Yes, 2 = No)
  * `openDate`, `closeDate` (dates, 'YYYY-MM-DD')

---

## 4. Frontend & Backend Admin/Public Page Map

This table maps each public-facing website view to the respective administrator control dashboard and database table feeding its data.

| Public Route | Public HTML File | Admin Dashboard Component | Database Table | API Endpoint |
| :--- | :--- | :--- | :--- | :--- |
| `/` | `home.html` | `tourist-category.ts` & `slider.ts` | `slider`, `tourist_categories` | `/api/public/sliders` |
| `/destination` | `destination.html` | `tour.ts` (Tour Type: Destination) | `tour` | `/api/public/tours/destinations` |
| `/festivals` | `festivals.html` | `tour.ts` (Tour Type: Festival) | `tour` | `/api/public/tours/festivals` |
| `/accommodation/govt` | `under-ttdcl.html` | `accommodation.ts` (Type: 1) | `accommodations` | `/api/public/accommodations/govt` |
| `/accommodation/private` | `registered-private.html` | `accommodation.ts` (Type: 2) | `accommodations` | `/api/public/accommodations/private` |
| `/accommodation/leaseout` | `leaseout-ttdcl.html` | `accommodation.ts` (Type: 3) | `accommodations` | `/api/public/accommodations/leaseout` |
| `/accommodation/homestays` | `homestays.html` | `accommodation.ts` (Type: 4) | `accommodations` | `/api/public/accommodations/homestays` |
| `/gallery/photos` | `photo-galleries.html` | `photo-gallery.ts` | `photos` | `/api/public/photos` |
| `/gallery/videos` | `video-galleries.html` | `video-gallery.ts` | `videos` | `/api/public/videos` |
| `/tourist-corner/tender` | `tender.html` | `documents.ts` (Type: 2) | `documents` | `/api/public/documents` |
| `/tourist-corner/guidelines` | `guidelines.html` | `documents.ts` (Type: 5) | `documents` | `/api/public/documents` |
| `/tourist-corner/forms` | `downloadable-forms.html` | `documents.ts` (Type: 6) | `documents` | `/api/public/documents` |
| `/tourist-corner/recruitment` | `recruitment.html` | `documents.ts` (Type: 10) | `documents` | `/api/public/documents` |

---

## 5. Production Management & Deployment Workflows

Deploying and upgrading the application requires building the frontend files and uploading the database migrations and static directories.

### 5.1 Rebuilding the Frontend SPA
The Angular SPA is stored under `resources/Tourism/`.

1. Open your terminal inside the Angular directory:
   ```bash
   cd resources/Tourism
   ```
2. Build the production package. This command will output compilation assets (JS, CSS, HTML) directly into `public/tourism/`:
   ```bash
   ng build
   ```
   * *Note*: Angular's compilation wipes out `public/tourism/` before building. 

---

### 5.2 Uploading Files to Your Hosting Webroot
To make the site live, connect via SFTP/FTP or cPanel File Manager and upload files.

1. **Deploying Code**:
   * Upload all directories and root files of your Laravel backend (`app/`, `bootstrap/`, `config/`, `database/`, `routes/`, `resources/`, `vendor/`, `composer.json`, `artisan`, `.env`).
   * Upload the `public/` directory (which contains your assets, index.php, `.htaccess`, and the freshly compiled `public/tourism/` Angular directory).

2. **Syncing Uploaded Media Folders (CRITICAL)**:
   * Dynamic images (testimonials, tours, categories) are stored outside the compiled frontend inside the Laravel backend's `public/images/` folder.
   * **Crucial Step**: Ensure you copy and sync the entire local **`public/images/`** directory and all its subfolders up to your hosted server's root **`public/images/`** folder. If this directory is not uploaded, user-uploaded images and testimonials will show up as broken.
   * **Static Assets**: Copy directories like `public/about/`, `public/gallery2/`, and files like `public/Guidelines.jpg` directly to the hosted server's root `public/` folder so they are accessible from the root domain path (e.g. `https://domain.com/Guidelines.jpg`).

---

### 5.3 Database Configuration & Migrations
1. Update your production `.env` configuration file with your production database credentials:
   ```ini
   DB_CONNECTION=mysql
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_DATABASE=your_production_db
   DB_USERNAME=your_production_user
   DB_PASSWORD=your_production_password
   ```
2. Execute the database migration commands on your hosting server via terminal (or import the database SQL structure dump):
   ```bash
   php artisan migrate --force
   ```
3. Run the optimization command to cache configurations in production:
   ```bash
   php artisan config:cache
   php artisan route:cache
   ```

---

## 6. Recent Technical Updates & SEO Enhancements

### 6.1 Live Social Media Integration
* **Facebook Page Feed**: Replaced JS SDK implementation with a direct Meta Page Plugin `<iframe>` using the standard header (`small_header=false`, `hide_cover=false`) and optimized `340px` width parameters to prevent clipping. Tab buttons and container aligned cleanly on the left.
* **Instagram Profile Feed**: Integrated a native `<iframe>` pointing to `/embed` with custom CSS (`.hide-scrollbar` inside [footer.css](file:///c:/Users/sagar/Documents/iLOGITRON/TOURISM-NPC/TRIPURA-TOURISM/resources/Tourism/src/app/core/components/footer/footer.css)) to load and scroll 12 posts cleanly without showing system scrollbars.

### 6.2 Advanced SEO & Schema.org JSON-LD
* **Structured Data**: Injected dynamic `GovernmentOrganization` and `TouristAttraction` JSON-LD schema blocks in the `<head>` via [seo.service.ts](file:///c:/Users/sagar/Documents/iLOGITRON/TOURISM-NPC/TRIPURA-TOURISM/resources/Tourism/src/app/core/services/seo.service.ts) to gain rich search snippets in Google.
* **Expanded Search Phrases**: Integrated high-ranking search keywords for Indian and North-East tourism (e.g., "tourist places of India", "Tourist attractions of tripura", "beautiful places to visit in Tripura", "Unakoti hills", etc.).

### 6.3 Code Quality & Comment Standardization
* **Comment Refactoring**: Converted all legacy doc block comments (`/** ... */`) to clean, standard inline comments (`//`) across models, mailers, middleware, and database factories for consistent codebase readability.

