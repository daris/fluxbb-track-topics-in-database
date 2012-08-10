<?php
/***********************************************************************/

// Some info about your mod.
$mod_title      = 'Track topics in database';
$mod_version    = '2.0';
$release_date   = '2012-08-10';
$author         = 'Daris';
$author_email   = 'daris91@gmail.com';

// Versions of FluxBB this mod was created for. A warning will be displayed, if versions do not match
$fluxbb_versions= array('1.5.0', '1.5-beta', '1.4.9', '1.4.8', '1.4.7');

// Set this to false if you haven't implemented the restore function (see below)
$mod_restore	= true;


// This following function will be called when the user presses the "Install" button
function install()
{
	global $db;

	$schema_forums_track = array(
		'FIELDS'			=> array(
				'user_id'	=> array(
						'datatype'			=> 'INT(10)',
						'allow_null'		=> false,
						'default'		=> '0'
				),
				'forum_id'	=> array(
						'datatype'			=> 'INT(10)',
						'allow_null'		=> false,
						'default'		=> '0'
				),
				'mark_time'	=> array(
						'datatype'			=> 'INT(10)',
						'allow_null'		=> false,
						'default'		=> '0'
				),
		),
		'PRIMARY KEY'		=> array('user_id', 'forum_id'),
	);
	$db->create_table('forums_track', $schema_forums_track) or error('Unable to create table', __FILE__, __LINE__, $db->error());


	$schema_topics_track = array(
		'FIELDS'			=> array(
				'user_id'	=> array(
						'datatype'			=> 'INT(10)',
						'allow_null'		=> false,
						'default'		=> '0'
				),
				'forum_id'	=> array(
						'datatype'			=> 'INT(10)',
						'allow_null'		=> false,
						'default'		=> '0'
				),
				'topic_id'	=> array(
						'datatype'			=> 'INT(10)',
						'allow_null'		=> false,
						'default'		=> '0'
				),
				'mark_time'	=> array(
						'datatype'			=> 'INT(10)',
						'allow_null'		=> false,
						'default'		=> '0'
				),
		),
		'PRIMARY KEY'		=> array('user_id', 'topic_id'),
		'INDEXES'		=> array(
			'forum_id_idx'		=> array('forum_id'),
		)
	);
	$db->create_table('topics_track', $schema_topics_track) or error('Unable to create table', __FILE__, __LINE__, $db->error());


	$db->add_field('users', 'last_mark', 'INT(10)', true, '0');
}

// This following function will be called when the user presses the "Restore" button (only if $mod_restore is true (see above))
function restore()
{
	global $db;

	$db->drop_field('users', 'last_mark');
	$db->drop_table('topics_track');
	$db->drop_table('forums_track');
}

/***********************************************************************/

// DO NOT EDIT ANYTHING BELOW THIS LINE!


// Circumvent maintenance mode
define('PUN_TURN_OFF_MAINT', 1);
define('PUN_ROOT', './');
require PUN_ROOT.'include/common.php';

// We want the complete error message if the script fails
if (!defined('PUN_DEBUG'))
	define('PUN_DEBUG', 1);

// Make sure we are running a FluxBB version that this mod works with
$version_warning = !in_array($pun_config['o_cur_version'], $fluxbb_versions);

$style = (isset($pun_user)) ? $pun_user['style'] : $pun_config['o_default_style'];

?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" lang="en" dir="ltr">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<title><?php echo pun_htmlspecialchars($mod_title) ?> installation</title>
<link rel="stylesheet" type="text/css" href="style/<?php echo $style.'.css' ?>" />
</head>
<body>

<div id="punwrap">
<div id="puninstall" class="pun" style="margin: 10% 20% auto 20%">

<?php

if (isset($_POST['form_sent']))
{
	if (isset($_POST['install']))
	{
		// Run the install function (defined above)
		install();

?>
<div class="block">
	<h2><span>Installation successful</span></h2>
	<div class="box">
		<div class="inbox">
			<p>Your database has been successfully prepared for <?php echo pun_htmlspecialchars($mod_title) ?>. See readme.txt for further instructions.</p>
		</div>
	</div>
</div>
<?php

	}
	else
	{
		// Run the restore function (defined above)
		restore();

?>
<div class="block">
	<h2><span>Restore successful</span></h2>
	<div class="box">
		<div class="inbox">
			<p>Your database has been successfully restored.</p>
		</div>
	</div>
</div>
<?php

	}
}
else
{

?>
<div class="blockform">
	<h2><span>Mod installation</span></h2>
	<div class="box">
		<form method="post" action="<?php echo $_SERVER['PHP_SELF'] ?>?foo=bar">
			<div><input type="hidden" name="form_sent" value="1" /></div>
			<div class="inform">
				<p>This script will update your database to work with the following modification:</p>
				<p><strong>Mod title:</strong> <?php echo pun_htmlspecialchars($mod_title.' '.$mod_version) ?></p>
				<p><strong>Author:</strong> <?php echo pun_htmlspecialchars($author) ?> (<a href="mailto:<?php echo pun_htmlspecialchars($author_email) ?>"><?php echo pun_htmlspecialchars($author_email) ?></a>)</p>
				<p><strong>Disclaimer:</strong> Mods are not officially supported by FluxBB. Mods generally can't be uninstalled without running SQL queries manually against the database. Make backups of all data you deem necessary before installing.</p>
<?php if ($mod_restore): ?>
				<p>If you've previously installed this mod and would like to uninstall it, you can click the Restore button below to restore the database.</p>
<?php endif; ?>
<?php if ($version_warning): ?>
				<p style="color: #a00"><strong>Warning:</strong> The mod you are about to install was not made specifically to support your current version of FluxBB (<?php echo $pun_config['o_cur_version']; ?>). This mod supports FluxBB versions: <?php echo pun_htmlspecialchars(implode(', ', $fluxbb_versions)); ?>. If you are uncertain about installing the mod due to this potential version conflict, contact the mod author.</p>
<?php endif; ?>
			</div>
			<p class="buttons"><input type="submit" name="install" value="Install" /><?php if ($mod_restore): ?><input type="submit" name="restore" value="Restore" /><?php endif; ?></p>
		</form>
	</div>
</div>
<?php

}

?>

</div>
</div>

</body>
</html>

<?php

// End the transaction
$db->end_transaction();

// Close the db connection (and free up any result data)
$db->close();
