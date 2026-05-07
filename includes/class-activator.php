<?php

defined("ABSPATH") || exit;

final class LOTPL_Customer_Hub_Activator {
    public static function activate(): void {
        self::create_customers_table();
        self::create_customer_events_table();
    }

    private static function create_customers_table(): void {
        global $wpdb;

        require_once ABSPATH . "wp-admin/includes/upgrade.php";

        $table_name = $wpdb->prefix . "lotpl_customers";
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$table_name} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            name VARCHAR(191) NOT NULL DEFAULT '',
            email VARCHAR(191) NOT NULL DEFAULT '',
            status VARCHAR(50) NOT NULL DEFAULT 'new',
            notes LONGTEXT NULL,
            tags LONGTEXT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY email (email),
            KEY status (status)
        ) {$charset_collate};";

        dbDelta($sql);
    }

    private static function create_customer_events_table(): void {
        global $wpdb;

        require_once ABSPATH . "wp-admin/includes/upgrade.php";

        $table_name = $wpdb->prefix . "lotpl_customer_events";
        $customers_table = $wpdb->prefix . "lotpl_customers";
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$table_name} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            customer_id BIGINT UNSIGNED NOT NULL,
            event_type VARCHAR(100) NOT NULL DEFAULT 'form_submission',
            event_source VARCHAR(100) NOT NULL DEFAULT 'system',
            subject TEXT NULL,
            body LONGTEXT NULL,
            meta_json LONGTEXT NULL,
            scheduled_at DATETIME NULL,
            sent_at DATETIME NULL,
            status VARCHAR(50) NOT NULL DEFAULT 'new',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY customer_id (customer_id),
            KEY event_type (event_type),
            KEY status (status)
        ) {$charset_collate};";

        dbDelta($sql);
    }
}
