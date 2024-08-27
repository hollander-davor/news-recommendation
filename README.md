# News-recommendation
Installation:
1. composer require davor/news-recommendation
2. php artisan vendor:publish --tag=config --provider="Hoks\NewsRecommendation\NewsRecommendationServiceProvider"
3. Set up config/newsrecommendation.php
4. Set up connection for mongodb in config/database.php
```php

    'mongodb' => [
            'driver'   => 'mongodb',
            'host'     => env('DB_HOST_MONGO', 'mongo'),
            'port'     => env('DB_PORT_MONGO', 27017),
            'database' => env('DB_DATABASE_MONGO', 'mongo'),
            'username' => env('DB_USERNAME_MONGO'),
            'password' => env('DB_PASSWORD_MONGO'),
            'options'  => [
                'database' => env('DB_AUTH_DATABASE', 'admin'), // Authentication database
            ],
        ],
```
5. Shedule job on desired interval 
    ```php
        $schedule->job(new ProcessReaders())->everyThirtyMinutes();
    ```
6. Use package :D




# OpenAI
This part of package is intended for communication with OpenAI.
# Code example

```php
/**
 * This example shows how to ask OpenAI to create prompt for creating OpenAI image
 */

//using facade we create client and specify uri for OpenAI API
$askClient = \OpenAI::client('chat/completions');
//we ask for prompt (note that we use ['content'] to retrieve prompt)
$imagePrompt = $askClient->ask('Write best prompt for creating poster of Novak Djokovic being the best tennis player ever')['content'];
//using facade we create ampther client for image
$imageClient = \OpenAI::client('images/generations',60,'dall-e-3');
//we retrieve image url (by default)
$imageUrl = $imageClient->generateImage($imagePrompt)[0];

```
