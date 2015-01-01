<?php
/*! Copyright (C) 2014 AJAXBOARD. All rights reserved. */
/**
 * @class  ajaxboard
 * @author Eunsoo Lee (contact@ajaxboard.co.kr)
 * @brief  Ajaxboard module high class.
 */

if (!function_exists('lcfirst'))
{
	require_once(_XE_PATH_ . 'modules/ajaxboard/packages/lcfirst.php');
}

require_once(_XE_PATH_ . 'modules/ajaxboard/packages/SocketIOEmitter.php');

class ajaxboard extends ModuleObject
{
	const SIO_VERSION = '1.2.1';

	private $columns = array(
		array('ajaxboard_notification_log', 'parent_member_srl', 'number', 11, 0, FALSE)
	);

	private $indexes = array(
		array('ajaxboard_notification_log', 'idx_parent_member_srl', array('parent_member_srl'), FALSE)
	);

	private $triggers = array(
		array( 'member.getMemberMenu',      'ajaxboard', 'controller', 'triggerMemberMenu',               'after'  ),
		array( 'document.insertDocument',   'ajaxboard', 'controller', 'triggerAfterInsertDocument',      'after'  ),
		array( 'document.deleteDocument',   'ajaxboard', 'controller', 'triggerAfterDeleteDocument',      'after'  ),
		array( 'document.updateVotedCount', 'ajaxboard', 'controller', 'triggerAfterUpdateVotedDocument', 'after'  ),
		array( 'comment.insertComment',     'ajaxboard', 'controller', 'triggerAfterInsertComment',       'after'  ),
		array( 'comment.deleteComment',     'ajaxboard', 'controller', 'triggerAfterDeleteComment',       'after'  ),
		array( 'comment.updateVotedCount',  'ajaxboard', 'controller', 'triggerAfterUpdateVotedComment',  'after'  ),
		array( 'moduleObject.proc',         'ajaxboard', 'controller', 'triggerAfterModuleObjectProc',    'after'  ),
		array( 'display',                   'ajaxboard', 'controller', 'triggerBeforeDisplay',            'before' )
	);

	function moduleInstall()
	{
		$oModuleController = getController('module');
		foreach ($this->triggers as $trigger)
		{
			$oModuleController->insertTrigger($trigger[0], $trigger[1], $trigger[2], $trigger[3], $trigger[4]);
		}

		return new Object();
	}

	function moduleUninstall()
	{
		$oModuleModel = getModel('module');
		$oModuleController = getController('module');
		foreach ($this->triggers as $trigger)
		{
			$oModuleController->deleteTrigger($trigger[0], $trigger[1], $trigger[2], $trigger[3], $trigger[4]);
		}

		return new Object();
	}

	function checkUpdate()
	{
		$oDB = DB::getInstance();
		$oModuleModel = getModel('module');
		foreach ($this->columns as $column)
		{
			if (!$oDB->isColumnExists($column[0], $column[1]))
			{
				return true;
			}
		}
		foreach ($this->indexes as $index)
		{
			if (!$oDB->isIndexExists($index[0], $index[1]))
			{
				return true;
			}
		}
		foreach ($this->triggers as $trigger)
		{
			if (!$oModuleModel->getTrigger($trigger[0], $trigger[1], $trigger[2], $trigger[3], $trigger[4]))
			{
				return true;
			}
		}

		return false;
	}

	function moduleUpdate()
	{
		$oDB = DB::getInstance();
		$oModuleModel = getModel('module');
		$oModuleController = getController('module');
		foreach ($this->columns as $column)
		{
			if (!$oDB->isColumnExists($column[0], $column[1]))
			{
				$oDB->addColumn($column[0], $column[1], $column[2], $column[3], $column[4], $column[5]);
			}
		}
		foreach ($this->indexes as $index)
		{
			if (!$oDB->isIndexExists($index[0], $index[1]))
			{
				$oDB->addIndex($index[0], $index[1], $index[2], $index[3]);
			}
		}
		foreach ($this->triggers as $trigger)
		{
			if (!$oModuleModel->getTrigger($trigger[0], $trigger[1], $trigger[2], $trigger[3], $trigger[4]))
			{
				$oModuleController->insertTrigger($trigger[0], $trigger[1], $trigger[2], $trigger[3], $trigger[4]);
			}
		}

		return new Object();
	}

	function close()
	{
		Context::close();
		exit();
	}
}

/* End of file ajaxboard.class.php */
/* Location: ./modules/ajaxboard/ajaxboard.class.php */
