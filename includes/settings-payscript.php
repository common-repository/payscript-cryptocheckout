<?php

/**
 * Settings for Payscript Gateway.
 *
 * @package WooCommerce/Classes/Payment
 */
defined('ABSPATH') || exit;

return array(
    'api_endpointurl' => array(
        'title' => __('Endpoint', 'payscript'),
        'type' => 'select',
        'description' => __('Get your API credentials from Payscript.', 'payscript'),
        'default' => '',
        'options'  => array(
            'https://api-gateway.sandbox.payscript.io/v1'    => __('Sandbox', 'payscript'),
            'https://api-gateway.payscript.io/v1'   => __('Production', 'payscript'),
        ),
        'class' => "payscript_endpointurl",
        'desc_tip' => true,
        'placeholder' => __('URL', 'payscript'),
    ),
    'api_publickey' => array(
        'title' => __('Public Key', 'payscript'),
        'type' => 'text',
        'description' => __('Get your API credentials from Payscript.', 'payscript'),
        'default' => '',
        'class' => "payscript_publickey",
        'desc_tip' => true,
        'placeholder' => __('Public Key', 'payscript'),
    ),
    'api_privatekey' => array(
        'title' => __('Private Key', 'payscript'),
        'type' => 'password',
        'description' => __('Get your API credentials from Payscript.', 'payscript'),
        'default' => '',
        'class' => "payscript_privatekey",
        'desc_tip' => true,
        'placeholder' => __('Private Key', 'payscript'),
    ),

    'order_statuses' => array(
        'type' => 'order_statuses'
    ),
    'balance' => array(
        'title' => __('Check Balance', 'payscript'),
        'type' => 'checkbox',
        'label' => sprintf(__('<a href="%s" class="button get_payscript_balance">Get Balance</a> <span class="balance_area"></span>', 'payscript'), 'javascript://'),
        'description' => __('Click button to get balance', 'payscript'),
        'css' => 'display:none',
        'desc_tip' => true,
    ),
);
