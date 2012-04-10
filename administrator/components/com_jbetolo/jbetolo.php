<?php
//$Copyright$
 
// no direct access
defined('_JEXEC') or die('Restricted access');

$task = JRequest::getCmd('task');

require_once dirname(__FILE__).'/helper.php';

$lang = JFactory::getLanguage();
$lang->load('plg_system_jbetolo');

switch ($task) {
        case 'serve':
                $file = JRequest::getString('file', false);
                $type = JRequest::getString('type', false);

                if ($file && $type) {
                        jbetoloComponentHelper::sendFile($type, $file);
                } else {
                        $file = JRequest::getString('cfile', false);
                        
                        if (!$file) die('Restricted access');

                        jbetoloComponentHelper::sendFile('htaccess', $file);
                }

                break;
        case 'clearcache':
                die(jbetoloComponentHelper::resetCache());
                break;
        case 'resetsetting':
                die(jbetoloComponentHelper::resetSetting());
                break;
        case 'savesetting':
                die(jbetoloComponentHelper::saveSetting());
                break;
        case 'smushit':
                die(jbetoloComponentHelper::smushIt());
                break;
        case 'ping':
                die(jbetoloComponentHelper::ping());
                break;
        default:
                jbetoloComponentHelper::redirectToPlg('jbetolo', 'system');
                break;
}

?>