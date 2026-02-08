<?php

namespace BackVista\DatabaseDumps\Util;

use BackVista\DatabaseDumps\Config\DumpConfig;
use BackVista\DatabaseDumps\Contract\ConfigLoaderInterface;
use BackVista\DatabaseDumps\Exception\ConfigNotFoundException;
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
            $data['partial_export'] ?? []
        );
    }
}
