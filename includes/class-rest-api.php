<?php

defined("ABSPATH") || exit;

require_once __DIR__ . "/class-customers-repository.php";
require_once __DIR__ . "/class-events-repository.php";

final class LOTPL_Customer_Hub_REST_API {
    public function register(): void {
        add_action("rest_api_init", array($this, "register_routes"));
    }

    public function register_routes(): void {
        register_rest_route("lotpl-customer-hub/v1", "/search", array("methods" => "GET", "callback" => array($this, "search_customers"), "permission_callback" => function($request) { $nonce = $request->get_header("X-WP-Nonce"); if ($nonce && wp_verify_nonce($nonce, "wp_rest")) { return true; } return current_user_can("manage_options"); }, ));

        register_rest_route("lotpl-customer-hub/v1", "/plan", array(
            "methods" => "POST",
            "callback" => array($this, "generate_ladakh_plan"),
            "permission_callback" => "__return_true",
        ));

        register_rest_route("lotpl-customer-hub/v1", "/customers", array(
            "methods" => "GET",
            "callback" => array($this, "get_customers"),
            "permission_callback" => function($request) {
                $nonce = $request->get_header("X-WP-Nonce");
                if ($nonce && wp_verify_nonce($nonce, "wp_rest")) { return true; }
                return current_user_can("manage_options");
            },
        ));

        register_rest_route("lotpl-customer-hub/v1", "/customers", array(
            "methods" => "POST",
            "callback" => array($this, "create_customer"),
            "permission_callback" => function($request) {
                $nonce = $request->get_header("X-WP-Nonce");
                if ($nonce && wp_verify_nonce($nonce, "wp_rest")) { return true; }
                return current_user_can("manage_options");
            },
        ));

        register_rest_route("lotpl-customer-hub/v1", "/customers/(?P<id>\\d+)", array(
            "methods" => "GET",
            "callback" => array($this, "get_customer"),
            "permission_callback" => function($request) {
                $nonce = $request->get_header("X-WP-Nonce");
                if ($nonce && wp_verify_nonce($nonce, "wp_rest")) { return true; }
                return current_user_can("manage_options");
            },
        ));
        register_rest_route("lotpl-customer-hub/v1", "/customers/(?P<id>\\d+)", array(
            "methods" => "PUT",
            "callback" => array($this, "update_customer"),
            "permission_callback" => function($request) {
                $nonce = $request->get_header("X-WP-Nonce");
                if ($nonce && wp_verify_nonce($nonce, "wp_rest")) { return true; }
                return current_user_can("manage_options");
            },
        ));
        register_rest_route("lotpl-customer-hub/v1", "/customers/(?P<id>\\d+)/staff-share", array("methods" => "POST", "callback" => array($this, "create_staff_share_link"), "permission_callback" => function($request) { $nonce = $request->get_header("X-WP-Nonce"); if ($nonce && wp_verify_nonce($nonce, "wp_rest")) { return true; } return current_user_can("manage_options"); }, ));

        register_rest_route("lotpl-customer-hub/v1", "/customers/(?P<id>\\d+)/status", array(
            "methods" => "PUT",
            "callback" => array($this, "update_customer_status"),
            "permission_callback" => function($request) {
                $nonce = $request->get_header("X-WP-Nonce");
                if ($nonce && wp_verify_nonce($nonce, "wp_rest")) { return true; }
                return current_user_can("manage_options");
            },
        ));
        register_rest_route("lotpl-customer-hub/v1", "/customers/(?P<id>\\d+)", array(
            "methods" => "DELETE",
            "callback" => array($this, "delete_customer"),
            "permission_callback" => function($request) {
                $nonce = $request->get_header("X-WP-Nonce");
                if ($nonce && wp_verify_nonce($nonce, "wp_rest")) { return true; }
                return current_user_can("manage_options");
            },
        ));



        register_rest_route("lotpl-customer-hub/v1", "/customers/(?P<id>\\d+)/events", array(
            "methods" => "POST",
            "callback" => array($this, "create_customer_event"),
            "permission_callback" => function($request) {
                $nonce = $request->get_header("X-WP-Nonce");
                if ($nonce && wp_verify_nonce($nonce, "wp_rest")) { return true; }
                return current_user_can("manage_options");
            },
        ));
        register_rest_route("lotpl-customer-hub/v1", "/customers/(?P<id>\\d+)/events", array(
            "methods" => "GET",
            "callback" => array($this, "get_customer_events"),
            "permission_callback" => function($request) {
                $nonce = $request->get_header("X-WP-Nonce");
                if ($nonce && wp_verify_nonce($nonce, "wp_rest")) { return true; }
                return current_user_can("manage_options");
            },
        ));
        register_rest_route("lotpl-customer-hub/v1", "/customers/(?P<id>\\d+)/events/(?P<event_id>\\d+)", array(
            "methods" => "PUT",
            "callback" => array($this, "update_customer_event"),
            "permission_callback" => function($request) {
                $nonce = $request->get_header("X-WP-Nonce");
                if ($nonce && wp_verify_nonce($nonce, "wp_rest")) { return true; }
                return current_user_can("manage_options");
            },
        ));
        register_rest_route("lotpl-customer-hub/v1", "/customers/(?P<id>\\d+)/events/(?P<event_id>\\d+)", array(
            "methods" => "DELETE",
            "callback" => array($this, "delete_customer_event"),
            "permission_callback" => function($request) {
                $nonce = $request->get_header("X-WP-Nonce");
                if ($nonce && wp_verify_nonce($nonce, "wp_rest")) { return true; }
                return current_user_can("manage_options");
            },
        ));
        register_rest_route("lotpl-customer-hub/v1", "/customers/(?P<id>\\d+)/reply-draft", array("methods" => "POST", "callback" => array($this, "create_reply_draft"), "permission_callback" => function($request) { $nonce = $request->get_header("X-WP-Nonce"); if ($nonce && wp_verify_nonce($nonce, "wp_rest")) { return true; } return current_user_can("manage_options"); }, ));
        register_rest_route("lotpl-customer-hub/v1", "/customers/(?P<id>\\d+)/deep-reply-draft", array("methods" => "POST", "callback" => array($this, "create_deep_reply_draft"), "permission_callback" => function($request) { $nonce = $request->get_header("X-WP-Nonce"); if ($nonce && wp_verify_nonce($nonce, "wp_rest")) { return true; } return current_user_can("manage_options"); }, ));
        register_rest_route("lotpl-customer-hub/v1", "/trip-intelligence/analyze", array("methods" => "POST", "callback" => array($this, "analyze_trip_intelligence"), "permission_callback" => function($request) { $nonce = $request->get_header("X-WP-Nonce"); if ($nonce && wp_verify_nonce($nonce, "wp_rest")) { return true; } return current_user_can("manage_options"); }, ));
        register_rest_route("lotpl-customer-hub/v1", "/trip-intelligence", array("methods" => "GET", "callback" => array($this, "list_trip_intelligence"), "permission_callback" => function($request) { $nonce = $request->get_header("X-WP-Nonce"); if ($nonce && wp_verify_nonce($nonce, "wp_rest")) { return true; } return current_user_can("manage_options"); }, ));
        register_rest_route("lotpl-customer-hub/v1", "/research-desk", array("methods" => "POST", "callback" => array($this, "research_desk"), "permission_callback" => function($request) { $nonce = $request->get_header("X-WP-Nonce"); if ($nonce && wp_verify_nonce($nonce, "wp_rest")) { return true; } return current_user_can("manage_options"); }, ));
        register_rest_route("lotpl-customer-hub/v1", "/customers/(?P<id>\\d+)/booking-sheet-draft", array("methods" => "POST", "callback" => array($this, "create_booking_sheet_draft"), "permission_callback" => function($request) { $nonce = $request->get_header("X-WP-Nonce"); if ($nonce && wp_verify_nonce($nonce, "wp_rest")) { return true; } return current_user_can("manage_options"); }, ));
        register_rest_route("lotpl-customer-hub/v1", "/customers/(?P<id>\\d+)/send-reply", array("methods" => "POST", "callback" => array($this, "save_manual_reply_sent"), "permission_callback" => function($request) { $nonce = $request->get_header("X-WP-Nonce"); if ($nonce && wp_verify_nonce($nonce, "wp_rest")) { return true; } return current_user_can("manage_options"); }, ));

    }

    public function research_desk(\WP_REST_Request $request): \WP_REST_Response { global $wpdb; $question=trim((string)$request->get_param("question")); if($question===""){ return new \WP_REST_Response(array("message"=>"Please enter a question."),400); } $like="%" . $wpdb->esc_like($question) . "%"; $api_key=defined("OPENAI_API_KEY")?OPENAI_API_KEY:get_option("lotpl_customer_hub_openai_api_key",""); $search_keyword=""; if($api_key!==""){ $kw_body=wp_json_encode(array("model"=>"gpt-4o-mini","response_format"=>array("type"=>"json_object"),"messages"=>array(array("role"=>"system","content"=>"Extract the most important search keyword from the question. Return JSON: {\"keyword\": \"one short word or name\"}. Examples: Rumtse to Tso Moriri Trek -> rumtse, Maria Aldera -> maria, August -> august, Markha Valley -> markha"),array("role"=>"user","content"=>$question))),JSON_UNESCAPED_UNICODE); $kw_resp=wp_remote_post("https://api.openai.com/v1/chat/completions",array("timeout"=>8,"headers"=>array("Authorization"=>"Bearer ".$api_key,"Content-Type"=>"application/json"),"body"=>$kw_body)); if(is_wp_error($kw_resp)===false){ $kw_decoded=json_decode((string)wp_remote_retrieve_body($kw_resp),true); $kw_text=isset($kw_decoded["choices"][0]["message"]["content"])?(string)$kw_decoded["choices"][0]["message"]["content"]:"{}"; $kw_result=json_decode(trim($kw_text),true); if(isset($kw_result["keyword"])&&trim($kw_result["keyword"])!==""){ $search_keyword=trim($kw_result["keyword"]); } } } if($search_keyword!==""){ $like="%".$wpdb->esc_like($search_keyword)."%"; } $ct=$wpdb->prefix . "lotpl_customers"; $et=$wpdb->prefix . "lotpl_customer_events"; $tt=$wpdb->prefix . "lotpl_trip_intelligence"; $$customers=$wpdb->get_results($wpdb->prepare("SELECT id,name,email,status FROM ".$ct." WHERE name LIKE %s OR email LIKE %s ORDER BY id DESC LIMIT 30",$like,$like),ARRAY_A); $trips=$wpdb->get_results($wpdb->prepare("SELECT id,customer_id,customer_name,customer_email,tour_name,travel_date_text,travel_month,party_size,trip_length,cost_text,quoted_cost,currency FROM ".$tt." WHERE normalized_tour_key NOT IN ('no_tour_found','') AND (tour_name LIKE %s OR customer_name LIKE %s OR travel_month LIKE %s) ORDER BY CASE WHEN quoted_cost IS NULL OR quoted_cost=0 THEN 1 ELSE 0 END,id DESC LIMIT 30",$like,$like,$like),ARRAY_A); $events=$wpdb->get_results($wpdb->prepare("SELECT customer_id,subject,body,created_at FROM ".$et." WHERE (subject LIKE %s OR body LIKE %s) AND event_type NOT IN ('staff_share_link','auto_reply_scheduled') ORDER BY created_at DESC LIMIT 30",$like,$like),ARRAY_A); $answer="Research Desk result for: " . $question . "\n\nCUSTOMER LIST\n"; if(is_array($customers) && count($customers)>0){ foreach($customers as $c){ $answer.="- #" . $c["id"] . " | " . $c["name"] . " | " . $c["email"] . " | " . $c["status"] . "\n"; } } else { $answer.="- No direct customer matches.\n"; } $answer.="\nTRIP INTELLIGENCE\n"; if(is_array($trips) && count($trips)>0){ foreach($trips as $t){ $cost=trim((string)($t["cost_text"]!==""?$t["cost_text"]:((string)$t["quoted_cost"] . " " . (string)$t["currency"]))); $answer.="- " . $t["tour_name"] . " | " . $t["customer_name"] . " <" . $t["customer_email"] . "> | Date: " . $t["travel_date_text"] . " | Month: " . $t["travel_month"] . " | Party: " . $t["party_size"] . " | Length: " . $t["trip_length"] . " | Cost: " . $cost . "\n"; } } else { $answer.="- No Trip Intelligence matches.\n"; } $answer.="\nCUSTOMER EVENTS\n"; if(is_array($events) && count($events)>0){ foreach($events as $e){ $body=trim(preg_replace("/\\s+/"," ",(string)$e["body"])); $answer.="- Customer #" . $e["customer_id"] . " | " . $e["created_at"] . " | " . $e["subject"] . " | " . substr($body,0,180) . "\n"; } } else { $answer.="- No event matches.\n"; } require_once __DIR__ . "/class-ai-inquiry-analyzer.php"; $analyzer = new LOTPL_Customer_Hub_AI_Inquiry_Analyzer(); $prompt = "You are an internal research assistant for LIFE on the PLANET LADAKH. Answer Honjo only. Use only the search results below from Customer List, Trip Intelligence, and Customer Events. Do not invent facts. If the data is missing, say it is not recorded. Return a concise, readable Japanese answer with clear sections and bullet points.\n\nQuestion:\n" . $question . "\n\nSearch results:\n" . $answer; $ai=$analyzer->analyze(array("name"=>"Honjo","message"=>$prompt,"item_name"=>"Research Desk","classification"=>array(),"analysis"=>array())); $final=isset($ai["reply_draft"]) ? trim((string)$ai["reply_draft"]) : ""; if($final===""){ $final=$answer; } return new \WP_REST_Response(array("answer"=>$final),200); }
    public function list_trip_intelligence(\WP_REST_Request $request): \WP_REST_Response { global $wpdb; $t=$wpdb->prefix . "lotpl_trip_intelligence"; $items=$wpdb->get_results("SELECT id,customer_id,event_id,customer_name,customer_email,tour_name,normalized_tour_key,itinerary_variant_key,travel_date_text,travel_month,party_size,trip_length,quoted_cost,currency,cost_text,cost_basis,itinerary_text,confidence FROM " . $t . " ORDER BY CASE WHEN quoted_cost IS NULL OR quoted_cost = '' THEN 1 ELSE 0 END, id DESC LIMIT 200", ARRAY_A); return new \WP_REST_Response(array("items" => $items), 200); }

    public function analyze_trip_intelligence(\WP_REST_Request $request): \WP_REST_Response { require_once __DIR__ . "/class-trip-intelligence-analyzer.php"; $analyzer = new LOTPL_Customer_Hub_Trip_Intelligence_Analyzer(); $result = $analyzer->analyze_unprocessed_events(20); return new \WP_REST_Response($result, 200); }

    public function create_reply_draft(\WP_REST_Request $request): \WP_REST_Response { global $wpdb; $customer_id = (int) $request->get_param("id"); $staff_note_id = (int) $request->get_param("staff_note_id"); $ai_instruction = trim((string) $request->get_param("ai_instruction")); if ($customer_id < 1) { return new \WP_REST_Response(array("message" => "Invalid request"), 400); } $ct=$wpdb->prefix . "lotpl_customers"; $et=$wpdb->prefix . "lotpl_customer_events"; $customer=$wpdb->get_row($wpdb->prepare("SELECT name,email FROM " . $ct . " WHERE id=%d", $customer_id), ARRAY_A); $note=$wpdb->get_row($wpdb->prepare("SELECT body FROM " . $et . " WHERE id=%d AND customer_id=%d", $staff_note_id, $customer_id), ARRAY_A); $name=is_array($customer) ? trim((string)$customer["name"]) : ""; $staff_note=is_array($note) ? trim((string)$note["body"]) : ""; if ($staff_note_id < 1) { $rows=$wpdb->get_results($wpdb->prepare("SELECT subject,body,created_at FROM " . $et . " WHERE customer_id=%d AND event_type<>%s ORDER BY created_at ASC,id ASC LIMIT 50", $customer_id, "staff_share_link"), ARRAY_A); $parts=array(); if (is_array($rows)) { foreach($rows as $row){ $parts[]="Date: ".(string)$row["created_at"]."\nSubject: ".(string)$row["subject"]."\nBody:\n".(string)$row["body"]; } } $staff_note=implode("\n\n---\n\n", $parts); } require_once __DIR__ . "/class-ai-inquiry-analyzer.php"; $analyzer = new LOTPL_Customer_Hub_AI_Inquiry_Analyzer(); $message = "Customer name: " . $name . "\n\nSYSTEM: You are a travel advisor for LIFE on the PLANET LADAKH. Write a customer email reply based only on the conversation history below.\n\nSTEP 1 - READ BEFORE WRITING\nIdentify: (a) every explicit question the customer asked, (b) their party size and dates, (c) the single best option from the conversation.\nIf a human instruction exists, treat it as highest priority.\n\nSTEP 2 - ANSWER FIRST\nEvery explicit customer question must be answered directly. Do not skip any question. Do not answer vaguely.\n\nSTEP 3 - MERGE DATA\nSame tour + same party size + same price = one record. Write it once. Never write the same price twice.\n\nSTEP 4 - WRITE THE REPLY\n- Warm greeting (one sentence)\n- Answer each customer question directly\n- State one clear recommendation using the words I recommend\n- Only mention shared departure if the customer explicitly asked about it\n- One clear next step. Prefer WhatsApp +91 9622867197.\n\nLanguage rules:\n- Never use: recorded, noted, planned, interest, database, system, package record, previously quoted, one version, another version, currently, groups, group dimensions\n- Make decisions. Do not write: If you prefer or If you would like.\n- Never use any phone number from the customer data. Only use WhatsApp +91 9622867197.\n\nSTEP 5 - REVIEW BEFORE RETURNING\n- Did I answer every customer question? If not, rewrite.\n- Is any price written more than once? If yes, remove the duplicate.\n- Is any topic mentioned more than once? If yes, merge into one sentence.\n- Does the reply contain I recommend? If not, add it.\n- Are there any banned internal words? If yes, replace them.\nOnly return the final email, no notes.\n\nStaff note and conversation history:\n\n\nStaff note:\n" . $staff_note . ($ai_instruction !== "" ? "\n\nHuman instruction for this reply:\n" . $ai_instruction : ""); $payload = array("name" => $name, "message" => $message, "item_name" => "", "classification" => array(), "analysis" => array()); $ai = $analyzer->analyze($payload); $draft = isset($ai["reply_draft"]) ? (string)$ai["reply_draft"] : ""; $review_message = "Review and rewrite this customer email if needed. Ensure all explicit customer questions are clearly answered in the final email. Do not remove questions about group size, age, or similar details without answering them. Never remove or weaken the main recommendation sentence. It must remain the core of the email. Check for duplicate prices, duplicate options, irrelevant group details, internal wording, mismatch with customer party size or dates, and weak recommendation. Return only the final customer email, no notes.\n\nCustomer context:\n" . $message . "\n\nDraft email:\n" . $draft; $review = $analyzer->analyze(array("name"=>$name,"message"=>$review_message,"item_name"=>"Reply Quality Review","classification"=>array(),"analysis"=>array())); $review_draft = isset($review["reply_draft"]) ? trim((string)$review["reply_draft"]) : ""; if(strlen($review_draft)>0){ $draft=$review_draft; } if ($name !== "") { $draft = preg_replace("/^Dear Guest,/", "Dear " . $name . ",", $draft, 1); } $draft = str_replace("Warm regards,\nTashi", "Warm regards,\n\nTashi", $draft); $draft = str_replace("Tashi Anchok, Mohd Amin, Junichiro Honjo, LIFE on the PLANET LADAKH,", "Tashi Anchok\nMohd Amin\nJunichiro Honjo\nLIFE on the PLANET LADAKH\n", $draft); $draft = str_replace("LIFE on the PLANET LADAKH, Kukarchey", "LIFE on the PLANET LADAKH,\nKukarchey", $draft); $draft = str_replace(", +91 9622867197", "\n+91 9622867197", $draft); $draft = str_replace(", info@lifeontheplanetladakh.com", "\ninfo@lifeontheplanetladakh.com", $draft); $draft = str_replace(", https://lifeontheplanetladakh.com", "\nhttps://lifeontheplanetladakh.com", $draft); $draft = str_replace("\n Kukarchey", "\nKukarchey", $draft); $draft = preg_replace("/\n\n?Warm regards,[\\s\\S]*$/", "", $draft); $draft = trim((string)$draft) . "\n\nWarm regards,\n\nTashi Anchok\nMohd Amin\nJunichiro Honjo\nLIFE on the PLANET LADAKH\nKukarchey, Chiktan, Ladakh, India\n+91 9622867197\ninfo@lifeontheplanetladakh.com\nhttps://lifeontheplanetladakh.com"; return new \WP_REST_Response(array("subject" => "Reply from LIFE on the PLANET LADAKH", "draft" => $draft), 200); }

    public function create_deep_reply_draft(\WP_REST_Request $request): \WP_REST_Response { global $wpdb; $customer_id=(int)$request->get_param("id"); $ai_instruction=trim((string)$request->get_param("ai_instruction")); $ct=$wpdb->prefix . "lotpl_customers"; $tt=$wpdb->prefix . "lotpl_trip_intelligence"; $c=$wpdb->get_row($wpdb->prepare("SELECT id,name,email FROM " . $ct . " WHERE id=%d",$customer_id),ARRAY_A); $name=is_array($c)?trim((string)$c["name"]):""; $email=is_array($c)?trim((string)$c["email"]):""; $own=$wpdb->get_row($wpdb->prepare("SELECT tour_name,normalized_tour_key,travel_month,travel_date_text FROM " . $tt . " WHERE customer_id=%d ORDER BY id DESC LIMIT 1",$customer_id),ARRAY_A); $tour=is_array($own)?trim((string)$own["tour_name"]):""; $key=is_array($own)?trim((string)$own["normalized_tour_key"]):""; $term=$tour!==""?$tour:$key; $rows=array(); if($term!=="" || $key!==""){ $like="%" . $wpdb->esc_like($term) . "%"; $rows=$wpdb->get_results($wpdb->prepare("SELECT ti.customer_id,COALESCE(c.name,ti.customer_name) AS customer_name,COALESCE(c.email,ti.customer_email) AS customer_email,ti.tour_name,ti.travel_date_text,ti.travel_month,ti.party_size,ti.trip_length,ti.cost_text,ti.quoted_cost,ti.currency,ti.itinerary_text,ti.confidence FROM " . $tt . " ti LEFT JOIN " . $ct . " c ON c.id=ti.customer_id WHERE ti.customer_id<>%d AND (ti.normalized_tour_key=%s OR ti.tour_name LIKE %s OR ti.itinerary_text LIKE %s) ORDER BY CASE WHEN ti.quoted_cost IS NULL OR ti.quoted_cost = 0 THEN 1 ELSE 0 END, ti.id DESC LIMIT 20",$customer_id,$key,$like,$like),ARRAY_A); } $hist_rows=$wpdb->get_results($wpdb->prepare("SELECT event_type,subject,body,created_at FROM " . $wpdb->prefix . "lotpl_customer_events" . " WHERE customer_id=%d AND event_type<>%s ORDER BY created_at ASC,id ASC LIMIT 20",$customer_id,"staff_share_link"),ARRAY_A); $target_history="Target customer conversation history:\n"; if(is_array($hist_rows)){ foreach($hist_rows as $hr){ $target_history.="- " . (string)$hr["created_at"] . " | " . (string)$hr["event_type"] . " | " . (string)$hr["subject"] . " | " . substr(trim(preg_replace("/\\s+/"," ",(string)$hr["body"])),0,500) . "\n"; } } $context="Deep research context from Customer List and Trip Intelligence.\nTarget customer: " . $name . " <" . $email . ">\nTarget tour: " . $tour . "\nTarget normalized key: " . $key . "\n\n" . $target_history . "\nOther real matching customers and trip data:\n"; if(is_array($rows) && count($rows)>0){ foreach($rows as $r){ if((isset($r["party_size"])==false) || ((int)$r["party_size"]!=2)) continue; $cost=trim((string)($r["cost_text"]!==""?$r["cost_text"]:((string)$r["quoted_cost"] . " " . (string)$r["currency"]))); $seen=isset($seen)?$seen:array(); $dedupe_key=strtolower(trim((string)$r["tour_name"])) . "|" . (string)$r["party_size"] . "|" . strtolower($cost); if(isset($seen[$dedupe_key])) continue; $seen[$dedupe_key]=true; $context.="- Name: " . (string)$r["customer_name"] . " | Email: " . (string)$r["customer_email"] . " | Tour: " . (string)$r["tour_name"] . " | Date: " . (string)$r["travel_date_text"] . " | Month: " . (string)$r["travel_month"] . " | Party size: " . (string)$r["party_size"] . " | Trip length: " . (string)$r["trip_length"] . " | Cost: " . $cost . " | Cost basis: " . (string)$r["cost_basis"] . " | Itinerary: " . substr(trim((string)$r["itinerary_text"]),0,600) . "\n"; } } else { $context.="No matching participant data found in Trip Intelligence. Do not invent participant details.\n"; } $rules="
SYSTEM: You are a travel advisor for LIFE on the PLANET LADAKH. Write a customer email reply.

STEP 1 - READ BEFORE WRITING
Identify: (a) what the customer asked, (b) their party size and dates, (c) the single best option from the data.
If staff_note exists, treat it as highest priority over all other data.

STEP 2 - MERGE DATA
Same tour + same party size + same price = one record. Write it once.
Two records describing the same offer = merge into one sentence before writing.
Never write the same price twice under any circumstance.
If two records have the same tour + same party size + same price: they are ONE package. It does not matter if the inclusions are described differently. Treat them as one. Pick the longer inclusion list and use it. Never present them as two separate options. Never repeat the same price twice. Never use Another option, Another private option, One version, Another version, or any similar phrasing.

STEP 3 - DELETE BEFORE WRITING
Remove: wrong party size, group price tables, unrelated tours, duplicate descriptions.
Delete even if the data exists in the source. No exceptions.
Never mention: number of groups, group compositions, how many people are in other groups, or any internal participant breakdown. If the customer is joining a shared departure, describe it only as: a shared departure with other travelers.

STEP 4 - WRITE THE REPLY
Structure:
- Warm greeting (one sentence)
- Answer the customer question directly in paragraph one
- State one clear recommendation using the words I recommend
- If cost_basis is private: present it as the private option
- If cost_basis is shared: present it as a shared departure with other travelers
- If both exist: private first, shared as alternative with its price clearly stated
- Add shared departure as one alternative only if it saves meaningful cost
- One clear next step in the final sentence. Prefer WhatsApp.
Language rules:
- Never use: recorded, noted, planned, interest, database, system, package record, previously quoted, one version, another version, currently, groups, group dimensions, group composition, departure you match, may be possible, planned, once the departure is confirmed, once confirmed
- When cost_basis is shared: write naturally as a positive fact, e.g. This departure runs with other travelers, which keeps the cost lower than a private trip
- Make decisions. Do not write If you prefer or If you would like.
- Write as a guide, not a data report.

STEP 5 - REVIEW BEFORE RETURNING
Check these five points before returning:
- Did I answer what the customer asked? If not, rewrite.
- Is any price written more than once? If yes, remove the duplicate.
- Is there any option that does not match the customer party size? If yes, delete it.
- Does the reply contain I recommend? If not, add it.
- Are there any banned internal words? If yes, replace them.
- Is any topic, requirement, or piece of information mentioned more than once? If yes, merge into one sentence.
Only return the reply after passing all five checks.
Additional output rules:
- Never say: age details are not available, information I have here, not available in our records, or any similar phrase. Simply omit information you do not have.
- Never repeat the same sentence or idea twice in the email.
- Never use any phone number from the customer data. The only contact number to use is WhatsApp +91 9622867197.
- Never say price varies by group size. If shared price is unknown, simply say the shared option is available at a lower cost and invite them to ask.
- Only mention a shared departure if the customer has explicitly asked about joining a group or sharing costs in their message. If the customer has not mentioned shared departure, do not include it. End the email with WhatsApp +91 9622867197 as the contact method."; $request->set_param("ai_instruction", trim($ai_instruction . "\n\n" . $context . $rules)); return $this->create_reply_draft($request); }

    public function save_manual_reply_sent(\WP_REST_Request $request): \WP_REST_Response { global $wpdb; $customer_id=(int)$request->get_param("id"); $subject=trim((string)$request->get_param("subject")); $body=trim((string)$request->get_param("body")); $cc=trim((string)$request->get_param("cc")); if($customer_id<1 || $body===""){ return new \WP_REST_Response(array("message"=>"Invalid request"),400); } $ct=$wpdb->prefix . "lotpl_customers"; $to=(string)$wpdb->get_var($wpdb->prepare("SELECT email FROM " . $ct . " WHERE id=%d", $customer_id)); if($to==="" || strtolower($to)==="test@test.com"){ return new \WP_REST_Response(array("message"=>"Real email sending is blocked for test or empty recipient."),400); } $cc_parts=preg_split("/[\\s,;]+/",$cc); $cc_clean=array(); foreach($cc_parts as $addr){ $addr=sanitize_email(trim((string)$addr)); if($addr===""){ continue; } if(strtolower($addr)===strtolower($to)){ continue; } if(in_array(strtolower($addr),array_map("strtolower",$cc_clean),true)){ continue; } $cc_clean[]=$addr; } $cc=implode(", ",$cc_clean); $t=$wpdb->prefix . "lotpl_customer_events"; $ok=$wpdb->insert($t,array("customer_id"=>$customer_id,"event_type"=>"manual_reply_sent","event_source"=>"customer_hub","subject"=>$subject,"body"=>$body,"meta_json"=>wp_json_encode(array("direction"=>"sent","sent_by"=>"honjo","email_sent"=>false,"to_email"=>$to,"cc_emails"=>$cc)),"status"=>"saved","created_at"=>current_time("mysql")),array("%d","%s","%s","%s","%s","%s","%s","%s")); if($ok===false){ return new \WP_REST_Response(array("message"=>"Failed to save reply"),500); } $event_id=(int)$wpdb->insert_id; $headers=array("From: LIFE on the PLANET LADAKH <info@lifeontheplanetladakh.com>","Content-Type: text/plain; charset=UTF-8"); if($cc!==""){ $headers[]="Cc: ".$cc; } $mail_ok=wp_mail($to,$subject,$body,$headers); if($mail_ok){ $meta=wp_json_encode(array("direction"=>"sent","sent_by"=>"honjo","email_sent"=>true,"to_email"=>$to,"cc_emails"=>$cc)); $wpdb->update($t,array("meta_json"=>$meta,"status"=>"sent","sent_at"=>current_time("mysql")),array("id"=>$event_id),array("%s","%s","%s"),array("%d")); return new \WP_REST_Response(array("saved"=>true,"email_sent"=>true,"event_id"=>$event_id,"status"=>"Sent"),200); } return new \WP_REST_Response(array("saved"=>true,"email_sent"=>false,"event_id"=>$event_id,"message"=>"Saved, but email sending failed."),500); }

    public function search_customers(\WP_REST_Request $request): \WP_REST_Response {
        global $wpdb;
        $q = trim((string) $request->get_param("q"));
        if ($q === "") { return new \WP_REST_Response(array("results" => array()), 200); }
        $like = "%" . $wpdb->esc_like($q) . "%";
        $ct = $wpdb->prefix . "lotpl_customers";
        $et = $wpdb->prefix . "lotpl_customer_events";
        $sql = $wpdb->prepare("SELECT DISTINCT c.id, c.name, c.email, c.status, c.created_at, c.updated_at FROM {$ct} c LEFT JOIN {$et} e ON e.customer_id = c.id WHERE c.name LIKE %s OR c.email LIKE %s OR c.status LIKE %s OR e.subject LIKE %s OR e.body LIKE %s ORDER BY CASE WHEN c.name LIKE %s THEN 0 WHEN c.email LIKE %s THEN 1 WHEN c.status LIKE %s THEN 2 ELSE 3 END, c.updated_at DESC, c.id DESC LIMIT 50", $like, $like, $like, $like, $like, $like, $like, $like);
        $rows = $wpdb->get_results($sql, ARRAY_A);
        if (is_array($rows)) { foreach ($rows as &$row) { $row["match_type"] = "Message match"; if (stripos((string) $row["name"], $q) !== false) { $row["match_type"] = "Name match"; } elseif (stripos((string) $row["email"], $q) !== false) { $row["match_type"] = "Email match"; } elseif (stripos((string) $row["status"], $q) !== false) { $row["match_type"] = "Status match"; } } unset($row); }
        return new \WP_REST_Response(array("results" => is_array($rows) ? $rows : array()), 200);
    }

    public function create_staff_share_link(\WP_REST_Request $request): \WP_REST_Response {
        $repo = new LOTPL_Customer_Hub_Events_Repository();
        $customer_id = (int) $request->get_param("id");
        if ($customer_id < 1) { return new \WP_REST_Response(array("message" => "Invalid customer"), 400); }
        $token = wp_generate_password(32, false, false);
        $event_id = $repo->add_event($customer_id, "staff_share_link", "system", "Staff share link", "Read-only staff share link", array("token" => $token, "expires_at" => gmdate("Y-m-d H:i:s", time() + 7 * DAY_IN_SECONDS), "customer_id" => $customer_id));
        if ($event_id < 1) { return new \WP_REST_Response(array("message" => "Failed to create share link"), 500); }
        return new \WP_REST_Response(array("token" => $token, "url" => home_url("/customer-share/?token=" . $token), "event_id" => $event_id), 201);
    }

    public function get_customers(\WP_REST_Request $request): \WP_REST_Response {
        $repo = new LOTPL_Customer_Hub_Customers_Repository();
        return new \WP_REST_Response(array("customers" => $repo->get_all()), 200);
    }

    public function create_customer(\WP_REST_Request $request): \WP_REST_Response {
        $repo = new LOTPL_Customer_Hub_Customers_Repository();
        $name = trim((string) $request->get_param("name"));
        $email = trim((string) $request->get_param("email"));
        $status = trim((string) $request->get_param("status"));

        if ($name === "") {
            return new \WP_REST_Response(array("message" => "Invalid request"), 400);
        }

        $customer_id = $repo->create_customer($name, $email, $status === "" ? "new" : $status);

        if ($customer_id < 1) {
            return new \WP_REST_Response(array("message" => "Failed to create customer"), 500);
        }

        return new \WP_REST_Response(array("customer_id" => $customer_id), 201);
    }

    public function get_customer(\WP_REST_Request $request): \WP_REST_Response {
        $repo = new LOTPL_Customer_Hub_Customers_Repository();
        $customer_id = (int) $request->get_param("id");
        $customer = $repo->get_by_id($customer_id);

        if ($customer === null) {
            return new \WP_REST_Response(array("message" => "Customer not found"), 404);
        }

        return new \WP_REST_Response(array("customer" => $customer), 200);
    }


    public function update_customer(\WP_REST_Request $request): \WP_REST_Response {
        $repo = new LOTPL_Customer_Hub_Customers_Repository();
        $customer_id = (int) $request->get_param("id");
        $name = trim((string) $request->get_param("name"));
        $email = trim((string) $request->get_param("email"));

        if ($customer_id < 1 || $name === "") {
            return new \WP_REST_Response(array("message" => "Invalid request"), 400);
        }

        if ($repo->update_customer($customer_id, $name, $email) === false) {
            return new \WP_REST_Response(array("message" => "Customer not found"), 404);
        }

        return new \WP_REST_Response(array("updated" => true, "customer_id" => $customer_id), 200);
    }

    public function update_customer_status(\WP_REST_Request $request): \WP_REST_Response {
        $repo = new LOTPL_Customer_Hub_Customers_Repository();
        $customer_id = (int) $request->get_param("id");
        $status = trim((string) $request->get_param("status"));

        if ($customer_id < 1 || $status === "") {
            return new \WP_REST_Response(array("message" => "Invalid request"), 400);
        }

        if ($repo->update_customer_status($customer_id, $status) === false) {
            return new \WP_REST_Response(array("message" => "Customer not found"), 404);
        }

        return new \WP_REST_Response(array("updated" => true, "customer_id" => $customer_id, "status" => $status), 200);
    }

    public function create_customer_event(\WP_REST_Request $request): \WP_REST_Response {
        $repo = new LOTPL_Customer_Hub_Events_Repository();
        $customer_id = (int) $request->get_param("id");
        $direction = trim((string) $request->get_param("direction"));
        $subject = trim((string) $request->get_param("subject"));
        $body = trim((string) $request->get_param("body"));
        $date = trim((string) $request->get_param("date"));
        $attachments = $request->get_param("attachments");
        if (is_array($attachments) === false) { $attachments = array(); }
 $event_source = sanitize_key((string) $request->get_param("event_source")); if ($event_source === "") { $event_source = "manual_entry"; }
        if ($customer_id < 1 || $body === "") {
            return new \WP_REST_Response(array("message" => "Invalid request"), 400);
        }

        global $wpdb;
        $et = $wpdb->prefix . "lotpl_customer_events";
        $dupe = (int) $wpdb->get_var($wpdb->prepare("SELECT id FROM " . $et . " WHERE customer_id=%d AND body=%s AND meta_json LIKE %s ORDER BY id DESC LIMIT 1", $customer_id, $body, "%\"direction\":\"" . $direction . "\"%"));
        if ($dupe > 0) { return new \WP_REST_Response(array("event_id" => $dupe, "duplicate" => true), 200); }

        $event_type = sanitize_key((string) $request->get_param("event_type")); if ($event_type !== "booking_sheet") { $event_type = "email"; }
        $event_id = $repo->add_event($customer_id, $event_type, $event_source, $subject, $body, array(
            "direction" => $direction,
            "date" => $date,
            "created_at" => current_time("mysql"),
        ));

        if ($event_id < 1) {
            return new \WP_REST_Response(array("message" => "Failed to create event"), 500);
        }

        return new \WP_REST_Response(array("event_id" => $event_id), 201);
    }
    public function get_customer_events(\WP_REST_Request $request): \WP_REST_Response {
        $repo = new LOTPL_Customer_Hub_Events_Repository();
        $customer_id = (int) $request->get_param("id");
        $limit = (int) $request->get_param("limit");
        if ($limit < 1) {
            $limit = 100;
        }
        return new \WP_REST_Response(array("events" => $repo->get_latest_by_customer_id($customer_id, $limit)), 200);
    }

    public function update_customer_event(\WP_REST_Request $request): \WP_REST_Response {
        $repo = new LOTPL_Customer_Hub_Events_Repository();
        $customer_id = (int) $request->get_param("id");
        $event_id = (int) $request->get_param("event_id");
        $direction = trim((string) $request->get_param("direction"));
        $subject = trim((string) $request->get_param("subject"));
        $body = trim((string) $request->get_param("body"));
        $date = trim((string) $request->get_param("date"));
        $attachments = $request->get_param("attachments");
        if (is_array($attachments) === false) { $attachments = array(); }

        if ($customer_id < 1 || $event_id < 1 || $body === "") {
            return new \WP_REST_Response(array("message" => "Invalid request"), 400);
        }

        if ($repo->update_event($customer_id, $event_id, $subject, $body, array("direction" => $direction, "date" => $date, "attachments" => $attachments)) === false) {
            return new \WP_REST_Response(array("message" => "Event not found"), 404);
        }

        return new \WP_REST_Response(array("updated" => true, "event_id" => $event_id), 200);
    }

    public function delete_customer_event(\WP_REST_Request $request): \WP_REST_Response {
        $repo = new LOTPL_Customer_Hub_Events_Repository();
        $customer_id = (int) $request->get_param("id");
        $event_id = (int) $request->get_param("event_id");

        if ($repo->delete_event($customer_id, $event_id) === false) {
            return new \WP_REST_Response(array("message" => "Event not found"), 404);
        }

        return new \WP_REST_Response(array("deleted" => true, "event_id" => $event_id), 200);
    }

    public function generate_ladakh_plan(\WP_REST_Request $request): \WP_REST_Response {
        $days = sanitize_text_field((string) $request->get_param("days"));
        $pace = sanitize_text_field((string) $request->get_param("pace"));
        $altitude = sanitize_text_field((string) $request->get_param("altitude"));
        $interest = $request->get_param("interest");
        if (is_array($interest) === false) { $interest = array(); }
        $interest = array_values(array_filter(array_map("sanitize_text_field", $interest)));
        if ($days === "" || $pace === "" || $altitude === "" || count($interest) < 1) {
            return new \WP_REST_Response(array("ok" => false, "error" => "Missing required planner fields."), 400);
        }
        $api_key=defined("OPENAI_API_KEY")?OPENAI_API_KEY:get_option("lotpl_customer_hub_openai_api_key",""); if($api_key===""){return new \WP_REST_Response(array("ok"=>false,"error"=>"No API key"),500);} $interest_str=implode(", ",$interest); $system="You are a Ladakh travel expert for LIFE on the PLANET LADAKH. Create detailed day-by-day itineraries based on traveler preferences."; $user="Create a {$days}-day Ladakh itinerary. Pace: {$pace}. Altitude comfort: {$altitude}. Interests: {$interest_str}. Return ONLY a JSON object with these exact keys: title (string), tagline (string), why (string), altitude_note (string), best_season (string), stay_suggestions (array of strings), days (array of objects with keys: day (int), title (string), summary (string))."; $body=wp_json_encode(array("model"=>"gpt-5.5","response_format"=>array("type"=>"json_object"),"messages"=>array(array("role"=>"system","content"=>$system),array("role"=>"user","content"=>$user))),JSON_UNESCAPED_UNICODE); $resp=wp_remote_post("https://api.openai.com/v1/chat/completions",array("timeout"=>30,"headers"=>array("Authorization"=>"Bearer ".$api_key,"Content-Type"=>"application/json"),"body"=>$body)); if(is_wp_error($resp)){return new \WP_REST_Response(array("ok"=>false,"error"=>$resp->get_error_message()),500);} $decoded=json_decode((string)wp_remote_retrieve_body($resp),true); $text=isset($decoded["choices"][0]["message"]["content"])?(string)$decoded["choices"][0]["message"]["content"]:""; $plan=json_decode(trim($text),true); if(is_array($plan)===false){return new \WP_REST_Response(array("ok"=>false,"error"=>"AI error"),500);} update_option("lotpl_trip_planner_generate_count", ((int)get_option("lotpl_trip_planner_generate_count",0))+1, false); update_option("lotpl_trip_planner_last_used", current_time("mysql"), false); return new \WP_REST_Response(array("ok"=>true,"plan"=>$plan),200);
    }

    public function delete_customer(\WP_REST_Request $request): \WP_REST_Response {
        $repo = new LOTPL_Customer_Hub_Customers_Repository();
        $customer_id = (int) $request->get_param("id");

        if ($repo->delete_customer($customer_id) === false) {
            return new \WP_REST_Response(array("message" => "Customer not found"), 404);
        }

        return new \WP_REST_Response(array("deleted" => true, "customer_id" => $customer_id), 200);
    }


    private function get_model(): string {
        if (defined("OPENAI_MODEL") && trim((string) OPENAI_MODEL) !== "") { return trim((string) OPENAI_MODEL); }
        $model = get_option("lotpl_customer_hub_openai_model", "");
        if (trim((string) $model) !== "") { return trim((string) $model); }
        $env_model = getenv("OPENAI_MODEL");
        if ($env_model !== false && trim((string) $env_model) !== "") { return trim((string) $env_model); }
        return "gpt-5.5";
    }

    private function get_api_key(): string {
        if (defined("OPENAI_API_KEY") && trim((string) OPENAI_API_KEY) !== "") { return trim((string) OPENAI_API_KEY); }
        $key = get_option("lotpl_customer_hub_openai_api_key", "");
        if (trim((string) $key) !== "") { return trim((string) $key); }
        $env_key = getenv("OPENAI_API_KEY");
        if ($env_key !== false && trim((string) $env_key) !== "") { return trim((string) $env_key); }
        return "";
    }
    public function create_booking_sheet_draft(\WP_REST_Request $request): \WP_REST_Response {
        global $wpdb;
        $cid=(int)$request->get_param("id");
        $ct=$wpdb->prefix."lotpl_customers";
        $et=$wpdb->prefix."lotpl_customer_events";
        $tt=$wpdb->prefix."lotpl_trip_intelligence";
        $c=$wpdb->get_row($wpdb->prepare("SELECT id,name,email FROM ".$ct." WHERE id=%d",$cid),ARRAY_A);
        if(is_array($c)===false){ return new \WP_REST_Response(array("message"=>"Customer not found"),404); }
        $hist=$wpdb->get_results($wpdb->prepare("SELECT event_type,subject,body,created_at FROM ".$et." WHERE customer_id=%d AND event_type NOT IN ('staff_share_link','auto_reply_scheduled','booking_sheet') ORDER BY created_at ASC LIMIT 30",$cid),ARRAY_A);
        $trips=$wpdb->get_results($wpdb->prepare("SELECT tour_name,travel_date_text,travel_month,party_size,trip_length,quoted_cost,currency,cost_basis FROM ".$tt." WHERE customer_id=%d AND normalized_tour_key NOT IN ('no_tour_found','') AND quoted_cost>0 ORDER BY id DESC LIMIT 5",$cid),ARRAY_A);
        $h2=""; if(is_array($hist)){ foreach($hist as $h){ $h2.=(string)$h["created_at"]." | ".(string)$h["event_type"]."\n".substr(trim((string)$h["body"]),0,400)."\n\n"; } }
        $ti=""; if(is_array($trips)){ foreach($trips as $t){ $ti.=(string)$t["tour_name"]." | Date: ".(string)$t["travel_date_text"]." | Party: ".(string)$t["party_size"]." | Cost: ".(string)$t["quoted_cost"]." ".(string)$t["currency"]."\n"; } }
        $ak=defined("OPENAI_API_KEY")?OPENAI_API_KEY:get_option("lotpl_customer_hub_openai_api_key","");
        if($ak===""){ return new \WP_REST_Response(array("message"=>"No API key"),500); }
        $pr="Extract booking info from this conversation. Return JSON with keys: tour, dates, people, price, currency, inclusions, itinerary, notes. Use only clearly stated info. Empty string if not found.\n\nCustomer: ".(string)$c["name"]."\nEmail: ".(string)$c["email"]."\n\nConversation:\n".$h2."\nTrip Intelligence:\n".$ti;
        $bd=wp_json_encode(array("model"=>"gpt-4o-mini","response_format"=>array("type"=>"json_object"),"messages"=>array(array("role"=>"system","content"=>"You are a booking assistant. Extract travel booking details. Return only valid JSON."),array("role"=>"user","content"=>$pr))),JSON_UNESCAPED_UNICODE);
        $rsp=wp_remote_post("https://api.openai.com/v1/chat/completions",array("timeout"=>20,"headers"=>array("Authorization"=>"Bearer ".$ak,"Content-Type"=>"application/json"),"body"=>$bd));
        if(is_wp_error($rsp)){ return new \WP_REST_Response(array("message"=>"AI error"),500); }
        $dc=json_decode((string)wp_remote_retrieve_body($rsp),true);
        $tx=isset($dc["choices"][0]["message"]["content"])?(string)$dc["choices"][0]["message"]["content"]:"{}";
        $res=json_decode(trim($tx),true);
        if(is_array($res)===false){ return new \WP_REST_Response(array("message"=>"AI parse error"),500); }
        $res["customer"]=(string)$c["name"];
        $res["email"]=(string)$c["email"];
        return new \WP_REST_Response(array("ok"=>true,"data"=>$res),200);
    }
}
