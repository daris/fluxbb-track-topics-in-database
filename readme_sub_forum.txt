<?
#
#---------[ 4. OPEN ]---------------------------------------------------------
#

index.php

#
#---------[ 5. FIND ]---------------------------------------------
#

$forums_info = $db->query('SELECT f.num_topics, f.num_posts, f.parent_forum_id, f.last_post_id, f.last_poster, f.last_post, f.id, f.forum_name FROM '.$db->prefix.'forums AS f LEFT JOIN '.$db->prefix.'forum_perms AS fp ON (fp.forum_id=f.id AND fp.group_id='.$pun_user['g_id'].') WHERE (fp.read_forum IS NULL OR fp.read_forum=1) AND f.parent_forum_id <> 0 ORDER BY f.disp_position') or error('Unable to fetch subforum list', __FILE__, __LINE__, $db->error());

#
#---------[ 5. REPLACE WITH ]---------------------------------------------
#

$sql_mark_time = $sql_forums_track = '';
if (!$pun_user['is_guest'])
{
	// Forum tracking
	$sql_mark_time = ', ft.mark_time AS forum_mark_time';
	$sql_forums_track = ' LEFT JOIN '.$db->prefix.'forums_track AS ft ON ft.user_id = '.$pun_user['id'].' AND f.id = ft.forum_id';
}
$forums_info = $db->query('SELECT f.num_topics, f.num_posts, f.parent_forum_id, f.last_post_id, f.last_poster, f.last_post, f.id, f.forum_name'.$sql_mark_time.' FROM '.$db->prefix.'forums AS f LEFT JOIN '.$db->prefix.'forum_perms AS fp ON (fp.forum_id=f.id AND fp.group_id='.$pun_user['g_id'].')'.$sql_forums_track.' WHERE (fp.read_forum IS NULL OR fp.read_forum=1) AND f.parent_forum_id <> 0 ORDER BY f.disp_position') or error('Unable to fetch subforum list', __FILE__, __LINE__, $db->error());

#
#---------[ 7. FIND (line: 208) ]---------------------------------------------
#

	// Are there new posts since our last visit?
	if (!empty($sfdb) && isset($sfdb[$cur_forum['fid']]))
	{
		foreach ($sfdb[$cur_forum['fid']] as $cur_subforum)
		{
			if (!$pun_user['is_guest'] && $cur_subforum['last_post'] > $pun_user['last_visit'] && (empty($tracked_topics['forums'][$cur_subforum['id']]) || $cur_forum['last_post'] > $tracked_topics['forums'][$cur_subforum['id']]))
			{
				// There are new posts in this forum, but have we read all of them already?
				foreach ($new_topics[$cur_subforum['id']] as $check_topic_id => $check_last_post)
				{
					if ((empty($tracked_topics['topics'][$check_topic_id]) || $tracked_topics['topics'][$check_topic_id] < $check_last_post) && (empty($tracked_topics['forums'][$cur_subforum['id']]) || $tracked_topics['forums'][$cur_subforum['id']] < $check_last_post))
					{
						$item_status .= ' inew';
						$forum_field_new = '<span class="newtext">[ <a href="search.php?action=show_new&amp;fid='.$cur_forum['fid'].'">'.$lang_common['New posts'].'</a> ]</span>';
						$icon_type = 'icon icon-new';

						break;
					}
				}
			}
		}
	}

#
#---------[ 8. REPLACE WITH ]---------------------------------------------------
#

	// Are there new posts since our last visit?
	if (!empty($sfdb) && isset($sfdb[$cur_forum['fid']]))
	{
		foreach ($sfdb[$cur_forum['fid']] as $cur_subforum)
		{
			if (!$pun_user['is_guest'])
			{
				// There are new posts in this forum, but have we read all of them already?
				if (empty($cur_subforum['forum_mark_time']))
					$cur_subforum['forum_mark_time'] = $pun_user['last_mark'];

				if ($cur_subforum['last_post'] > $cur_subforum['forum_mark_time'])
				{
					$item_status .= ' inew';
					$forum_field_new = '<span class="newtext">[ <a href="search.php?action=show_new&amp;fid='.$cur_forum['fid'].'">'.$lang_common['New posts'].'</a> ]</span>';
					$icon_type = 'icon icon-new';

					break;
				}
			}
		}
	}

#
#---------[ 4. OPEN ]---------------------------------------------------------
#

viewforum.php

#
#---------[ 5. FIND ]---------------------------------------------
#

	$subforum_result = $db->query('SELECT f.forum_desc, f.forum_name, f.id, f.last_post, f.last_post_id, f.last_poster, f.moderators, f.num_posts, f.num_topics, f.redirect_url FROM '.$db->prefix.'forums AS f LEFT JOIN '.$db->prefix.'forum_perms AS fp ON (fp.forum_id=f.id AND fp.group_id='.$pun_user['g_id'].') WHERE (fp.read_forum IS NULL OR fp.read_forum=1) AND parent_forum_id='.$id.' ORDER BY disp_position') or error('Unable to fetch sub forum info',__FILE__,__LINE__,$db->error());

#
#---------[ 5. REPLACE WITH ]---------------------------------------------
#

	$sql_mark_time = $sql_forums_track = '';
	if (!$pun_user['is_guest'])
	{
		// Forum tracking
		$sql_mark_time = ', ft.mark_time AS forum_mark_time';
		$sql_forums_track = ' LEFT JOIN '.$db->prefix.'forums_track AS ft ON ft.user_id = '.$pun_user['id'].' AND f.id = ft.forum_id';
	}
	$subforum_result = $db->query('SELECT f.forum_desc, f.forum_name, f.id, f.last_post, f.last_post_id, f.last_poster, f.moderators, f.num_posts, f.num_topics, f.redirect_url'.$sql_mark_time.' FROM '.$db->prefix.'forums AS f LEFT JOIN '.$db->prefix.'forum_perms AS fp ON (fp.forum_id=f.id AND fp.group_id='.$pun_user['g_id'].')'.$sql_forums_track.' WHERE (fp.read_forum IS NULL OR fp.read_forum=1) AND parent_forum_id='.$id.' ORDER BY disp_position') or error('Unable to fetch sub forum info',__FILE__,__LINE__,$db->error());

#
#---------[ 7. FIND (line: 208) ]---------------------------------------------
#

			// Are there new posts?
			if (!$pun_user['is_guest'] && $cur_subforum['last_post'] > $pun_user['last_visit'] && (empty($tracked_topics['forums'][$cur_subforum['id']]) || $cur_subforum['last_post'] > $tracked_topics['forums'][$cur_subforum['id']]))
			{
				// There are new posts in this forum, but have we read all of them already?
				foreach ($new_topics[$cur_subforum['id']] as $check_topic_id => $check_last_post)
				{
					if ((empty($tracked_topics['topics'][$check_topic_id]) || $tracked_topics['topics'][$check_topic_id] < $check_last_post) && (empty($tracked_topics['forums'][$cur_subforum['id']]) || $tracked_topics['forums'][$cur_subforum['id']] < $check_last_post))
					{
						$item_status = 'inew';
						$icon_type = 'icon inew';

						break;
					}
				}
			}
#
#---------[ 8. REPLACE WITH ]---------------------------------------------------
#

			// Are there new posts?
			if (!$pun_user['is_guest'])
			{
				 if (empty($cur_subforum['forum_mark_time']))
					$cur_subforum['forum_mark_time'] = $pun_user['last_mark'];

				if ($cur_subforum['last_post'] > $cur_subforum['forum_mark_time'])
				{
					$item_status = 'inew';
					$icon_type = 'icon inew';
				}
			}

#
#---------[ 20. SAVE/UPLOAD ]-------------------------------------------------
#
