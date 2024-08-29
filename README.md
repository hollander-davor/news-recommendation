# News-recommendation
## Installation:
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
6. Set up Redis to take data from user (see example bellow)
7. Use package :D




# OpenAI
This part of package is intended for communication with OpenAI.
## Code example

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


# Set up Redis to take data from user
This is example on how to take user data and store it in Redis
## Code example
### Set up route
```php
Route::post('/reader', [FrontendController::class, 'readerData'])->name('reader');
```
### Write method in controller
```php
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Carbon;

public function readerData(){
    $redisKeys = Redis::keys('*');

    $data = request()->validate([
        'article_id' => ['required','numeric','exists:articles,id'],
        'readerID' => ['required','string'],
        'publish_at' => ['required','date']

    ]);
    $publishDate = Carbon::parse($data['publish_at']);
    // only take into account articles that are not older than 10 days
    if($publishDate > now()->subDays(10)){
        //extract reader string from redis and update it
        $readerData = Redis::get('reader-'.$data['readerID']);
        if($readerData){
            Redis::append('reader-'.$data['readerID'],'|'.$data['article_id']);
        }else{
            Redis::set('reader-'.$data['readerID'],$data['article_id']);
        }
    }

}
```
### Write script on blade.php that will create user id and make ajax
```php
<script>
    function generateUUID() {
        // Get current time in milliseconds
        var d = new Date().getTime();

        // Define the UUID template with placeholder characters
        var uuid = "xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx";

        // Replace the placeholders with random hexadecimal digits
        uuid = uuid.replace(/[xy]/g, function(c) {
            // Generate a random number between 0 and 15
            var r = (d + Math.random()*16)%16 | 0;

            // Update value of d for the next placeholder
            d = Math.floor(d/16);

            // Convert the number to a hexadecimal digit and return it
            return (c=="x" ? r : (r&0x3|0x8)).toString(16);
        });

        return uuid+'-'+{{$article['id']}};
    }
    
    $(document).ready(function() {
        const reader = localStorage.getItem("reader");
        var readerID;
        // if there is reader take data
        if(reader){
            readerID = reader;
        }else{  
            //generate readerID
            readerID = generateUUID();
            localStorage.setItem("reader",readerID);
        }
        console.log(readerID);
        $.ajax({
            type: "POST",
            url: "{{route('frontend.ajax.reader')}}",
            data: {
                _token: "{{ csrf_token() }}",
                article_id: {{$article['id']}},
                readerID : readerID,
                publish_at : "{{$article['publish_at']}}"

            }
        });

    });
</script>
```
