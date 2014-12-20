<?php
/*! Copyright (C) 2014 AJAXBOARD. All rights reserved. */
/**
 * @class  ajaxboardMobile
 * @author Eunsoo Lee (contact@ajaxboard.co.kr)
 * @brief  Ajaxboard module mobile view class.
 */

require_once(_XE_PATH_ . 'modules/ajaxboard/ajaxboard.view.php');

class ajaxboardMobile extends ajaxboardView
{
	function init()
	{
		$oAjaxboardModel = getModel('ajaxboard');
		$module_config = $oAjaxboardModel->getConfig();
		Context::set('module_config', $module_config);

		$tpl_path = sprintf('%sm.skins/%s', $this->module_path, $module_config->mskin);
		$this->module_info->mlayout_srl = $module_config->mlayout_srl;
		$this->setTemplatePath($tpl_path);
	}
}

/* End of file ajaxboard.mobile.php */
/* Location: ./modules/ajaxboard/ajaxboard.mobile.php */
