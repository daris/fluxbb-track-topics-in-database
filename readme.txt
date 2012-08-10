##
##
##        Mod title:  Track topics in database
##
##      Mod version:  1.1.1
##  Works on FluxBB:  1.4.8, 1.4.7, 1.4.6, 1.4.5, 1.4.4
##     Release date:  2012-01-21
##      Review date:  2012-01-21
##           Author:  Daris (daris91@gmail.com)
##
##      Description:  Topics and forums you have read since your last logon
##                    are saved in the database instead of a tracking cookie
##                    (useful when you log in from different locations and
##                    your board has a long visit timeout)
##
##Easy installation:  This mod can be easily installed via Patcher -> https://github.com/daris/fluxbb-patcher
##
## Upgrade from 1.x:  Restore orginal files (look into previous version readme.txt and do steps in reverse order)
##                    Then install v2.0 - it'll automatically convert tracking data from old storage method
##
##   Repository URL:  http://fluxbb.org/resources/mods/track-topics-in-database/
##
##   Affected files:  include/functions.php
##                    include/common.php
##                    index.php
##                    login.php
##                    misc.php
##                    viewforum.php
##                    viewtopic.php
##                    admin_forums.php
##                    moderate.php
##                    post.php
##                    register.php
##                    search.php
##
##       Affects DB:  Yes
##
##       DISCLAIMER:  Please note that "mods" are not officially supported by
##                    FluxBB. Installation of this modification is done at
##                    your own risk. Backup your forum database and any and
##                    all applicable files before proceeding.
##<?
##


#
#---------[ 1. UPLOAD ]-------------------------------------------------------
#

install_mod.php to /

#
#---------[ 2. RUN ]----------------------------------------------------------
#

install_mod.php

#
#---------[ 3. DELETE ]-------------------------------------------------------
#

install_mod.php

#
#---------[ 4. OPEN ]---------------------------------------------------------
#

include/functions.php

#
#---------[ 5. FIND ]---------------------------------------------
#

				// Reset tracked topics
				set_tracked_topics(null);
#
#---------[ 5. REPLACE WITH ]---------------------------------------------
#

				// Reset tracked topics
				// set_tracked_topics(null);

#
#---------[ 7. FIND (line: 208) ]---------------------------------------------
#

				// Update tracked topics with the current expire time
				if (isset($_COOKIE[$cookie_name.'_track']))
					forum_setcookie($cookie_name.'_track', $_COOKIE[$cookie_name.'_track'], $now + $pun_config['o_timeout_visit']);

#
#---------[ 8. REPLACE WITH (just delete the above line) ]---------------------------------------------------
#
				// Update tracked topics with the current expire time
				// if (isset($_COOKIE[$cookie_name.'_track']))
				// 	forum_setcookie($cookie_name.'_track', $_COOKIE[$cookie_name.'_track'], $now + $pun_config['o_timeout_visit']);

#
#---------[ 5. AT THE END OF FILE ADD ]---------------------------------------------
#

//
// Mark as read specified data (all, forum, topic)
// This function is very much inspired by the markread()
// from the phpBB Group forum software phpBB3 (http://www.phpbb.com)
//
function mark_read($mode, $forum_id = false, $topic_id = false, $post_time = 0, $last_post = 0, $mark_time = false)
{
	global $db, $pun_user;

	if ($mode == 'all')
	{
		if ($forum_id !== false || !empty($forum_id))
			return false;

		$db->query('DELETE FROM '.$db->prefix.'topics_track WHERE user_id = '.$pun_user['id']) or error('Unable to delete topics track', __FILE__, __LINE__, $db->error());
		$db->query('DELETE FROM '.$db->prefix.'forums_track WHERE user_id = '.$pun_user['id']) or error('Unable to delete forums track', __FILE__, __LINE__, $db->error());
		$db->query('UPDATE '.$db->prefix.'users SET last_mark = '.time().' WHERE id = '.$pun_user['id']) or error('Unable to update last mark value', __FILE__, __LINE__, $db->error());
	}
	else if ($mode == 'forum')
	{
		// Mark all topics in forums read
		// TODO: Do we need forums array? Will subforums be implemented in 2.0?
		if (!is_array($forum_id))
			$forum_id = array($forum_id);

		$forum_id_sql = 'IN ('.implode(', ', $forum_id).')';

		// Check whether there are some unread topics
		$result = $db->query('SELECT 1 FROM '.$db->prefix.'topics AS t
			LEFT JOIN '.$db->prefix.'topics_track AS tt ON tt.user_id = '.$pun_user['id'].' AND t.id = tt.topic_id
			LEFT JOIN '.$db->prefix.'forums_track AS ft ON ft.user_id = '.$pun_user['id'].' AND t.forum_id = ft.forum_id
			WHERE t.forum_id NOT '.$forum_id_sql.' AND t.last_post > '.$pun_user['last_mark'].' AND (
					(tt.mark_time IS NOT NULL AND t.last_post > tt.mark_time) OR
					(tt.mark_time IS NULL AND ft.mark_time IS NOT NULL AND t.last_post > ft.mark_time) OR
					(tt.mark_time IS NULL AND ft.mark_time IS NULL))
			LIMIT 1') or error('Unable to check for unread topics', __FILE__, __LINE__, $db->error());

		// If there aren't, delete user's topic and forum tracks and update user's last_mark value
		if (!$db->num_rows($result))
		{
			mark_read('all');
			return false;
		}

		// Delete user's topic track entries
		$db->query('DELETE FROM '.$db->prefix.'topics_track WHERE user_id = '.$pun_user['id'].' AND forum_id '.$forum_id_sql) or error('Unable to delete topics track', __FILE__, __LINE__, $db->error());

		// Update forum last mark value for the current user (or insert when it does not exist)
		foreach ($forum_id as $fid)
		{
			$result = $db->query('SELECT 1 FROM '.$db->prefix.'forums_track WHERE user_id = '.$pun_user['id'].' AND forum_id = '.$fid) or error('Unable to get forums track', __FILE__, __LINE__, $db->error());
			if ($db->num_rows($result))
				$db->query('UPDATE '.$db->prefix.'forums_track SET mark_time = '.time().' WHERE user_id = '.$pun_user['id'].' AND forum_id = '.$fid) or error('Unable to update forums track', __FILE__, __LINE__, $db->error());
			else
				$db->query('INSERT INTO '.$db->prefix.'forums_track (user_id, forum_id, mark_time) VALUES('.$pun_user['id'].', '.$fid.', '.time().')') or error('Unable to insert forums track', __FILE__, __LINE__, $db->error());
		}
	}
	else if ($mode == 'topic')
	{
		if (!$post_time)
			$post_time = time();

		// Determine the users last forum mark time if not given.
		if ($mark_time === false)
			$mark_time = $pun_user['last_mark'];

		// Update topic track last mark value (or insert when it does not exist)
		$result = $db->query('SELECT 1 FROM '.$db->prefix.'topics_track WHERE user_id = '.$pun_user['id'].' AND forum_id = '.$forum_id.' AND topic_id = '.$topic_id) or error('Unable to get topics track', __FILE__, __LINE__, $db->error());
		if ($db->num_rows($result))
			$db->query('UPDATE '.$db->prefix.'topics_track SET mark_time = '.time().' WHERE user_id = '.$pun_user['id'].' AND forum_id = '.$forum_id.' AND topic_id = '.$topic_id) or error('Unable to update topics track', __FILE__, __LINE__, $db->error());
		else
			$db->query('INSERT INTO '.$db->prefix.'topics_track (user_id, forum_id, topic_id, mark_time) VALUES('.$pun_user['id'].', '.$forum_id.', '.$topic_id.', '.time().')') or error('Unable to insert topics track', __FILE__, __LINE__, $db->error());

		// Check the forum for any left unread topics (if we do not mark forum as read before).
		// If there are none, we mark the forum as read.
		if ($mark_time < $last_post)
		{
			$result = $db->query('SELECT 1 FROM '.$db->prefix.'topics AS t
				LEFT JOIN '.$db->prefix.'topics_track AS tt ON tt.user_id = '.$pun_user['id'].' AND t.id = tt.topic_id
				WHERE t.forum_id = '.$forum_id.' AND t.last_post > '.$mark_time.' AND t.moved_to IS NULL AND (tt.topic_id IS NULL OR tt.mark_time < t.last_post)
				LIMIT 1') or error('Unable to get unread topics', __FILE__, __LINE__, $db->error());

			if (!$db->num_rows($result))
				mark_read('forum', $forum_id);
		}
	}
}

//
// Get tracked topics by using already fetched info
// This function is very much inspired by the get_topic_tracking_info()
// from the phpBB Group forum software phpBB3 (http://www.phpbb.com)
//
function get_topic_tracking_info($forum_id, $topic_ids, $topic_list, $forum_mark_time)
{
	global $pun_user;

	$last_read = array();

	if (!is_array($topic_ids))
		$topic_ids = array($topic_ids);

	foreach ($topic_ids as $topic_id)
	{
		if (!empty($topic_list[$topic_id]['mark_time']))
			$last_read[$topic_id] = $topic_list[$topic_id]['mark_time'];
	}

	$topic_ids = array_diff($topic_ids, array_keys($last_read));

	if (!empty($topic_ids))
	{
		$mark_time = array();

		if (!empty($forum_mark_time[$forum_id]) && $forum_mark_time[$forum_id] !== false)
			$mark_time[$forum_id] = $forum_mark_time[$forum_id];

		$user_lastmark = (isset($mark_time[$forum_id])) ? $mark_time[$forum_id] : $pun_user['last_mark'];

		foreach ($topic_ids as $topic_id)
			$last_read[$topic_id] = $user_lastmark;
	}

	return $last_read;
}

//
// Move tracked topics from cookie to database
//
function convert_tracked_topics()
{
	global $db, $pun_user, $cookie_name;

	// We need to convert only at user first login
	if ($pun_user['last_mark'] != 0)
		return false;

	// Get tracked topics from users table (Track topics in database v1.x)
	if (isset($pun_user['tracked_topics']))
		$tracked_topics = empty($pun_user['tracked_topics']) ? array('forums' => array(), 'topics' => array()) : unserialize($pun_user['tracked_topics']);

	// or load from cookie (FluxBB method)
	else
		$tracked_topics = get_tracked_topics();

	$result = $db->query('SELECT 1 FROM '.$db->prefix.'topics_track WHERE user_id = '.$pun_user['id']) or error('Unable to get topics track', __FILE__, __LINE__, $db->error());
	// User have already his tracked topic settings in database
	if ($db->num_rows($result))
		return false;

	$result = $db->query('SELECT 1 FROM '.$db->prefix.'forums_track WHERE user_id = '.$pun_user['id']) or error('Unable to get forums track', __FILE__, __LINE__, $db->error());
	// User have already his tracked forums settings in database
	if ($db->num_rows($result))
		return false;

	$pun_user['last_mark'] = $pun_user['last_visit'];
	$db->query('UPDATE '.$db->prefix.'users SET last_mark = '.$pun_user['last_mark'].' WHERE id = '.$pun_user['id']) or error('Unable to update user', __FILE__, __LINE__, $db->error());

	foreach ($tracked_topics['forums'] as $cur_forum => $timestamp)
		$db->query('INSERT INTO '.$db->prefix.'forums_track (user_id, forum_id, mark_time) VALUES('.$pun_user['id'].', '.$cur_forum.', '.$timestamp.')') or error('Unable to insert tracked forums', __FILE__, __LINE__, $db->error());

	foreach ($tracked_topics['topics'] as $topic_id => $timestamp)
	{
		$result = $db->query('SELECT forum_id, last_post FROM '.$db->prefix.'topics WHERE id = '.$topic_id) or error('Unable to get topic info', __FILE__, __LINE__, $db->error());
		$cur_topic = $db->fetch_assoc($result);

		$result = $db->query('SELECT mark_time FROM '.$db->prefix.'forums_track WHERE forum_id = '.$cur_topic['forum_id']) or error('Unable to get topics track', __FILE__, __LINE__, $db->error());
		$forum_mark_time = false;
		if ($db->num_rows($result))
			$forum_mark_time = intval($db->result($result));

		mark_read('topic', $cur_topic['forum_id'], $topic_id, $timestamp, $cur_topic['last_post'], $forum_mark_time);
	}

	// Delete cookie
	if (isset($_COOKIE[$cookie_name.'_track']))
	{
		forum_setcookie($cookie_name.'_track', '', time() - 3600);
		unset($_COOKIE[$cookie_name.'_track']);
	}
}

#
#---------[ 7. FIND (line: 208) ]---------------------------------------------
#

	// Delete any subscriptions for this topic
	$db->query('DELETE FROM '.$db->prefix.'topic_subscriptions WHERE topic_id='.$topic_id) or error('Unable to delete subscriptions', __FILE__, __LINE__, $db->error());

#
#---------[ 8. AFTER ADD ]---------------------------------------------------
#

	$db->query('DELETE FROM '.$db->prefix.'topics_track WHERE topic_id = '.$topic_id) or error('Unable to delete topics track', __FILE__, __LINE__, $db->error());

#
#---------[ 7. OPEN ]---------------------------------------------
#

include/common.php


#
#---------[ 7. FIND (line: 208) ]---------------------------------------------
#

	define('FORUM_MAX_COOKIE_SIZE', 4048);

#
#---------[ 8. AFTER ADD ]---------------------------------------------------
#

// Move tracked topics from cookie to database
if (!$pun_user['is_guest'])
	convert_tracked_topics();

#
#---------[ 7. OPEN ]---------------------------------------------
#

index.php

#
#---------[ 7. FIND (line: 208) ]---------------------------------------------
#

	$tracked_topics = get_tracked_topics();

#
#---------[ 8. REPLACE WITH (just delete the above line) ]---------------------------------------------------
#

	// $tracked_topics = get_tracked_topics();

#
#---------[ 7. FIND (line: 208) ]---------------------------------------------
#

$result = $db->query('SELECT c.id AS cid, c.cat_name, f.id AS fid, f.forum_name, f.forum_desc, f.redirect_url, f.moderators, f.num_topics, f.num_posts, f.last_post, f.last_post_id, f.last_poster FROM '.$db->prefix.'categories AS c INNER JOIN '.$db->prefix.'forums AS f ON c.id=f.cat_id LEFT JOIN '.$db->prefix.'forum_perms AS fp ON (fp.forum_id=f.id AND fp.group_id='.$pun_user['g_id'].') WHERE fp.read_forum IS NULL OR fp.read_forum=1 ORDER BY c.disp_position, c.id, f.disp_position', true) or error('Unable to fetch category/forum list', __FILE__, __LINE__, $db->error());

#
#---------[ 8. REPLACE WITH ]---------------------------------------------------
#

$sql_mark_time = $sql_forums_track = '';
if (!$pun_user['is_guest'])
{
	// Forum tracking
	$sql_mark_time = ', ft.mark_time AS forum_mark_time';
	$sql_forums_track = ' LEFT JOIN '.$db->prefix.'forums_track AS ft ON ft.user_id = '.$pun_user['id'].' AND f.id = ft.forum_id';
}
$result = $db->query('SELECT c.id AS cid, c.cat_name, f.id AS fid, f.forum_name, f.forum_desc, f.redirect_url, f.moderators, f.num_topics, f.num_posts, f.last_post, f.last_post_id, f.last_poster'.$sql_mark_time.' FROM '.$db->prefix.'categories AS c INNER JOIN '.$db->prefix.'forums AS f ON c.id=f.cat_id LEFT JOIN '.$db->prefix.'forum_perms AS fp ON (fp.forum_id=f.id AND fp.group_id='.$pun_user['g_id'].')'.$sql_forums_track.' WHERE fp.read_forum IS NULL OR fp.read_forum=1 ORDER BY c.disp_position, c.id, f.disp_position', true) or error('Unable to fetch category/forum list', __FILE__, __LINE__, $db->error());

#
#---------[ 5. FIND (line: 10) ]---------------------------------------------
#

	// Are there new posts since our last visit?
	if (!$pun_user['is_guest'] && $cur_forum['last_post'] > $pun_user['last_visit'] && (empty($tracked_topics['forums'][$cur_forum['fid']]) || $cur_forum['last_post'] > $tracked_topics['forums'][$cur_forum['fid']]))
	{
		// There are new posts in this forum, but have we read all of them already?
		foreach ($new_topics[$cur_forum['fid']] as $check_topic_id => $check_last_post)
		{
			if ((empty($tracked_topics['topics'][$check_topic_id]) || $tracked_topics['topics'][$check_topic_id] < $check_last_post) && (empty($tracked_topics['forums'][$cur_forum['fid']]) || $tracked_topics['forums'][$cur_forum['fid']] < $check_last_post))
			{
				$item_status .= ' inew';
				$forum_field_new = '<span class="newtext">[ <a href="search.php?action=show_new&amp;fid='.$cur_forum['fid'].'">'.$lang_common['New posts'].'</a> ]</span>';
				$icon_type = 'icon icon-new';

				break;
			}
		}
	}

#
#---------[ 6. REPLACE WITH ]-------------------------------------------------
#

	// Are there new posts since our last visit?
	if (!$pun_user['is_guest'])
	{
		 if (empty($cur_forum['forum_mark_time']))
			$cur_forum['forum_mark_time'] = $pun_user['last_mark'];

		if ($cur_forum['last_post'] > $cur_forum['forum_mark_time'])
		{
			$item_status .= ' inew';
			$forum_field_new = '<span class="newtext">[ <a href="search.php?action=show_new&amp;fid='.$cur_forum['fid'].'">'.$lang_common['New posts'].'</a> ]</span>';
			$icon_type = 'icon icon-new';
		}
	}

#
#---------[ 7. OPEN ]---------------------------------------------
#

login.php

#
#---------[ 7. FIND (line: 208) ]---------------------------------------------
#

	set_tracked_topics(null);

#
#---------[ 8. REPLACE WITH (just delete the above line) ]---------------------------------------------------
#

 	// set_tracked_topics(null);

#
#---------[ 7. OPEN ]---------------------------------------------
#

misc.php

#
#---------[ 7. FIND (line: 208) ]---------------------------------------------
#

	$db->query('UPDATE '.$db->prefix.'users SET last_visit='.$pun_user['logged'].' WHERE id='.$pun_user['id']) or error('Unable to update user last visit data', __FILE__, __LINE__, $db->error());

	// Reset tracked topics
	set_tracked_topics(null);

#
#---------[ 8. REPLACE WITH ]---------------------------------------------------
#

	mark_read('all');

#
#---------[ 7. FIND (line: 208) ]---------------------------------------------
#

	$tracked_topics = get_tracked_topics();
	$tracked_topics['forums'][$fid] = time();
	set_tracked_topics($tracked_topics);

#
#---------[ 8. REPLACE WITH ]---------------------------------------------------
#

	mark_read('forum', $fid);

#
#---------[ 7. OPEN ]---------------------------------------------
#

viewforum.php

#
#---------[ 7. FIND (line: 208) ]---------------------------------------------
#

// Get topic/forum tracking data
if (!$pun_user['is_guest'])
	$tracked_topics = get_tracked_topics();

#
#---------[ 8. REPLACE WITH (just delete the above lines) ]---------------------------------------------------
#

// Get topic/forum tracking data
// if (!$pun_user['is_guest'])
// 	$tracked_topics = get_tracked_topics();

#
#---------[ 7. FIND (line: 208) ]---------------------------------------------
#

	$result = $db->query('SELECT f.forum_name, f.redirect_url, f.moderators, f.num_topics, f.sort_by, fp.post_topics, s.user_id AS is_subscribed FROM '.$db->prefix.'forums AS f LEFT JOIN '.$db->prefix.'forum_subscriptions AS s ON (f.id=s.forum_id AND s.user_id='.$pun_user['id'].') LEFT JOIN '.$db->prefix.'forum_perms AS fp ON (fp.forum_id=f.id AND fp.group_id='.$pun_user['g_id'].') WHERE (fp.read_forum IS NULL OR fp.read_forum=1) AND f.id='.$id) or error('Unable to fetch forum info', __FILE__, __LINE__, $db->error());

#
#---------[ 8. REPLACE WITH ]---------------------------------------------------
#

	$result = $db->query('SELECT f.forum_name, f.redirect_url, f.moderators, f.num_topics, f.sort_by, fp.post_topics, s.user_id AS is_subscribed, ft.mark_time AS forum_mark_time FROM '.$db->prefix.'forums AS f LEFT JOIN '.$db->prefix.'forum_subscriptions AS s ON (f.id=s.forum_id AND s.user_id='.$pun_user['id'].') LEFT JOIN '.$db->prefix.'forum_perms AS fp ON (fp.forum_id=f.id AND fp.group_id='.$pun_user['g_id'].') LEFT JOIN '.$db->prefix.'forums_track AS ft ON ft.user_id = '.$pun_user['id'].' AND f.id = ft.forum_id WHERE (fp.read_forum IS NULL OR fp.read_forum=1) AND f.id='.$id) or error('Unable to fetch forum info', __FILE__, __LINE__, $db->error());

#
#---------[ 5. FIND (line: 10) ]---------------------------------------------
#

	// Fetch list of topics to display on this page
	if ($pun_user['is_guest'] || $pun_config['o_show_dot'] == '0')
	{
		// Without "the dot"
		$sql = 'SELECT id, poster, subject, posted, last_post, last_post_id, last_poster, num_views, num_replies, closed, sticky, moved_to FROM '.$db->prefix.'topics WHERE id IN('.implode(',', $topic_ids).') ORDER BY sticky DESC, '.$sort_by.', id DESC';
	}
	else
	{
		// With "the dot"
		$sql = 'SELECT p.poster_id AS has_posted, t.id, t.subject, t.poster, t.posted, t.last_post, t.last_post_id, t.last_poster, t.num_views, t.num_replies, t.closed, t.sticky, t.moved_to FROM '.$db->prefix.'topics AS t LEFT JOIN '.$db->prefix.'posts AS p ON t.id=p.topic_id AND p.poster_id='.$pun_user['id'].' WHERE t.id IN('.implode(',', $topic_ids).') GROUP BY t.id'.($db_type == 'pgsql' ? ', t.subject, t.poster, t.posted, t.last_post, t.last_post_id, t.last_poster, t.num_views, t.num_replies, t.closed, t.sticky, t.moved_to, p.poster_id' : '').' ORDER BY t.sticky DESC, t.'.$sort_by.', t.id DESC';
	}

#
#---------[ 6. REPLACE WITH ]-------------------------------------------------
#

	$sql_mark_time = $sql_topics_track = '';
	if (!$pun_user['is_guest'])
	{
		// Forum tracking
		$sql_mark_time = ', tt.mark_time';
		$sql_topics_track = ' LEFT JOIN '.$db->prefix.'topics_track AS tt ON tt.user_id = '.$pun_user['id'].' AND t.id = tt.topic_id';
	}

	// Fetch list of topics to display on this page
	if ($pun_user['is_guest'] || $pun_config['o_show_dot'] == '0')
	{
		// Without "the dot"
		$sql = 'SELECT id, poster, subject, posted, last_post, last_post_id, last_poster, num_views, num_replies, closed, sticky, moved_to'.$sql_mark_time.' FROM '.$db->prefix.'topics'.$sql_topics_track.' WHERE id IN('.implode(',', $topic_ids).') ORDER BY sticky DESC, '.$sort_by.', id DESC';
	}
	else
	{
		// With "the dot"
		$sql = 'SELECT p.poster_id AS has_posted, t.id, t.subject, t.poster, t.posted, t.last_post, t.last_post_id, t.last_poster, t.num_views, t.num_replies, t.closed, t.sticky, t.moved_to'.$sql_mark_time.' FROM '.$db->prefix.'topics AS t LEFT JOIN '.$db->prefix.'posts AS p ON t.id=p.topic_id AND p.poster_id='.$pun_user['id'].$sql_topics_track.' WHERE t.id IN('.implode(',', $topic_ids).') GROUP BY t.id'.($db_type == 'pgsql' ? ', t.subject, t.poster, t.posted, t.last_post, t.last_post_id, t.last_poster, t.num_views, t.num_replies, t.closed, t.sticky, t.moved_to, p.poster_id' : '').' ORDER BY t.sticky DESC, t.'.$sort_by.', t.id DESC';
	}

#
#---------[ 7. FIND (line: 208) ]---------------------------------------------
#

	$topic_count = 0;
	while ($cur_topic = $db->fetch_assoc($result))

#
#---------[ 8. REPLACE WITH ]---------------------------------------------------
#

	$topics = array();
	while ($cur_topic = $db->fetch_assoc($result))
		$topics[] = $cur_topic;

	// Get tracked topics
	if (!$pun_user['is_guest'])
	{
		// Generate topic list...
		$topic_list = array();
		foreach ($topics as $cur_topic)
			$topic_list[$cur_topic['id']] = $cur_topic;

		$tracked_topics = get_topic_tracking_info($id, $topic_ids, $topic_list, array($id => $cur_forum['forum_mark_time']), false);
	}

	$topic_count = 0;
	foreach ($topics as $cur_topic)

#
#---------[ 7. FIND (line: 208) ]---------------------------------------------
#

		if (!$pun_user['is_guest'] && $cur_topic['last_post'] > $pun_user['last_visit'] && (!isset($tracked_topics['topics'][$cur_topic['id']]) || $tracked_topics['topics'][$cur_topic['id']] < $cur_topic['last_post']) && (!isset($tracked_topics['forums'][$id]) || $tracked_topics['forums'][$id] < $cur_topic['last_post']) && is_null($cur_topic['moved_to']))

#
#---------[ 8. REPLACE WITH ]---------------------------------------------------
#

		if (!$pun_user['is_guest'] && isset($tracked_topics[$cur_topic['id']]) && $cur_topic['last_post'] > $tracked_topics[$cur_topic['id']] && $cur_topic['moved_to'] == null)

#
#---------[ 7. OPEN ]---------------------------------------------
#

viewtopic.php

#
#---------[ 7. FIND (line: 208) ]---------------------------------------------
#

		// We need to check if this topic has been viewed recently by the user
		$tracked_topics = get_tracked_topics();
		$last_viewed = isset($tracked_topics['topics'][$id]) ? $tracked_topics['topics'][$id] : $pun_user['last_visit'];

		$result = $db->query('SELECT MIN(id) FROM '.$db->prefix.'posts WHERE topic_id='.$id.' AND posted>'.$last_viewed) or error('Unable to fetch first new post info', __FILE__, __LINE__, $db->error());

#
#---------[ 8. REPLACE WITH ]---------------------------------------------------
#

		// Check whether there are some unread topics
		$result = $db->query('SELECT MIN(p.id) AS new_pid FROM '.$db->prefix.'posts AS p
			LEFT JOIN '.$db->prefix.'topics AS t ON t.id = p.topic_id
			LEFT JOIN '.$db->prefix.'topics_track AS tt ON tt.user_id = '.$pun_user['id'].' AND t.id = tt.topic_id
			LEFT JOIN '.$db->prefix.'forums_track AS ft ON ft.user_id = '.$pun_user['id'].' AND t.forum_id = ft.forum_id
			WHERE p.topic_id = '.$id.' AND p.posted > '.$pun_user['last_mark'].' AND (
					(tt.mark_time IS NOT NULL AND p.posted > tt.mark_time) OR
					(tt.mark_time IS NULL AND ft.mark_time IS NOT NULL AND p.posted > ft.mark_time) OR
					(tt.mark_time IS NULL AND ft.mark_time IS NULL))
			LIMIT 1');

#
#---------[ 7. FIND (line: 208) ]---------------------------------------------
#

if (!$pun_user['is_guest'])
	$result = $db->query('SELECT t.subject, t.closed, t.num_replies, t.sticky, t.first_post_id, f.id AS forum_id, f.forum_name, f.moderators, fp.post_replies, s.user_id AS is_subscribed FROM '.$db->prefix.'topics AS t INNER JOIN '.$db->prefix.'forums AS f ON f.id=t.forum_id LEFT JOIN '.$db->prefix.'topic_subscriptions AS s ON (t.id=s.topic_id AND s.user_id='.$pun_user['id'].') LEFT JOIN '.$db->prefix.'forum_perms AS fp ON (fp.forum_id=f.id AND fp.group_id='.$pun_user['g_id'].') WHERE (fp.read_forum IS NULL OR fp.read_forum=1) AND t.id='.$id.' AND t.moved_to IS NULL') or error('Unable to fetch topic info', __FILE__, __LINE__, $db->error());
else
	$result = $db->query('SELECT t.subject, t.closed, t.num_replies, t.sticky, t.first_post_id, f.id AS forum_id, f.forum_name, f.moderators, fp.post_replies, 0 AS is_subscribed FROM '.$db->prefix.'topics AS t INNER JOIN '.$db->prefix.'forums AS f ON f.id=t.forum_id LEFT JOIN '.$db->prefix.'forum_perms AS fp ON (fp.forum_id=f.id AND fp.group_id='.$pun_user['g_id'].') WHERE (fp.read_forum IS NULL OR fp.read_forum=1) AND t.id='.$id.' AND t.moved_to IS NULL') or error('Unable to fetch topic info', __FILE__, __LINE__, $db->error());

#
#---------[ 8. REPLACE WITH ]---------------------------------------------------
#

if (!$pun_user['is_guest'])
	$result = $db->query('SELECT t.subject, t.closed, t.num_replies, t.sticky, t.first_post_id, t.last_post, f.id AS forum_id, f.forum_name, f.moderators, fp.post_replies, s.user_id AS is_subscribed, tt.mark_time, ft.mark_time as forum_mark_time FROM '.$db->prefix.'topics AS t INNER JOIN '.$db->prefix.'forums AS f ON f.id=t.forum_id LEFT JOIN '.$db->prefix.'topic_subscriptions AS s ON (t.id=s.topic_id AND s.user_id='.$pun_user['id'].') LEFT JOIN '.$db->prefix.'forum_perms AS fp ON (fp.forum_id=f.id AND fp.group_id='.$pun_user['g_id'].') LEFT JOIN '.$db->prefix.'topics_track AS tt ON tt.user_id = '.$pun_user['id'].' AND t.id = tt.topic_id LEFT JOIN '.$db->prefix.'forums_track AS ft ON ft.user_id = '.$pun_user['id'].' AND t.forum_id = ft.forum_id WHERE (fp.read_forum IS NULL OR fp.read_forum=1) AND t.id='.$id.' AND t.moved_to IS NULL') or error('Unable to fetch topic info', __FILE__, __LINE__, $db->error());
else
	$result = $db->query('SELECT t.subject, t.closed, t.num_replies, t.sticky, t.first_post_id, t.last_post, f.id AS forum_id, f.forum_name, f.moderators, fp.post_replies, 0 AS is_subscribed FROM '.$db->prefix.'topics AS t INNER JOIN '.$db->prefix.'forums AS f ON f.id=t.forum_id LEFT JOIN '.$db->prefix.'forum_perms AS fp ON (fp.forum_id=f.id AND fp.group_id='.$pun_user['g_id'].') WHERE (fp.read_forum IS NULL OR fp.read_forum=1) AND t.id='.$id.' AND t.moved_to IS NULL') or error('Unable to fetch topic info', __FILE__, __LINE__, $db->error());

#
#---------[ 5. FIND (line: 10) ]---------------------------------------------
#

// Add/update this topic in our list of tracked topics
if (!$pun_user['is_guest'])
{
	$tracked_topics = get_tracked_topics();
	$tracked_topics['topics'][$id] = time();
	set_tracked_topics($tracked_topics);
}

#
#---------[ 6. REPLACE WITH ]-------------------------------------------------
#

// // Add/update this topic in our list of tracked topics
// if (!$pun_user['is_guest'])
// {
// 	$tracked_topics = get_tracked_topics();
// 	$tracked_topics['topics'][$id] = time();
// 	set_tracked_topics($tracked_topics);
// }

#
#---------[ 7. FIND (line: 208) ]---------------------------------------------
#

while ($cur_post = $db->fetch_assoc($result))

#
#---------[ 8. BEFORE ADD ]---------------------------------------------------
#

// Get tracked topics
if (!$pun_user['is_guest'])
	$tracked_topics = get_topic_tracking_info($cur_topic['forum_id'], $id, array($id => $cur_topic), array($cur_topic['forum_id'] => $cur_topic['forum_mark_time']));

#
#---------[ 7. FIND (line: 208) ]---------------------------------------------
#

	$signature = '';

#
#---------[ 8. AFTER ADD ]---------------------------------------------------
#

	$icon_type = 'icon';

	if ($cur_post['posted'] > $max_post_time)
		$max_post_time = $cur_post['posted'];

#
#---------[ 7. FIND (line: 208) ]---------------------------------------------
#

	// Perform the main parsing of the message (BBCode, smilies, censor words etc)
	$cur_post['message'] = parse_message($cur_post['message'], $cur_post['hide_smilies']);

#
#---------[ 8. BEFORE ADD ]---------------------------------------------------
#

	if (!$pun_user['is_guest'] && isset($tracked_topics[$id]) && $cur_post['posted'] > $tracked_topics[$id])
	{
		$item_status = 'inew';
		$icon_type = 'icon icon-new';
		$icon_text = $lang_common['New icon'];
	}
	else
	{
		$item_status = '';
		$icon_text = '<!-- -->';
	}


#
#---------[ 7. FIND (line: 208) ]---------------------------------------------
#

<div id="p<?php echo $cur_post['id'] ?>" class="blockpost<?php echo ($post_count % 2 == 0) ? ' roweven' : ' rowodd' ?><?php if ($cur_post['id'] == $cur_topic['first_post_id']) echo ' firstpost'; ?><?php if ($post_count == 1) echo ' blockpost1'; ?>">
	<h2><span><span class="conr">#<?php echo ($start_from + $post_count) ?></span> <a href="viewtopic.php?pid=<?php echo $cur_post['id'].'#p'.$cur_post['id'] ?>"><?php echo format_time($cur_post['posted']) ?></a></span></h2>

#
#---------[ 8. REPLACE WITH ]---------------------------------------------------
#

<div id="p<?php echo $cur_post['id'] ?>" class="blockpost<?php echo ($post_count % 2 == 0) ? ' roweven' : ' rowodd' ?><?php if ($cur_post['id'] == $cur_topic['first_post_id']) echo ' firstpost'; ?><?php if ($post_count == 1) echo ' blockpost1'; ?><?php if ($item_status != '') echo ' '.$item_status ?>">
	<h2><span><span class="conr">#<?php echo ($start_from + $post_count) ?></span><div class="<?php echo $icon_type ?>"><div class="nosize"><?php echo $icon_text ?></div></div> &nbsp; <a href="viewtopic.php?pid=<?php echo $cur_post['id'].'#p'.$cur_post['id'] ?>"><?php echo format_time($cur_post['posted']) ?></a></span></h2>

#
#---------[ 7. FIND (line: 208) ]---------------------------------------------
#

$forum_id = $cur_topic['forum_id'];

#
#---------[ 8. BEFORE ADD ]---------------------------------------------------
#

if (!$pun_user['is_guest'])
{

	// Only mark topic if it's currently unread. Also make sure we do not set topic tracking back if earlier pages are viewed.
	if (isset($tracked_topics[$id]) && $cur_topic['last_post'] > $tracked_topics[$id] && $max_post_time > $tracked_topics[$id])
		mark_read('topic', $cur_topic['forum_id'], $id, $max_post_time, $cur_topic['last_post'], (isset($cur_topic['forum_mark_time'])) ? $cur_topic['forum_mark_time'] : false);
}

#
#---------[ 8. OPEN ]---------------------------------------------------
#

admin_forums.php

#
#---------[ 7. FIND (line: 208) ]---------------------------------------------
#

		$db->query('DELETE FROM '.$db->prefix.'forum_subscriptions WHERE forum_id='.$forum_id) or error('Unable to delete subscriptions', __FILE__, __LINE__, $db->error());

#
#---------[ 8. AFTER ADD ]---------------------------------------------------
#

		// Delete forum tracking data
		$db->query('DELETE FROM '.$db->prefix.'forums_track WHERE forum_id='.$forum_id) or error('Unable to delete forums track', __FILE__, __LINE__, $db->error());

#
#---------[ 7. OPEN ]---------------------------------------------
#

moderate.php

#
#---------[ 7. FIND (line: 208) ]---------------------------------------------
#

// Get topic/forum tracking data
if (!$pun_user['is_guest'])
	$tracked_topics = get_tracked_topics();

#
#---------[ 8. REPLACE WITH (just delete the above lines) ]---------------------------------------------------
#

// Get topic/forum tracking data
// if (!$pun_user['is_guest'])
// 	$tracked_topics = get_tracked_topics();

#
#---------[ 7. FIND (line: 208) ]---------------------------------------------
#

$result = $db->query('SELECT f.forum_name, f.redirect_url, f.num_topics, f.sort_by FROM '.$db->prefix.'forums AS f LEFT JOIN '.$db->prefix.'forum_perms AS fp ON (fp.forum_id=f.id AND fp.group_id='.$pun_user['g_id'].') WHERE (fp.read_forum IS NULL OR fp.read_forum=1) AND f.id='.$fid) or error('Unable to fetch forum info', __FILE__, __LINE__, $db->error());

#
#---------[ 8. REPLACE WITH ]---------------------------------------------------
#

$result = $db->query('SELECT f.forum_name, f.redirect_url, f.num_topics, f.sort_by, ft.mark_time AS forum_mark_time FROM '.$db->prefix.'forums AS f LEFT JOIN '.$db->prefix.'forum_perms AS fp ON (fp.forum_id=f.id AND fp.group_id='.$pun_user['g_id'].') LEFT JOIN '.$db->prefix.'forums_track AS ft ON ft.user_id = '.$pun_user['id'].' AND f.id = ft.forum_id WHERE (fp.read_forum IS NULL OR fp.read_forum=1) AND f.id='.$fid) or error('Unable to fetch forum info', __FILE__, __LINE__, $db->error());

#
#---------[ 5. FIND (line: 10) ]---------------------------------------------
#

	$result = $db->query('SELECT id, poster, subject, posted, last_post, last_post_id, last_poster, num_views, num_replies, closed, sticky, moved_to FROM '.$db->prefix.'topics WHERE id IN('.implode(',', $topic_ids).') ORDER BY sticky DESC, '.$sort_by.', id DESC') or error('Unable to fetch topic list for forum', __FILE__, __LINE__, $db->error());

#
#---------[ 6. REPLACE WITH ]-------------------------------------------------
#

	$result = $db->query('SELECT t.id, t.poster, t.subject, t.posted, t.last_post, t.last_post_id, t.last_poster, t.num_views, t.num_replies, t.closed, t.sticky, t.moved_to, tt.mark_time FROM '.$db->prefix.'topics AS t LEFT JOIN '.$db->prefix.'topics_track AS tt ON tt.user_id = '.$pun_user['id'].' AND t.id = tt.topic_id WHERE t.id IN('.implode(',', $topic_ids).') ORDER BY t.sticky DESC, '.$sort_by.', t.id DESC') or error('Unable to fetch topic list for forum', __FILE__, __LINE__, $db->error());

#
#---------[ 7. FIND (line: 208) ]---------------------------------------------
#

	$topic_count = 0;
	while ($cur_topic = $db->fetch_assoc($result))

#
#---------[ 8. REPLACE WITH ]---------------------------------------------------
#

	$topics = array();
	while ($cur_topic = $db->fetch_assoc($result))
		$topics[] = $cur_topic;

	// Get tracked topics
	if (!$pun_user['is_guest'])
	{
		// Generate topic list...
		$topic_list = array();
		foreach ($topics as $cur_topic)
			$topic_list[$cur_topic['id']] = $cur_topic;

		$tracked_topics = get_topic_tracking_info($fid, $topic_ids, $topic_list, array($fid => $cur_forum['forum_mark_time']), false);
	}

	$topic_count = 0;
	foreach ($topics as $cur_topic)

#
#---------[ 7. FIND (line: 208) ]---------------------------------------------
#

		if (!$ghost_topic && $cur_topic['last_post'] > $pun_user['last_visit'] && (!isset($tracked_topics['topics'][$cur_topic['id']]) || $tracked_topics['topics'][$cur_topic['id']] < $cur_topic['last_post']) && (!isset($tracked_topics['forums'][$fid]) || $tracked_topics['forums'][$fid] < $cur_topic['last_post']))

#
#---------[ 8. REPLACE WITH ]---------------------------------------------------
#

		if (!$ghost_topic && isset($tracked_topics[$cur_topic['id']]) && $cur_topic['last_post'] > $tracked_topics[$cur_topic['id']])

#
#---------[ 7. OPEN ]---------------------------------------------
#

post.php

#
#---------[ 7. FIND (line: 208) ]---------------------------------------------
#

			$tracked_topics = get_tracked_topics();
			$tracked_topics['topics'][$new_tid] = time();
			set_tracked_topics($tracked_topics);
#
#---------[ 8. REPLACE WITH ]---------------------------------------------------
#

			// $tracked_topics = get_tracked_topics();
			// $tracked_topics['topics'][$new_tid] = time();
			// set_tracked_topics($tracked_topics);
#
#---------[ 7. OPEN ]---------------------------------------------
#

register.php

#
#---------[ 7. FIND (line: 208) ]---------------------------------------------
#

		$db->query('INSERT INTO '.$db->prefix.'users (username, group_id, password, email, email_setting, timezone, dst, language, style, registered, registration_ip, last_visit) VALUES(\''.$db->escape($username).'\', '.$intial_group_id.', \''.$password_hash.'\', \''.$db->escape($email1).'\', '.$email_setting.', '.$timezone.' , '.$dst.', \''.$db->escape($language).'\', \''.$pun_config['o_default_style'].'\', '.$now.', \''.get_remote_address().'\', '.$now.')') or error('Unable to create user', __FILE__, __LINE__, $db->error());
#
#---------[ 8. REPLACE WITH ]---------------------------------------------------
#

		$db->query('INSERT INTO '.$db->prefix.'users (username, group_id, password, email, email_setting, timezone, dst, language, style, registered, registration_ip, last_visit, last_mark) VALUES(\''.$db->escape($username).'\', '.$intial_group_id.', \''.$password_hash.'\', \''.$db->escape($email1).'\', '.$email_setting.', '.$timezone.' , '.$dst.', \''.$db->escape($language).'\', \''.$pun_config['o_default_style'].'\', '.$now.', \''.get_remote_address().'\', '.$now.', '.$now.')') or error('Unable to create user', __FILE__, __LINE__, $db->error());

#
#---------[ 7. OPEN ]---------------------------------------------
#

search.php

#
#---------[ 7. FIND (line: 208) ]---------------------------------------------
#

		// Run the query and fetch the results
		if ($show_as == 'posts')
			$result = $db->query('SELECT p.id AS pid, p.poster AS pposter, p.posted AS pposted, p.poster_id, p.message, p.hide_smilies, t.id AS tid, t.poster, t.subject, t.first_post_id, t.last_post, t.last_post_id, t.last_poster, t.num_replies, t.forum_id, f.forum_name FROM '.$db->prefix.'posts AS p INNER JOIN '.$db->prefix.'topics AS t ON t.id=p.topic_id INNER JOIN '.$db->prefix.'forums AS f ON f.id=t.forum_id WHERE p.id IN('.implode(',', $search_ids).') ORDER BY '.$sort_by_sql.' '.$sort_dir) or error('Unable to fetch search results', __FILE__, __LINE__, $db->error());
		else
			$result = $db->query('SELECT t.id AS tid, t.poster, t.subject, t.last_post, t.last_post_id, t.last_poster, t.num_replies, t.closed, t.sticky, t.forum_id, f.forum_name FROM '.$db->prefix.'topics AS t INNER JOIN '.$db->prefix.'forums AS f ON f.id=t.forum_id WHERE t.id IN('.implode(',', $search_ids).') ORDER BY '.$sort_by_sql.' '.$sort_dir) or error('Unable to fetch search results', __FILE__, __LINE__, $db->error());

#
#---------[ 8. REPLACE WITH ]---------------------------------------------------
#

		$sql_track_select = $sql_track_joins = '';
		if (!$pun_user['is_guest'])
		{
			$sql_track_select = ', tt.mark_time, ft.mark_time AS forum_mark_time';
			$sql_track_joins = ' LEFT JOIN '.$db->prefix.'topics_track AS tt ON tt.user_id = '.$pun_user['id'].' AND t.id = tt.topic_id LEFT JOIN '.$db->prefix.'forums_track AS ft ON ft.user_id = '.$pun_user['id'].' AND t.forum_id = ft.forum_id';
		}

		// Run the query and fetch the results
		if ($show_as == 'posts')
			$result = $db->query('SELECT p.id AS pid, p.poster AS pposter, p.posted AS pposted, p.poster_id, p.message, p.hide_smilies, t.id AS tid, t.poster, t.subject, t.first_post_id, t.last_post, t.last_post_id, t.last_poster, t.num_replies, t.forum_id, f.forum_name'.$sql_track_select.' FROM '.$db->prefix.'posts AS p INNER JOIN '.$db->prefix.'topics AS t ON t.id=p.topic_id INNER JOIN '.$db->prefix.'forums AS f ON f.id=t.forum_id'.$sql_track_joins.' WHERE p.id IN('.implode(',', $search_ids).') ORDER BY '.$sort_by_sql.' '.$sort_dir) or error('Unable to fetch search results', __FILE__, __LINE__, $db->error());
		else
			$result = $db->query('SELECT t.id AS tid, t.poster, t.subject, t.last_post, t.last_post_id, t.last_poster, t.num_replies, t.closed, t.sticky, t.forum_id, f.forum_name'.$sql_track_select.' FROM '.$db->prefix.'topics AS t INNER JOIN '.$db->prefix.'forums AS f ON f.id=t.forum_id'.$sql_track_joins.' WHERE t.id IN('.implode(',', $search_ids).') ORDER BY '.$sort_by_sql.' '.$sort_dir) or error('Unable to fetch search results', __FILE__, __LINE__, $db->error());

#
#---------[ 7. FIND (line: 208) ]---------------------------------------------
#

		// Get topic/forum tracking data
		if (!$pun_user['is_guest'])
			$tracked_topics = get_tracked_topics();

#
#---------[ 8. REPLACE WITH ]---------------------------------------------------
#

		// Get tracked topics
		if (!$pun_user['is_guest'])
		{
			$tracked_topics = array();

			// Generate topic forum list...
			$forum_list = $topic_list = array();
			foreach ($search_set as $cur_search)
			{
				$forum_list[$cur_search['forum_id']]['forum_mark_time'] = isset($cur_search['forum_mark_time']) ? $cur_search['forum_mark_time'] : 0;
				$forum_list[$cur_search['forum_id']]['topics'][] = $cur_search['tid'];

				$topic_list[$cur_search['tid']] = $cur_search;
			}

			foreach ($forum_list as $f_id => $cur_forum)
				$tracked_topics += get_topic_tracking_info($f_id, $cur_forum['topics'], $topic_list, array($f_id => $cur_forum['forum_mark_time']));
		}

#
#---------[ 7. FIND (line: 208) ]---------------------------------------------
#

				if (!$pun_user['is_guest'] && $cur_search['last_post'] > $pun_user['last_visit'] && (!isset($tracked_topics['topics'][$cur_search['tid']]) || $tracked_topics['topics'][$cur_search['tid']] < $cur_search['last_post']) && (!isset($tracked_topics['forums'][$cur_search['forum_id']]) || $tracked_topics['forums'][$cur_search['forum_id']] < $cur_search['last_post']))

#
#---------[ 8. REPLACE WITH ]---------------------------------------------------
#

				if (!$pun_user['is_guest'] && (isset($tracked_topics[$cur_search['tid']]) && $cur_search['pposted'] > $tracked_topics[$cur_search['tid']]))

#
#---------[ 7. FIND (line: 208) ]---------------------------------------------
#

				if (!$pun_user['is_guest'] && $cur_search['last_post'] > $pun_user['last_visit'] && (!isset($tracked_topics['topics'][$cur_search['tid']]) || $tracked_topics['topics'][$cur_search['tid']] < $cur_search['last_post']) && (!isset($tracked_topics['forums'][$cur_search['forum_id']]) || $tracked_topics['forums'][$cur_search['forum_id']] < $cur_search['last_post']))

#
#---------[ 8. REPLACE WITH ]---------------------------------------------------
#

				if (!$pun_user['is_guest'] && (isset($tracked_topics[$cur_search['tid']]) && $cur_search['last_post'] > $tracked_topics[$cur_search['tid']]))

#
#---------[ 20. SAVE/UPLOAD ]-------------------------------------------------
#
