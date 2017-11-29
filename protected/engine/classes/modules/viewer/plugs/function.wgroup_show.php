<?php
/*---------------------------------------------------------------------------
 * @Project: Alto CMS v.2.x.x
 * @Project URI: https://altocms.com
 * @Description: Advanced Community Engine
 * @Copyright: Alto CMS Team
 * @License: GNU GPL v2 & MIT
 *----------------------------------------------------------------------------
 */

/**
 * Plugin for Smarty
 * Display widget group
 *
 * @param   array                    $aParams
 * @param   Smarty_Internal_Template $oSmartyTemplate
 *
 * @return  string
 */
function smarty_function_wgroup_show($aParams, $oSmartyTemplate)
{
    static $aStack = [];

    if (empty($aParams['group']) && empty($aParams['name'])) {
        $sError = 'Parameter "group" does not define in {wgroup_show ...} function';
        if ($oSmartyTemplate->template_resource) {
            $sError .= ' (template: ' . $oSmartyTemplate->template_resource . ')';
        }
         \F::SysWarning($sError);
        return null;
    }

    if (empty($aParams['group']) && !empty($aParams['name'])) {
        $aParams['group'] = $aParams['name'];
        unset($aParams['name']);
    }

    $sWidgetGroup = $aParams['group'];
    $aWidgetParams = (isset($aParams['params']) ? array_merge($aParams['params'], $aParams): $aParams);

    if (isset($aStack[$sWidgetGroup])) {
        // wgroup nested in self
        $sError = 'Template function {wgroup group="' . $sWidgetGroup . '" nested in self ';
        if ($oSmartyTemplate->template_resource) {
            $sError .= ' (template: ' . $oSmartyTemplate->template_resource . ')';
        }
         \F::SysWarning($sError);
        return null;
    }

    // add group into the stack
    $aStack[$sWidgetGroup] = $aWidgetParams;

    $aWidgets = \E::Module('Viewer')->getWidgets();

    $sResult = '';
    if (isset($aWidgets[$sWidgetGroup])) {
        if (!function_exists('smarty_function_widget')) {
             \F::includeFile('function.widget.php');
        }
        foreach ($aWidgets[$sWidgetGroup] as $oWidget) {
            $sResult .= smarty_function_widget(array_merge($aWidgetParams, ['widget' => $oWidget]), $oSmartyTemplate);
        }
    }
    // Pop element off the stack
    array_pop($aStack);

    return $sResult;
}

// EOF