<?php

namespace BackVista\DatabaseDumps\Tests\Unit\Service\Security;

use PHPUnit\Framework\TestCase;
use BackVista\DatabaseDumps\Config\EnvironmentConfig;
use BackVista\DatabaseDumps\Exception\ProductionEnvironmentException;
use BackVista\DatabaseDumps\Service\Security\EnvironmentChecker;
use BackVista\DatabaseDumps\Service\Security\ProductionGuard;

class ProductionGuardTest extends TestCase
{
    public function testEnsureSafeForImportThrowsExceptionInProduction(): void
    {
        $config = new EnvironmentConfig('prod');
        $checker = new EnvironmentChecker($config);
        $guard = new ProductionGuard($checker);

        $this->expectException(ProductionEnvironmentException::class);
        $this->expectExceptionMessage('production/predprod');

        $guard->ensureSafeForImport();
    }

    public function testEnsureSafeForImportDoesNotThrowInDevelopment(): void
    {
        $config = new EnvironmentConfig('dev');
        $checker = new EnvironmentChecker($config);
        $guard = new ProductionGuard($checker);

        // Should not throw
        $guard->ensureSafeForImport();

        $this->assertTrue(true); // Assert passed if no exception
    }

    public function testEnsureSafeForImportDoesNotThrowInTest(): void
    {
        $config = new EnvironmentConfig('test');
        $checker = new EnvironmentChecker($config);
        $guard = new ProductionGuard($checker);

        // Should not throw
        $guard->ensureSafeForImport();

        $this->assertTrue(true); // Assert passed if no exception
    }
}
