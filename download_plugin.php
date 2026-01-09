<?php
/**
 * VantixDash - Dynamischer Plugin Generator
 * Baut strukturierte Daten für Plugins und Themes in die API ein.
 */

// Dashboard URL aus der View abgreifen
$origin = isset($_GET['origin']) ? $_GET['origin'] : '*';
$origin = htmlspecialchars(rtrim($origin, '/'), ENT_QUOTES, 'UTF-8');

$pluginName = "vantixdash-child";
$zipName = "vantixdash-child.zip";

header('Content-Type: application/zip');
header('Content-Disposition: attachment; filename="' . $zipName . '"');
header('Pragma: no-cache');
header('Expires: 0');

$zip = new ZipArchive();
$tempFile = tmpfile();
$tempFilePath = stream_get_meta_data($tempFile)['uri'];

if ($zip->open($tempFilePath, ZipArchive::CREATE | ZipArchive::OVERWRITE) === TRUE) {
    
    // Generierung des Plugin-Codes
    $pluginCode = <<<EOD
<?php
/**
 * Plugin Name: VantixDash Child
 * Description: Sicherer Connector für dein VantixDash Monitoring (Zugelassen für $origin).
 * Version: 1.6.0
 * Author: VantixDash
 */

if (!defined('ABSPATH')) exit;

/**
 * 1. CORS-Sicherheit
 */
add_action('rest_api_init', function() {
    remove_filter('rest_pre_serve_request', 'rest_send_cors_headers');
    add_filter('rest_pre_serve_request', function(\$value) {
        header('Access-Control-Allow-Origin: $origin'); 
        header('Access-Control-Allow-Methods: GET');
        header('Access-Control-Allow-Headers: X-Vantix-Secret, Content-Type');
        header('Access-Control-Allow-Credentials: true');
        return \$value;
    });
}, 15);

/**
 * 2. Einstellungsseite
 */
add_action('admin_menu', function() {
    add_options_page('VantixDash', 'VantixDash', 'manage_options', 'vantixdash-settings', function() {
        if (isset(\$_POST['vantix_key'])) {
            update_option('vantix_api_key', sanitize_text_field(\$_POST['vantix_key']));
            echo '<div class="updated"><p>API Key erfolgreich gespeichert!</p></div>';
        }
        \$key = get_option('vantix_api_key');
        ?>
        <div class="wrap">
            <h1>VantixDash Child Verbindung</h1>
            <p>Konfiguriert für Dashboard: <code>$origin</code></p>
            <form method="post">
                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="vantix_key">API Key</label></th>
                        <td><input name="vantix_key" type="text" id="vantix_key" value="<?php echo esc_attr(\$key); ?>" class="regular-text font-monospace"></td>
                    </tr>
                </table>
                <?php submit_button('Verbindung speichern'); ?>
            </form>
        </div>
        <?php
    });
});

/**
 * 3. Auto-Login
 */
add_action('init', function() {
    if (isset(\$_GET['vantix_token'])) {
        \$data = get_option('vantix_login_token');
        if (\$data && hash_equals(\$data['token'], \$_GET['vantix_token']) && time() < \$data['expires']) {
            delete_option('vantix_login_token');
            wp_set_auth_cookie(\$data['user_id']);
            wp_redirect(admin_url());
            exit;
        }
    }
});

/**
 * 4. API-Schnittstellen (Status & Login)
 */
add_action('rest_api_init', function () {
    \$auth_check = function(\$request) {
        \$auth_header = \$request->get_header('X-Vantix-Secret');
        \$saved_key = get_option('vantix_api_key');
        return (\$saved_key && \$auth_header === \$saved_key);
    };

    register_rest_route('vantixdash/v1', '/status', [
        'methods' => 'GET',
        'callback' => function () {
            require_once(ABSPATH . 'wp-admin/includes/update.php');
            require_once(ABSPATH . 'wp-admin/includes/plugin.php');
            require_once(ABSPATH . 'wp-admin/includes/theme.php');
            
            \$up_p = get_site_transient('update_plugins');
            \$up_t = get_site_transient('update_themes');
            \$up_c = get_site_transient('update_core');

            // CORE Updates
            \$core_updates = 0;
            if (isset(\$up_c->updates) && is_array(\$up_c->updates)) {
                foreach(\$up_c->updates as \$u) { 
                    if(\$u->response === 'upgrade') { \$core_updates = 1; break; } 
                }
            }

            // PLUGINS Liste
            \$p_list = [];
            if (!empty(\$up_p->response)) {
                foreach (\$up_p->response as \$path => \$data) {
                    \$info = get_plugin_data(WP_PLUGIN_DIR . '/' . \$path);
                    \$p_list[] = [
                        'name' => \$info['Name'] ?: \$path,
                        'old_version' => \$info['Version'],
                        'new_version' => \$data->new_version
                    ];
                }
            }

            // THEMES Liste
            \$t_list = [];
            if (!empty(\$up_t->response)) {
                foreach (\$up_t->response as \$slug => \$data) {
                    \$theme = wp_get_theme(\$slug);
                    \$t_list[] = [
                        'name' => \$theme->exists() ? \$theme->get('Name') : \$slug,
                        'old_version' => \$theme->exists() ? \$theme->get('Version') : '?',
                        'new_version' => \$data['new_version']
                    ];
                }
            }

            return [
                'version' => get_bloginfo('version'),
                'php'     => phpversion(),
                'core'    => \$core_updates,
                'plugins' => count(\$p_list),
                'themes'  => count(\$t_list),
                'plugin_list' => \$p_list,
                'theme_list'  => \$t_list,
                'ip'      => \$_SERVER['SERVER_ADDR'] ?? ''
            ];
        },
        'permission_callback' => \$auth_check
    ]);

    register_rest_route('vantixdash/v1', '/login', [
        'methods' => 'GET',
        'callback' => function() {
            \$token = bin2hex(random_bytes(20));
            \$admin = get_users(['role' => 'administrator', 'number' => 1])[0];
            update_option('vantix_login_token', [
                'token' => \$token, 
                'user_id' => \$admin->ID, 
                'expires' => time() + 60
            ]);
            return ['login_url' => home_url('?vantix_token=' . \$token)];
        },
        'permission_callback' => \$auth_check
    ]);
});
EOD;

    // Datei zum ZIP hinzufügen
    $zip->addFromString($pluginName . '/' . $pluginName . '.php', $pluginCode);
    $zip->close();

    // ZIP an den Browser senden
    readfile($tempFilePath);
}
exit;
