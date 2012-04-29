<?php
//$Copyright$

defined('_JEXEC') or die('Restricted access');

class jbetoloComponentHelper {
        private static $contentTypes = array('css' => 'text/css', 'js' => 'text/javascript', 'font' => 'text/plain');
        
        private static function gzdecode($file){
                if (!is_file($file)) return '';

                if (function_exists('gzdecode')) return gzdecode($file);

                ob_start();
                readgzfile($file);
                $content = ob_get_clean();

                return $content;
        }

        private static function gmdateStr($time = null) {
                if (is_null($time)) $time = time();
                
                return gmdate("D, d M Y H:i:s", $time) . " GMT";
        }

        private static function doSendFile($type, $cache_file, $is_gz, $age) {
                $m_time = self::gmdateStr(filemtime($cache_file));
                
                $document = JFactory::getDocument();
                
                header("Content-type: ".self::$contentTypes[$type]."; charset: " . $document->getCharset());

                if (isset($_SERVER['HTTP_IF_MODIFIED_SINCE']) && $_SERVER['HTTP_IF_MODIFIED_SINCE'] == $m_time) {
                        header('Last-Modified: ' . $m_time);
                        header('Content-Length: 0');
                        header("HTTP/1.0 304 Not Modified");
                        return;
                }
                
                jimport('joomla.plugin.plugin');
                JPluginHelper::importPlugin('system', 'jbetolo');
                
                $t_age = self::gmdateStr(time() + $age);

                if ($is_gz) {
                        header("Content-Encoding: gzip");
                }

                header('Last-Modified: ' . self::gmdateStr(filemtime($cache_file)));
                header('Content-Length: ' . filesize($cache_file));
                header('Cache-Control: must-revalidate, max-age=' . $age);
                header('Cache-Control: Public');
                header('Vary: Accept-Encoding');
                header('Expires: ' . $t_age);

                if (!$is_gz && JBETOLO_IS_GZ) {
                        $content = self::gzdecode($cache_file);
                } else {
                        jimport('joomla.filesystem.file');
                        $content = JFile::read($cache_file);
                }

                die($content);
        }

        public static function sendFile($type, $file_name) {
                (isset($file_name) && $file_name != '') || die('jbetolo: sendFile: no file to send.');

                if ($type != 'htaccess') {
                        jimport('joomla.plugin.plugin');
                        JPluginHelper::importPlugin('system', 'jbetolo');

                        $is_gz = JRequest::getBool('gz', false);
                        $is_minify = JRequest::getBool('min', false);
                        $to_file = JBETOLO_CACHE_DIR . $file_name;
                        $age = JRequest::getInt('ag', plgSystemJBetolo::param('cache-age'));
                } else {
                        list($is_gz, $age, $type, $file_name) = explode('_', $file_name);
                        $to_file = JBETOLO_CACHE_DIR . $file_name;
                        $is_gz = (bool) $is_gz;
                }

                if (file_exists($to_file)) {
                        self::doSendFile($type, $to_file, $is_gz, $age);
                } else {
                        die('Restricted access');
                }
        }

        public static function isJ16() {
                return version_compare(JVERSION, '1.6.0', 'ge');
        }

        public static function pluginLocation() {
                return JPATH_PLUGINS.'/system/'.(self::isJ16()?'jbetolo/':'').'jbetolo.php';
        }

        private static function settingAllowed() {
                $app = JFactory::getApplication();
                
                if ($app->getName() == 'administrator') return true;

                JError::raiseError(403, JText::_("ALERTNOTAUTH"));
        }
        
        public static function ping() {
                $user = JFactory::getUser();

                if ($user->gid != 25 && $user->gid != 24) {
                        JError::raiseError(403, JText::_("ALERTNOTAUTH"));
                }                
                
                require_once self::pluginLocation();
                
                return jbetoloHelper::pingUrls();
        }

        public static function resetCache() {
                self::settingAllowed();

                require_once self::pluginLocation();
                
                return jbetoloHelper::resetCache(JRequest::getCmd('app', 'all'));
        }

        public static function resetSetting() {
                self::settingAllowed();
                
                require_once self::pluginLocation();
                
                return jbetoloHelper::resetSetting(JRequest::getCmd('setting', ''));
        }

        public static function saveSetting() {
                self::settingAllowed();
                
                $name = JRequest::getString('name');
                $setting = JRequest::getString('settings');
                
                require_once self::pluginLocation();
                
                return jbetoloHelper::saveSetting($name, $setting);
        }

        public static function smushIt() {
                self::settingAllowed();

                $dir = JRequest::getString('dir');
                $replace = JRequest::getString('replace');
                $recursive = JRequest::getString('recursive');
                $fix = JRequest::getString('fix', '_smushed');

                require_once self::pluginLocation();

                return jbetoloHelper::smushItDirectory($dir, $recursive == 'recursive', $replace == 'replace', $fix);
        }
        
        public static function redirectToPlg($plgName, $folder, $msg = '') {
                $app = JFactory::getApplication();
                
                if (!$app->isAdmin()) return;
                
                $j16 = self::isJ16();

                if ($j16) {
                        $query = "SELECT extension_id AS id FROM #__extensions WHERE type = 'plugin' AND folder = '$folder' AND element = '$plgName' LIMIT 1";
                } else {
                        $query = "SELECT id FROM #__plugins WHERE folder = '$folder' AND element = '$plgName' LIMIT 1";
                }
                
                $db = JFactory::getDBO();
                $db->setQuery($query);
                $plgId = $db->loadResult();
                
                if ($j16) {
                        $url = "index.php?option=com_plugins&task=plugin.edit&extension_id=".$plgId;
                } else {
                        $url = "index.php?option=com_plugins&view=plugin&client=site&task=edit&cid[]=".$plgId;
                }
                
                $app->redirect($url, !empty($msg) ? $msg : JText::_('No component setting, redirected to corresponding plugin setting page'), 'info');
        }        
}
?>