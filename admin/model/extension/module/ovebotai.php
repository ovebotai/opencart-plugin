<?php
class ModelExtensionModuleOvebotai extends Model {

    public function install() {
        $this->load->model('setting/setting');

        $settings = $this->model_setting_setting->getSetting('module_ovebotai');

        // Seed the storefront credentials once, on first install, and never
        // overwrite them on reinstall (a reinstall must not silently break a
        // feed URL / order-lookup already registered with Ovebot.ai).
        if (empty($settings['module_ovebotai_feed_hash'])) {
            $settings['module_ovebotai_feed_hash'] = $this->randomToken(16);
        }
        if (empty($settings['module_ovebotai_order_user'])) {
            $settings['module_ovebotai_order_user'] = $this->orderUser();
        }
        if (empty($settings['module_ovebotai_order_pass'])) {
            $settings['module_ovebotai_order_pass'] = $this->randomToken(16);
        }
        // Never auto-enable the storefront chat on install — that only happens
        // once the setup wizard actually completes.
        if (!isset($settings['module_ovebotai_setup_complete'])) {
            $settings['module_ovebotai_setup_complete'] = '';
        }

        $this->model_setting_setting->editSetting('module_ovebotai', $settings);

        $this->addEvents();
    }

    public function uninstall() {
        // Only the setting row — no custom tables in phase 1.
        $this->db->query("DELETE FROM `" . DB_PREFIX . "setting` WHERE `code` = 'module_ovebotai'");

        $this->removeEvents();
    }

    // ── Storefront events ─────────────────────────────────────────────────────

    // Three hooks, all resolving to ControllerExtensionModuleOvebotai:
    //   - common/footer/after: appends the chat widget snippet (index()).
    //   - checkout/success/index/before: the success controller's own
    //     $this->session->data['order_id'] is still readable here, so the id
    //     is captured (captureOrderId()) into the registry before it either
    //     runs or gets cleared.
    //   - common/success/after: the purchase-conversion pixel (purchaseEvent())
    //     — reads back the id captured above and appends its own snippet once
    //     the success template's own output ($output) already exists.
    // All three append to $output by reference (OC's .../after event
    // signature — see index()); success/index/before only needs $route/$args.
    //
    // OpenCart's event model moved from setting/event (2.x, addEvent/
    // deleteEvent) to extension/event (3.x, addEvent/deleteEventByCode) —
    // same addEvent() signature otherwise, just an extra $sort_order param
    // on 3.x that defaults to 0.

    private function addEvents() {
        $events = array(
            'catalog/controller/common/footer/after'     => 'extension/module/ovebotai/index',
            'catalog/controller/checkout/success/before' => 'extension/module/ovebotai/captureOrderId',
            'catalog/view/*/success/after'               => 'extension/module/ovebotai/purchaseEvent',
        );

        if (version_compare(VERSION, '3.0', '<')) {
            $this->load->model('extension/event');
            $this->model_extension_event->deleteEvent('module_ovebotai');
            foreach ($events as $trigger => $action) {
                $this->model_extension_event->addEvent('module_ovebotai', $trigger, $action, 1);
            }
        } else {
            $this->load->model('setting/event');
            $this->model_setting_event->deleteEventByCode('module_ovebotai');
            foreach ($events as $trigger => $action) {
                $this->model_setting_event->addEvent('module_ovebotai', $trigger, $action, 1);
            }
        }
    }

    private function removeEvents() {
        if (version_compare(VERSION, '3.0', '<')) {
            $this->load->model('extension/event');
            $this->model_extension_event->deleteEvent('module_ovebotai');
        } else {
            $this->load->model('setting/event');
            $this->model_setting_event->deleteEventByCode('module_ovebotai');
        }
    }

    // ── Information pages for the KB select step ─────────────────────────────

    // Returns array of array('information_id', 'title', 'checked'). When the
    // store has never saved a selection, every page defaults to checked (same
    // as the WordPress wizard); afterwards the saved selection is honoured.
    public function getInformationPages($language_id) {
        $saved = $this->config->get('module_ovebotai_kb_page_ids');
        $saved = is_array($saved) ? array_map('intval', $saved) : array();

        $query = $this->db->query(
            "SELECT i.information_id, id.title
             FROM `" . DB_PREFIX . "information` i
             LEFT JOIN `" . DB_PREFIX . "information_description` id
                ON (i.information_id = id.information_id)
             WHERE id.language_id = '" . (int)$language_id . "'
               AND i.status = '1'
             ORDER BY id.title ASC"
        );

        $pages = array();

        foreach ($query->rows as $row) {
            $id = (int)$row['information_id'];
            $pages[] = array(
                'information_id' => $id,
                'title'          => $row['title'],
                'checked'        => $saved ? in_array($id, $saved, true) : true,
            );
        }

        return $pages;
    }

    // ── Product counts for the products step ─────────────────────────────────

    public function getProductCounts() {
        $total = (int)$this->db->query(
            "SELECT COUNT(*) AS total FROM `" . DB_PREFIX . "product` WHERE `status` = '1'"
        )->row['total'];

        // Products that would actually go on the feed: enabled, in stock, with
        // a positive price.
        $feed_count = (int)$this->db->query(
            "SELECT COUNT(*) AS total FROM `" . DB_PREFIX . "product`
             WHERE `status` = '1' AND `quantity` > 0 AND `price` > 0"
        )->row['total'];

        return array(
            'total'      => $total,
            'feed_count' => $feed_count,
        );
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    private function randomToken($bytes) {
        try {
            return bin2hex(random_bytes($bytes));
        } catch (\Exception $e) {
            return md5(uniqid('ovebotai_', true) . microtime(true));
        } catch (\Throwable $e) {
            return md5(uniqid('ovebotai_', true) . microtime(true));
        }
    }

    private function orderUser() {
        $host = parse_url(defined('HTTP_CATALOG') ? HTTP_CATALOG : '', PHP_URL_HOST);
        $host = preg_replace('/^www\./i', '', (string)$host);
        $slug = strtolower(preg_replace('/[^a-z0-9]+/i', '_', $host));
        $slug = trim($slug, '_');

        return ($slug !== '' ? $slug : 'store') . '_' . substr($this->randomToken(4), 0, 8);
    }
}
