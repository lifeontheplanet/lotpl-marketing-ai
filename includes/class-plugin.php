<?php

defined("ABSPATH") || exit;

require_once __DIR__ . "/class-admin-menu.php";
require_once __DIR__ . "/class-wpforms-intake.php";
require_once __DIR__ . "/class-cf7-intake.php";
require_once __DIR__ . "/class-auto-reply-runner.php";
require_once __DIR__ . "/class-travel-inquiry-analyzer.php";
require_once __DIR__ . "/class-ai-inquiry-analyzer.php";
require_once __DIR__ . "/class-rest-api.php";

final class LOTPL_Customer_Hub_Plugin {
    public function run(): void {
        $admin_menu = new LOTPL_Customer_Hub_Admin_Menu();
        $admin_menu->register();

        $wpforms_intake = new LOTPL_Customer_Hub_WPForms_Intake();
        $wpforms_intake->register();

        $cf7_intake = new LOTPL_Customer_Hub_CF7_Intake();
        $cf7_intake->register();

        $auto_reply_runner = new LOTPL_Customer_Hub_Auto_Reply_Runner();
        $auto_reply_runner->register();

        $rest_api = new LOTPL_Customer_Hub_REST_API();
        $rest_api->register();
    }
}
