<?php

declare(strict_types=1);

namespace SavinMikhail\Tests\AddNamedArgumentsRector\PhpyhStrategy;

use Iterator;
use PHPUnit\Framework\Attributes\DataProvider;
use Rector\Testing\PHPUnit\AbstractRectorTestCase;
use SavinMikhail\AddNamedArgumentsRector\AddNamedArgumentsRector;

final class AddNamedArgumentsRectorTest extends AbstractRectorTestCase
{
    #[DataProvider('provideCases')]
    public function test(string $filePath): void
    {
        $this->doTestFile($filePath);
    }

    /**
     * @return Iterator<array<int, string>>
     */
    public static function provideCases(): Iterator
    {
        return self::yieldFilesFromDirectory(__DIR__ . '/Fixture');
    }

    public function provideConfigFilePath(): string
    {
        return __DIR__ . '/config/rector.php';
    }

    protected function getRectorClass(): string
    {
        return AddNamedArgumentsRector::class;
    }
}
