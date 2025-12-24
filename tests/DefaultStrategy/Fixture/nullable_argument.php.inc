<?php

declare(strict_types=1);

use DateTimeImmutable;
use DateTimeZone;

class NullableArgument
{
    public function test(): void
    {
        $this->dateAndNull(
            new DateTimeImmutable('2025-12-23'),
            null,
        );
    }

    private function dateAndNull(DateTimeImmutable $dateTimeImmutable, DateTimeZone | null $dateTimeZone): void
    {
        return;
    }
}
?>
-----
<?php

declare(strict_types=1);

use DateTimeImmutable;
use DateTimeZone;

class NullableArgument
{
    public function test(): void
    {
        $this->dateAndNull(
            dateTimeImmutable: new DateTimeImmutable(datetime: '2025-12-23'),
            dateTimeZone: null,
        );
    }

    private function dateAndNull(DateTimeImmutable $dateTimeImmutable, DateTimeZone | null $dateTimeZone): void
    {
        return;
    }
}
?>
