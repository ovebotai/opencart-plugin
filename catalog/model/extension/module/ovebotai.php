<?php
// Storefront data for Ovebot.ai: the products feed and the order-tracking
// lookup. Modelled directly on the aquatime store's ControllerModuleOvebotai
// (OpenCart 1.5.x), adapted to the current OpenCart schema/registry and to
// this module's config keys (module_ovebotai_*). Kept as a plain Model (not
// the system/library/ovebotai.php orchestrator) since this is pure catalog
// data building, with no OAuth/API concerns.
class ModelExtensionModuleOvebotai extends Model {

    // ── Products feed ─────────────────────────────────────────────────────────

    // Enabled, in-stock, priced products only — same filter as the admin
    // wizard's "feed_count" preview (see admin/model/.../ovebotai.php
    // getProductCounts()), so what the wizard promises is what actually ships.
    public function getProductsFeed($language_id, $store_id, $customer_group_id) {
        $language_id       = (int)$language_id;
        $store_id           = (int)$store_id;
        $customer_group_id = (int)$customer_group_id;

        $product_categories = $this->getProductsLongestCategoryPath($language_id);
        $product_attributes = $this->getProductsAttributes($language_id);
        $product_options    = $this->getProductsOptions($language_id);

        $query = $this->db->query("
            SELECT p.product_id, p.sku, p.model, pd.name, pd.description, p.image, m.name AS manufacturer,
                   p.quantity, p.price, p.tax_class_id,
                   (SELECT price FROM `" . DB_PREFIX . "product_special` ps
                    WHERE ps.product_id = p.product_id AND ps.customer_group_id = '" . $customer_group_id . "'
                    AND ((ps.date_start = '0000-00-00' OR ps.date_start < NOW()) AND (ps.date_end = '0000-00-00' OR ps.date_end > NOW()))
                    ORDER BY ps.priority ASC, ps.price ASC LIMIT 1) AS special
            FROM `" . DB_PREFIX . "product` p
            JOIN `" . DB_PREFIX . "product_description` pd ON (p.product_id = pd.product_id AND pd.language_id = '" . $language_id . "')
            LEFT JOIN `" . DB_PREFIX . "product_to_store` p2s ON (p.product_id = p2s.product_id AND p2s.store_id = '" . $store_id . "')
            LEFT JOIN `" . DB_PREFIX . "manufacturer` m ON (p.manufacturer_id = m.manufacturer_id)
            WHERE p.status = '1' AND p.date_available <= NOW() AND p.quantity > 0 AND p.price > 0
            ORDER BY p.product_id ASC
        ");

        $data = array();

        foreach ($query->rows as $row) {
            $pid = (int)$row['product_id'];

            $price     = round($this->tax->calculate($row['price'], $row['tax_class_id'], $this->config->get('config_tax')), 2);
            if (!empty($row['special']) && $row['special'] < $row['price']) {
                $special = round($this->tax->calculate($row['special'], $row['tax_class_id'], $this->config->get('config_tax')), 2);
            } else {
                $special = null;
            }

            $category = isset($product_categories[$pid]) ? $product_categories[$pid] : null;

            $ref  = (string)$pid;
            $name = strip_tags(html_entity_decode($row['name'], ENT_QUOTES, 'UTF-8'));
            $desc = $this->htmlToPlainText(html_entity_decode($row['description'], ENT_QUOTES, 'UTF-8'));

            $attributes = isset($product_attributes[$pid]) ? $product_attributes[$pid] : array();
            if (isset($product_options[$pid])) {
                $attributes = array_merge($attributes, $product_options[$pid]);
            }

            $data[] = array(
                'ref'          => $ref,
                'name'         => $name,
                'description'  => $desc,
                'category'     => $category,
                'manufacturer' => $row['manufacturer'] !== null ? strip_tags(html_entity_decode($row['manufacturer'], ENT_QUOTES, 'UTF-8')) : null,
                'availability' => $row['quantity'] > 0 ? 'in_stock' : 'out_of_stock',
                'quantity'     => (int)$row['quantity'],
                'price'        => $price,
                'special'      => $special,
                'currency'     => $this->config->get('config_currency'),
                'image'        => !empty($row['image']) ? $this->imageBase() . html_entity_decode($row['image'], ENT_QUOTES, 'UTF-8') : null,
                'url'          => html_entity_decode($this->url->link('product/product', 'product_id=' . $pid), ENT_QUOTES, 'UTF-8'),
                'attributes'   => $attributes,
                // 'lang'         => $this->config->get('config_language'),
            );
        }

        return $data;
    }

    private function getProductsAttributes($language_id) {
        $query = $this->db->query("
            SELECT pa.product_id, ad.name,
                   GROUP_CONCAT(pa.`text` SEPARATOR '\n') AS `text`
            FROM `" . DB_PREFIX . "product_attribute` pa
            JOIN `" . DB_PREFIX . "attribute` a ON (a.attribute_id = pa.attribute_id)
            JOIN `" . DB_PREFIX . "attribute_description` ad ON (ad.attribute_id = pa.attribute_id AND ad.language_id = '" . (int)$language_id . "')
            LEFT JOIN `" . DB_PREFIX . "attribute_group` ag ON (a.attribute_group_id = ag.attribute_group_id)
            WHERE pa.language_id = '" . (int)$language_id . "'
            GROUP BY pa.product_id, pa.attribute_id
            ORDER BY pa.product_id, ag.sort_order, a.sort_order, ad.name
        ");

        $product_attributes = array();

        foreach ($query->rows as $row) {
            $pid  = (int)$row['product_id'];
            $name = strip_tags(html_entity_decode($row['name'], ENT_QUOTES, 'UTF-8'));
            $text = html_entity_decode((string)$row['text'], ENT_QUOTES, 'UTF-8');

            $product_attributes[$pid][$name] = $text;
        }

        return $product_attributes;
    }

    // Choice-based product options (select/radio/checkbox — anything with
    // option values, e.g. "Culoare: Rosu | Maro | Mov"). Text/date/file-type
    // options have no option_value rows so they're naturally excluded here.
    // Folded into the same 'attributes' map as getProductsAttributes() (one
    // entry per option name, values joined with ' | ').
    private function getProductsOptions($language_id) {
        $query = $this->db->query("
            SELECT pov.product_id, od.name AS option_name,
                   GROUP_CONCAT(ovd.name ORDER BY ov.sort_order SEPARATOR ' | ') AS value_names
            FROM `" . DB_PREFIX . "product_option_value` pov
            JOIN `" . DB_PREFIX . "option` o ON (o.option_id = pov.option_id)
            JOIN `" . DB_PREFIX . "option_description` od ON (od.option_id = pov.option_id AND od.language_id = '" . (int)$language_id . "')
            JOIN `" . DB_PREFIX . "option_value` ov ON (ov.option_value_id = pov.option_value_id)
            JOIN `" . DB_PREFIX . "option_value_description` ovd ON (ovd.option_value_id = pov.option_value_id AND ovd.language_id = '" . (int)$language_id . "')
            GROUP BY pov.product_id, pov.option_id
            ORDER BY pov.product_id, pov.product_option_value_id
        ");

        $product_options = array();

        foreach ($query->rows as $row) {
            $pid  = (int)$row['product_id'];
            $name = strip_tags(html_entity_decode($row['option_name'], ENT_QUOTES, 'UTF-8'));
            $values = html_entity_decode((string)$row['value_names'], ENT_QUOTES, 'UTF-8');

            $product_options[$pid][$name] = $values;
        }

        return $product_options;
    }

    private function getProductsLongestCategoryPath($language_id) {
        $query = $this->db->query("
            SELECT p2c.product_id,
                   p2c.category_id,
                   (
                       SELECT GROUP_CONCAT(TRIM(cd2.name) ORDER BY cp.level ASC SEPARATOR ' > ')
                       FROM `" . DB_PREFIX . "category_path` cp
                       JOIN `" . DB_PREFIX . "category_description` cd2 ON (cd2.category_id = cp.path_id AND cd2.language_id = '" . (int)$language_id . "')
                       WHERE cp.category_id = p2c.category_id
                   ) AS path_text,
                   (
                       SELECT COUNT(*) FROM `" . DB_PREFIX . "category_path` cp2 WHERE cp2.category_id = p2c.category_id
                   ) AS path_depth
            FROM `" . DB_PREFIX . "product_to_category` p2c
            JOIN `" . DB_PREFIX . "category` c ON (c.category_id = p2c.category_id AND c.status = 1)
        ");

        $product_categories = array();
        $best_depth = array();

        foreach ($query->rows as $row) {
            $pid   = (int)$row['product_id'];
            $depth = (int)$row['path_depth'];
            $path  = $row['path_text'];

            if ($path === null || $path === '') {
                continue;
            }

            if (!isset($best_depth[$pid]) || $depth > $best_depth[$pid]) {
                $best_depth[$pid]         = $depth;
                $product_categories[$pid] = $path;
            }
        }

        return $product_categories;
    }

    private function htmlToPlainText($content) {
        $text = strip_tags($content);
        $text = html_entity_decode($text, ENT_QUOTES, 'UTF-8');
        $text = preg_replace('/\t+/', ' ', $text);
        $text = preg_replace('/ +/', ' ', $text);
        $text = preg_replace("/(\r?\n){2,}/", "\n", $text);

        return trim($text);
    }

    private function imageBase() {
        if (defined('HTTPS_SERVER')) {
            return HTTPS_SERVER . 'image/';
        }
        if (defined('HTTP_SERVER')) {
            return HTTP_SERVER . 'image/';
        }
        return '';
    }

    // ── Order tracking ───────────────────────────────────────────────────────

    // $type is 'email' or 'phone', $value already validated/normalized by the
    // controller. AWB/carrier/tracking_url come from the first shipping
    // module (Label_finder) that has one for this order — null when none
    // do. estimated_delivery is always null — the delivery-estimate feature
    // was dropped along with its settings-form panel, but the key stays
    // present so Ovebot.ai's response shape is unchanged.
    public function getOrderData($order_id, $type, $value) {
        $language_id = (int)$this->config->get('config_language_id');

        if ($type === 'phone') {
            $clean_column = "REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(o.telephone, ' ', ''), '.', ''), '-', ''), '/', ''), '(', ''), ')', ''), '+', '')";
            $match_condition = $clean_column . " LIKE '%" . $this->db->escape($value) . "'";
        } else {
            $match_condition = "o.email = '" . $this->db->escape($value) . "'";
        }

        $query = $this->db->query("
            SELECT o.order_id, o.date_added, o.total, o.currency_code, os.name AS order_status
            FROM `" . DB_PREFIX . "order` o
            LEFT JOIN `" . DB_PREFIX . "order_status` os ON (o.order_status_id = os.order_status_id AND os.language_id = '" . $language_id . "')
            WHERE o.order_id = '" . (int)$order_id . "'
              AND {$match_condition}
              AND o.order_status_id > '0'
              AND o.date_added >= DATE_SUB(NOW(), INTERVAL 60 DAY)
            LIMIT 1
        ");

        if (!$query->num_rows) {
            return null;
        }

        $order = $query->row;

        $label = $this->findOrderLabel((int)$order['order_id']);

        return array(
            'id'                 => (int)$order['order_id'],
            'date'               => $order['date_added'],
            'status'             => $order['order_status'] !== null ? $order['order_status'] : '',
            'total'              => round((float)$order['total'], 2),
            'currency'           => $order['currency_code'],
            'estimated_delivery' => null,
            'carrier'            => $label !== null ? $label['name'] : null,
            'awb'                => $label !== null ? $label['awb'] : null,
            'awb_tracking_url'   => $label !== null ? $label['tracking_url'] : null,
        );
    }

    // Label_finder::findOrderLabel() with returnFirst = true returns a
    // single-entry array(order_id => array(...)) or null — this unwraps to
    // just the details array (or null).
    private function findOrderLabel($order_id) {
        $this->load->library('label_finder');

        $result = $this->label_finder->findOrderLabel($order_id, true);

        return $result ? reset($result) : null;
    }
}
