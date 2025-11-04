<?php
if (!defined('ABSPATH')) exit;

class SPFCW_Admin
{
    private $api;

    public function __construct($api)
    {
        $this->api = $api;
        add_action('admin_menu', [$this, 'menu']);
        add_action('admin_post_spfcw_add_product', [$this, 'handle_add_product']);
        add_action('admin_post_spfcw_add_all_products', [$this, 'handle_add_all_products']);
        add_action('spfcw_process_bulk_import', [$this, 'process_bulk_import']); // رویداد پس‌زمینه
    }

    public function menu()
    {
        add_menu_page('SPFCW', 'SPFCW', 'manage_options', 'spfcw', [$this, 'page_products'], 'dashicons-store');
        add_submenu_page('spfcw', 'Settings', 'Settings', 'manage_options', 'spfcw-settings', [$this, 'page_settings']);
    }

    /** 📦 صفحه محصولات */
    public function page_products()
    {
        echo '<div class="wrap"><h1>📦 Import Products from Main Site</h1>';

        // پیام‌ها
        if (!empty($_GET['msg'])) {
            if ($_GET['msg'] === 'queued') {
                echo '<div class="updated"><p>🕐 محصولات در حال افزوده شدن هستند... لطفاً چند دقیقه دیگر صفحه را رفرش کنید.</p></div>';
            } elseif ($_GET['msg'] === 'added') {
                echo '<div class="updated"><p>✅ محصول با موفقیت افزوده شد.</p></div>';
            } elseif ($_GET['msg'] === 'done') {
                echo '<div class="updated"><p>✅ همه محصولات با موفقیت افزوده شدند!</p></div>';
            } elseif ($_GET['msg'] === 'exists') {
                echo '<div class="notice notice-warning"><p>⚠️ این محصول قبلاً افزوده شده است.</p></div>';
            }
        }

        $products = $this->api->get_products();

        // دکمه افزودن همه
        echo '
            <form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" style="margin-bottom:15px;">
                <input type="hidden" name="action" value="spfcw_add_all_products">
                ' . wp_nonce_field('spfcw_add_all', '_wpnonce', true, false) . '
                <button class="button button-primary">🚀 افزودن همه محصولات</button>
            </form>
        ';

        if (is_wp_error($products)) {
            echo '<div class="error"><p>' . esc_html($products->get_error_message()) . '</p></div></div>';
            return;
        }

        echo '<table class="widefat"><thead><tr><th>تصویر</th><th>عنوان</th><th>قیمت</th><th></th></tr></thead><tbody>';

        foreach ($products as $p) {
            $id = intval($p['id']);
            $exists = get_posts([
                'post_type'  => 'product',
                'meta_key'   => 'spfcw_main_id',
                'meta_value' => $id,
                'fields'     => 'ids',
                'numberposts' => 1
            ]);

            echo '<tr>';
            echo '<td><img src="' . esc_url($p['image']) . '" width="50"/></td>';
            echo '<td>' . esc_html($p['title']) . '</td>';
            echo '<td>' . esc_html($p['price']) . '</td>';

            if (!empty($exists)) {
                echo '<td><button class="button" disabled>✅ افزوده شده</button></td>';
            } else {
                echo '<td>
                    <form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">
                        <input type="hidden" name="action" value="spfcw_add_product">
                        <input type="hidden" name="product_id" value="' . $id . '">
                        ' . wp_nonce_field('spfcw_add_' . $id, '_wpnonce', true, false) . '
                        <button class="button button-secondary">➕ افزودن به فروشگاه من</button>
                    </form>
                </td>';
            }

            echo '</tr>';
        }

        echo '</tbody></table></div>';
    }

    /** ➕ افزودن محصول تکی */
    public function handle_add_product()
    {
        if (!current_user_can('manage_options')) wp_die('No access');
        $id = intval($_POST['product_id']);
        if (!wp_verify_nonce($_POST['_wpnonce'], 'spfcw_add_' . $id)) wp_die('Invalid nonce');

        $product = $this->api->get_product($id);
        if (is_wp_error($product)) wp_die($product->get_error_message());

        $this->import_single_product($product);

        wp_redirect(admin_url('admin.php?page=spfcw&msg=added'));
        exit;
    }

    /** 🚀 افزودن همه محصولات (در پس‌زمینه) */
    public function handle_add_all_products()
    {
        if (!current_user_can('manage_options')) wp_die('No access');
        if (!wp_verify_nonce($_POST['_wpnonce'], 'spfcw_add_all')) wp_die('Invalid nonce');

        // برنامه‌ریزی رویداد پس‌زمینه
        wp_schedule_single_event(time() + 5, 'spfcw_process_bulk_import');
        wp_redirect(admin_url('admin.php?page=spfcw&msg=queued'));
        exit;
    }

    /** 🧠 انجام واقعی ایمپورت گروهی (در پس‌زمینه) */
    public function process_bulk_import()
    {
        $products = $this->api->get_products();
        if (is_wp_error($products)) return;

        foreach ($products as $p) {
            $exists = get_posts([
                'post_type'  => 'product',
                'meta_key'   => 'spfcw_main_id',
                'meta_value' => $p['id'],
                'fields'     => 'ids',
                'numberposts' => 1
            ]);
            if (!empty($exists)) continue;

            $single = $this->api->get_product($p['id']);
            if (is_wp_error($single)) continue;

            $this->import_single_product($single);
        }

        // علامت پایان کار برای پیام بعدی
        update_option('spfcw_import_done', time());
    }

    /** 🧩 ایمپورت تکی محصول */
    private function import_single_product($product)
    {
        if (empty($product['id'])) return;

        $exists = get_posts([
            'post_type'  => 'product',
            'meta_key'   => 'spfcw_main_id',
            'meta_value' => $product['id'],
            'fields'     => 'ids',
            'numberposts' => 1
        ]);
        if (!empty($exists)) return;

        $post_id = wp_insert_post([
            'post_type'   => 'product',
            'post_title'  => $product['title'],
            'post_content' => $product['description'],
            'post_status' => 'publish'
        ]);

        update_post_meta($post_id, 'spfcw_main_id', $product['id']);
        update_post_meta($post_id, '_price', $product['price']);
        update_post_meta($post_id, '_regular_price', $product['price']);
        update_post_meta($post_id, '_sku', $product['sku']);
        update_post_meta($post_id, '_product_url', $product['permalink']);
        update_post_meta($post_id, '_button_text', 'خرید از سایت اصلی');
        wp_set_object_terms($post_id, 'external', 'product_type');

        if (!empty($product['image'])) {
            $img_id = media_sideload_image($product['image'], $post_id, $product['title'], 'id');
            if (!is_wp_error($img_id)) set_post_thumbnail($post_id, $img_id);
        }
    }

    /** ⚙️ صفحه تنظیمات */
    public function page_settings()
    {
        if (isset($_POST['main_site_url'])) {
            check_admin_referer('spfcw_settings');
            $opts = [
                'main_site_url' => esc_url_raw($_POST['main_site_url']),
                'api_key'       => sanitize_text_field($_POST['api_key'])
            ];
            update_option('spfcw_settings', $opts);
            echo '<div class="updated"><p>Saved.</p></div>';
        }

        $opts = get_option('spfcw_settings', []);
?>
        <div class="wrap">
            <h1>SPFCW Settings</h1>
            <form method="post">
                <?php wp_nonce_field('spfcw_settings'); ?>
                <table class="form-table">
                    <tr>
                        <th>Main Site URL</th>
                        <td><input type="text" name="main_site_url" value="<?php echo esc_attr($opts['main_site_url'] ?? ''); ?>" class="regular-text"></td>
                    </tr>
                    <tr>
                        <th>API Key</th>
                        <td><input type="text" name="api_key" value="<?php echo esc_attr($opts['api_key'] ?? ''); ?>" class="regular-text"></td>
                    </tr>
                </table>
                <?php submit_button(); ?>
            </form>
        </div>
<?php
    }
}
