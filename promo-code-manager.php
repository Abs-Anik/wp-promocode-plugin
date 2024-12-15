<?php
/*
 * Plugin Name:       Promo Code Manager
 * Plugin URI:        https://turbo-addons.com/
 * Description:       A plugin to manage promo codes with a form submission that downloads a file upon a valid promo code.
 * Version:           1.0.0
 * Requires at least: 5.2
 * Requires PHP:      7.2
 * Author:            Md. Abu Bakkar Siddik
 * Author URI:        https://abs-anik.github.io/anik.github.io/
 * License:           GPLv2
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       pcd-promocode-manager
 */


register_activation_hook(__FILE__, 'pcd_create_tables');
function pcd_create_tables() {
    global $wpdb;
    $promo_table = $wpdb->prefix . 'promo_codes';
    $submission_table = $wpdb->prefix . 'promo_submissions';
    $charset_collate = $wpdb->get_charset_collate();

    $promo_sql = "CREATE TABLE $promo_table (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        promocode varchar(255) NOT NULL,
        status varchar(10) NOT NULL DEFAULT 'available',
        PRIMARY KEY  (id)
    ) $charset_collate;";

    $submission_sql = "CREATE TABLE $submission_table (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        name varchar(255) NOT NULL,
        email varchar(255) NOT NULL,
        promocode varchar(255) NOT NULL,
        PRIMARY KEY  (id)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($promo_sql);
    dbDelta($submission_sql);
}

// Shortcode to display the form
add_shortcode('pcd_form', 'pcd_display_form');
function pcd_display_form() {
    ob_start();
    ?>
    <form method="POST">
        <label for="name">Name:</label><br>
        <input type="text" id="name" name="name" required><br>
        <label for="email">Email:</label><br>
        <input type="email" id="email" name="email" required><br>
        <label for="promocode">Promo Code:</label><br>
        <input type="text" id="promocode" name="promocode" required><br><br>
        <input type="submit" name="submit_promo" value="Submit">
    </form>
    <?php
    return ob_get_clean();
}

add_action('wp', 'pcd_handle_form_submission');
function pcd_handle_form_submission() {
    if (isset($_POST['submit_promo'])) {
        global $wpdb;
        $name = sanitize_text_field($_POST['name']);
        $email = sanitize_email($_POST['email']);
        $promocode = sanitize_text_field($_POST['promocode']);

        $promo_table = $wpdb->prefix . 'promo_codes';
        $promo_data = $wpdb->get_row($wpdb->prepare("SELECT * FROM $promo_table WHERE promocode = %s", $promocode));

        if ($promo_data) {
            if ($promo_data->status == 'used') {
                echo '<script>
                    alert("Sorry, this promo code has already been used. Please try another code.");
                    window.location.href = "https://turbo-addons.com/appsumo-redemption/";
                </script>';
                exit;
            } else {
                $submission_table = $wpdb->prefix . 'promo_submissions';
                $wpdb->insert(
                    $submission_table,
                    [
                        'name' => $name,
                        'email' => $email,
                        'promocode' => $promocode
                    ]
                );
                $wpdb->update($promo_table, ['status' => 'used'], ['id' => $promo_data->id]);

                $file_url = plugins_url('/downloads/promo-code-manager.zip', __FILE__);
                echo '<script>
                    window.location.href = "' . esc_url($file_url) . '";
                    setTimeout(function() {
                        window.location.href = "https://turbo-addons.com/widgets/";
                    }, 10000);
                </script>';
                exit;
            }
        } else {
            echo '<script>
                alert("Invalid promo code. Please try again.");
                window.location.href = "https://turbo-addons.com/appsumo-redemption/";
            </script>';
            exit;
            }
    }
}

// Admin menu for promo code submissions
add_action('admin_menu', 'pcd_create_admin_menu');
function pcd_create_admin_menu() {
    add_menu_page('Promo Submissions', 'Promo Submissions', 'manage_options', 'promo-submissions', 'pcd_display_submissions');
    add_menu_page('Promo Code Manager', 'Promo Code Manager', 'manage_options', 'promo-code-manager', 'pcd_display_promo_manager');
}

function pcd_display_submissions() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'promo_submissions';
    $results = $wpdb->get_results("SELECT * FROM $table_name");

    echo '<div class="wrap"><h1>Promo Code Submissions</h1>';
    if ($results) {
        echo '<table class="widefat fixed" cellspacing="0"><thead><tr><th>ID</th><th>Name</th><th>Email</th><th>Promo Code</th></tr></thead><tbody>';
        foreach ($results as $row) {
            echo '<tr><td>' . esc_html($row->id) . '</td><td>' . esc_html($row->name) . '</td><td>' . esc_html($row->email) . '</td><td>' . esc_html($row->promocode) . '</td></tr>';
        }
        echo '</tbody></table>';
    } else {
        echo '<p>No submissions found.</p>';
    }
    echo '</div>';
}

// Promo code management page
function pcd_display_promo_manager() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'promo_codes';

    if (isset($_POST['submit_promo'])) {
        $promocode = sanitize_text_field($_POST['promocode']);
        $status = sanitize_text_field($_POST['status']);

        if (!empty($_POST['edit_id'])) {
            $wpdb->update($table_name, ['promocode' => $promocode, 'status' => $status], ['id' => intval($_POST['edit_id'])]);
        } else {
            $wpdb->insert($table_name, ['promocode' => $promocode, 'status' => $status]);
        }
    }

    if (isset($_GET['delete'])) {
        $wpdb->delete($table_name, ['id' => intval($_GET['delete'])]);
    }

    $results = $wpdb->get_results("SELECT * FROM $table_name");

    echo '<div class="wrap"><h1>Manage Promo Codes</h1>';
    ?>
    <form method="post" action="">
        <input type="hidden" name="edit_id" value="<?php echo isset($_GET['edit']) ? intval($_GET['edit']) : ''; ?>">
        <table class="form-table">
            <tr>
                <th><label for="promocode">Promo Code</label></th>
                <td><input type="text" name="promocode" id="promocode" required></td>
            </tr>
            <tr>
                <th><label for="status">Status</label></th>
                <td><select name="status" id="status" required>
                    <option value="available">Available</option>
                    <option value="used">Used</option>
                </select></td>
            </tr>
        </table>
        <p class="submit">
            <input type="submit" name="submit_promo" value="Save Promo Code">
        </p>
    </form>

    <h2>Promo Codes List</h2>
    <table class="widefat fixed">
        <thead>
            <tr><th>ID</th><th>Promo Code</th><th>Status</th><th>Actions</th></tr>
        </thead>
        <tbody>
            <?php
            if ($results) {
                foreach ($results as $row) {
                    echo '<tr><td>' . esc_html($row->id) . '</td><td>' . esc_html($row->promocode) . '</td><td>' . esc_html($row->status) . '</td><td><a href="?page=promo-code-manager&edit=' . $row->id . '">Edit</a> | <a href="?page=promo-code-manager&delete=' . $row->id . '">Delete</a></td></tr>';
                }
            } else {
                echo '<tr><td colspan="4">No promo codes found.</td></tr>';
            }
            ?>
        </tbody>
    </table>
    <?php
    echo '</div>';
}
?>
