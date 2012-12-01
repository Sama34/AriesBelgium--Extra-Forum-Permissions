<?php
/**
 * Extra Forum Permission Pack
 * Copyright 2011 Aries-Belgium
 *
 * $Id$
 */

// Disallow direct access to this file for security reasons
if(!defined("IN_MYBB"))
{
	die("Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.");
}

$plugins->add_hook('admin_forum_management_permission_groups', 'extraforumperm_custom_permissions');
$plugins->add_hook('admin_user_groups_edit_graph_tabs', 'extraforumperm_usergroup_permissions_tab');
$plugins->add_hook('admin_user_groups_edit_graph', 'extraforumperm_usergroup_permissions');
$plugins->add_hook('admin_user_groups_edit_commit', 'extraforumperm_usergroup_permissions_save');

// canrateownthreads
$plugins->add_hook('ratethread_start', 'extraforumperm_canrateownthreads');
// canstickyownthreads, cancloseownthreads
$plugins->add_hook('showthread_start', 'extraforumperm_showthreadmoderation');
$plugins->add_hook('newreply_end', 'extraforumperm_newreplymoderation');
$plugins->add_hook('newthread_end', 'extraforumperm_newthreadmoderation');
$plugins->add_hook('moderation_start', 'extraforumperm_moderation');
$plugins->add_hook('newreply_do_newreply_end', 'extraforumperm_save_modoptions');
$plugins->add_hook('newthread_do_newthread_end', 'extraforumperm_save_modoptions');
// canpostlinks, canpostimages, canpostvideos
$plugins->add_hook('datahandler_post_validate_thread', 'extraforumperm_validatepost');
$plugins->add_hook('datahandler_post_validate_post', 'extraforumperm_validatepost');

/**
 * Info function for MyBB plugin system
 */
function extraforumperm_info()
{
	global $lang;
	
	extraforumperm__lang_load();
	
	$donate_button = 
'<a href="https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=RQNL345SN45DS" style="float:right;margin-top:-8px;padding:4px;" target="_blank"><img src="https://www.paypalobjects.com/WEBSCR-640-20110306-1/en_US/i/btn/btn_donate_SM.gif" /></a>';

	return array(
		"name"			=> $lang->extraforumperm,
		"description"	=> "{$donate_button}{$lang->extraforumperm_description}",
		"website"		=> "http://mods.mybb.com/view/extra-forum-permissions",
		"author"		=> "Aries-Belgium",
		"authorsite"	=> "mailto:aries.belgium@gmail.com",
		"version"		=> "1.2",
		"guid" 			=> "aa4ae3a915facf10a67a029af9ea154a",
		"compatibility" => "16*"
	);
}

/**
 * The install function for the plugin system
 */
function extraforumperm_install()
{
	global $db, $cache;
	
	// add the extra fields to the permission table
	$permissions = extraforumperm_permissions();
	foreach($permissions as $permission => $default)
	{
		if(!$db->field_exists($permission, "forumpermissions"))
		{
			$db->query("ALTER TABLE ".TABLE_PREFIX."forumpermissions ADD  {$permission} INT( 1 ) NOT NULL DEFAULT  '{$default}'");
		}
		if(!$db->field_exists($permission, "usergroups"))
		{
			$db->query("ALTER TABLE ".TABLE_PREFIX."usergroups ADD  {$permission} INT( 1 ) NOT NULL DEFAULT  '{$default}'");
		}
	}
	
	// rebuild the cache
	$cache->update_usergroups();
	$cache->update_forumpermissions();
}

/**
 * The is_installed function for the plugin system
 */
function extraforumperm_is_installed()
{
	global $db;
	
	// check if the extra fields exist
	$fields_exist = true;
	$permissions = extraforumperm_permissions();
	foreach($permissions as $permission => $default)
	{
		if(!$db->field_exists($permission, 'forumpermissions') || !$db->field_exists($permiission, 'usergroups'))
		{
			$fields_exist = false;
			break;
		}
	}
	
	return $fields_exist;
}

/**
 * The uninstall function for the plugin system
 */
function extraforumperm_uninstall()
{
	global $db, $cache;
	
	// delete the extra fields
	$permissions = extraforumperm_permissions();
	foreach($permissions as $permission => $default)
	{
		$db->query("ALTER TABLE ".TABLE_PREFIX."forumpermissions DROP ".$permission);
		$db->query("ALTER TABLE ".TABLE_PREFIX."usergroups DROP ".$permission);
	}
	
	// rebuild the cache
	$cache->update_usergroups();
	$cache->update_forumpermissions();
}

/**
 * The activate function for the plugin system
 */
function extraforumperm_activate()
{
	global $cache;
	
	// rebuild the cache
	$cache->update_usergroups();
	$cache->update_forumpermissions();
}

function extraforumperm_permissions()
{
	return array(
		'canrateownthreads' => 1,
		'canstickyownthreads' => 0,
		'cancloseownthreads' => 0,
		'canpostlinks' => 1,
		'canpostimages' => 1,
		'canpostvideos' => 1
	);
}

/**
 * Implementation of the admin_forum_management_permission_groups hook
 *
 * Add the extra permissions to the custom permissions form
 */
function extraforumperm_custom_permissions(&$groups)
{
	global $lang;
	
	extraforumperm__lang_load();
	
	$permissions = extraforumperm_permissions();
	foreach($permissions as $permission => $default)
	{
		$groups[$permission] = 'extra';
	}
}

function extraforumperm_usergroup_permissions_tab(&$tabs)
{
	global $lang;
	
	extraforumperm__lang_load();
	
	$tabs['extra'] = $lang->group_extra;
}

/**
 * Implementation of the admin_formcontainer_end hook
 *
 * Add the extra permissions to the end of the usergroups table
 */
function extraforumperm_usergroup_permissions()
{
	global $mybb, $lang, $form;
	
	extraforumperm__lang_load();
	
	$permissions = extraforumperm_permissions();

	print '<div id="tab_extra">';
	$form_container = new FormContainer($lang->group_extra);
	
	$extra_options = array();
	foreach($permissions as $permission => $default)
	{
		$l = 'extra_field_'.$permission;
		$extra_options[] = $form->generate_check_box($permission, 1, $lang->$l, array("checked" => $mybb->input[$permission]));
	}
	
	$form_container->output_row($lang->extraforumperm, "", "<div class=\"group_settings_bit\">".implode("</div><div class=\"group_settings_bit\">", $extra_options)."</div>");
	
	$form_container->end();
	
	print '</div>';
}

function extraforumperm_usergroup_permissions_save()
{
	global $mybb, $updated_group;
	
	$permissions = extraforumperm_permissions();
	
	foreach($permissions as $permission => $default)
	{
		$updated_group[$permission] = intval($mybb->input[$permission]);
	}
}

/**
 * Implementation of the ratethread_start hook
 *
 * If the current user is the topicstarter check the canrateownthreads permission
 */
function extraforumperm_canrateownthreads()
{
	global $lang, $mybb, $thread, $forumpermissions;
	
	if($forumpermissions['canrateownthreads'] != 1 && $thread['uid'] == $mybb->user['uid'])
	{
		extraforumperm__lang_load();
		error($lang->error_canrateownthread);
	}
}

/**
 * Implementation of the showthread_end hook
 *
 * Show the thread moderation for several extra permissions
 */
function extraforumperm_showthreadmoderation()
{
	global $mybb, $lang, $thread, $templates, $forumpermissions, $moderationoptions, $closeoption, $inlinecount, $inlinecookie;
	
	// if $moderationoptions is not empty the current user already has
	// moderation rights to this thread
	if(!empty($moderationoptions))
		return;
	
	// if the user doesn't have any permission where we need the moderation
	// tool for, just exit the function
	if(
		$forumpermissions['canstickyownthreads'] == 0 &&
		$forumpermissions['cancloseownthreads'] == 0
	)
		return;
		
	$options = array();
	
	if($forumpermissions['cancloseownthreads'] == 1 && $thread['uid'] == $mybb->user['uid'])
	{
		if($thread['closed'] == 1)
		{
			$closelinkch = ' checked="checked"';
		}
		$closeoption = "<br /><label><input type=\"checkbox\" class=\"checkbox\" name=\"modoptions[closethread]\" value=\"1\"{$closelinkch} />&nbsp;<strong>".$lang->close_thread."</strong></label>";
		
		$options[] = '<option value="openclosethread">'.$lang->open_close_thread.'</option>';
	}
	
	if($forumpermissions['canstickyownthreads'] == 1 && $thread['uid'] == $mybb->user['uid'])
	{
		if($thread['sticky'])
		{
			$stickch = ' checked="checked"';
		}
		
		$closeoption .= "<br /><label><input type=\"checkbox\" class=\"checkbox\" name=\"modoptions[stickthread]\" value=\"1\"{$stickch} />&nbsp;<strong>".$lang->stick_thread."</strong></label>";
		
		$options[] = '<option value="stick">'.$lang->stick_unstick_thread.'</option>';
	}
	
	eval("\$gobutton = \"".$templates->get('gobutton')."\";");
	
	if(count($options) > 0)
	{
		$moderationoptions = 
'<form action="moderation.php" method="post" style="margin-top: 0; margin-bottom: 0;" id="extraforumperms">
	<input type="hidden" name="extraforumperm" value="1" />
	<input type="hidden" name="modtype" value="thread" />
	<input type="hidden" name="tid" value="'.$thread['tid'].'" />
	<input type="hidden" name="my_post_key" value="'.$mybb->post_code.'" />
	<span class="smalltext">
	<strong>'.$lang->moderation_options.'</strong></span>
	<select name="action" onchange="$(\'extraforumperms\').submit();">
	'.implode("\n", $options).'
	</select>
	'.$gobutton.'
</form><br/>';
	}
}

/**
 * Implementation of the newreply_end hook
 *
 * Show the mod options to the user if he has the right
 * permissions
 */
function extraforumperm_newreplymoderation()
{
	global $mybb, $templates, $lang, $forumpermissions, $thread, $modoptions, $bgcolor;
	
	if($mybb->input['processed'])
	{
		$closed = intval($mybb->input['modoptions']['closethread']);
		$stuck = intval($mybb->input['modoptions']['stickthread']);
	}
	else
	{
		$closed = $thread['closed'];
		$stuck = $thread['sticky'];
	}
	
	if($closed)
	{
		$closecheck = ' checked="checked"';
	}
	else
	{
		$closecheck = '';
	}

	if($stuck)
	{
		$stickycheck = ' checked="checked"';
	}
	else
	{
		$stickycheck = '';
	}
	
	if($forumpermissions['canstickyownthreads'] == 0 || $mybb->user['uid'] != $thread['uid'])
	{
		$stickycheck .= ' disabled="disabled"';
	}
	
	if($forumpermissions['cancloseownthreads'] == 0 || $mybb->user['uid'] != $thread['uid'])
	{
		$closecheck .= ' disabled="disabled"';
	}
	
	if(empty($modoptions) && ($forumpermissions['canstickyownthreads'] || $forumpermissions['cancloseownthreads']))
	{
		eval("\$modoptions = \"".$templates->get("newreply_modoptions")."\";");
	}
}

/**
 * Implementation of the newreply_end hook
 *
 * Show the mod options to the user if he has the right
 * permissions
 */
function extraforumperm_newthreadmoderation()
{
	global $mybb, $templates, $lang, $forumpermissions, $modoptions, $bgcolor;
	
	if($mybb->input['processed'])
	{
		$closed = intval($mybb->input['modoptions']['closethread']);
		$stuck = intval($mybb->input['modoptions']['stickthread']);
	}
	else
	{
		$closed = $thread['closed'];
		$stuck = $thread['sticky'];
	}
	
	if($closed)
	{
		$closecheck = ' checked="checked"';
	}
	else
	{
		$closecheck = '';
	}

	if($stuck)
	{
		$stickycheck = ' checked="checked"';
	}
	else
	{
		$stickycheck = '';
	}
	
	if($forumpermissions['canstickyownthreads'] == 0)
	{
		$stickycheck .= ' disabled="disabled"';
	}
	
	if($forumpermissions['cancloseownthreads'] == 0)
	{
		$closecheck .= ' disabled="disabled"';
	}
	
	if(empty($modoptions) && ($forumpermissions['canstickyownthreads'] || $forumpermissions['cancloseownthreads']))
	{
		eval("\$modoptions = \"".$templates->get("newreply_modoptions")."\";");
	}
}

/**
 * Implementation of the moderation_start hook
 *
 * Enable regular users to have some moderation actions
 * on there own threads
 */
function extraforumperm_moderation()
{
	global $mybb, $moderation, $plugins, $templates, $parser, $lang;
	
	if($mybb->input['extraforumperm'] == 1)
	{
		// @see moderation.php: 41 -> 98 ------------------------------------
		$tid = intval($mybb->input['tid']);
		$pid = intval($mybb->input['pid']);
		$fid = intval($mybb->input['fid']);

		if($pid)
		{
			$post = get_post($pid);
			$tid = $post['tid'];
			if(!$post['pid'])
			{
				error($lang->error_invalidpost);
			}
		}

		if($tid)
		{
			$thread = get_thread($tid);
			$fid = $thread['fid'];
			if(!$thread['tid'])
			{
				error($lang->error_invalidthread);
			}
		}

		if($fid)
		{
			$modlogdata['fid'] = $fid;
			$forum = get_forum($fid);

			// Make navigation
			build_forum_breadcrumb($fid);
		}

		$thread['subject'] = htmlspecialchars_uni($parser->parse_badwords($thread['subject'])); 

		if($tid)
		{
			add_breadcrumb($thread['subject'], get_thread_link($thread['tid']));
			$modlogdata['tid'] = $tid;
		}

		// Get our permissions all nice and setup
		$permissions = forum_permissions($fid);

		if($fid)
		{
			// Check if this forum is password protected and we have a valid password
			check_forum_password($forum['fid']);
		}

		if($mybb->user['uid'] != 0)
		{
			eval("\$loginbox = \"".$templates->get("changeuserbox")."\";");
		}
		else
		{
			eval("\$loginbox = \"".$templates->get("loginbox")."\";");
		}
		// ------------------------------------------------------------------
		
		switch($mybb->input['action'])
		{
			// canstickyownthread
			case 'stick':
				verify_post_check($mybb->input['my_post_key']);
				
				if(
					!is_moderator($fid, "canmanagethreads") && 
					($permissions['canstickyownthreads'] == 0) &&
					($mybb->user['uid'] != $thread['uid'])
				)
				{
					error_no_permission();
				}

				$plugins->run_hooks("moderation_stick");

				if($thread['sticky'] == 1)
				{
					$stuckunstuck = $lang->unstuck;
					$redirect = $lang->redirect_unstickthread;
					$moderation->unstick_threads($tid);
				}
				else
				{
					$stuckunstuck = $lang->stuck;
					$redirect = $lang->redirect_stickthread;
					$moderation->stick_threads($tid);
				}

				$lang->mod_process = $lang->sprintf($lang->mod_process, $stuckunstuck);

				log_moderator_action($modlogdata, $lang->mod_process);

				moderation_redirect(get_thread_link($thread['tid']), $redirect);
				break;
			// cancloseownthread
			case 'openclosethread':
				verify_post_check($mybb->input['my_post_key']);
				
				if(!is_moderator($fid, "canopenclosethreads") && 
					($permissions['cancloseownthreads'] == 0) &&
					($mybb->user['uid'] != $thread['uid'])
				)
				{
					error_no_permission();
				}

				if($thread['closed'] == 1)
				{
					$openclose = $lang->opened;
					$redirect = $lang->redirect_openthread;
					$moderation->open_threads($tid);
				}
				else
				{
					$openclose = $lang->closed;
					$redirect = $lang->redirect_closethread;
					$moderation->close_threads($tid);
				}

				$lang->mod_process = $lang->sprintf($lang->mod_process, $openclose);

				log_moderator_action($modlogdata, $lang->mod_process);

				moderation_redirect(get_thread_link($thread['tid']), $redirect);
				break;
		}
	}
}

/**
 * Implementation of the newreply_do_newreply_end hook
 *
 * Check if the mod options are check when doing a new reply
 * and save the mod options.
 */
function extraforumperm_save_modoptions()
{
	global $forumpermissions, $post, $thread, $new_thread, $thread_info, $lang, $db, $mybb;
	
	if(is_moderator($post['fid'], "", $post['uid']))
	{
		// the options are already done for moderators
		return;
	}
	
	$lang->load("datahandler_post", true);
	
	// small hack for the newthread action
	if(isset($thread_info) && is_array($thread_info))
	{
		$thread = array_merge($new_thread, $thread_info);
		$post['modoptions'] = $thread['modoptions'];
	}
	
	$modoptions = $post['modoptions'];
	$modlogdata['fid'] = $thread['fid'];
	$modlogdata['tid'] = $thread['tid'];
	
	$forumpermissions = forum_permissions($thread['fid']);

	$update = array();
	if($forumpermissions['cancloseownthreads'] && $mybb->user['uid'] == $thread['uid'])
	{
		// Close the thread.
		if($modoptions['closethread'] == 1 && $thread['closed'] != 1)
		{
			$update['closed'] = 1;
			log_moderator_action($modlogdata, $lang->thread_closed);
		}

		// Open the thread.
		if($modoptions['closethread'] != 1 && $thread['closed'] == 1)
		{
			$update['closed'] = 0;
			log_moderator_action($modlogdata, $lang->thread_opened);
		}
	}

	if($forumpermissions['canstickyownthreads'] && $mybb->user['uid'] == $thread['uid'])
	{
		// Stick the thread.
		if($modoptions['stickthread'] == 1 && $thread['sticky'] != 1)
		{
			$update['sticky'] = 1;
			log_moderator_action($modlogdata, $lang->thread_stuck);
		}

		// Unstick the thread.
		if($modoptions['stickthread'] != 1 && $thread['sticky'])
		{
			$update['sticky'] = 0;					
			log_moderator_action($modlogdata, $lang->thread_unstuck);
		}
	}

	// Execute moderation options.
	if($update)
	{
		$db->update_query('threads', $update, 'tid=\''.(int)$thread['tid'].'\'');
	}
}

/**
 * Implementation of the datahandler_post_validate_thread and datahandler_post_validate_post hook
 *
 * When there are links in the new post, throw an error
 */
function extraforumperm_validatepost(&$datahandler)
{
	global $lang;
	
	$forumpermissions = forum_permissions($datahandler->data['fid']);
	
	$message = " ".$datahandler->data['message'];
		
	// ignore text between php and code tags because MyBB doesn't parse
	// the content of those tags
	$message = preg_replace("#\[(code|php)\](.*?)\[/\\1\](\r\n?|\n?)#si", "", $message);
	
	if(!$forumpermissions['canpostlinks'])
	{	
		$http_links = "#([\>\s\(\)])(http|https|ftp|news){1}://([^\/\"\s\<\[\.]+\.([^\/\"\s\<\[\.]+\.)*[\w]+(:[0-9]+)?(/[^\"\s<\[]*)?)#i";
		$www_links = "#([\>\s\(\)])(www|ftp)\.(([^\/\"\s\<\[\.]+\.)*[\w]+(:[0-9]+)?(/[^\"\s<\[]*)?)#i";
		$url_tags_simple = "#\[url\]([a-z]+?://)([^\r\n\"<]+?)\[/url\]#si";
		$url_tags_simple2 = "#\[url\]([^\r\n\"<]+?)\[/url\]#i";
		$url_tags_complex = "#\[url=([a-z]+?://)([^\r\n\"<]+?)\](.+?)\[/url\]#si";
		$url_tags_complex2 = "#\[url=([^\r\n\"<&\(\)]+?)\](.+?)\[/url\]#si";
		
		if(
			preg_match($http_links, $message) ||
			preg_match($www_links, $message) ||
			preg_match($url_tags_simple, $message) ||
			preg_match($url_tags_simple2, $message) ||
			preg_match($url_tags_complex, $message) ||
			preg_match($url_tags_complex2, $message)
		)
		{
			extraforumperm__lang_load();
			$datahandler->is_validated = false;
			$datahandler->set_error($lang->error_canpostlinks);
		}
	}
	
	if(!$forumpermissions['canpostimages'])
	{
		$img_tags_1 = "#\[img\](\r\n?|\n?)(https?://([^<>\"']+?))\[/img\]#is";
		$img_tags_2 = "#\[img=([0-9]{1,3})x([0-9]{1,3})\](\r\n?|\n?)(https?://([^<>\"']+?))\[/img\]#is";
		$img_tags_3 = "#\[img align=([a-z]+)\](\r\n?|\n?)(https?://([^<>\"']+?))\[/img\]#is";
		$img_tags_4 = "#\[img=([0-9]{1,3})x([0-9]{1,3}) align=([a-z]+)\](\r\n?|\n?)(https?://([^<>\"']+?))\[/img\]#is";
		
		if(
			preg_match($img_tags_1, $message) ||
			preg_match($img_tags_2, $message) ||
			preg_match($img_tags_3, $message) ||
			preg_match($img_tags_4, $message)
		)
		{
			extraforumperm__lang_load();
			$datahandler->is_validated = false;
			$datahandler->set_error($lang->error_canpostimages);
		}
	}
	
	if(!$forumpermissions['canpostvideos'])
	{
		$video_tag = "#\[video=(.*?)\](.*?)\[/video\]#i";
		
		if(preg_match($video_tag, $message))
		{
			extraforumperm__lang_load();
			$datahandler->is_validated = false;
			$datahandler->set_error($lang->error_canpostvideos);
		}
	}
}

/**
 * Helper function to load language files for the plugin
 */
function extraforumperm__lang_load($file="", $supress_error=false)
{
	global $lang;
	
	$plugin_name = str_replace('__lang_load', '', __FUNCTION__);
	$plugin_lang_dir = MYBB_ROOT."inc/plugins/{$plugin_name}/lang/";
	if(empty($file)) $file = $plugin_name;
	
	$langparts = explode("/", $lang->language, 2);
	$language = $langparts[0];
	if(isset($langparts[1]))
	{
		$dir = "/".$langparts[1];
	}
	else
	{
		$dir = "";
	}
	
	if(file_exists($plugin_lang_dir.$language.$dir."/{$file}.lang.php"))
	{
		require_once $plugin_lang_dir.$language.$dir."/{$file}.lang.php";
	}
	elseif(file_exists($plugin_lang_dir."english".$dir."/{$file}.lang.php"))
	{
		require_once $plugin_lang_dir."english".$dir."/{$file}.lang.php";
	}
	else
	{
		if($supress_error != true)
		{
			die($plugin_lang_dir."english".$dir."/{$file}.lang.php");
		}
	}
	
	if(is_array($l))
	{
		foreach($l as $key => $val)
		{
			if(empty($lang->$key) || $lang->$key != $val)
			{
				$lang->$key = $val;
			}
		}
	}
}