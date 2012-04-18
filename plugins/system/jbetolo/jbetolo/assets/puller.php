<?php
/**
* @version:	2.0.0.b10-99 - 2012 March 09 16:03:58 +0300
* @package:	jbetolo
* @subpackage:	jbetolo
* @copyright:	Copyright (C) 2010 - 2011 jproven.com. All rights reserved. 
* @license:	GNU General Public License Version 2, or later http://www.gnu.org/licenses/gpl.html
*/

require_once dirname(__FILE__) . '/jbetolo.cdn.conf';

$file = $_GET['cfile'];
//$file = filter_input(INPUT_GET, 'cfile', FILTER_VALIDATE_REGEXP, array('options' => array('regexp' => '[a-zA-Z0-9\_\-\.\s\\\/]+')));

pullCDN($file);

function pullCDN($file_loc) {
        $src = JPATH_SITE . '/' . $file_loc;

        if (is_file($src)) {
                $srcFolder = dirname($file_loc);
                $file = basename($file_loc);
                $srcFolder = str_replace('\\', '/', $srcFolder);
                $folders = explode('/', $srcFolder);
                $srcFolder = JPATH_SITE . '/' . $srcFolder;

                if (!defined('JBETOLO_CDN_OWN_FOLDER')) {
                         return;
                }

                $curr = JBETOLO_CDN_OWN_FOLDER;
                $success = true;

                foreach ($folders as $folder) {
                        if (!$folder) {
                                $success = false;
                                break;
                        }

                        $curr .= $folder;
                        
                        if (!is_dir($curr)) {
                                if (!($success = mkdir($curr))) {
                                        break;
                                } else {
                                        if ($dh = opendir($srcFolder)) {
                                                while (false !== ($file = readdir($dh))) {
                                                        if ($file == '.htaccess') {
                                                                copy($srcFolder . '/.htaccess', $curr . '/.htaccess');
                                                                break;
                                                        }
                                                }

                                                closedir($dh);
                                        }
                                }
                        }

                        $curr .= '/';
                }

                if ($success) {
                        $dst = JBETOLO_CDN_OWN_FOLDER . $file_loc;
                        copy($src, $dst);

                        $dst = JBETOLO_URI_CDN . $file_loc;

                        header('HTTP/1.1 301 Moved Permanently');
			header('Location: '.$dst);
                }
        }
}

?>
