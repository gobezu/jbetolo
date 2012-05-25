<?php
//$Copyright$

defined('_JEXEC') or die('Restricted access');

jimport('joomla.plugin.plugin');
require_once dirname(__FILE__) . '/../../jbetolo.php';

class JbetoloCdnpurge {
        public static function ui($name) {
                if (jbetoloHelper::isJ16()) {
                        $name = str_replace('-', '_', $name);
                }
                
                $document = JFactory::getDocument();
                $document->addScript(JURI::root(true).'/plugins/system/jbetolo/'.(jbetoloHelper::isJ16() ? 'jbetolo/':'').'/elements/cdnpurge.js');

                $document->addScriptDeclaration("
                        window.addEvent('domready', function() {
                                new jbetolocdnpurge({base: '". JURI::base() ."'});
                        });
                ");

                $ui = "
                        <div class='fieldContainer'>
                                <div style='clear:both;'><label for='cdnpurgeCDN'>CDN</label><select id='cdnpurgeCDN'><option>maxcdn</option><option>cloudfront</option></select></div>
                                <div style='clear:both;'><label for='cdnpurgePurge'>File to purge</label><input id='cdnpurgePurge' type='text' size='90' /></div>
                                <div style='clear:both;'><label for='cdnpurgeKeys'>Keys</label><input id='cdnpurgeKeys' type='text' size='90' /><ul style='clear:both;'><li>maxcdn = APIKEY::APIID</li><li>cloudfront = ACCESSKEYID::SECRETKEYID::DISTRIBUTIONID</li></ul></div>
                                <div style='clear:both;'><button type='button' id='smushItBtn'>".JText::_('PLG_SYSTEM_JBETOLO_CDNPURGE_BTN')."</button></div>
                        </div>
                "
                ;

                return $ui;
        }
}

if (jbetoloHelper::isJ16()) {
        class JFormFieldCdnpurge extends JFormField {
                public $type = 'JbetoloCdnpurge';

                protected function getInput() {
                        return JbetoloCdnpurge::ui($this->fieldname);
                }
        }
} else {
        class JElementCdnpurge extends JElement {
                var $_name = 'JbetoloCdnpurge';

                public function fetchElement($name, $value, &$node, $control_name) {
                        return JbetoloCdnpurge::ui($name);
                }
        }
}

?>
