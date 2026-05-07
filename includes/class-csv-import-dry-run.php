<?php

defined("ABSPATH") || exit;

final class LOTPL_Customer_Hub_CSV_Import_Dry_Run {
    public function run(string $csv_path): array {
        $result = array(
            "csv_path" => $csv_path,
            "rows_total" => 0,
            "rows_skipped" => 0,
            "customers_total" => 0,
            "events_total" => 0,
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

        $name_index = array_search("Customer Name", $header, true);
        $email_index = array_search("Email", $header, true);
        $tour_index = array_search("Tour", $header, true);
        $body_index = array_search("Body", $header, true);
        $direction_index = array_search("Direction", $header, true);
        if ($email_index === false) {
            fclose($handle);
            return $result;
        }

        $customers = array();
        $last_email_by_key = array();

        while (($row = fgetcsv($handle)) !== false) {
            $result["rows_total"]++;
            $name = $name_index !== false && isset($row[$name_index]) ? trim(wp_strip_all_tags((string) $row[$name_index])) : "";
            $tour = $tour_index !== false && isset($row[$tour_index]) ? trim(wp_strip_all_tags((string) $row[$tour_index])) : "";
            $key = strtolower($name . "||" . $tour);
            $email = isset($row[$email_index]) ? $this->normalize_email((string) $row[$email_index]) : "";
            $direction = $direction_index !== false && isset($row[$direction_index]) ? strtolower(trim((string) $row[$direction_index])) : "";

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
                continue;
            }

            if ($key !== "||") {
                $last_email_by_key[$key] = $email;
            }

            $result["events_total"]++;
            $customers[$email] = true;
        }

        fclose($handle);

        $result["customers_total"] = count($customers);

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
