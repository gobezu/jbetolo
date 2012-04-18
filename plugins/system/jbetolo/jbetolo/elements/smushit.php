<?php
/**
* @version:	2.0.0.b10-99 - 2012 March 09 16:03:58 +0300
* @package:	jbetolo
* @subpackage:	jbetolo
* @copyright:	Copyright (C) 2010 - 2011 jproven.com. All rights reserved. 
* @license:	GNU General Public License Version 2, or later http://www.gnu.org/licenses/gpl.html
*/

defined('_JEXEC') or die('Restricted access');

jimport('joomla.plugin.plugin');
require_once dirname(__FILE__) . '/../../jbetolo.php';

class JbetoloSmushitElement {
        public static function ui($name) {
                if (jbetoloHelper::isJ16()) {
                        $name = str_replace('-', '_', $name);
                }
                
                $document = JFactory::getDocument();
                $loc = JURI::root(true).'/plugins/system/jbetolo/'.(jbetoloHelper::isJ16() ? 'jbetolo/':'').'elements/';
                $document->addScript($loc.'smushit.js');

                $document->addScriptDeclaration("
                        var _jbetolosmushit;

                        window.addEvent('domready', function() {
                                _jbetolosmushit = new jbetolosmushit({
                                        base: '". JURI::base() ."',
                                        smushitDir: '". $name ."',
                                        j16: ".(jbetoloHelper::isJ16() ? 'true' : 'false').",
                                        PLG_JBETOLO_SMUSHIT_DIRECTORY: '". JText::_('PLG_JBETOLO_SMUSHIT_DIRECTORY') ."'
                                });
                        });
                ");

                $ui = '<div style="border:2px solid #F06F47; padding:3px;clear:both;">
                        <div style="clear:both;">
                        <img id="smushitprogress" src="'.$loc.'progress.gif" style="visibility:hidden;" /><input type="text" name="'.$name.'" id="'.$name.'" value="" size="50" />
                        <button type="button" id="smushItBtn">'.JText::_('PLG_JBETOLO_SMUSHIT_BTN').'</button>
                        </div>
                        <div style="clear:both;">
                        <input type="checkbox" name="'.$name.'_replace" id="'.$name.'_replace" />'. JText::_('PLG_JBETOLO_SMUSHIT_REPLACE') .'
                        </div>
                        <div style="clear:both;">
                        <input type="checkbox" name="'.$name.'_recursive" id="'.$name.'_recursive" />'. JText::_('PLG_JBETOLO_SMUSHIT_RECURSIVE') .'<br />
                        </div>
                        <div style="clear:both;padding-bottom:8px;">
                        <input type="text" name="'.$name.'_fix" id="'.$name.'_fix" value="_smush" />'. JText::_('PLG_JBETOLO_SMUSHIT_FIX') .'
                        </div>
                       </div>'
                ;

                return $ui;
        }
}

if (jbetoloHelper::isJ16()) {
        class JFormFieldSmushit extends JFormField {
                public $type = 'JbetoloSmushit';

                protected function getInput() {
                        return JbetoloSmushitElement::ui($this->fieldname);
                }
        }
} else {
        class JElementSmushit extends JElement {
                var $_name = 'JbetoloSmushit';

                public function fetchElement($name, $value, &$node, $control_name) {
                        return JbetoloSmushitElement::ui($name);
                }
        }
}

?>
