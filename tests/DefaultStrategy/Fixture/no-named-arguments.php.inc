<?php

use PHPUnit\Framework\TestCase;

class AttributedContextDenormalizerTest extends TestCase
{
    public function test(): void
    {
        $attributedContext = $this->createMock(DateTimeImmutable::class);
        $attributedContext->method('getAttribute');
    }
}

?>
-----
<?php

use PHPUnit\Framework\TestCase;

class AttributedContextDenormalizerTest extends TestCase
{
    public function test(): void
    {
        $attributedContext = $this->createMock(originalClassName: DateTimeImmutable::class);
        $attributedContext->method('getAttribute');
    }
}

?>
