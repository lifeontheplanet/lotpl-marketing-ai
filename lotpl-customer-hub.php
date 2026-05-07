<?php
/**
 * Plugin Name: LOTPL Customer Hub
 * Plugin URI: https://lifeontheplanetladakh.com/
 * Description: Customer hub for LOTPL inquiries, timelines, and delayed auto replies.
 * Version: 0.1.0
 * Author: LIFE on the PLANET LADAKH
 * Author URI: https://lifeontheplanetladakh.com/
 * Text Domain: lotpl-customer-hub
 */

defined("ABSPATH") || exit;

require_once __DIR__ . "/includes/class-plugin.php";
require_once __DIR__ . "/includes/class-activator.php";

register_activation_hook(__FILE__, array("LOTPL_Customer_Hub_Activator", "activate"));

function lotpl_customer_hub(): LOTPL_Customer_Hub_Plugin {
    static $plugin = null;

    if ($plugin === null) {
        $plugin = new LOTPL_Customer_Hub_Plugin();
    }

    return $plugin;
}

lotpl_customer_hub()->run();
