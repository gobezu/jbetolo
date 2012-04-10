<?php
//$Copyright$

defined('_JEXEC') or die('Restricted access');

jimport('joomla.plugin.plugin');

require_once dirname(__FILE__) . '/jbetolo/helper.php';

class plgSystemJBetolo extends JPlugin {
        const EXCLUDE_REG_PREFIX = 'reg:';
        public static $jquery = null;
        private static $tagRegex = array(
            'js' => "/<script [^>]+(\/>|><\/script>)/i",
            'css' => "|<link[^>]+rel=[\"\']stylesheet[\"\'][^>]+[/]?>((.*)</[^>]+>)?|Ui",
            'css2' => "|@import\s*(?:url\()?[\'\"]?([^\'\"\()]+)[\'\"]?\)?;|Uims"
        );
        private static $srcRegex = array(
            'js' => "/src=(?:[\"\'])([^\"\']+)(?:[\"\'])/i",
            'css' => "/href=(?:[\"\'])([^\"\']+)(?:[\"\'])/i"
        );
        private static $predefinedExclude = array(
            'js' => array(),
            'css' => array()
        );
        private static $dontsEmpty = '__EMPTY__';
        private static $donts = array(
            'jbetolo' => array(
                'option' => array(),
                'task' => array('edit', 'add'),
                'layout' => array('form', 'edit'),
                'view' => array('edit')
            ),
            'cdn' => array()
        );
        private static $serializableParams = array('files', 'templates');
        private static $conditionalTagScript = array(
            'js' => "/<\!--\[if[^\]]+?\]>.*?<\!\[endif\]-->/ims",
            'css' => "/<\!--\[if[^\]]+?\]>.*?<\!\[endif\]-->/ims"
        );
        private static $conditionalSrcScript = array(
            'js' => "/<script[^>]*?src=(?:[\"\'])([^\"\']*?)[\"\'][^>]*?(?:\/>|>.*?<\/script>)/ims",
            'css' => "/<link[^>]*?href=(?:[\"\'])([^\"\']*?)[\"\'][^>]*?(?:\/>|>.*?<\/link>)/ims"
        );
        
        function plgSystemJBetolo(& $subject, $config) {                
                parent::__construct($subject, $config);
                $this->loadLanguage('', JPATH_ADMINISTRATOR);
        }

        function onAfterRender() {
                if (plgSystemJBetolo::dontJbetolo()) {
                        if (!plgSystemJBetolo::dontJbetolo('cdn')) {
                                $body = JResponse::getBody();
                                
                                if (jbetoloHelper::mapCDN($body)) JResponse::setBody($body);
                        }

                        return;
                } else {
                        $body = JResponse::getBody();
                }

                if (JBETOLO_DEBUG) {
                        jbetoloHelper::timer();
                        jbetoloHelper::resetCache();
                }
                
                jbetoloHelper::handleChanges();

                $_conds = $_srcs = $_esrcs = $_tags = $_indexes = array();

                list($_srcs['css'], $_esrcs['css'], $_tags['css'], $_conds['css'], $_indexes['css']) =
                        $this->parseBody($body, 'css');

                list($_srcs['js'], $_esrcs['js'], $_tags['js'], $_conds['js'], $_indexes['js']) =
                        $this->parseBody($body, 'js');

                jbetoloFileHelper::createFile($body, $_srcs, $_esrcs, $_tags, $_conds, $_indexes);

                jbetoloJS::moveInlineScripts($body);
                
                if (plgSystemJBetolo::param('html_minify')) $body = jbetoloFileHelper::minify('html', $body);
                
                $body = jbetoloHelper::mapCDN($body);
                
                if (JBETOLO_DEBUG) jbetoloHelper::timer(false, true, $body);
                
                JResponse::setBody($body);
                
                // jbetoloHelper::sanityCheck();
        }

        public static function dontJbetolo($type = 'jbetolo') {
                $app = JFactory::getApplication()->getName();
                $user = JFactory::getUser();
                $allowedIn = $type == 'jbetolo' ? plgSystemJBetolo::param('allow_in') : 'site';

                if (
                        $allowedIn == 'anonymous' && !$user->guest ||
                        $app != $allowedIn && $allowedIn != 'all' ||
                        $app == 'administrator' && $user->guest
                   ) {
                        return true;
                }

                $document = JFactory::getDocument();
                $doctype = $document->getType();

                if ($doctype != 'html') {
                        return true;
                }


                if ($type == 'cdn') {
                        if (!(bool) plgSystemJBetolo::param('cdn_enabled', false)) {
                                return true;
                        }
                }

                $donts = plgSystemJBetolo::$donts[$type];
                
                if ($type == 'jbetolo') {
			$excludeComponents = plgSystemJBetolo::param('js_exclude_components');
                } else {
			$excludeComponents = plgSystemJBetolo::param($type . '_exclude_components');
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

                if (plgSystemJBetolo::checkDonts($donts)) {
                        return true;
                }

                return false;
        }

        private static function checkDonts($rules) {
                $cmds = array_keys($rules);

                foreach ($rules as $key => $rule) {
                        if (empty($rule))
                                continue;

                        if (is_array($rule) && !in_array($rule[0], $cmds) || is_string($rule)) {
                                $val = JRequest::getCmd($key, plgSystemJBetolo::$dontsEmpty);

                                if (is_array($rule) && in_array($val, $rule) || is_string($rule) && $val == $rule) {
                                        return true;
                                }
                        } else {
                                return plgSystemJBetolo::checkDonts($rule);
                        }
                }

                return false;
        }

        private function parseBody($body, $type) {
                $merge = plgSystemJBetolo::param($type . '_merge');
                $gzip = JBETOLO_IS_GZ && plgSystemJBetolo::param($type . '_gzip');
                $gzip_excluded = $gzip && plgSystemJBetolo::param($type . '_gzip_excluded');
                $minify = JBETOLO_IS_MINIFY && plgSystemJBetolo::param($type . '_minify');
                $minify_excluded = $minify && plgSystemJBetolo::param($type . '_minify_excluded');

                if (!$merge && !$gzip_excluded && !$minify_excluded)
                        return;

                // absolutely included resources are appended to body 
                $included = plgSystemJBetolo::param($type . '_include');

                if (isset($included) && $included) {
                        $included = @explode(',', $included);

                        $includedStr = '';

                        foreach ($included as $include) {
                                $include = jbetoloFileHelper::normalizeCall($include);

                                if ($type == 'js') {
                                        $includedStr .= '<script type="text/javascript" src="' . $include . '"></script>' . "\n";
                                } else if ($type == 'css') {
                                        $includedStr .= '<link rel="stylesheet" href="' . $include . '" type="text/css" media="screen" />' . "\n";
                                }
                        }

                        $body = str_ireplace('</title>', '</title>' . $includedStr, $body);
                }

                $excluded = $conds = $excludedSrcs = array();

                // find and consider IE conditionals as excluded from merging
                preg_match_all(plgSystemJBetolo::$conditionalTagScript[$type], $body, $matches);
                preg_match_all(plgSystemJBetolo::$conditionalSrcScript[$type], implode('', $matches[0]), $matches);

                foreach ($matches[0] as $c => $conditional) {
                        $conds[] = jbetoloFileHelper::normalizeCall($matches[1][$c]);
                        $excludedSrcs[] = $conds[$c];
                }

                // collect resources to be excluded from merging
                if ($merge) {
                        $excluded = plgSystemJBetolo::param($type . '_merge_exclude');

                        if (isset($excluded) && $excluded) {
                                $excluded = @explode(',', $excluded);
                        } else {
                                $excluded = array();
                        }

                        $excluded = array_merge($excluded, plgSystemJBetolo::$predefinedExclude[$type], $excludedSrcs);

                        // Gzip operates at file level, therefore if a file is indicated to be non-gzipped
                        // and gzipping of merged file is enabled then we need to exclude it
                        // (the analogus doesn't apply for minify as merged file can contain a mix of
                        //  minified and non-minified code)
                        $abs_excl = plgSystemJBetolo::param('gzip_exclude');

                        if ($abs_excl && $gzip) {
                                $abs_excl = explode(',', $abs_excl);
                                $excluded = array_merge($excluded, $abs_excl);
                        }
                }

                // find all resources
                preg_match_all(plgSystemJBetolo::$tagRegex[$type], $body, $matches);
                $tags = $matches[0];
                preg_match_all(plgSystemJBetolo::$srcRegex[$type], implode('', $tags), $matches);

                $excludedSrcs = $_excludedSrcs = $srcs = $indexes = array();
                
                // prepare required input for the merging by processing each found resource
                // 1. separate the excluded ones by considering the choosen merging method
                // 2. if css identify and assign correct media type
                // 3. if resource is not locally available no further processing
                foreach ($matches[1] as $s => $src) {
                        $indexes[] = array('src' => $src, 'tag' => $tags[$s], 'srci' => '', 'media' => 'screen');

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
                                } else {
                                        $excludedSrcs[$src] = array('src' => $src, 'tag' => $tags[$s], 'dynamic' => $asDynamic);
                                        $_excludedSrcs[] = $src;
                                        $tags[$s] = JBETOLO_EMPTYTAG;
                                }
                        } else {
                                // external url's or resources not found physically on the server
                                // are left untouched
                                $tags[$s] = JBETOLO_EMPTYTAG;
                        }
                }

                // resources to be deleted are removed from found ones
                $deleteSrcs = plgSystemJBetolo::param('delete');

                if ($deleteSrcs) {
                        $deleteSrcs = explode(',', $deleteSrcs);

                        foreach ($deleteSrcs as $d) {
                                $_d = jbetoloFileHelper::normalizeCall($d);

                                if ($_d !== false) {
                                        $d = $_d;
                                }

                                $f = jbetoloFileHelper::fileInArray($d, $srcs);

                                if ($f) {
                                        unset($srcs[$f[0]]);
                                }
                        }
                }

                if ($type == 'js') {
                        jbetoloJS::setJqueryFile($srcs, $_excludedSrcs);
                }

                // apply merging ordering 
                $orderedSrcs = jbetoloFileHelper::customOrder($srcs, $type);
                $orderedExcludedSrcs = jbetoloFileHelper::customOrder($excludedSrcs, $type, $_excludedSrcs);

                return array($orderedSrcs, $orderedExcludedSrcs, $tags, $conds, $indexes);
        }

        /**
         * both getter and setter of plugin parameters  
         * (de)serializes indicated params before getting resp. setting
         */
        public static function param($name, $value = '', $dir = 'get') {
                static $plg, $params, $plgId, $db, $plgT, $_params, $j16;

                if (!isset($params)) {
                        $j16 = jbetoloHelper::isJ16();

                        if ($j16) {
                                $query = "SELECT extension_id AS id, params FROM #__extensions WHERE type = 'plugin' AND folder = 'system' AND element = 'jbetolo' LIMIT 1";
                        } else {
                                $query = "SELECT id, params FROM #__plugins WHERE folder = 'system' AND element = 'jbetolo' LIMIT 1";
                        }

                        $db = JFactory::getDBO();
                        $db->setQuery($query);
                        $plg = $db->loadObject();

                        jimport('joomla.html.parameter');
                        $params = new JParameter($plg->params);
                        $plgId = $plg->id;
                }

                if ($dir == 'set') {
                        if ($value instanceof JRegistry) {
                                $params = $value;
                        } else {
                                if (in_array($name, plgSystemJBetolo::$serializableParams)) {
                                        $value = serialize($value);
                                }

                                $params->set($name, $value);
                        }
                        
                        JTable::addIncludePath(JPATH_SITE.'/libraries/joomla/database/table/');
                        $plgT = JTable::getInstance(!$j16 ? 'plugin' : 'extension');
                        $key = $j16 ? "extension_id" : 'id';
                        $data = array($key => $plgId, "params" => $params->toString($j16 ? 'JSON' : 'INI'));

                        $plgT->bind($data);

                        if (!$plgT->store()) {
                                return JError::raiseWarning(500, $db->getError());
                        }

                        if (!empty($name))
                                unset($_params[$name]);
                } else {
                        if (!isset($_params[$name]) ||
                                (in_array($name, plgSystemJBetolo::$serializableParams) && !is_array($_params[$name]))) {
                                $_params[$name] = $params->get($name);

                                if (is_string($_params[$name])) {
                                        $_params[$name] = trim($_params[$name]);
                                }

                                if (!isset($_params[$name])) {
                                        $_params[$name] = $value;
                                }

                                if (in_array($name, plgSystemJBetolo::$serializableParams)) {
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
