<?php
/*! Copyright (C) 2014 AJAXBOARD. All rights reserved. */
/**
 * @class  ajaxboardView
 * @author Eunsoo Lee (contact@ajaxboard.co.kr)
 * @brief  Ajaxboard module view class.
 */

class ajaxboardView extends ajaxboard
{
	function init()
	{
		$oAjaxboardModel = getModel('ajaxboard');
		$module_config = $oAjaxboardModel->getConfig();
		Context::set('module_config', $module_config);

		$tpl_path = sprintf('%sskins/%s', $this->module_path, $module_config->skin);
		$this->module_info->layout_srl = $module_config->layout_srl;
		$this->setTemplatePath($tpl_path);
	}

	function dispAjaxboardNotificationConfig()
	{
		$logged_info = Context::get('logged_info');
		if (!($logged_info && $GLOBALS['__ajaxboard__']['addon']['enabled'] === TRUE))
		{
			return new Object(-1, 'msg_not_permitted');
		}

		$oAjaxboardModel = getModel('ajaxboard');
		$menu_name = $GLOBALS['__ajaxboard__']['addon']['menu_name'];
		$user_info = $oAjaxboardModel->getAddonUserInfo();
		$selected = $oAjaxboardModel->getFilterUserInfo($logged_info->member_srl);
		Context::set('menu_name', $menu_name);
		Context::set('user_info', $user_info);
		Context::set('selected', $selected);

		$this->setTemplateFile('notification');
	}
}

/* End of file ajaxboard.view.php */
/* Location: ./modules/ajaxboard/ajaxboard.view.php */
