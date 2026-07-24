<?php

namespace LabelFinders;

// Finder for DPD. Filename/class name must match ('Dpd') so
// filter_label_finders can select it with the short code 'Dpd'.
class Dpd_1 extends \Model {

    // Enabled if any of the settings the DPD module has historically used
    // across versions is on — first match wins.
    public function checkStatus() {
        // $keys = array(
        //     'dpd_status',
        //     'module_dpd_status',
        //     'shipping_dpd_status',
        // );

        // foreach ($keys as $key) {
        //     if ($this->config->get($key)) {
        //         return true;
        //     }
        // }

        return true;
    }

    // Returns array(order_id => array('code', 'name', 'awb', 'tracking_url'))
    // or null when the module's table doesn't exist or has no shipment for
    // the order. When multiple shipments exist for the same order, the most
    // recent one (latest date_added) is used.
    public function findLabelForOrder($order_id) {
        if (!$this->tableExists()) {
            return null;
        }

        $query = $this->db->query(
            "SELECT `shipment_id` FROM `" . DB_PREFIX . "order_dpd_shipment`
             WHERE `order_id` = '" . (int)$order_id . "'
             ORDER BY `date_added` DESC
             LIMIT 1"
        );

        if (!$query->num_rows) {
            return null;
        }

        $awb = $query->row['shipment_id'];

        return array(
            (int)$order_id => array(
                'code'         => 'dpd',
                'name'         => 'DPD',
                'awb'          => $awb,
                'tracking_url' => $this->buildTrackingUrl('dpd', $awb),
            ),
        );
    }

    // Uses the registry's '{code}_tracking_url_format' override when set
    // (placeholder '{code}' is replaced with the awb), otherwise the
    // hardcoded default.
    private function buildTrackingUrl($code, $awb) {
        $format = $this->config->get($code . '_tracking_url_format');

        if (!$format) {
            $format = 'https://services.dpd.ro/tracking/?shipmentNumber={code}&language=ro';
        }

        return str_replace('{code}', $awb, $format);
    }

    private function tableExists() {
        $query = $this->db->query("SHOW TABLES LIKE '" . DB_PREFIX . "order_dpd_shipment'");

        return (bool)$query->num_rows;
    }
}
