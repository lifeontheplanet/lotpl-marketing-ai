<?php

defined("ABSPATH") || exit;

final class LOTPL_Customer_Hub_Customers_Repository {
    public function find_or_create_by_email(string $name, string $email): int {
        global $wpdb;

        $table_name = $wpdb->prefix . "lotpl_customers";
        $email = sanitize_email($email);
        $name = trim(wp_strip_all_tags($name));

        if ($email === "") {
            return 0;
        }

        $existing_id = (int) $wpdb->get_var($wpdb->prepare("SELECT id FROM {$table_name} WHERE email = %s ORDER BY id DESC LIMIT 1", $email));

        if ($existing_id > 0) {
            if ($name !== "") {
                $wpdb->update($table_name, array("name" => $name), array("id" => $existing_id), array("%s"), array("%d"));
            }

            return $existing_id;
        }

        $wpdb->insert($table_name, array(
            "name" => $name,
            "email" => $email,
            "status" => "new",
        ), array("%s", "%s", "%s"));

        return (int) $wpdb->insert_id;
    }

    public function create_customer(string $name, string $email, string $status = "new"): int {
        global $wpdb;

        $table_name = $wpdb->prefix . "lotpl_customers";
        $name = trim(wp_strip_all_tags($name));
        $email = sanitize_email($email);
        $status = trim(wp_strip_all_tags($status));

        if ($name === "") {
            return 0;
        }

        $wpdb->insert($table_name, array(
            "name" => $name,
            "email" => $email,
            "status" => $status === "" ? "new" : $status,
        ), array("%s", "%s", "%s"));

        return (int) $wpdb->insert_id;
    }

    public function get_by_id(int $customer_id): ?array {
        global $wpdb;

        $table_name = $wpdb->prefix . "lotpl_customers";
        $customer_id = max(1, $customer_id);

        if ($customer_id < 1) {
            return null;
        }

        $sql = $wpdb->prepare(
            "SELECT id, name, email, status, notes, tags, created_at, updated_at FROM {$table_name} WHERE id = %d LIMIT 1",
            $customer_id
        );

        $row = $wpdb->get_row($sql, ARRAY_A);

        return is_array($row) ? $row : null;
    }

    public function count_all(): int {
        global $wpdb;

        $table_name = $wpdb->prefix . "lotpl_customers";
        $count = $wpdb->get_var("SELECT COUNT(*) FROM {$table_name} ");

        return (int) $count;
    }

    public function get_all(): array {
        global $wpdb;

        $table_name = $wpdb->prefix . "lotpl_customers";
        $events_table = $wpdb->prefix . "lotpl_customer_events";
        $rows = $wpdb->get_results("SELECT c.id, c.name, c.email, c.status, c.created_at, c.updated_at, COALESCE(MAX(e.created_at), c.created_at) AS latest_message_at FROM {$table_name} c LEFT JOIN {$events_table} e ON e.customer_id = c.id GROUP BY c.id, c.name, c.email, c.status, c.created_at, c.updated_at ORDER BY latest_message_at DESC, c.id DESC", ARRAY_A);

        return is_array($rows) ? $rows : array();
    }

    public function get_latest(int $limit = 20): array {
        global $wpdb;

        $table_name = $wpdb->prefix . "lotpl_customers";
        $limit = max(1, $limit);
        $limit = min(100, $limit);

        $sql = $wpdb->prepare(
            "SELECT id, name, email, status, created_at, updated_at FROM {$table_name} ORDER BY id DESC LIMIT %d",
            $limit
        );

        $rows = $wpdb->get_results($sql, ARRAY_A);

        return is_array($rows) ? $rows : array();
    }
    public function update_customer(int $customer_id, string $name, string $email): bool {
        global $wpdb;

        $table_name = $wpdb->prefix . "lotpl_customers";
        $customer_id = max(1, $customer_id);
        $name = trim(wp_strip_all_tags($name));
        $email = sanitize_email($email);

        if ($customer_id < 1 || $name === "") {
            return false;
        }

        $updated = $wpdb->update(
            $table_name,
            array("name" => $name, "email" => $email),
            array("id" => $customer_id),
            array("%s", "%s"),
            array("%d")
        );

        return $updated !== false;
    }

    public function update_customer_status(int $customer_id, string $status): bool {
        global $wpdb;

        $table_name = $wpdb->prefix . "lotpl_customers";
        $customer_id = max(1, $customer_id);
        $status = trim(wp_strip_all_tags($status));

        if ($customer_id < 1 || $status === "") {
            return false;
        }

        $updated = $wpdb->update(
            $table_name,
            array("status" => $status),
            array("id" => $customer_id),
            array("%s"),
            array("%d")
        );

        return $updated !== false;
    }

    public function delete_customer(int $customer_id): bool {
        global $wpdb;

        $table_name = $wpdb->prefix . "lotpl_customers";
        $customer_id = max(1, $customer_id);

        if ($customer_id < 1) {
            return false;
        }

        $deleted = $wpdb->delete(
            $table_name,
            array("id" => $customer_id),
            array("%d")
        );

        return $deleted === 1;
    }
}
