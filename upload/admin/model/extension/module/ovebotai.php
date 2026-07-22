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
        if (!isset($settings['module_ovebotai_cache_version'])) {
            $settings['module_ovebotai_cache_version'] = 1;
        }
        // Never auto-enable the storefront chat on install — that only happens
        // once the setup wizard actually completes.
        if (!isset($settings['module_ovebotai_setup_complete'])) {
            $settings['module_ovebotai_setup_complete'] = '';
        }

        $this->model_setting_setting->editSetting('module_ovebotai', $settings);
    }

    public function uninstall() {
        // Only the setting row — no custom tables in phase 1.
        $this->db->query("DELETE FROM `" . DB_PREFIX . "setting` WHERE `code` = 'module_ovebotai'");
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
