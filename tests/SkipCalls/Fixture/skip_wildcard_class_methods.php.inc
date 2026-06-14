<?php

declare(strict_types=1);

final class FixtureWildcardQueryBuilder
{
    public function where(mixed ...$predicates): self
    {
        return $this;
    }

    public function setParameter(string $key, mixed $value): self
    {
        return $this;
    }
}

$qb = new FixtureWildcardQueryBuilder();
$qb->where('category.id = :id');
$qb->setParameter('id', 1);

?>
-----
<?php

declare(strict_types=1);

final class FixtureWildcardQueryBuilder
{
    public function where(mixed ...$predicates): self
    {
        return $this;
    }

    public function setParameter(string $key, mixed $value): self
    {
        return $this;
    }
}

$qb = new FixtureWildcardQueryBuilder();
$qb->where('category.id = :id');
$qb->setParameter('id', 1);

?>
