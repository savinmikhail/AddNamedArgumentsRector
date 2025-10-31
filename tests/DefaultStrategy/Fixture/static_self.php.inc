<?php

declare(strict_types=1);

final class Article
{
    public const string SLUID_TAG = 'tag';

    private array $newTaxonomyItems = [];
    private array $currentAccessibleTaxonomyItems = [];

    public function setTaxonomyItems(
        array $newTaxonomyItems,
        array $currentAccessibleTaxonomyItems,
    ): void {
        $this->newTaxonomyItems = $newTaxonomyItems;
        $this->currentAccessibleTaxonomyItems = $currentAccessibleTaxonomyItems;
    }
}

final class TestCase
{
    public static function taxonomyItems(): void
    {
        $taxonomyTypeRepository = 'test';

        $article = new Article();
        $article->setTaxonomyItems(
            [
                self::createTaxonomyItemByType(
                    $taxonomyTypeRepository,
                    Article::SLUID_TAG,
                    'new-tag',
                ),
            ],
            [],
        );
    }

    public static function createTaxonomyItemByType(
        string $taxonomyTypeRepository,
        string $taxonomyTypeSlUid,
        string $dutchLabel,
    ): string {
        return $taxonomyTypeRepository . $taxonomyTypeSlUid . $dutchLabel;
    }
}
-----
<?php

declare(strict_types=1);

final class Article
{
    public const string SLUID_TAG = 'tag';

    private array $newTaxonomyItems = [];
    private array $currentAccessibleTaxonomyItems = [];

    public function setTaxonomyItems(
        array $newTaxonomyItems,
        array $currentAccessibleTaxonomyItems,
    ): void {
        $this->newTaxonomyItems = $newTaxonomyItems;
        $this->currentAccessibleTaxonomyItems = $currentAccessibleTaxonomyItems;
    }
}

final class TestCase
{
    public static function taxonomyItems(): void
    {
        $taxonomyTypeRepository = 'test';

        $article = new Article();
        $article->setTaxonomyItems(
            newTaxonomyItems: [
                self::createTaxonomyItemByType(
                    taxonomyTypeRepository: $taxonomyTypeRepository,
                    taxonomyTypeSlUid: Article::SLUID_TAG,
                    dutchLabel: 'new-tag',
                ),
            ],
            currentAccessibleTaxonomyItems: [],
        );
    }

    public static function createTaxonomyItemByType(
        string $taxonomyTypeRepository,
        string $taxonomyTypeSlUid,
        string $dutchLabel,
    ): string {
        return $taxonomyTypeRepository . $taxonomyTypeSlUid . $dutchLabel;
    }
}
