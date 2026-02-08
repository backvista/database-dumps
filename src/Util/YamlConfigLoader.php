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

        $connections = [];
        if (isset($data[DumpConfig::KEY_CONNECTIONS]) && is_array($data[DumpConfig::KEY_CONNECTIONS])) {
            foreach ($data[DumpConfig::KEY_CONNECTIONS] as $connName => $connData) {
                if (is_array($connData)) {
                    $connections[(string) $connName] = new DumpConfig(
                        $connData[DumpConfig::KEY_FULL_EXPORT] ?? [],
                        $connData[DumpConfig::KEY_PARTIAL_EXPORT] ?? []
                    );
                }
            }
        }

        return new DumpConfig(
            $data[DumpConfig::KEY_FULL_EXPORT] ?? [],
            $data[DumpConfig::KEY_PARTIAL_EXPORT] ?? [],
            $connections
        );
    }
}
