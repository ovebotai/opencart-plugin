<?php

namespace LabelFinders;

// Finder for Dragon Star Curier. Filename/class name must match ('DragonStar')
// so filter_label_finders can select it with the short code 'Dragon' (or
// 'DragonStar').
class DragonStar_1 extends \Model {

    // Enabled if any of the settings the Dragon Star module has historically
    // used across versions is on — first match wins.
    public function checkStatus() {
        $keys = array(
            'dragonstar_status',
            'module_dragonstar_status',
            'shipping_dragonstar_status',
        );

        foreach ($keys as $key) {
            if ($this->config->get($key)) {
                return true;
            }
        }

        return false;
    }

    // Returns array(order_id => array('code', 'name', 'awb', 'tracking_url'))
    // or null when the module's table doesn't exist or has no AWB for the
    // order. When multiple AWBs exist for the same order, the most recent
    // one (latest date_added) is used.
    public function findLabelForOrder($order_id) {
        if (!$this->tableExists()) {
            return null;
        }

        $query = $this->db->query(
            "SELECT `awb_number` FROM `" . DB_PREFIX . "dragonstar_awb`
             WHERE `order_id` = '" . (int)$order_id . "'
             ORDER BY `date_added` DESC
             LIMIT 1"
        );

        if (!$query->num_rows) {
            return null;
        }

        $awb = $query->row['awb_number'];

        return array(
            (int)$order_id => array(
                'code'         => 'dragonstar',
                'name'         => 'Dragon Star',
                'awb'          => $awb,
                'tracking_url' => $this->buildTrackingUrl('dragonstar', $awb),
            ),
        );
    }

    // Uses the registry's '{code}_tracking_url_format' override when set
    // (placeholder '{code}' is replaced with the awb), otherwise the
    // hardcoded default.
    private function buildTrackingUrl($code, $awb) {
        $format = $this->config->get($code . '_tracking_url_format');

        if (!$format) {
            $format = 'https://dragonstarcurier.ro/tracking-awb?awb={code}';
        }

        return str_replace('{code}', $awb, $format);
    }

    private function tableExists() {
        $query = $this->db->query("SHOW TABLES LIKE '" . DB_PREFIX . "dragonstar_awb'");

        return (bool)$query->num_rows;
    }
}
