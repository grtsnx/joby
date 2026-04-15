# <img src="assets/icon.png" width="80" height="80" align="center"> Joby Sync

**Premium Overhaul** — A premium, high-performance WordPress plugin designed to dynamically fetch and sync thousands of remote jobs directly into your database. Refined for speed, beauty, and global availability.

---

## 🌟 What's New?

- **✨ Premium UI**: A pure minimalist, "Clean-Light" design with a premium dark header.
- **🌍 Multi-Provider Engine**: Support for **Adzuna** (US/UK/EU) and **Arbeitnow** (Global Remote).
- **🇳🇬 Nigeria Ready**: **Arbeitnow** integration provides instant access to global remote jobs for the Nigerian market with **zero-configuration**.
- **⚡ Automated Workflow**: New "Auto-Sync on Save" checkbox for hands-off updates.
- **🔍 Deep Diagnostics**: New "View Raw Data" modal with real-time internal state and last API response logging.
- **🚀 Manual Step Mode**: "Force Process Next Batch" button to bypass WP-Cron limitations and push tasks manually.
- **🔔 Real-time UX**: Sliding "Toast" notifications and deep progress tracking.
- **🛑 Total Control**: Instant "Cancel Sync" functionality that wipes background schedules.

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

## 🛠️ Troubleshooting

### Progress Bar Is Stuck?
If your sync progress isn't moving, it is likely because **WP-Cron** is not triggering on your server.
1. Open the **"View Raw Data"** link in the Sync Activity Logs card.
2. Click the **"Force Process Next Batch"** button.
3. This will manually push the next 5 tasks and bypass the cron system. Each click will move the progress bar.

---

## 🤝 Community

- **Contributing**: Please see [CONTRIBUTING.md](CONTRIBUTING.md) for updated design & code guidelines.
- **Support**: Developed by **Abolade Greatness** ([@grtsnx](https://github.com/grtsnx)).

---

## ⚖️ License

Distributed under the **GPL-2.0 License**. See `LICENSE` for more information.
