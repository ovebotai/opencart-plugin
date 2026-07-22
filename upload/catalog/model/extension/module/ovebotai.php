<?php
class ModelExtensionModuleOvebotai extends Model {

    public function getData() {
        $query = $this->db->query("SELECT * FROM " . DB_PREFIX . "setting WHERE code = 'module_ovebotai'");
        return $query->rows;
    }

}