<?php
if ( !in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {
    return;
}
// update_option(Opt_Mecom_Proxies, array(), true);

require_once(plugin_dir_path(__FILE__) . 'utils.php');

add_action('restrict_manage_posts', function () {
    global $typenow;
    filter_orders_by_sync_status($typenow);
},
    20, 1);
if (get_option('woocommerce_custom_orders_table_enabled') === 'yes') {
    add_action('woocommerce_order_list_table_extra_tablenav', 'filter_orders_by_sync_status', 20);
}
function filter_orders_by_sync_status($type) {
    if (defined('cs_pp_filter_orders_by_sync_status_ran')) {
        return;
    }
    define('cs_pp_filter_orders_by_sync_status_ran', 1);
    if ( 'shop_order' === $type ) {
        ?>
        <select name="_shop_order_sync_status" id="dropdown_shop_order_sync_status">
            <option value="">Filter by PayPal tracking sync status</option>
            <option value="<?= OPT_CS_PAYPAL_NOT_SYNCED ?>" <?php echo esc_attr( isset( $_GET['_shop_order_sync_status'] ) ? selected( OPT_CS_PAYPAL_NOT_SYNCED, wc_clean( $_GET['_shop_order_sync_status'] ), false ) : '' ); ?>>
                Unsynced
            </option>
            <option value="<?= OPT_CS_PAYPAL_SYNCED ?>" <?php echo esc_attr( isset( $_GET['_shop_order_sync_status'] ) ? selected( OPT_CS_PAYPAL_SYNCED, wc_clean( $_GET['_shop_order_sync_status'] ), false ) : '' ); ?>>
                Synced
            </option>
            <option value="<?= OPT_CS_PAYPAL_SYNC_ERROR ?>" <?php echo esc_attr( isset( $_GET['_shop_order_sync_status'] ) ? selected( OPT_CS_PAYPAL_SYNC_ERROR, wc_clean( $_GET['_shop_order_sync_status'] ), false ) : '' ); ?>>
                Sync error
            </option>
        </select>
        <?php
    }
}

add_action('manage_posts_extra_tablenav',
    function ($which) {
        global $typenow;
        admin_order_list_top_bar_button($typenow, $which);
    },
    20, 1);

if (get_option('woocommerce_custom_orders_table_enabled') === 'yes') {
    add_action('woocommerce_order_list_table_extra_tablenav', 'admin_order_list_top_bar_button', 20, 2);
}

function admin_order_list_top_bar_button( $type, $which ) {
    if ( 'shop_order' === $type && 'top' === $which ) {
        wp_register_style("mecom_woo_css", plugins_url('assets/css/woo_styles.css', __FILE__), [], OPT_MECOM_PAYPAL_VERSION);
        wp_enqueue_style('mecom_woo_css');

        wp_register_script("mecom_woo_scripts", plugins_url('assets/js/woo_scripts.js', __FILE__), [], OPT_MECOM_PAYPAL_VERSION);
        wp_enqueue_script("mecom_woo_scripts");
        wp_localize_script('mecom_woo_scripts', 'cs_ajax_object', [ 'ajax_url' => admin_url('admin-ajax.php'), 'we_value' => 1234 ] );

        $countOrderNeedSync = countOrderNeedSync();
        ?>
        <div class="alignleft actions custom">
            <input id="sync-count" type="hidden" value="<?= $countOrderNeedSync ?>"/>
            <button type="button" class="button button-primary" id="sync-tracking-info-btn">Sync tracking to PayPal: <?= $countOrderNeedSync ?><span class="load loading"></button>
        </div>
        <?php
    }
}

add_action('admin_menu', 'add_mecom_paypal_paygate_menu');
add_action('wp_ajax_mecom_gateway_paypal_action', 'mecom_gateway_paypal_action');

function add_mecom_paypal_paygate_menu()
{
    $mypage = add_menu_page('CardsShield Gateway PayPal Settings', 'CardsShield PayPal', 'manage_options', 'mecom-gateway-paypal', 'mecom_page_init');
    add_action('load-' . $mypage, 'enqueue_scripts_front_end');
}


//enqueue draggable on the front end
function enqueue_scripts_front_end()
{
    // css
    wp_register_style('mecom_bs_css', "https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/css/bootstrap.min.css");
    wp_enqueue_style('mecom_bs_css');

    wp_register_style("mecom_settings_css", plugins_url('assets/css/settings.css', __FILE__), [], OPT_MECOM_PAYPAL_VERSION);
    wp_enqueue_style('mecom_settings_css');


    // js
    wp_register_script("mecom_swal2", plugins_url('assets/js/sweetalert2.all.min.js', __FILE__), [], OPT_MECOM_PAYPAL_VERSION);
    wp_enqueue_script("mecom_swal2");

    wp_register_script("mecom_bs_js", "https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/js/bootstrap.min.js");
    wp_enqueue_script("mecom_bs_js");

    wp_register_script("mecom_settings", plugins_url('assets/js/settings.js', __FILE__), [], OPT_MECOM_PAYPAL_VERSION);
    wp_enqueue_script("mecom_settings");


    // in JavaScript, object properties are accessed as ajax_object.ajax_url, ajax_object.we_value
    wp_localize_script('mecom_settings', 'cs_ajax_object', [ 'ajax_url' => admin_url('admin-ajax.php'), 'we_value' => 1234 ] );
}

/**
 * Handle a custom 'customvar' query var to get orders with the 'customvar' meta.
 * @param array $query - Args for WP_Query.
 * @param array $query_vars - Query vars from WC_Order_Query.
 * @return array modified $query
 */
function handle_tracking_info( $query, $query_vars ) {
    if ( ! empty( $query_vars[ METAKEY_PAYPAL_SYNC_TRACKING_INFO ] ) ) {
        $csPayPalGw = WC()->payment_gateways->payment_gateways()['mecom_paypal'];
        $trackingSyncPlugin = $csPayPalGw->get_option('sync_tracking_plugin');

        $metaQuery = [
            'relation' => 'AND',
            [
                'key'   => METAKEY_PAYPAL_SYNC_TRACKING_INFO,
                'value' => esc_attr( $query_vars[ METAKEY_PAYPAL_SYNC_TRACKING_INFO ] ),
            ],
            [
                'relation' => 'OR',
                [
                    'key'   => METAKEY_CS_PAYPAL_CAPTURED,
                    'compare' => 'NOT EXISTS'
                ],
                [
                    'key'   => METAKEY_CS_PAYPAL_CAPTURED,
                    'value' => 'true'
                ]
            ]
        ];

        switch ( $trackingSyncPlugin ) {
            case OPT_CS_TRACKING_SYNC_PLUGIN_ORDERS_TRACKING:
                break;
            case OPT_CS_TRACKING_SYNC_PLUGIN_DIANXIAOMI:
                $metaQuery[] = [
                    'key'     => '_dianxiaomi_tracking_provider_name',
                    'compare' => '!=',
                    'value'   => null,
                ];
                $metaQuery[] = [
                    'key'     => '_dianxiaomi_tracking_number',
                    'compare' => '!=',
                    'value'   => null,
                ];
                break;
            case OPT_CS_TRACKING_SYNC_PLUGIN_ADVANCED_SHIPMENT_TRACKING:
            default:
                $metaQuery[] = [
                    'key'     => '_wc_shipment_tracking_items',
                    'compare' => '!=',
                    'value'   => null,
                ];
                break;

        }
        $query['meta_query'][] = $metaQuery;
    }

    return $query;
}

add_filter( 'woocommerce_order_data_store_cpt_get_orders_query', 'handle_tracking_info', 10, 2 );

function renderMoneyRow($title,  $tooltip, $value,  $currency,  $negative = false ) {
    /**
     * Bad type hint in WC phpdoc.
     *
     * @psalm-suppress InvalidScalarArgument
     */
    return '
            <tr>
                <td class="label">' . wc_help_tip( $tooltip ) . ' ' . esc_html( $title ) . '
                </td>
                <td width="1%"></td>
                <td class="total">
                    ' .
           ( $negative ? ' - ' : '' ) .
           wc_price( $value, array( 'currency' => $currency ) ) . '
                </td>
            </tr>';
}

function action_woocommerce_admin_order_totals_after_total($order_get_id) {
    $wc_order = wc_get_order( $order_get_id );
    if ( ! $wc_order instanceof WC_Order ) {
        return;
    }

    if ($wc_order->get_payment_method() !== 'mecom_paypal') {
        return;
    }

    $paypalFee      = $wc_order->get_meta( METAKEY_CS_PAYPAL_FEE );
    $paypalCurrency = $wc_order->get_meta( METAKEY_CS_PAYPAL_CURRENCY );
    $paypalPayout   = $wc_order->get_meta( METAKEY_CS_PAYPAL_PAYOUT );

    $html = '';

    if ( isset( $paypalFee ) && isset( $paypalCurrency ) ) {
        $html .= renderMoneyRow( 'PayPal Fee:', 'The fee PayPal collects for the transaction.',
            $paypalFee,
            $paypalCurrency,
            true
        );
    }

    if ( isset( $paypalPayout ) && isset( $paypalCurrency ) ) {
        $html .= renderMoneyRow(
            'PayPal Payout:',
            'The net total that will be credited to your PayPal account.',
            $paypalPayout,
            $paypalCurrency
        );
    }

    echo $html;
}
add_action( 'woocommerce_admin_order_totals_after_total', 'action_woocommerce_admin_order_totals_after_total', 10, 1);

add_filter( 'request', 'filter_orders_by_sync_status_query' );
function filter_orders_by_sync_status_query( $vars ) {
    global $typenow;
    if ( 'shop_order' === $typenow && isset( $_GET['_shop_order_sync_status'] ) && '' != $_GET['_shop_order_sync_status'] ) {
        $vars['meta_query'][] = array(
            'key'       => '_mecom_paypal_sync_tracking_info',
            'value'     => wc_clean( $_GET['_shop_order_sync_status'] ),
            'compare'   => 'LIKE'
        );
    }

    return $vars;
}


add_filter( 'bulk_actions-edit-shop_order', 'cs_register_tracking_sync_bulk_action' );
if (get_option('woocommerce_custom_orders_table_enabled') === 'yes') {
    add_filter('bulk_actions-woocommerce_page_wc-orders', 'cs_register_tracking_sync_bulk_action');
}
function cs_register_tracking_sync_bulk_action( $bulk_actions ) {

    $bulk_actions[ 'cs_change_pp_sync_status_to_synced' ] = 'Change PayPal sync status to synced';
    $bulk_actions[ 'cs_change_pp_sync_status_to_unsynced' ] = 'Change PayPal sync status to unsynced';
    return $bulk_actions;

}

add_action( 'handle_bulk_actions-edit-shop_order', 'cs_bulk_process_pp_tracking_status', 20, 3 );
function cs_bulk_process_pp_tracking_status( $redirect, $doaction, $object_ids ) {

    if( 'cs_change_pp_sync_status_to_synced' === $doaction ) {

        // change status of every selected order
        foreach ( $object_ids as $order_id ) {
            $order = wc_get_order($order_id);
            $order->update_meta_data( METAKEY_PAYPAL_SYNC_TRACKING_INFO, OPT_CS_PAYPAL_SYNCED );
            $order->save_meta_data();
        }

        // do not forget to add query args to URL because we will show notices later
        $redirect = add_query_arg(
            array(
                'bulk_action' => 'cs_change_pp_sync_status_to_synced',
                'changed' => count( $object_ids ),
            ),
            $redirect
        );

    }

    if( 'cs_change_pp_sync_status_to_unsynced' === $doaction ) {

        // change status of every selected order
        foreach ( $object_ids as $order_id ) {
            $order = wc_get_order($order_id);
            $order->update_meta_data( METAKEY_PAYPAL_SYNC_TRACKING_INFO, OPT_CS_PAYPAL_NOT_SYNCED );
            $order->save_meta_data();
        }

        // do not forget to add query args to URL because we will show notices later
        $redirect = add_query_arg(
            array(
                'bulk_action' => 'cs_change_pp_sync_status_to_unsynced',
                'changed' => count( $object_ids ),
            ),
            $redirect
        );

    }

    return $redirect;
}

add_action( 'admin_notices', 'cs_process_pp_tracking_status_notices' );
function cs_process_pp_tracking_status_notices() {

    if(
        isset( $_REQUEST[ 'bulk_action' ] )
        && 'cs_change_pp_sync_status_to_synced' == $_REQUEST[ 'bulk_action' ]
        && isset( $_REQUEST[ 'changed' ] )
        && $_REQUEST[ 'changed' ]
    ) {

        // displaying the message
        printf(
            '<div id="message" class="updated notice is-dismissible"><p>' . _n( '%d order PayPal tracking sync status changed.', '%d order PayPal tracking sync statuses changed.', $_REQUEST[ 'changed' ] ) . '</p></div>',
            $_REQUEST[ 'changed' ]
        );

    }

}


function mecom_gateway_paypal_action()
{
    switch ($_POST['command']) {
        case 'changeRotationMethod':
            changeRotationMethod();
            break;
        case 'addNewProxy':
            addNewProxy();
            break;
        case 'deleteProxy':
            deleteProxy();
            break;
        case 'activateProxy':
            activateProxy();
            break;
        case 'moveToUnusedProxies':
            moveToUnusedProxies();
            break;
        case 'saveProxies':
            saveProxies();
            break;
        case 'moveBackProxies':
            moveBackProxies();
            break;
        case 'syncTrackingInfo':
            syncTrackingInfo();
            break;
        case 'changeConnectionMode':
            changeConnectionMode();
            break;
        case 'saveEndpointSettings':
            saveEndpointSettings();
            break;
        default:
            break;
    }

    wp_die(); // this is required to terminate immediately and return a proper response
}

function changeRotationMethod()
{
    $isSuccess = update_option(OPT_MECOM_PAYPAL_ROTATION_METHOD, $_POST['rotationMethod'], true);
    echo json_encode([
        'success' => $isSuccess
    ]);
}

function activateProxy()
{
    $rotationMethod = $_POST["rotationMethod"];
    $proxyID = $_POST["proxyID"];

    $proxies = get_option(OPT_MECOM_PAYPAL_PROXIES, []);
    foreach ($proxies as $proxy) {
        if ($proxy["id"] == $proxyID) {
            // Active
            update_option(OPT_MECOM_PAYPAL_ACTIVATED_PROXY, $proxy, true);
            if ( $rotationMethod === OPT_CS_PAYPAL_BY_TIME) {
                update_option(OPT_MECOM_PAYPAL_CURRENT_ROTATION_VALUE, time(), true);
            }
            logRotation($rotationMethod, $proxy, "Force");
            echo json_encode([
                'success' => true
            ]);
            return;
        }
    }
    echo json_encode([
        'success' => false
    ]);
}

function deleteProxy()
{
    $deleteProxyIds = $_POST["deleteProxyIds"];
    $proxies = get_option(OPT_MECOM_PAYPAL_UNUSED_PROXIES, []);
    foreach ($proxies as $key => $proxy) {
        if (in_array($proxy['id'], $deleteProxyIds)) {
            unset($proxies[$key]);
        }
    }
    $isSuccess = update_option(OPT_MECOM_PAYPAL_UNUSED_PROXIES, array_values($proxies), true);
    echo json_encode([
        'success' => $isSuccess
    ]);
}

function addNewProxy()
{
    $rotationMethod = $_POST["rotationMethod"];
    $proxyUrl = $_POST["proxyUrl"];
    $rotationValue = $_POST["rotationValue"];

    // Get current proxies
    $proxies = get_option(OPT_MECOM_PAYPAL_PROXIES, []);
    if (empty($proxies)) {
        $proxies = [];
    }
    // Add the new one
    $proxy = [
        'id' => uniqid(),
        'url' => $proxyUrl,
        'paid_amount' => 0
    ];
    if ( $rotationMethod === OPT_CS_PAYPAL_BY_TIME) {
        $proxy['timestamp'] = $rotationValue;
        $proxy['amount'] = 0;
    } else if ( $rotationMethod === OPT_CS_PAYPAL_BY_AMOUNT) {
        $proxy['timestamp'] = 0;
        $proxy['amount'] = $rotationValue;
    }
    $proxies[] = $proxy;
    // Save
    $isSuccess = update_option(OPT_MECOM_PAYPAL_PROXIES, $proxies, true);

    $activatedProxy = get_option(OPT_MECOM_PAYPAL_ACTIVATED_PROXY, null);
    if (empty($activatedProxy)) {
        update_option(OPT_MECOM_PAYPAL_ACTIVATED_PROXY, $proxies[0], true);
        update_option(OPT_MECOM_PAYPAL_CURRENT_ROTATION_VALUE, time(), true);
        update_option(OPT_MECOM_PAYPAL_ROTATION_METHOD, $rotationMethod, true);
    }

    echo json_encode([
        'success' => $isSuccess,
        'addedProxy' => $proxy
    ]);
}

function moveToUnusedProxies() {
    $proxyIds = $_POST["proxyIds"];

    $proxies        = get_option( OPT_MECOM_PAYPAL_PROXIES, [] );
    if (empty($proxies)) {
        $proxies = [];
    }
    $unusedProxies  = get_option( OPT_MECOM_PAYPAL_UNUSED_PROXIES, [] );
    if (empty($unusedProxies)) {
        $unusedProxies = [];
    }
    $activatedProxy = get_option( OPT_MECOM_PAYPAL_ACTIVATED_PROXY, null );
    if(isset($activatedProxy) && in_array($activatedProxy['id'], $proxyIds)) {
        echo json_encode( [
            "success" => false,
            "error" => "Can't move activated proxy to unused list!"
        ] );
        return;
    }
    foreach ( $proxies as $key => $proxy ) {
        if ( in_array( $proxy['id'], $proxyIds ) ) {
            $unusedProxies[] = $proxy;
            unset( $proxies[ $key ] );
        }
    }
    $isSuccess1 = update_option( OPT_MECOM_PAYPAL_PROXIES, array_values($proxies), true );
    $isSuccess2 = update_option( OPT_MECOM_PAYPAL_UNUSED_PROXIES, $unusedProxies, true );
    echo json_encode( [
        "success" => $isSuccess1 && $isSuccess2
    ] );

}

function saveProxies() {
    $rotationMethod = $_POST["rotationMethod"];
    $newProxies = $_POST["proxies"];

    $proxies = get_option( OPT_MECOM_PAYPAL_PROXIES, [] );
    $activatedProxy = get_option( OPT_MECOM_PAYPAL_ACTIVATED_PROXY, null );
    foreach ($proxies as $key => $proxy) {
        if ( $proxy['id'] !== $newProxies[$key]['id']) {
            continue;
        }
        $proxies[$key]['url'] = $newProxies[$key]['url'];
        $proxies[$key]['timestamp'] = $rotationMethod === OPT_CS_PAYPAL_BY_TIME ? $newProxies[$key]['rotationValue'] : $proxies[$key]['timestamp'];
        $proxies[$key]['amount'] = $rotationMethod === OPT_CS_PAYPAL_BY_AMOUNT ? $newProxies[$key]['rotationValue'] : $proxies[$key]['amount'];

        // Update activated proxy
        if (isset($activatedProxy) && $activatedProxy['id'] === $proxy['id']) {
            update_option(OPT_MECOM_PAYPAL_ACTIVATED_PROXY, $proxies[$key], true);
        }
    }
    update_option(OPT_MECOM_PAYPAL_PROXIES, $proxies, true);
    echo json_encode([
        "success" => true
    ]);
}

function moveBackProxies() {
    $moveBackProxyIds = $_POST["moveBackProxyIds"];

    $proxies        = get_option( OPT_MECOM_PAYPAL_PROXIES, [] );
    $needActiveFirstProxy = false;
    if (count($proxies) == 0) {
        $needActiveFirstProxy = true;
    }
    $unusedProxies  = get_option( OPT_MECOM_PAYPAL_UNUSED_PROXIES, [] );
    foreach ( $unusedProxies as $key => $proxy ) {
        if ( in_array( $proxy['id'], $moveBackProxyIds ) ) {
            $proxies[] = $proxy;
            unset( $unusedProxies[ $key ] );
        }
    }
    $isSuccess1 = update_option( OPT_MECOM_PAYPAL_PROXIES, $proxies, true );
    $isSuccess2 = update_option( OPT_MECOM_PAYPAL_UNUSED_PROXIES, array_values($unusedProxies), true );
    if ($needActiveFirstProxy) {
        update_option( OPT_MECOM_PAYPAL_ACTIVATED_PROXY, isset($proxies[0]) ? $proxies[0] : null, true );
    }
    echo json_encode(["success" => $isSuccess1 && $isSuccess2]);
}

function changeConnectionMode() {
    $connectionMode = $_POST["connectionMode"];
    $isSuccess1 = update_option( OPT_MECOM_PAYPAL_CONNECTION_MODE, $connectionMode, true );
    echo json_encode(["success" => $isSuccess1]);
}

function saveEndpointSettings() {
    $endpointToken = $_POST["endpointToken"];
    $endpointSecret = $_POST["endpointSecret"];
    update_option( OPT_CS_PAYPAL_ENDPOINT_TOKEN, $endpointToken, true );
    update_option( OPT_CS_PAYPAL_ENDPOINT_SECRET, $endpointSecret, true );
    echo json_encode(["success" => true]);
}


/**
 * MEcom Paypal Gateway
 */

function mecom_page_init()
{
    $rotationMethod = get_option(OPT_MECOM_PAYPAL_ROTATION_METHOD, OPT_CS_PAYPAL_BY_TIME);
    $connectionMode = get_option(OPT_MECOM_PAYPAL_CONNECTION_MODE, OPT_CS_PAYPAL_CONNECTION_MODE_SHIELD_DOMAINS);
    $endpointToken = get_option(OPT_CS_PAYPAL_ENDPOINT_TOKEN, null);
    $endpointSecret = get_option(OPT_CS_PAYPAL_ENDPOINT_SECRET, null);
    if (empty($rotationMethod)) {
        $rotationMethod = OPT_CS_PAYPAL_BY_TIME;
        update_option(OPT_MECOM_PAYPAL_ROTATION_METHOD, OPT_CS_PAYPAL_BY_TIME, true);
    }

    $proxies = get_option(OPT_MECOM_PAYPAL_PROXIES, [] );
    $unusedProxies = get_option(OPT_MECOM_PAYPAL_UNUSED_PROXIES, [] );
    $activatedProxy = get_option(OPT_MECOM_PAYPAL_ACTIVATED_PROXY, null);

    $countOrderNeedSync = countOrderNeedSync();
    $currency  = get_woocommerce_currency();
    ?>
    <style>
        .by-time {
        <?= $rotationMethod !== OPT_CS_PAYPAL_BY_TIME ? 'display: none' : '' ?>;
        }
        .by-amount {
        <?= $rotationMethod !== OPT_CS_PAYPAL_BY_AMOUNT ? 'display: none' : '' ?>;
        }
    </style>
    <br/>
    <div class="container">
        <h3>CardsShield PayPal Settings</h3>
        <br/>
        <h5>Sync tracking info</h5>
        <div class="sync-tracking-info">
            <button type="button" id="sync-tracking-info-btn" class="btn btn-primary">
                <span id="sync-spinner" class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>
                Sync tracking info
            </button>
            <div class="sync-info">Unsynced orders: <?= $countOrderNeedSync ?></div>
            <input id="sync-count" type="hidden" value="<?= $countOrderNeedSync ?>"/>
        </div>
        <hr style="border-top: 1px solid #333"/>
        <h5>Connection mode</h5>
        <div class="row">
            <div class="col-sm">
                <div class="form-group rotation-method-wrapper">
                    <div class="custom-control custom-radio">
                        <input type="radio" id="connectionMode1" name="connectionMode" value="<?= OPT_CS_PAYPAL_CONNECTION_MODE_SHIELD_DOMAINS ?>" class="custom-control-input" <?= $connectionMode === OPT_CS_PAYPAL_CONNECTION_MODE_SHIELD_DOMAINS ? 'checked' : '' ?>>
                        <label class="custom-control-label" for="connectionMode1">Shield domains</label>
                    </div>
                    <div>Connect with shields directly by shield domains</div>
                    <br>
                    <div class="custom-control custom-radio">
                        <input type="radio" id="connectionMode2" name="connectionMode" value="<?= OPT_CS_PAYPAL_CONNECTION_MODE_ENDPOINT_TOKEN ?>" class="custom-control-input" <?= $connectionMode === OPT_CS_PAYPAL_CONNECTION_MODE_ENDPOINT_TOKEN ? 'checked' : '' ?>>
                        <label class="custom-control-label" for="connectionMode2">Endpoint token</label>
                    </div>
                    <div>Connect with shields by endpoint token. please go to <a href="https://manager.cardsshield.com">manager.cardsshield.com</a> to setup an endpoint token.</div>
                </div>
            </div>
        </div>
        <div id="connection_mode_shield_domains_area" style="<?= $connectionMode == OPT_CS_PAYPAL_CONNECTION_MODE_SHIELD_DOMAINS ? '' :  'display: none;' ?>">
            <hr style="border-top: 1px solid #333"/>
            <h5 style="margin-top: 30px">Rotation settings</h5>
            <div class="row">
                <div class="col-sm">
                    <div class="form-group form-inline rotation-method-wrapper">
                        <label class="rotation-method-label" style="justify-content: left" for="rotation-type">Rotation
                            method: </label>
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="radio" name="rotationMethod" id="rotationByTime"
                                   value="by_time" <?= $rotationMethod === OPT_CS_PAYPAL_BY_TIME ? 'checked' : '' ?> >
                            <label class="form-check-label" for="rotationByTime">Time</label>
                        </div>
                        <div class="form-check form-check-inline">
                            <input class="form-check-input" type="radio" name="rotationMethod" id="rotationByAmount"
                                   value="by_amount" <?= $rotationMethod === OPT_CS_PAYPAL_BY_AMOUNT ? 'checked' : '' ?> >
                            <label class="form-check-label" for="rotationByAmount">Amount (per day)</label>
                        </div>
                        <div class="by-amount" style="font-size: 0.85rem; color: gray; width: 100%;">
                            *The gateway will not show if cannot affort order total amount.
                        </div>
                    </div>

                </div>
            </div>
            <div class="row">
                <div class="col-sm">
                    <table class="table table-proxy table-hover table-borderless">
                        <thead>
                        <tr>
                            <th scope="col" class="checkbox-col"></th>
                            <th scope="col" class="proxy-url-col">Shield URL</th>
                            <th scope="col" class="rotation-value-col">
                                <span class="by-time">Time(min)</span>
                                <span class="by-amount">Amount(<?= $currency ?>/day)</span>
                            </th>
                            <th scope="col" class="control-button-col"></th>
                        </tr>
                        <tr>
                            <th></th>
                            <th>
                                <input type="text" class="form-control proxy-url" id="new-proxy-url">
                            </th>
                            <th>
                                <input type="number" class="form-control proxy-rotation-value" id="new-rotation-value">
                            </th>
                            <th>
                                <button id="btn-add-proxy" class="btn btn-info" type="button">Add</button>
                            </th>
                        </tr>
                        <tr style="border-top: 1px solid rgba(0,0,0,.1)">
                            <th></th>
                            <th>
                                <b>Rotation List</b>
                            </th>
                            <th></th>
                            <th class="today-paid-amount"><span class="by-amount">Rev.</span></th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php
                        foreach ($proxies as $proxy) {
                            $proxy["rotationValue"] = $rotationMethod === OPT_CS_PAYPAL_BY_TIME ? $proxy["timestamp"] : $proxy["amount"];
                            $className = isset($activatedProxy['id']) && $activatedProxy['id'] === $proxy['id'] ? 'activated-proxy' : '';
                            echo "
                                <tr class='proxy {$className}'>
                                    <td>
                                        <input type='checkbox' class='form-control proxy-id' value='{$proxy["id"]}'>
                                    </td>
                                    <td>
                                        <input type='text' class='form-control proxy-url' value='{$proxy["url"]}'>
                                    </td>
                                    <td>
                                        <input type='number' class='form-control proxy-rotation-value' value='{$proxy["rotationValue"]}'>
                                    </td>
                                    <td class='today-paid-amount'>
                                        <span class='by-amount'>{$proxy['paid_amount']}</span>
                                    </td>
                                </tr>
                                ";
                        }
                        ?>
                        </tbody>
                    </table>
                    <div class="control-button">
                        <button id="btn-save" class="btn btn-success mr-4" type="button">Save all</button>
                        <button id="btn-force-active" class="btn btn-primary mr-4" type="button">Force active</button>
                        <button id="btn-move-unused" class="btn btn-danger" type="button">Move to unused</button>
                    </div>

                    <table class="table table-unused table-hover table-borderless">
                        <thead>
                        <tr style="border-top: 1px solid rgba(0,0,0,.1)">
                            <th scope="col" class="checkbox-col"></th>
                            <th scope="col" class="proxy-url-col">
                                <b>Unused List</b>
                            </th>
                            <th scope="col" class="rotation-value-col">
                            <th scope="col" class="control-button-col"></th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php
                        foreach ($unusedProxies as $proxy) {
                            $proxy["rotationValue"] = $rotationMethod === OPT_CS_PAYPAL_BY_TIME ? $proxy["timestamp"] : $proxy["amount"];
                            echo "
                                <tr class='proxy'>
                                    <td>
                                        <input type='checkbox' class='form-control proxy-id' value='{$proxy["id"]}'>
                                    </td>
                                    <td>
                                        <input type='text' class='form-control proxy-url' value='{$proxy["url"]}'>
                                    </td>
                                    <td>
                                        <input type='number' class='form-control proxy-rotation-value' value='{$proxy["rotationValue"]}'>
                                    </td>
                                    <td></td>
                                </tr>
                            ";
                        }
                        ?>
                        </tbody>
                    </table>
                    <div class="control-button">
                        <button id="btn-move-back" class="btn btn-primary mr-4" type="button">Move back</button>
                        <button id="btn-delete" class="btn btn-danger" type="button">Delete</button>
                    </div>

                </div>
            </div>
        </div>
        <div id="connection_mode_endpoint_token_area"
             style="<?= $connectionMode == OPT_CS_PAYPAL_CONNECTION_MODE_ENDPOINT_TOKEN ? '' : 'display: none;' ?>">
            <hr style="border-top: 1px solid #333"/>
            <h5 style="margin-top: 30px">Endpoint settings</h5>
            <div class="row">
            <div class="col-sm">
                <div class="form-group rotation-method-wrapper">
                    <div class="row form-group">
                        <div class="col-md-3">
                            <label>PayPal token</label>
                        </div>
                        <div class="col-md-9">
                            <input class="form-control" name="endpointToken" value="<?= $endpointToken ?>">
                        </div>
                    </div>
                    <div class="row form-group">
                        <div class="col-md-3">
                            <label>Secret Key</label>
                        </div>
                        <div class="col-md-9">
                            <input type="password" class="form-control" name="endpointSecret" value="<?= $endpointSecret ?>">
                        </div>
                    </div>
                    <div class="row form-group">
                        <div class="col-md-3">
                            <label>Remaining balance today</label>
                        </div>
                        <div class="col-md-9">
                            <label id="paypal-ep-amount-remain">None</label>
                        </div>
                    </div>
                    <div class="control-button">
                        <button id="btn-save-endpoint-settings" class="btn btn-primary mr-4" type="button">Save</button>
                        <button id="btn-save-endpoint-cancel" class="btn btn-danger" type="button">Cancel</button>
                    </div>
                </div>
            </div>
        </div>
        </div>
    </div>
    <?php
}
