<?php
require_once CONFIG_PATH . '/constants.php';

class Language {
    private $translations;
    private $lang;

    public function __construct($lang = DEFAULT_LANGUAGE) {
        $this->lang = in_array($lang, ['es', 'en']) ? $lang : DEFAULT_LANGUAGE;
        $this->loadTranslations();
    }

    // Cargar traducciones desde archivo JSON
    private function loadTranslations() {
        $file = ASSETS_PATH . '/lang/' . $this->lang . '.json';
        if (file_exists($file)) {
            $this->translations = json_decode(file_get_contents($file), true);
        } else {
            $this->translations = [];
            error_log("Translation file not found: " . $file);
        }
    }

    // Obtener traducción para una clave
    public function get($key, $default = null) {
        return $this->translations[$key] ?? ($default ?? $key);
    }

    // Cambiar idioma
    public function setLanguage($lang) {
        if (in_array($lang, ['es', 'en'])) {
            $this->lang = $lang;
            $this->loadTranslations();
        }
    }

    // Obtener idioma actual
    public function getCurrentLanguage() {
        return $this->lang;
    }
}
?>