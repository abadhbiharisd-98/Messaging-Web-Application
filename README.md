# 💬 Messaging Web Application (Render & Aiven Cloud Deployment Ready)

A modern, responsive, real-time PHP & MySQL messaging web application upgraded for **PHP 8.2**, **Render Docker Container hosting**, and **Aiven Cloud MySQL SSL connections**.

---

## 🚀 Live Application & Credentials

| Resource | URL / Value |
| :--- | :--- |
| **Live App URL** | `https://<your-render-service-name>.onrender.com` |
| **Database Installer Tool** | `https://<your-render-service-name>.onrender.com/db_setup.php` |
| **Default Admin Email** | `jsmith@sample.com` |
| **Default Admin Password** | `jsmith` |

### 👥 Other Pre-seeded Demo Users:
- `cblake@sample.com` / `cblake`
- `slou@sample.com` / `slou`
- `mwilliams@sample.com` / `mwilliams`

---

## ⚙️ Aiven Cloud MySQL Environment Variables

When deploying to Render, set these environment variables in your Render Dashboard:

| Variable Name | Example Value | Description |
| :--- | :--- | :--- |
| `DB_SERVER` | `mysql-xxxx-project.aivencloud.com` | Aiven MySQL Service URI / Host |
| `DB_PORT` | `26284` | Aiven MySQL custom 5-digit port |
| `DB_USERNAME` | `avnadmin` | Aiven MySQL Admin User |
| `DB_PASSWORD` | `AVNS_xxxxxxxxxxxxxx` | Aiven MySQL Password |
| `DB_NAME` | `defaultdb` or `messaging_db` | Target Database name |
| `DB_SSL` | `true` | Enforces TLS/SSL connection (`MYSQLI_CLIENT_SSL`) |
| `BASE_URL` | `https://your-service.onrender.com` | (Optional) Your live service URL |

---

## 🛠️ Step-by-Step Render Deployment Guide

### Method 1: 1-Click Blueprint (`render.yaml`)
1. Push this repository to your **GitHub** account.
2. Go to [Render Dashboard](https://dashboard.render.com).
3. Click **New +** > **Blueprint**.
4. Connect this GitHub repository. Render will automatically detect `render.yaml`.
5. Enter your Aiven MySQL credentials in the environment variables form.
6. Click **Apply**. Render will build the Docker container and deploy the app!

### Method 2: Manual Web Service
1. In Render Dashboard, click **New +** > **Web Service**.
2. Select your GitHub repository.
3. Choose **Docker** as the Runtime environment.
4. Set the **Region** (e.g., Oregon or Frankfurt, close to your Aiven MySQL cluster).
5. Choose the **Free** instance type.
6. In **Environment Variables**, add:
   - `DB_SERVER`
   - `DB_PORT`
   - `DB_USERNAME`
   - `DB_PASSWORD`
   - `DB_NAME`
   - `DB_SSL` = `true`
7. Click **Create Web Service**.

---

## 📦 1-Click Database Setup (`db_setup.php`)

Once Render finishes deploying your application:

1. Open your browser and navigate to:
   ```
   https://<your-app-name>.onrender.com/db_setup.php
   ```
2. Verify that the **Database Connection Status** displays a green **Connected** badge with SSL active.
3. Click the green button **"1-Click Import Database to Aiven"**.
4. The tool will parse `messaging_db.sql`, create tables (`users`, `messages`, `convo_list`), and seed the initial users.
5. Click **"Proceed to Login"** and sign in with `jsmith@sample.com` / `jsmith`.

---

## 🔧 Fixes & Upgrades Implemented

### 1. PHP 8.x Compatibility
- Property expressions and function calls inside class definitions moved safely inside `__construct()`.
- Added null-checks and `mysqli_result` instance checks across queries to prevent `Uncaught Error: Call to a member function fetch_assoc() on bool` when tables are empty or initializing.

### 2. Aiven Cloud MySQL SSL & 5-Digit Port Support
- Enforced `mysqli_init()` with `MYSQLI_CLIENT_SSL` and `ssl_set(NULL, NULL, NULL, NULL, NULL)` to satisfy Aiven's `require_secure_transport=ON` policy.
- Disabled client server cert validation options for seamless compatibility with Aiven cloud certificates.
- Full integer port parsing for custom 5-digit ports (e.g. 26284).

### 3. Render Reverse Proxy & Port 10000 Stripping
- Render dynamically assigns the `$PORT` environment variable (typically 10000). The container's startup script `start.sh` dynamically binds Apache to listen on `$PORT`.
- Configured Apache with:
  ```apache
  ServerName localhost
  UseCanonicalName Off
  UseCanonicalPhysicalPort Off
  ```
  This eliminates Apache's default behavior behind reverse proxies where it attaches `:10000` to redirects.
- Configured `.htaccess` with smart port-stripping rules and configured direct file routing (`./index.php` and `./login.php`) to avoid directory slash loops.

### 4. Safe Original Backups
- In accordance with safety rules, all original files have been preserved with `.original` extensions (`DBConnection.php.original`, `Actions.php.original`, `index.php.original`, `login.php.original`, etc.).

---

## 💻 Local Development (Optional)

If running locally with PHP 8.x:
```bash
# If using PHP built-in server:
php -S localhost:8000
```
Then visit `http://localhost:8000/db_setup.php` to verify your local or cloud database connection.
