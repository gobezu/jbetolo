<?php
//$Copyright$

defined('_JEXEC') or die('Restricted access');

jimport('joomla.plugin.plugin');
require_once dirname(__FILE__) . '/../../jbetolo.php';

class JbetoloClearcacheElement {
        public static function ui($name) {
                if (jbetoloHelper::isJ16()) {
                        $name = str_replace('-', '_', $name);
                }
                
                $document = JFactory::getDocument();
                $document->addScript(JURI::root(true).'/plugins/system/jbetolo/'.(jbetoloHelper::isJ16() ? 'jbetolo/':'').'/elements/clearcache.js');

                $document->addScriptDeclaration("
                        var _jbetoloclearcache;

                        window.addEvent('domready', function() {
                                _jbetoloclearcache = new jbetoloclearcache({
                                        base: '". JURI::base() ."',
                                        prefix: '".(jbetoloHelper::isJ16() ? 'jform_params_' : 'params')."',
                                        j16: ".(jbetoloHelper::isJ16() ? 'true' : 'false').",
                                        PLG_SYSTEM_JBETOLO_CACHE_CLEARED: '". JText::_('PLG_SYSTEM_JBETOLO_CACHE_CLEARED') ."',
                                });
                        });
                ");

                $ui = "
                        <div class='fieldContainer'>
                                <ul class='btns'>
                                        <li><a id='clearSiteCacheBtn' title='".JText::_('PLG_SYSTEM_JBETOLO_CACHE_CLEAR_SITE')."' href='#'>".JText::_('PLG_SYSTEM_JBETOLO_CACHE_CLEAR_SITE')."</a></li>
                                        <li><a id='clearAdministratorCacheBtn' title='".JText::_('PLG_SYSTEM_JBETOLO_CACHE_CLEAR_ADMINISTRATOR')."' href='#'>".JText::_('PLG_SYSTEM_JBETOLO_CACHE_CLEAR_ADMINISTRATOR')."</a></li>
                                </ul>
                        </div>
                "
                ;

                return $ui;
        }
}

if (jbetoloHelper::isJ16()) {
        class JFormFieldClearcache extends JFormField {
                public $type = 'JbetoloClearcache';

                protected function getInput() {
                        return JbetoloClearcacheElement::ui($this->fieldname);
                }
        }
} else {
        class JElementClearcache extends JElement {
                var $_name = 'JbetoloClearcache';

                public function fetchElement($name, $value, &$node, $control_name) {
                        $fileList = parent::fetchElement($name, $value, $node, $control_name);
                        return JbetoloClearcacheElement::ui($name);
                }
        }
}

?>
