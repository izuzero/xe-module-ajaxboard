<?php
/*! Copyright (C) 2014 AJAXBOARD. All rights reserved. */
/**
 * @class  ajaxboardAdminController
 * @author Eunsoo Lee (contact@ajaxboard.co.kr)
 * @brief  Ajaxboard module admin controller class.
 */

class ajaxboardAdminController extends ajaxboard
{
	function init()
	{
		$this->setTemplatePath($this->module_path . 'tpl');
	}

	function procAjaxboardAdminInsertConfig()
	{
		$oModuleController = getController('module');

		$config = Context::getRequestVars();
		getDestroyXeVars($config);
		unset($config->module);
		unset($config->act);

		if ($config->del_storage_password)
		{
			$config->storage_password = '';
			unset($config->del_storage_password);
		}

		$output = $oModuleController->updateModuleConfig('ajaxboard', $config);
		if (!$output->toBool())
		{
			return $output;
		}

		$this->setMessage('success_updated');
		$this->setRedirectUrl(getNotEncodedUrl('', 'module', 'admin', 'act', 'dispAjaxboardAdminConfig'));
	}

	function procAjaxboardAdminInsertPlugin()
	{
		$enable_pc = Context::get('enable_pc');
		$enable_mobile = Context::get('enable_mobile');
		$oAjaxboardController = getController('ajaxboard');
		$output = $oAjaxboardController->updatePluginStatus($enable_pc, $enable_mobile);
		if (!$output->toBool())
		{
			return $output;
		}

		$this->setMessage('success_updated');
		$this->setRedirectUrl(getNotEncodedUrl('', 'module', 'admin', 'act', 'dispAjaxboardAdminPlugins', 'page', Context::get('page')));
	}

	function procAjaxboardAdminUpdatePlugin()
	{
		$plugin_name = Context::get('plugin_name');
		if (!is_string($plugin_name))
		{
			return new Object(-1, 'msg_invalid_request');
		}

		$extra_vars = Context::getRequestVars();
		$module_srls = explode(',', $extra_vars->target_module_srl);
		getDestroyXeVars($extra_vars);
		unset($extra_vars->module);
		unset($extra_vars->act);
		unset($extra_vars->mid);
		unset($extra_vars->vid);
		unset($extra_vars->plugin_name);
		unset($extra_vars->target_module_srl);

		$oAjaxboardController = getController('ajaxboard');
		$output = $oAjaxboardController->updatePluginVars($plugin_name, $extra_vars);
		if (!$output->toBool())
		{
			return $output;
		}

		$output = $oAjaxboardController->updateAttachInfo($plugin_name, $module_srls);
		if (!$output->toBool())
		{
			return $output;
		}

		$this->setMessage('success_saved');
		$this->setRedirectUrl(getNotEncodedUrl('', 'module', 'admin', 'act', 'dispAjaxboardAdminPluginConfig', 'plugin_name', $plugin_name, 'page', Context::get('page')));
	}

	function procAjaxboardAdminInsertDeniedLog()
	{
		$ipaddress = Context::get('ipaddress');
		$description = Context::get('description');
		if (!is_array($ipaddress))
		{
			$ipaddress = array($ipaddress);
		}
		if (!is_array($description))
		{
			$description = array($description);
		}

		$oAjaxboardController = getController('ajaxboard');
		$len = count($ipaddress);
		for ($i = 0; $i < $len; $i++)
		{
			if (!(is_string($ipaddress[$i]) && is_string($description[$i]) && preg_match('/^(?:25[0-5]|2[0-4]\d|1\d\d|[1-9]\d|\d)(?:[.](?:25[0-5]|2[0-4]\d|1\d\d|[1-9]\d|\d)){3}$/', $ipaddress[$i])))
			{
				continue;
			}

			$description[$i] = htmlspecialchars($description[$i], ENT_COMPAT | ENT_HTML401, 'UTF-8', FALSE);
			$output = $oAjaxboardController->insertDeniedLog($ipaddress[$i], $description[$i]);
			if (!$output->toBool())
			{
				return $output;
			}
		}

		$this->setMessage('success_saved');
		$this->setRedirectUrl(getNotEncodedUrl('', 'module', 'admin', 'act', 'dispAjaxboardAdminDeniedLog', 'page', Context::get('page')));
	}

	function procAjaxboardAdminDeleteDeniedLog()
	{
		$oAjaxboardController = getController('ajaxboard');
		$ipaddress = Context::get('ipaddress');
		$output = $oAjaxboardController->deleteDeniedLog($ipaddress);
		if (!$output->toBool())
		{
			return $output;
		}

		$this->setMessage('success_deleted');
		$this->setRedirectUrl(getNotEncodedUrl('', 'module', 'admin', 'act', 'dispAjaxboardAdminDeniedLog', 'page', Context::get('page')));
	}

	function procAjaxboardAdminBroadcast()
	{
		$message = Context::get('message');
		$receiver_srl = Context::get('receiver_srl');

		$stack = array();
		if (count($receiver_srl) < 2 && $receiver_srl[0] == 0)
		{
			$stack[] = 0;
		}
		else
		{
			$oMemberModel = getModel('member');
			foreach ($receiver_srl as $member_srl)
			{
				$member_info = $oMemberModel->getMemberInfoByMemberSrl($member_srl);
				if ($member_info)
				{
					$stack[] = $member_srl;
				}
			}
		}

		$args = new stdClass();
		$args->extra_vars = new stdClass();
		$args->extra_vars->message = $message;
		$oAjaxboardController = getController('ajaxboard');
		foreach ($stack as $member_srl)
		{
			$args->target_member_srl = $member_srl;
			$oAjaxboardController->insertNotificationLog('broadcastMessage', $args);
		}

		Context::set('message', 'success_sended');
		$this->setTemplateFile('closePopup');
	}
}

/* End of file ajaxboard.admin.controller.php */
/* Location: ./modules/ajaxboard/ajaxboard.admin.controller.php */
