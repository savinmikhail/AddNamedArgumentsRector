<?php

declare(strict_types=1);

final class FixtureSkippedQueryBuilder
{
    public function addSelect(mixed ...$select): self
    {
        return $this;
    }

    public function setParameter(string $key, mixed $value): self
    {
        return $this;
    }
}

$qb = new FixtureSkippedQueryBuilder();
$qb->addSelect('item');
$qb->setParameter('id', 1);

?>
-----
<?php

declare(strict_types=1);

final class FixtureSkippedQueryBuilder
{
    public function addSelect(mixed ...$select): self
    {
        return $this;
    }

    public function setParameter(string $key, mixed $value): self
    {
        return $this;
    }
}

$qb = new FixtureSkippedQueryBuilder();
$qb->addSelect('item');
$qb->setParameter(key: 'id', value: 1);

?>
