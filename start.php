<?php

function sphinx_init() {
	elgg_register_class('SphinxClient', dirname(__FILE__) . '/vendors/sphinx/sphinxapi.php');
	elgg_register_viewtype('sphinx');

	elgg_unregister_plugin_hook_handler('search', 'object', 'search_objects_hook');
	elgg_unregister_plugin_hook_handler('search', 'group', 'search_groups_hook');
	elgg_unregister_plugin_hook_handler('search', 'user', 'search_users_hook');
	
	elgg_register_plugin_hook_handler('search', 'object', 'sphinx_objects_hook');
	elgg_register_plugin_hook_handler('search', 'group', 'sphinx_groups_hook');
	elgg_register_plugin_hook_handler('search', 'user', 'sphinx_users_hook');
	
	elgg_register_admin_menu_item('configure', 'sphinx', 'settings');
	elgg_register_admin_menu_item('administer', 'sphinx', 'statistics');
	
	elgg_register_action('sphinx/configure', dirname(__FILE__) . '/actions/sphinx/configure.php', 'admin');
}

function sphinx_query($params, $index) {
	$cl = new SphinxClient();
	$cl->SetServer("localhost", 9312);
	$cl->SetMatchMode(SPH_MATCH_ANY);
	$cl->SetLimits($params['offset'], $params['limit']);
	
	if (isset($params['subtype'])) {
		$subtype_id = intval(get_subtype_id($params['type'], $params['subtype']));
		$cl->setFilter('subtype', array($subtype_id));
	}
	
	if (isset($params['owner_guid'])) {
		$cl->SetFilter('owner_guid', array($params['owner_guid']));
	}
	
	if (isset($params['container_guid'])) {
		$cl->SetFilter('container_guid', array($params['container_guid']));
	}
	
	$result = $cl->Query($params['query'], $index);
	if ($result === false) {
		elgg_log($cl->GetLastError(), 'ERROR');
		return;
	} 
	
	if ($cl->GetLastWarning()) {
		elgg_log($cl->GetLastWarning(), 'WARNING');
	}
	
	//echo "<pre>", print_r($result['matches']), "</pre>";
	
	$entities = array();
	
	if(!empty($result['matches'])){
	foreach ($result['matches'] as $match) {
		$entities[] = entity_row_to_elggstar((object)$match['attrs']);
	}
	}
		
	return array(
		'count' => $result['total_found'],
		'entities' => $entities,
	);
	
}

/**
 * Return default results for searches on objects.
 */
function sphinx_objects_hook($hook, $type, $value, $params) {
	$return = sphinx_query($params, 'objects');
	
	if (!isset($return)) {
		return NULL;
	}
	
	$objects = $return['entities'];
	
	// add the volatile data for why these entities have been returned.
	foreach ($objects as $object) {
		$title = search_get_highlighted_relevant_substrings($object->title, $params['query']);
		$object->setVolatileData('search_matched_title', $title);

		$desc = search_get_highlighted_relevant_substrings($object->description, $params['query']);
		$object->setVolatileData('search_matched_description', $desc);
	}
	
	return $return;
}

/**
 * Return default results for searches on groups.
 */
function sphinx_groups_hook($hook, $type, $value, $params) {
	$return = sphinx_query($params, 'groups');
	
	if (!isset($return)) {
		return NULL;
	}
	
	$groups = $return['entities'];
	
	// add the volatile data for why these entities have been returned.
	foreach ($groups as $group) {
		$title = search_get_highlighted_relevant_substrings($group->name, $params['query']);
		$group->setVolatileData('search_matched_title', $title);

		$desc = search_get_highlighted_relevant_substrings($group->description, $params['query']);
		$group->setVolatileData('search_matched_description', $desc);
	}
	
	return $return;
}

/**
 * Return default results for searches on users.
 *
 * @todo add profile field MD searching
 */
function sphinx_users_hook($hook, $type, $value, $params) {
	$return = sphinx_query($params, 'users');
	
	if (!isset($return)) {
		return NULL;
	}
	
	$users = $return['entities'];
	
	// add the volatile data for why these entities have been returned.
	foreach ($users as $user) {
		$title = search_get_highlighted_relevant_substrings($user->username, $params['query']);
		$user->setVolatileData('search_matched_title', $title);

		$desc = search_get_highlighted_relevant_substrings($user->name, $params['query']);
		$user->setVolatileData('search_matched_description', $desc);
	}
	
	return $return;
}

function sphinx_write_conf() {
	$handle = fopen(elgg_get_data_path() . 'sphinx/sphinx.conf', 'w');
	fwrite($handle, elgg_view('sphinx/conf'));
	fclose($handle);
}

elgg_register_event_handler('init', 'system', 'sphinx_init');
