<?php

namespace LabelFinders;

// Finder for Fan Courier. Filename/class name must match ('FanCourier') so
// filter_label_finders can select it with the short code 'Fan'.
class FanCourier_1 extends \Model {

    // Enabled if any of the settings the Fan Courier module has historically
    // used across versions is on — first match wins.
    public function checkStatus() {
        $keys = array(
            'fancourier_status',
            'module_fancourier_status',
            'shipping_fancourier_status',
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
    // one (highest id) is used.
    public function findLabelForOrder($order_id) {
        if (!$this->tableExists()) {
            return null;
        }

        $query = $this->db->query(
            "SELECT `awb` FROM `" . DB_PREFIX . "fancurier_selfawb_orders`
             WHERE `order_id` = '" . (int)$order_id . "'
             ORDER BY `id` DESC
             LIMIT 1"
        );

        if (!$query->num_rows) {
            return null;
        }

        $awb = $query->row['awb'];

        return array(
            (int)$order_id => array(
                'code'         => 'fancourier',
                'name'         => 'Fancourier',
                'awb'          => $awb,
                'tracking_url' => $this->buildTrackingUrl('fancourier', $awb),
            ),
        );
    }

    // Uses the registry's '{code}_tracking_url_format' override when set
    // (placeholder '{code}' is replaced with the awb), otherwise the
    // hardcoded default.
    private function buildTrackingUrl($code, $awb) {
        $format = $this->config->get($code . '_tracking_url_format');

        if (!$format) {
            $format = 'https://www.fancourier.ro/awb-tracking/?tracking={code}';
        }

        return str_replace('{code}', $awb, $format);
    }

    private function tableExists() {
        $query = $this->db->query("SHOW TABLES LIKE '" . DB_PREFIX . "fancurier_selfawb_orders'");

        return (bool)$query->num_rows;
    }
}
