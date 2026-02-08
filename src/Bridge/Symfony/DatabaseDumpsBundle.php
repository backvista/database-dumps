<?php

namespace BackVista\DatabaseDumps\Bridge\Symfony;

use BackVista\DatabaseDumps\Bridge\Symfony\DependencyInjection\DatabaseDumpsExtension;
use Symfony\Component\HttpKernel\Bundle\Bundle;

/**
 * Symfony Bundle для автоматической регистрации
 */
class DatabaseDumpsBundle extends Bundle
{
    public function getContainerExtension(): DatabaseDumpsExtension
    {
        return new DatabaseDumpsExtension();
    }
}
