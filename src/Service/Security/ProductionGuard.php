<?php

namespace SmartCrm\DatabaseDumps\Service\Security;

use SmartCrm\DatabaseDumps\Exception\ProductionEnvironmentException;

/**
 * Защита от опасных операций в production
 */
class ProductionGuard
{
    private EnvironmentChecker $environmentChecker;

    public function __construct(EnvironmentChecker $environmentChecker)
    {
        $this->environmentChecker = $environmentChecker;
    }

    /**
     * Проверить безопасность импорта и выбросить исключение если production
     *
     * @throws ProductionEnvironmentException
     */
    public function ensureSafeForImport(): void
    {
        if ($this->environmentChecker->isProduction()) {
            throw ProductionEnvironmentException::importBlocked(
                $this->environmentChecker->getCurrentEnvironment()
            );
        }
    }
}
