<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Yaml\Yaml;

class ConvertSwaggerJsonToYaml extends Command
{
    protected $signature = 'swagger:json-to-yaml {jsonFile=storage/api-docs/api-docs.json} {yamlFile=storage/api-docs/swaggernew.yaml}';
    protected $description = 'Convert Swagger JSON file to YAML format';

    public function handle()
    {
        $jsonFile = base_path($this->argument('jsonFile'));
        $yamlFile = base_path($this->argument('yamlFile'));

        if (!file_exists($jsonFile)) {
            $this->error("JSON file not found at: {$jsonFile}");
            return 1;
        }

        $json = file_get_contents($jsonFile);
        $array = json_decode($json, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->error("Invalid JSON file.");
            return 1;
        }

        $yaml = Yaml::dump($array, 20, 2, Yaml::DUMP_OBJECT_AS_MAP);

        file_put_contents($yamlFile, $yaml);

        $this->info("Converted {$jsonFile} → {$yamlFile}");
        return 0;
    }
}
