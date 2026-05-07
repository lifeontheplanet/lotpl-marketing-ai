<?php

defined("ABSPATH") || exit;

final class LOTPL_Customer_Hub_Trip_Intelligence_Analyzer {
    public function analyze_unprocessed_events(int $batch_size = 20): array {
        global $wpdb;
        $events = $wpdb->prefix . "lotpl_customer_events";
        $trip = $wpdb->prefix . "lotpl_trip_intelligence";
        $total = (int) $wpdb->get_var("SELECT COUNT(*) FROM " . $events . " e LEFT JOIN " . $trip . " ti ON ti.event_id=e.id WHERE ti.id IS NULL AND e.event_type<>\"staff_share_link\"");
        $rows = $wpdb->get_results("SELECT e.id,e.customer_id,e.event_type,e.subject,e.body,e.created_at FROM " . $events . " e LEFT JOIN " . $trip . " ti ON ti.event_id=e.id WHERE ti.id IS NULL AND e.event_type<>\"staff_share_link\" ORDER BY e.created_at ASC,e.id ASC LIMIT 20", ARRAY_A);
        $batch_results=array(); if(is_array($rows)){foreach($rows as $row){if(is_array($row)){$batch_results[]=$this->build_insert_preview($row);}}} return array("processed" => count($batch_results), "total_unprocessed" => $total, "batch_size" => $batch_size, "batch_found" => is_array($rows) ? count($rows) : 0, "batch_results" => $batch_results, "message" => "Trip Intelligence batch processed.");
    }

    private function build_ai_extraction_prompt(array $event): string {
        return wp_json_encode(array("task"=>"Extract trip and quote facts only from this customer event. Do not invent prices.","allowed_currencies"=>array("INR","EUR","USD","GBP","JPY"),"required_keys"=>array("trips"),"trip_item_keys"=>array("tour_name","normalized_tour_key","itinerary_variant_key","travel_date_text","travel_month","party_size","trip_length","quoted_cost","currency","cost_text","cost_basis","quote_created_at","quote_date_source","itinerary_text","confidence"),"event"=>$event), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    private function build_insert_preview(array $event): array {
        $ai = $this->extract_with_openai($event);
        $result = isset($ai["result"]) && is_array($ai["result"]) ? $ai["result"] : array(); $trips = isset($result["trips"]) && is_array($result["trips"]) ? $result["trips"] : array($result); if (isset($trips[0]) && is_array($trips[0])) { $result = $trips[0]; }
        global $wpdb; $wpdb->delete($wpdb->prefix . "lotpl_trip_intelligence", array("event_id" => (int)$event["id"]), array("%d")); $seen=array(); $inserted=0; foreach($trips as $trip){ if(is_array($trip)===false){continue;} $sig=md5(wp_json_encode(array(isset($trip["tour_name"])?$trip["tour_name"]:"",isset($trip["normalized_tour_key"])?$trip["normalized_tour_key"]:"",isset($trip["itinerary_variant_key"])?$trip["itinerary_variant_key"]:"",isset($trip["trip_length"])?$trip["trip_length"]:""),JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)); if(isset($seen[$sig])){continue;} $seen[$sig]=true; $label=strtolower((isset($trip["tour_name"])?(string)$trip["tour_name"]:"")." ".(isset($trip["normalized_tour_key"])?(string)$trip["normalized_tour_key"]:"")); if(trim($label)===""){continue;} if(isset($trip["normalized_tour_key"]) && trim((string)$trip["normalized_tour_key"])===""){continue;} if(strpos($label,"surcharge")!==false||strpos($label,"single occupancy")!==false||strpos($label,"single supplement")!==false||strpos($label,"extra charge")!==false||strpos($label,"discount")!==false||strpos($label,"recalculation")!==false||strpos($label,"fuel cost")!==false||strpos($label,"supplement")!==false||strpos($label,"individual person")!==false){continue;} $wpdb->insert($wpdb->prefix . "lotpl_trip_intelligence", array("event_id" => (int)$event["id"], "customer_id" => (int)$event["customer_id"], "tour_name" => isset($trip["tour_name"]) ? (string)$trip["tour_name"] : "", "normalized_tour_key" => isset($trip["normalized_tour_key"]) ? str_replace("-","_",(string)$trip["normalized_tour_key"]) : "", "itinerary_variant_key" => isset($trip["itinerary_variant_key"]) ? (string)$trip["itinerary_variant_key"] : "", "trip_length" => isset($trip["trip_length"]) ? (string)$trip["trip_length"] : "", "travel_date_text" => isset($trip["travel_date_text"]) ? (string)$trip["travel_date_text"] : "", "travel_month" => isset($trip["travel_month"]) ? (string)$trip["travel_month"] : "", "party_size" => isset($trip["party_size"]) ? (string)$trip["party_size"] : "", "quoted_cost" => isset($trip["quoted_cost"]) ? $trip["quoted_cost"] : null, "currency" => isset($trip["currency"]) ? (string)$trip["currency"] : "", "cost_text" => isset($trip["cost_text"]) ? (string)$trip["cost_text"] : "", "cost_basis" => isset($trip["cost_basis"]) ? (string)$trip["cost_basis"] : "", "quote_created_at" => isset($trip["quote_created_at"]) ? (string)$trip["quote_created_at"] : null, "quote_date_source" => isset($trip["quote_date_source"]) ? (string)$trip["quote_date_source"] : "", "confidence" => isset($trip["confidence"]) ? $trip["confidence"] : null, "itinerary_text" => isset($trip["itinerary_text"]) ? (string)$trip["itinerary_text"] : "", "extracted_json" => wp_json_encode($trip, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES), "extracted_at" => current_time("mysql"), "updated_at" => current_time("mysql"))); $inserted++; } if($inserted<1){$wpdb->insert($wpdb->prefix."lotpl_trip_intelligence",array("event_id"=>(int)$event["id"],"customer_id"=>(int)$event["customer_id"],"normalized_tour_key"=>"no_tour_found","extracted_at"=>current_time("mysql"),"updated_at"=>current_time("mysql")));}
        return array("event_id" => isset($event["id"]) ? (int) $event["id"] : 0, "customer_id" => isset($event["customer_id"]) ? (int) $event["customer_id"] : 0, "source_event_created_at" => isset($event["created_at"]) ? (string) $event["created_at"] : "", "ai_result" => $result, "trip_count" => is_array($trips) ? count($trips) : 0);
    }

    private function extract_with_openai(array $event): array {
        $api_key = $this->get_api_key();
        if ($api_key === "") { return array("error" => "no_api_key"); }
        return $this->call_openai_for_extraction($event);
    }

    private function call_openai_for_extraction(array $event): array {
        $prompt = $this->build_ai_extraction_prompt($event);
        $body = array("model" => $this->get_model(), "response_format" => array("type" => "json_object"), "messages" => array(array("role" => "system", "content" => "Return strict JSON only with a trips array. You are extracting confirmed trip quotes for LIFE on the PLANET LADAKH.

RULES:
1. Only extract trips that are LOTPL offerings: treks, tours, expeditions, retreats in Ladakh or Zanskar.
2. Do NOT extract: hotel prices, single supplement fees, fuel costs, flight prices, permit fees, visa costs, transfers only, or any non-tour item.
3. normalized_tour_key MUST exactly match one of these slugs (then convert hyphens to underscores): ,rumtse-to-tsomoriri-trek markha-valley-trek 6-day-markha-valley-trek 7-day-markha-valley-trek-skiu-to-chokdo markha-valley-trek-7-days-chilling-to-shang-sumdo shang-sumdo-nimaling-via-kongmaru-la-pass changthang-traverse hemis-zangla-trek zanskar-expedition-17-day-journey-lost-valleys-monastic-secrets zanskar-trek-raru-to-tso-moriri-via-phuktal zanskar-trek-stongde-to-raru-via-phuktal leh-to-zanskar-trek-phuktal-gonpa-12-day lamayuru-to-darcha-trek lamayuru-chilling-trek-itinerary-ladakh-6-day-adventure lamayuru-to-alchi-via-stakspi-la-trek-a-5-day lamayuru-to-alchi-via-tarla-trek lamayuru-to-hemis-trek lamayuru-to-rupsho-tsokar-lake-trek padum-to-lamayuru-trek padum-to-darcha-trek padum-to-leh-via-charcha-la rangdum-to-lamayuru-via-kanji-la-trek-a-7-day rangdum-to-padum-trek hemis-to-padum-via-charchar-la hemis-to-tso-moriri-trek tso-moriri-to-kibber-trek-a-7-day tso-moriri-to-spiti-valley-trek-itinerary shang-to-stok-trek-ladakh-10-days-leh-hotel spituk-to-matho-trek-a-5-day spituk-to-stok-trek skiu-to-stok-trek-3-day sham-valley-trek-a-3-day sham-trek-monasteries ganda-la-trek-a-3-day zingchen-to-stok-la-to-stok-trek zinchen-to-kaya-a-4-day-homestay-trek-through-ladakhs-hidden-valleys 4-day-shashi-lake-trek-wilderness-culture-ladakh warwan-valley-loop-trek-10-days-epic-adventure suru-glacier-trek-7-day-high-altitude-adventure red-and-blue-lakes-trekking-adventure-zanskar 12-day-rupshu-valley-trek-guide-ladakh-high-altitude-trekking experience-ladakhs-moonland-4-5-day-lamayuru-to-sumda-do-homestay-trek 8-day-majestic-ladakh-trekking-adventure durbuk-to-sabu-trek zalung-karpo-la-trek kang-la sko-valley-trek kulum-leh-trek darcha-sarchu-trek phiyang-to-hunder-trek saboo-to-khalsar-trek kibber-trek likir-to-khaltse-trek zanskar-river-rafting offbeat-ladakh-car-safari-8-days ladakh-zanskar-tour ladakh-stargazing-tour ladakh-star-gazing-tour-7-nights-8-days ice-hockey-cultural-exchange-program ladakh-10-day-tour-nubra-pangong-hanle-tso-moriri 7-day-nubra-pangong-tso-tour-shayok ladakh-apricot-blossom-tour silk-route-odyssey-ladakh-tour zanskar-bike-tour-manali-to-leh ladakh-adventure-8-day-trek-scenic-tours-himalayas ladakh-apricot-blossom-expedition ladakh-cultural-exchange meditation-and-yoga-adventure meditation-yoga-and-trekking-bliss yoga-meditation-trek-retreat yoga-and-meditation-escape meditation-and-yoga-expedition ladakh-beyond-cycling-expedition ladakh-cycling-panorama-pedal ladakh-nomadic-odyssey ladakh-adventure-retreat ladakh-wilderness-photo-expedition ecological-exploration dah-hanu-alchi-lamayuru tso-moriri-tso-kar-tour-2-days pangong-tso-tour nubra-turtuk-tour-3-days thiksey-hemis-matho-chemrey-tour sham-monerstries-tour sham-monerstries-tour-1day monasteries-tour manali-to-leh-motor-bike lakes-monasteries-tour ladakh-pangong-tso wonder-of-ladakh-2 ladakh-wonders chadar-frozen-zanskar-trek-8-days chadar-trek-frozen-adventure-9-days kang-yatse-2-trek-second kang-yartse-expedition menthok-kangri-expedition mount-kun-climbing-expedition mount-nun-climbing-expedition mt-ut-kangri-peak-expedition-a-9-day tsomothang-summit-expedition-22-days nun-kun-tour everest-base-camp-trek-15-days sacred-journey-to-kailash-mansarovar-spiritual-odyssey
4. If the tour matches a slug in the list, use that slug. If it is a custom or tailored itinerary not in the list, create a short descriptive snake_case key such as custom_ladakh_itinerary or private_zanskar_trek.
5. Do not invent prices. If price is not explicitly quoted to this customer, set quoted_cost, currency, cost_text to null.
6. cost_basis must be: per_person, total, per_group, or unknown.
7. currency must be: INR, EUR, USD, GBP, JPY, or null.
8. Only extract prices clearly quoted TO this customer, not general website prices.
9. One entry per price and group size combination.
10. Do not write explanations. Return only the JSON object."), array("role" => "user", "content" => $prompt)));
        $response = wp_remote_post("https://api.openai.com/v1/chat/completions", array("timeout" => 30, "headers" => array("Authorization" => "Bearer " . $this->get_api_key(), "Content-Type" => "application/json"), "body" => wp_json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)));
        if (is_wp_error($response)) { return array("error" => "wp_error", "message" => $response->get_error_message()); }
        $decoded = json_decode((string) wp_remote_retrieve_body($response), true);
        $text = "";
        $text = isset($decoded["choices"][0]["message"]["content"]) ? (string)$decoded["choices"][0]["message"]["content"] : "";
        $result = json_decode(trim($text), true);
        if (is_array($result) === false) { return array("error" => "output_not_json", "model" => $this->get_model(), "status" => (int) wp_remote_retrieve_response_code($response), "output_text" => trim($text)); }
        return array("ok" => true, "model" => $this->get_model(), "status" => (int) wp_remote_retrieve_response_code($response), "result" => $result);
    }

    private function get_model(): string {
        if (defined("OPENAI_MODEL") && trim((string) OPENAI_MODEL) !== "") { return trim((string) OPENAI_MODEL); }
        $model = get_option("lotpl_customer_hub_openai_model", "");
        if (trim((string) $model) !== "") { return trim((string) $model); }
        $env_model = getenv("OPENAI_MODEL");
        if ($env_model !== false && trim((string) $env_model) !== "") { return trim((string) $env_model); }
        return "gpt-4o-mini";
    }

    private function get_api_key(): string {
        if (defined("OPENAI_API_KEY") && trim((string) OPENAI_API_KEY) !== "") { return trim((string) OPENAI_API_KEY); }
        $key = get_option("lotpl_customer_hub_openai_api_key", "");
        if (trim((string) $key) !== "") { return trim((string) $key); }
        $env_key = getenv("OPENAI_API_KEY");
        if ($env_key !== false && trim((string) $env_key) !== "") { return trim((string) $env_key); }
        return "";
    }
}
