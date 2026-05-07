<?php

defined("ABSPATH") || exit;

require_once __DIR__ . "/class-customers-repository.php";
require_once __DIR__ . "/class-events-repository.php";

final class LOTPL_Customer_Hub_Admin_Menu {
    public function register(): void {
        add_action("admin_menu", array($this, "add_menu"));
    }

    public function add_menu(): void {
        add_menu_page(
            "LOTPL Customer Hub",
            "Customer Hub",
            "manage_options",
            "lotpl-customer-hub",
            array($this, "render_page"),
            "dashicons-groups",
            26
        );
    }
    public function render_page(): void {
        $legacy_url = add_query_arg(array("v" => 3, "_t" => time(), "rest_base" => rawurlencode(rest_url("lotpl-customer-hub/v1/")), "rest_nonce" => wp_create_nonce("wp_rest")), home_url("/customers/lotpl-customer-manager"));
        $planner_count = (int) get_option("lotpl_trip_planner_generate_count", 0);
        $planner_last = (string) get_option("lotpl_trip_planner_last_used", "Never");
        echo "<div class=\"wrap\"><h1>LOTPL Customer Hub</h1><div style=\"margin:10px 0 12px;padding:12px 14px;background:#fff;border:1px solid #dcdcde;border-radius:8px;\"><strong>Trip Planner</strong><span style=\"margin-left:16px;\">Generated: " . esc_html((string) $planner_count) . "</span><span style=\"margin-left:16px;color:#646970;\">Last used: " . esc_html($planner_last) . "</span></div><iframe id=\"lotpl-hub-iframe\" src=\"" . esc_url($legacy_url) . "\" style=\"width:100%;height:calc(100vh - 170px);border:1px solid #dcdcde;background:#fff;border-radius:8px;\"></iframe></div>";
        return;

        $repo = new LOTPL_Customer_Hub_Customers_Repository();
        $events_repo = new LOTPL_Customer_Hub_Events_Repository();
        $count = $repo->count_all();
        $customers = $repo->get_all();
        $customer_id = isset($_GET["customer_id"]) ? (int) $_GET["customer_id"] : 0;

        echo "<div class=\"wrap\">";
        echo "<h1>LOTPL Customer Hub</h1>";
        echo "<div class=\"notice notice-info inline\"><p>v0.1 skeleton is active.</p></div>";

        if ($customer_id > 0) {
            $selected = null;

            foreach ($customers as $customer) {
                if ((int) $customer["id"] === $customer_id) {
                    $selected = $customer;
                    break;
                }
            }

            echo "<p><a href=\"admin.php?page=lotpl-customer-hub\">&larr; Back to customers</a></p>";

            if ($selected === null) {
                echo "<p>Customer not found in latest list.</p>";
                echo "</div>";
                return;
            }

            $events = $events_repo->get_latest_by_customer_id($customer_id);

            echo "<h2>Customer Detail</h2>";
            echo "<p><strong>ID:</strong> " . (int) $selected["id"] . "</p>";
            echo "<p><strong>Name:</strong> " . esc_html((string) $selected["name"]) . "</p>";
            echo "<p><strong>Email:</strong> " . esc_html((string) $selected["email"]) . "</p>";
            echo "<p><strong>Status:</strong> " . esc_html((string) $selected["status"]) . "</p>";
            echo "<p><strong>Created:</strong> " . esc_html((string) $selected["created_at"]) . "</p>";

            echo "<h2>Timeline</h2>";

            if (empty($events)) {
                echo "<p>No timeline entries yet.</p>";
                echo "</div>";
                return;
            }

            foreach ($events as $event) {
                $meta_json = (string) $event["meta_json"];
                $review_label = strpos($meta_json, chr(34) . "should_auto_reply" . chr(34) . ":false") !== false ? "Human review needed" : "";
                $is_lotpl = strpos((string) $event["event_type"], "auto_reply") !== false || strpos($meta_json, chr(34) . "direction" . chr(34) . ":" . chr(34) . "sent" . chr(34)) !== false;
                $align = $is_lotpl ? "margin-left:auto;background:#f3f8f3;border:1px solid #d9e7d9;" : "margin-right:auto;background:#ffffff;border:1px solid #e5e7eb;";
                $label = $is_lotpl ? "LOTPL" : "Guest";
                echo "<div style=\"max-width:78%;" . $align . "border-radius:18px;padding:14px 16px;margin:0 0 14px;box-shadow:0 1px 2px rgba(0,0,0,.04);\">";
                echo "<div style=\"display:flex;justify-content:space-between;gap:12px;font-size:12px;color:#6b7280;margin:0 0 8px;\"><span><strong>" . esc_html($label) . "</strong>" . ($review_label !== "" ? " <em>" . esc_html($review_label) . "</em>" : "") . "</span><span>" . esc_html((string) $event["created_at"]) . "</span></div>";
                $subject_text = trim((string) $event["subject"]);
                $body_text = trim((string) $event["body"]);
                $body_text = str_replace(array(" Message Body:", " Tour Name:", " Tour URL:", " Name:", " Email:", " Message:", " Your name ", " Your email ", " Number of person ", " Your phone ", " What would you like to inquire about? "), array("\n\nMessage Body:", "\nTour Name:", "\nTour URL:", "\nName:", "\nEmail:", "\nMessage:", "\nYour name ", "\nYour email ", "\nNumber of person ", "\nYour phone ", "\nWhat would you like to inquire about?\n"), $body_text);
                $body_text = str_replace(array(" Dear ", " Hi ", " Warm regards", " Best regards", " Kind regards", " Regards", " https://"), array("\n\nDear ", "\n\nHi ", "\n\nWarm regards", "\n\nBest regards", "\n\nKind regards", "\n\nRegards", "\nhttps://"), $body_text);
                if ($subject_text !== "") {
                    echo "<div style=\"font-size:13px;font-weight:600;line-height:1.5;margin:0 0 6px;\">" . esc_html($subject_text) . "</div>";
                }
                echo "<div style=\"font-size:14px;line-height:1.75;white-space:pre-line;\">" . esc_html($body_text) . "</div>";
                echo "</div>";
            }
        }

        echo "<h2>Customers</h2>";
        echo "<p>Total customers: " . (int) $count . "</p>";

        if (empty($customers)) {
            echo "<p>No customers yet.</p>";
            echo "</div>";
            return;
        }

        echo "<div style=\"max-width:720px;display:grid;gap:10px;\">";

        foreach ($customers as $customer) {
            echo "<a href=\"admin.php?page=lotpl-customer-hub&customer_id=" . (int) $customer["id"] . "\" style=\"display:block;text-decoration:none;color:inherit;background:#fff;border:1px solid #e5e7eb;border-radius:14px;padding:14px 16px;box-shadow:0 1px 2px rgba(0,0,0,.04);\">";
            echo "<div style=\"display:flex;justify-content:space-between;gap:12px;align-items:flex-start;\">";
            echo "<div>";
            echo "<div style=\"font-size:15px;font-weight:600;line-height:1.4;\">" . esc_html((string) $customer["name"]) . "</div>";
            echo "<div style=\"font-size:12px;color:#6b7280;line-height:1.5;\">" . esc_html((string) $customer["email"]) . "</div>";
            echo "</div>";
            echo "<div style=\"font-size:11px;color:#6b7280;white-space:nowrap;\">" . esc_html((string) $customer["status"]) . "</div>";
            echo "</div>";
            echo "<div style=\"margin-top:8px;font-size:11px;color:#9ca3af;\">Customer #" . (int) $customer["id"] . "  " . esc_html((string) $customer["created_at"]) . "</div>";
            echo "</a>";
        }

        echo "</div>";
        echo "</div>";
    }
    }
