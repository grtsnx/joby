# <img src="assets/icon.png" width="80" height="80" align="center"> Joby Sync v2.0

**The v2.0 Overhaul** — A premium, high-performance WordPress plugin designed to dynamically fetch and sync thousands of remote jobs directly into your database. Refined for speed, beauty, and global availability.

---

## 🌟 What's New in v2.0?

- **✨ Premium UI**: A pure minimalist, "Clean-Light" design with a premium dark header.
- **🌍 Multi-Provider Engine**: Support for **Adzuna** (US/UK/EU) and **Arbeitnow** (Global Remote).
- **🇳🇬 Nigeria Ready**: **Arbeitnow** integration provides instant access to global remote jobs for the Nigerian market with **zero-configuration**.
- **⚡ Automated Workflow**: New "Auto-Sync on Save" checkbox for hands-off updates.
- **🔔 Real-time UX**: Sliding "Toast" notifications and deep progress tracking.
- **🛑 Total Control**: Instant "Cancel Sync" functionality to stop active tasks.

---

## 📋 Requirements

- **PHP**: 7.4+
- **WordPress**: 5.0+
- **System**: cURL enabled & WP-Cron active.
- **Providers**: 
  - **Adzuna**: Requires API Credentials.
  - **Arbeitnow**: **Zero-config** (Public API).

---

## 🚀 Quick Start

### 1. Installation
1. Download the latest **[joby-sync.zip](https://github.com/grtsnx/joby/releases/latest)**.
2. Go to **Plugins > Add New > Upload Plugin** in your WordPress dashboard.
3. Upload and **Activate**.

### 2. Configuration
- Navigate to the **Joby Sync** menu.
- **Choose Your Provider**: Pick "Arbeitnow" for instant global remote jobs, or "Adzuna" for local market focus.
- **Enable Auto-Sync**: Check the box to start fetching immediately after saving.

### 3. Monitoring
- Use the **Settings Dashboard** to track real-time sync progress.
- Click **"View Synced Jobs"** to manage your imported listings.

---

## 🛠️ Developer Architecture

| Component | Description |
| :--- | :--- |
| **Provider Factory** | `Joby_API` – Instantiates provider classes dynamically. |
| **Interface** | `Joby_Provider_Interface` – Foundation for adding new APIs. |
| **Queue Engine** | `Joby_Sync_Engine` – Handles batch processing and rate limiting. |

**Meta Fields**: 
`_ajs_remote_id`, `_ajs_location`, `_ajs_company`, `_ajs_url`, `_ajs_type`, `_ajs_salary`.

---

## 🤝 Community

- **Contributing**: Please see [CONTRIBUTING.md](CONTRIBUTING.md) for updated design & code guidelines.
- **Support**: Developed by **Abolade Greatness** ([@grtsnx](https://github.com/grtsnx)).

---

## ⚖️ License

Distributed under the **GPL-2.0 License**. See `LICENSE` for more information.
