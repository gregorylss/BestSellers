<?php

namespace BestSellers\Api\Resource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\GetCollection;
use BestSellers\Api\Provider\BestSellersProvider;
use Symfony\Component\Serializer\Annotation\Groups;

#[ApiResource(
    operations: [
        new GetCollection(
            uriTemplate: '/best-sellers',
            openapiContext: [
                'parameters' => [
                    [
                        'name' => 'product_ref',
                        'in' => 'query',
                        'required' => false,
                        'schema' => [
                            'type' => 'string'
                        ],
                        'description' => 'Filtrer par référence produit pour obtenir les produits achetés ensemble'
                    ],
                    [
                        'name' => 'order',
                        'in' => 'query',
                        'required' => false,
                        'schema' => [
                            'type' => 'string',
                            'enum' => ['sold_count', 'sold_count_reverse', 'sold_amount', 'sold_amount_reverse', 'sale_ratio', 'sale_ratio_reverse']
                        ],
                        'description' => 'Tri des résultats'
                    ],
                    [
                        'name' => 'only_sold_products',
                        'in' => 'query',
                        'required' => false,
                        'schema' => [
                            'type' => 'boolean'
                        ],
                        'description' => 'N\'afficher que les produits ayant des ventes'
                    ],
                    [
                        'name' => 'limit',
                        'in' => 'query',
                        'required' => false,
                        'schema' => [
                            'type' => 'integer'
                        ],
                        'description' => 'Nombre maximum de résultats'
                    ],
                    [
                        'name' => 'itemsPerPage',
                        'in' => 'query',
                        'required' => false,
                        'schema' => [
                            'type' => 'integer'
                        ],
                        'description' => 'Alias pour limit - Nombre maximum de résultats'
                    ],
                    [
                        'name' => 'page',
                        'in' => 'query',
                        'required' => false,
                        'schema' => [
                            'type' => 'integer',
                            'default' => 1
                        ],
                        'description' => 'Numéro de page pour la pagination'
                    ],
                ]
            ],
            paginationEnabled: true,
            paginationItemsPerPage: 10,
            paginationClientItemsPerPage: true,
            provider: BestSellersProvider::class
        ),
    ],
    normalizationContext: ['groups' => ['best_sellers:read']]
)]
class BestSeller
{
    #[Groups(['best_sellers:read'])]
    private ?int $id = null;

    #[Groups(['best_sellers:read'])]
    private ?string $reference = null;

    #[Groups(['best_sellers:read'])]
    private ?string $title = null;

    #[Groups(['best_sellers:read'])]
    private ?float $soldQuantity = null;

    #[Groups(['best_sellers:read'])]
    private ?float $soldAmount = null;

    #[Groups(['best_sellers:read'])]
    private ?float $saleRatio = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(?int $id): self
    {
        $this->id = $id;
        return $this;
    }

    public function getReference(): ?string
    {
        return $this->reference;
    }

    public function setReference(?string $reference): self
    {
        $this->reference = $reference;
        return $this;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(?string $title): self
    {
        $this->title = $title;
        return $this;
    }

    public function getSoldQuantity(): ?float
    {
        return $this->soldQuantity;
    }

    public function setSoldQuantity(?float $soldQuantity): self
    {
        $this->soldQuantity = $soldQuantity;
        return $this;
    }

    public function getSoldAmount(): ?float
    {
        return $this->soldAmount;
    }

    public function setSoldAmount(?float $soldAmount): self
    {
        $this->soldAmount = $soldAmount;
        return $this;
    }

    public function getSaleRatio(): ?float
    {
        return $this->saleRatio;
    }

    public function setSaleRatio(?float $saleRatio): self
    {
        $this->saleRatio = $saleRatio;
        return $this;
    }
}
