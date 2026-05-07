<?php

defined("ABSPATH") || exit;

final class LOTPL_Customer_Hub_Inquiry_Classifier {
    public function classify(string $subject, string $message, string $item_name = ""): array {
        $text = strtolower(trim($subject . "\n" . $message . "\n" . $item_name));
        $item_name = trim($item_name);

        if ($this->is_spam($text)) {
            return array("type" => "spam");
        }

        if ($this->is_sales_outreach($text)) {
            return array("type" => "sales_outreach");
        }

        if ($item_name !== "" || $this->is_travel_text($text)) {
            return array("type" => "travel_inquiry", "subtype" => $this->determine_travel_subtype($text, $item_name));
        }

        return array("type" => "non_travel");
    }

    private function determine_travel_subtype(string $text, string $item_name): string {
        $item_name_lc = strtolower($item_name);

        if (strpos($text, "accommodation") !== false || strpos($text, "hotel") !== false || strpos($text, "homestay") !== false || strpos($item_name_lc, "hotel") !== false || strpos($item_name_lc, "camp") !== false || strpos($item_name_lc, "homestay") !== false) {
            return "accommodation_inquiry";
        }

        if (strpos($text, "trek") !== false || strpos($text, "trekking") !== false || strpos($item_name_lc, "trek") !== false) {
            return "trekking_inquiry";
        }

        if (strpos($text, "custom") !== false || strpos($text, "plan") !== false || strpos($text, "itinerary") !== false || strpos($text, "tailor-made") !== false) {
            return "custom_trip_inquiry";
        }

        return "tour_inquiry";
    }

    private function is_travel_text(string $text): bool {
        $needles = array(
            "tour",
            "trek",
            "trekking",
            "trip",
            "ladakh",
            "zanskar",
            "itinerary",
            "departure",
            "availability",
            "price",
            "cost",
            "accommodation",
            "homestay",
            "car safari",
            "summer",
            "winter"
        );

        foreach ($needles as $needle) {
            if (strpos($text, $needle) !== false) {
                return true;
            }
        }

        return false;
    }

    private function is_sales_outreach(string $text): bool {
        $needles = array(
            "seo",
            "backlink",
            "guest post",
            "traffic",
            "marketing service",
            "boost your traffic",
            "web design",
            "lead generation",
            "google ranking"
        );

        foreach ($needles as $needle) {
            if (strpos($text, $needle) !== false) {
                return true;
            }
        }

        return false;
    }

    private function is_spam(string $text): bool {
        $needles = array(
            "casino",
            "crypto",
            "viagra",
            "porn",
            "xbet",
            "1xbet",
            "justmail.click"
        );

        foreach ($needles as $needle) {
            if (strpos($text, $needle) !== false) {
                return true;
            }
        }

        return false;
    }
}
