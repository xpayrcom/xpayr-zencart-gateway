<?php
/**
 * XPayr Crypto Payment Gateway — Language Definitions (Zen Cart 1.5.x)
 *
 * @license GPL-2.0-or-later https://www.gnu.org/licenses/gpl-2.0.html
 * @copyright Copyright (c) 2024 XPayr - https://xpayr.com
 */
if (!defined('IS_ADMIN_FLAG')) {
    die('Illegal Access');
}
define('MODULE_PAYMENT_XPAYR_TEXT_ADMIN_TITLE', 'XPayr Gateway');
define('MODULE_PAYMENT_XPAYR_TEXT_CATALOG_TITLE', zen_image(DIR_WS_IMAGES . 'xpayr.png', 'XPayr', 24, 24) . '<span style="margin-left:8px;">Pay with Crypto (XPayr)</span>');
define('MODULE_PAYMENT_XPAYR_TEXT_DESCRIPTION', 'Accept crypto payments via XPayr hosted checkout. Non-custodial and on-chain.');
define('MODULE_PAYMENT_XPAYR_TEXT_ERROR_MESSAGE', 'There was an error while processing the XPayr payment.');
define('MODULE_PAYMENT_XPAYR_TEXT_ERROR_MISSING_SETTINGS', 'XPayr is not configured correctly. Please contact store administrator.');
define('MODULE_PAYMENT_XPAYR_TEXT_ERROR_CREATE_SESSION', 'Could not create XPayr payment session.');
define('MODULE_PAYMENT_XPAYR_TEXT_ERROR_INVALID_RESPONSE', 'XPayr API returned invalid response.');
define('MODULE_PAYMENT_XPAYR_TEXT_ORDER_NOTE_CREATED', 'XPayr session created: %s');
