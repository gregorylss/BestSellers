<?php

namespace BestSellers\Api\Provider;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use BestSellers\BestSellers;
use BestSellers\EventListeners\BestSellersEvent;
use BestSellers\Api\Resource\BestSeller;
use Propel\Runtime\ActiveQuery\Criteria;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Thelia\Model\Map\OrderProductTableMap;
use Thelia\Model\Map\ProductTableMap;
use Thelia\Model\OrderProductQuery;
use Thelia\Model\ProductQuery;

/**
 * Provider pour la ressource API "best_sellers".
 *
 * @implements ProviderInterface<BestSeller>
 */
class BestSellersProvider implements ProviderInterface
{
    private EventDispatcherInterface $dispatcher;

    public function __construct(EventDispatcherInterface $dispatcher)
    {
        $this->dispatcher = $dispatcher;
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): array|object|null
    {
        // Vérifie si on demande les produits achetés ensemble
        if (!empty($context['filters']['product_ref'])) {
            return $this->providePurchasedWith($context['filters']['product_ref'], $context);
        }

        // Sinon fournit les meilleurs vendeurs (code existant)
        $query = $this->buildModelCriteria();

        $limit = BestSellers::getConfigValue('limit') ?? 10;
        $offset = BestSellers::getConfigValue('offset') ?? 0;

        $query
            ->limit($limit)
            ->offset($offset);

        $products = $query->find();
        $bestSellers = [];

        foreach ($products as $product) {
            $bestSeller = new BestSeller();
            $bestSeller->setId($product->getId());
            $bestSeller->setReference($product->getRef());
            $bestSeller->setTitle($product->getTitle());

            $soldQuantity = $product->getVirtualColumn('sold_quantity') ?? 0;
            $soldAmount = $product->getVirtualColumn('sold_amount') ?? 0;
            $saleRatio = $product->getVirtualColumn('sale_ratio') ?? 0;

            $bestSeller->setSoldQuantity($soldQuantity);
            $bestSeller->setSoldAmount($soldAmount);
            $bestSeller->setSaleRatio($saleRatio);

            $bestSellers[] = $bestSeller;
        }

        return $bestSellers;
    }

    public function buildModelCriteria()
    {
        $query = ProductQuery::create();

        $startDate = new \DateTime();
        $endDate = new \DateTime();

        $dateType = BestSellers::getConfigValue('date_type');
        $startDateString = BestSellers::getConfigValue('start_date');
        $endDateString = BestSellers::getConfigValue('end_date');

        switch ($dateType) {
            case BestSellers::FIXED_DATE:
                $dates = $this->setFixedDate($startDateString, $endDateString);
                $startDate = $dates['start_date'];
                $endDate = $dates['end_date'];
                break;
            case BestSellers::DATE_RANGE:
                $startDate = $this->setDateRange(BestSellers::getConfigValue('date_range'));
                break;
        }

        $event = new BestSellersEvent($startDate, $endDate);

        $this->dispatcher->dispatch(
            $event,
            BestSellers::GET_BEST_SELLING_PRODUCTS
        );

        $caseClause = $caseSalesClause = '';

        $productData = $event->getBestSellingProductsData();

        array_walk($productData, function ($item) use (&$caseClause, &$caseSalesClause): void {
            $caseClause .= sprintf('WHEN %d THEN %F ', $item['product_id'], $item['total_quantity']);
            $caseSalesClause .= sprintf('WHEN %d THEN %F ', $item['product_id'], $item['total_sales']);
        });

        if (!empty($caseClause)) {
            $query
                ->withColumn(
                    'CASE ' . ProductTableMap::ID . ' ' . $caseClause . ' ELSE 0 END',
                    'sold_quantity'
                )
                ->withColumn(
                    'CASE ' . ProductTableMap::ID . ' ' . $caseSalesClause . ' ELSE 0 END',
                    'sold_amount'
                );

            if ($this->getOnlySoldProducts()) {
                $query->where('(CASE ' . ProductTableMap::ID . ' ' . $caseClause . ' ELSE 0 END) > 0');
            }
        } else {
            $query
                ->withColumn('(0)', 'sold_quantity')
                ->withColumn('(0)', 'sold_amount');
        }

        if ($event->getTotalSales() !== 0) {
            $query->withColumn(
                '(select 100 * sold_amount / ' . $event->getTotalSales() . ')',
                'sale_ratio'
            );
        } else {
            $query->withColumn('(0)', 'sale_ratio');
        }

        $orders = $this->getOrder();

        $customOrder = BestSellers::getConfigValue('order_by');

        if ($customOrder) {
            if ($customOrder === BestSellers::ORDER_BY_SALES_REVENUE) {
                $query->orderBy('sale_ratio', Criteria::DESC);
            } elseif ($customOrder === BestSellers::ORDER_BY_NUMBER_OF_SALES) {
                $query->orderBy('sold_quantity', Criteria::DESC);
            }

            return $query;
        }

        foreach ($orders as $order) {
            switch ($order) {
                case 'sold_count':
                    $query->orderBy('sold_quantity', Criteria::ASC);
                    break;
                case 'sold_count_reverse':
                    $query->orderBy('sold_quantity', Criteria::DESC);
                    break;
                case 'sold_amount':
                    $query->orderBy('sold_amount', Criteria::ASC);
                    break;
                case 'sold_amount_reverse':
                    $query->orderBy('sold_amount', Criteria::DESC);
                    break;
                case 'sale_ratio':
                    $query->orderBy('sale_ratio', Criteria::ASC);
                    break;
                case 'sale_ratio_reverse':
                    $query->orderBy('sale_ratio', Criteria::DESC);
                    break;
            }
        }

        return $query;
    }


    /**
     * Fournit les produits achetés avec un produit spécifié
     */
    private function providePurchasedWith(string $productRef, array $context = []): array
    {
        $query = $this->buildPurchasedWithCriteria($productRef, $context);

        $limit = $context['filters']['itemsPerPage'] ?? $context['filters']['limit'] ?? BestSellers::getConfigValue('limit') ?? 10;
        $offset = $context['filters']['offset'] ?? BestSellers::getConfigValue('offset') ?? 0;

        $query
            ->limit($limit)
            ->offset($offset);

        $orderProducts = $query->find();
        $purchasedWith = [];

        foreach ($orderProducts as $orderProduct) {
            // Récupérer le produit complet pour obtenir toutes les infos
            $product = ProductQuery::create()
                ->filterByRef($orderProduct->getProductRef())
                ->findOne();

            if ($product) {
                $bestSeller = new BestSeller();
                $bestSeller->setId($product->getId());
                $bestSeller->setReference($product->getRef());
                $bestSeller->setTitle($product->getTitle());
                $bestSeller->setSoldQuantity($orderProduct->getVirtualColumn('sold_count'));
                $bestSeller->setSoldAmount(0); // Non applicable dans ce contexte
                $bestSeller->setSaleRatio(0); // Non applicable dans ce contexte

                $purchasedWith[] = $bestSeller;
            }
        }

        return $purchasedWith;
    }

    /**
     * Construit la requête pour récupérer les produits achetés ensemble
     */
    private function buildPurchasedWithCriteria(string $productRef, array $context = [])
    {
        $query = OrderProductQuery::create()
            ->withColumn('count(' . OrderProductTableMap::COL_PRODUCT_REF . ')', 'sold_count')
            ->filterByProductRef($productRef, Criteria::NOT_EQUAL)
            // Trouver les produits commandés avec notre produit
            ->where(OrderProductTableMap::COL_ORDER_ID . ' in (
                select ' . OrderProductTableMap::COL_ORDER_ID . '
                from ' . OrderProductTableMap::TABLE_NAME . '
                where ' . OrderProductTableMap::COL_PRODUCT_REF . ' = ?)', $productRef)
            // Qui existent toujours et sont visibles
            ->where(
                OrderProductTableMap::COL_PRODUCT_REF . ' in (
                select ' . ProductTableMap::COL_REF . '
                from ' . ProductTableMap::TABLE_NAME . '
                where ' . ProductTableMap::COL_VISIBLE . ' = 1)'
            )
            ->groupByProductRef();

        // Ordonnancement
        $order = $context['filters']['order'] ?? 'sold_count_reverse';

        if ($order === 'sold_count') {
            $query->orderBy('sold_count', Criteria::ASC);
        } else {
            $query->orderBy('sold_count', Criteria::DESC);
        }

        return $query;
    }


    private function getOnlySoldProducts()
    {
        return BestSellers::getConfigValue('only_sold_products') === true;
    }

    private function getOrder()
    {
        return BestSellers::getConfigValue('order') ?? [];
    }

    private function setFixedDate($startDateString, $endDateString)
    {
        $startDate = new \DateTime($startDateString);
        $startDate->setTime(0, 0, 0);
        $endDate = new \DateTime($endDateString);
        $endDate->setTime(23, 59, 59);

        return ['start_date' => $startDate, 'end_date' => $endDate];
    }


    private function setDateRange($dateRange)
    {
        switch ($dateRange) {
            case BestSellers::LAST_15_DAYS:
                return new \DateTime('-15 days');
            case BestSellers::LAST_30_DAYS:
                return new \DateTime('-30 days');
            case BestSellers::LAST_6_MONTHS:
                return new \DateTime('-6 months');
            case BestSellers::LAST_3_MONTHS:
                return new \DateTime('-3 months');
            case BestSellers::THIS_YEAR:
                return new \DateTime('first day of January');
            case BestSellers::LAST_YEAR:
                return new \DateTime('first day of January last year');
            default:
                return new \DateTime(); // fallback
        }
    }
}
