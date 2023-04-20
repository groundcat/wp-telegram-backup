# WP Telegram Backup

WP Telegram Backup is a WordPress plugin that allows you to backup your WordPress site and upload the backup to Telegram. The plugin creates a database dump and a compressed archive of your WordPress files, splits the archive into 49MB chunks, and uploads the backup parts to Telegram.

## Installation

1. Upload the `wp-telegram-backup` folder to the `/wp-content/plugins/` directory.
2. Activate the plugin through the 'Plugins' menu in WordPress.
3. Go to the 'Tools' menu and configure your Telegram API token and chat ID.

## Usage

To backup your WordPress site and upload the backup to Telegram, simply call the `wp_telegram_backup` function. You can call this function manually or schedule it to run periodically using a WordPress cron job.

Example:

```php
wp_telegram_backup();
```

## Configuration

To configure the plugin, go to the 'Settings' menu and enter your Telegram API token and chat ID. You can obtain your API token from the [Telegram BotFather](https://t.me/botfather), and your chat ID from the [Telegram ID Bot](https://t.me/myidbot).

## License

This plugin is licensed under the MIT License. See the LICENSE file for details.
