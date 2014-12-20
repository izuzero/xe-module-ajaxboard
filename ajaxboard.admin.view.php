<?php
/*! Copyright (C) 2014 AJAXBOARD. All rights reserved. */
/**
 * @class  ajaxboardAdminView
 * @author Eunsoo Lee (contact@ajaxboard.co.kr)
 * @brief  Ajaxboard module admin view class.
 */

class ajaxboardAdminView extends ajaxboard
{
	function init()
	{
		$oAjaxboardModel = getModel('ajaxboard');
		$module_config = $oAjaxboardModel->getConfig();
		Context::set('module_config', $module_config);
		Context::set('module_path', $this->module_path);

		$security = new Security();
		$security->encodeHTML('module_config..');

		$this->setTemplatePath($this->module_path . 'tpl');
		$this->setTemplateFile(lcfirst(str_replace('dispAjaxboardAdmin', '', $this->act)));
	}

	function dispAjaxboardAdminPlugins()
	{
		$oAjaxboardModel = getModel('ajaxboard');
		$plugins_info = $oAjaxboardModel->getPluginsInfo();
		$plugin_list = $oAjaxboardModel->getPageHandler($plugins_info, Context::get('page'));

		Context::set('page', $plugin_list->page);
		Context::set('total_page', $plugin_list->total_page);
		Context::set('total_count', $plugin_list->total_count);
		Context::set('plugin_list', $plugin_list->data);
		Context::set('page_navigation', $plugin_list->page_navigation);
	}

	function dispAjaxboardAdminPluginConfig()
	{
		$plugin_name = Context::get('plugin_name');
		if (!is_string($plugin_name))
		{
			return new Object(-1, 'msg_invalid_request');
		}

		$oAjaxboardModel = getModel('ajaxboard');
		$plugin_info = $oAjaxboardModel->getPluginInfo($plugin_name);

		Context::set('plugin_info', $plugin_info->xml_info);
		Context::set('plugin_vars', $plugin_info->extra_vars);
		Context::set('attach_info', $plugin_info->attach_info);
	}

	function dispAjaxboardAdminBroadcast()
	{
		$oMemberAdminModel = getAdminModel('member');
		$oMemberModel = getModel('member');
		$output = $oMemberAdminModel->getMemberList();

		$filter = Context::get('filter_type');
		switch ($filter)
		{
			case 'super_admin':
				Context::set('filter_type_title', Context::getLang('cmd_show_super_admin_member'));
				break;
			case 'site_admin':
				Context::set('filter_type_title', Context::getLang('cmd_show_site_admin_member'));
				break;
			default:
				Context::set('filter_type_title', Context::getLang('cmd_show_all_member'));
				break;
		}
		if ($output->data)
		{
			foreach ($output->data as $key => $member)
			{
				$output->data[$key]->group_list = $oMemberModel->getMemberGroups($member->member_srl, 0);
			}
		}

		$module_config = $oMemberModel->getMemberConfig();
		$memberIdentifiers = array('user_id' => 'user_id', 'user_name' => 'user_name', 'nick_name' => 'nick_name');
		$usedIdentifiers = array();

		if(is_array($module_config->signupForm))
		{
			foreach($module_config->signupForm as $signupItem)
			{
				if (!count($memberIdentifiers))
				{
					break;
				}
				if (in_array($signupItem->name, $memberIdentifiers) && ($signupItem->required || $signupItem->isUse))
				{
					unset($memberIdentifiers[$signupItem->name]);
					$usedIdentifiers[$signupItem->name] = Context::getLang($signupItem->name);;
				}
			}
		}

		$group_list = $oMemberModel->getGroups();

		Context::set('total_count', $output->total_count);
		Context::set('total_page', $output->total_page);
		Context::set('page', $output->page);
		Context::set('group_list', $group_list);
		Context::set('member_list', $output->data);
		Context::set('usedIdentifiers', $usedIdentifiers);
		Context::set('page_navigation', $output->page_navigation);

		$security = new Security();
		$security->encodeHTML(
			'group_list..',
			'member_list..user_name',
			'member_list..nick_name',
			'member_list..group_list..'
		);
	}

	function dispAjaxboardAdminBroadcastPopup()
	{
		$receiver_srl = array();
		$member_srl = Context::get('receiver_srl');
		if (!is_array($member_srl))
		{
			$member_srl = array($member_srl);
		}

		$oMemberModel = getModel('member');
		$receiver_info = array();
		foreach ($member_srl as $val)
		{
			$output = $oMemberModel->getMemberInfoByMemberSrl($val);
			if ($output)
			{
				$receiver_srl[] = $val;
				$receiver_info[$val] = $output;
			}
		}

		Context::set('receiver_srl', $receiver_srl);
		Context::set('receiver_info', $receiver_info);

		$oEditorModel = getModel('editor');
		$option = new stdClass();
		$option->primary_key_name = 'receiver_srl[]';
		$option->content_key_name = 'message';
		$option->allow_fileupload = FALSE;
		$option->enable_autosave = FALSE;
		$option->enable_default_component = TRUE;
		$option->enable_component = FALSE;
		$option->resizable = FALSE;
		$option->disable_html = TRUE;
		$option->height = 200;
		$editor = $oEditorModel->getEditor($logged_info->member_srl, $option);
		Context::set('editor', $editor);

		$this->setLayoutPath('./common/tpl/');
		$this->setLayoutFile('popup_layout');
	}

	function dispAjaxboardAdminConfig()
	{
		$oLayoutModel = getModel('layout');
		$layout_list = $oLayoutModel->getLayoutList();
		$mlayout_list = $oLayoutModel->getLayoutList(0, 'M');

		$oModuleModel = getModel('module');
		$skin_list = $oModuleModel->getSkins($this->module_path);
		$mskin_list = $oModuleModel->getSkins($this->module_path, 'm.skins');

		Context::set('layout_list', $layout_list);
		Context::set('mlayout_list', $mlayout_list);
		Context::set('skin_list', $skin_list);
		Context::set('mskin_list', $mskin_list);
	}
}

/* End of file ajaxboard.admin.view.php */
/* Location: ./modules/ajaxboard/ajaxboard.admin.view.php */
