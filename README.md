# Joby Sync for WordPress


**Joby Sync** is a robust, high-performance WordPress plugin designed to dynamically fetch and sync thousands of remote jobs directly into your database. Built for scale, it handles rate limits and server resources efficiently using a background task queue.

---

## 🌟 Key Features

- **🌍 Global Sync**: Connect multiple countries (NG, US, UK, etc.) in a single dashboard.
- **⚡ Smart Queueing**: Processes jobs in batches of 50 to respect API limits and server health.
- **🧹 Auto-Purge**: Automatically deletes expired jobs to keep your database lean.
- **📅 Background Sync**: Fully automated daily updates powered by WP-Cron.
- **🛠️ Flexible Config**: Admin-controlled job targets per country.

---

## 📋 Requirements

- **PHP**: 7.4+
- **WordPress**: 5.0+
- **System**: cURL enabled & WP-Cron active.
- **API Credentials**: App ID and App Key from your provider.

---

## 🚀 Quick Start

### 1. Installation
1. Download the latest **[joby-sync.zip](https://github.com/grtsnx/joby/releases/latest)**.
2. Go to **Plugins > Add New > Upload Plugin** in your WordPress dashboard.
3. Upload and **Activate**.

### 2. Configuration
- Navigate to the **Joby Sync** menu.
- Enter your **API Credentials**.
- Add countries (e.g., `ng` for Nigeria, `us` for USA) and set your desired job counts.

### 3. Execution
- Click **Manual Sync** for an immediate pull, or let the **Daily Sync** handle it automatically.

---

## 🛠️ Developer Info

| Type | Identifier |
| :--- | :--- |
| **Post Type** | `ajs_job` |
| **Taxonomy** | `ajs_country` |
| **API Limit** | 250 calls / 12,500 jobs daily |

**Meta Fields**: 
`_ajs_remote_id`, `_ajs_location`, `_ajs_company`, `_ajs_redirect_url`, `_ajs_salary_min`, `_ajs_salary_max`.

---

## 🤝 Community

- **Contributing**: Please see [CONTRIBUTING.md](CONTRIBUTING.md) for guidelines.
- **Security**: Report vulnerabilities privately via the [Security Policy](SECURITY.md).
- **Support**: Developed by **Abolade Greatness** ([@grtsnx](https://github.com/grtsnx)).

---

## ⚖️ License

Distributed under the **GPL-2.0 License**. See `LICENSE` for more information.
