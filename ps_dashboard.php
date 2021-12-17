<?php
/**
 * 2007-2016 PrestaShop
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License (AFL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/afl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@prestashop.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade PrestaShop to newer
 * versions in the future. If you wish to customize PrestaShop for your
 * needs please refer to http://www.prestashop.com for more information.
 *
 * @author    PrestaShop SA <contact@prestashop.com>
 * @copyright 2007-2016 PrestaShop SA
 * @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 * International Registered Trademark & Property of PrestaShop SA
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

$autoloadPath = __DIR__ . '/vendor/autoload.php';
if (file_exists($autoloadPath)) {
    require_once $autoloadPath;
}

use PrestaShop\Module\Dashboard\HookDispatcher;
use PrestaShop\PrestaShop\Core\Module\WidgetInterface;

class ps_dashboard extends Module implements WidgetInterface
{
    /**
     * @var Db
     */
    private $database;

    /**
     * @var HookDispatcher
     */
    private $hookDispatcher;

    public function __construct()
    {
        $this->name = 'ps_dashboard';
        $this->tab = 'ps_dashboard';
        $this->version = '1.0.0';
        $this->author = 'PrestaShop';

        parent::__construct();
        $this->displayName = $this->trans('Dashboard Activity', array(), 'Modules.Dashactivity.Admin');
        $this->ps_versions_compliancy = array('min' => '8.0.0', 'max' => _PS_VERSION_);
        $this->hookDispatcher = new HookDispatcher($this);
    }

    public function install()
    {
        Configuration::updateValue('DASHACTIVITY_CART_ACTIVE', 30);
        Configuration::updateValue('DASHACTIVITY_CART_ABANDONED_MIN', 24);
        Configuration::updateValue('DASHACTIVITY_CART_ABANDONED_MAX', 48);
        Configuration::updateValue('DASHACTIVITY_VISITOR_ONLINE', 30);

        return (parent::install()
            && $this->registerHook($this->getHookDispatcher()->getAvailableHooks())
        );
    }

    /**
     * Return current context
     *
     * @return Context
     */
    public function getContext(): Context
    {
        return $this->context;
    }

    /**
     * Return the current database instance
     *
     * @return Db
     */
    public function getDatabase(): Db
    {
        if ($this->database === null) {
            $this->database = Db::getInstance();
        }

        return $this->database;
    }

    /**
     * @return HookDispatcher
     */
    public function getHookDispatcher(): HookDispatcher
    {
        return $this->hookDispatcher;
    }

    /**
     * Dispatch hooks
     *
     * @param string $methodName
     * @param array $arguments
     */
    public function __call(string $methodName, array $arguments)
    {
        return $this->getHookDispatcher()->dispatch(
            $methodName,
            !empty($arguments[0]) ? $arguments[0] : []
        );
    }

    /**
     * Render template
     *
     * @param string $template
     * @param array $params
     *
     * @return string
     */
    public function render($template, array $params = [])
    {
        $this->context->smarty->assign($params);

        return $this->display(__FILE__, $template);
    }

    public function renderWidget($hookName, array $configuration) {

    }
    public function getWidgetVariables($hookName, array $configuration) {

    }
    public function renderConfigForm()
    {
        $fields_form = [
            'form' => [
                'id_form' => 'step_carrier_general',
                'input' => [
                    [
                        'label' => $this->trans('Active cart', [], 'Modules.Dashactivity.Admin'),
                        'hint' => $this->trans('How long (in minutes) a cart is to be considered as active after the last recorded change (default: 30 min).', [], 'Modules.Dashactivity.Admin'),
                        'name' => 'DASHACTIVITY_CART_ACTIVE',
                        'type' => 'select',
                        'options' => [
                            'query' => [
                                ['id' => 15, 'name' => 15],
                                ['id' => 30, 'name' => 30],
                                ['id' => 45, 'name' => 45],
                                ['id' => 60, 'name' => 60],
                                ['id' => 90, 'name' => 90],
                                ['id' => 120, 'name' => 120],
                            ],
                            'id' => 'id',
                            'name' => 'name',
                        ],
                    ],
                    [
                        'label' => $this->trans('Online visitor', [], 'Modules.Dashactivity.Admin'),
                        'hint' => $this->trans('How long (in minutes) a visitor is to be considered as online after their last action (default: 30 min).', [], 'Modules.Dashactivity.Admin'),
                        'name' => 'DASHACTIVITY_VISITOR_ONLINE',
                        'type' => 'select',
                        'options' => [
                            'query' => [
                                ['id' => 15, 'name' => 15],
                                ['id' => 30, 'name' => 30],
                                ['id' => 45, 'name' => 45],
                                ['id' => 60, 'name' => 60],
                                ['id' => 90, 'name' => 90],
                                ['id' => 120, 'name' => 120],
                            ],
                            'id' => 'id',
                            'name' => 'name',
                        ],
                    ],
                    [
                        'label' => $this->trans('Abandoned cart (min)', [], 'Modules.Dashactivity.Admin'),
                        'hint' => $this->trans('How long (in hours) after the last action a cart is to be considered as abandoned (default: 24 hrs).', [], 'Modules.Dashactivity.Admin'),
                        'name' => 'DASHACTIVITY_CART_ABANDONED_MIN',
                        'type' => 'text',
                        'suffix' => $this->trans('hrs', [], 'Modules.Dashactivity.Admin'),
                    ],
                    [
                        'label' => $this->trans('Abandoned cart (max)', [], 'Modules.Dashactivity.Admin'),
                        'hint' => $this->trans('How long (in hours) after the last action a cart is no longer to be considered as abandoned (default: 24 hrs).', [], 'Modules.Dashactivity.Admin'),
                        'name' => 'DASHACTIVITY_CART_ABANDONED_MAX',
                        'type' => 'text',
                        'suffix' => $this->trans('hrs', [], 'Modules.Dashactivity.Admin'),
                    ]
                ],
                'submit' => [
                    'title' => $this->trans('Save', [], 'Admin.Actions'),
                    'class' => 'btn btn-default pull-right submit_dash_config',
                    'reset' => [
                        'title' => $this->trans('Cancel', [], 'Admin.Actions'),
                        'class' => 'btn btn-default cancel_dash_config',
                    ]
                ]
            ],
        ];

        $helper = new HelperForm();
        $helper->show_toolbar = false;
        $helper->table = $this->table;
        $helper->default_form_language = (new Language((int)Configuration::get('PS_LANG_DEFAULT')))->id;
        $helper->allow_employee_form_lang = Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG') ? Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG') : 0;
        $this->fields_form = [];
        $helper->id = (int)Tools::getValue('id_carrier');
        $helper->identifier = $this->identifier;
        $helper->submit_action = 'submitDashConfig';
        $helper->tpl_vars = [
            'fields_value' => $this->getConfigFieldsValues(),
            'languages' => $this->context->controller->getLanguages(),
            'id_language' => $this->context->language->id
        ];

        return $helper->generateForm(array($fields_form));
    }

    public function getConfigFieldsValues()
    {
        return [
            'DASHACTIVITY_CART_ACTIVE' => Tools::getValue('DASHACTIVITY_CART_ACTIVE', Configuration::get('DASHACTIVITY_CART_ACTIVE')),
            'DASHACTIVITY_CART_ABANDONED_MIN' => Tools::getValue('DASHACTIVITY_CART_ABANDONED_MIN', Configuration::get('DASHACTIVITY_CART_ABANDONED_MIN')),
            'DASHACTIVITY_CART_ABANDONED_MAX' => Tools::getValue('DASHACTIVITY_CART_ABANDONED_MAX', Configuration::get('DASHACTIVITY_CART_ABANDONED_MAX')),
            'DASHACTIVITY_VISITOR_ONLINE' => Tools::getValue('DASHACTIVITY_VISITOR_ONLINE', Configuration::get('DASHACTIVITY_VISITOR_ONLINE')),
        ];
    }
}
