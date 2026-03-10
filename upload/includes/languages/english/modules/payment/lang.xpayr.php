<?php
/**
 * XPayr Crypto Payment Gateway — Language Definitions (Zen Cart 2.x)
 *
 * Provides all translatable strings used by the XPayr payment module.
 *
 * @license GPL-2.0-or-later https://www.gnu.org/licenses/gpl-2.0.html
 * @copyright Copyright (c) 2024 XPayr - https://xpayr.com
 */
if (!defined('IS_ADMIN_FLAG')) {
    die('Illegal Access');
}

$define = [
    'MODULE_PAYMENT_XPAYR_TEXT_ADMIN_TITLE' => 'XPayr Crypto Payment Gateway',
    'MODULE_PAYMENT_XPAYR_TEXT_CATALOG_TITLE' => 'Pay with Crypto (XPayr)',
    'MODULE_PAYMENT_XPAYR_TEXT_DESCRIPTION' => 'Accept crypto payments via XPayr hosted checkout. Non-custodial and on-chain.',
    'MODULE_PAYMENT_XPAYR_TEXT_ERROR_MESSAGE' => 'There was an error while processing the XPayr payment.',
    'MODULE_PAYMENT_XPAYR_TEXT_ERROR_MISSING_SETTINGS' => 'XPayr is not configured correctly. Please contact store administrator.',
    'MODULE_PAYMENT_XPAYR_TEXT_ERROR_CREATE_SESSION' => 'Could not create XPayr payment session.',
    'MODULE_PAYMENT_XPAYR_TEXT_ERROR_INVALID_RESPONSE' => 'XPayr API returned invalid response.',
    'MODULE_PAYMENT_XPAYR_TEXT_ORDER_NOTE_CREATED' => 'XPayr session created: %s',
];

return $define;
