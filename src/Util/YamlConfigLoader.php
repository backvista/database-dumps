<?php

namespace SmartCrm\DatabaseDumps\Util;

use SmartCrm\DatabaseDumps\Config\DumpConfig;
use SmartCrm\DatabaseDumps\Contract\ConfigLoaderInterface;
use SmartCrm\DatabaseDumps\Exception\ConfigNotFoundException;
use Symfony\Component\Yaml\Yaml;

/**
 * Загрузчик конфигурации из YAML файла
 */
class YamlConfigLoader implements ConfigLoaderInterface
{
    public function load(string $path): DumpConfig
    {
        if (!file_exists($path)) {
            throw ConfigNotFoundException::fileNotFound($path);
        }

        $data = Yaml::parseFile($path);

        return new DumpConfig(
            $data['full_export'] ?? [],
            $data['partial_export'] ?? [],
            $data['exclude'] ?? []
        );
    }
}
