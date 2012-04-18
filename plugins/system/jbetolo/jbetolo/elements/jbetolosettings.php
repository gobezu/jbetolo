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

class JbetoloSettingsElement {
        public static function ui($name, $fileList) {
                if (jbetoloHelper::isJ16()) {
                        $name = str_replace('-', '_', $name);
                }
                
                $document = JFactory::getDocument();
                $document->addScript(JURI::root(true).'/plugins/system/jbetolo/'.(jbetoloHelper::isJ16() ? 'jbetolo/':'').'/elements/jbetolosettings.js');

                $document->addScriptDeclaration("
                        var _jbetolosettings;

                        window.addEvent('domready', function() {
                                _jbetolosettings = new jbetolosettings({
                                        base: '". JURI::base() ."',
                                        settingsSelectorID: '". $name ."',
                                        prefix: '".(jbetoloHelper::isJ16() ? 'jform_params_' : 'params')."',
                                        j16: ".(jbetoloHelper::isJ16() ? 'true' : 'false').",
                                        PLG_JBETOLO_PREDEFINED_SUCCESS: '". JText::_('PLG_JBETOLO_PREDEFINED_SUCCESS') ."',
                                        PLG_JBETOLO_PREDEFINED_CONFIRM: '". JText::_('PLG_JBETOLO_PREDEFINED_CONFIRM') ."',
                                        PLG_JBETOLO_PREDEFINED_SAVENAME: '". JText::_('PLG_JBETOLO_PREDEFINED_SAVENAME') ."',
                                        PLG_JBETOLO_PREDEFINED_SAVEAUTHOR: '". JText::_('PLG_JBETOLO_PREDEFINED_SAVEAUTHOR') ."',
                                        PLG_JBETOLO_PREDEFINED_SAVEFAILURE: '". JText::_('PLG_JBETOLO_PREDEFINED_SAVEFAILURE') ."',
                                        PLG_JBETOLO_PREDEFINED_SAVESUCCESS: '". JText::_('PLG_JBETOLO_PREDEFINED_SAVESUCCESS') ."',
                                        PLG_JBETOLO_PREDEFINED_NAMEEXISTS: '". JText::_('PLG_JBETOLO_PREDEFINED_NAMEEXISTS') ."'
                                });
                        });
                ");

                $ui = "
                        <div style='float:left;'>
                                <div style='clear:both;'>
                                        " . $fileList . "
                                </div>
                                <div style='clear:both;'>
                                        <a id='saveSettingBtn' title='".JText::_('PLG_JBETOLO_PREDEFINED_SAVE')."' href='#'>" . JText::_('PLG_JBETOLO_PREDEFINED_SAVE') . "</a>
                                </div>
                                <div style='clear:both;'>
                                        <a id='readSettingBtn' title='".JText::_('PLG_JBETOLO_PREDEFINED_READ')."' href='#'>" . JText::_('PLG_JBETOLO_PREDEFINED_READ') . "</a>
                                </div>
                                <div style='clear:both;'>
                                        <a id='pingBtn' title='".JText::_('PLG_JBETOLO_PING')."' href='#'>" . JText::_('PLG_JBETOLO_PING') . "</a>
                                </div>
                        </div>
                        <div style='clear:both;'></div>
                "
                ;

                JHTML::_('behavior.modal');

                return $ui;
        }
}

if (jbetoloHelper::isJ16()) {
        require_once JPATH_SITE . '/libraries/joomla/form/fields/filelist.php';
        
        class JFormFieldJbetolosettings extends JFormFieldFileList {
                public $type = 'JbetoloJbetoloSettings';

                protected function getInput() {
                        $fileList = parent::getInput();
                        return JbetoloSettingsElement::ui($this->fieldname, $fileList);
                }
        }
} else {
        require_once JPATH_SITE . '/libraries/joomla/html/parameter/element/filelist.php';

        class JElementJbetoloSettings extends JElementFilelist {
                var $_name = 'JbetoloJbetoloSettings';

                public function fetchElement($name, $value, &$node, $control_name) {
                        $fileList = parent::fetchElement($name, $value, $node, $control_name);
                        return JbetoloSettingsElement::ui($name, $fileList);
                }
        }
}

?>
