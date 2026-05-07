<?php

defined("ABSPATH") || exit;

final class LOTPL_Customer_Hub_AI_Inquiry_Analyzer {
    private $last_openai_debug = array();
    public function analyze(array $payload): array {
        $openai_result = $this->analyze_with_openai($payload);
        if (is_array($openai_result) && empty($openai_result) === false) {
            if (isset($openai_result["missing_info"]) && is_array($openai_result["missing_info"])) {
                $openai_result["missing_info"] = array_values(array_filter($openai_result["missing_info"], function($v){ return strtolower(trim((string) $v)) !== "none"; }));
            }
            $openai_result["source"] = "openai";
            $openai_result["debug"] = $this->last_openai_debug;
            return $openai_result;
        }

        $classification = isset($payload["classification"]) && is_array($payload["classification"]) ? $payload["classification"] : array();
        $analysis = isset($payload["analysis"]) && is_array($payload["analysis"]) ? $payload["analysis"] : array();
        $message = isset($payload["message"]) ? trim((string) $payload["message"]) : "";
        $item_name = isset($payload["item_name"]) ? trim((string) $payload["item_name"]) : "";
        $intent = isset($classification["subtype"]) ? (string) $classification["subtype"] : "";
        $missing_info = isset($analysis["missing_info"]) && is_array($analysis["missing_info"]) ? $analysis["missing_info"] : array();
        $travel_timing = $this->extract_travel_timing($message);
        $party_size = $this->extract_party_size($message);
        if ($travel_timing !== "") {
            $missing_info = array_values(array_filter($missing_info, function($field){ return $field !== "travel_timing"; }));
        }

        if ($party_size !== "") {
            $missing_info = array_values(array_filter($missing_info, function($field){ return $field !== "party_size"; }));
        }

        if (preg_match("/\\b\\d+\\s*[- ]?day(s)?\\b/i", $item_name)) {
            $missing_info = array_values(array_filter($missing_info, function($field){ return $field !== "trip_length"; }));
        }

        $summary = $item_name !== "" ? "Guest is asking about " . $item_name . "." : "Guest sent a travel inquiry.";
        $reply_goal = $this->build_reply_goal($message, $missing_info);

        return array(
            "source" => "fallback",
            "intent" => $intent,
            "summary" => $summary,
            "facts" => array(
                "travel_timing" => $travel_timing,
                "party_size" => $party_size,
                "item_interest" => $item_name,
            ),
            "missing_info" => $missing_info,
            "reply_goal" => $reply_goal,
            "confidence" => 0.3,
            "should_auto_reply" => true,
            "reply_draft" => $this->build_reply_draft($payload, $message, $item_name, $missing_info),
            "debug" => $this->last_openai_debug,
        );
    }

    private function build_reply_goal(string $message, array $missing_info): string {
        $message_lc = strtolower($message);
        $parts = array();

        if (strpos($message_lc, "price") !== false || strpos($message_lc, "cost") !== false || strpos($message_lc, "fee") !== false || strpos($message_lc, "how much") !== false) {
            $parts[] = "Address the pricing question.";
        }

        if (strpos($message_lc, "available") !== false || strpos($message_lc, "availability") !== false || strpos($message_lc, "operate") !== false || strpos($message_lc, "running") !== false || strpos($message_lc, "departure") !== false) {
            $parts[] = "Clarify availability or operating status.";
        }

        if (strpos($message_lc, "solo") !== false || strpos($message_lc, "alone") !== false || strpos($message_lc, "myself") !== false) {
            $parts[] = "Address solo participation if relevant.";
        }

        if (strpos($message_lc, "winter") !== false || strpos($message_lc, "summer") !== false || strpos($message_lc, "spring") !== false || strpos($message_lc, "autumn") !== false || strpos($message_lc, "fall") !== false) {
            $parts[] = "Take the requested season into account.";
        }

        if (empty($missing_info) === false) {
            $parts[] = "Ask only for the missing information needed to reply more precisely.";
        }

        if (empty($parts)) {
            return "Provide a helpful follow-up reply.";
        }

        return implode(" ", $parts);
    }

    private function build_reply_draft(array $payload, string $message, string $item_name, array $missing_info): string {
        $name = isset($payload["name"]) ? trim((string) $payload["name"]) : "";
        $greeting = $name !== "" ? "Dear " . $name . "," : "Dear Guest,";
        $message_lc = strtolower($message);
        $lines = array();
        $lines[] = $greeting;
        $lines[] = "";
        if ($item_name !== "") {
            $lines[] = "Thank you very much for your inquiry about " . $item_name . ".";
        } else {
            $lines[] = "Thank you very much for your inquiry to LIFE on the PLANET LADAKH.";
        }
        $has_price = strpos($message_lc, "price") !== false || strpos($message_lc, "cost") !== false || strpos($message_lc, "fee") !== false || strpos($message_lc, "how much") !== false;
        $has_availability = strpos($message_lc, "available") !== false || strpos($message_lc, "availability") !== false || strpos($message_lc, "operate") !== false || strpos($message_lc, "running") !== false || strpos($message_lc, "departure") !== false;
        $has_season = strpos($message_lc, "winter") !== false || strpos($message_lc, "summer") !== false || strpos($message_lc, "spring") !== false || strpos($message_lc, "autumn") !== false || strpos($message_lc, "fall") !== false;
        if ($has_price || $has_availability || $has_season) {
            $parts = array();
            if ($has_price) {
                $parts[] = "pricing";
            }
            if ($has_availability) {
                $parts[] = "availability";
            }
            if ($has_season) {
                $parts[] = "seasonal suitability";
            }
            $lines[] = "We will review " . implode(", ", $parts) . " for your request.";
        }
        if (empty($missing_info) === false) {
            $lines[] = $this->build_missing_info_sentence($missing_info);
        } else {
            $lines[] = "We would be happy to help.";
            $lines[] = "Our team will review your request and get back to you shortly with a more personal reply.";
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

    private function build_missing_info_sentence(array $missing_info): string {
        $labels = array();
        foreach ($missing_info as $field) {
            if ($field === "travel_timing") {
                $labels[] = "your preferred travel dates or approximate timing";
            }
            if ($field === "party_size") {
                $labels[] = "how many people will be joining";
            }
            if ($field === "experience_level") {
                $labels[] = "your trekking experience level";
            }
            if ($field === "stay_dates") {
                $labels[] = "your planned stay dates";
            }
            if ($field === "nights") {
                $labels[] = "how many nights you would like to stay";
            }
            if ($field === "trip_length") {
                $labels[] = "your preferred trip length";
            }
            if ($field === "interests") {
                $labels[] = "the places or experiences you are most interested in";
            }
        }
        if (empty($labels)) {
            return "Our team will get back to you shortly with a more personal reply.";
        }
        if (count($labels) === 1) {
            $label_text = $labels[0];
        } else {
            $last = array_pop($labels);
            $label_text = implode(", ", $labels) . " and " . $last;
        }
        return "We would be happy to help. To suggest the most suitable option and share accurate details, could you please let us know " . $label_text . "?";
    }

    private function analyze_with_openai(array $payload): array {
        $this->last_openai_debug = array("stage" => "start", "model" => $this->get_model());
        $api_key = $this->get_api_key();
        if ($api_key === "") {
            $this->last_openai_debug = array("stage" => "no_api_key", "model" => $this->get_model());
            return array();
        }

        $body = array(
            "model" => $this->get_model(),
            "input" => array(
                array(
                    "role" => "system",
                    "content" => array(
                        array(
                            "type" => "input_text",
                            "text" => "You analyze travel inquiries for LIFE on the PLANET LADAKH. Return strict JSON with keys intent, summary, facts, missing_info, reply_goal, confidence, should_auto_reply, reply_draft, source. Do not include markdown. Keep reply_draft concise, warm, professional, and in English. Ask only for truly missing information. In reply_draft, always begin with Dear {name}, if a sender name is available, otherwise Dear Guest,. Always end with exactly this signature block: Warm regards, followed by a blank line, then Tashi Anchok, Mohd Amin, Junichiro Honjo, LIFE on the PLANET LADAKH, Kukarchey, Chiktan, Ladakh, India, +91 9622867197, info@lifeontheplanetladakh.com, https://lifeontheplanetladakh.com. Avoid making operational promises or factual claims you cannot verify; for uncertain matters, use cautious wording such as may be possible, depends on availability, or we will be happy to advise. Set should_auto_reply to false for complaints, partnership pitches, sales outreach, press/media requests, legal issues, medical or altitude-sickness concerns, emergencies, refund disputes, safety-critical judgement calls, or complex custom requests that clearly need human review."
                        )
                    )
                ),
                array(
                    "role" => "user",
                    "content" => array(
                        array(
                            "type" => "input_text",
                            "text" => wp_json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
                        )
                    )
                )
            ),
            "text" => array(
                "format" => array(
                    "type" => "text"
                )
            )
        );

        $response = wp_remote_post(
            "https://api.openai.com/v1/responses",
            array(
                "timeout" => 30,
                "headers" => array(
                    "Authorization" => "Bearer " . $api_key,
                    "Content-Type" => "application/json",
                ),
                "body" => wp_json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            )
        );

        if (is_wp_error($response)) {
            $this->last_openai_debug = array("stage" => "wp_error", "model" => $this->get_model(), "message" => $response->get_error_message());
            return array();
        }

        $status = (int) wp_remote_retrieve_response_code($response);
        if ($status < 200 || $status >= 300) {
            $this->last_openai_debug = array("stage" => "http_error", "model" => $this->get_model(), "status" => $status, "body" => substr((string) wp_remote_retrieve_body($response), 0, 500));
            return array();
        }

        $decoded = json_decode((string) wp_remote_retrieve_body($response), true);
        if (is_array($decoded) === false) {
            return array();
        }

        $text = "";
        if (isset($decoded["output"]) && is_array($decoded["output"])) {
            foreach ($decoded["output"] as $output_item) {
                if (isset($output_item["content"]) === false || is_array($output_item["content"]) === false) {
                    continue;
                }
                foreach ($output_item["content"] as $content_item) {
                    if (isset($content_item["type"]) && $content_item["type"] === "output_text" && isset($content_item["text"])) {
                        $text .= (string) $content_item["text"];
                    }
                }
            }
        }

        if (trim($text) === "") {
            $this->last_openai_debug = array("stage" => "no_output_text", "model" => $this->get_model(), "status" => $status, "body" => substr((string) wp_remote_retrieve_body($response), 0, 500));
            return array();
        }

        $result = json_decode($text, true);
        if (is_array($result) === false) {
            $this->last_openai_debug = array("stage" => "output_not_json", "model" => $this->get_model(), "status" => $status, "text" => substr($text, 0, 500));
            return array();
        }

        $this->last_openai_debug = array("stage" => "success", "model" => $this->get_model(), "status" => $status);
        return $result;
    }

    private function extract_travel_timing(string $message): string {
        $message_lc = strtolower($message);

        if (strpos($message_lc, "winter") !== false) {
            return "winter";
        }

        if (strpos($message_lc, "summer") !== false) {
            return "summer";
        }

        if (strpos($message_lc, "autumn") !== false || strpos($message_lc, "fall") !== false) {
            return "autumn";
        }

        if (strpos($message_lc, "spring") !== false) {
            return "spring";
        }

        return "";
    }

    private function get_model(): string {
        if (defined("OPENAI_MODEL") && trim((string) OPENAI_MODEL) !== "") {
            return trim((string) OPENAI_MODEL);
        }

        $model = get_option("lotpl_customer_hub_openai_model", "");
        if (trim((string) $model) !== "") {
            return trim((string) $model);
        }

        $env_model = getenv("OPENAI_MODEL");
        if ($env_model !== false && trim((string) $env_model) !== "") {
            return trim((string) $env_model);
        }

        return "gpt-5.4-mini";
    }

    private function get_api_key(): string {
        if (defined("OPENAI_API_KEY") && trim((string) OPENAI_API_KEY) !== "") {
            return trim((string) OPENAI_API_KEY);
        }

        $key = get_option("lotpl_customer_hub_openai_api_key", "");
        if (trim((string) $key) !== "") {
            return trim((string) $key);
        }

        $env_key = getenv("OPENAI_API_KEY");
        if ($env_key !== false && trim((string) $env_key) !== "") {
            return trim((string) $env_key);
        }

        return "";
    }

    private function extract_party_size(string $message): string {
        $message_lc = strtolower($message);

        if (strpos($message_lc, "solo") !== false || strpos($message_lc, "alone") !== false || strpos($message_lc, "myself") !== false) {
            return "solo";
        }

        if (strpos($message_lc, "my wife and i") !== false || strpos($message_lc, "my husband and i") !== false || strpos($message_lc, "the two of us") !== false || strpos($message_lc, "two of us") !== false) {
            return "2";
        }

        if (strpos($message_lc, "2 people") !== false || strpos($message_lc, "two people") !== false || strpos($message_lc, "2 pax") !== false) {
            return "2";
        }

        if (strpos($message_lc, "3 people") !== false || strpos($message_lc, "three people") !== false || strpos($message_lc, "3 pax") !== false) {
            return "3";
        }

        if (strpos($message_lc, "4 people") !== false || strpos($message_lc, "four people") !== false || strpos($message_lc, "4 pax") !== false) {
            return "4";
        }

        if (strpos($message_lc, "friends") !== false || strpos($message_lc, "family") !== false || strpos($message_lc, "group") !== false) {
            return "group";
        }

        return "";
    }
}
