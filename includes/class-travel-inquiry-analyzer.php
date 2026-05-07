<?php

defined("ABSPATH") || exit;

final class LOTPL_Customer_Hub_Travel_Inquiry_Analyzer {
    public function analyze(array $classification, array $payload): array {
        $subtype = isset($classification["subtype"]) ? (string) $classification["subtype"] : "";

        $required_fields = $this->get_required_fields($subtype);

        return array(
            "subtype" => $subtype,
            "required_fields" => $required_fields,
            "missing_info" => $required_fields,
        );
    }

    private function get_required_fields(string $subtype): array {
        if ($subtype === "trekking_inquiry") {
            return array("travel_timing", "party_size", "experience_level");
        }

        if ($subtype === "accommodation_inquiry") {
            return array("stay_dates", "nights", "party_size");
        }

        if ($subtype === "custom_trip_inquiry") {
            return array("travel_timing", "party_size", "trip_length", "interests");
        }

        return array("travel_timing", "party_size");
    }
}
