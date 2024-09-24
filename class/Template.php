<?php
class Template {
    protected $variables = [];
    
    public function assign($key, $value) {
        $this->variables[$key] = $value;
    }
    
    public function render($template) {
        extract($this->variables);
        ob_start();
        include "templates/$template";
        return ob_get_clean();
    }
}
?>