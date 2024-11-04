<?php

namespace Hoks\NewsRecommendation\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Redis;
use Hoks\NewsRecommendation\Models\UserMongo;
use Hoks\NewsRecommendation\Models\ArticleMongo;

class ProcessReaders extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'process:readers';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Proces redis readers';

    public function handle()
    {
    
        //get todays date
        $todaysDate = now()->format('d-m-Y');

        $redisKeys = Redis::keys('*');
        foreach ($redisKeys as $redisKey) {
            
            //process only redis keys starting with "reader-"
            if (strstr($redisKey, config('newsrecommendation.redis_reader_prefix').'reader-')) {

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
                        $siteId = $articleMongo->site_id;
                        $siteKey = 'site_'.$siteId;
                        if(isset($articleTagsSummedArray[$siteKey])){
                            $articleTagsSummedArray[$siteKey] = array_merge($articleTagsSummedArray[$siteKey], $articleTags);
                        }else{
                            $articleTagsSummedArray[$siteKey] = $articleTags;
                        }
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
                $tagOccurencesArray = [];
                foreach($articleTagsSummedArray as $key => $articleTagsSummedArrayOneSite){
                    $tagOccurencesArray[$key] = array_count_values($articleTagsSummedArrayOneSite);
                }
                //sorting array by the number of tags in descending order
                $tagsOcurrencesSortArray = [];
                foreach($tagOccurencesArray as $siteKey => $tagsOcurrences){
                    $tagsOcurrencesSort = [];
                    foreach($tagsOcurrences as $key => $value) {
                        $tagsOcurrencesSort[$key] = $value;
                    }
                    arsort($tagsOcurrencesSort);
                    $tagsOcurrencesSortArray[$siteKey] = $tagsOcurrencesSort;
                }
               
                //we create a user id
                //there are two variants of redisKey, with and without firebase key
                if(strstr($redisKey,'###')){
                    $longRedisKeyArray = explode('###',$redisKey);
                    $userId = str_replace(config('newsrecommendation.redis_reader_prefix').'reader-','',$longRedisKeyArray[0]);
                    $firebaseUid = $longRedisKeyArray[1];
                }else{
                    $userId = str_replace(config('newsrecommendation.redis_reader_prefix').'reader-', '', $redisKey);
                    $firebaseUid = false;

                }

                if($firebaseUid){
                    //try to find if there is user with firebase_uid
                    $existingUser = UserMongo::where('firebase_uid', $firebaseUid)->first();
                    //if there is no user with firebase_uid and firebase_uid is in redis key, it could
                    //be the first login on anonymus user, so we try to find that user
                    if(!isset($existingUser) && empty($existingUser)){
                        $existingUser = UserMongo::where('user_id', $userId)->first();
                    }
                }else{
                    $existingUser = UserMongo::where('user_id', $userId)->first();
                }

                //now we get user data on tags for this day
                //if data does not exist, we create it (first job call for day)
                $existingUser = UserMongo::where('user_id', $userId)->first();

                if(isset($existingUser) && !empty($existingUser)) {
                    //we take all the tags that exist
                    $tags = $existingUser->tags;
                    //if there is a key with today
                    if(isset($tags[$todaysDate])) {
                        $userTagsArray = [];
                        $userTagsArray = (array) json_decode($existingUser->tags[$todaysDate]);
                        //todaysTags should contain all tags for todaysDate (both new and old)
                        $todaysTags = [];
                        foreach($userTagsArray as $siteKey => $userTagsOnSite){
                            //check if there are new tags for this site id
                            if(isset($tagsOcurrencesSortArray[$siteKey])){
                                $userTagsOnSite = (array) $userTagsOnSite;
                                //if there are already tags under today's key, add new ones
                                if(count($userTagsOnSite) > 0) {
                                    //if new tag already exists, increase its occurence number in original array
                                    foreach ($userTagsOnSite as $key => $userTag) {
                                        if (isset($tagsOcurrencesSortArray[$siteKey][$key])) {
                                            $userTagsOnSite[$key] = $userTag + $tagsOcurrencesSortArray[$siteKey][$key];
                                            unset($tagsOcurrencesSortArray[$siteKey][$key]);
                                        }
                                    }
                                    //for this siteKey, merge new and old tags
                                    $userTagsArray[$siteKey] = array_merge($userTagsOnSite, $tagsOcurrencesSortArray[$siteKey]);
                                    $todaysTags[$siteKey] = $userTagsArray[$siteKey];
                                }
                                //if there is no key with today's date among the tags, create it and merge the new tags with the existing ones
                                else {
                                    $todaysTags[$siteKey] = $tagsOcurrencesSortArray[$siteKey];
                                }
                            }else{
                                //if there are no new tags for some site, we just save old values for that site
                                $todaysTags[$siteKey] = $userTagsArray[$siteKey];
                            }
                        }
                        $todaysTags =  [$todaysDate => json_encode($todaysTags)];
                        $existingUser->tags = array_merge($existingUser->tags,$todaysTags);
                    //if there is no key with today, create it and enter the tags
                    }else {
                        $todaysTags = [$todaysDate => json_encode($tagsOcurrencesSortArray)];
                        $existingUser->tags = array_merge($existingUser->tags,$todaysTags);
                    }

                    //if there are read news, add new ones, but keep the old ones
                    if(!empty($existingUser->read_news)) {
                        $readNewsOld = (array) json_decode($existingUser->read_news);
                        $readNewsNew = $articlesIdsArray;
                        $readNewsMerge = array_merge($readNewsOld, $readNewsNew);
                        $readNewsMerge = array_unique($readNewsMerge);
                        $readNewsMerge = array_values($readNewsMerge);
                        $existingUser->read_news = json_encode($readNewsMerge);
                    }else {
                        $existingUser->readed_news = json_encode($articlesIdsArray);
                    }

                    //we take all the tags from the user and format them as a multidimensional array
                    $userTagsAll = $existingUser->tags;
                    
                    $formatedTags = [];
                    foreach($userTagsAll as $date => $sitesTags) {
                        $sitesTags = (array) json_decode($sitesTags);
                        foreach($sitesTags as $siteKey => $tags){
                            $tags = (array) $tags;
                            $formatedTags[$date][$siteKey] = $tags;
                        }
                    }
                    //we check whether the user has read the news so that there is no duplication for the recommendation
                    $readNewsOld = (array) json_decode($existingUser->read_news);
                    if(empty($readNewsOld)) {
                        //we call a method that gives us an array of recommended news ids
                        $recommendedArticles = $this->recommendedArticles($formatedTags, $articlesIdsArray);
                        $existingUser->news_recommendation = json_encode($recommendedArticles);
                    } else {
                        $readNewsMerge = array_merge($readNewsOld, $articlesIdsArray);
                        $readNewsMerge = array_unique($readNewsMerge);
                        $recommendedArticles = $this->recommendedArticles($formatedTags, $readNewsMerge);
                        $existingUser->news_recommendation = json_encode($recommendedArticles);
                    }
                    $existingUser->latest_update = $todaysDate;
                    //check if userId is changed (user used another device or deleted local storage)
                    if($firebaseUid){
                        if($userId != $existingUser->user_id){
                            $existingUser->user_id = $userId;
                        }
                    }
                    $existingUser->save();

                //if there is no user, create one
                }else {
                   //only create user if there are tags for articles (it may happen that user read some news that have no tags)
                   if(!empty($tagsOcurrencesSortArray)){
                        $userMongo = new UserMongo();
                        $userMongo->user_id = $userId;
                        $userMongo->read_news = json_encode($articlesIdsArray);
                        $recommendedArticles = $this->recommendedArticles([$todaysDate => $tagsOcurrencesSortArray], $articlesIdsArray);
                        $userMongo->news_recommendation = json_encode($recommendedArticles);
                        $userMongo->tags = [$todaysDate => json_encode($tagsOcurrencesSortArray)];
                        $userMongo->latest_update = $todaysDate;
                        if($firebaseUid){
                            $userMongo->firebase_uid = $firebaseUid;
                        }
                        $userMongo->save();
                    }
                }

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
    protected function recommendedArticles($userTagsArray, $read_news = []){
        //we set values to integers
        if(!empty($read_news)) {
            $read_news = array_map('intval', $read_news);
        }
        //we merge tags for all days for one site in one element of an array
        $allTagsArray = [];
        //foreach trough days
        foreach($userTagsArray as $oneDayTagsArray){
            //foreach trough sites
            foreach($oneDayTagsArray as $siteKey => $oneDayOneSiteTags){
                //foreach trough tags for one day for one site
                $tempArray = [];
                foreach($oneDayOneSiteTags as $tag => $value){
                    if(isset($allTagsArray[$siteKey][$tag])){
                        $tempArray[$tag] = $allTagsArray[$siteKey][$tag]+$value;
                    }else{
                        $tempArray[$tag] = $value;
                    }
                }
                //sort tags by its occurrence
                arsort($tempArray);
                $allTagsArray[$siteKey] = $tempArray;
            }
            
        }
        //based on lowbar, we cut off part of array
        $arrayLength = config('newsrecommendation.tags_array_length');
        $tagsConstant = config('newsrecommendation.tags_constant');
        $recommendedArticlesCount = config('newsrecommendation.recommended_articles_count');
        $recommendedArticlesFinal = [];
        foreach($allTagsArray as $siteKey => $allTags){
            $allTags = array_slice($allTags,0,$arrayLength);
        
            //we sum all tags values (sum of all occurrences)
            $sumTagsValues = 0;
            foreach($allTags as $tag => $value){
                $sumTagsValues = $sumTagsValues+$value;
            }

            //we calculate tag coefficients
            $tagsCoefficients = [];
            foreach($allTags as $tag => $value){
                //here we get number that is less than 1 so we multiply by 10 and by constant from config
                $tagsCoefficients[$tag] = (int) round(($value/$sumTagsValues)*10*$tagsConstant);
            }
            //for each tag, we get tagCoeff number of articles (take just ids)
            $articles = [];
            foreach($tagsCoefficients as $tag => $value){
                //get $value articles with $tag, should be array of ids
                $siteIdForQuery = (int) str_replace('site_','',$siteKey);
                $articlesQuery = ArticleMongo::where('site_id',$siteIdForQuery)->where('tags', $tag)->whereNotIn('article_id', $read_news)->orderBy('publish_at', 'desc')->limit($value)->pluck('article_id');
                $articlesTemp = [];
                foreach($articlesQuery as $item){
                    $articlesTemp[] = $item;
                }
                $articles[$tag] = $articlesTemp;
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
                $articles[$tag] = array_values($articles[$tag]);

            }

            //we now have some articles for every tag
            //once again we recalculate how many of each we should take
            $articlesSum = 0;
            foreach($articles as $tag => $articlesArray){
            $articlesSum = $articlesSum + count($articlesArray);
            }
            $articlesCoefficient = [];
            if($articlesSum != 0) {
                foreach($articles as $tag => $articlesArray){
                    $articlesCoefficient[$tag] = (int) round((count($articlesArray)/$articlesSum)*$recommendedArticlesCount);
                }
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
                    if(isset($articles[$tag][$x])){
                        $recommendedArticles[] = $articles[$tag][$x];
                        unset($articles[$tag][$x]);
                    }
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

            $recommendedArticlesFinal[$siteKey] = $recommendedArticles;

        }

        return $recommendedArticlesFinal;
        
    }

   

}
