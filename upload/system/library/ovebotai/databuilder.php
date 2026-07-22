<?php

namespace Ovebotai;

// Turns OpenCart catalog data (information pages, products) into the payload
// shapes Ovebot.ai expects. Deliberately minimal for phase 1 — the setup
// wizard's KB sync builds its page payloads in the Ovebotai orchestrator for
// now. This class exists and is constructed exactly like Typesense's
// DataBuilder so later phases (product feed documents, richer KB extraction)
// have a home that already has full registry access ($this->db, $this->config,
// catalog models, …) without changing the orchestrator's constructor.
class DataBuilder extends \Model {
    private $language_id;

    public function __construct($registry) {
        parent::__construct($registry);

        $this->language_id = (int)$this->config->get('config_language_id');
    }

    public function setLanguageId($language_id) {
        $this->language_id = (int)$language_id;
        return $this;
    }

    public function getLanguageId() {
        return $this->language_id ? $this->language_id : (int)$this->config->get('config_language_id');
    }
}
