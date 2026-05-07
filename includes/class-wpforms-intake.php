<?php

defined("ABSPATH") || exit;

require_once __DIR__ . "/class-customers-repository.php";
require_once __DIR__ . "/class-events-repository.php";
require_once __DIR__ . "/class-inquiry-classifier.php";
require_once __DIR__ . "/class-travel-inquiry-analyzer.php";
require_once __DIR__ . "/class-ai-inquiry-analyzer.php";

final class LOTPL_Customer_Hub_WPForms_Intake {
    public function register(): void {
        add_action("wpforms_process_complete", array($this, "capture_submission"), 10, 4);
    }

    public function capture_submission(array $fields, array $entry, array $form_data, int $entry_id): void {
        $form_id = isset($form_data["id"]) ? (int) $form_data["id"] : 0;

        if ($this->is_supported_form($form_id) === false) {
            return;
        }

        $payload = $this->extract_payload($form_id, $fields);

        if ($payload["email"] === "") {
            return;
        }

        $spam_text = strtolower(trim($payload["name"] . " " . $payload["message"]));
        $word_count = str_word_count($spam_text);
        if ((strpos($spam_text, "doombgm") !== false) || ($word_count <= 8 && (strpos($spam_text, "price") !== false || strpos($spam_text, "prezzo") !== false || strpos($spam_text, "preu") !== false || strpos($spam_text, "precio") !== false))) {
            return;
        }

        $classifier = new LOTPL_Customer_Hub_Inquiry_Classifier();

        $api_key=defined("OPENAI_API_KEY")?OPENAI_API_KEY:get_option("lotpl_customer_hub_openai_api_key",""); if($api_key!==""){ $spam_prompt="You are a spam filter for a Ladakh travel company. Analyze this inquiry and return JSON only: {\"is_spam\": true} or {\"is_spam\": false}. Mark as spam if: random-looking name, price-only question with no travel details, no dates or group size, copy-paste template feel, or no genuine travel intent. Genuine inquiries mention travel dates, group size, specific tours, or personal travel context."; $spam_body=wp_json_encode(array("model"=>"gpt-4o-mini","response_format"=>array("type"=>"json_object"),"messages"=>array(array("role"=>"system","content"=>$spam_prompt),array("role"=>"user","content"=>"Name: ".$payload["name"]."\nMessage: ".$payload["message"]))),JSON_UNESCAPED_UNICODE); $spam_resp=wp_remote_post("https://api.openai.com/v1/chat/completions",array("timeout"=>10,"headers"=>array("Authorization"=>"Bearer ".$api_key,"Content-Type"=>"application/json"),"body"=>$spam_body)); if(is_wp_error($spam_resp)===false){ $spam_decoded=json_decode((string)wp_remote_retrieve_body($spam_resp),true); $spam_result=json_decode(isset($spam_decoded["choices"][0]["message"]["content"])?(string)$spam_decoded["choices"][0]["message"]["content"]:"{}",true); if(isset($spam_result["is_spam"])&&$spam_result["is_spam"]===true){ return; } } }
        $classification = $classifier->classify($payload["subject"], $payload["message"], $payload["item_name"]);
        $payload["classification"] = $classification;

        if ($classification["type"] === "travel_inquiry") {
            $analyzer = new LOTPL_Customer_Hub_Travel_Inquiry_Analyzer();
            $payload["analysis"] = $analyzer->analyze($classification, $payload);

            $ai_analyzer = new LOTPL_Customer_Hub_AI_Inquiry_Analyzer();
            $payload["ai_analysis"] = $ai_analyzer->analyze($payload);
        }

        if ($classification["type"] !== "travel_inquiry") {
            $ai_analyzer = new LOTPL_Customer_Hub_AI_Inquiry_Analyzer();
            $payload["ai_analysis"] = $ai_analyzer->analyze($payload);
            if (isset($payload["ai_analysis"]["should_auto_reply"]) && $payload["ai_analysis"]["should_auto_reply"] === false) {
                return;
            }
            $payload["classification"] = array("type" => "travel_inquiry", "subtype" => "ai_human_review");
            $payload["needs_human_review"] = true;
        }

        $customers_repo = new LOTPL_Customer_Hub_Customers_Repository();
        $events_repo = new LOTPL_Customer_Hub_Events_Repository();
        $customer_id = $customers_repo->find_or_create_by_email($payload["name"], $payload["email"]);

        if ($customer_id < 1) {
            return;
        }

        $events_repo->add_event(
            $customer_id,
            "form_submission",
            "wpforms",
            $payload["subject"],
            $this->build_event_body($payload),
            $payload
        );

        if (isset($payload["classification"]["type"]) && $payload["classification"]["type"] !== "travel_inquiry") {
            return;
        }

        if (isset($payload["ai_analysis"]["should_auto_reply"]) && $payload["ai_analysis"]["should_auto_reply"] === false) {
            return;
        }

        $events_repo->schedule_auto_reply(
            $customer_id,
            "Auto reply pending: " . $payload["subject"],
            $this->build_event_body($payload),
            $payload
        );
    }

    private function extract_payload(int $form_id, array $fields): array {
        return array(
            "form_id" => $form_id,
            "name" => $this->get_field_value($fields, 0),
            "email" => sanitize_email($this->get_field_value($fields, 1)),
            "message" => $this->get_field_value($fields, 2),
            "subject" => $this->build_subject($form_id, $fields),
            "item_name" => $this->get_field_value($fields, 4),
            "item_url" => $this->get_field_value($fields, 5),
            "page_url" => isset($_SERVER["REQUEST_URI"]) ? home_url((string) $_SERVER["REQUEST_URI"]) : "",
            "referrer" => isset($_SERVER["HTTP_REFERER"]) ? esc_url_raw((string) $_SERVER["HTTP_REFERER"]) : "",
            "user_agent" => isset($_SERVER["HTTP_USER_AGENT"]) ? trim((string) $_SERVER["HTTP_USER_AGENT"]) : "",
            "submitted_at_utc" => gmdate("Y-m-d H:i:s"),
        );
    }

    private function build_event_body(array $payload): string {
        $lines = array();

        if ($payload["item_name"] !== "") {
            $lines[] = "Item: " . $payload["item_name"];
        }

        if ($payload["item_url"] !== "") {
            $lines[] = "URL: " . $payload["item_url"];
        }

        if ($payload["name"] !== "") {
            $lines[] = "Name: " . $payload["name"];
        }

        if ($payload["email"] !== "") {
            $lines[] = "Email: " . $payload["email"];
        }

        if ($payload["message"] !== "") {
            $lines[] = "";
            $lines[] = "Message:";
            $lines[] = $payload["message"];
        }

        return trim(implode(PHP_EOL, $lines));
    }

    private function build_subject(int $form_id, array $fields): string {
        if ($form_id === 1141) {
            return "Tour Inquiry: " . $this->get_field_value($fields, 4);
        }

        if ($form_id === 49955) {
            return "Accommodation Inquiry: " . $this->get_field_value($fields, 4);
        }

        return "New Contact Inquiry";
    }

    private function get_field_value(array $fields, int $field_id): string {
        if (isset($fields[$field_id]["value"]) === false) {
            return "";
        }

        return trim(wp_strip_all_tags((string) $fields[$field_id]["value"]));
    }

    private function is_supported_form(int $form_id): bool {
        return in_array($form_id, array(49951, 1141, 49955), true);
    }
}
