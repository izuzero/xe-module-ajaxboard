<?php
/*! Copyright (C) 2014 AJAXBOARD. All rights reserved. */
/**
 * @class  ajaxboardModel
 * @author Eunsoo Lee (contact@ajaxboard.co.kr)
 * @brief  Ajaxboard module model class.
 */

class ajaxboardModel extends ajaxboard
{
	function init()
	{
	}

	function getAjaxboardListener()
	{
		$uid = Context::get('uid');
		if (!is_string($uid))
		{
			return new Object(-1, 'msg_invalid_request');
		}
		$uid = md5($uid);
		if (!$uid)
		{
			return new Object(-1, 'msg_invalid_request');
		}

		$module_config = $this->getConfig();
		$oAjaxboardController = getController('ajaxboard');
		$oAjaxboardController->_printSSEHeader();
		print('retry: ' . $module_config->retry . PHP_EOL);

		$stack = array();
		$stack[] = $_SERVER['HTTP_LAST_EVENT_ID'];
		$stack[] = Context::get('lastEventId');
		$stack[] = 0;
		foreach ($stack as $item)
		{
			if (isset($item))
			{
				$last_event_id = floatval($item);
				break;
			}
		}

		$ipaddress = $this->getRealIP();
		$description = Context::getLang('msg_ajaxboard_auto_ip_denied');
		if ($ipaddress)
		{
			$denied_info = $this->getDeniedLog($ipaddress);
		}
		if ($denied_info)
		{
			$description = $denied_info->description;
		}

		$session = &$_SESSION['ajaxboard']['listener'];
		if (!is_array($session))
		{
			$session = array();
		}

		$abnormal = count($session) > 1799;
		if ($abnormal && $ipaddress)
		{
			$oAjaxboardController->insertDeniedLog($ipaddress, $description);
		}
		if ($denied_info || $abnormal)
		{
			$session = NULL;
			print('event: pollingDenied' . PHP_EOL);
			print('id: ' . $last_event_id++ . PHP_EOL);
			print('data: ' . json_encode($description) . PHP_EOL);
			print(PHP_EOL);
			$this->close();
		}
		foreach ($session as $key => $val)
		{
			if ($val['validation'] < date('YmdHis'))
			{
				$session[$key] = NULL;
			}
		}
		if (!$session[$uid])
		{
			$session[$uid]['validation'] = date('YmdHis', strtotime('30 minutes'));
		}

		$last_log = $this->getLatestNotificationLog();
		$last_id = $session[$uid]['id'];
		$session[$uid]['id'] = 0;
		if ($last_log)
		{
			$session[$uid]['id'] = $last_log->id;
			if ($last_log->id == $last_id)
			{
				$this->close();
			}
		}
		else
		{
			$this->close();
		}
		if (is_null($last_id))
		{
			$this->close();
		}

		$logged_info = Context::get('logged_info');
		$args = new stdClass();
		$args->excess_id = $last_id;
		$log_list = $this->getFilterNotificationLog($args);
		foreach ($log_list as $log)
		{
			$type = $log->type;
			unset($log->type);
			print('event: ' . $type . PHP_EOL);
			print('id: ' . $last_event_id++ . PHP_EOL);
			print('data: ' . json_encode($log) . PHP_EOL);
			print(PHP_EOL);
		}

		$this->close();
	}

	function getAjaxboardDocument()
	{
		$oDocumentModel = getModel('document');
		$oDocument = $oDocumentModel->getDocument(Context::get('document_srl'));
		if ($oDocument->get('document_srl'))
		{
			$oModuleModel = getModel('module');
			$module_info = $oModuleModel->getModuleInfoByModuleSrl($oDocument->get('module_srl'));

			$args = new stdClass();
			$args->is_exists     = $oDocument->isExists();
			$args->is_granted    = $oDocument->isGranted();
			$args->is_accessible = $oDocument->isAccessible();
			$args->module_srl    = $oDocument->get('module_srl');
			$args->document_srl  = $oDocument->get('document_srl');
			$args->member_srl    = $oDocument->getMemberSrl();
			$args->browser_title = $module_info->browser_title;
			$args->title         = $oDocument->getTitle();
			$args->content       = $oDocument->getContent(FALSE, FALSE, FALSE, FALSE, FALSE);
			$args->nickname      = $oDocument->getNickName();
			$args->voted_count   = $oDocument->get('voted_count');
			$args->blamed_count  = $oDocument->get('blamed_count');
			$this->adds($args);
		}
	}

	function getAjaxboardComment()
	{
		$oCommentModel = getModel('comment');
		$oComment = $oCommentModel->getComment(Context::get('comment_srl'));
		if ($oComment->get('comment_srl'))
		{
			$oModuleModel = getModel('module');
			$module_info = $oModuleModel->getModuleInfoByModuleSrl($oComment->get('module_srl'));
			if (!$oComment->get('parent_srl'))
			{
				$oComment->add('parent_srl', $oComment->get('document_srl'));
			}

			$args = new stdClass();
			$args->is_exists     = $oComment->isExists();
			$args->is_granted    = $oComment->isGranted();
			$args->is_accessible = $oComment->isAccessible();
			$args->module_srl    = $oComment->get('module_srl');
			$args->parent_srl    = $oComment->get('parent_srl');
			$args->document_srl  = $oComment->get('document_srl');
			$args->comment_srl   = $oComment->get('comment_srl');
			$args->member_srl    = $oComment->getMemberSrl();
			$args->browser_title = $module_info->browser_title;
			$args->content       = $oComment->getContent(FALSE, FALSE, FALSE);
			$args->nickname      = $oComment->getNickName();
			$args->voted_count   = $oComment->get('voted_count');
			$args->blamed_count  = $oComment->get('blamed_count');
			$this->adds($args);
		}
	}

	function getPageHandler($args = array(), $page = 1, $page_count = 10, $list_count = 20)
	{
		$page = (int)$page;
		$page_count = (int)$page_count;
		$list_count = (int)$list_count;
		if (!$page)
		{
			$page = 1;
		}
		if (!$page_count)
		{
			$page_count = 10;
		}
		if (!$list_count)
		{
			$list_count = 20;
		}

		$total_count = count($args);
		$total_page = $total_count ? (int)(($total_count - 1) / $list_count) + 1 : 1;

		$output = new Object();
		$output->total_count = $total_count;
		$output->total_page = $total_page;
		$output->page = $page;
		$output->page_navigation = new PageHandler($total_count, $total_page, $page, $page_count);
		$output->data = $page > $total_page ? array() : array_slice($args, ($page - 1) * $list_count, $list_count);

		return $output;
	}

	function getConfig()
	{
		if ($this->module_config)
		{
			return $this->module_config;
		}

		$oModuleModel = getModel('module');
		$module_config = $oModuleModel->getModuleConfig('ajaxboard');
		$module_config->waiting_message = $module_config->waiting_message !== 'N';
		$module_config->type            = isset($module_config->type)         ? (int)$module_config->type : 1;
		$module_config->server_port     = isset($module_config->server_port)  ? (int)$module_config->server_port : 3000;
		$module_config->storage_port    = isset($module_config->storage_port) ? (int)$module_config->storage_port : 6379;
		$module_config->timeout         = isset($module_config->timeout)      ? (int)$module_config->timeout : 30000;
		$module_config->retry           = isset($module_config->retry)        ? (int)$module_config->retry : 3000;
		if (!isset($module_config->layout_srl))
		{
			$module_config->layout_srl = -1;
		}
		if (!isset($module_config->mlayout_srl))
		{
			$module_config->mlayout_srl = -1;
		}
		if (!isset($module_config->skin))
		{
			$module_config->skin = 'default';
		}
		if (!isset($module_config->mskin))
		{
			$module_config->mskin = 'default';
		}

		return $this->module_config = $module_config;
	}

	function getTemplateConfig()
	{
		$oModuleModel = getModel('module');
		$module_info = $oModuleModel->getModuleInfoByMid(Context::get('mid'));

		$logged_info = Context::get('logged_info');
		$member_srl  = $logged_info->member_srl;

		$lang = new stdClass();
		$lang->msg_ajaxboard_delete_document   = Context::getLang('msg_ajaxboard_delete_document');
		$lang->msg_ajaxboard_delete_comment    = Context::getLang('msg_ajaxboard_delete_comment');
		$lang->msg_ajaxboard_password_required = Context::getLang('msg_ajaxboard_password_required');

		$module_config = $this->getConfig();
		$module_config->lang = $lang;
		$module_config->current_url  = Context::get('current_url');
		$module_config->request_uri  = Context::get('request_uri');
		$module_config->current_mid  = Context::get('mid');
		$module_config->document_srl = Context::get('document_srl');
		$module_config->module_srl   = $module_info->module_srl;
		$module_config->member_srl   = $member_srl;
		$module_config->SIO_VERSION  = self::SIO_VERSION;

		getDestroyXeVars($module_config);
		unset($module_config->layout_srl);
		unset($module_config->mlayout_srl);
		unset($module_config->skin);
		unset($module_config->mskin);
		unset($module_config->waiting_message);
		unset($module_config->storage_host);
		unset($module_config->storage_port);
		unset($module_config->storage_password);

		return $module_config;
	}

	function getPluginInfo($plugin_name, $column_list = array())
	{
		$hash_id = md5('plugin_name:' . trim((string)$plugin_name));
		$plugins_info = $this->getPluginsInfo($column_list);

		return $plugins_info[$hash_id];
	}

	function getPluginsInfo($column_list = array())
	{
		$plugin_list = $GLOBALS['__ajaxboard__']['plugin_list'];
		if (is_null($plugin_list))
		{
			$plugin_list = FALSE;
			$oCacheHandler = CacheHandler::getInstance('object', NULL, TRUE);
			if ($oCacheHandler->isSupport())
			{
				$object_key = 'plugin_list';
				$cache_key = $oCacheHandler->getGroupKey('ajaxboard', $object_key);
				$plugin_list = $oCacheHandler->get($cache_key);
			}
			if ($plugin_list === FALSE)
			{
				$plugin_list = array();
				$plugin_path = './modules/ajaxboard/plugins/';
				$dir_list = FileHandler::readDir($plugin_path);

				natcasesort($dir_list);
				foreach ($dir_list as $plugin_name)
				{
					if (is_dir($plugin_path . $plugin_name))
					{
						$plugin_list[] = $plugin_name;
					}
				}
				if ($oCacheHandler->isSupport())
				{
					$oCacheHandler->put($cache_key, $plugin_list);
				}
			}
		}
		$plugins_info = array();
		foreach ($plugin_list as $plugin_name)
		{
			$hash_id = md5('plugin_name:' . trim((string)$plugin_name));
			$plugin_info = $GLOBALS['__ajaxboard__']['plugin_info'][$hash_id];
			if (is_null($plugin_info))
			{
				$plugin_info = FALSE;
				if ($oCacheHandler->isSupport())
				{
					$object_key = 'plugin_info:' . $hash_id;
					$cache_key = $oCacheHandler->getGroupKey('ajaxboard', $object_key);
					$plugin_info = $oCacheHandler->get($cache_key);
				}
				if ($plugin_info === FALSE)
				{
					$args = new stdClass();
					$args->plugin_name = $plugin_name;
					$output = executeQuery('ajaxboard.getPluginInfo', $args);
					$plugin_info = $this->arrangePluginInfo($plugin_name, $output->data);
					if ($oCacheHandler->isSupport())
					{
						$oCacheHandler->put($cache_key, $plugin_info);
					}
				}
				$GLOBALS['__ajaxboard__']['plugin_info'][$hash_id] = $plugin_info;
			}
			$plugins_info[$hash_id] = $plugin_info;
		}
		if (count($column_list))
		{
			foreach ($plugins_info as &$plugin_info)
			{
				$temp = $plugin_info;
				$plugin_info = new stdClass();
				foreach ($temp as $key => $val)
				{
					if (in_array($key, $column_list))
					{
						$plugin_info->{$key} = $val;
					}
				}
			}
		}

		return $plugins_info;
	}

	function getEnabledPluginsInfo($divided = FALSE, $mobile = FALSE, $column_list = array())
	{
		$hash_key = 'enabled';
		if ($divided)
		{
			$hash_key .= ':' . ($mobile ? 'M' : 'P');
		}

		$hash_id = md5($hash_key);
		$output = $GLOBALS['__ajaxboard__']['plugin_info'][$hash_id];
		if (is_null($output))
		{
			$output = array();
			$plugins_info = $this->getPluginsInfo();
			foreach ($plugins_info as $key => $plugin_info)
			{
				$enabled = $divided ?
					($mobile ? $plugin_info->enable_mobile : $plugin_info->enable_pc) :
					($plugin_info->enable_pc || $plugin_info->enable_mobile);

				if ($enabled)
				{
					$output[$key] = $plugin_info;
				}
			}
			$GLOBALS['__ajaxboard__']['plugin_info'][$hash_id] = $output;
		}
		if (count($column_list))
		{
			foreach ($output as &$plugin_info)
			{
				$temp = $plugin_info;
				$plugin_info = new stdClass();
				foreach ($temp as $key => $val)
				{
					if (in_array($key, $column_list))
					{
						$plugin_info->{$key} = $val;
					}
				}
			}
		}

		return $output;
	}

	function getPluginsInfoByModuleSrl($module_srl, $mobile = FALSE, $column_list = array())
	{
		$hash_id = md5('module_srl:' . (int)$module_srl);
		$output = $GLOBALS['__ajaxboard__']['plugin_info'][$hash_id];
		if (is_null($output))
		{
			$output = array();
			$plugins_info = $this->getPluginsInfo();
			foreach ($plugins_info as $key => $plugin_info)
			{
				$attach_info = $plugin_info->attach_info;
				$enabled = $mobile ? $plugin_info->enable_mobile : $plugin_info->enable_pc;
				if ($enabled && in_array($module_srl, $attach_info))
				{
					$output[$key] = $plugin_info;
				}
			}
			$GLOBALS['__ajaxboard__']['plugin_info'][$hash_id] = $output;
		}
		if (count($column_list))
		{
			foreach ($output as &$plugin_info)
			{
				$temp = $plugin_info;
				$plugin_info = new stdClass();
				foreach ($temp as $key => $val)
				{
					if (in_array($key, $column_list))
					{
						$plugin_info->{$key} = $val;
					}
				}
			}
		}

		return $output;
	}

	function getPluginsInfoByMid($mid, $mobile = FALSE, $column_list = array())
	{
		$oModuleModel = getModel('module');
		$module_info = $oModuleModel->getModuleInfoByMid($mid);

		return $this->getPluginsInfoByModuleSrl($module_info->module_srl, $mobile, $column_list);;
	}

	function getPluginInfoXml($plugin_name, $extra_vals)
	{
		$plugin_path = $this->module_path . 'plugins/' . $plugin_name;
		$xml_file = $plugin_path . '/plugin.xml';
		if (!file_exists($xml_file))
		{
			return new stdClass();
		}

		$oXmlParser = new XmlParser();
		$xml_obj = $oXmlParser->loadXmlFile($xml_file);
		if ($xml_obj->plugin)
		{
			$xml_obj = $xml_obj->plugin;
		}
		else
		{
			return new stdClass();
		}

		if (!is_array($extra_vals))
		{
			$extra_vals = array($extra_vals);
		}

		$plugin_info = new stdClass();
		$plugin_info->title = $xml_obj->title->body;
		if ($xml_obj->version && $xml_obj->attrs->version == '1.0')
		{
			sscanf($xml_obj->date->body, '%d-%d-%d', $date_obj->y, $date_obj->m, $date_obj->d);
			$plugin_info->version = $xml_obj->version->body;
			$plugin_info->date = sprintf('%04d%02d%02d', $date_obj->y, $date_obj->m, $date_obj->d);
			$plugin_info->homepage = $xml_obj->link->body;
			$plugin_info->license = $xml_obj->license->body;
			$plugin_info->license_link = $xml_obj->license->attrs->link;
			$plugin_info->description = $xml_obj->description->body;

			if (is_array($xml_obj->author))
			{
				$author_list = $xml_obj->author;
			}
			else
			{
				$author_list[] = $xml_obj->author;
			}
			foreach ($author_list as $author)
			{
				$author_obj = new stdClass();
				$author_obj->name = $author->name->body;
				$author_obj->email_address = $author->attrs->email_address;
				$author_obj->homepage = $author->attrs->link;
				$plugin_info->author[] = $author_obj;
			}
			if ($xml_obj->extra_vars)
			{
				$extra_var_groups = $xml_obj->extra_vars->group;
				if (!$extra_var_groups)
				{
					$extra_var_groups = $xml_obj->extra_vars;
				}
				if (!is_array($extra_var_groups))
				{
					$extra_var_groups = array($extra_var_groups);
				}
				foreach ($extra_var_groups as $group)
				{
					$extra_vars = $group->var;
					if (!$extra_vars)
					{
						continue;
					}
					if (!is_array($extra_vars))
					{
						$extra_vars = array($extra_vars);
					}
					foreach ($extra_vars as $key => $val)
					{
						$obj = new stdClass();
						if (!$val->attrs->type)
						{
							$val->attrs->type = 'text';
						}
						$obj->group = $group->title->body;
						$obj->name = $val->attrs->name;
						$obj->title = $val->title->body;
						$obj->type = $val->attrs->type;
						$obj->description = $val->description->body;
						$obj->default = $val->attrs->default;
						if ($obj->name)
						{
							$obj->value = $extra_vals[$obj->name]->value;
						}
						if (strpos($obj->value, '|@|') != FALSE)
						{
							$obj->value = explode('|@|', $obj->value);
						}
						if (is_array($val->options))
						{
							$options_length = count($val->options);
							for ($i = 0; $i < $options_length; $i++)
							{
								$obj->options[$i] = new stdClass();
								$obj->options[$i]->title = $val->options[$i]->title->body;
								$obj->options[$i]->value = $val->options[$i]->attrs->value;
							}
						}
						else
						{
							$obj->options[0] = new stdClass();
							$obj->options[0]->title = $val->options->title->body;
							$obj->options[0]->value = $val->options->attrs->value;
						}

						$plugin_info->extra_vars[] = $obj;
					}
				}
			}
		}

		$colorset = $xml_obj->colorset->color;
		if ($colorset)
		{
			if (!is_array($colorset))
			{
				$colorset = array($colorset);
			}
			foreach ($colorset as $color)
			{
				$name = $color->attrs->name;
				$title = $color->title->body;
				$screenshot = $color->attrs->src;
				if ($screenshot)
				{
					$screenshot = $plugin_path . '/' . $screenshot;
					if (!file_exists($screenshot))
					{
						$screenshot = '';
					}
				}
				else
				{
					$screenshot = '';
				}

				$obj = new stdClass();
				$obj->name = $name;
				$obj->title = $title;
				$obj->screenshot = $screenshot;
				$plugin_info->colorset[] = $obj;
			}
		}

		$thumbnail = $plugin_path . '/thumbnail.png';
		if (!file_exists($thumbnail))
		{
			$thumbnail = NULL;
		}

		$plugin_info->thumbnail = $thumbnail;

		return $plugin_info;
	}

	function arrangePluginInfo($plugin_name, &$args, $insert = FALSE)
	{
		$plugin_info = new stdClass();
		$plugin_info->plugin_name = $plugin_name;

		if (!is_object($args))
		{
			$args = new stdClass();
		}
		if ($args->plugin_name)
		{
			$plugin_info->plugin_name = $args->plugin_name;
		}
		if ($insert)
		{
			$plugin_info->enable_pc = $args->enable_pc === TRUE ? 'Y' : 'N';
			$plugin_info->enable_mobile = $args->enable_mobile === TRUE ? 'Y' : 'N';
			$plugin_info->extra_vars = serialize($args->extra_vars);
		}
		else
		{
			$plugin_info->enable_pc = $args->enable_pc === 'Y';
			$plugin_info->enable_mobile = $args->enable_mobile === 'Y';
			$plugin_info->extra_vars = unserialize($args->extra_vars);
			$plugin_info->attach_info = $this->getAttachInfo($plugin_info->plugin_name);
			$plugin_info->xml_info = $this->getPluginInfoXml($plugin_info->plugin_name, $plugin_info->extra_vars);
		}

		return $args = $plugin_info;
	}

	function getAttachInfo($plugin_name)
	{
		$hash_id = md5('plugin_name:' . trim((string)$plugin_name));
		$attach_info = $GLOBALS['__ajaxboard__']['attach_info'][$hash_id];
		if (is_null($attach_info))
		{
			$attach_info = FALSE;
			$oCacheHandler = CacheHandler::getInstance('object', NULL, TRUE);
			if ($oCacheHandler->isSupport())
			{
				$object_key = 'attach_info:' . $hash_id;
				$cache_key = $oCacheHandler->getGroupKey('ajaxboard', $object_key);
				$attach_info = $oCacheHandler->get($cache_key);
			}
			if ($attach_info === FALSE)
			{
				$attach_info = array();
				$args = new stdClass();
				$args->plugin_name = $plugin_name;
				$output = executeQueryArray('ajaxboard.getAttachInfo', $args);
				foreach ($output->data as $val)
				{
					$attach_info[] = $val->target_srl;
				}
				if ($oCacheHandler->isSupport())
				{
					$oCacheHandler->put($cache_key, $attach_info);
				}
			}
			$GLOBALS['__ajaxboard__']['attach_info'][$hash_id] = $attach_info;
		}

		return $attach_info;
	}

	function getUserInfo($member_srl)
	{
		$hash_id = md5('member_srl:' . (int)$member_srl);
		$user_info = $GLOBALS['__ajaxboard__']['user_info'][$hash_id];
		if (is_null($user_info))
		{
			$user_info = FALSE;
			$oCacheHandler = CacheHandler::getInstance('object', NULL, TRUE);
			if ($oCacheHandler->isSupport())
			{
				$object_key = 'user_info:' . $hash_id;
				$cache_key = $oCacheHandler->getGroupKey('ajaxboard', $object_key);
				$user_info = $oCacheHandler->get($cache_key);
			}
			if ($user_info === FALSE)
			{
				$user_info = array();
				$args = new stdClass();
				$args->member_srl = $member_srl;
				$output = executeQueryArray('ajaxboard.getUserInfo', $args);
				foreach ($output->data as $val)
				{
					$user_info[] = $val->target_srl;
				}
				if ($oCacheHandler->isSupport())
				{
					$oCacheHandler->put($cache_key, $user_info);
				}
			}
			$GLOBALS['__ajaxboard__']['user_info'][$hash_id] = $user_info;
		}

		return $user_info;
	}

	function getAddonUserInfo($is_admin = FALSE)
	{
		$user_info = array();
		$logged_info = Context::get('logged_info');
		if (($is_admin || $logged_info) && $GLOBALS['__ajaxboard__']['addon']['enabled'] === TRUE)
		{
			$user_info = $GLOBALS['__ajaxboard__']['addon']['user_info'];
		}

		return $user_info;
	}

	function getFilterUserInfo($member_srl)
	{
		$addon_user_info = $this->getAddonUserInfo();
		$user_info = $this->getUserInfo($member_srl);
		if (!$user_info && $GLOBALS['__ajaxboard__']['addon']['selected'] === TRUE)
		{
			$user_info = $addon_user_info;
		}

		return array_intersect($addon_user_info, $user_info);
	}

	function getNotificationLog($args)
	{
		if (!is_object($args))
		{
			$args = new stdClass();
		}

		$output = executeQueryArray('ajaxboard.getNotificationLog', $args);
		$log_list = $output->data;
		foreach ($log_list as &$log)
		{
			$log->extra_vars = unserialize($log->extra_vars);
		}

		return $log_list;
	}

	function getLatestNotificationLog()
	{
		$output = executeQuery('ajaxboard.getLatestNotificationLog');
		return $output->data;
	}

	function getFilterNotificationLog($args, $member_srl)
	{
		if (!is_object($args))
		{
			$args = new stdClass();
		}
		if (is_null($member_srl))
		{
			$logged_info = Context::get('logged_info');
			$member_srl = $logged_info->member_srl;
		}

		$log_list = $this->getNotificationLog($args);
		foreach ($log_list as $key => $log)
		{
			unset($log_list[$key]->id);
			unset($log_list[$key]->regdate);
			if ($log->target_member_srl && $log->target_member_srl != $member_srl && in_array($log->type, array('sendMessage', 'broadcastMessage')))
			{
				unset($log_list[$key]);
			}
		}

		return $log_list;
	}

	function getDeniedLog($ipaddress)
	{
		$denied_log = $GLOBALS['__ajaxboard__']['denied_log'];
		if (is_null($denied_log))
		{
			$denied_log = FALSE;
			$oCacheHandler = CacheHandler::getInstance('object', NULL, TRUE);
			if ($oCacheHandler->isSupport())
			{
				$cache_key = $oCacheHandler->getGroupKey('ajaxboard', 'denied_log');
				$denied_log = $oCacheHandler->get($cache_key);
			}
			if ($denied_log === FALSE)
			{
				$denied_log = array();
				$output = executeQueryArray('ajaxboard.getDeniedLog');
				foreach ($output->data as $log)
				{
					$denied_log[$log->ipaddress] = $log;
				}
				if ($oCacheHandler->isSupport())
				{
					$oCacheHandler->put($cache_key, $denied_log);
				}
			}
			$GLOBALS['__ajaxboard__']['denied_log'] = $denied_log;
		}
		if ($ipaddress)
		{
			return $denied_log[$ipaddress];
		}

		return $denied_log;
	}

	function isValidIP($addr)
	{
		return !!filter_var($addr, FILTER_VALIDATE_IP);
	}

	function getRealIP()
	{
		$keys = array('HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED', 'HTTP_X_CLUSTER_CLIENT_IP', 'HTTP_FORWARDED_FOR', 'HTTP_FORWARDED', 'REMOTE_ADDR');
		foreach ($keys as $key)
		{
			if (array_key_exists($key, $_SERVER) === TRUE)
			{
				$stack = explode(',', $_SERVER[$key]);
				foreach ($stack as $addr)
				{
					$addr = trim($addr);
					if ($this->isValidIP($addr))
					{
						return $addr;
					}
				}
			}
		}

		return NULL;
	}

	function getRoomKey($args)
	{
		$keys = array();
		$queue = array();
		foreach ($args as $key => $val)
		{
			$keys[] = $key;
		}
		sort($keys);
		foreach ($keys as $key)
		{
			$val = $args->{$key};
			$queue[] = ((string)$key . ':' . (string)$val);
		}

		return implode(':', $queue);
	}

	function setArray($val)
	{
		if (is_object($val))
		{
			settype($val, 'array');
		}
		if (is_array($val))
		{
			foreach ($val as $k => $v)
			{
				$val[$k] = $this->setArray($v);
			}
		}

		return $val;
	}
}

/* End of file ajaxboard.model.php */
/* Location: ./modules/ajaxboard/ajaxboard.model.php */
