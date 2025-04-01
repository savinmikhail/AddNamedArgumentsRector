<?php

use PHPUnit\Framework\TestCase;

class AttributedContextDenormalizerTest extends TestCase
{
    public function test(): void
    {
        // this method inherits @no-named-arguments from parent class
        $attributedContext = $this->createMock(DateTimeImmutable::class);
        // this method is called directly on class with @no-named-argument
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
        // this method inherits @no-named-arguments from parent class
        $attributedContext = $this->createMock(DateTimeImmutable::class);
        // this method is called directly on class with @no-named-argument
        $attributedContext->method('getAttribute');
    }
}

?>
