<?php
/**
 * XPayr Crypto Payment Gateway for Zen Cart 2.x
 *
 * Non-custodial hosted checkout integration.
 * Redirects the customer to XPayr checkout after order placement,
 * then processes webhook callbacks for order status updates.
 *
 * @version 2.0.0
 * @license GPL-2.0-or-later https://www.gnu.org/licenses/gpl-2.0.html
 * @copyright Copyright (c) 2024 XPayr - https://xpayr.com
 */
if (!defined('IS_ADMIN_FLAG')) {
    die('Illegal Access');
}

/**
 * Render a <select> dropdown for XPayr network selection.
 * Fetches available networks from the XPayr API using the stored secret key.
 *
 * @param string $value Current configuration value
 * @param string $key   Configuration key name
 * @return string HTML select element
 */
function zen_cfg_xpayr_network_select($value, $key = '')
{
    $options = zen_cfg_xpayr_fetch_network_options();
    $name = $key ? 'configuration[' . zen_output_string($key) . ']' : 'configuration_value';
    $html = '<select name="' . $name . '" class="form-control">';
    foreach ($options as $opt) {
        $selected = ($opt['key'] === $value) ? ' selected="selected"' : '';
        $html .= '<option value="' . zen_output_string($opt['key']) . '"' . $selected . '>' . zen_output_string($opt['name']) . '</option>';
    }
    $html .= '</select>';
    return $html;
}

/**
 * Render a <select> dropdown for XPayr currency selection.
 * Fetches available currencies from the XPayr API using the stored secret key.
 *
 * @param string $value Current configuration value
 * @param string $key   Configuration key name
 * @return string HTML select element
 */
function zen_cfg_xpayr_currency_select($value, $key = '')
{
    $options = zen_cfg_xpayr_fetch_currency_options();
    $name = $key ? 'configuration[' . zen_output_string($key) . ']' : 'configuration_value';
    $html = '<select name="' . $name . '" class="form-control">';
    foreach ($options as $opt) {
        $selected = ($opt['key'] === $value) ? ' selected="selected"' : '';
        $html .= '<option value="' . zen_output_string($opt['key']) . '"' . $selected . '>' . zen_output_string($opt['name']) . '</option>';
    }
    $html .= '</select>';
    return $html;
}

/**
 * Fetch network options from XPayr API.
 * Falls back to hardcoded defaults if API is unreachable.
 *
 * @return array List of arrays with 'key' and 'name'
 */
function zen_cfg_xpayr_fetch_network_options()
{
    $catalog = zen_cfg_xpayr_fetch_catalog();
    $options = [];

    if (is_array($catalog)) {
        foreach ($catalog as $row) {
            if (!is_array($row))
                continue;
            $network = isset($row['network']) ? trim((string) $row['network']) : '';
            if (!$network)
                continue;
            $networkName = isset($row['network_name']) ? trim((string) $row['network_name']) : $network;
            $options[$network] = ['key' => $network, 'name' => $networkName];
        }
    }

    if (empty($options)) {
        $options = [
            'bsc-testnet' => ['key' => 'bsc-testnet', 'name' => 'BSC Testnet'],
            'base-sepolia' => ['key' => 'base-sepolia', 'name' => 'Base Sepolia'],
            'polygon-amoy' => ['key' => 'polygon-amoy', 'name' => 'Polygon Amoy'],
            'bsc' => ['key' => 'bsc', 'name' => 'BSC Mainnet'],
            'ethereum' => ['key' => 'ethereum', 'name' => 'Ethereum'],
            'polygon' => ['key' => 'polygon', 'name' => 'Polygon'],
            'base' => ['key' => 'base', 'name' => 'Base'],
        ];
    }

    return array_values($options);
}

/**
 * Fetch currency options from XPayr API.
 * Falls back to hardcoded defaults if API is unreachable.
 *
 * @return array List of arrays with 'key' and 'name'
 */
function zen_cfg_xpayr_fetch_currency_options()
{
    $catalog = zen_cfg_xpayr_fetch_catalog();
    $symbols = [];

    if (is_array($catalog)) {
        foreach ($catalog as $row) {
            if (!is_array($row))
                continue;
            $symbol = isset($row['symbol']) ? strtoupper(trim((string) $row['symbol'])) : '';
            if ($symbol) {
                $symbols[$symbol] = ['key' => $symbol, 'name' => $symbol];
            }
        }
    }

    if (empty($symbols)) {
        $symbols = [
            'USDC' => ['key' => 'USDC', 'name' => 'USDC'],
            'USDT' => ['key' => 'USDT', 'name' => 'USDT'],
        ];
    }

    ksort($symbols);
    return array_values($symbols);
}

/**
 * Call XPayr /me/networks API and cache per-request.
 *
 * @return array Raw API data rows
 */
function zen_cfg_xpayr_fetch_catalog()
{
    static $cached = null;
    if ($cached !== null) {
        return $cached;
    }

    $cached = [];

    $apiBase = defined('MODULE_PAYMENT_XPAYR_API_BASE_URL')
        ? rtrim((string) MODULE_PAYMENT_XPAYR_API_BASE_URL, '/') : '';
    $secret = defined('MODULE_PAYMENT_XPAYR_SECRET_KEY')
        ? trim((string) MODULE_PAYMENT_XPAYR_SECRET_KEY) : '';

    if ($apiBase === '' || $secret === '') {
        return $cached;
    }

    $headers = "Authorization: Bearer " . $secret . "\r\n" .
        "Accept: application/json\r\n";

    $options = [
        'http' => [
            'method' => 'GET',
            'header' => $headers,
            'timeout' => 15,
            'ignore_errors' => true,
        ],
    ];

    $context = stream_context_create($options);
    $body = @file_get_contents($apiBase . '/me/networks', false, $context);

    if ($body === false) {
        return $cached;
    }

    $http = 0;
    if (isset($http_response_header) && is_array($http_response_header) && !empty($http_response_header[0])) {
        preg_match('/HTTP\/\d\.\d\s+(\d+)/', $http_response_header[0], $matches);
        $http = isset($matches[1]) ? (int) $matches[1] : 0;
    }

    if ($http < 200 || $http >= 300) {
        return $cached;
    }

    $json = json_decode((string) $body, true);
    if (is_array($json) && isset($json['data']) && is_array($json['data'])) {
        $cached = $json['data'];
    }

    return $cached;
}


class xpayr
{
    /**
     * Internal code name for this payment module
     * @var string
     */
    public $code;

    /**
     * Displayed name for this payment method
     * @var string
     */
    public $title;

    /**
     * Soft description of this payment method
     * @var string
     */
    public $description;

    /**
     * Whether this module is enabled during checkout
     * @var bool
     */
    public $enabled;

    /**
     * Order display priority (lowest first)
     * @var int|null
     */
    public $sort_order;

    /**
     * Order status to set after processing
     * @var int
     */
    public $order_status;

    /**
     * Configuration key existence check cache
     * @var int
     */
    protected $_check;

    /**
     * Class constructor.
     * Uses defined() guards so the module can be listed before installation.
     */
    function __construct()
    {
        global $order;

        $this->code = 'xpayr';
        $this->title = defined('MODULE_PAYMENT_XPAYR_TEXT_ADMIN_TITLE')
            ? MODULE_PAYMENT_XPAYR_TEXT_ADMIN_TITLE
            : 'XPayr Crypto Payment Gateway';
        $this->description = defined('MODULE_PAYMENT_XPAYR_TEXT_DESCRIPTION')
            ? MODULE_PAYMENT_XPAYR_TEXT_DESCRIPTION
            : 'Accept crypto payments via XPayr hosted checkout.';
        $this->sort_order = defined('MODULE_PAYMENT_XPAYR_SORT_ORDER')
            ? MODULE_PAYMENT_XPAYR_SORT_ORDER
            : null;
        $this->enabled = (defined('MODULE_PAYMENT_XPAYR_STATUS') && MODULE_PAYMENT_XPAYR_STATUS == 'True');

        if (null === $this->sort_order) {
            return false;
        }

        if (defined('MODULE_PAYMENT_XPAYR_ORDER_STATUS_ID') && (int) MODULE_PAYMENT_XPAYR_ORDER_STATUS_ID > 0) {
            $this->order_status = (int) MODULE_PAYMENT_XPAYR_ORDER_STATUS_ID;
        }

        if (is_object($order)) {
            $this->update_status();
        }
    }

    /**
     * Disable the module if the customer billing zone does not match
     * the configured payment zone.
     */
    function update_status()
    {
        global $order, $db;

        if (!defined('MODULE_PAYMENT_XPAYR_ZONE')) {
            return;
        }

        if ($this->enabled && (int) MODULE_PAYMENT_XPAYR_ZONE > 0 && isset($order->billing['country']['id'])) {
            $check_flag = false;
            $check = $db->Execute(
                "SELECT zone_id FROM " . TABLE_ZONES_TO_GEO_ZONES .
                " WHERE geo_zone_id = '" . (int) MODULE_PAYMENT_XPAYR_ZONE . "'" .
                " AND zone_country_id = '" . (int) $order->billing['country']['id'] . "'" .
                " ORDER BY zone_id"
            );

            while (!$check->EOF) {
                if ($check->fields['zone_id'] < 1 || (int) $check->fields['zone_id'] === (int) $order->billing['zone_id']) {
                    $check_flag = true;
                    break;
                }
                $check->MoveNext();
            }

            if (!$check_flag) {
                $this->enabled = false;
            }
        }
    }

    /**
     * No client-side validation needed.
     */
    function javascript_validation()
    {
        return false;
    }

    /**
     * Return the payment method selection entry for checkout.
     */
    function selection()
    {
        return [
            'id' => $this->code,
            'module' => $this->title,
        ];
    }

    /**
     * No pre-confirmation checks needed.
     */
    function pre_confirmation_check()
    {
        return false;
    }

    /**
     * No confirmation fields needed.
     */
    function confirmation()
    {
        return false;
    }

    /**
     * No hidden process button fields needed.
     */
    function process_button()
    {
        return false;
    }

    /**
     * No actions before order processing.
     */
    function before_process()
    {
        return false;
    }

    /**
     * After the order is saved, create XPayr payment session
     * and redirect the customer to the hosted checkout page.
     */
    function after_process()
    {
        global $db, $order, $insert_id, $messageStack;

        $orderId = (int) $insert_id;
        if ($orderId <= 0) {
            $this->log('Invalid order id in after_process', true);
            return false;
        }

        $existing = $this->find_transaction_by_order_id($orderId);
        if (is_array($existing) && !empty($existing['payment_url'])) {
            zen_redirect($existing['payment_url']);
            return false;
        }

        $apiBase = defined('MODULE_PAYMENT_XPAYR_API_BASE_URL')
            ? rtrim((string) MODULE_PAYMENT_XPAYR_API_BASE_URL, '/') : '';
        $secret = defined('MODULE_PAYMENT_XPAYR_SECRET_KEY')
            ? trim((string) MODULE_PAYMENT_XPAYR_SECRET_KEY) : '';

        if ($apiBase === '' || $secret === '') {
            $msg = defined('MODULE_PAYMENT_XPAYR_TEXT_ERROR_MISSING_SETTINGS')
                ? MODULE_PAYMENT_XPAYR_TEXT_ERROR_MISSING_SETTINGS
                : 'XPayr is not configured correctly.';
            $failStatus = defined('MODULE_PAYMENT_XPAYR_ORDER_STATUS_ID_FAILED')
                ? (int) MODULE_PAYMENT_XPAYR_ORDER_STATUS_ID_FAILED : 1;
            $this->add_history($orderId, $msg, $failStatus);
            $messageStack->add_session('checkout_payment', $msg, 'error');
            zen_redirect(zen_href_link(FILENAME_CHECKOUT_PAYMENT, '', 'SSL'));
            return false;
        }

        $network = defined('MODULE_PAYMENT_XPAYR_NETWORK') ? (string) MODULE_PAYMENT_XPAYR_NETWORK : 'bsc-testnet';
        $currency = defined('MODULE_PAYMENT_XPAYR_CURRENCY') ? strtoupper((string) MODULE_PAYMENT_XPAYR_CURRENCY) : 'USDC';

        $orderReference = 'ZC-' . $orderId;
        $payload = [
            'amount' => number_format((float) $order->info['total'], 2, '.', ''),
            'currency' => $currency,
            'network' => $network,
            'order_id' => $orderReference,
            'description' => sprintf('ZenCart Order #%s', $orderId),
            'success_url' => zen_href_link(FILENAME_CHECKOUT_SUCCESS, '', 'SSL', false),
            'cancel_url' => zen_href_link(FILENAME_CHECKOUT_PAYMENT, '', 'SSL', false),
            'ipn_callback_url' => zen_href_link('ipn_xpayr.php', '', 'SSL', false, false, true),
            'metadata' => [
                'source' => 'zencart',
                'order_id' => $orderReference,
                'zencart_order_id' => $orderId,
                'customer_email' => (string) ($order->customer['email_address'] ?? ''),
            ],
        ];

        $apiRes = $this->api_request('POST', '/payments', $payload);

        if (empty($apiRes['success'])) {
            $msg = defined('MODULE_PAYMENT_XPAYR_TEXT_ERROR_CREATE_SESSION')
                ? MODULE_PAYMENT_XPAYR_TEXT_ERROR_CREATE_SESSION
                : 'Could not create XPayr payment session.';
            if (!empty($apiRes['message'])) {
                $msg .= ' ' . $apiRes['message'];
            }
            $failStatus = defined('MODULE_PAYMENT_XPAYR_ORDER_STATUS_ID_FAILED')
                ? (int) MODULE_PAYMENT_XPAYR_ORDER_STATUS_ID_FAILED : 1;
            $this->add_history($orderId, $msg, $failStatus);
            $messageStack->add_session('checkout_payment', $msg, 'error');
            zen_redirect(zen_href_link(FILENAME_CHECKOUT_PAYMENT, '', 'SSL'));
            return false;
        }

        $sessionId = (string) ($apiRes['data']['id'] ?? $apiRes['data']['session_id'] ?? '');
        $invoiceId = (string) ($apiRes['data']['invoice_id'] ?? '');
        $paymentUrl = (string) ($apiRes['data']['payment_url'] ?? '');

        if ($sessionId === '' || $paymentUrl === '') {
            $msg = defined('MODULE_PAYMENT_XPAYR_TEXT_ERROR_INVALID_RESPONSE')
                ? MODULE_PAYMENT_XPAYR_TEXT_ERROR_INVALID_RESPONSE
                : 'XPayr API returned invalid response.';
            $failStatus = defined('MODULE_PAYMENT_XPAYR_ORDER_STATUS_ID_FAILED')
                ? (int) MODULE_PAYMENT_XPAYR_ORDER_STATUS_ID_FAILED : 1;
            $this->add_history($orderId, $msg, $failStatus);
            $messageStack->add_session('checkout_payment', $msg, 'error');
            zen_redirect(zen_href_link(FILENAME_CHECKOUT_PAYMENT, '', 'SSL'));
            return false;
        }

        $this->save_transaction($orderId, $orderReference, $sessionId, $invoiceId, $paymentUrl, 'pending', $apiRes['raw']);

        $noteTemplate = defined('MODULE_PAYMENT_XPAYR_TEXT_ORDER_NOTE_CREATED')
            ? MODULE_PAYMENT_XPAYR_TEXT_ORDER_NOTE_CREATED
            : 'XPayr session created: %s';
        $note = sprintf($noteTemplate, $sessionId);
        $defaultStatus = defined('MODULE_PAYMENT_XPAYR_ORDER_STATUS_ID')
            ? (int) MODULE_PAYMENT_XPAYR_ORDER_STATUS_ID : 1;
        $this->add_history($orderId, $note, $defaultStatus);

        zen_redirect($paymentUrl);
        return false;
    }

    /**
     * Check whether the module is installed by verifying
     * the existence of its configuration keys.
     */
    function check()
    {
        global $db;

        if (!isset($this->_check)) {
            $check_query = $db->Execute(
                "SELECT configuration_value FROM " . TABLE_CONFIGURATION .
                " WHERE configuration_key = 'MODULE_PAYMENT_XPAYR_STATUS'"
            );
            $this->_check = $check_query->RecordCount();
        }

        return $this->_check;
    }

    /**
     * Install the module configuration keys and create the transactions table.
     */
    function install()
    {
        global $db, $messageStack;

        if (defined('MODULE_PAYMENT_XPAYR_STATUS')) {
            $messageStack->add_session('XPayr module already installed.', 'error');
            zen_redirect(zen_href_link(FILENAME_MODULES, 'set=payment&module=xpayr', 'NONSSL'));
            return 'failed';
        }

        $db->Execute("INSERT INTO " . TABLE_CONFIGURATION . "
            (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added)
            VALUES
            ('Enable XPayr Gateway', 'MODULE_PAYMENT_XPAYR_STATUS', 'True', 'Do you want to accept payments via XPayr?', '6', '0', 'zen_cfg_select_option(array(\'True\', \'False\'), ', now())");

        $db->Execute("INSERT INTO " . TABLE_CONFIGURATION . "
            (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added)
            VALUES
            ('Sort order of display', 'MODULE_PAYMENT_XPAYR_SORT_ORDER', '0', 'Sort order of displaying payment option. Lowest is displayed first.', '6', '1', now())");

        $db->Execute("INSERT INTO " . TABLE_CONFIGURATION . "
            (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, use_function, date_added)
            VALUES
            ('Default Order Status (Created)', 'MODULE_PAYMENT_XPAYR_ORDER_STATUS_ID', '1', 'Status after session is created and customer is redirected to XPayr.', '6', '2', 'zen_cfg_pull_down_order_statuses(', 'zen_get_order_status_name', now())");

        $db->Execute("INSERT INTO " . TABLE_CONFIGURATION . "
            (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, use_function, date_added)
            VALUES
            ('Order Status (Paid)', 'MODULE_PAYMENT_XPAYR_ORDER_STATUS_ID_PAID', '2', 'Status when XPayr webhook reports payment.completed.', '6', '3', 'zen_cfg_pull_down_order_statuses(', 'zen_get_order_status_name', now())");

        $db->Execute("INSERT INTO " . TABLE_CONFIGURATION . "
            (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, use_function, date_added)
            VALUES
            ('Order Status (Failed/Expired)', 'MODULE_PAYMENT_XPAYR_ORDER_STATUS_ID_FAILED', '1', 'Status when XPayr webhook reports failed or expired payment.', '6', '4', 'zen_cfg_pull_down_order_statuses(', 'zen_get_order_status_name', now())");

        $db->Execute("INSERT INTO " . TABLE_CONFIGURATION . "
            (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, use_function, set_function, date_added)
            VALUES
            ('Payment Zone', 'MODULE_PAYMENT_XPAYR_ZONE', '0', 'If a zone is selected, only enable this payment method for that zone.', '6', '5', 'zen_get_zone_class_title', 'zen_cfg_pull_down_zone_classes(', now())");

        $db->Execute("INSERT INTO " . TABLE_CONFIGURATION . "
            (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added)
            VALUES
            ('API Base URL', 'MODULE_PAYMENT_XPAYR_API_BASE_URL', 'https://xpayr.com/api/v1', 'XPayr API base URL.', '6', '6', now())");

        $db->Execute("INSERT INTO " . TABLE_CONFIGURATION . "
            (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added)
            VALUES
            ('Secret API Key', 'MODULE_PAYMENT_XPAYR_SECRET_KEY', '', 'Your XPayr secret key (sk_test_... or sk_live_...).', '6', '7', now())");

        $db->Execute("INSERT INTO " . TABLE_CONFIGURATION . "
            (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added)
            VALUES
            ('Network', 'MODULE_PAYMENT_XPAYR_NETWORK', 'bsc-testnet', 'Blockchain network for checkout session.', '6', '8', 'zen_cfg_xpayr_network_select(', now())");

        $db->Execute("INSERT INTO " . TABLE_CONFIGURATION . "
            (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added)
            VALUES
            ('Currency', 'MODULE_PAYMENT_XPAYR_CURRENCY', 'USDC', 'Settlement currency symbol.', '6', '9', 'zen_cfg_xpayr_currency_select(', now())");

        $db->Execute("INSERT INTO " . TABLE_CONFIGURATION . "
            (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, date_added)
            VALUES
            ('Webhook Secret', 'MODULE_PAYMENT_XPAYR_WEBHOOK_SECRET', '', 'Webhook secret used to verify X-XPayr-Signature header.', '6', '10', now())");

        $db->Execute("INSERT INTO " . TABLE_CONFIGURATION . "
            (configuration_title, configuration_key, configuration_value, configuration_description, configuration_group_id, sort_order, set_function, date_added)
            VALUES
            ('Debug Log', 'MODULE_PAYMENT_XPAYR_DEBUG', 'False', 'Log API/Webhook errors to /logs/xpayr-zencart.log', '6', '11', 'zen_cfg_select_option(array(\'True\', \'False\'), ', now())");

        $db->Execute(
            "CREATE TABLE IF NOT EXISTS " . $this->transaction_table() . " (
                id int(11) unsigned NOT NULL AUTO_INCREMENT,
                order_id int(11) unsigned NOT NULL,
                order_reference varchar(64) NOT NULL,
                session_id varchar(80) NOT NULL,
                invoice_id varchar(32) DEFAULT '',
                payment_url text NOT NULL,
                status varchar(32) NOT NULL DEFAULT 'pending',
                payload_json longtext NULL,
                created_at datetime NOT NULL,
                updated_at datetime NOT NULL,
                PRIMARY KEY (id),
                UNIQUE KEY uniq_order (order_id),
                KEY idx_session (session_id),
                KEY idx_invoice (invoice_id),
                KEY idx_reference (order_reference)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        );
    }

    /**
     * Remove all configuration keys for this module.
     */
    function remove()
    {
        global $db;

        $db->Execute("DELETE FROM " . TABLE_CONFIGURATION . " WHERE configuration_key IN ('" . implode("', '", $this->keys()) . "')");
    }

    /**
     * Return the list of configuration keys managed by this module.
     */
    function keys()
    {
        return [
            'MODULE_PAYMENT_XPAYR_STATUS',
            'MODULE_PAYMENT_XPAYR_SORT_ORDER',
            'MODULE_PAYMENT_XPAYR_ORDER_STATUS_ID',
            'MODULE_PAYMENT_XPAYR_ORDER_STATUS_ID_PAID',
            'MODULE_PAYMENT_XPAYR_ORDER_STATUS_ID_FAILED',
            'MODULE_PAYMENT_XPAYR_ZONE',
            'MODULE_PAYMENT_XPAYR_API_BASE_URL',
            'MODULE_PAYMENT_XPAYR_SECRET_KEY',
            'MODULE_PAYMENT_XPAYR_NETWORK',
            'MODULE_PAYMENT_XPAYR_CURRENCY',
            'MODULE_PAYMENT_XPAYR_WEBHOOK_SECRET',
            'MODULE_PAYMENT_XPAYR_DEBUG',
        ];
    }

    /**
     * Return error info if needed.
     */
    function get_error()
    {
        return false;
    }

    /**
     * Return the custom transactions table name with prefix.
     */
    protected function transaction_table()
    {
        return DB_PREFIX . 'xpayr_transactions';
    }

    /**
     * Look up an existing transaction record for the given order.
     *
     * @param int $orderId Zen Cart order ID
     * @return array|null Transaction row or null if not found
     */
    protected function find_transaction_by_order_id($orderId)
    {
        global $db;

        $query = $db->Execute(
            "SELECT * FROM " . $this->transaction_table() . " WHERE order_id = '" . (int) $orderId . "' LIMIT 1"
        );

        if ($query->EOF) {
            return null;
        }

        return $query->fields;
    }

    /**
     * Save or update a transaction record.
     *
     * @param int    $orderId    Zen Cart order ID
     * @param string $reference  Order reference string
     * @param string $sessionId  XPayr session ID
     * @param string $invoiceId  XPayr invoice ID
     * @param string $paymentUrl Hosted checkout URL
     * @param string $status     Payment status
     * @param string $payloadRaw Raw API response JSON
     */
    protected function save_transaction($orderId, $reference, $sessionId, $invoiceId, $paymentUrl, $status, $payloadRaw = '')
    {
        global $db;

        $safeReference = zen_db_input(substr((string) $reference, 0, 64));
        $safeSession = zen_db_input(substr((string) $sessionId, 0, 80));
        $safeInvoice = zen_db_input(substr((string) $invoiceId, 0, 32));
        $safeUrl = zen_db_input((string) $paymentUrl);
        $safeStatus = zen_db_input(substr((string) $status, 0, 32));
        $safePayload = zen_db_input((string) $payloadRaw);

        $existing = $this->find_transaction_by_order_id($orderId);

        if ($existing) {
            $db->Execute(
                "UPDATE " . $this->transaction_table() .
                " SET order_reference = '" . $safeReference . "'," .
                " session_id = '" . $safeSession . "'," .
                " invoice_id = '" . $safeInvoice . "'," .
                " payment_url = '" . $safeUrl . "'," .
                " status = '" . $safeStatus . "'," .
                " payload_json = '" . $safePayload . "'," .
                " updated_at = now()" .
                " WHERE order_id = '" . (int) $orderId . "'"
            );
            return;
        }

        $db->Execute(
            "INSERT INTO " . $this->transaction_table() .
            " (order_id, order_reference, session_id, invoice_id, payment_url, status, payload_json, created_at, updated_at)" .
            " VALUES ('" . (int) $orderId . "', '" . $safeReference . "', '" . $safeSession . "', '" . $safeInvoice . "', '" . $safeUrl . "', '" . $safeStatus . "', '" . $safePayload . "', now(), now())"
        );
    }

    /**
     * Add an order status history entry and update the order status.
     *
     * @param int    $orderId  Zen Cart order ID
     * @param string $comment  History comment
     * @param int    $statusId Order status ID
     */
    protected function add_history($orderId, $comment, $statusId)
    {
        global $db;

        $safeComment = zen_db_input((string) $comment);
        $orderStatus = (int) $statusId;

        if ($orderStatus <= 0) {
            $orderStatus = defined('MODULE_PAYMENT_XPAYR_ORDER_STATUS_ID')
                ? (int) MODULE_PAYMENT_XPAYR_ORDER_STATUS_ID : 1;
        }

        $db->Execute(
            "INSERT INTO " . TABLE_ORDERS_STATUS_HISTORY .
            " (orders_id, orders_status_id, date_added, customer_notified, comments)" .
            " VALUES ('" . (int) $orderId . "', '" . $orderStatus . "', now(), 0, '" . $safeComment . "')"
        );

        $db->Execute(
            "UPDATE " . TABLE_ORDERS .
            " SET orders_status = '" . $orderStatus . "', last_modified = now()" .
            " WHERE orders_id = '" . (int) $orderId . "'"
        );
    }

    /**
     * Make an HTTP request to the XPayr API.
     *
     * @param string $method  HTTP method (GET, POST, etc.)
     * @param string $path    API endpoint path
     * @param array  $payload Request body data
     * @return array Response with 'success', 'data', 'message', and 'raw' keys
     */
    protected function api_request($method, $path, $payload = [])
    {
        $apiBase = defined('MODULE_PAYMENT_XPAYR_API_BASE_URL')
            ? rtrim((string) MODULE_PAYMENT_XPAYR_API_BASE_URL, '/') : '';
        $secret = defined('MODULE_PAYMENT_XPAYR_SECRET_KEY')
            ? trim((string) MODULE_PAYMENT_XPAYR_SECRET_KEY) : '';
        $url = $apiBase . '/' . ltrim((string) $path, '/');

        $headers = "Authorization: Bearer " . $secret . "\r\n" .
            "Content-Type: application/json\r\n" .
            "Accept: application/json\r\n";

        $options = [
            'http' => [
                'method' => strtoupper((string) $method),
                'header' => $headers,
                'timeout' => 30,
                'ignore_errors' => true,
            ],
        ];

        if (!empty($payload)) {
            $options['http']['content'] = json_encode($payload);
        }

        $context = stream_context_create($options);
        $raw = @file_get_contents($url, false, $context);

        $code = 0;
        if (isset($http_response_header) && is_array($http_response_header) && !empty($http_response_header[0])) {
            preg_match('/HTTP\/\d\.\d\s+(\d+)/', $http_response_header[0], $matches);
            $code = isset($matches[1]) ? (int) $matches[1] : 0;
        }

        if ($raw === false) {
            $this->log('XPayr API request failed to ' . $url, true);
            return ['success' => false, 'message' => 'HTTP request failed'];
        }

        $json = json_decode((string) $raw, true);
        if ($code < 200 || $code >= 300 || !is_array($json)) {
            $this->log('XPayr API bad response [' . $code . ']: ' . $raw, true);
            $msg = is_array($json) && !empty($json['error']['message'])
                ? (string) $json['error']['message']
                : 'API returned HTTP ' . $code;
            return ['success' => false, 'message' => $msg, 'raw' => (string) $raw];
        }

        return ['success' => true, 'data' => $json, 'raw' => (string) $raw];
    }

    /**
     * Write a line to the debug log file if debugging is enabled.
     *
     * @param string $message Log message
     * @param bool   $force   Write even if debug mode is off
     */
    protected function log($message, $force = false)
    {
        if (!$force && (!defined('MODULE_PAYMENT_XPAYR_DEBUG') || MODULE_PAYMENT_XPAYR_DEBUG !== 'True')) {
            return;
        }

        $line = '[' . date('c') . '] ' . (string) $message . "\n";
        $logPath = defined('DIR_FS_LOGS') ? DIR_FS_LOGS . '/xpayr-zencart.log' : 'logs/xpayr-zencart.log';
        @file_put_contents($logPath, $line, FILE_APPEND);
    }
}
