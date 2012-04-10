<?php
//$Copyright$

defined( '_JEXEC' ) or die( 'Restricted access' );

$error = false;
$extensions = array();
$this->parent->getDBO =& $this->parent->getDBO();
$db = JFactory::getDBO();

// Create stored procedure
jimport('joomla.filesystem.file');
if (JFile::exists(dirname(__FILE__).'/sp.install.mysql.sql')) {
        if (class_exists('PDO')) {
                $sql = JFile::read(dirname(__FILE__).'/sp.install.mysql.sql');
                $sql = str_replace('DELIMITER //', '', $sql);
                $config = JFactory::getConfig();
                $dbh = new PDO('mysql:host='.$config->getValue('config.host').';dbname='.$config->getValue('config.db'), $config->getValue('config.user'), $config->getValue('config.password'));
                $sql = $db->replacePrefix($sql);
                $dbh->exec($sql);
                $status = $dbh->errorInfo();
                $sperror = isset($status[1]);
        } else {
                $sperror = true;
        }
} else {
	$sperror = false;
}

$j16 = version_compare(JVERSION, '1.6.0', 'ge');

// Gather additional extensions to be installed
$add = $j16 ? $this->manifest->xpath('additional') : $this->manifest->getElementByPath('additional');
if ($j16 && $add) $add = $add[0];

if ((is_a($add, 'JSimpleXMLElement') || is_a($add, 'JXMLElement')) && count($add->children())) {
        $exts =& $add->children();
        foreach ($exts as $ext) {
                $extensions[] = array(
                        'ename' => attr($ext, 'name'),
                        'name' => $ext->data(),
                        'type' => $ext->name(),
                        'folder' => $this->parent->getPath('source').'/'.attr($ext, 'folder', $j16),
                        'installer' => new JInstaller(),
                        'status' => false,
                        'enable' => $ext->name() == 'plugin' && attr($ext, 'enable') == '1',
                        'group' => attr($ext, 'group')
                );
        }
}

$aerror = false;

// Install additional extensions
for ($i = 0; $i < count($extensions); $i++) {
        $extension =& $extensions[$i];
        
        if ($extension['installer']->install($extension['folder'])) {
                $extension['status'] = true;
                if ($extension['type'] == 'plugin' && $extension['enable']) {
                        // Publish installed plugins
                        if ($j16) {
                                $query = "UPDATE `#__extensions` SET published = 1 WHERE type = 'plugin' AND folder = '".$db->Quote($extension['group'])."' AND element = '".$db->Quote($extension['name'])."'";
                        } else {
                                $query = "UPDATE `#__plugins` SET published = 1 WHERE folder = '".$db->Quote($extension['group'])."' AND element = '".$db->Quote($extension['name'])."'";
                        }
                        
                        $db->setQuery($query);
                        $status = $db->query();

                        if (!$status && ($db->getErrorNum() != 1060)) {
                                $aerror = true;
                        } 
                }
        } else {
                $error = true;
                break;
        }
}

// rollback on installation errors
if ($error) {
        $this->parent->abort(JText::_('Component').' '.JText::_('Install').': '.JText::_('Error'), 'component');
        
        for ($i = 0; $i < count($extensions); $i++) {
                if ($extensions[$i]['status']) {
                        $extensions[$i]['installer']->abort(JText::_($extensions[$i]['type']).' '.JText::_('Install').': '.JText::_('Error'), $extensions[$i]['type']);
                        $extensions[$i]['status'] = false;
                }
        }
} else {
        // jbetolo specific tasks
        
        require_once JPATH_SITE . '/plugins/system/' . ($j16 ? 'jbetolo/' : '').'jbetolo.php';
        
        // clean ev. earlier created jbetolo cache
        jbetoloHelper::resetCache();

        // if any default setting is given in metadata file of the plugin preload those settings
        jbetoloHelper::loadDefaultPredefinedSetting();

        // try reorder jbetolo to appear right before the system/cache plugin
        if ($j16) {
                $query = "SELECT ordering, (SELECT extension_id FROM #__extensions WHERE type = 'plugin' AND folder = 'system' AND element = 'jbetolo' LIMIT 1) AS jbetolo FROM #__extensions WHERE type = 'plugin' AND folder = 'system' AND element = 'cache' LIMIT 1";
        } else {
                $query = "SELECT ordering, (SELECT id FROM #__plugins WHERE folder = 'system' AND element = 'jbetolo' LIMIT 1) AS jbetolo FROM #__plugins WHERE folder = 'system' AND element = 'cache' LIMIT 1";
        }

        $db->setQuery($query);
        $rec = $db->loadObject();

        if ($rec && $rec->ordering) {
                $ord = $rec->ordering - 1;
                $row = JTable::getInstance($j16 ? 'extension' : 'plugin');
                $row->load($rec->jbetolo);
                $row->ordering = $ord;
                $row->store();
        }
}

function attr($ext, $name, $j16) {
        return $j16 ? $ext->getAttribute($name) : $ext->attributes($name);
}

?>

<h3><?php echo JText::_('Extensions'); ?></h3>
<table class="adminlist">
	<thead>
		<tr>
			<th class="title"><?php echo JText::_('Extension'); ?></th>
			<th width="60%"><?php echo JText::_('Status'); ?></th>
		</tr>
	</thead>
	<tfoot>
		<tr>
			<td colspan="2">&nbsp;</td>
		</tr>
	</tfoot>
	<tbody>
                <?php if (isset($sperror) && $sperror) : ?>
			<tr>
                                <td colspan="2">
                                        <div class="error"><?php echo JText::_('Stored procedure creation failed. Please create manually from the file sp.install.mysql.sql.'); ?></div>
                                </td>
			</tr>
		<?php endif; ?>
                <?php if (isset($aerror) && $aerror) : ?>
			<tr>
                                <td colspan="2">
                                        <div class="error"><?php echo JText::_('Was unable to publish some required plugins. Please make sure to publish all plugins.'); ?></div>
                                </td>
			</tr>
		<?php endif; ?>                
		<?php foreach ($extensions as $i => $ext) : ?>
			<tr class="row<?php echo $i % 2; ?>">
				<td class="key"><?php echo $ext['name']; ?> (<?php echo JText::_($ext['type']); ?>)</td>
				<td>
					<?php $style = $ext['status'] ? 'font-weight: bold; color: green;' : 'font-weight: bold; color: red;'; ?>
					<span style="<?php echo $style; ?>"><?php echo $ext['status'] ? JText::_('Installed successfully') : JText::_('NOT Installed'); ?></span>
				</td>
			</tr>
		<?php endforeach; ?>
	</tbody>
</table>