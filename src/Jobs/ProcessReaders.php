<?php

namespace Hoks\NewsRecommendation\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Redis;

class ProcessReaders implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        //get todays date
        $todaysDate = now()->format('d-m-Y');

        $redisKeys = Redis::keys('*');
        foreach($redisKeys as $redisKey){
            //process only redis keys starting with "reader-"
            if(strstr($redisKey,'reader-')){
                //get articles ids string and convert it to array
                $articlesString = Redis::get($redisKey);
                $articlesIdsArray = explode('|',$articlesString);
                $articlesIdsArray = array_unique($articlesIdsArray);
                //we will make one big array with following structure
                // 'tag_name' => nummber of occurences
                $articleTagsSummedArray = [];
                foreach($articlesIdsArray as $articleId){
                    //get every article tags from mongoDB
                    $articleTags = [];
                    $articleTagsSummedArray = array_merge($articleTagsSummedArray,$articleTags);
                }

                /**
                 * we now count duplicates to get something like this
                 * [
                 *      'tagA' => 10,
                 *      'tagB' => 7,
                 *      'tagC' => 5,
                 *      'tagD' => 3,
                 *      'tagE' => 1,
                 * ]
                 * and this array we actually put into db
                 */
                $tagsOcurrences = array_count_values($articleTagsSummedArray);

                //now we get user data on tags for this day
                //if data does not exist, we create it (first job call for day)
                $userTags = [];

                if(count($userTags) > 0){
                    foreach($userTags as $key => $userTag){
                        if(isset($tagsOcurrences[$key])){
                            $userTags[$key] = $userTag+$tagsOcurrences[$key];
                            unset($tagsOcurrences[$key]);
                        }
                    }
                    $userTags = array_merge($userTags,$tagsOcurrences);
                }
                
                //update user tags for this day

                //set latest update for user now()

                //check if user has tags for day before n days, and delete it
                $daysKeepData = config('newsrecommendation.days_keep_data');
                $dayToDelete = now()->subDays($daysKeepData)->format('d-m-Y');

                //if user has latest update older than n days, delete user
                $daysDeleteUser = config('newsrecommendation.days_delete_user');
                $dayToDelete = now()->subDays($daysDeleteUser)->format('d-m-Y');

                //delete redis key
                Redis::command('DEL',[$redisKey]);

            }
        }
        
    }
}
