<?php

namespace Hoks\NewsRecommendation\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;

class GenerateConfigJson extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'generate:config-json';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Make config json from newsrecomendation config';

    public function handle()
    {
        // Get Laravel config
        $configData = Config::get('newsrecommendation', []);

        if (empty($configData)) {
            $this->error('The newsrecommendation.php config file is empty or missing.');
            return;
        }

        // Convert to JSON format
        $jsonData = json_encode($configData, JSON_PRETTY_PRINT);

        if ($jsonData === false) {
            $this->error('Failed to encode JSON.');
            return;
        }

        // Define the path to save the Python config file
        $configPath = config_path('python_recommendations_config.json');

        // Write to the file
        $fileContent = $jsonData;

        file_put_contents($configPath, $fileContent);

        $this->info("Python configuration file generated successfully at: $configPath");
    }
    

}
