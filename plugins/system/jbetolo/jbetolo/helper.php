<?php
//$Copyright$

defined('_JEXEC') or die('Restricted access');

jimport('joomla.plugin.plugin');
jimport('joomla.filesystem.folder');
jimport('joomla.filesystem.file');

class jbetoloHelper {
        public static function browser($browser) {
                if ($browser == 'ie' || $browser == 'internet explorer') return 'msie';
                else if ($browser == 'ff' || $browser == 'firefox') return 'mozilla';
                return $browser;
        }
        
        public static function logClientsiderError($data, $reset = false) {
                $logFile = JPath::clean(JPATH_SITE.'/'.plgSystemJBetolo::param('clientsideerrorlog'));
                $logSep = "\t";
                $data = implode($logSep, $data);
                if (!$reset && JFile::exists($logFile)) $data = JFile::read($logFile) . "\n" . $data;
                JFile::write($logFile, $data);
        }
        
        public static function loadClientsiderErrorLogger(&$body) {
                $js = plgSystemJBetolo::param('logclientsideerror');
                if (!$js) return;
                $js = JURI::base().'plugins/system/jbetolo/'.(jbetoloHelper::isJ16() ? 'jbetolo/' : '').'clientsideerrorlog/'.$js.'.js';
                $js = '<script type="text/javascript" src="'.$js.'" />';
                jbetoloFileHelper::placeTags($body, $js, 'js', 4);
        }
        
        public static function lazyLoad(&$body, $stage) {
                $js = plgSystemJBetolo::param('lazyload_img');
                
                if (!$js) return;
                
                $loc = JURI::base().'plugins/system/jbetolo/'.(jbetoloHelper::isJ16() ? 'jbetolo/' : '').'lazyload/';
                
                if ($stage == 0 || $stage == 1) {
                        if ($js == 'mootools') {
                                $src = 'LazyLoad.js';
                        } else if ($js == 'jquery') {
                                $src = 'jquery.lazyload.min.js';
                        }

                        $src = '<script type="text/javascript" src="'.$loc.$src.'" />';
                        
                        if ($js == 'mootools') {
                                $src .= '<script type="text/javascript">window.addEvent("domready",function(){ $$("img.lazy").setStyle("display", "inline"); new LazyLoad({realSrcAttribute:"data-original"}); });</script>';
                        } else if ($js == 'jquery') {
                                $src .= '<script type="text/javascript">jQuery.ready(function(){jQuery("img.lazy").show().lazyload({effect:"fadeIn"});});</script>';
                        }
                        
                        jbetoloFileHelper::placeTags($body, '<style>.lazy {display: none;}</style>', 'css', 3);
                        jbetoloFileHelper::placeTags($body, $src, 'js', 4);
                }
                
                if ($stage == 0 || $stage == 2) {
                        if (preg_match_all("#<img(.+)(?:src=[\"\'])([^\"\']+)(?:[\"\'])([^>]+>)#Uim", $body, $matches))  {
                                $excludes = plgSystemJBetolo::param('lazyload_exclude', '');
                                
                                if (!empty($excludes)) $excludes = explode(',', $excludes);
                                
                                foreach ($matches[0] as $i => $tag) {
                                        $f = $matches[2][$i];
                                        
                                        if (jbetoloFileHelper::isFileExcluded($f, $excludes)) continue;
                                        
                                        $orig = jbetoloFileHelper::normalizeCall($f, true, false);
                                        
                                        $stag = 
                                                '<img class="lazy" src="'.$loc.'blank.jpg" data-original="'.$orig.'" '.$matches[1][$i].' '.$matches[3][$i].
                                                '<noscript>'.$tag.'</noscript>';

                                        $body = str_ireplace($tag, $stag, $body);
                                }
                        }
                                
                        return true;
                }
                        
                return true;
        }
        
        public static function sanityCheck() {
                $arr = plgSystemJBetolo::param('files');
                $app = JFactory::getApplication()->getName();
                $types = array('css', 'js');
                $errors = '';
                
                foreach ($types as $type) {
                        $set = $arr[$app][$tmpl][$type];
                        
                        if (empty($set)) continue;
                        
                        foreach ($set as $mediaType => $item) {
                                $merged = JBETOLO_CACHE_DIR . $item['merged'];
                                if (!@filesize($merged)) {
                                        $errors .= 
                                                JText::_('PLG_JBETOLO_SANITY_CHECK_ERROR_FILE_MISSING').' ('. $app . ',' . 
                                                $type . 
                                                ($type == 'css' && !empty($mediaType) ? ',' . $mediaType  : '') . ') ' . 
                                                $item['merged'] . "\r\n"
                                                ;
                                }
                        }
                }
                
                if (!empty($errors)) {
                        $query = 'SELECT email, name FROM #__users WHERE gid = 25 AND sendEmail = 1';
                        $db = JFactory::getDBO();
                        $db->setQuery($query);
                        
                        if (!$db->query()) {
                                JError::raiseError(500, $db->stderr(true));
                                return;
                        }
                        
                        $adminRows = $db->loadObjectList();
                        
                        jimport('joomla.utilities.date');
                        $time = new JDate();
                        $time = $time->toFormat();
                        
                        $subject = JText::sprintf('PLG_JBETOLO_SANITY_CHECK_EMAIL_SUBJECT', $time);
                        $body = JText::sprintf('PLG_JBETOLO_SANITY_CHECK_EMAIL_BODY', $time, $errors);

                        $config = JFactory::getConfig();
                        $from = $config->getValue('mailfrom');
                        $fromName = $config->getValue('fromname');
                        
                        foreach ($adminRows as $adminRow) {
                                JUtility::sendMail($from, $fromName, $adminRow->email, $subject, $body);
                        }       
                        
                        return false;
                }
                
                return true;
        }
        /**
         * pingUrls ping all menu items that are internal to the site and by doing that
         * builds a complete jbetolo cache files
         * 
         * currently pinging is done only at one level thus this can't be used to generate
         * CDN load set and will be part of the improvement
         */
        public static function pingUrls($deep = false) {
                $db = JFactory::getDBO();
                $query = 'SELECT '.$db->nameQuote('id').', link, '.$db->nameQuote('type').' FROM #__menu WHERE published = 1';
                
                $db->setQuery($query);
                
                $links = $db->loadObjectList();
                $processed = array();
                
                $root = str_replace('administrator/', '', JURI::base());
                $uri = JURI::getInstance();
                $uri = clone $uri;
                $internal = clone $uri;
                
                $internal->parse($root);
                
                foreach ($links as $linkRecord) {
                        $link = $linkRecord->link;
                        
                        if (in_array($link, $processed)) continue;
                        
                        $processed[] = $link;
                        
                        if (!$internal->isInternal($link) || empty($link)) continue;
                        
                        $link = $root . $link;
                        
                        if (!$uri->parse($link)) {
                                continue;
                        }
                        
                        $uri->setVar('Itemid', $linkRecord->id);
                        $link = $uri->toString();
                        
                        try {
                                $content = jbetoloFileHelper::makeHTTPRequest($link, 'html');
                        } catch (Exception $exc) {
                                return JText::_('PLG_JBETOLO_PING_FAILED');
                        }
                }
                
                return JText::_('PLG_JBETOLO_PING_SUCCESSFUL');
        }
        
        /**
         * available types are: 
         * 1. main cdn with no type specified
         * 2. images, movies, docs, css, js
         */
        private static function createCDNUri($cdnType = '') {
                if ($cdnType != '') {
                        $cdnType = '_' . $cdnType;
                }
                
                $cdn = trim(plgSystemJBetolo::param('cdn_domain'.$cdnType));
                
                if (!$cdn) {
                        if ($cdnType != '') return JBETOLO_URI_CDN;
                        else return '';
                }
                
                $_cdn = parse_url($cdn);

                $cdn = '';

                if (!isset($_cdn['scheme'])) {
                        $cdn = 'http';
                        $_cdn['host'] = $_cdn['path'];
                        unset($_cdn['path']);
                } else {
                        $cdn = $_cdn['scheme'];
                }

                $cdn .= '://' . $_cdn['host'];

                if (!isset($_cdn['path'])) {
                        $cdn .= '/';
                } else {
                        $_cdn['path'] = preg_replace('#([\/]+)#', '/', '/'.$_cdn['path'].'/');
                        $cdn .= $_cdn['path'];
                }     
                
                return $cdn;
        }
        
        public static function defineConstants() {
                if (defined('JBETOLO_URI_BASE')) return;
                
                $app = JFactory::getApplication();
                $uri = JUri::base();
                $path = JUri::base(true) . '/';

                plgSystemJbetolo::$allowAll = JRequest::getCmd('option') == 'com_jbetolo' && $app->getName() == 'administrator';
                
                if ($app->getName() != 'site') {
                        $uri = str_replace('/administrator', '', $uri);
                        $path = str_replace('/administrator', '', $path);
                }
                
                define('JBETOLO_CDN_MAP', !plgSystemJBetolo::dontJbetolo('cdn'));

                $cdn = '';

                $ownCdn = false;

                if (JBETOLO_CDN_MAP) {
                        $cdn = self::createCDNUri();
                        define('JBETOLO_URI_CDN', $cdn);
                        $cdn = self::createCDNUri('images');
                        define('JBETOLO_URI_CDN_IMAGES', $cdn);
                        $cdn = self::createCDNUri('movies');
                        define('JBETOLO_URI_CDN_MOVIES', $cdn);
                        $cdn = self::createCDNUri('docs');
                        define('JBETOLO_URI_CDN_DOCS', $cdn);
                        $cdn = self::createCDNUri('css');
                        define('JBETOLO_URI_CDN_CSS', $cdn);
                        $cdn = self::createCDNUri('js');
                        define('JBETOLO_URI_CDN_JS', $cdn);
                        
//                        $cdn = plgSystemJBetolo::param('cdn_domain');
//                        $_cdn = parse_url($cdn);
//
//                        $cdn = '';
//
//                        if (!isset($_cdn['scheme'])) {
//                                $cdn = 'http';
//                                $_cdn['host'] = $_cdn['path'];
//                                unset($_cdn['path']);
//                        } else {
//                                $cdn = $_cdn['scheme'];
//                        }
//
//                        $cdn .= '://' . $_cdn['host'];
//
//                        if (!isset($_cdn['path'])) {
//                                $cdn .= '/';
//                        } else {
//                                $_cdn['path'] = preg_replace('#([\/]+)#', '/', '/'.$_cdn['path'].'/');
//                                $cdn .= $_cdn['path'];
//                        }
//
                        define('JBETOLO_CDN_OWN', plgSystemJBetolo::param('cdn_type') == 'pullown');
                } else {
                        define('JBETOLO_URI_CDN', '');
                }

                define('JBETOLO_URI_BASE', $uri);
                define('JBETOLO_PATH', JPATH_SITE.'/plugins/system/'.(jbetoloHelper::isJ16() ? 'jbetolo/' : ''));
                define('JBETOLO_JQUERY', plgSystemJBetolo::param('add_local_jquery_version', 'jquery-1.7.2.min').'.js');
                define('JBETOLO_JQUERY_UI', 'jquery-ui-1.8.22.custom.min.js');
                define('JBETOLO_JQUERY_UI_CSS', 'jquery-ui-1.8.22.custom.css');
                
                if (plgSystemJbetolo::$allowAll) {
                        define('JBETOLO_CACHE_DIR', JPATH_SITE . '/cache/jbetolo/');
                } else {
                        define('JBETOLO_CACHE_DIR', JPATH_CACHE . '/jbetolo/');
                }
                
                define('JBETOLO_FILES_CACHE', JBETOLO_CACHE_DIR . 'jbetolo.files.ini');
                
                if (JBETOLO_CDN_MAP && JBETOLO_CDN_OWN) {
                        jbetoloHelper::setupOwnCDN();
                }

                define('JBETOLO_URI_PATH', $path);
                define("JBETOLO_EMPTYTAG", "_JBETOLO_");
                
                $gz = extension_loaded('zlib') || ini_get('zlib.output_compression');
                
                if ($gz && JBETOLO_CDN_MAP) {
                        $gz = !(bool) plgSystemJBetolo::param('cdn_compress', 0);
                }
                
                define('JBETOLO_IS_GZ', $gz);
                
                define('JBETOLO_IS_MINIFY', 1);
                define('JBETOLO_DEBUG', (bool) plgSystemJBetolo::param('debug_mode'));
                define('JBETOLO_DEBUG_FILENAME', (bool) plgSystemJBetolo::param('debug_mode_filename'));
                
                $user = JFactory::getUser();
                require_once JPATH_ADMINISTRATOR.'/components/com_jbetolo/helpers/helper.php';
                
                if ($app->getName() == 'administrator' && !$user->guest && $gz) {
                        // if files compressed and CDN can't compress provide info
                        if (self::isNginx()) {
                                if (JRequest::getString('JBETOLO_NGINX_NOTICE', '', 'cookie') == '' && jbetoloComponentHelper::inPlg()) {
                                        JFactory::getLanguage()->load('plg_system_jbetolo');
                                        $msg = JText::sprintf(PLG_JBETOLO_CDN_COMPRESS_NGINX, JBETOLO_URI_BASE.'/plugins/system/jbetolo/jbetolo/assets/nginx.conf.txt');
                                        JFactory::getApplication()->enqueueMessage($msg, 'warning');
                                        setcookie('JBETOLO_NGINX_NOTICE', 'YES');
                                }                                                                
                        }
                }
                
                jbetoloFileHelper::createCacheDir();
        }
        
        public static function isApache() {
                $server = strtolower($_SERVER['SERVER_SOFTWARE']);
                return jbetoloHelper::beginWith($server, 'apache');
        }
        
        public static function isNginx() {
                $server = strtolower($_SERVER['SERVER_SOFTWARE']);
                return jbetoloHelper::beginWith($server, 'nginx');
        }
        
        public static function setupOwnCDN() {
                if (jbetoloHelper::defineOwnCDNFolder()) {
                        jbetoloFileHelper::allowRewrite('cdn', JBETOLO_CDN_OWN_FOLDER);
                }
        }

        private static function defineOwnCDNFolder() {
                if (defined('JBETOLO_CDN_OWN_FOLDER')) {
                        return true;
                }
                
                $folder = plgSystemJBetolo::param('cdn_own_folder', false);

                if ($folder) {
                        // remove pre and post slashes
                        $folder = preg_replace('#(^[\/\\\]+|[\/\\\]+$)#', '', $folder);

                        // normalize intermediary slashes
                        $folder = preg_replace('#([\\\]+)#', '/', $folder);
                        $folder = JPATH_SITE . '/' . $folder . '/';
                        
                        if (is_dir($folder)) {
                                define('JBETOLO_CDN_OWN_FOLDER', $folder);
                                return true;
                        }
                }

                if (JBETOLO_CDN_MAP && JBETOLO_CDN_OWN) {
                        JError::raiseError(404, JText::_('PLG_JBETOLO_CDN_FOLDER_ERROR'));
                }

                return false;
        }

        /**
         * @@todo: avoid processing same image twice?
         */
        public static function smushItDirectory($path, $recursive, $replace, $fix) {
                if (empty($path) || !$replace && empty($fix)) {
                        return false;
                }
                
                require_once dirname(__FILE__) . '/class.smushit.php';

                $files = JFolder::files(JPATH_SITE.'/'.$path, '\.(jpg|JPG|jpeg|JPEG|png|PNG|gif|GIF)$', $recursive, true);
                $smush = new SmushIt(true, $replace, $fix, JPATH_SITE.'/'.$path);
                $result = array('success' => 0, 'fail' => 0);
                
                foreach ($files as $file) {
                        $smush->smushFile($file);
                }

                return JText::sprintf('PLG_JBETOLO_SMUSHIT_SUCCESS', $smush->count, ($smush->size - $smush->compressedSize)/1000);
        }

        /**
         * @@todo additional types of media to be supported as per extension list in normalizeTOCDN
         */
        public static function mapCDN(&$html) {
                if (!JBETOLO_CDN_MAP) return false;
                
                $excluded = str_replace('\\', '/', plgSystemJBetolo::param('cdn_components_exclude'));
                $excluded = preg_replace('#([\/]+)#', '/', $excluded);
                $excluded = explode(',', $excluded);
                
                $option = JRequest::getCmd('option');
                
                if (in_array($option, $excluded)) return false;
                
                $excluded = str_replace('\\', '/', plgSystemJBetolo::param('cdn_types_exclude'));
                $excluded = preg_replace('#([\/]+)#', '/', $excluded);
                $excluded = explode(',', $excluded);

                $typeTags = array();

                $types = plgSystemJBetolo::param('cdn_types_images', '');
                if ($types) {
                        $typeTags['img'] = array('src' => 'src', 'ext' => str_replace(array(',', ';'), '|', $types), 'type'=>'images');
                }

                $types = plgSystemJBetolo::param('cdn_types_movies', '');
                if ($types) {
                        $typeTags['embed'] = array('src' => 'src', 'ext' => str_replace(array(',', ';'), '|', $types), 'type'=>'movies');
                }

                $types = plgSystemJBetolo::param('cdn_types_docs', '');
                if ($types) {
                        $typeTags['a'] = array('src' => 'href', 'ext' => str_replace(array(',', ';'), '|', $types), 'type'=>'docs');
                }

                if ((bool) plgSystemJBetolo::param('cdn_types_css', false)) {
                        $typeTags['link'] = array('src' => 'href', 'ext' => array('css'), 'type'=>'css');
                }

                if ((bool) plgSystemJBetolo::param('cdn_types_css', false)) {
                        $typeTags['script'] = array('src' => 'src', 'ext' => array('js'), 'type'=>'js');
                }

                $tags = implode('|', array_keys($typeTags));
                $pat = '#<('.$tags.')\s+([^>]+)\s*(?:/|)\s*>#Uims';

                foreach ($typeTags as $tag => $typeTag) {
                        $typeTags[$tag]['reg'] = "#".$typeTag['src']."=[\"\']([^\"\']+\.(?:".
                                (is_array($typeTag['ext']) ? implode('|', $typeTag['ext']) : $typeTag['ext'])
                        . "))[\"\']+#Uim";
                }

                /**
                 * @@todo: merge regular expressions to improve performance?
                 */

                $values = array();
                
                if (preg_match_all($pat, $html, $m)) {
                        $tags = $m[1];
                        $attrs = $m[2];

                        foreach ($attrs as $a => $attr) {
                                if (preg_match_all($typeTags[$tags[$a]]['reg'], $attr, $m)) {
                                        $map = jbetoloFileHelper::normalizeTOCDN($m[1][0], $typeTags[$tags[$a]]['type']);

                                        if ($map) {
                                                if (jbetoloFileHelper::isOnPath($map, $excluded)) continue;

                                                $map = str_replace($m[1][0], $map, $m[0][0]);
                                                $html = str_replace($m[0][0], $map, $html);
                                        }
                                }
                        }
                }
                
                return true;
        }

        /**
         * Changes that invalidates current cache files
         * (except file changes which is handled in the createFile merging method)
         * is template change and mooversion change
         * where the latter is applicable only to J1.5 versions.
         */
        public static function handleChanges() {
                /*$app = JFactory::getApplication();
                $saved = plgSystemJBetolo::param('templates');
                $curr = $app->getTemplate();
                $appName = $app->getName();

                if (!isset($saved[$appName]) || $saved[$appName] != $curr) {
                        jbetoloHelper::resetCache($appName);
                        $saved[$appName] = $curr;
                        plgSystemJBetolo::param('templates', $saved, 'set');
                }

                if (!jbetoloHelper::isJ16()) {
                        $saved = plgSystemJBetolo::param('mooversion');

                        jimport('joomla.plugin.plugin');
                        $curr = JPluginHelper::getPlugin('system', 'mtupgrade') ? '+1.2' : '1.1';

                        if (!isset($saved) || $saved != $curr) {
                                jbetoloHelper::resetCache();
                                plgSystemJBetolo::param('mooversion', $curr, 'set');
                        }
                }*/
        }

        public static function isJ16() {
                return version_compare(JVERSION, '1.6.0', 'ge');
        }

        public static function getMetaData($name, $isField = true, $attr = '') {
                jimport('joomla.application.helper');
                $xml = simplexml_load_file(JApplicationHelper::getPath('plg_xml', 'system'.DS.'jbetolo'));

                if ($isField) {
                        $path = jbetoloHelper::isJ16() ? "config/fields/fieldset/field" : "params/param";
                        $result = $xml->xpath($path."[@name='".$name."']");

                        if ($attr) {
                                $result = $result[0];
                                $result = $result->attributes();
                                $result = (string) $result[$attr];
                        }
                } else {
                        $result = $xml->xpath($name);
                        $result = (string) $result[0];
                }

                return $result;
        }

        public static function getVersion() {
                return jbetoloHelper::getMetaData('version', false);
        }

        private static function settingsLocation($settingName = '', $isNew = false) {
                static $loc;

                if (!isset($loc)) {
                        $loc = jbetoloHelper::getMetaData('predefined_settings', true, 'directory') . '/';
                        $loc = jbetoloFileHelper::normalizeCall($loc, true);
                }

                if ($loc) {
                        if ($settingName) {
                                $file = $loc . $settingName . '.ini';

                                if (!$isNew && JFile::exists($file)) {
                                        return $file;
                                } else if ($isNew && !JFile::exists($file)) {
                                        return $file;
                                }
                        } else {
                                return $loc;
                        }
                }

                return false;
        }

        /**
         * default value is provided in the format version:predfined-setting-name
         * and several such defintions can be provided comma separated
         *
         * if version is left out it will be assumed to apply to all setups
         *
         * note: since joomla 1.5 differs in parameter / field setup of manifest
         * file please make sure to include applicable value in the correct instance
         */
        public static function loadDefaultPredefinedSetting() {
                $setting = jbetoloHelper::getMetaData('predefined_settings', true, 'default');
                $settings = explode(',', $setting);
                $setting = '';

                foreach ($settings as $set) {
                        if (empty($set) || !empty($setting)) break;

                        $set = explode(':', $set);

                        if (count($set) == 1) {
                                 $setting = $set[0];
                        } else {
                                $ver = $set[0];
                                $jver = JVERSION;

                                if (substr_count($ver, '.') == 1) {
                                        $jver = substr($jver, 0, strrpos($jver, '.'));
                                }

                                if (version_compare($jver, $ver, 'eq')) {
                                        $setting = $set[1];
                                }
                        }
                }

                if (!empty($setting)) jbetoloHelper::resetSetting($setting);
        }

        public static function resetSetting($setting) {
                if (empty($setting)) return false;

                jimport('joomla.registry.registry');
                $reg = new JRegistry();

                $setting = self::settingsLocation($setting);
                
                if ($setting) {
                        $setting = JFile::read($setting);
                        $reg->loadINI($setting);
                        plgSystemJBetolo::param('', $reg, 'set');
                        return true;
                }

                return false;
        }

        public static function saveSetting($name, $setting) {
                $file = jbetoloHelper::settingsLocation($name, true);

                if (JFile::exists($file)) return false;

                $header =
                        ';This is an auto-generated setting of jbetolo, a Joomla! site optimization plugin.' . "\r\n" .
                        ';jbetolo Version: ' . jbetoloHelper::getVersion() . "\r\n" .
                        ';Generated time: ' . date('Y-m-d H:i:s') .  "\r\n"
                        ;

                $setting = $header . $setting;
                $fp = fopen($file, "w");
                fwrite($fp, $setting);
                fclose($fp);

                return true;
        }

        /**
         * resets cache as a result of user invoked call from admin
         * by removing the jbetolo cache directory
         *
         * if frontend cache is removed and own Pull CDN is enabled
         * we also clear the cache in own Pull CDN folder
         */
        public static function resetCache($app = 'all') {
                if ($app == 'all') {
                        plgSystemJBetolo::param('files', '', 'set');
                } else {
                        $param = plgSystemJBetolo::param('files');
                        unset($param[$app]);
                        
                        plgSystemJBetolo::param('files', $param, 'set');
                }
                
                if ($app == 'all' || $app == 'site') {
                        $loc = JBETOLO_CACHE_DIR;

                        if (JFolder::exists($loc)) {
                                JFolder::delete($loc);
                        }

                        if (jbetoloHelper::defineOwnCDNFolder()) {
                                $loc = JBETOLO_CDN_OWN_FOLDER . 'cache/jbetolo';

                                if (JFolder::exists($loc)) {
                                        JFolder::delete($loc);
                                }
                        }
                }

                if ($app == 'all' || $app == 'administrator') {
                        $loc = JPATH_ADMINISTRATOR . '/cache/jbetolo';
                        
                        if (JFolder::exists($loc)) {
                                JFolder::delete($loc);
                        }
                }

                return JText::_('PLG_SYSTEM_JBETOLO_CACHE_CLEARED');
        }

        public static function extractAttributes($tag) {
                $attr = '';

                if (preg_match("|<link[^>]+media=[\"\']([^\"\']+)[\"\'][^>]+[/]?>((.*)</[^>]+>)?|Ui", $tag, $m)) {
                        $media = explode(',', strtolower($m[1]));
                        sort($media);
                        $attr .= implode(',', $media);
                } else {
                        $attr .= '';
                }

                if (preg_match("|<link[^>]+title=[\"\']([^\"\']+)[\"\'][^>]+[/]?>((.*)</[^>]+>)?|Ui", $tag, $m)) {
                        $media = explode(',', strtolower($m[1]));
                        sort($media);
                        $attr .= '%%%'.$m[1];
                }

                return $attr;
        }

        public static function returnAttributes($attr) {
                if (!$attr) return '';
                
                $attr = explode('%%%', $attr);
                
                return ' media="' . $attr[0] . '"' . (count($attr) > 1 ? ' title="' . $attr[1] . '" ' : '');
        }

        public static function clientEncoding() {
                if (!isset($_SERVER['HTTP_ACCEPT_ENCODING'])) {
                        return false;
                }

                $encoding = false;

                if (false !== strpos($_SERVER['HTTP_ACCEPT_ENCODING'], 'gzip')) {
                        $encoding = 'gzip';
                }

                if (false !== strpos($_SERVER['HTTP_ACCEPT_ENCODING'], 'x-gzip')) {
                        $encoding = 'x-gzip';
                }

                return $encoding;
        }

        public static function getArrayValues($list, $key) {
                return array_map(create_function('$el', 'return $el["' . $key . '"];'), $list);
        }

        public static function getArrayKey($list, $value) {
                foreach ($list as $key => $val) {
                        if (!is_array($val)) {
                                if ($val == $value) return $key;
                        } else {
                                if (in_array($value, $val)) {
                                        return $key;
                                }
                        }
                }

                return false;
        }

        public static function filterArrayValues($list, $keys, $values) {
                $result = array();
                $isValueArr = is_array($values);
                $isKeyArr = is_array($keys);

                foreach ($list as $el) {
                        foreach ($el as $key => $value) {
                                if (!$isKeyArr && $key == $keys || in_array($key, $keys)) {
                                        if (!$isValueArr && $values == $value || in_array($value, $values)) {
                                               $result[] = $el;
                                        }
                                }
                        }
                }

                return $result;
        }

        public static function eatWhiteSpace($cont) {
                return preg_replace("#(^\s*($|))#m", "", $cont);
        }

        public static function replaceTags(&$subject, $tags, $replaceWith = "\n", $indexes, $before = '', $after = '') {
                if (!empty($before) && is_string($before)) $before = explode(',', $before);
                
                if (!empty($after) && is_string($after)) $after = explode(',', $after);
                
                $toMove = array('before' => array(), 'after' => array());
                
                foreach ($tags as $s => $tag) {
                        if ($tag == JBETOLO_EMPTYTAG) {
                                // external resource to be left as is but lets see if it should be moved to
                                // any of the designated positions
                                if (jbetoloFileHelper::isIncluded($indexes[$s]['src'], $before)) {
                                        $subject = str_ireplace($indexes[$s]['tag'], $replaceWith, $subject);
                                        $toMove['before'][] = $indexes[$s]['tag'];
                                }
                                
                                if (jbetoloFileHelper::isIncluded($indexes[$s]['src'], $after)) {
                                        $subject = str_ireplace($indexes[$s]['tag'], $replaceWith, $subject);
                                        $toMove['after'][] = $indexes[$s]['tag'];
                                }
                                
                                continue;
                        }
                        
                        $subject = str_ireplace($tag, $replaceWith, $subject);
                }
                
                return $toMove;
        }

        public static function endWith($str, $end) {
                return $end != '' && substr($str, -strlen($end)) == $end;
        }

        public static function beginWith($str, $begin) {
                return $begin != '' && strpos($str, $begin) === 0;
        }

        public static function timer($continue_measuring = false, $print = false, &$body = '') {
                static $time;

                list($usec, $sec) = explode(" ", microtime());
                $ctime = ((float) $usec + (float) $sec);

                if (isset($time)) {
                        if ($print) {
                                $content = 'Execution time is: '.($ctime - $time);
                                
                                if (!empty($body)) {
                                        $body = preg_replace('#(<body[^>]*>)#', '$1'.$content, $body, 1);
                                } else {
                                        echo $content;
                                }
                        } else {
                                return ($ctime - $time);
                        }

                        if (!$continue_measuring) $time = null;
                } else {
                        $time = $ctime;
                }
        }
}

class jbetoloJS {
        public static function build($files, $is_generate_file = true, $key = 'main') {
                if (is_string($files)) $files = array($files);

                $data = array();
                $jqueryNoConflict = plgSystemJBetolo::param('js_jquery_no_conflict');
                $jqFile = plgSystemJBetolo::$jquery;

                foreach ($files as $f => $file) {
                        $content = jbetoloFileHelper::getContent($file, 'js');

                        if ($jqueryNoConflict && jbetoloHelper::endWith($file, $jqFile)) {
                                $content .= "\n jQuery.noConflict();\n";
                        }

                        $data[] = array('content' => (JBETOLO_DEBUG || JBETOLO_DEBUG_FILENAME ? "/** JBF: $file **/\n" : '') . $content, 'file' => $file);
                }

                $res = '';

                if ($is_generate_file) {
                        $res = jbetoloFileHelper::createFileName($files, 'js');
                        $res['srcs'] = $files;
                        jbetoloFileHelper::writeToFile($res['merged'], $data, 'js');
                        $res = array('main' => $res);
                } else {
                        $res = jbetoloHelper::getArrayValues($data, 'content');
                        $res = implode(chr(13), $res);
                }

                return $res;
        }
        
        public static function modifyInlineScripts(&$body) {
                $js_move_inline = plgSystemJBetolo::param('js_move_inline');
                $js_add_inline = plgSystemJBetolo::param('js_add_inline');
                $js_remove_inline = plgSystemJBetolo::param('js_remove_inline');

                if ($js_move_inline == 1 && empty($js_add_inline) && empty($js_remove_inline)) {
                        return;
                }
                
                if (!empty($js_remove_inline)) {
                        $js_remove_inline = explode('#jsbr#', $js_remove_inline);
                }

                $scriptRegex = "/<script\s*(?(?<!src=)[^>])*>(.*?)<\/script>/ims";

                preg_match_all($scriptRegex, $body, $matches);

                $scripts = '';
                
                $asis = plgSystemJBetolo::param('js_inline_dont_move', '');
                
                // we shoudln't move away any script block dependent of the position it is placed in such as document.write
                $asis .= (empty($asis) ? '' : ',') . 'document.write';
                
                // If email cloaking plugin is enabled we shouldn't move away the code rendered by it
                if (JPluginHelper::isEnabled('content', 'emailcloak')) {
                        $asis .= (empty($asis) ? '' : ',') . 'var prefix';
                }
                
                $asis = empty($asis) ? false : explode(',', $asis);
                $removes = array();
                
                foreach ($matches[0] as $m => $match) {
                        if (!empty($js_remove_inline)) {
                                $found = false;
                                
                                foreach ($js_remove_inline as $js_remove) {
                                        $js_remove = trim($js_remove);
                                        
                                        if (empty($js_remove)) continue;
                                        
                                        if (stripos($js_remove, plgSystemJBetolo::EXCLUDE_REG_PREFIX) === 0) {
                                                $js_remove = str_replace(plgSystemJBetolo::EXCLUDE_REG_PREFIX, '', $js_remove);
                                                $found = preg_match('#'.$js_remove.'#i', $match);
                                        } else if (stripos($js_remove, plgSystemJBetolo::DELETE_REG_START_PREFIX) === 0) {
                                                $js_remove = str_replace(plgSystemJBetolo::DELETE_REG_START_PREFIX, '', $js_remove);
                                                
                                                if (stripos($js_remove, plgSystemJBetolo::DELETE_REG_END_PREFIX)) {
                                                        $js_remove = explode(plgSystemJBetolo::DELETE_REG_END_PREFIX, $js_remove);
                                                        
                                                        if (($js_remove[0] = stripos($match, $js_remove[0])) !== false) {
                                                                if (($js_remove[1] = stripos($match, $js_remove[1])) !== false) {
                                                                        if ($js_remove[1] > $js_remove[0]) {
                                                                                $found = true;
                                                                                $match = substr($match, 0, $js_remove[0]) .
                                                                                        substr($match, $js_remove[1]);
                                                                        }
                                                                }
                                                        }
                                                } else if (stripos($match, $js_remove) !== false) {
                                                        $found = true;
                                                        $match = $js_remove;
                                                }
                                        } else if (stripos($match, $js_remove) !== false) {
                                                $found = true;
                                                $match = $js_remove;
                                        }
                                        
                                        if ($found) break;
                                }
                                
                                if ($found) {
                                        $removes[] = $match;
                                        continue;
                                }
                        }
                        
                        if (strpos(plgSystemJBetolo::$conditionalTags, $match) !== false) continue;
                        
                        if ($asis) {
                                $found = false;
                                
                                foreach ($asis as $as) {
                                        $as = trim($as);
                                        
                                        if (strpos($as, plgSystemJBetolo::EXCLUDE_REG_PREFIX) === 0) {
                                                $as = str_replace(plgSystemJBetolo::EXCLUDE_REG_PREFIX, '', $as);
                                                $found = preg_match('#'.$as.'#i', $match);
                                        } else {
                                                $found = strpos($match, $as) !== false;
                                        }
                                        
                                        if ($found) break;
                                }
                                
                                if ($found) continue;
                        }
                        
                        if ($found) $removes[] = $match;
                        
                        $scripts .= $match . "\n";
                }
                
                if (!empty($removes)) {
                        $body = str_replace($removes, '', $body);
                }
                
                if (!empty($js_add_inline)) {
                        $scripts .= $js_add_inline . "\n";
                }

                if ($scripts) {
                        jbetoloFileHelper::placeTags($body, $scripts, 'js', $js_move_inline);
                }
        }

        public static function setJqueryFile($srcs, $excls) {
                if (isset(plgSystemJBetolo::$jquery))
                        return;

                $jquery = trim(plgSystemJBetolo::param('js_jquery'));

                if (isset($jquery)) {
                        $jq = jbetoloFileHelper::fileInArray($jquery, $srcs);

                        if ($jq) {
                                plgSystemJBetolo::$jquery = $jq[1];
                        } else {
                                $jq = jbetoloFileHelper::fileInArray($jquery, $excls);

                                if ($jq) {
                                        plgSystemJBetolo::$jquery = $jq[1];
                                }
                        }
                }
        }
}

class jbetoloCSS {
        private static $contents, $files, $root, $cdn_merged, $deleteSrcs;
        
        private static function replace(&$css) {
                $from = trim(plgSystemJBetolo::param('css_replace_from'));

                if (empty($from)) return;

                $from = explode("\n", $from);

                $to = trim(plgSystemJBetolo::param('css_replace_to'));
                $to = explode("\n", $to);

                if (count($to) != count($from)) $to = array_fill(0, count($from), $to[0]);

                $css = str_replace($from, $to, $css);
        }

        public static function build($files, $attrs, $is_generate_file = true, $index = array()) {
                if (is_string($files)) $files = array($files);

                $categorized = array();
                self::$cdn_merged = JBETOLO_CDN_MAP && (bool) plgSystemJBetolo::param('cdn_merged');
                $deleteSrcs = plgSystemJBetolo::param('delete');
                self::$deleteSrcs = $deleteSrcs ? explode(',', $deleteSrcs) : array();

                foreach ($files as $f => $file) {
                        self::$root = null;
                        self::$contents = self::$files = array();
                        self::load($file);
                        $attr = '';
                        
                        if (!empty($index)) {
                                foreach ($index as $ind) {
                                        if ($ind['src'] == $file) {
                                                $attr = $ind['attr'];
                                                break;
                                        }
                                }
                        }

                        if (empty($attr) && !empty($attrs)) $attr = $attrs[$f];

                        if (is_array($attr)) $attr = implode(',', $attr);

                        if (!isset($categorized[$attr])) {
                                $categorized[$attr] = array('files' => array(), 'contents' => array(), 'srcs' => array());
                        }

                        if (is_array(self::$files)) {
                                $categorized[$attr]['files'] = array_merge($categorized[$attr]['files'], self::$files);
                                $categorized[$attr]['contents'] = array_merge($categorized[$attr]['contents'], self::$contents);
                        } else {
                                $categorized[$attr]['files'][] = self::$files;
                                $categorized[$attr]['contents'][] = self::$contents;
                        }

                        $categorized[$attr]['srcs'] = array_merge($categorized[$attr]['srcs'], array($file));
                }

                $res = array();

                if ($is_generate_file) {
                        foreach ($categorized as $attr => $recs) {
                                $res[$attr] = jbetoloFileHelper::createFileName($recs['files'], 'css');
                                $res[$attr]['srcs'] = $recs['srcs'];
                                jbetoloFileHelper::writeToFile($res[$attr]['merged'], $recs['contents'], 'css');
                        }
                } else {
                        $res = jbetoloHelper::getArrayValues($categorized['main']['contents'], 'content');
                        $res = implode(chr(13), $res);
                }

                self::$contents = self::$files = null;

                return $res;
        }

        private static function load($file, $is_recursive_call = false) {
                $content = jbetoloFileHelper::getContent($file, 'css');
                $base = jbetoloFileHelper::getDirectoryName($file);
                if (empty(self::$root)) self::$root = $base;
                $content = self::buildPath($content, $base);

                $content = preg_replace_callback(
                                '#^[\s]*?\@import\s*?(?:url\()?[\'\"]?([^\'\"\()]+)[\'\"]?\)?;#im',
                                'jbetoloCSS::_load',
                                $content
                );

                $content = (JBETOLO_DEBUG || JBETOLO_DEBUG_FILENAME ? "/** JBF: $file **/\n" : '') . $content;
                
                self::replace($content);

                self::$contents[] = array('file' => $file, 'content' => $content);
                self::$files[] = $file;

                if ($is_recursive_call) {
                        return '';
                }
        }

        private static function _load($matches) {
                $file = jbetoloFileHelper::normalizeCall($matches[1]);
                $file = (array) $file;
                foreach (self::$deleteSrcs as $d) {
                        $_d = jbetoloFileHelper::normalizeCall($d);

                        if ($_d !== false) {
                                $d = $_d;
                        }

                        $f = jbetoloFileHelper::fileInArray($d, $file);

                        if ($f) return '';
                }                
                return self::load($matches[1], true);
        }

        private static function buildPath($content, $base) {
                if (preg_match_all('/@import\s+(?:url\s*(\(|))\s*([\'\"]?)([^\'\"\()]+)(?(1)\)|)(?(2)\2|)\;/i', $content, $matches)) {
                        foreach ($matches[1] as $m => $match) {
                                $path = jbetoloFileHelper::normalizeCall($base . '/' . $matches[3][$m], true, false);
                                $content = str_replace($matches[0][$m], '@import url("' . $path . '");', $content);
                        }
                }
                
                /**
                 * @@todo: simplify and document regexps
                 */
                if (preg_match_all('/url\([\'"]?(?![a-z]+:|\/+)([^\'"\?\#)]+)([^\'")]+)?[\'"]?\)/i', $content, $matches)) {
                        foreach ($matches[1] as $m => $match) {
                                if (self::$cdn_merged) {
                                        $path = jbetoloFileHelper::normalizeTOCDN($base . '/' . $match);

                                        if (!$path) {
                                                $path = jbetoloFileHelper::normalizeTOCDN(self::$root . '/' . $match);
                                        }
                                } else {
                                        $abs = plgSystemJBetolo::param('css_map_resources_absolute', false);
                                        
                                        $path = jbetoloFileHelper::normalizeCall($base . '/' . $match, $abs, false);

                                        if (!$path) {
                                                $path = jbetoloFileHelper::normalizeCall(self::$root . '/' . $match, $abs, false);
                                        }
                                }
                                
                                $path = self::processResources($path);
                                
                                if ($path) {
                                        $path .= $matches[2][$m];
                                        $content = str_replace($matches[0][$m], 'url(' . $path . ')', $content);
                                }
                        }
                }

                return $content;
        }

        private static function processResources($resource) {
                static $enabled;
                
                if (!isset($enabled)) {
                        $enabled = array(
                            'compress' =>  explode(',', plgSystemJBetolo::param('css_compress_resources')),
                            'datauri' =>  explode(',', plgSystemJBetolo::param('css_datauri')),
                            'datauri-max' => (int) plgSystemJBetolo::param('css_datauri_max', 0),
                            'datauri-files' => explode(',', plgSystemJBetolo::param('css_datauri_files'))
                        );

                        $app = JFactory::getApplication()->getName();
                        $allowedIn = plgSystemJBetolo::param('css_datauri_allow_in');

                        if ($app != $allowedIn && $allowedIn != 'all') {
                                $enabled['datauri'] = array();
                        }
                }

                $types = array('font' => array('eot', 'ttf', 'svg', 'otf', 'woff'));

                $ext = jbetoloFileHelper::getExt($resource);
                $type = jbetoloHelper::getArrayKey($types, $ext);

                $processed = false;

                if (JBETOLO_IS_GZ && in_array($ext, $enabled['compress'])) {
                        $path = jbetoloFileHelper::normalizeCall($resource, true, true);
                        $file_name = basename($path);
                        $cache_file = JBETOLO_CACHE_DIR . $file_name;

                        if (!JFile::exists($cache_file)) {
                                if ($type == 'font') {
                                        $data = file_get_contents($path);
                                        $processed = jbetoloFileHelper::writeToFile($file_name, $data, 'font', true);
                                }
                        }

                        $resource = jbetoloFileHelper::getServingURL($file_name, $type, true);
                }

                if (!$processed) {
                        $included = is_array($enabled['datauri-files']) && jbetoloFileHelper::isFileIncluded($resource, $enabled['datauri-files']);

                        if (in_array($ext, $enabled['datauri']) || $included) {
                                $path = jbetoloFileHelper::normalizeCall($resource, true, true);
                                $max = (int) $enabled['datauri-max'];

                                if ($path && is_file($path) && ($included || ($max <= 0 || filesize($path) <= $max))) {
                                        $contentType = $type == 'font' ? 'application/octet-stream' : 'image/'.$ext;

                                        $resource = '"data:'.$contentType.';base64,'.base64_encode(file_get_contents($path)).'"';
                                        $processed = true;
                                }
                        }
                }

                return $resource;
        }
}

class jbetoloFileHelper {
        public static function getExt($file) {
                return JFile::getExt($file);
                //return substr($file, strripos($file, '.') + 1);
        }
        public static function getServingURL($file, $type, $gz, $age = null) {
                if (JBETOLO_CDN_MAP && (bool) plgSystemJBetolo::param('cdn_merged')) {
                        return self::normalizeTOCDN(JBETOLO_CACHE_DIR.$file, $type);
                } else if ($gz) {
                        if (self::allowRewrite('serve')) {
                                $file = $gz.'_'.$age.'_'.$type.'_'.$file;
                                return JUri::base() . 'cache/jbetolo/' . $file;
                        } else {
                                return JUri::base() . 'index.php?option=com_jbetolo&amp;task=serve&amp;gz=1&amp;file=' . $file . '&amp;type=' . $type . ($age ? '&amp;ag=' . $age : '');
                        }
                } else {
                        return self::normalizeCall(JBETOLO_CACHE_DIR.$file, true, false);
                }
        }

        public static function customOrder($files, $type, $index = null) {
                $customOrder = plgSystemJBetolo::param($type . '_custom_order', '');
                $orderedSrcs = array();
                
                if ($type == 'js') {
                        $customOrder = explode(',', $customOrder);
                        $moos = array();
                        $_files = !empty($index) ? $index : $files;

                        foreach ($_files as $file) {
                                if (preg_match('#mootools-(core|more){1}(\-[\d\.]+){0,1}\.js$#i', $file) || preg_match('#mootools\.js$#i', $file)) {
                                        $b = basename($file);
                                        $f = jbetoloFileHelper::fileInArray($b, $customOrder);
                                        
                                        if ($f) unset($customOrder[$f[0]]);
                                        
                                        $moos[] = $file;
                                        
                                        if (strpos($file, 'mootools-core') !== false) array_unshift($moos, $file);
                                        else $moos[] = $file;
                                }
                        }
                        
                        $moos = array_unique($moos);
                        
                        $customOrder = implode(',', $customOrder);
                        $jquery = '';
                        
                        if (plgSystemJBetolo::param('add_local_jquery', 0)) {
                                $jquery = (empty($moos) ? '' : ',') . JBETOLO_JQUERY;
                                
                                if (plgSystemJBetolo::param('add_local_jquery_ui', 0)) {
                                        $jquery .= ',' . JBETOLO_JQUERY_UI;
                                }
                                
                        }
                        $customOrder = implode(',', $moos).$jquery.($customOrder ? ','  . $customOrder : '');
                }
                
                if (!empty($customOrder)) {
                        $customOrder = explode(',', $customOrder);
                        $lastSrcs = array();
                        
                        foreach ($customOrder as $co) {
                                $isLast = jbetoloHelper::endWith($co, '*');
                                
                                if ($isLast) $co = str_replace('*', '', $co);
                                
                                $_co = jbetoloFileHelper::normalizeCall($co);

                                if ($_co !== false) $co = $_co;

                                $f = jbetoloFileHelper::fileInArray($co, $index ? $index : $files);
                                
                                if ($f) {
                                        if ($isLast) {
                                                $lastSrcs[] = $index ? $files[$f[1]] : $f[1];
                                        } else {
                                                $orderedSrcs[] = $index ? $files[$f[1]] : $f[1];
                                        }
                                        
                                        unset($files[$f[$index ? 1 : 0]]);
                                }
                        }
                        
                        $orderedSrcs = array_merge($orderedSrcs, $index ? array_values($files) : $files);
                        
                        if (!empty($lastSrcs)) $orderedSrcs = array_merge($orderedSrcs, $lastSrcs);
                        
                        $orderedSrcs = array_filter($orderedSrcs);
                } else {
                        $orderedSrcs = $files;
                }
                
                return $orderedSrcs;
        }

        private static function areFilesChanged($files) {
                if (plgSystemJBetolo::param('dont_stat', 0)) return false;
                
                foreach ($files as $file) {
                        $f = jbetoloFileHelper::normalizeCall($file['file'], true);
                        $curr_f_time = filemtime($f);

                        if ($file['time'] < $curr_f_time) {
                                return true;
                        }
                }

                return false;
        }

        public static function fileInArray($key_file, $files, $is_key_search = false) {
                if ($is_key_search) {
                        $files = array_keys($files);
                }

                foreach ($files as $f => $file) {
                        if (jbetoloHelper::endWith($file, $key_file)) {
                                return array($f, $file);
                        }
                }

                return false;
        }

        /**
         * @param $file_name that will end_with any of the given files in $exclude_list
         */
        public static function isFileExcluded($file_name, $exclude_list) {
                return self::isIncluded($file_name, $exclude_list);
        }

        public static function isFileIncluded($file_name, $include_list) {
                return self::isIncluded($file_name, $include_list);
        }

        public static function isIncluded($el, $list) {
                if (empty($list)) return false;

                if (!is_array($list)) $list = explode(',', $list);

                foreach ($list as $lel) {
                        if (strpos($lel, plgSystemJBetolo::EXCLUDE_REG_PREFIX) === 0) {
                                $lel = preg_replace('#^'.plgSystemJBetolo::EXCLUDE_REG_PREFIX.'#', '', $lel);
                                if (preg_match('#'.$lel.'#', $el)) return true;
                        } else if (jbetoloHelper::endWith($el, $lel)) {
                                return true;
                        }
                }

                return false;
        }

        public static function isOnPath($file, $paths) {
                if (!$paths || !$file) return false;

                if (!is_array($paths)) $paths = explode(',', $paths);

                foreach ($paths as $path) {
                        if ($path && strpos($file, $path) !== false) {
                                return true;
                        }
                }

                return false;
        }

        public static function createCacheDir() {
                if (!JFolder::exists(JBETOLO_CACHE_DIR)) {
                        if (JFolder::create(JBETOLO_CACHE_DIR)) {
                                $content = "<html><body bgcolor='#FFFFFF'></body></html>";
                                JFile::write(JBETOLO_CACHE_DIR . 'index.html', $content);
                        }

                        if (!JBETOLO_CDN_MAP) {
                                self::allowRewrite('create', JBETOLO_CACHE_DIR);
                        }
                }
                
                // if files compressed and CDN can't compress, provide correct header
                if (JBETOLO_CDN_MAP && JBETOLO_IS_GZ && !(bool) plgSystemJBetolo::param('cdn_compress', 0)) {
                        $server = strtolower($_SERVER['SERVER_SOFTWARE']);

                        if (strpos($server, 'apache') === 0) {
                                $patchFile = dirname(__FILE__) . '/assets/htaccess_cdn_content_encoding.txt';
                                JFile::copy($patchFile, JBETOLO_CACHE_DIR.'.htaccess');
                        }
                }                
        }

        public static function allowRewrite($task, $dst = '') {
                if ((bool) plgSystemJBetolo::param('htaccess')) {
                        $app = JFactory::getApplication()->getName();
                        
                        if ($app == 'site') {
                                if ($task == 'serve') {
                                        return JFile::exists(JBETOLO_CACHE_DIR . '.htaccess');
                                } else if ($task == 'create' || $task == 'cdn') {
                                        $dstDir = $dst;
                                        $dst .= '.htaccess';

                                        if (!JFile::exists($dst)) {
                                                $src = dirname(__FILE__). '/assets/htaccess_' . $task.'.txt';

                                                if (JFile::exists($src)) {
                                                        $content = JFile::read($src);

                                                        if ($task == 'cdn') {
                                                                $content = str_replace('HTTP_HOST_REPLACE', JBETOLO_URI_CDN, $content);
                                                                jbetoloFileHelper::createCDNPuller($dstDir);
                                                        } else {
                                                                $uri = JURI::getInstance();
                                                                $replacement = $uri->toString(array('scheme', 'user', 'pass', 'host', 'port', 'path'));
                                                                $replacement = str_replace('index.php', '', $replacement);
                                                                $content = str_replace('HTTP_HOST_REPLACE', $replacement, $content);
                                                        }

                                                        JFile::write($dst, $content);

                                                        return true;
                                                }
                                        }
                                }
                        }
                }

                return false;
        }

        public static function createCDNPuller($dst) {
                $src = dirname(__FILE__) . '/assets/puller.php';

                if (JFile::exists($src)) {
                        copy($src, $dst.'puller.php');
                }

                $src = dirname(__FILE__) . '/assets/jbetolo.cdn.conf';

                if (JFile::exists($src)) {
                        $content = JFile::read($src);
                        $content = str_replace('%JPATH_SITE%', JPATH_SITE, $content);
                        $content = str_replace('%JBETOLO_CDN_OWN_FOLDER%', JBETOLO_CDN_OWN_FOLDER, $content);
                        $content = str_replace('%JBETOLO_URI_CDN%', JBETOLO_URI_CDN, $content);

                        JFile::write($dst.'jbetolo.cdn.conf', $content);
                }
        }

        public static function createFileName($src_files, $type) {
                $res = array();

                foreach ($src_files as $s => $src_file) {
                        list($src_file, ) = explode('?', $src_file);
                        $f = jbetoloFileHelper::normalizeCall($src_file, true);
                        $t = filemtime($f);
                        $res[] = array('file' => $src_file, 'time' => $t);
                        $src_files[$s] = $src_file.$t;
                }

                array_multisort($src_files, SORT_ASC, SORT_STRING);
                $gz = JBETOLO_IS_GZ && plgSystemJBetolo::param($type . '_gzip') ? '-gz' : '';
                $minify = plgSystemJBetolo::param($type . '_minify', 0) ? '-min' : '';
                $cdn = JBETOLO_CDN_MAP ? '-cdn' : '';
                $fn = JBETOLO_DEBUG_FILENAME ? '-fn' : '';
                $browsers = plgSystemJBetolo::param('exclude_browsers');
                if ($browsers) $browsers = '-b'.$browsers;
                
                $key = md5(implode($src_files) . $minify . $gz . $cdn . $fn . $browsers);
                $key = JFile::makeSafe($key);

                return array('merged' => $key . "." . $type, 'parts' => $res);
        }

        public static function normalizeTOCDN($call, $type = '') {
                $cdn = JBETOLO_URI_CDN;
                
                if (!$type) {
                        $ext = jbetoloFileHelper::getExt($call);
                        $maps = array(
                                'images' => array('png', 'jpg', 'gif', 'jpeg', 'tiff', 'bmp', 'psd', 'tif', 'ai', 'drw', 'svg', 'ico'),
                                'movies' => array('mov', 'avi', 'flv', 'aif', 'mp3', 'mpa', 'ra', 'wma', 'wav', 'swf', 'vob', 'wmv'),
                                'docs' => array('odt', 'pdf', 'doc', 'docx', 'txt', 'rtf', 'eps', 'ps', 'xls', 'xlsx', 'zip', 'gz', 'tar', 'log', 'dat', 'xml', 'pps', 'ppt', 'pptx', 'epub', 'indd', 'pct', 'mdb', 'sql'),
                                'css' => array('css'),
                                'js' => array('js')
                        );
                        $found = false;
                        $t = '';
                        foreach ($maps as $t => $map) {
                                if (in_array($ext, $map)) {
                                       $found = true;
                                       break;
                                }
                        }
                        if ($found) $type = $t;
                }
                
                if ($type == 'images') {
                        $cdn = JBETOLO_URI_CDN_IMAGES;
                } else if ($type == 'movies') {
                        $cdn = JBETOLO_URI_CDN_MOVIES;
                } else if ($type == 'docs') {
                        $cdn = JBETOLO_URI_CDN_DOCS;
                } else if ($type == 'css') {
                        $cdn = JBETOLO_URI_CDN_CSS;
                } else if ($type == 'js') {
                        $cdn = JBETOLO_URI_CDN_JS;
                }
                
                return jbetoloFileHelper::normalizeCall($call, true, false, true, '', false, $cdn);
        }

        /**
         * call is re-formatted (regardless of http or file path) to be:
         * 1. relative to the JPATH_SITE or JUri::base() (or absolute path, c.f. $is_absolute)
         * 2. file path with directory separator
         */
        public static function normalizeCall(
                $call, 
                $is_absolute = false, 
                $is_file_path = true, 
                $maintain_query_string = true, 
                $type = null, 
                $passPHP = false, 
                $cdn = false
                ) {
                $path = $call;
                if (jbetoloHelper::beginWith($path, JBETOLO_URI_BASE)) {
                        $path = str_ireplace(JBETOLO_URI_BASE, '', $path);
                } else if (jbetoloHelper::beginWith($path, JBETOLO_URI_CDN)) {
                        $path = str_ireplace(JBETOLO_URI_CDN, '', $path);
                } else if (jbetoloHelper::beginWith($path, JPATH_SITE . '/')) {
                        $path = str_replace(JPATH_SITE . '/', '', $path);
                } else if (jbetoloHelper::beginWith($path, JPATH_SITE)) {
                        $path = str_replace(JPATH_SITE, '', $path);
                } else if (jbetoloHelper::beginWith($path, JBETOLO_URI_PATH)) {
                        $path = substr($path, strlen(JBETOLO_URI_PATH));
                } else {
                        $app = JFactory::getApplication();

                        if ($app->getName() != 'site' && !jbetoloHelper::beginWith($path, 'administrator')) {
                                $_path = JPATH_SITE . '/' . jbetoloFileHelper::cleanUpCall($path, '/', true, true);

                                if (!file_exists($_path)) {
                                        $path = 'administrator/' . $path;
                                }
                        }
                }

                $path = JPATH_SITE . '/' . jbetoloFileHelper::cleanUpCall($path, '/', true, true);
                
                if (!file_exists($path)) {
                        if ($type && strtolower(substr($path, -strlen($type))) != $type) {
                                $path .= '.' . $type;
                        }

                        if (!file_exists($path)) {
                                return false;
                        }                                
                }

                if (!$is_absolute || !$is_file_path) {
                        $path = str_replace(JPATH_SITE . '/', '', $path);
                }

                if (!$is_file_path) {
                        if ($is_absolute) {
                                if ($path[0] == '/') $path = substr($path, 1);
                                
                                $path = (JBETOLO_CDN_MAP && $cdn ? $cdn : JBETOLO_URI_BASE) . $path;
                        }
                }

                $isPHP = self::isPHP($path);
                
                if ($is_file_path && $isPHP && !$passPHP) {
                        return false;
                }

                if ($maintain_query_string) {
                        $call = explode('?', $call);

                        if (count($call) > 1 && $isPHP) {
                                if ($is_file_path) {
                                        return false;
                                }

                                $path .= '?' . $call[1];
                        }
                }
                
                $path = html_entity_decode($path);
                
                return $path;
        }
        
        private static function isPHP($call) {
                if (strpos($call, '?') !== false) list($call, $query) = explode('?', $call);
                return jbetoloHelper::endWith($call, '.php');
        }

        public static function isSkippedAsDynamic($call) {
                return self::isPHP($call) && (bool) plgSystemJBetolo::param('skip_dynamic');
        }

        // NOT USED
        function normalizeCalls(&$files, $key = '', $is_absolute = false, $is_file_path = true, $maintain_query_string = true) {
                if (!isset($files) || count($files) <= 0)
                        return;

                foreach ($files as $f => $file) {
                        $file = self::normalizeCall($key == '' ? $file : $file[$key], $is_absolute, $is_file_path, $maintain_query_string);

                        if ($key == '') {
                                $files[$f] = $file;
                        } else {
                                $files[$f][$key] = $file;
                        }
                }
        }

        public static function getDirectoryName($call) {
                $call = self::normalizeCall($call, true, true, false, null, true, false);
                return dirname($call);
        }

        /*
         * adapted version of JURI::_cleanPath
         */
        public static function cleanUpCall($path, $sep = '', $delete_query_string = false, $enforce_file_path = false) {
                if (!$sep) {
                        $sep = '/';
                }

                $path = JPath::clean($path, $sep);
                $path = explode($sep, $path);

                for ($i = 0; $i < count($path); $i++) {
                        if ($path[$i] == '.') {
                                unset($path[$i]);
                                $path = array_values($path);
                                $i--;
                        } elseif ($path[$i] == '..' && ($i > 1 OR ($i == 1 && $path[0] != ''))) {
                                unset($path[$i]);
                                unset($path[$i - 1]);
                                $path = array_values($path);
                                $i -= 2;
                        } elseif ($path[$i] == '..' && $i == 1 && $path[0] == '') {
                                unset($path[$i]);
                                $path = array_values($path);
                                $i--;
                        } else {
                                continue;
                        }
                }

                $path = implode($sep, $path);

                if ($delete_query_string) {
                        $path = explode('?', $path);
                        $path = $path[0];
                }

                if (!$enforce_file_path && self::isHttpCall($path)) {
                        $path = preg_replace('/(http[s]?:)([\/]{1})([^\/])/i', '\1\2/\3', $path);
                }

                return $path;
        }

        private static function isHttpCall($call) {
                $_call = jbetoloFileHelper::normalizeCall($call, true, false, true);
                return stripos($_call, $call);
        }

        public static function getContent($call, $type, $method = null) {
                $html = '';
                $_call = $call;

                $call = jbetoloFileHelper::normalizeCall($call, true, true, true, $type);

                try {
                        if ($call) {
                                $html = file_get_contents($call);
                        } else {
                                $call = jbetoloFileHelper::normalizeCall($_call, true, false, true, $type);
                                
                                if ($call) {
                                        $html = jbetoloFileHelper::makeHTTPRequest($call, $type);
                                }
                        }
                } catch (Exception $e) {
                        JError::raiseWarning(500, $e);
                        return '';
                }

                return $html;
        }

        /**
         * adaptation of David Cramer's <dcramer@gmail.com> httplib code with addition of 
         * the CURL use, i.e. if CURL is available we always default to it if not default to
         * named implementation
         */
        function makeHTTPRequest($request, $type, $method = 'GET', $timeout = 60, $ua = '') {
                static $CURL;
                
                if (!isset($CURL)) {
                        $CURL = in_array('curl', get_loaded_extensions(), true);
                }
                
                list($protocol, $request) = explode('://', $request);
                
                $request = JPath::clean(html_entity_decode($request), '/');
                
                if ($protocol) {
                        $request = $protocol . '://' . $request;
                }
                
                if (empty($ua)) {
                        $ua = 'Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.9.2.10) Gecko/20100914 Firefox/3.6.10 ( .NET CLR 3.5.30729)';
                }

                if ($CURL) {
                        $ch = curl_init($request);
                        
                        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
                        curl_setopt($ch, CURLOPT_HEADER, 0);
                        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                        curl_setopt($ch, CURLOPT_USERAGENT, $ua);

                        $response_content = curl_exec($ch);

                        curl_close($ch);

                        return $response_content;
                }

                $method = strtoupper($method);

                $uri = JURI::getInstance($request);
                $host = $uri->getHost();

                $port = $uri->getPort();
                if (empty($port))
                        $port = 80;

                $path = $uri->getPath();
                $query_string = $uri->getQuery();

                if ($method == 'GET') {
                        $path .= '?' . $query_string;
                }

                $socket = @fsockopen($host, $port, $errorNumber, $errorString, (float) $timeout);

                if (!$socket) {
                        JError::raiseError(500, 'jbetolo: Failed connecting to ' . $host . ':' . $port . ': ' . socket_strerror($errorNumber) . ' (' . $errorNumber . '); ' . $errorString);
                        return '';
                }

                stream_set_timeout($socket, (float) $timeout);

                // set default headers
                $headers['User-Agent'] = $ua;
                
                if ($type == 'js') {
                        $headers['Content-Type'] = 'application/javascript';
                } else if ($type == 'css') {
                        $headers['Content-Type'] = 'text/css';
                } else {
                        $headers['Content-Type'] = 'text/html';
                }
                
                if ($method == 'POST') {
                        $headers['Content-Length'] = strlen($query_string);
                }

                $headers['Host'] = $host;

                // build the header string
                $request_header = $method . " " . $path . " HTTP/1.1\r\n";

                foreach ($headers as $key => &$value) {
                        $request_header .= $key . ": " . $value . "\r\n";
                }

                $request_header .= "Connection: close\r\n\r\n";

                if ($method == "POST") {
                        $request_header .= $query_string;
                }

                fwrite($socket, $request_header);

                $response_header = '';

                do {
                        $response_header .= fread($socket, 1);
                } while (!preg_match('/\\r\\n\\r\\n$/', $response_header));

                $_headers = explode("\r\n", $response_header);
                $headers = array();

                foreach ($_headers as &$line) {
                        if (strpos($line, 'HTTP/') === 0) {
                                $data = explode(' ', $line);
                                $status = $data[1];
                                $message = implode(' ', array_slice($data, 2));
                        } elseif (strpos($line, ':')) {
                                $data = explode(':', $line);
                                $value = trim(implode(':', array_slice($data, 1)));
                                $headers[$data[0]] = $value;
                        }
                }

                $response_content = '';
                if (isset($headers['Transfer-Encoding']) && $headers['Transfer-Encoding'] == 'chunked') {
                        while ($chunk_length = hexdec(fgets($socket))) {
                                $response_content_chunk = '';
                                $read_length = 0;

                                while ($read_length < $chunk_length) {
                                        $response_content_chunk .= fread($socket, $chunk_length - $read_length);
                                        $read_length = strlen($response_content_chunk);
                                }

                                $response_content .= $response_content_chunk;
                                fgets($socket);
                        }
                } else {
                        while (!feof($socket)) {
                                $response_content .= fgets($socket, 128);
                        }
                }

                return chop($response_content);

                fclose($socket);
        }

        public static function createFile(&$body, $src_files, $excluded_files, $replace_tags, $conds, $comments, $indexes) {
                $arr = plgSystemJBetolo::param('files');
                $abs_excl_minify = plgSystemJBetolo::param('minify_exclude');

                if ($abs_excl_minify) {
                        $abs_excl_minify = explode(',', $abs_excl_minify);
                }

                $abs_excl_gzip = plgSystemJBetolo::param('gzip_exclude');

                if ($abs_excl_gzip) {
                        $abs_excl_gzip = explode(',', $abs_excl_gzip);
                }

                $excl_js_imports = $excl_css_imports = '';
                $css_imports = $js_imports = array();
                $js_placement = plgSystemJBetolo::param('js_placement');
                $age = plgSystemJBetolo::param('cache_age');
                $app = JFactory::getApplication()->getName();
                $tmpl = JFactory::getApplication()->getTemplate();
                $paramHasChanged = false;
                $external_custom_orders = array();

                /**
                 * app[administrator|site],type[css|js],attr[css=media,js='']
                 */
                foreach ($src_files as $type => $_src_files) {
                        if (empty($_src_files)) continue;
                        
                        $skipFiles = array_merge($conds[$type], $comments[$type]);
                        $merge = plgSystemJBetolo::param($type . '_merge');
                        $is_gz = JBETOLO_IS_GZ && plgSystemJBetolo::param($type . '_gzip') && jbetoloHelper::clientEncoding();
                        $is_minify = JBETOLO_IS_MINIFY && plgSystemJBetolo::param($type . '_minify');
                        $excl_files = $excluded_files[$type];

                        if ($merge) {
                                $isMono = plgSystemJBetolo::param($type.'_merge_mode', 'mono') == 'mono';
                                $are_files_changed = $new_files_found = false;

                                if (count($arr) == 0 || !isset($arr[$app]) || !isset($arr[$app][$tmpl]) || !isset($arr[$app][$tmpl][$type]) || !is_array($arr[$app][$tmpl][$type])) {
                                        $arr[$app][$tmpl][$type] = array();
                                }

                                if ($isMono) {
                                        if (count($arr[$app][$tmpl][$type])) {
                                                foreach ($arr[$app][$tmpl][$type] as $attr => $rec) {
                                                        $merged = array();
                                                        $merged_file = JBETOLO_CACHE_DIR . $rec['merged'];

                                                        $found_files = array();
                                                        $delete_merged_file = false;

                                                        foreach ($_src_files as $s => $src_file) {
                                                                if ($type == 'js' || isset($indexes['css'][$s]['attr']) && $attr == $indexes['css'][$s]['attr']) {
                                                                        if (!in_array($src_file, $rec['srcs'])) {
                                                                                $found_files[] = $src_file;
                                                                                $delete_merged_file = true;
                                                                        }
                                                                }
                                                        }

                                                        if (!empty($found_files)) {
                                                                $merged = array_merge($merged, $found_files);
                                                        }

                                                        $merged_file_exists = JFile::exists($merged_file);

                                                        if (empty($found_files) && $merged_file_exists) {
                                                                $are_files_changed = self::areFilesChanged($rec['parts']);

                                                                if ($are_files_changed) {
                                                                        $delete_merged_file = true;
                                                                }
                                                        }

                                                        if ($delete_merged_file && $merged_file_exists) {
                                                                JFile::delete($merged_file);
                                                        }

                                                        $merged_file_exists = JFile::exists($merged_file);

                                                        if (!$merged_file_exists) {
                                                                $merged = array_merge($merged, $rec['srcs']);
                                                        }

                                                        if (!empty($merged)) {
                                                                $merged = array_unique($merged);
                                                                $merged = jbetoloFileHelper::customOrder($merged, $type);

                                                                if ($type == 'js') {
                                                                        jbetoloJS::setJqueryFile($merged, jbetoloHelper::getArrayValues($excl_files, 'src'));
                                                                        $res = jbetoloJS::build($merged);
                                                                } else {
                                                                        $res = jbetoloCSS::build($merged, array_fill(0, count($merged), $attr));
                                                                }

                                                                $arr[$app][$tmpl][$type][$attr] = $res[$attr];
                                                                $paramHasChanged = true;
                                                        }
                                                }
                                        } else {
                                                $_src_files = array_unique($_src_files);

                                                if ($type == 'js') {
                                                        jbetoloJS::setJqueryFile($_src_files, jbetoloHelper::getArrayValues($excl_files, 'src'));
                                                }

                                                $arr[$app][$tmpl][$type] =
                                                        $type == 'css' ?
                                                        jbetoloCSS::build($_src_files, '', true, $indexes['css']) :
                                                        jbetoloJS::build($_src_files);
                                                
                                                $paramHasChanged = true;
                                        }
                                        
                                        $imports = $arr[$app][$tmpl][$type];
                                } else {
                                        $_src_files = array_unique($_src_files);
                                        $files_key = $_src_files;
                                        sort($files_key);
                                        $files_key = implode('', $files_key);
                                        
                                        if (isset($arr[$app][$tmpl][$type][$files_key])) {
                                                $rec = $arr[$app][$tmpl][$type][$files_key];
                                                $add_key = key($rec);
                                                $rec = $rec[$add_key];
                                                
                                                if (self::areFilesChanged($rec['parts'])) {
                                                        if (JFile::exists(JBETOLO_CACHE_DIR . $rec['merged'])) {
                                                                JFile::delete(JBETOLO_CACHE_DIR . $rec['merged']);
                                                        }
                                                        
                                                        $paramHasChanged = true;
                                                }
                                        } else {
                                                $paramHasChanged = true;
                                        }
                                        
                                        if ($paramHasChanged) {
                                                $arr[$app][$tmpl][$type][$files_key] =
                                                        $type == 'css' ?
                                                        jbetoloCSS::build($_src_files, '', true, $indexes['css']) :
                                                        jbetoloJS::build($_src_files)
                                                        ;
                                        }
                                        
                                        $imports = $arr[$app][$tmpl][$type][$files_key];
                                }
                                
                                $external_custom_orders[$type] = jbetoloHelper::replaceTags(
                                        $body, 
                                        $replace_tags[$type], 
                                        "",
                                        $indexes[$type],
                                        plgSystemJBetolo::param($type.'_external_custom_order_before'),
                                        plgSystemJBetolo::param($type.'_external_custom_order_after')
                                );

                                /**
                                 * @@todo: if cdn enabled just provide the file and no dynamic url
                                 */
                                foreach ($imports as $attr => $rec) {
                                        $url = jbetoloFileHelper::getServingURL($rec['merged'], $type, $is_gz, $age);

                                        if ($type == 'js') {
                                                $js_imports[] = "\n" . '<script type="text/javascript" src="' . $url . '"></script>';
                                        } else {
                                                $attrs = jbetoloHelper::returnAttributes($attr);
                                                $css_imports[] = "\n" . '<link rel="stylesheet" type="text/css" href="' . $url . '" '.$attrs . ' />';
                                        }
                                }
                        }
                        
                        $gzip_excluded = plgSystemJBetolo::param($type . '_gzip_excluded');
                        $minify_excluded = plgSystemJBetolo::param($type . '_minify_excluded');

                        if ((($is_gz && $gzip_excluded) || ($is_minify && $minify_excluded)) && count($excl_files)) {
                                $jqueryNoConflict = plgSystemJBetolo::param('js_jquery_no_conflict');

                                foreach ($excl_files as $excl_file) {
                                        $src = $excl_file['src'];
                                        if (jbetoloFileHelper::fileInArray($src, $skipFiles))
                                                continue;

                                        $_minify = $is_minify && !jbetoloFileHelper::isFileExcluded($src, $abs_excl_minify);
                                        $_gz = $is_gz && !jbetoloFileHelper::isFileExcluded($src, $abs_excl_gzip);

                                        if (($_minify || $_gz) && !$excl_file['dynamic']) {
                                                $src = str_replace(JBETOLO_URI_BASE, '', $src);
                                                $attr = $type == 'css' ? jbetoloHelper::extractAttributes($excl_file['tag']) : 'main';

                                                $file =
                                                        $type == 'css' ?
                                                        jbetoloCSS::build($src, array($attr)) :
                                                        jbetoloJS::build($src);

                                                $file = $file[$attr]['merged'];

                                                $src = jbetoloFileHelper::getServingURL($file, $type, $is_gz, $age);

                                                if ($type == 'js') {
                                                        $excl_js_imports .=
                                                                "\n" . '<script type="text/javascript" src="' . $src . '"></script>';
                                                } else if ($type == 'css') {
                                                        $attrs = jbetoloHelper::returnAttributes($attr);
                                                        
                                                        $excl_css_imports .=
                                                                "\n" . '<link rel="stylesheet" type="text/css" href="' . $src . '" ' . $attrs . ' />';
                                                }

                                                $body = str_ireplace($excl_file['tag'], '', $body);
                                        }
                                }
                        }
                }

                jbetoloFileHelper::placeTags($body, $excl_css_imports, 'css');
                
                jbetoloFileHelper::placeTags(
                        $body, 
                        $js_imports, 
                        'js', 
                        $js_placement, 
                        isset($external_custom_orders['js']) ? $external_custom_orders['js']['before'] : array(), 
                        isset($external_custom_orders['js']['after']) ? $external_custom_orders['js']['after'] : array()
                );
                
                jbetoloFileHelper::placeTags($body, $excl_js_imports, 'js', $js_placement);
                
                jbetoloFileHelper::placeTags($body, $css_imports, 'css');

                if ($paramHasChanged)
                        plgSystemJBetolo::param('files', $arr, 'set');
        }

        public static function placeTags(&$body, $tags, $type, $rule = false, $tagsBefore = null, $tagsAfter = null) {
                if (empty($tags))
                        return;

                static $titleExists, $headExists;

                if (!isset($titleExists)) {
                        $titleExists = strpos($body, '</title>') !== false;
                        $headExists = strpos($body, '</head>') !== false;
                }

                if ($rule === false) {
                        if ($type == 'css') {
                                $rule = 1;
                        } else {
                                $rule = 4;
                        }
                }

                if (is_array($tags)) {
                        $tags = implode("\n", $tags);
                }
                
                $tagsBefore = empty($tagsBefore) ? '' : implode("\n", $tagsBefore);
                $tagsAfter = empty($tagsAfter) ? '' : implode("\n", $tagsAfter);
                
                $tags = $tagsBefore . $tags . $tagsAfter;

                if (($rule == 1 || $rule == 2) && $titleExists) {
                        $body = str_ireplace('</title>', '</title>' . $tags, $body);
                } else if (($rule == 3) || (!$titleExists && $headExists)) {
                        $body = str_ireplace('</head>', $tags . '</head>', $body);
                } else if (($rule == 4) || (!$titleExists && !$headExists)) {
                        if ($rule != 4) {
                                $body = str_ireplace('<body>', '<body>' . $tags, $body);
                        } else {
                                $body = str_ireplace('</body>', $tags . '</body>', $body);
                        }
                }
        }
        
        public static function minify($type, $cont) {
                if ($type == 'css') return self::cssMinimize($cont);
                
                if ($type == 'js') return self::jsMinimize($cont);
                
                static $id = 0;
                
                $path = dirname(__FILE__) . '/minify-2.1.5/min/lib/';
                set_include_path(get_include_path() . PATH_SEPARATOR . $path);
                
                require_once dirname(__FILE__) . '/jbetolo.php';
                require_once 'Minify.php';
                
                switch ($type) {
                        case 'js': 
                                $type = Minify::TYPE_JS;
                        break;
                        case 'css':
                                $type = Minify::TYPE_CSS;
                        break;
                        case 'htm': 
                        case 'html': 
                                $type = Minify::TYPE_HTML;
                        break;
                }
                
                $id++;
                
                $cont = Minify::serve('jBetolo',
                        array(
                            'contentType' => $type, 
                            'content' => $cont, 
                            'id' => 'minify_'.$id, 
                            'quiet' => true,
                            'encodeOutput' => false,
                            'maxAge' => 0,
                            'rewriteCssUris' => false
                        )
                );
                
                return $cont['content'];
        }
        
        private static function jsMinimize($contents) {
                require_once dirname(__FILE__).'/jsminplus-1.4/jsminplus.php';
                return JSMinPlus::minify($contents);
        }
        
        private static function cssMinimize($contents) {
                // Adapted from Drupal core
                $contents = preg_replace('/^@charset\s+[\'"](\S*)\b[\'"];/i', '', $contents);

                // Perform some safe CSS optimizations.
                // Regexp to match comment blocks.
                $comment     = '/\*[^*]*\*+(?:[^/*][^*]*\*+)*/';
                // Regexp to match double quoted strings.
                $double_quot = '"[^"\\\\]*(?:\\\\.[^"\\\\]*)*"';
                // Regexp to match single quoted strings.
                $single_quot = "'[^'\\\\]*(?:\\\\.[^'\\\\]*)*'";
                // Strip all comment blocks, but keep double/single quoted strings.
                $contents = preg_replace(
                        "<($double_quot|$single_quot)|$comment>Ss",
                        "$1",
                        $contents
                );
                // Remove certain whitespace.
                // There are different conditions for removing leading and trailing
                // whitespace.
                // @see http://php.net/manual/en/regexp.reference.subpatterns.php
                $contents = preg_replace('<
                                # Strip leading and trailing whitespace.
                                \s*([@{};,])\s*
                                # Strip only leading whitespace from:
                                # - Closing parenthesis: Retain "@media (bar) and foo".
                                | \s+([\)])
                                # Strip only trailing whitespace from:
                                # - Opening parenthesis: Retain "@media (bar) and foo".
                                # - Colon: Retain :pseudo-selectors.
                                | ([\(:])\s+
                        >xS',
                        // Only one of the three capturing groups will match, so its reference
                        // will contain the wanted value and the references for the
                        // two non-matching groups will be replaced with empty strings.
                        '$1$2$3',
                        $contents
                );
                // End the file with a new line.
                $contents = trim($contents);
                $contents .= "\n";
                
                return $contents;
        }

        public static function writeToFile($to_file, $data, $type, $overrideGZ = false) {
                if (!$to_file) {
                        return false;
                }

                $to_file = JBETOLO_CACHE_DIR . '/' . str_replace(JBETOLO_CACHE_DIR . '/', '', $to_file);

                if (JFile::exists($to_file)) {
                        return true;
                }

                if ($type == 'css' || $type == 'js') {
                        $minify = JBETOLO_IS_MINIFY && plgSystemJBetolo::param($type . '_minify');
                        $exclMinify = plgSystemJBetolo::param('minify_exclude');

                        if (is_array($data)) {
                                if ($minify && $exclMinify && count($exclMinify) > 0) {
                                        $exclMinify = explode(',', $exclMinify);

                                        foreach ($data as $d => $content) {
                                                if (jbetoloFileHelper::isFileExcluded($content['file'], $exclMinify)) {
                                                        $data[$d] = $content['content'];
                                                } else {
                                                        $data[$d] = jbetoloFileHelper::minify($type, $content['content']);
                                                }
                                        }

                                        $data = implode("\n", $data);
                                } else {
                                        $data = jbetoloHelper::getArrayValues($data, 'content');
                                        $data = implode("\n", $data);

                                        if ($minify) {
                                                $data = jbetoloFileHelper::minify($type, $data);
                                        }
                                }
                        } else if ($minify) {
                                $data = jbetoloFileHelper::minify($type, $data);
                        } else {
                                $data = jbetoloHelper::eatWhiteSpace($data);
                        }
                }

                if (JBETOLO_IS_GZ && (plgSystemJBetolo::param($type . '_gzip') || $overrideGZ)) {
                        $data = gzencode($data);
                        JFile::write($to_file, $data);
                } else {
                        JFile::write($to_file, $data);
                }

                return true;
        }

}

/**
 * only experimental trying to achieve a one pass parsing instead of current
 * several ones
 * need to consider if the over head cost is worth in the longer run...?
 */
class jbetoloParser {
        var $image = array('_tag' => array('img'));
        var $document = array('_tag' => array('a' => array('attr' => 'href')));
        
        public static function parseHTML($html, $tags = 'link|script') {
                $pat = '<('.$tags.')([^>]+)(?:/\s*>|>(.*)</('.$tags.')>)';
                $pat = '#(<\!--\s*\[if[^\]]+?\]\s*>\s*)?(?:' . $pat . '\s*)+(?(1)<\!\[endif\]-->)#Uism';

                $values = array();

                if (preg_match_all($pat, $html, $m)) {
                        $htmls = $m[0];
                        $conds = $m[1];
                        $tags = $m[2];
                        $attrs = $m[3];
                        $texts = $m[4];

                        $merge = array('css' => plgSystemJBetolo::param('css_merge'), 'js' => plgSystemJBetolo::param('js_merge'));
                        $gzip = array('css' => JBETOLO_IS_GZ && plgSystemJBetolo::param('css_gzip'), 'js' => JBETOLO_IS_GZ && plgSystemJBetolo::param('js_gzip'));
                        $gzip_excluded = array('css' => $gzip && plgSystemJBetolo::param('css_gzip_excluded'), 'js' => $gzip && plgSystemJBetolo::param('js_gzip_excluded'));
                        $minify = array('css' => JBETOLO_IS_MINIFY && plgSystemJBetolo::param('css_minify'), 'js' => JBETOLO_IS_MINIFY && plgSystemJBetolo::param('js_minify'));
                        $minify_excluded = array('css' => $minify && plgSystemJBetolo::param('css_minify_excluded'), 'js' => $minify && plgSystemJBetolo::param('js_minify_excluded'));

                        // collect resources to be excluded from merging
                        $merge_exclude = array();

                        foreach ($merge as $type => $m) {
                                if ($m) {
                                        $merge_exclude[$type] = plgSystemJBetolo::param($type . '_merge_exclude');

                                        if (isset($merge_exclude[$type]) && $merge_exclude[$type]) {
                                                $merge_exclude[$type] = explode(',', $merge_exclude[$type]);
                                        } else {
                                                $merge_exclude[$type] = array();
                                        }

                                        // Gzip operates at file level, therefore if a file is indicated to be non-gzipped
                                        // and gzipping of merged file is enabled then we need to exclude it
                                        // (the analogus doesn't apply for minify as merged file can contain a mix of
                                        //  minified and non-minified code)
                                        $abs_excl = plgSystemJBetolo::param('gzip_exclude');

                                        if ($abs_excl && $gzip) {
                                                $abs_excl = explode(',', $abs_excl);
                                                $merge_exclude[$type] = array_merge($merge_exclude[$type], $abs_excl);
                                        }
                                }
                        }

                        foreach ($merge_exclude as $type => $m) {
                                foreach ($m as $i => $file) {
                                        $merge_exclude[$type][$i] = jbetoloFileHelper::normalizeCall($file, false, false);
                                }
                        }

                        foreach (array('link', 'script') as $tag) {
                                $included = plgSystemJBetolo::param(($tag == 'link' ? 'css' : 'js') . '_include');

                                if (!empty($included)) {
                                        $included = explode(',', $included);
                                        foreach ($included as $incl) {
                                                $conds[] = $texts[] = '';
                                                $tags[] = $tag;
                                                $attrs[] =
                                                        ' ' .
                                                        ($tag == 'link' ? 'href' : 'src') . '="' . $incl . '" ' .
                                                        ($tag == 'link' ? 'rel="stylesheet"' : ' type="text/javascript"');
                                        }
                                }
                        }

                        foreach ($attrs as $i => $attr) {
                                $aggr = $tags[$i];
                                $value = array('_html' => $htmls[$i], '_text' => $texts[$i], '_tag' => $tags[$i], '_iecond' => !empty($conds[$i]));

                                if (preg_match_all("#([^=\s]+)=[\"\']([^\"\']+)[\"\']+#Uim", $attr, $m, PREG_PATTERN_ORDER)) {
                                        $value = array_merge($value, array_combine($m[1], $m[2]));
                                }

                                $value['_inline'] = !empty($value['_text']);

                                if (!$value['_inline']) {
                                        if ($value['_tag'] == 'script') {
                                                $value['_type'] = $aggr = 'js';
                                                $value['_src'] = jbetoloFileHelper::normalizeCall($value['src'], false, false, true, 'js');
                                                $value['_excluded'] = jbetoloFileHelper::isFileExcluded($value['_src'], $merge_exclude['js']);
                                        } else if ($value['_tag'] == 'link' && $value['rel'] == 'stylesheet' || $value['_tag'] == 'style') {
                                                $value['_type'] = $aggr = 'css';

                                                if ($value['_tag'] == 'link') {
                                                        $value['_href'] = jbetoloFileHelper::normalizeCall($value['href'], false, false, true, 'css');
                                                        $value['_excluded'] = jbetoloFileHelper::isFileExcluded($value['_href'], $merge_exclude['css']);

                                                        if (!isset($value['media'])) {
                                                                $value['media'] = 'screen';
                                                        }
                                                }
                                        }
                                }

                                if ($value['_iecond']) $value['_excluded'] = true;

                                if (!isset($values[$aggr])) {
                                        $values[$aggr] = array();
                                }

                                $values[$aggr][] = $value;
                        }
                }

                return $values;
        }
}