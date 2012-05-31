<?php
//$Copyright$

defined('_JEXEC') or die('Restricted access');

jimport('joomla.plugin.plugin');
require_once dirname(__FILE__) . '/../../jbetolo.php';

class JbetoloJbetolocss {
        public static function ui($name) {
                if (jbetoloHelper::isJ16()) {
                        $name = str_replace('-', '_', $name);
                }
                
                $document = JFactory::getDocument();
                $document->addStyleSheet(JURI::root(true).'/plugins/system/jbetolo/'.(jbetoloHelper::isJ16() ? 'jbetolo/':'').'/elements/jbetolo.css');

                return '';
        }
}

if (jbetoloHelper::isJ16()) {
        class JFormFieldJbetolocss extends JFormField {
                public $type = 'JbetoloJbetolocss';

                protected function getInput() {
                        return JbetoloJbetolocss::ui($this->fieldname);
                }
                
                protected function getLabel() { return false; }
        }
} else {
        class JElementJbetolocss extends JElement {
                var $_name = 'JbetoloJbetolocss';

                public function fetchElement($name, $value, &$node, $control_name) {
                        return JbetoloJbetolocss::ui($name);
                }
                
                function fetchTooltip($label, $description, &$node, $control_name, $name){
                        return NULL;
                }                
        }
}

?>
