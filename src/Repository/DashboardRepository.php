<?php

namespace PrestaShop\Module\Dashboard\Repository;

use AdminStatsController;
use Doctrine\DBAL\Connection;
use PrestaShop\PrestaShop\Adapter\Configuration;
use Module;
use PrestaShop\PrestaShop\Core\Addon\Module\ModuleManager;
use Product;
use Shop;

class DashboardRepository
{
    /** @var Connection */
    private $connection;

    /** @var ModuleManager */
    private $moduleManager;

    /** @var string */
    private $databasePrefix;

    /** @var string */
    private $maintenanceIps;

    /** @var bool */
    private $isCustomerPageViewsStored;

    /** @var int */
    private $confDashActivityCartAbandonedMax;

    /** @var int */
    private $confDashActivityCartAbandonedMin;

    /** @var int */
    private $confDashActivityCartActive;

    public function __construct(Connection $connection, string $databasePrefix, Configuration $configuration, ModuleManager $moduleManager)
    {
        $this->connection = $connection;
        $this->moduleManager = $moduleManager;
        $this->databasePrefix = $databasePrefix;
        $this->maintenanceIps = implode(
            ',',
            array_map(
                'ip2long',
                array_map(
                    'trim',
                    explode(
                        ',',
                        $configuration->get('PS_MAINTENANCE_IP')
                    )
                )
            )
        );

        $this->isCustomerPageViewsStored = (bool) $configuration->get('PS_STATSDATA_CUSTOMER_PAGESVIEWS');
        $this->confDashActivityCartAbandonedMax = (int) $configuration->get('DASHACTIVITY_CART_ABANDONED_MAX');
        $this->confDashActivityCartAbandonedMin = (int) $configuration->get('DASHACTIVITY_CART_ABANDONED_MIN');
        $this->confDashActivityCartActive = (int) $configuration->get('DASHACTIVITY_CART_ACTIVE');
    }

    /**
     * @param string $date_from
     * @param string $date_to
     * @return array{visits: integer, unique_visitors: integer}
     */
    public function getUniqueVisitors(string $date_from, string $date_to): array
    {
        $query = sprintf(
            'SELECT COUNT(*) as visits, COUNT(DISTINCT `id_guest`) as unique_visitors'
            . ' FROM `%sconnections`'
            . ' WHERE `date_add` BETWEEN "%s" AND "%s"'
            . ' %s'
            ,
            $this->databasePrefix,
            pSQL($date_from),
            pSQL($date_to),
            Shop::addSqlRestriction(false)
        );
        $statement = $this->connection->prepare($query);
        $result = $statement->executeQuery();

        return $result->fetchAssociative();
    }

    public function getOnlineVisitors(): int
    {
        $inMaintenanceIps = preg_replace('/[^,0-9]/', '', $this->maintenanceIps);
        if ($this->isCustomerPageViewsStored) {
            $query = sprintf(
                'SELECT c.id_guest, c.ip_address, c.date_add, c.http_referer, pt.name as page'
					. ' FROM `%sconnections` c'
					. ' LEFT JOIN `%sconnections_page` cp ON c.id_connections = cp.id_connections'
					. ' LEFT JOIN `%spage` p ON p.id_page = cp.id_page'
					. ' LEFT JOIN `%spage_type` pt ON p.id_page_type = pt.id_page_type'
					. ' INNER JOIN `%sguest` g ON c.id_guest = g.id_guest'
					. ' WHERE (g.id_customer IS NULL OR g.id_customer = 0)'
					. ' AND cp.`time_end` IS NULL'
					. ' AND TIME_TO_SEC(TIMEDIFF(\'%s\', cp.`time_start`)) < 900'
                    . ($this->maintenanceIps ? ' AND c.ip_address NOT IN (%s)' : '%s')
					. ' %s'
					. ' GROUP BY c.id_connections'
					. ' ORDER BY c.date_add DESC',
                $this->databasePrefix,
                $this->databasePrefix,
                $this->databasePrefix,
                $this->databasePrefix,
                $this->databasePrefix,
                pSQL(date('Y-m-d H:i:00', time())),
                $this->maintenanceIps ? $inMaintenanceIps : '',
                Shop::addSqlRestriction(false, 'c')
            );
        } else {
            $query = sprintf(
                'SELECT c.id_guest, c.ip_address, c.date_add, c.http_referer, "-" as page'
                . ' FROM `%sconnections` c'
                . ' INNER JOIN `%sguest` g ON c.id_guest = g.id_guest'
                . ' WHERE (g.id_customer IS NULL OR g.id_customer = 0)'
                . ' AND TIME_TO_SEC(TIMEDIFF(\'%s\', c.`date_add`)) < 900'
                . ($this->maintenanceIps ? ' AND c.ip_address NOT IN (%s)' : '%s')
                . ' %s'
                . ' ORDER BY c.date_add DESC',
                $this->databasePrefix,
                $this->databasePrefix,
                pSQL(date('Y-m-d H:i:00', time())),
                $this->maintenanceIps ? $inMaintenanceIps : '',
                Shop::addSqlRestriction(false, 'c')
            );
        }

        $statement = $this->connection->prepare($query);

        return $statement->executeStatement();
    }

    public function getPendingOrders(): int
    {
        $query = sprintf(
            'SELECT COUNT(*)'
            . ' FROM `%sorders` o'
            . ' LEFT JOIN `%sorder_state` os ON (o.current_state = os.id_order_state)'
            . ' WHERE os.paid = 1 AND os.shipped = 0 %s',
            $this->databasePrefix,
            $this->databasePrefix,
            Shop::addSqlRestriction(Shop::SHARE_ORDER)
        );

        $statement = $this->connection->prepare($query);
        $result = $statement->executeQuery();

        return (int) $result->fetchOne();
    }

    public function getAbandonedCarts(): int
    {
        $query = sprintf(
            'SELECT COUNT(*)'
            . ' FROM `%scart`'
            . ' WHERE `date_upd` BETWEEN "%s" AND "%s"'
            . ' AND id_cart NOT IN (SELECT id_cart FROM `%sorders`)'
            . ' %s',
            $this->databasePrefix,
            pSQL(date('Y-m-d H:i:s', strtotime('-'.$this->confDashActivityCartAbandonedMax.' MIN'))),
            pSQL(date('Y-m-d H:i:s', strtotime('-'.$this->confDashActivityCartAbandonedMin.' MIN'))),
            $this->databasePrefix,
            Shop::addSqlRestriction(Shop::SHARE_ORDER)
        );

        $statement = $this->connection->prepare($query);
        $result = $statement->executeQuery();

        return (int) $result->fetchOne();
    }

    public function getReturnExchanges(string $date_from, string $date_to): int
    {
        $query = sprintf(
            'SELECT COUNT(*)'
            . ' FROM `%sorders` o'
            . ' LEFT JOIN `%sorder_return` ore ON (o.id_order = ore.id_order)'
            . ' WHERE ore.`date_add` BETWEEN "%s" AND "%s"'
            . ' %s',
            $this->databasePrefix,
            $this->databasePrefix,
            pSQL($date_from),
            pSQL($date_to),
            Shop::addSqlRestriction(Shop::SHARE_ORDER, 'o')
        );

        $statement = $this->connection->prepare($query);
        $result = $statement->executeQuery();

        return (int) $result->fetchOne();
    }

    public function getProductsOutOfStock(): int
    {
        $query = sprintf(
            'SELECT SUM(IF(IFNULL(stock.quantity, 0) > 0, 0, 1))'
            . ' FROM `%sproduct` p'
            . ' %s'
            . ' LEFT JOIN `%sproduct_attribute` pa ON p.id_product = pa.id_product'
            . ' %s'
            . ' WHERE p.active = 1',
            $this->databasePrefix,
            Shop::addSqlAssociation('product', 'p'),
            $this->databasePrefix,
            Product::sqlStock('p', 'pa')
        );

        $statement = $this->connection->prepare($query);
        $result = $statement->executeQuery();

        return (int) $result->fetchOne();
    }

    public function getPendingMessages(): int
    {
        return (int) AdminStatsController::getPendingMessages();
    }

    public function getActiveCarts(): int
    {
        $query = sprintf(
            'SELECT COUNT(*)'
            . ' FROM `%scart`'
            . ' WHERE date_upd > "%s"'
			. ' %s',
            $this->databasePrefix,
            pSQL(date('Y-m-d H:i:s', strtotime('-'.$this->confDashActivityCartActive.' MIN'))),
            Shop::addSqlRestriction(Shop::SHARE_ORDER)
        );

        $statement = $this->connection->prepare($query);
        $result = $statement->executeQuery();

        return (int) $result->fetchOne();
    }

    public function getNewCustomers(string $date_from, string $date_to): int
    {
        $query = sprintf(
            'SELECT COUNT(*)'
            . ' FROM `%scustomer`'
            . ' WHERE `date_add` BETWEEN "%s" AND "%s"'
            . ' %s',
            $this->databasePrefix,
            pSQL($date_from),
            pSQL($date_to),
            Shop::addSqlRestriction(Shop::SHARE_ORDER)
        );

        $statement = $this->connection->prepare($query);
        $result = $statement->executeQuery();

        return (int) $result->fetchOne();
    }

    public function getNewsletterSubscribers(string $date_from = null, string $date_to = null): int
    {
        $query = sprintf(
            'SELECT COUNT(*)'
            . ' FROM `%scustomer`'
            . ' WHERE newsletter = 1'
            . ' %s',
            $this->databasePrefix,
            Shop::addSqlRestriction(Shop::SHARE_ORDER)
        );
        if ($date_to && $date_from) {
            $query = sprintf(
                $query  . ' AND `newsletter_date_add` BETWEEN "%s" AND "%s"',
                pSQL($date_from),
                pSQL($date_to),
            );
        }

        $statement = $this->connection->prepare($query);
        $result = $statement->executeQuery();

        return (int) $result->fetchOne();
    }

    public function getProductReviews(string $date_from, string $date_to): int
    {
        if (!$this->moduleManager->isInstalled('$product_reviews')) {
            return 0;
        }
        $query = sprintf(
            'SELECT COUNT(*)'
            . ' FROM `%sproduct_comment` pc'
            . ' LEFT JOIN `%sproduct` p ON (pc.id_product = p.id_product)'
            . ' %s'
            . ' WHERE pc.deleted = 0'
            . ' AND pc.`date_add` BETWEEN "%s" AND "%s"'
            . ' %s',
            $this->databasePrefix,
            $this->databasePrefix,
            Shop::addSqlAssociation('product', 'p'),
            pSQL($date_from),
            pSQL($date_to),
            Shop::addSqlRestriction(Shop::SHARE_ORDER)
        );

        $statement = $this->connection->prepare($query);
        $result = $statement->executeQuery();

        return (int) $result->fetchOne();
    }

    /**
     * @param string $date_from
     * @param string $date_to
     * @param int $limit
     * @return array<int, array<string, string>>
     */
    public function getReferrers(string $date_from, string $date_to, int $limit): array
    {
        $query = sprintf(
            'SELECT http_referer'
            . ' FROM `%sconnections`'
            . ' WHERE `date_add` BETWEEN "%s" AND "%s"'
            . ' %s'
            . ' LIMIT %d',
            $this->databasePrefix,
            pSQL($date_from),
            pSQL($date_to),
            Shop::addSqlRestriction(false),
            $limit
        );
        $statement = $this->connection->prepare($query);
        $result = $statement->executeQuery();

        return $result->fetchAllAssociative();
    }
}
