<?php
//$Copyright$

defined('_JEXEC') or die('Restricted access');

jimport('joomla.plugin.plugin');

require_once dirname(__FILE__) . '/jbetolo/helper.php';

class plgSystemJBetolo extends JPlugin {
        public static $allowAll = false;
        const JBETOLO_SKIP = 'JBETOLOSKIP';
        const EXCLUDE_REG_PREFIX = 'reg:';
        const DELETE_REG_START_PREFIX = 'regs:';
        const DELETE_REG_END_PREFIX = 'rege:';
        public static $jquery = null;
        private static $tagRegex = array(
            'js' => "/<script [^>]+(\/>|><\/script>)/i",
            'css' => "|<link[^>]+rel=[\"\']stylesheet[\"\'][^>]+[/]?>((.*)</[^>]+>)?|Ui",
            'css2' => "|@import\s*(?:url\()?[\'\"]?([^\'\"\()]+)[\'\"]?\)?;|Uims"
        );
        public static $srcRegex = array(
            'js' => "/src=(?:[\"\'])([^\"\']+)(?:[\"\'])/i",
            'css' => "/href=(?:[\"\'])([^\"\']+)(?:[\"\'])/i"
        );
        private static $predefinedExclude = array(
            'js' => array(),
            'css' => array()
        );
        private static $dontsEmpty = '__EMPTY__';
        private static $dos = array(
        );
        private static $donts = array(
            'jbetolo' => array(
                'option' => array('com_contentsubmit'),
                'task' => array('edit', 'add'),
                'layout' => array('form', 'edit'),
                'view' => array('edit')
            ),
            'cdn' => array(),
            'defer' => array()
        );
        private static $serializableParams = array('files', 'templates');
        public static $conditionalTags = '';
        private static $conditionalTagScript = array(
            'js' => "/<\!--\[if[^\]]+?\]>.*?<\!\[endif\]-->/ims",
            'css' => "/<\!--\[if[^\]]+?\]>.*?<\!\[endif\]-->/ims"
        );
        private static $conditionalSrcScript = array(
            'js' => "/<script[^>]*?src=(?:[\"\'])([^\"\']*?)[\"\'][^>]*?(?:\/>|>.*?<\/script>)/ims",
            'css' => "/<link[^>]*?href=(?:[\"\'])([^\"\']*?)[\"\'][^>]*?(?:\/>|>.*?<\/link>)/ims"
        );
        private static $commentedTagScript = array(
            'js' => "/<\!--.*?-->/ims",
            'css' => "/<\!--.*?-->/ims"
        );
        private static $ua = null;

        function plgSystemJBetolo(& $subject, $config) {
                parent::__construct($subject, $config);

                $this->loadLanguage('', JPATH_ADMINISTRATOR);
        }

        function onAfterInitialise() {
        }

        function onAfterRender() {
                if (self::dontJbetolo()) {
                        if (!self::dontJbetolo('cdn')) {
                                $body = JResponse::getBody();

                                if (jbetoloHelper::mapCDN($body)) JResponse::setBody($body);
                        }

                        if (self::doJbetolo('jbetolo') && self::param('add_local_jquery', 0) && self::doJbetolo('add_local_jquery_always')) {
                                $body = JResponse::getBody();

                                $jquery = '<script type="text/javascript" src="'.JBETOLO_URI_BASE.'plugins/system/jbetolo/jbetolo/assets/jquery/'.JBETOLO_JQUERY.'"></script>';

                                if (self::param('add_local_jquery_ui', 0)) {
                                        $jquery .= '<script type="text/javascript" src="'.JBETOLO_URI_BASE.'plugins/system/jbetolo/jbetolo/assets/jquery-ui/'.JBETOLO_JQUERY_UI.'"></script>';
                                }

                                if (self::param('js_jquery_no_conflict')) {
                                        $jquery .= "\n <script type='text/javascript'>jQuery.noConflict();</script>\n";
                                }

                                jbetoloFileHelper::placeTags($body, $jquery, 'js', 2);
                                JResponse::setBody($body);
                        }

                        return;
                } else {
                        $body = JResponse::getBody();
                }

                if (JBETOLO_DEBUG) {
                        jbetoloHelper::timer();
                        jbetoloHelper::resetCache();
                }

                jbetoloHelper::lazyLoad($body, 1);
                jbetoloHelper::loadClientsiderErrorLogger($body);
                //jbetoloHelper::handleChanges();

                $_comments = $_conds = $_srcs = $_esrcs = $_tags = $_indexes = array();

                if (self::param('cdnjs', false)) {
                        $jss = self::param('cdnjs', false);
                        foreach ($jss as &$js) {
                                $js = '<script type="text/javascript" src="'.$js.'"></script>';
                        }
                        jbetoloFileHelper::placeTags($body, $jss, 'js');
                }

                list($_srcs['css'], $_esrcs['css'], $_tags['css'], $_conds['css'], $_comments['css'], $_indexes['css']) =
                        $this->parseBody($body, 'css');

                list($_srcs['js'], $_esrcs['js'], $_tags['js'], $_conds['js'], $_comments['js'], $_indexes['js']) =
                        $this->parseBody($body, 'js');

                jbetoloFileHelper::createFile($body, $_srcs, $_esrcs, $_tags, $_conds, $_comments, $_indexes);

                jbetoloJS::modifyInlineScripts($body);

                jbetoloFileHelper::finalizePlaceTags($body);

                if (self::param('html_minify')) $body = jbetoloFileHelper::minify('html', $body);

                jbetoloHelper::lazyLoad($body, 2);

                jbetoloHelper::mapCDN($body);

                if (JBETOLO_DEBUG) jbetoloHelper::timer(false, true, $body);

                JResponse::setBody($body);
                // jbetoloHelper::sanityCheck();
        }

        public function onUserLogin($user, $options = array()) {
                setcookie('JBETOLO_PASS', '1');
                return true;
        }

        public function onUserLogout($user, $options = array()) {
                setcookie('JBETOLO_PASS', '');
                return true;
        }

        // J1.5 ditto
        public function onLoginUser($user, $options = array()) {
                return $this->onUserLogin($user, $options);
        }

        public function onLogoutUser($user, $options = array()) {
                return $this->onUserLogout($user, $options);
        }

        public static function doJbetolo($type) {
                if (empty($type)) return false;

                $app = JFactory::getApplication()->getName();
                $user = JFactory::getUser();
                $allowedIn = $type == 'jbetolo' ? self::param('allow_in') : 'site';

                if ($allowedIn != '' && (
                        $allowedIn == 'anonymous' && $user->guest ||
                        $allowedIn != 'anonymous' && ($app == $allowedIn || $allowedIn == 'all')
                   )) {
                        return true;
                }

                if (JFactory::getDocument()->getType() != 'html') return false;

                $dos = isset(self::$dos[$type]) ? self::$dos[$type] : array();
                $includeComponents = self::param($type . '_include_components');

                if (!empty($includeComponents)) {
                        $includeComponents = explode(',', $includeComponents);

                        foreach ($includeComponents as $i => $component) {
                                $component = trim($component);

                                if ($component != 'all' && substr($component, 0, 4) != 'com_') {
                                        $includeComponents[$i] = 'com_' . $component;
                                } else {
                                        $includeComponents[$i] = $component;
                                }
                        }

                        if (in_array('all', $includeComponents)) return true;

                        if (isset($dos['option']) && is_array($dos['option'])) {
                                $dos['option'] = array_merge($dos['option'], $includeComponents);
                        } else {
                                $dos['option'] = $includeComponents;
                        }

                        $dos['option'] = array_unique($dos['option']);
                }

                $includeURLs = self::param($type . '_include_urls', array());

                if (!empty($includeURLs)) {
                        $includeURLs = explode(',', $includeURLs);

                        foreach ($includeURLs as &$url) {
                                if (strpos($url, self::EXCLUDE_REG_PREFIX) === false) parse_str($url, $url);
                                else $url = str_replace(self::EXCLUDE_REG_PREFIX, '', $url);
                        }
                }

                $input = JFactory::getApplication()->input;

                if (!empty($includeURLs)) {
                        $uri = JURI::getInstance();
                        $curr = $uri->toString(array('scheme', 'host', 'port', 'path', 'query'));

                        foreach ($includeURLs as $url) {
                                $match = true;

                                if (is_string($url)) {
                                        $match = preg_match('#'.preg_quote($url).'#', $curr);
                                } else {
                                        foreach ($url as $k => $v) {
                                                $val = $input->get($k, self::$dontsEmpty);
                                                //$val = JRequest::getVar($k, self::$dontsEmpty);
                                                if ($val != $v) {
                                                        $match = false;
                                                        break;
                                                }
                                        }
                                }

                                if ($match) return true;
                        }
                }

                if (self::checkRules($dos)) {
                        return true;
                }
        }

        private static function checkRules($rules) {
                $cmds = array_keys($rules);
                $input = JFactory::getApplication()->input;

                // $rules = array('option'=>array('com_community'));

                foreach ($rules as $key => $rule) {
                        if (empty($rule)) continue;

                        if (is_array($rule) && !in_array($rule[0], $cmds) || is_string($rule)) {
                                $val = $input->get($key, self::$dontsEmpty);
                                // $val = JRequest::getCmd($key, self::$dontsEmpty);

                                if (is_array($rule) && in_array($val, $rule) || is_string($rule) && $val == $rule) {
                                        return true;
                                }
                        } else {
                                return self::checkRules($rule);
                        }
                }

                return false;
        }

        public static function dontJbetolo($type = 'jbetolo') {
                if (self::param('listen_request', 0) && JRequest::getCmd('nojbetolo', 0) == 1) return true;

                $app = JFactory::getApplication()->getName();
                $user = JFactory::getUser();
                $allowedIn = $type == 'jbetolo' ? self::param('allow_in') : 'site';

                if (!self::$allowAll && (
                        $allowedIn == 'anonymous' && !$user->guest ||
                        $allowedIn != 'anonymous' && $app != $allowedIn && $allowedIn != 'all' ||
                        $app == 'administrator' && $user->guest
                   )) {
                        return true;
                }

                $document = JFactory::getDocument();
                $doctype = $document->getType();

                if ($doctype != 'html') {
                        return true;
                }

                if ($type == 'cdn') {
                        if (!(bool) self::param('cdn_enabled', false)) {
                                return true;
                        }
                }

                $donts = self::$donts[$type];

                if ($type == 'jbetolo') {
			$excludeComponents = self::param('exclude_components');
                } else {
			$excludeComponents = self::param($type . '_exclude_components');
                }

                if (!empty($excludeComponents)) {
                        $excludeComponents = explode(',', $excludeComponents);

                        foreach ($excludeComponents as $i => $component) {
                                if (substr($component, 0, 4) != 'com_') {
                                        $excludeComponents[$i] = 'com_' . $component;
                                }
                        }

                        if (is_array($donts['option'])) {
                                $donts['option'] = array_merge($donts['option'], $excludeComponents);
                        } else {
                                $donts['option'] = $excludeComponents;
                        }
                }

                $excludeURLs = self::param('exclude_urls', array());

                if (!empty($excludeURLs)) {
                        $excludeURLs = explode(',', $excludeURLs);
                        foreach ($excludeURLs as &$url) {
                                if (strpos($url, self::EXCLUDE_REG_PREFIX) === false) parse_str($url, $url);
                                else $url = str_replace(self::EXCLUDE_REG_PREFIX, '', $url);
                        }
                }

                $excludeURLs[] = array('option' => 'com_maqmahelpdesk', 'task' => 'ticket_view');
                $excludeURLs[] = array('option' => 'com_jevents', 'task' => 'icalevent.edit');
                $excludeURLs[] = array('option' => 'com_content', 'task' => 'article.add');
                $excludeURLs[] = array('option' => 'com_content', 'task' => 'article.edit');
                $excludeURLs[] = array('option' => 'com_k2', 'task' => 'article.add');
                $excludeURLs[] = array('option' => 'com_k2', 'task' => 'article.edit');
                $excludeURLs[] = array('option' => 'com_easydiscuss', 'view' => 'ask');

                if (!empty($excludeURLs)) {
                        $uri = JURI::getInstance();
			$curr = $uri->toString(array('scheme', 'host', 'port', 'path', 'query'));

                        foreach ($excludeURLs as $url) {
                                $match = true;

                                if (is_string($url)) {
                                        $match = preg_match('#'.preg_quote($url).'#', $curr);
                                } else {
                                        foreach ($url as $k => $v) {
                                                $val = JRequest::getVar($k, self::$dontsEmpty);
                                                if ($val != $v) {
                                                        $match = false;
                                                        break;
                                                }
                                        }
                                }

                                if ($match) return true;
                        }
                }

                if (self::checkRules($donts)) {
                        return true;
                }

                $excludeBrowsers = self::param('exclude_browsers');

                if (!empty($excludeBrowsers)) {
                        $excludeBrowsers = explode("\n", $excludeBrowsers);

                        jimport('joomla.environment.browser');
			$navigator = JBrowser::getInstance();

                        foreach ($excludeBrowsers as $excludeBrowser) {
                                $excludeBrowser = strtolower($excludeBrowser);
                                $excludeBrowser = trim($excludeBrowser);
                                $additionalCheck = false;

                                $_excludeBrowser = explode('-version', $excludeBrowser);
                                $_excludeBrowser[0] = jbetoloHelper::browser($_excludeBrowser[0]);

                                if (count($_excludeBrowser) > 1) {
                                        if ($navigator->isBrowser($_excludeBrowser[0])) {
                                                if (self::browserCompare($_excludeBrowser, 'version', $navigator)) {
                                                        return true;
                                                }
                                        }

                                        $additionalCheck = true;
                                }

                                if (!$additionalCheck) {
                                        $excludeBrowser = jbetoloHelper::browser($excludeBrowser);

                                        if ($navigator->isBrowser($excludeBrowser)) {
                                                return true;
                                        }
                                }
                        }
                }

                if ((bool) self::param('exclude_mobile', 0)) {
                        jimport('joomla.environment.browser');

			$navigator = JBrowser::getInstance();

                        if ($navigator->isMobile()) return true;
                }

                return false;
        }

        private static function browserCompare($compareData, $compareOn, $navigator) {
                $found = true;

                for ($i = 1, $n = count($compareData); $i < $n; $i++) {
                        $data = $compareData[$i];

                        if ($compareOn == 'version') {
                                $op = substr($data, 0, 2);
                                $version = substr($data, 2);
                                $ver = $navigator->getVersion();
                                $ver = preg_replace('#(\.0+)$#', '', $ver);

                                if (!version_compare($ver, $version, $op)) {
                                        $found = false;
                                        break;
                                }
                        }
                }

                return $found;
        }

        private function parseBody($body, $type) {
                $merge = self::param($type . '_merge');
                $gzip = JBETOLO_IS_GZ && self::param($type . '_gzip');
                $gzip_excluded = $gzip && self::param($type . '_gzip_excluded');
                $minify = JBETOLO_IS_MINIFY && self::param($type . '_minify');
                $minify_excluded = $minify && self::param($type . '_minify_excluded');

                if (!$merge && !$gzip_excluded && !$minify_excluded)
                        return;

                // absolutely included resources are appended to body
                $included = plgSystemJBetolo::param($type . '_include', '');
                $included = trim($included);

                if ($included) {
                        $included = @explode(',', $included);

                        foreach ($included as $i => $include) {
                                $include = jbetoloFileHelper::normalizeCall($include);

                                if ($type == 'js') {
                                        $included[$i] = "<script type=\"text/javascript\" src=\"" . $include . "\"></script>\n";
                                } else if ($type == 'css') {
                                        $included[$i] = "<link rel=\"stylesheet\" href=\"" . $include . "\" type=\"text/css\" media=\"screen\" />\n";
                                }
                        }

                        $included = implode('', $included);

                        $body = str_ireplace('</title>', '</title>' . $included, $body);
                }

                $excluded = $comments = $conds = $excludedSrcs = array();

                // find and consider IE conditionals as excluded from merging
                preg_match_all(self::$conditionalTagScript[$type], $body, $condTags);
                $condTags = $condTags[0];
                self::$conditionalTags = implode('', $condTags);
                preg_match_all(self::$conditionalSrcScript[$type], self::$conditionalTags, $matches);

                foreach ($matches[0] as $c => $conditional) {
                        $conds[] = jbetoloFileHelper::normalizeCall($matches[1][$c]);
                        $excludedSrcs[] = $conds[$c];
                }

                // find and exclude commented resources from merging
                preg_match_all(self::$commentedTagScript[$type], $body, $matches);

                if (!empty($matches[0]) && !empty($condTags)) {
                        foreach ($matches[0] as $m => $match) {
                                if (in_array($match, $condTags)) unset($matches[0][$m]);
                        }
                }

                $matches = implode('', $matches[0]);
                preg_match_all(self::$conditionalSrcScript[$type], $matches, $matches);

                foreach ($matches[0] as $c => $conditional) {
                        $comments[] = jbetoloFileHelper::normalizeCall($matches[1][$c]);
                        $excludedSrcs[] = $comments[$c];
                }

                // collect resources to be excluded from merging
                if ($merge) {
                        $excluded = self::param($type . '_merge_exclude');

                        if (isset($excluded) && $excluded) {
                                $excluded = @explode(',', $excluded);
                        } else {
                                $excluded = array();
                        }

                        $excluded = array_merge($excluded, self::$predefinedExclude[$type], $excludedSrcs);

                        // Gzip operates at file level, therefore if a file is indicated to be non-gzipped
                        // and gzipping of merged file is enabled then we need to exclude it
                        // (the analogus doesn't apply for minify as merged file can contain a mix of
                        //  minified and non-minified code)
                        $abs_excl = self::param('gzip_exclude');

                        if ($abs_excl && $gzip) {
                                $abs_excl = explode(',', $abs_excl);
                                $excluded = array_merge($excluded, $abs_excl);
                        }
                }

                // find all resources
                preg_match_all(self::$tagRegex[$type], $body, $matches);
                $tags = $matches[0];
                preg_match_all(self::$srcRegex[$type], implode('', $tags), $matches);

                if (count($matches[0]) != count($tags)) {
                        // Due to incorrect syntax some tags has not found corresponding source entry and will be discarded
                        $n = count($matches[1]);
                        $d = 0;
                        foreach ($tags as $s => $src) {
                                $si = $s - $d;
                                if ($si < $n) {
                                        if (strpos($tags[$s], $matches[1][$si]) === false) {
                                                unset($tags[$s]);
                                                $d++;
                                        }
                                } else {
                                        unset($tags[$s]);
                                }
                        }
                        $tags = array_filter($tags);
                        $tags = array_values($tags);
                }

                $excludedSrcs = $_excludedSrcs = $srcs = $indexes = $srcsIndexes = array();

                // prepare required input for the merging by processing each found resource
                // 1. separate the excluded ones by considering the choosen merging method
                // 2. if css identify and assign correct media type
                // 3. if resource is not locally available no further processing
                $deleteSrcs = self::param('delete');

                if ($deleteSrcs) {
                        $deleteSrcs = explode(',', $deleteSrcs);
                }

                foreach ($matches[1] as $s => $src) {
                        $indexes[] = array('src' => $src, 'tag' => $tags[$s], 'srci' => '');

                        $src = jbetoloFileHelper::normalizeCall($src, false, false, true, $type);

                        if ($src) {
                                $asDynamic = jbetoloFileHelper::isSkippedAsDynamic($src);

                                if ($merge) {
                                        $shouldIgnore = jbetoloFileHelper::isFileExcluded($src, $excluded);
                                } else {
                                        $shouldIgnore = true;
                                }

                                if ($type == 'css') {
                                        $attr = jbetoloHelper::extractAttributes($tags[$s]);
                                        $indexes[$s]['attr'] = $attr;
                                }

                                if (!$shouldIgnore && !$asDynamic) {
                                        $srcs[] = $src;
                                        $indexes[$s]['srci'] = count($srcs) - 1;
                                        $srcsIndexes[$src] = $indexes[$s];
                                } else {
                                        $isDeleted = false;

                                        // is deleted
                                        if ($deleteSrcs) {
                                                foreach ($deleteSrcs as $d) {
                                                        if ($d == $matches[1][$s]) {
                                                                $isDeleted = true;
                                                                break;
                                                        }
                                                }
                                        }

                                        if (!$isDeleted) {
                                                $excludedSrcs[$src] = array('src' => $src, 'tag' => $tags[$s], 'dynamic' => $asDynamic);
                                                $_excludedSrcs[] = $src;
                                                $tags[$s] = JBETOLO_EMPTYTAG;
                                        }
                                }
                        } else {
                                // external url or resource not found physically on the server
                                $isDeleted = false;

                                // is deleted
                                if ($deleteSrcs) {
                                        foreach ($deleteSrcs as $d) {
                                                if ($d == $matches[1][$s]) {
                                                        $isDeleted = true;
                                                        break;
                                                }
                                        }
                                }

                                // is left untouched
                                if (!$isDeleted) $tags[$s] = JBETOLO_EMPTYTAG;
                        }
                }

                // resources to be deleted are removed from found ones
                if ($deleteSrcs) {
                        foreach ($deleteSrcs as $d) {
                                $_d = jbetoloFileHelper::normalizeCall($d);

                                if ($_d !== false) {
                                        $d = $_d;
                                }

                                $f = jbetoloFileHelper::fileInArray($d, $srcs);

                                if ($f) {
                                        unset($srcsIndexes[$srcs[$f[0]]]);
                                        unset($srcs[$f[0]]);
                                }
                        }
                }

                if ($type == 'js') {
                        if (self::param('add_local_jquery', 0)) {
                                $srcs[] = JBETOLO_PATH.'jbetolo/assets/jquery/'.JBETOLO_JQUERY;
                                $srcsIndexes[JBETOLO_PATH.'jbetolo/assets/jquery/'.JBETOLO_JQUERY] =
                                        array(
                                            'src' => JBETOLO_PATH.'jbetolo/assets/jquery/'.JBETOLO_JQUERY,
                                            'tag' => '',
                                            'srci' => ''
                                        );

                                self::param('js_jquery', JBETOLO_JQUERY, 'set');

                                if (self::param('add_local_jquery_ui', 0)) {
                                        $srcs[] = JBETOLO_PATH.'jbetolo/assets/jquery-ui/'.JBETOLO_JQUERY_UI;
                                        $srcsIndexes[JBETOLO_PATH.'jbetolo/assets/jquery-ui/'.JBETOLO_JQUERY_UI] =
                                                array(
                                                    'src' => JBETOLO_PATH.'jbetolo/assets/jquery-ui/'.JBETOLO_JQUERY_UI,
                                                    'tag' => '',
                                                    'srci' => ''
                                                );
                                }
                        }

                        jbetoloJS::setJqueryFile($srcs, $_excludedSrcs);
                } else if ($type == 'css') {
                        if (self::param('add_local_jquery_ui_css', 0)) {
                                $srcs[] = JBETOLO_PATH.'jbetolo/assets/'.JBETOLO_JQUERY_UI_CSS;
                                $srcsIndexes[JBETOLO_PATH.'jbetolo/assets/'.JBETOLO_JQUERY_UI_CSS] =
                                        array(
                                            'src' => JBETOLO_PATH.'jbetolo/assets/'.JBETOLO_JQUERY_UI_CSS,
                                            'tag' => '',
                                            'srci' => ''
                                        );
                        }
                }

                // apply merging ordering
                $orderedSrcs = jbetoloFileHelper::customOrder($srcsIndexes, $type, $srcs);
                $orderedSrcs = jbetoloHelper::getArrayValues($orderedSrcs, 'src');

                $orderedExcludedSrcs = jbetoloFileHelper::customOrder($excludedSrcs, $type, $_excludedSrcs);

                return array($orderedSrcs, $orderedExcludedSrcs, $tags, $conds, $comments, $indexes);
        }

        /**
         * both getter and setter of plugin parameters
         * (de)serializes indicated params before getting resp. setting
         */
        public static function param($name, $value = '', $dir = 'get') {
                static $plg, $params, $_params;

                if (!isset($params)) {
                        $plg = JPluginHelper::getPlugin('system', 'jbetolo');

                        if (!$plg) return;

                        if (version_compare(JVERSION, '2.5', 'ge')) {
                                $params = new JRegistry($plg->params);
                        } else {
                                jimport('joomla.html.parameter');
                                $params = new JParameter($plg->params);
                        }
                }

                if ($dir == 'set') {
                        static $plgT, $db, $plgId, $j16;

                        if (!isset($db)) {
                                $j16 = jbetoloHelper::isJ16();
                                $db = JFactory::getDBO();
                                JTable::addIncludePath(JPATH_SITE.'/libraries/joomla/database/table/');
                                $plgT = JTable::getInstance(!$j16 ? 'plugin' : 'extension');

                                if ($j16) {
                                        $query = "SELECT extension_id FROM #__extensions WHERE type = 'plugin' AND folder = 'system' AND element = 'jbetolo' LIMIT 1";
                                } else {
                                        $query = "SELECT id FROM #__plugins WHERE folder = 'system' AND element = 'jbetolo' LIMIT 1";
                                }

                                $db = JFactory::getDBO();
                                $db->setQuery($query);
                                $plgId = $db->loadResult();
                        }

                        $files = '';

                        if ($value instanceof JRegistry || $value instanceof JParameter) {
                                $params = $value;
                                $files = $params->get('files');
                        } else {
                                if (in_array($name, self::$serializableParams)) {
                                        $value = serialize($value);
                                }

                                if ($name == 'files') $files = $value;
                                else $params->set($name, $value);
                        }

                        if ($files) JFile::write(JBETOLO_FILES_CACHE, $files);

                        $params->set('files', null);
                        $plgT->bind(array(($j16 ? 'extension_id' : 'id') => $plgId, 'params' => $params->toString($j16 ? 'JSON' : 'INI')));

                        if (!$plgT->store()) {
                                return JError::raiseWarning(500, $db->getError());
                        }

                        if (!empty($name))
                                unset($_params[$name]);
                } else {
                        if (!isset($_params[$name]) ||
                                (in_array($name, self::$serializableParams) && !is_array($_params[$name]))) {
                                if ($name == 'files') {
                                        $files = JFile::exists(JBETOLO_FILES_CACHE) ? JFile::read(JBETOLO_FILES_CACHE) : '';
                                        $params->set('files', $files);
                                        $files = null;
                                }

                                $_params[$name] = $params->get($name);

                                if (is_string($_params[$name])) {
                                        $_params[$name] = trim($_params[$name]);
                                }

                                if (!isset($_params[$name])) {
                                        $_params[$name] = $value;
                                }

                                if (in_array($name, self::$serializableParams)) {
                                        if (isset($_params[$name]) && !empty($_params[$name])) {
                                                $_params[$name] = @unserialize($_params[$name]);
                                        }

                                        if (empty($_params[$name])) {
                                                $_params[$name] = array();
                                        }
                                }
                        }

                        return $_params[$name];
                }
        }
}

jbetoloHelper::defineConstants();