<?php
/**
 * Copyright since 2007 PrestaShop SA and Contributors
 * PrestaShop is an International Registered Trademark & Property of PrestaShop SA
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License 3.0 (AFL-3.0)
 * that is bundled with this package in the file LICENSE.md.
 * It is also available through the world-wide-web at this URL:
 * https://opensource.org/licenses/AFL-3.0
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@prestashop.com so we can send you a copy immediately.
 *
 * @author    PrestaShop SA <contact@prestashop.com>
 * @copyright Since 2007 PrestaShop SA and Contributors
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License 3.0 (AFL-3.0)
 */

namespace PrestaShop\Module\Dashboard\Hook;

use AdminStatsController;
use Configuration;
use Module;
use Product;
use Shop;
use Tools;

class Activity extends AbstractHook
{
    const AVAILABLE_HOOKS = [
        'dashboardZoneOne',
        'dashboardData',
        'actionAdminControllerSetMedia',
    ];

    protected static $colors = ['#1F77B4', '#FF7F0E', '#2CA02C'];

    /**
     * @param array $params
     * @return string
     */
    public function dashboardZoneOne(array $params): string
    {
        $smartyData = array_merge($this->module->getConfigFieldsValues(), [
            'dashactivity_config_form' => $this->module->renderConfigForm(),
            'date_subtitle' => $this->module->getTranslator()->trans('(from %s to %s)', [], 'Modules.Dashactivity.Admin'),
            'date_format' => $this->context->language->date_format_lite,
            'link' => $this->context->link
        ]);

        $this->context->smarty->assign($smartyData);

        return $this->module->render('dashboard_zone_one.tpl');
    }

    public function dashboardData(array $params): array
    {
        if (Tools::strlen($params['date_from']) == 10) {
            $params['date_from'] .= ' 00:00:00';
        }
        if (Tools::strlen($params['date_to']) == 10) {
            $params['date_to'] .= ' 23:59:59';
        }

        if ($this->isDemoMode) {
            return $this->getDemoData($params);
        }

        // Visitors
        $unique_visitors = $this->module->get('dashboard_repository')->getUniqueVisitors($params['date_from'], $params['date_to']);
        $online_visitors = $this->module->get('dashboard_repository')->getOnlineVisitors();
        // Orders
        $pending_orders = $this->module->get('dashboard_repository')->getPendingOrders();
        $return_exchanges = $this->module->get('dashboard_repository')->getReturnExchanges($params['date_from'], $params['date_to']);
        // Carts
        $abandoned_cart = $this->module->get('dashboard_repository')->getAbandonedCarts();
        $active_cart = $this->module->get('dashboard_repository')->getActiveCarts();
        // Products
        $products_out_of_stock = $this->module->get('dashboard_repository')->getProductsOutOfStock();
        $product_reviews = $this->module->get('dashboard_repository')->getProductReviews($params['date_from'], $params['date_to']);
        // Messages
        $new_messages = $this->module->get('dashboard_repository')->getPendingMessages();
        // Customers
        $new_customers = $this->module->get('dashboard_repository')->getNewCustomers($params['date_from'], $params['date_to']);
        $new_subscribers = $this->module->get('dashboard_repository')->getNewsletterSubscribers($params['date_from'], $params['date_to']);
        $total_subscribers = $this->module->get('dashboard_repository')->getNewsletterSubscribers();

        return array(
            'data_value' => [
                'pending_orders' => $pending_orders,
                'return_exchanges' => $return_exchanges,
                'abandoned_cart' => $abandoned_cart,
                'products_out_of_stock' => $products_out_of_stock,
                'new_messages' => $new_messages,
                'product_reviews' => $product_reviews,
                'new_customers' => $new_customers,
                'online_visitor' => $online_visitors,
                'active_shopping_cart' => $active_cart,
                'new_registrations' => $new_subscribers,
                'total_subscribers' => $total_subscribers,
                'visits' => (int) $unique_visitors['visits'],
                'unique_visitors' => (int) $unique_visitors['unique_visitors'],
            ],
            'data_trends' => [
                'orders_trends' => [
                    'way' => 'down',
                    'value' => 0.42
                ],
            ],
            'data_list_small' => [
                'dash_traffic_source' => $this->getTrafficSources($this->getReferer($params['date_from'], $params['date_to'])),
            ],
            'data_chart' => [
                'dash_trends_chart1' => $this->getChartTrafficSource($this->getReferer($params['date_from'], $params['date_to'])),
            ],
        );
    }

    public function actionAdminControllerSetMedia(array $params)
    {
        if (get_class($this->context->controller) !== 'AdminDashboardController') {
            return;
        }
        $this->context->controller->addJs($this->module->getPathUri() . 'views/js/' . $this->module->name . '.js');
    }

    private function getDemoData(array $params): array
    {
        $days = (strtotime($params['date_to']) - strtotime($params['date_from'])) / 3600 / 24;
        $online_visitor = rand(10, 50);
        $visits = rand(200, 2000) * $days;

        return [
            'data_value' => [
                'pending_orders' => round(rand(0, 5)),
                'return_exchanges' => round(rand(0, 5)),
                'abandoned_cart' => round(rand(5, 50)),
                'products_out_of_stock' => round(rand(1, 10)),
                'new_messages' => round(rand(1, 10) * $days),
                'product_reviews' => round(rand(5, 50) * $days),
                'new_customers' => round(rand(1, 5) * $days),
                'online_visitor' => round($online_visitor),
                'active_shopping_cart' => round($online_visitor / 10),
                'new_registrations' => round(rand(1, 5) * $days),
                'total_subscribers' => round(rand(200, 2000)),
                'visits' => round($visits),
                'unique_visitors' => round($visits * 0.6),
            ],
            'data_trends' => [
                'orders_trends' => [
                    'way' => 'down',
                    'value' => 0.42
                ],
            ],
            'data_list_small' => [
                'dash_traffic_source' => [
                    '<i class="icon-circle" style="color:'.self::$colors[0].'"></i> prestashop.com' => round($visits / 2),
                    '<i class="icon-circle" style="color:'.self::$colors[1].'"></i> google.com' => round($visits / 3),
                    '<i class="icon-circle" style="color:'.self::$colors[2].'"></i> Direct Traffic' => round($visits / 4)
                ]
            ],
            'data_chart' => [
                'dash_trends_chart1' => [
                    'chart_type' => 'pie_chart_trends',
                    'data' => [
                        [
                            'key' => 'prestashop.com',
                            'y' => round($visits / 2),
                            'color' => self::$colors[0]
                        ],
                        [
                            'key' => 'google.com',
                            'y' => round($visits / 3),
                            'color' => self::$colors[1]
                        ],
                        [
                            'key' => 'Direct Traffic',
                            'y' => round($visits / 4),
                            'color' => self::$colors[2]
                        ]
                    ]
                ]
            ]
        ];
    }

    protected function getTrafficSources(array $referrers): array
    {
        $i = 0;

        $traffic_sources = [];
        foreach ($referrers as $referrer_name => $n) {
            $traffic_sources['<i class="icon-circle" style="color:'.self::$colors[$i++].'"></i> '.$referrer_name] = $n;
        }

        return $traffic_sources;
    }

    protected function getChartTrafficSource(array $referrers): array
    {
        $i = 0;

        $return = [
            'chart_type' => 'pie_chart_trends',
            'data' => []
        ];
        foreach ($referrers as $referrer_name => $n) {
            $return['data'][] = [
                'key' => $referrer_name,
                'y' => $n,
                'color' => self::$colors[$i++]
            ];
        }

        return $return;
    }

    protected function getReferer(string $date_from, string $date_to, int $limit = 3)
    {
        $direct_link = $this->module->getTranslator()->trans('Direct link', [], 'Admin.Orderscustomers.Notification');
        $websites = [$direct_link => 0];

        /** @var array<int, array<string, string>> $result */
        $result = $this->module->get('dashboard_repository')->getReferrers($date_from, $date_to, $limit);
        foreach ($result as $row) {
            if (empty($row['http_referer'])) {
                ++$websites[$direct_link];
            } else {
                $website = preg_replace('/^www./', '', parse_url($row['http_referer'], PHP_URL_HOST));
                if (!isset($websites[$website])) {
                    $websites[$website] = 0;
                }
                $websites[$website]++;
            }
        }
        arsort($websites);

        return $websites;
    }

}
