<?php
/*
Plugin Name: WP Telegram Backup
Plugin URI: https://github.com/groundcat/wp-telegram-backup
Description: Backup WordPress site to Telegram via a Telegram bot.
Version: 1.1
License: MIT
*/

// Add the plugin menu to the WordPress admin panel
add_action('admin_menu', 'wp_telegram_backup_menu');

// Register the plugin settings
add_action('admin_init', 'wp_telegram_backup_settings');

// Add the cron job to schedule the backups
add_action('wp_telegram_backup_cron', 'wp_telegram_backup');

function wp_telegram_backup_menu() {
  // Add the plugin menu to the Tools section of the WordPress admin panel
  add_submenu_page('tools.php', 'WP Telegram Backup Settings', 'WP Telegram Backup', 'manage_options', 'wp-telegram-backup-settings', 'wp_telegram_backup_settings_page');
}

function wp_telegram_backup_settings() {
  // Register the plugin settings
  register_setting('wp_telegram_backup_settings', 'wp_telegram_backup_token');
  register_setting('wp_telegram_backup_settings', 'wp_telegram_backup_chat_id');
}

function wp_telegram_backup_settings_page() {
  // Display the plugin settings page
  ?>
  <div class="wrap">
    <h1>WP Telegram Backup Settings</h1>
    <form method="post" action="options.php">
      <?php settings_fields('wp_telegram_backup_settings'); ?>
      <?php do_settings_sections('wp_telegram_backup_settings'); ?>
      <table class="form-table">
        <tr valign="top">
          <th scope="row">Telegram Bot Token</th>
          <td><input type="text" name="wp_telegram_backup_token" value="<?php echo esc_attr(get_option('wp_telegram_backup_token')); ?>" /></td>
        </tr>
        <tr valign="top">
          <th scope="row">Telegram Chat ID</th>
          <td><input type="text" name="wp_telegram_backup_chat_id" value="<?php echo esc_attr(get_option('wp_telegram_backup_chat_id')); ?>" /></td>
        </tr>
      </table>
      <?php submit_button(); ?>
      <button type="button" class="button" onclick="wp_telegram_backup_now()">Backup Now</button>
    </form>
  </div>
  <script>
    function wp_telegram_backup_now() {
      // Trigger the backup immediately
      var xhr = new XMLHttpRequest();
      xhr.open('POST', '<?php echo admin_url('admin-ajax.php'); ?>', true);
      xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded; charset=UTF-8');
      xhr.send('action=wp_telegram_backup_now');
      alert('Backup started!');
    }
  </script>
  <?php
  
}

function wp_telegram_backup() {
  // Get the plugin settings
  $token = get_option('wp_telegram_backup_token');
  $chat_id = get_option('wp_telegram_backup_chat_id');

  // Create a temporary directory for the backup
  $site_name = preg_replace("/[^A-Za-z0-9]/", "", get_bloginfo('name'));
  $backup_dir = wp_upload_dir()['basedir'] . '/' . $site_name . '-wp-telegram-backup';
  if (!is_dir($backup_dir)) {
    mkdir($backup_dir);
  }
  
  // Add a .htaccess file to block external access to the directory
  $htaccess_file = $backup_dir . '/.htaccess';
  $htaccess_content = "Deny from all\n";
  file_put_contents($htaccess_file, $htaccess_content);

  // Backup the WordPress database
  $db_backup = $backup_dir . '/' . $site_name . '-wp-db.sql';
  $db_host = DB_HOST;
  $db_name = DB_NAME;
  $db_user = DB_USER;
  $db_password = DB_PASSWORD;
  $command = "mysqldump --add-drop-table --no-tablespaces --host=$db_host --user=$db_user --password=$db_password $db_name > $db_backup";
  exec($command);

  // Backup the WordPress files
  $file_backup = $backup_dir . '/' . $site_name . '-wp-files.tar.gz';
  $dir = get_home_path();
  exec("tar -czf '{$file_backup}' --exclude='*.tar.gz' --exclude='*.tar.gz' '{$dir}' '{$db_backup}'");

  // Split the backup into 49MB chunks
  $split_cmd = "split -b 49m '$file_backup' '$backup_dir/{$site_name}-wp-files.tar.gz.part-'";
  exec($split_cmd);

  // Upload the backup parts to Telegram
  $url = "https://api.telegram.org/bot{$token}/sendDocument";
  $parts = glob("$backup_dir/{$site_name}-wp-files.tar.gz.part-*");
  foreach ($parts as $part) {
    $file = new CURLFile($part);
    $post_fields = array(
      'chat_id' => $chat_id,
      'document' => $file,
    );
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $post_fields);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $result = curl_exec($ch);
    curl_close($ch);
  }

  // Remove the temporary backup directory
  if (is_dir($backup_dir)) {
    $files = new RecursiveIteratorIterator(
      new RecursiveDirectoryIterator($backup_dir, RecursiveDirectoryIterator::SKIP_DOTS),
      RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($files as $fileinfo) {
      $todo = ($fileinfo->isDir() ? 'rmdir' : 'unlink');
      $todo($fileinfo->getRealPath());
    }
    rmdir($backup_dir);
  }
}

// Register the cron job
add_action('wp_telegram_backup_cron', 'wp_telegram_backup');
if (!wp_next_scheduled('wp_telegram_backup_cron')) {
  wp_schedule_event(time(), 'daily', 'wp_telegram_backup_cron');
}

// Add AJAX endpoint for immediate backup
add_action('wp_ajax_wp_telegram_backup_now', 'wp_telegram_backup_now');
function wp_telegram_backup_now() {
  wp_telegram_backup();
  wp_die();
}

