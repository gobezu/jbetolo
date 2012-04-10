<?PHP

// smushit-php - a PHP client for Yahoo!'s Smush.it web service
//
// June 24, 2010
// Tyler Hall <tylerhall@gmail.com>
// http://github.com/tylerhall/smushit-php/tree/master

// Modified for use within jbetolo Joomla! optimization plugin
// by Gobezu Sewu <info@jproven.com>
// $Copyright$

class SmushIt {
        const SMUSH_URL = 'http://www.smushit.com/ysmush.it/ws.php?';

        public $filename;
        public $url;
        public $compressedUrl;
        public $size;
        public $compressedSize;
        public $savings;
        public $error;
        public $count;

        var $save, $replace, $fix, $tmpDir;

        public function __construct($save = false, $replace = false, $fix = '_', $tmpDir = '/tmp') {
                $this->size = $this->compressedSize = $this->count = $this->savings = 0;
                $this->save = $save;
                $this->replace = $replace;
                $this->fix = $fix;
                $this->tmpDir = $tmpDir;
        }

        public function smushURL($url) {
                $this->url = $url;

                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, self::SMUSH_URL . 'img=' . $url);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
                $json_str = curl_exec($ch);
                curl_close($ch);

                return $this->parseResponse($json_str);
        }

        public function smushFile($filename) {
                $this->filename = $filename;

                if (!is_readable($filename)) {
                        $this->error = 'Could not read file';
                        JError::raiseError(404, JText::_('Smush: Could not read file'));
                        return false;
                }

                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, self::SMUSH_URL);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, array('files' => '@' . $filename));
                $json_str = curl_exec($ch);
                curl_close($ch);

                return $this->parseResponse($json_str);
        }

        private function parseResponse($json_str) {
                $this->compressedUrl = null;

                $this->error = null;
                $json = json_decode($json_str);

                if (is_null($json)) {
                        $this->error = 'Bad response from Smush.it web service';
                        JError::raiseError(404, JText::_('Smush: Bad response from Smush.it web service'));
                        return false;
                }

                if (isset($json->error)) {
                        $this->error = $json->error;
                        return false;
                }

                $this->compressedUrl = $json->dest;
                

                if ($this->save) {
                        jimport('joomla.filesystem.file');

                        $tmp_filename = tempnam($this->tmpDir, '__smush');
                        $fp = fopen($tmp_filename, 'w+');
                        $ch = curl_init($this->compressedUrl);
                        curl_setopt($ch, CURLOPT_FILE, $fp);
                        curl_exec($ch);
                        curl_close($ch);
                        fclose($fp);

                        if (file_exists($tmp_filename) && is_readable($tmp_filename) && filesize($tmp_filename) == $json->dest_size) {
                                $this->size += $json->src_size;
                                $this->compressedSize += $json->dest_size;
                                $this->savings += $json->percent;
                                $this->count++;
                                
                                $dst = '';

                                if ($this->replace) {
                                        if (JFile::delete($this->filename)) {
                                                $dst = $this->filename;
                                        } else {
                                                JError::raiseError(404, JText::_('Smush: Failed deleting original file') . ': ' . $this->filename);
                                                return false;
                                        }
                                } else {
                                        $ext = substr($this->filename, strrpos($this->filename, '.') + 1);
                                        $dst = str_replace('.' . $ext, $this->fix . '.' . $ext, $this->filename);
                                }

                                if (!JFile::move($tmp_filename, $dst)) {
                                        JError::raiseError(404, JText::_('Smush: Failed moving smushed file') . ': ' . $tmp_filename);
                                        return false;
                                }

                                return true;
                        } else {
                                JError::raiseError(404, JText::_('Smush: Could not download smushed version of ') . ': ' . $this->filename);
                                return false;
                        }
                }

                return true;
        }

}