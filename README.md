# Joby Sync for WordPress

Dynamically fetch thousands of jobs daily from a remote job board API and store them directly in your WordPress database as Custom Post Types.

## Requirements
To use this plugin, ensure your server meets the following:
- **PHP 7.4 or higher**
- **WordPress 5.0 or higher**
- **Remote API Credentials** (App ID & App Key) - Get these from the job board provider.
- **WP-Cron** must be enabled for automatic daily syncing.
- **cURL support** (required for `wp_remote_get`).

## Key Features
- **Global Coverage**: Sync jobs from any supported country (e.g., Nigeria, UK, US, Canada, etc.).
- **Dynamic Fetching**: Configure target job counts per country (e.g., fetch 1,000 jobs for Nigeria and 500 for Ghana).
- **Background Sync**: Processes jobs in small batches (50 per call) using a robust Task Queue to avoid server timeouts and respect API rate limits (250 calls/day).
- **Auto-Cleanup**: Automatically removes "stale" jobs that are no longer available in the latest fetch, keeping your database fresh.
- **Premium Interface**: A modern, easy-to-use admin dashboard to manage your API keys and country settings.

## Installation & Setup

### 1. Upload & Activate
- Upload the `joby-sync` folder to your `/wp-content/plugins/` directory.
- Go to **Plugins** in your WordPress dashboard and click **Activate** on "Joby Sync".
- Upon activation, you will be redirected to the settings page.

### 2. Configure API Keys
- Sign up for a free developer account at the provider's portal.
- Copy your **App ID** and **App Key** and paste them into the plugin settings.

### 3. Add Countries
- In the "Countries & Job Targets" section, add the countries you want to track.
- Use **2-letter ISO codes** (e.g., `ng` for Nigeria, `us` for Global US, `gb` for UK).
- Set your target job count in multiples of 50.

### 4. Running the Sync
- The plugin is configured to run a full sync **Daily** using WordPress Cron.
- You can trigger a **Manual Sync** anytime by clicking the button on the dashboard.
- The sync runs in the background. You can check the dashboard to see progress.

## Technical Details
- **CPT**: `ajs_job` (Joby Jobs)
- **Taxonomy**: `ajs_country` (Job Country)
- **Meta Fields**: `_ajs_remote_id`, `_ajs_location`, `_ajs_company`, `_ajs_redirect_url`, `_ajs_salary_min`, `_ajs_salary_max`.
- **API Limit**: The plugin is designed to fit 12,500 jobs (250 calls x 50 jobs) within the 24-hour limit.

## Support
For support or customization, please contact the developer.
