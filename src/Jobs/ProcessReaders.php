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
        foreach ($redisKeys as $redisKey) {

            //process only redis keys starting with "reader-"
            if (strstr($redisKey, 'reader-')) {

                //get articles ids string and convert it to array
                $articlesString = Redis::get($redisKey);
                $articlesIdsArray = explode('|', $articlesString);
                $articlesIdsArray = array_unique($articlesIdsArray);

                //we will make one big array with following structure
                // 'tag_name' => nummber of occurences
                $articleTagsSummedArray = [];
                foreach ($articlesIdsArray as $articleId) {
                    //get every article tags from mongoDB
                    $articleMongo = ArticleMongo::where('article_id', (int) $articleId)->first();
                    if(isset($articleMongo) && !empty($articleMongo)) {
                        $articleTags = [];
                        $articleTags = $articleMongo->tags;
                        $articleTagsSummedArray = array_merge($articleTagsSummedArray, $articleTags);
                    }
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

                //sorting array by the number of tags in descending order
                $tagsOcurrencesSort = [];
                foreach($tagsOcurrences as $key => $value) {
                    $tagsOcurrencesSort[$key] = $value;
                }
                arsort($tagsOcurrencesSort);

                //we create a user id
                $explodeRedisKey = explode('-', $redisKey);
                $userId = $explodeRedisKey[1];

                //now we get user data on tags for this day
                //if data does not exist, we create it (first job call for day)
                $existingUser = UserMongo::where('user_id', $userId)->first();

                if(isset($existingUser) && !empty($existingUser)) {
                    //we take all the tags that exist
                    $tags = $existingUser->tags;
                    //if there is a key with today
                    if(isset($tags[$todaysDate])) {
                        $userTags = [];
                        $userTags = (array) json_decode($existingUser->tags[$todaysDate]);
                        //if there are already tags under today's key, add new ones
                        if(count($userTags) > 0) {
                            foreach ($userTags as $key => $userTag) {
                                if (isset($tagsOcurrencesSort[$key])) {
                                    $userTags[$key] = $userTag + $tagsOcurrencesSort[$key];
                                    unset($tagsOcurrencesSort[$key]);
                                }
                            }
                            $userTags = array_merge($userTags, $tagsOcurrencesSort);
                            $todaysTags = [$todaysDate => json_encode($userTags)];
                            $existingUser->tags = array_merge($existingUser->tags,$todaysTags);
                            $existingUser->save();
                        }
                        //if there is no key with today's date among the tags, create it and merge the new tags with the existing ones
                        else {
                            $todaysTags = [$todaysDate => json_encode($tagsOcurrencesSort)];
                            $existingUser->tags = array_merge($existingUser->tags,$todaysTags);
                            // $existingUser->tags = [$todaysDate => json_encode($tagsOcurrencesSort)];
                            $existingUser->save();
                        }
                    //if there is no key with today, create it and enter the tags
                    }else {
                        $todaysTags = [$todaysDate => json_encode($tagsOcurrencesSort)];
                        $existingUser->tags = array_merge($existingUser->tags,$todaysTags);
                        // $existingUser->tags = [$todaysDate => json_encode($tagsOcurrencesSort)];
                        $existingUser->save();
                    }
                //if there is no user, create one
                }else {
                    $userMongo = new UserMongo();
                    $userMongo->user_id = $userId;
                    $userMongo->news_recommendation = null;
                    $userMongo->tags = [$todaysDate => json_encode($tagsOcurrencesSort)];
                    $userMongo->latest_update = $todaysDate;
                    $userMongo->save();
                }

                // $userTags = [];

                // if (count($userTags) > 0) {
                //     foreach ($userTags as $key => $userTag) {
                //         if (isset($tagsOcurrencesSort[$key])) {
                //             $userTags[$key] = $userTag + $tagsOcurrencesSort[$key];
                //             unset($tagsOcurrencesSort[$key]);
                //         }
                //     }
                //     $userTags = array_merge($userTags, $tagsOcurrencesSort);
                // }

                //update user tags for this day

                //set latest update for user now()

                //check if user has tags for day before n days, and delete it
                if(isset($existingUser)) {
                    $daysKeepData = config('newsrecommendation.days_keep_data');
                    $dayToDeleteTag = now()->subDays($daysKeepData)->format('d-m-Y');

                    $tagsToDelete = $existingUser->tags;
                    if(isset($tagsToDelete[$dayToDeleteTag])) {
                        unset($tagsToDelete[$dayToDeleteTag]);
                        $existingUser->tags = $tagsToDelete;
                        $existingUser->save();
                    }

                }

                //delete redis key
                Redis::command('DEL', [$redisKey]);
            }
        }
    }


    /**
     * this method returns list of recommended articles ids for given user tags
     */
    protected function recommendedArticles($userTags){
        //we merge tags for all days in one array
        $allTags = [];
        //foreach trough days
        foreach($userTags as $oneDayTags){
            //foreach trough tags for one day
            foreach($oneDayTags as $tag => $value){
                if(isset($allTags[$tag])){
                    $allTags[$tag] = $allTags[$tag]+$value;
                }else{
                    $allTags[$tag] = $value;
                }
            }
        }

        //sort tags by its occurrence
        arsort($allTags);

        //based on lowbar, we cut off part of array
        $arrayLength = config('newsrecommendation.tags_array_length');
        $allTags = array_slice($allTags,0,$arrayLength);
        
        //we sum all tags values (sum of all occurrences)
        $sumTagsValues = 0;
        foreach($allTags as $tag => $value){
            $sumTagsValues = $sumTagsValues+$value;
        }

        //we calculate tag coefficients
        $tagsCoefficients = [];
        $tagsConstant = config('newsrecommendation.tags_constant');
        foreach($allTags as $tag => $value){
            //here we get number that is less than 1 so we multiply by 10 and by constant from config
            $tagsCoefficients[$tag] = (int) round(($value/$sumTagsValues)*10*$tagsConstant);
        }

        //for each tag, we get tagCoeff number of articles (take just ids)
        $articles = [];
        foreach($tagsCoefficients as $tag => $value){
            $articles[$tag] = ArticleMongo::where();//get $value articles with $tag, should be array of ids
        }
        //remove duplicates, keep originals (original is first of its kind)
        $seen = [];
        foreach($articles as $tag => $articlesArray){
            foreach($articlesArray as $key => $id){
                if(in_array($id,$seen)){
                    unset($articles[$tag][$key]);
                }
                $seen[] = $id;
            }
        }

        //we now have some articles for every tag
        //once again we recalculate how many of each we should take
        $articlesSum = 0;
        foreach($articles as $tag => $articlesArray){
           $articlesSum = $articlesSum + count($articlesArray);
        }
        $recommendedArticlesCount = config('newsrecommendation.recommended_articles_count');
        $articlesCoefficient = [];
        foreach($articles as $tag => $articlesArray){
            $articlesCoefficient[$tag] = (int) round((count($articlesArray)/$articlesSum)*$recommendedArticlesCount);
        }

        // finnaly, we take recommended articles by the articlesCoeff
        $recommendedArticles = [];
        $enoughArticles = false;
        foreach($articlesCoefficient as $tag => $value){
            for ($x = 0; $x < $value; $x++) {
                //break when you collect enough articles
                if(count($recommendedArticles) >= $recommendedArticlesCount){
                    $enoughArticles = true;
                    break;
                }
                $recommendedArticles[] = $articles[$tag][$x];
                unset($articles[$tag][$x]);
            }
        }

        //if there are not enough articles, add some more
        if(!$enoughArticles){
            foreach($articles as $tag => $articlesArray){
                foreach($articlesArray as $article){
                    if(count($recommendedArticles) >= $recommendedArticlesCount){
                    break;
            }
                    $recommendedArticles[] = $article;
                }
            
            }
        }

        return $recommendedArticles;
        
    }
}
