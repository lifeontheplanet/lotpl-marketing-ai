<?php

defined("ABSPATH") || exit;

require_once __DIR__ . "/class-customers-repository.php";
require_once __DIR__ . "/class-events-repository.php";

final class LOTPL_Customer_Hub_Auto_Reply_Runner {
    public function register(): void {
        add_action("init", array($this, "maybe_run"));
        add_filter("wp_mail_from", array($this, "mail_from"));
        add_filter("wp_mail_from_name", array($this, "mail_from_name"));
    }

    public function mail_from(string $from): string {
        return "info@lifeontheplanetladakh.com";
    }

    public function mail_from_name(string $name): string {
        return "LIFE on the PLANET LADAKH";
    }

    public function maybe_run(): void {
        $events_repo = new LOTPL_Customer_Hub_Events_Repository();
        $scheduled_event = $events_repo->get_next_scheduled_auto_reply();

        if (is_array($scheduled_event) === false) {
            return;
        }

        $event_id = isset($scheduled_event["id"]) ? (int) $scheduled_event["id"] : 0;

        if ($event_id < 1) {
            return;
        }

        if ($events_repo->mark_scheduled_as_processing($event_id) === false) {
            return;
        }

        $customer_id = isset($scheduled_event["customer_id"]) ? (int) $scheduled_event["customer_id"] : 0;
        $customers_repo = new LOTPL_Customer_Hub_Customers_Repository();
        $customer = $customers_repo->get_by_id($customer_id);

        if (is_array($customer) === false) {
            $events_repo->mark_processing_as_failed($event_id);
            return;
        }

        $customer_email = isset($customer["email"]) ? sanitize_email((string) $customer["email"]) : "";

        if ($customer_email === "") {
            $events_repo->mark_processing_as_failed($event_id);
            return;
        }

        $meta = array();

        if (isset($scheduled_event["meta_json"]) && (string) $scheduled_event["meta_json"] !== "") {
            $decoded = json_decode((string) $scheduled_event["meta_json"], true);
            if (is_array($decoded)) {
                $meta = $decoded;
            }
        }

        $original_subject = isset($meta["subject"]) ? trim((string) $meta["subject"]) : "";
        $customer_name = isset($customer["name"]) ? trim((string) $customer["name"]) : "";
        $subject_base = $original_subject !== "" ? "Re: " . $original_subject : "Re: Your inquiry to LIFE on the PLANET LADAKH";
        $subject = $customer_name !== "" ? $subject_base . " | " . $customer_name : $subject_base;
        $body = isset($meta["ai_analysis"]["reply_draft"]) && trim((string) $meta["ai_analysis"]["reply_draft"]) !== "" ? $this->normalize_ai_reply($customer, (string) $meta["ai_analysis"]["reply_draft"]) : $this->build_reply_body($customer, $meta);
        $headers = array("Content-Type: text/plain; charset=UTF-8", "Cc: info@lifeontheplanetladakh.com");
        $sent = wp_mail($customer_email, $subject, $body, $headers);

        if ($sent) {
            $events_repo->mark_processing_as_sent($event_id);
            $events_repo->add_event(
                $customer_id,
                "auto_reply_sent",
                "system",
                $subject,
                $body,
                array("scheduled_event_id" => $event_id, "email_sent" => true, "to_email" => $customer_email, "cc_emails" => "info@lifeontheplanetladakh.com")
            );
            return;
        }

        $events_repo->mark_processing_as_failed($event_id);
        $events_repo->add_event(
            $customer_id,
            "auto_reply_failed",
            "system",
            $subject,
            $body,
            array("scheduled_event_id" => $event_id, "email_sent" => false, "to_email" => $customer_email, "cc_emails" => "info@lifeontheplanetladakh.com")
        );
    }

    private function normalize_ai_reply(array $customer, string $body): string {
        $body = trim($body);
        if ($body === "") {
            return $body;
        }

        $name = isset($customer["name"]) ? trim((string) $customer["name"]) : "";
        $greeting = $name !== "" ? "Dear " . $name . "," : "Dear Guest,";
        $signature = implode(PHP_EOL, array(
            "Warm regards,",
            "",
            "Tashi Anchok",
            "Mohd Amin",
            "Junichiro Honjo",
            "LIFE on the PLANET LADAKH",
            "Kukarchey, Chiktan, Ladakh, India",
            "+91 9622867197",
            "info@lifeontheplanetladakh.com",
            "https://lifeontheplanetladakh.com",
        ));
        $flat_signature = "Warm regards," . PHP_EOL . PHP_EOL . "Tashi Anchok, Mohd Amin, Junichiro Honjo, LIFE on the PLANET LADAKH, Kukarchey, Chiktan, Ladakh, India, +91 9622867197, info@lifeontheplanetladakh.com, https://lifeontheplanetladakh.com";
        $body = str_replace($flat_signature, $signature, $body);

        if (strpos($body, "Dear ") !== 0) {
            $body = $greeting . PHP_EOL . PHP_EOL . $body;
        }

        if (strpos($body, "Tashi Anchok") === false || strpos($body, "info@lifeontheplanetladakh.com") === false) {
            $body .= PHP_EOL . PHP_EOL . $signature;
        }

        return $body;
    }

    private function build_detail_line(string $message): string {
        $message_lc = strtolower($message);
        $parts = array();

        if (strpos($message_lc, "oct") !== false || strpos($message_lc, "nov") !== false || strpos($message_lc, "sep") !== false || strpos($message_lc, "date") !== false || strpos($message_lc, "when") !== false || strpos($message_lc, "month") !== false) {
            $parts[] = "We will check the travel timing and seasonal suitability as part of our reply.";
        }

        if (strpos($message_lc, "price") !== false || strpos($message_lc, "cost") !== false || strpos($message_lc, "fee") !== false || strpos($message_lc, "quote") !== false) {
            $parts[] = "We will also review the pricing details for your request.";
        }

        if (strpos($message_lc, "solo") !== false || strpos($message_lc, "myself") !== false || strpos($message_lc, "alone") !== false || strpos($message_lc, "individual") !== false) {
            $parts[] = "We noted that you may be planning this as a solo journey.";
        }

        if (strpos($message_lc, "people") !== false || strpos($message_lc, "person") !== false || strpos($message_lc, "pax") !== false || strpos($message_lc, "solo") !== false || strpos($message_lc, "myself") !== false || strpos($message_lc, "alone") !== false) {
            $parts[] = "We will take the group size into account in our follow-up.";
        }

        if (strpos($message_lc, "available") !== false || strpos($message_lc, "availability") !== false || strpos($message_lc, "operate") !== false || strpos($message_lc, "running") !== false || strpos($message_lc, "departure") !== false) {
            $parts[] = "We will confirm availability and operating status for you.";
        }

        return implode(" ", $parts);
    }

    private function build_missing_info_question(array $meta): string {
        if (isset($meta["ai_analysis"]["missing_info"]) && is_array($meta["ai_analysis"]["missing_info"])) {
            $missing = $meta["ai_analysis"]["missing_info"];
        } elseif (isset($meta["analysis"]["missing_info"]) && is_array($meta["analysis"]["missing_info"])) {
            $missing = $meta["analysis"]["missing_info"];
        } else {
            return "";
        }
        $labels = array();

        foreach ($missing as $field) {
            if ($field === "travel_timing") {
                $labels[] = "your preferred travel timing";
            }
            if ($field === "party_size") {
                $labels[] = "your expected party size";
            }
            if ($field === "experience_level") {
                $labels[] = "your trekking experience level";
            }
            if ($field === "stay_dates") {
                $labels[] = "your stay dates";
            }
            if ($field === "nights") {
                $labels[] = "the number of nights";
            }
            if ($field === "trip_length") {
                $labels[] = "your preferred trip length";
            }
            if ($field === "interests") {
                $labels[] = "the places or experiences you are most interested in";
            }
        }

        if (empty($labels)) {
            return "";
        }

        return "To help us reply more precisely, could you please let us know " . implode(", ", $labels) . "?";
    }

    private function build_reply_body(array $customer, array $meta): string {
        $name = isset($customer["name"]) ? trim((string) $customer["name"]) : "";
        $item_name = isset($meta["item_name"]) ? trim((string) $meta["item_name"]) : "";
        $message = isset($meta["message"]) ? trim((string) $meta["message"]) : "";
        $greeting = $name !== "" ? "Dear " . $name . "," : "Dear Guest,";
        $lines = array();
        $lines[] = $greeting;
        $lines[] = "";
        if ($item_name !== "") {
            $lines[] = "Thank you very much for your inquiry about " . $item_name . ".";
        } else {
            $lines[] = "Thank you very much for your inquiry to LIFE on the PLANET LADAKH.";
        }
        if ($message !== "") {
            $lines[] = "We have received your message and will review the details carefully.";
        }
        $detail_line = $this->build_detail_line($message);
        if ($detail_line !== "") {
            $lines[] = $detail_line;
        }
        $missing_info_question = $this->build_missing_info_question($meta);
        if ($missing_info_question !== "") {
            $lines[] = $missing_info_question;
        } else {
            $lines[] = "Our team will get back to you shortly with a more personal reply.";
        }
        $lines[] = "";
        $lines[] = "Warm regards,";
        $lines[] = "";
        $lines[] = "Tashi Anchok";
        $lines[] = "Mohd Amin";
        $lines[] = "Junichiro Honjo";
        $lines[] = "LIFE on the PLANET LADAKH";
        $lines[] = "Kukarchey, Chiktan, Ladakh, India";
        $lines[] = "+91 9622867197";
        $lines[] = "info@lifeontheplanetladakh.com";
        $lines[] = "https://lifeontheplanetladakh.com";
        return implode(PHP_EOL, $lines);
    }
}
