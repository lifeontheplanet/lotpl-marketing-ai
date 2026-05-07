<?php

defined("ABSPATH") || exit;

require_once __DIR__ . "/class-customers-repository.php";

final class LOTPL_Customer_Hub_CSV_Importer {
    public function run(string $csv_path, int $limit = 5): array {
        global $wpdb;

        $result = array(
            "csv_path" => $csv_path,
            "limit" => $limit,
            "rows_seen" => 0,
            "rows_skipped" => 0,
            "customers_imported" => 0,
            "events_imported" => 0,
            "readable" => false,
        );

        if ($csv_path === "" || file_exists($csv_path) === false || is_readable($csv_path) === false) {
            return $result;
        }

        $result["readable"] = true;

        $handle = fopen($csv_path, "r");
        if ($handle === false) {
            return $result;
        }

        $header = fgetcsv($handle);
        if (is_array($header) === false) {
            fclose($handle);
            return $result;
        }

        if (isset($header[0])) {
            $header[0] = ltrim((string) $header[0], "\xEF\xBB\xBF");
        }

        $customers_repo = new LOTPL_Customer_Hub_Customers_Repository();
        $events_table = $wpdb->prefix . "lotpl_customer_events";
        $name_index = array_search("Customer Name", $header, true);
        $email_index = array_search("Email", $header, true);
        $tour_index = array_search("Tour", $header, true);
        $date_index = array_search("Date", $header, true);
        $direction_index = array_search("Direction", $header, true);
        $subject_index = array_search("Subject", $header, true);
        $body_index = array_search("Body", $header, true);
        $last_email_by_key = array();

        while (($row = fgetcsv($handle)) !== false) {
            $result["rows_seen"]++;

            $name = $name_index !== false && isset($row[$name_index]) ? trim(wp_strip_all_tags((string) $row[$name_index])) : "";
            $tour = $tour_index !== false && isset($row[$tour_index]) ? trim(wp_strip_all_tags((string) $row[$tour_index])) : "";
            $key = strtolower($name . "||" . $tour);
            $email = $email_index !== false && isset($row[$email_index]) ? $this->normalize_email((string) $row[$email_index]) : "";
            $direction = $direction_index !== false && isset($row[$direction_index]) ? strtolower(trim((string) $row[$direction_index])) : "";
            $date_value = $date_index !== false && isset($row[$date_index]) ? trim((string) $row[$date_index]) : "";
            $subject = $subject_index !== false && isset($row[$subject_index]) ? trim(wp_strip_all_tags((string) $row[$subject_index])) : "";
            $body = $body_index !== false && isset($row[$body_index]) ? trim((string) $row[$body_index]) : "";
            $event_type = "email";
            $event_source = "csv_import";
            $sent_at = $direction === "sent" && $date_value !== "" ? $date_value . " 00:00:00" : null;

            if ($email === "" && $tour !== "") {
                $email = $this->normalize_email($tour);
            }

            if ($email === "" && $direction === "received" && $body_index !== false && isset($row[$body_index])) {
                $email = $this->extract_email_from_body((string) $row[$body_index]);
            }

            if ($email === "" && $key !== "||" && isset($last_email_by_key[$key])) {
                $email = $last_email_by_key[$key];
            }

            if ($email === "") {
                $result["rows_skipped"]++;
            } else {
                if ($key !== "||") {
                    $last_email_by_key[$key] = $email;
                }

                $customer_id = $customers_repo->find_or_create_by_email($name, $email);
                if ($customer_id > 0) {
                    $result["customers_imported"]++;
                    $created_at = $date_value !== "" ? $date_value . " 00:00:00" : current_time("mysql", true);
                    $exists = (int) $wpdb->get_var($wpdb->prepare("SELECT id FROM {$events_table} WHERE customer_id = %d AND event_source = %s AND subject = %s AND body = %s ORDER BY id DESC LIMIT 1", $customer_id, $event_source, $subject, $body));
                    if ($exists < 1) {
                        $meta_json = wp_json_encode(array("direction" => $direction, "tour" => $tour), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                        $inserted = $wpdb->insert($events_table, array("customer_id" => $customer_id, "event_type" => $event_type, "event_source" => $event_source, "subject" => $subject, "body" => $body, "meta_json" => $meta_json, "sent_at" => $sent_at, "status" => "imported", "created_at" => $created_at), array("%d", "%s", "%s", "%s", "%s", "%s", "%s", "%s", "%s"));
                        if ($inserted === 1) {
                            $result["events_imported"]++;
                        }
                    }
                }
            }

            if ($result["rows_seen"] >= $limit) {
                break;
            }
        }

        fclose($handle);

        return $result;
    }

    private function normalize_email(string $email): string {
        $email = trim($email);

        if (stripos($email, "mailto:") === 0) {
            $email = substr($email, 7);
        }

        $email = sanitize_email($email);

        return $email;
    }

    private function extract_email_from_body(string $body): string {
        if (preg_match("~[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}~i", $body, $m) !== 1) {
            return "";
        }

        return $this->normalize_email((string) $m[0]);
    }
}
