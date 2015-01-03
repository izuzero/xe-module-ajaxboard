<?php
/*! Copyright (C) 2014 AJAXBOARD. All rights reserved. */
/**
 * @class  ajaxboardController
 * @author Eunsoo Lee (contact@ajaxboard.co.kr)
 * @brief  Ajaxboard module controller class.
 */

class ajaxboardController extends ajaxboard
{
	function init()
	{
	}

	function procAjaxboardInsertNotificationConfig()
	{
		$logged_info = Context::get('logged_info');
		if (!($logged_info && $GLOBALS['__ajaxboard__']['addon']['enabled'] === TRUE))
		{
			return new Object(-1, 'msg_not_permitted');
		}

		$target_srls = Context::get('target_srl');
		if (!is_array($target_srls))
		{
			$target_srls = array($target_srls);
		}

		$oModuleModel = getModel('module');
		foreach ($target_srls as $key => $val)
		{
			if (!is_numeric($val))
			{
				unset($target_srls[$key]);
				continue;
			}

			$module_info = $oModuleModel->getModuleInfoByModuleSrl($val);
			if (!$module_info)
			{
				unset($target_srls[$key]);
			}
		}
		if (!$target_srls)
		{
			$target_srls = array(0);
		}

		$output = $this->updateUserInfo($logged_info->member_srl, $target_srls);
		if (!$output->toBool())
		{
			return $output;
		}

		$this->setMessage('success_saved');
		$this->setRedirectUrl(getNotEncodedUrl('', 'mid', Context::get('mid'), 'vid', Context::get('vid'), 'act', 'dispAjaxboardNotificationConfig'));
	}

	function procAjaxboardRedirect()
	{
		$type = strtoupper(Context::get('type'));
		switch ($type)
		{
			case 'C':
				$comment_srl = Context::get('target_srl');
				$oCommentModel = getModel('comment');
				$oComment = $oCommentModel->getComment($comment_srl);
				if ($oComment->get('document_srl'))
				{
					$redirect_url = getNotEncodedUrl('', 'document_srl', $oComment->get('document_srl')) . '#comment_' . $oComment->get('comment_srl');
				}
				break;
		}
		if (!$redirect_url)
		{
			return new Object(-1, 'msg_invalid_request');
		}

		$this->setRedirectUrl($redirect_url);
	}

	function insertPluginInfo($plugin_name, $args)
	{
		$oDB = DB::getInstance();
		$oDB->begin();

		$output = $this->deletePluginInfo($plugin_name);
		if (!$output->toBool())
		{
			$oDB->rollback();
			return $output;
		}

		if (!is_object($args))
		{
			$args = new stdClass();
		}
		$oAjaxboardModel = getModel('ajaxboard');
		$plugin_info = $oAjaxboardModel->arrangePluginInfo($plugin_name, $args, TRUE);
		$output = executeQuery('ajaxboard.insertPluginInfo', $plugin_info);
		if (!$output->toBool())
		{
			$oDB->rollback();
			return $output;
		}

		$oDB->commit();
		return $output;
	}

	function insertAddonUserInfo($target = array())
	{
		$user_info = array();
		$oModuleModel = getModel('module');
		foreach ($target as $key => $val)
		{
			$val = trim($val);
			if (!$val)
			{
				continue;
			}

			$module_info = is_numeric($val) ?
				$oModuleModel->getModuleInfoByModuleSrl($val) :
				$oModuleModel->getModuleInfoByMid($val);

			if ($module_info)
			{
				$user_info[] = $module_info->module_srl;
			}
		}

		$GLOBALS['__ajaxboard__']['addon']['user_info'] = $user_info;
		$GLOBALS['__ajaxboard__']['addon']['enabled'] = TRUE;
	}

	function insertNotificationLog($type, $args)
	{
		if (!($type && is_string($type)))
		{
			return new Object(-1, 'msg_invalid_request');
		}
		if (!is_object($args))
		{
			$args = new stdClass();
		}

		$logged_info = Context::get('logged_info');
		if ($logged_info && is_null($args->member_srl))
		{
			$args->member_srl = $logged_info->member_srl;
		}

		$args->type = $type;
		$args->extra_vars = serialize($args->extra_vars);
		$output = executeQuery('ajaxboard.insertNotificationLog', $args);
		if ($output->toBool())
		{
			$args->extra_vars = unserialize($args->extra_vars);
			$GLOBALS['__ajaxboard__']['notification_log'][] = $args;
		}

		return $output;
	}

	function insertDeniedLog($ipaddress, $description = '')
	{
		if (!(is_string($ipaddress) && is_string($description)))
		{
			return new Object('msg_invalid_request');
		}
		if (!preg_match('/^(?:25[0-5]|2[0-4]\d|1\d\d|[1-9]\d|\d)(?:[.](?:25[0-5]|2[0-4]\d|1\d\d|[1-9]\d|\d)){3}$/', $ipaddress))
		{
			return new Object(-1, 'msg_ajaxboard_invalid_ip');
		}

		$output = $this->deleteDeniedLog($ipaddress);
		if (!$output->toBool())
		{
			return $output;
		}

		$args = new stdClass();
		$args->ipaddress = $ipaddress;
		$args->description = $description;
		$output = executeQuery('ajaxboard.insertDeniedLog', $args);

		return $output;
	}

	function updatePluginInfo($plugin_name, $args)
	{
		if (!is_object($args))
		{
			$args = new stdClass();
		}
		$oAjaxboardModel = getModel('ajaxboard');
		$plugin_info = $oAjaxboardModel->getPluginInfo($plugin_name);
		foreach ($args as $key => $val)
		{
			$plugin_info->{$key} = $val;
		}

		return $this->insertPluginInfo($plugin_name, $plugin_info);
	}

	function updatePluginStatus($pc_list = array(), $mobile_list = array())
	{
		$oDB = DB::getInstance();
		$oDB->begin();

		$oAjaxboardModel = getModel('ajaxboard');
		$plugins_info = $oAjaxboardModel->getPluginsInfo();

		if (!is_array($pc_list))
		{
			$pc_list = array($pc_list);
		}
		if (!is_array($mobile_list))
		{
			$mobile_list = array($mobile_list);
		}
		foreach ($plugins_info as $key => $plugin_info)
		{
			$need_update = FALSE;
			if (in_array($plugin_info->plugin_name, $pc_list))
			{
				if (!$plugin_info->enable_pc)
				{
					$plugin_info->enable_pc
						= $need_update
						= TRUE;
				}
			}
			else if ($plugin_info->enable_pc)
			{
				$plugin_info->enable_pc = FALSE;
				$need_update = TRUE;
			}
			if (in_array($plugin_info->plugin_name, $mobile_list))
			{
				if (!$plugin_info->enable_mobile)
				{
					$plugin_info->enable_mobile
						= $need_update
						= TRUE;
				}
			}
			else if ($plugin_info->enable_mobile)
			{
				$plugin_info->enable_mobile = FALSE;
				$need_update = TRUE;
			}
			if (!$need_update)
			{
				continue;
			}

			$output = $this->updatePluginInfo($plugin_info->plugin_name, $plugin_info);
			if (!$output->toBool())
			{
				$oDB->rollback();
				return $output;
			}
		}

		$oDB->commit();
		return new Object();
	}

	function updatePluginVars($plugin_name, $extra_vars)
	{
		if (!(is_string($plugin_name) && is_object($extra_vars)))
		{
			return new Object(-1, 'msg_invalid_request');
		}

		getDestroyXeVars($extra_vars);
		$oAjaxboardModel = getModel('ajaxboard');
		$plugin_info = $oAjaxboardModel->getPluginInfo($plugin_name);
		$plugin_vars = $plugin_info->xml_info->extra_vars;
		$hash_id = md5('plugin_name:' . trim((string)$plugin_name));

		foreach ($plugin_vars as $key => $val)
		{
			if ($val->type == 'image')
			{
				$img = $extra_vars->{$val->name};
				$del = $extra_vars->{'del_' . $val->name};
				unset($extra_vars->{'del_' . $val->name});

				if ($del == 'Y')
				{
					FileHandler::removeFile($val->value);
					unset($extra_vars->{$val->name});
					continue;
				}
				if (!$img['tmp_name'] && $val->value)
				{
					$extra_vars->{$val->name} = $val->value;
					continue;
				}

				$img_path = './files/attach/images/ajaxboard/' . $hash_id;
				$img_file = $img_path . '/' . $img['name'];

				if (!(is_uploaded_file($img['tmp_name']) && checkUploadedFile($img['tmp_name']) && preg_match('/\.(jpg|jpeg|gif|png)$/i', $img['name']) && FileHandler::makeDir($img_path) && move_uploaded_file($img['tmp_name'], $img_file)))
				{
					unset($extra_vars->{$val->name});
					continue;
				}

				FileHandler::removeFile($val->value);
				$extra_vars->{$val->name} = $img_file;
			}
			if ($val->type == 'module_srl')
			{
				$module_srls = array();
				if ($extra_vars->{$val->name})
				{
					$module_srls = explode(',', $extra_vars->{$val->name});
				}
				foreach ($module_srls as $key => $module_srl)
				{
					$module_srls[$key] = (int)$module_srl;
				}

				$extra_vars->{$val->name} =	$module_srls;
			}
		}

		$args = new stdClass();
		$args->plugin_name = $plugin_name;
		$args->extra_vars = array();
		foreach ($extra_vars as $key => $val)
		{
			$params = new stdClass();
			$params->name = trim($key);
			$params->value = $val;
			$args->extra_vars[$key] = $params;
		}

		return $this->updatePluginInfo($plugin_name, $args);
	}

	function updateAttachInfo($plugin_name, $module_srls = array())
	{
		$oDB = DB::getInstance();
		$oDB->begin();

		$output = $this->deleteAttachInfo($plugin_name);
		if (!$output->toBool())
		{
			$oDB->rollback();
			return $output;
		}

		$args = new stdClass();
		$args->plugin_name = $plugin_name;
		foreach ($module_srls as $module_srl)
		{
			$args->target_srl = (int)$module_srl;
			$output = executeQuery('ajaxboard.insertAttachInfo', $args);
			if (!$output->toBool())
			{
				$oDB->rollback();
				return $output;
			}
		}

		$oDB->commit();
		return new Object();
	}

	function updateUserInfo($member_srl, $module_srls = array())
	{
		$oDB = DB::getInstance();
		$oDB->begin();

		$output = $this->deleteUserInfo($member_srl);
		if (!$output->toBool())
		{
			$oDB->rollback();
			return $output;
		}

		$args = new stdClass();
		$args->member_srl = $member_srl;
		foreach ($module_srls as $module_srl)
		{
			$args->target_srl = $module_srl;
			$output = executeQuery('ajaxboard.insertUserInfo', $args);
			if (!$output->toBool())
			{
				$oDB->rollback();
				return $output;
			}
		}

		$oDB->commit();
		return new Object();
	}

	function deletePluginInfo($plugin_name, $args)
	{
		if (!is_object($args))
		{
			$args = new stdClass();
		}
		$args->plugin_name = $plugin_name;
		$output = executeQuery('ajaxboard.deletePluginInfo', $args);
		if ($output->toBool())
		{
			unset($GLOBALS['__ajaxboard__']['plugin_info']);
			$oCacheHandler = CacheHandler::getInstance('object', NULL, TRUE);
			if ($oCacheHandler->isSupport())
			{
				$oCacheHandler->invalidateGroupKey('ajaxboard');
			}
		}

		return $output;
	}

	function deleteAttachInfo($plugin_name, $module_srl)
	{
		$args = new stdClass();
		$args->plugin_name = $plugin_name;
		if ($module_srl)
		{
			$args->target_srl = $module_srl;
		}
		$output = executeQuery('ajaxboard.deleteAttachInfo', $args);
		if ($output->toBool())
		{
			unset($GLOBALS['__ajaxboard__']['plugin_info']);
			unset($GLOBALS['__ajaxboard__']['attach_info']);
			$oCacheHandler = CacheHandler::getInstance('object', NULL, TRUE);
			if ($oCacheHandler->isSupport())
			{
				$oCacheHandler->invalidateGroupKey('ajaxboard');
			}
		}

		return $output;
	}

	function deleteUserInfo($member_srl, $module_srl)
	{
		$args = new stdClass();
		$args->member_srl = (int)$member_srl;
		if ($module_srl)
		{
			$args->target_srl = $module_srl;
		}
		$output = executeQuery('ajaxboard.deleteUserInfo', $args);
		if ($output->toBool())
		{
			unset($GLOBALS['__ajaxboard__']['user_info']);
			$oCacheHandler = CacheHandler::getInstance('object', NULL, TRUE);
			if ($oCacheHandler->isSupport())
			{
				$oCacheHandler->invalidateGroupKey('ajaxboard');
			}
		}

		return $output;
	}

	function deleteDeniedLog($ipaddress)
	{
		if (!is_string($ipaddress))
		{
			return new Object('msg_invalid_request');
		}
		if (!preg_match('/^(?:25[0-5]|2[0-4]\d|1\d\d|[1-9]\d|\d)(?:[.](?:25[0-5]|2[0-4]\d|1\d\d|[1-9]\d|\d)){3}$/', $ipaddress))
		{
			return new Object(-1, 'msg_ajaxboard_invalid_ip');
		}

		$args = new stdClass();
		$args->ipaddress = $ipaddress;
		$output = executeQuery('ajaxboard.deleteDeniedLog', $args);
		if ($output->toBool())
		{
			unset($GLOBALS['__ajaxboard__']['denied_log']);
			$oCacheHandler = CacheHandler::getInstance('object', NULL, TRUE);
			if ($oCacheHandler->isSupport())
			{
				$oCacheHandler->invalidateGroupKey('ajaxboard');
			}
		}

		return $output;
	}

	function triggerMemberMenu()
	{
		$member_srl = Context::get('target_srl');
		$logged_info = Context::get('logged_info');
		if ($logged_info->member_srl != $member_srl && $logged_info->is_admin == 'Y')
		{
			$oMemberController = getController('member');
			$oMemberController->addMemberPopupMenu(getUrl('', 'module', 'ajaxboard', 'act', 'dispAjaxboardAdminBroadcastPopup', 'receiver_srl', $member_srl), 'cmd_ajaxboard_send_notification', '', 'popup');
		}

		return new Object();
	}

	function triggerAfterInsertDocument(&$obj)
	{
		$oAjaxboardModel = getModel('ajaxboard');
		$plugins_info = $oAjaxboardModel->getEnabledPluginsInfo();
		if (count($plugins_info))
		{
			$args = new stdClass();
			$args->module_srl = $obj->module_srl;
			$args->target_srl = $obj->document_srl;
			$args->target_member_srl = $obj->member_srl;
			return $this->insertNotificationLog('insertDocument', $args);
		}

		return new Object();
	}

	function triggerAfterDeleteDocument(&$obj)
	{
		$oAjaxboardModel = getModel('ajaxboard');
		$plugins_info = $oAjaxboardModel->getEnabledPluginsInfo();
		if (count($plugins_info))
		{
			$args = new stdClass();
			$args->module_srl = $obj->module_srl;
			$args->target_srl = $obj->document_srl;
			$args->target_member_srl = $obj->member_srl;
			return $this->insertNotificationLog('deleteDocument', $args);
		}

		return new Object();
	}

	function triggerAfterUpdateVotedDocument(&$obj)
	{
		$oAjaxboardModel = getModel('ajaxboard');
		$plugins_info = $oAjaxboardModel->getEnabledPluginsInfo();
		if (count($plugins_info))
		{
			$args = new stdClass();
			$args->module_srl = $obj->module_srl;
			$args->target_srl = $obj->document_srl;
			$args->target_member_srl = $obj->member_srl;
			$args->extra_vars = new stdClass();
			$args->extra_vars->point = $obj->after_point;
			return $this->insertNotificationLog('voteDocument', $args);
		}

		return new Object();
	}

	function triggerAfterInsertComment(&$obj)
	{
		$oAjaxboardModel = getModel('ajaxboard');
		$plugins_info = $oAjaxboardModel->getEnabledPluginsInfo();
		if (count($plugins_info))
		{
			$args = new stdClass();
			$args->module_srl = $obj->module_srl;
			$args->target_srl = $obj->comment_srl;
			$args->target_member_srl = $obj->member_srl;
			$args->parent_srl = $obj->parent_srl;
			if ($args->parent_srl)
			{
				$oCommentModel = getModel('comment');
				$oComment = $oCommentModel->getComment($args->parent_srl);
				$args->parent_member_srl = $oComment->get('member_srl');
			}
			else
			{
				$oDocumentModel = getModel('document');
				$oDocument = $oDocumentModel->getDocument($obj->document_srl);
				$args->parent_srl = $oDocument->get('document_srl');
				$args->parent_member_srl = $oDocument->get('member_srl');
			}

			return $this->insertNotificationLog('insertComment', $args);
		}

		return new Object();
	}

	function triggerAfterDeleteComment(&$obj)
	{
		$oAjaxboardModel = getModel('ajaxboard');
		$plugins_info = $oAjaxboardModel->getEnabledPluginsInfo();
		if (count($plugins_info))
		{
			$args = new stdClass();
			$args->module_srl = $obj->module_srl;
			$args->target_srl = $obj->comment_srl;
			$args->target_member_srl = $obj->member_srl;
			$args->parent_srl = $obj->parent_srl;
			if ($args->parent_srl)
			{
				$oCommentModel = getModel('comment');
				$oComment = $oCommentModel->getComment($args->parent_srl);
				$args->parent_member_srl = $oComment->get('member_srl');
			}
			else
			{
				$oDocumentModel = getModel('document');
				$oDocument = $oDocumentModel->getDocument($obj->document_srl);
				$args->parent_srl = $oDocument->get('document_srl');
				$args->parent_member_srl = $oDocument->get('member_srl');
			}

			return $this->insertNotificationLog('deleteComment', $args);
		}

		return new Object();
	}

	function triggerAfterUpdateVotedComment(&$obj)
	{
		$oAjaxboardModel = getModel('ajaxboard');
		$plugins_info = $oAjaxboardModel->getEnabledPluginsInfo();
		if (count($plugins_info))
		{
			$oCommentModel = getModel('comment');
			$oComment = $oCommentModel->getComment($obj->comment_srl);
			$args = new stdClass();
			$args->module_srl = $obj->module_srl;
			$args->target_srl = $obj->comment_srl;
			$args->target_member_srl = $obj->member_srl;
			$args->parent_srl = $oComment->get('parent_srl');
			$args->extra_vars = new stdClass();
			$args->extra_vars->point = $obj->after_point;
			if ($args->parent_srl)
			{
				$oComment = $oCommentModel->getComment($args->parent_srl);
				$args->parent_member_srl = $oComment->get('member_srl');
			}
			else
			{
				$oDocumentModel = getModel('document');
				$oDocument = $oDocumentModel->getDocument($oComment->get('document_srl'));
				$args->parent_srl = $oDocument->get('document_srl');
				$args->parent_member_srl = $oDocument->get('member_srl');
			}

			return $this->insertNotificationLog('voteComment', $args);
		}

		return new Object();
	}

	function triggerAfterModuleObjectProc(&$oModule)
	{
		$oAjaxboardModel = getModel('ajaxboard');
		$module_config = $oAjaxboardModel->getConfig();
		$log_list = $GLOBALS['__ajaxboard__']['notification_log'];
		if ($module_config->type == 1 && is_array($log_list) && extension_loaded('redis'))
		{
			$host = parse_url($module_config->storage_host);
			$host = $host['host'] ? $host['host'] : '127.0.0.1';
			$port = $module_config->storage_port;
			$passwd = $module_config->storage_password;
			$timeout = $module_config->timeout / 1000;

			$redis = new Redis();
			if (!$redis->connect($host, $port, $timeout))
			{
				return new Object();
			}
			if ($passwd && !$redis->auth($passwd))
			{
				return new Object();
			}

			$emitter = new SocketIOEmitter($redis);
			foreach ($log_list as $log)
			{
				$log = $oAjaxboardModel->setArray($log);
				$type = $log['type'];
				unset($log['type']);
				switch ($type)
				{
					case 'broadcastMessage':
						if ($log['target_member_srl'])
						{
							$args = new stdClass();
							$args->member_srl = $log['target_member_srl'];
							$room_key = $oAjaxboardModel->getRoomKey($args);
							$emitter->in($room_key)->emit($type, $log);
							break;
						}
					default:
						$emitter->broadcast->emit($type, $log);
						break;
				}
			}

			$redis->close();
		}

		return new Object();
	}

	function triggerBeforeDisplay(&$output)
	{
		if (Context::getResponseMethod() == 'HTML')
		{
			$mid = Context::get('mid');
			if ($mid)
			{
				$oAjaxboardModel = getModel('ajaxboard');
				$plugins_info = $oAjaxboardModel->getPluginsInfoByMid($mid, Mobile::isFromMobilePhone());
				if (count($plugins_info))
				{
					$module_config = $oAjaxboardModel->getConfig();
					if ($module_config->type == 1)
					{
						Context::loadFile($this->module_path . 'tpl/js/libs/socket.io.js', 'head');
					}
					Context::loadFile($this->module_path . 'tpl/js/libs/eventsource.js', 'head');
					Context::loadFile($this->module_path . 'tpl/js/client.js', 'head');

					$oTemplate = TemplateHandler::getInstance();
					Context::set('waiting_message', $module_config->waiting_message);
					Context::set('module_config', $oAjaxboardModel->getTemplateConfig());
					$compile = $oTemplate->compile($this->module_path . 'tpl', 'templateConfig');
					$output .= $compile;

					$logged_info = Context::get('logged_info');
					$user_info = $oAjaxboardModel->getFilterUserInfo($logged_info->member_srl);
					Context::set('user_info', $user_info);
					foreach ($plugins_info as $plugin_info)
					{
						Context::set('plugin_info', $plugin_info);
						$plugin_name = $plugin_info->plugin_name;
						$plugin_path = $this->module_path . 'plugins/' . $plugin_name;
						$compile = $oTemplate->compile($plugin_path, 'plugin');
						$output .= $compile;
					}
				}
			}
		}

		return new Object();
	}

	function _printSSEHeader()
	{
		header('Content-Type: text/event-stream; charset=UTF-8');
		header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');
		header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . ' GMT');
		header('Cache-Control: no-store, no-cache, must-revalidate');
		header('Cache-Control: post-check=0, pre-check=0', FALSE);
		header('Pragma: no-cache');
	}
}

/* End of file ajaxboard.controller.php */
/* Location: ./modules/ajaxboard/ajaxboard.controller.php */
