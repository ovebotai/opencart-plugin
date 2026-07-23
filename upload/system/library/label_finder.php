<?php

// Scans system/library/LabelFinders/ for AWB-lookup classes and asks each
// one (in turn) whether it can find a shipping label for an order. Loaded
// the normal OpenCart way — $this->load->library('label_finder') — so it
// stays a plain (non-namespaced) Model itself, unlike \Ovebotai\Ovebotai;
// only the finder classes it loads live under the LabelFinders\ namespace.
class Label_finder extends Model {

    // $returnFirst = true (default): stops at the first finder that returns
    // a label and returns that label directly (array|null).
    // $returnFirst = false: runs every matching finder and returns an array
    // of all labels found (possibly empty).
    public function findOrderLabel($order_id, $returnFirst = true) {
        $labels = array();

        foreach ($this->getFinderFiles() as $file) {
            $class = 'LabelFinders\\' . basename($file, '.php');

            require_once($file);

            if (!class_exists($class)) {
                continue;
            }

            $finder = new $class($this->registry);

            if (!$finder->checkStatus()) {
                continue;
            }

            $label = $finder->findLabelForOrder($order_id);

            if (!$label) {
                continue;
            }

            if ($returnFirst) {
                return $label;
            }

            $labels[] = $label;
        }

        return $returnFirst ? null : $labels;
    }

    // filter_label_finders (if set in config) holds short codes (e.g.
    // ['Cargus', 'Fan']) matched as a case-insensitive substring against the
    // finder's class/file name (e.g. 'FanCourier' matches 'Fan'). Unset ->
    // no filtering, every finder in the folder is returned.
    private function getFinderFiles() {
        $dir   = DIR_SYSTEM . 'library/LabelFinders/';
        $files = glob($dir . '*.php');
        $files = is_array($files) ? $files : array();

        $filters = $this->config->get('filter_label_finders');
        $filters = is_array($filters) ? $filters : array();

        if (!$filters) {
            return $files;
        }

        $filtered = array();

        foreach ($files as $file) {
            $class = basename($file, '.php');

            foreach ($filters as $filter) {
                if (stripos($class, (string)$filter) !== false) {
                    $filtered[] = $file;
                    break;
                }
            }
        }

        return $filtered;
    }
}
