<?php
//$Copyright$

defined('_JEXEC') or die('Restricted access');

jimport('joomla.plugin.plugin');
require_once __DIR__ . '/../../jbetolo.php';

class JbetoloHtaccess {
        public static function ui($name) {
                if (jbetoloHelper::isJ16()) {
                        $name = str_replace('-', '_', $name);
                }
                
                $document = JFactory::getDocument();
                $document->addScript(JURI::root(true).'/plugins/system/jbetolo/'.(jbetoloHelper::isJ16() ? 'jbetolo/':'').'/elements/htaccess.js');

                $document->addScriptDeclaration("
                        window.addEvent('domready', function() {
                                new jbetolohtaccess({base: '". JURI::base() ."'});
                        });
                ");

                $ui = "
                        <div class='fieldContainer'>
                                <button type='button' id='htaccessBtn'>".JText::_('PLG_SYSTEM_JBETOLO_HTACCESS_PATCH_BTN')."</button>
                        </div>
                "
                ;

                return $ui;
        }
}

if (jbetoloHelper::isJ16()) {
        class JFormFieldHtaccess extends JFormField {
                public $type = 'JbetoloHtaccess';

                protected function getInput() {
                        return JbetoloHtaccess::ui($this->fieldname);
                }
        }
} else {
        class JElementHtaccess extends JElement {
                var $_name = 'JbetoloHtaccess';

                public function fetchElement($name, $value, &$node, $control_name) {
                        return JbetoloHtaccess::ui($name);
                }
        }
}

?>
