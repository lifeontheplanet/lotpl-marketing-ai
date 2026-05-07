<?php

defined("ABSPATH") || exit;

final class LOTPL_Customer_Hub_Events_Repository {
    public function schedule_auto_reply(int $customer_id, string $subject, string $body, array $meta = array()): int {
        global $wpdb;

        $table_name = $wpdb->prefix . "lotpl_customer_events";
        $customer_id = max(1, $customer_id);
        $subject = trim(wp_strip_all_tags($subject));
        $body = trim((string) $body);
        $meta_json = empty($meta) ? null : wp_json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $scheduled_at = date("Y-m-d H:i:s", current_time("timestamp") + (10 * MINUTE_IN_SECONDS));

        if ($customer_id < 1) {
            return 0;
        }

        $wpdb->insert($table_name, array(
            "customer_id" => $customer_id,
            "event_type" => "auto_reply_scheduled",
            "event_source" => "system",
            "subject" => $subject,
            "body" => $body,
            "meta_json" => $meta_json,
            "scheduled_at" => $scheduled_at,
            "status" => "scheduled",
        ), array("%d", "%s", "%s", "%s", "%s", "%s", "%s", "%s"));

        return (int) $wpdb->insert_id;
    }

    public function add_event(int $customer_id, string $event_type, string $event_source, string $subject, string $body, array $meta = array()): int {
        global $wpdb;

        $table_name = $wpdb->prefix . "lotpl_customer_events";
        $customer_id = max(1, $customer_id);
        $event_type = sanitize_key($event_type);
        $event_source = sanitize_key($event_source);
        $subject = trim(wp_strip_all_tags($subject));
        $body = trim((string) $body);
        $created_at = isset($meta["created_at"]) ? trim((string) $meta["created_at"]) : ""; unset($meta["created_at"]); $meta_json = empty($meta) ? null : wp_json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if ($customer_id < 1) {
            return 0;
        }

        $wpdb->insert($table_name, array(
            "customer_id" => $customer_id,
            "event_type" => $event_type === "" ? "form_submission" : $event_type,
            "event_source" => $event_source === "" ? "wpforms" : $event_source,
            "subject" => $subject,
            "body" => $body,
            "meta_json" => $meta_json,
            "status" => "new",
            "created_at" => $created_at !== "" ? $created_at : current_time("mysql"),
        ), array("%d", "%s", "%s", "%s", "%s", "%s", "%s", "%s"));

        return (int) $wpdb->insert_id;
    }

    public function get_next_scheduled_auto_reply(): ?array {
        global $wpdb;

        $table_name = $wpdb->prefix . "lotpl_customer_events";
        $now = current_time("mysql");
        $sql = $wpdb->prepare(
            "SELECT id, customer_id, event_type, event_source, subject, body, meta_json, scheduled_at, sent_at, status, created_at FROM {$table_name} WHERE event_type = %s AND status = %s AND scheduled_at IS NOT NULL AND scheduled_at <= %s ORDER BY scheduled_at ASC, id ASC LIMIT 1",
            "auto_reply_scheduled",
            "scheduled",
            $now
        );

        $row = $wpdb->get_row($sql, ARRAY_A);

        return is_array($row) ? $row : null;
    }

    public function mark_scheduled_as_processing(int $event_id): bool {
        global $wpdb;

        $table_name = $wpdb->prefix . "lotpl_customer_events";
        $event_id = max(1, $event_id);

        if ($event_id < 1) {
            return false;
        }

        $updated = $wpdb->update(
            $table_name,
            array("status" => "processing"),
            array("id" => $event_id, "event_type" => "auto_reply_scheduled", "status" => "scheduled"),
            array("%s"),
            array("%d", "%s", "%s")
        );

        return $updated === 1;
    }

    public function mark_processing_as_sent(int $event_id): bool {
        global $wpdb;

        $table_name = $wpdb->prefix . "lotpl_customer_events";
        $event_id = max(1, $event_id);
        $sent_at = current_time("mysql", true);

        if ($event_id < 1) {
            return false;
        }

        $updated = $wpdb->update(
            $table_name,
            array("status" => "sent", "sent_at" => $sent_at),
            array("id" => $event_id, "event_type" => "auto_reply_scheduled", "status" => "processing"),
            array("%s", "%s"),
            array("%d", "%s", "%s")
        );

        return $updated === 1;
    }

    public function mark_processing_as_failed(int $event_id): bool {
        global $wpdb;

        $table_name = $wpdb->prefix . "lotpl_customer_events";
        $event_id = max(1, $event_id);

        if ($event_id < 1) {
            return false;
        }

        $updated = $wpdb->update(
            $table_name,
            array("status" => "failed"),
            array("id" => $event_id, "event_type" => "auto_reply_scheduled", "status" => "processing"),
            array("%s"),
            array("%d", "%s", "%s")
        );

        return $updated === 1;
    }

    public function count_all(): int {
        global $wpdb;

        $table_name = $wpdb->prefix . "lotpl_customer_events";
        $count = $wpdb->get_var("SELECT COUNT(*) FROM {$table_name}");

        return (int) $count;
    }

    public function get_latest_by_customer_id(int $customer_id, int $limit = 20): array {
        global $wpdb;

        $table_name = $wpdb->prefix . "lotpl_customer_events";
        $customer_id = max(1, $customer_id);
        $limit = max(1, $limit);
        $limit = min(100, $limit);

        $sql = $wpdb->prepare(
            "SELECT id, customer_id, event_type, event_source, subject, body, meta_json, scheduled_at, sent_at, status, created_at FROM {$table_name} WHERE customer_id = %d ORDER BY created_at DESC, id DESC LIMIT %d",
            $customer_id,
            $limit
        );

        $rows = $wpdb->get_results($sql, ARRAY_A);

        if (is_array($rows) === false) { return array(); }
        foreach ($rows as &$row) { $meta = json_decode((string) ($row["meta_json"] ?? ""), true); if (is_array($meta)) { $row = array_merge($row, $meta); } }
        unset($row);
        return $rows;
    }
    public function update_event(int $customer_id, int $event_id, string $subject, string $body, array $meta = array()): bool {
        global $wpdb;

        $table_name = $wpdb->prefix . "lotpl_customer_events";
        $customer_id = max(1, $customer_id);
        $event_id = max(1, $event_id);
        $subject = trim(wp_strip_all_tags($subject));
        $body = trim((string) $body);
        $meta_json = empty($meta) ? null : wp_json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if ($customer_id < 1 || $event_id < 1 || $body === "") {
            return false;
        }

        $updated = $wpdb->update(
            $table_name,
            array("subject" => $subject, "body" => $body, "meta_json" => $meta_json),
            array("id" => $event_id, "customer_id" => $customer_id),
            array("%s", "%s", "%s"),
            array("%d", "%d")
        );

        return $updated !== false;
    }

    public function delete_event(int $customer_id, int $event_id): bool {
        global $wpdb;

        $table_name = $wpdb->prefix . "lotpl_customer_events";
        $customer_id = max(1, $customer_id);
        $event_id = max(1, $event_id);

        if ($customer_id < 1 || $event_id < 1) {
            return false;
        }

        $deleted = $wpdb->delete(
            $table_name,
            array("id" => $event_id, "customer_id" => $customer_id),
            array("%d", "%d")
        );

        return $deleted === 1;
    }
}
