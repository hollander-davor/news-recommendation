<?php

namespace Hoks\NewsRecommendation\Observers;

use Exception;
use Hoks\NewsRecommendation\Models\ArticleMongo;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Carbon;

class ArticleMongoObserver
{

    public function updated($article)
    {
        try{
            //check if $article is an instance of the evaluated class
            $expectedClass = config('newsrecommendation.article_model');
            if (!($article instanceof $expectedClass)) {
                return;
            }
            //retrieving the original values ​​before the update occurs
            $original = $article->getOriginal();

            //retrieving changed values
            $changedAttributes = $article->getDirty();


            //article site
            if(!config('newsrecommendation.site_id')){
                if(config('newsrecommendation.site_id_from_public')){
                    //get row from publish table
                    $publishSite = \DB::table(config('newsrecommendation.publish_table_name'))->where('article_id', $original['id'])->first();
                    if($publishSite){
                        $website = \DB::table(config('newsrecommendation.websites_table_name'))->where('id', $publishSite->site_id)->first();
                        $domain = $website->url;
                    }else{
                        return false;
                    }
                }else{
                    //here we should have to check if article has site_id set in articles table
                    //its best to have another config element to check that
                    $website = \DB::table(config('newsrecommendation.websites_table_name'))->first();
                    $domain = $website->url;
                }
            }else{
                $website = \DB::table(config('newsrecommendation.websites_table_name'))->where('id', config('newsrecommendation.site_id'))->first();
                $domain = $website->url;
            }
            if(config('newsrecommendation.use_publish')) {
                $publish = \DB::table(config('newsrecommendation.publish_table_name'))->where('article_id', $original['id'])->first();
                if(!$publish){
                    return;
                }
                //article category
                $category = \DB::table(config('newsrecommendation.categories_table_name'))->where('id', $publish->category_id)->first();
                $categoryName = $category->name;
                $categoryUrl = $domain . '/' . \Str::slug($categoryName);

                //article subcategory
                $subcategory = \DB::table(config('newsrecommendation.categories_table_name'))->where('id', $publish->subcategory_id)->first();
                $subcategoryName = $subcategory->name;
                $subcategoryUrl = $domain . '/' . \Str::slug($categoryName) . '/' . \Str::slug($subcategoryName);
            }else{
                //article category
                if(isset($changedAttributes['category_id'])){
                    //if we changed category_id, take the new one (this will only work if we store category_id in articles table, otherwise antoher observer is in work)
                    $category = \DB::table(config('newsrecommendation.categories_table_name'))->where('id', $changedAttributes['category_id'])->first();
                }else{
                    $category = \DB::table(config('newsrecommendation.categories_table_name'))->where('id', $original['category_id'])->first();
                }
                $categoryName = $category->name;
                $categoryUrl = $domain . '/' . \Str::slug($categoryName);
                //article subcategory
                if(isset($changedAttributes['subcategory_id'])){
                    //if we changed subcategory_id, take the new one (this will only work if we store subcategory_id in articles table, otherwise antoher observer is in work)
                    $subcategory = \DB::table(config('newsrecommendation.categories_table_name'))->where('id', $changedAttributes['subcategory_id'])->first();
                }else{
                    $subcategory = \DB::table(config('newsrecommendation.categories_table_name'))->where('id', $original['subcategory_id'])->first();
                }
                $subcategoryName = $subcategory->name;
                $subcategoryUrl = $domain . '/' . \Str::slug($categoryName) . '/' . \Str::slug($subcategoryName);
            }

            

            if (isset($changedAttributes['heading'])) {
                $heading = $changedAttributes['heading'];
            }else {
                $heading = $original['heading'];
            }

            if (isset($changedAttributes['text'])) {
                $text = $changedAttributes['text'];
                $text = strip_tags($text);
            }else {
                $text = $original['text'];
                $text = strip_tags($text);
            }

            if (isset($changedAttributes['lead'])) {
                $lead = $changedAttributes['lead'];
            }else {
                $lead = $original['lead'];
            }

            //publish_at
            if (isset($changedAttributes['publish_at'])) {
                $publishAt = Carbon::parse($changedAttributes['publish_at'])->format('Y-m-d H:i:s');
                if(strtotime(($publishAt)) === false){
                    Log::info('MRK_2',$changedAttributes);
                }
            }else {
                $publishAt = Carbon::parse($original['publish_at'])->format('Y-m-d H:i:s');
                if(strtotime(($publishAt)) === false){
                    Log::info('MRK_3',$original);
                }
                // $publishAt = $original['publish_at'];

            }
            //published
            if (isset($changedAttributes['published'])) {
                $published = $changedAttributes['published'];
            }else {
                $published = $original['published'];
            }
           

            //call OpenAI only when heading or text or lead is changed
            if(isset($changedAttributes['heading']) || isset($changedAttributes['text']) || isset($changedAttributes['lead'])) {

                $AiModel = config('newsrecommendation.ai_model');
                $client = \OpenAI::client('chat/completions',30,$AiModel);
                $answerString = $client->ask('Analiziraj tekst, uvod, naslov i kategoriju novinskog artikla i predloži 10 ključnih reči koje se odnose na glavne teme i entitete. Pokaži ih samo kao string, odvojene sa "|", bez navodnika, bez dodatnog objašnjenja. Ovo je naslov: '.$heading.'.Ovo je uvod: '.$lead.'. Ovo je kategorija: ' . $categoryName . '. Ovo je tekst: '.$text)['content'];
                $answerArray = explode('|',strtolower($answerString));
                $answerArrayFinal = [];
                foreach($answerArray as $key => $value){
                    $answerArrayFinal[$key] = str_replace('"','',trim($value));
                }
            }

            $articleUrl = $domain . '/' . \Str::slug($categoryName) . '/' . \Str::slug($subcategoryName) . '/' . $original['id'] . '/' . \Str::slug(!empty($original['og_title']) ? $original['og_title'] : $heading) .'/vest';
            //get salt from config
            $salt = config('newsrecommendation.url_salt');
            $urlWithSalt = $salt . '|' . $articleUrl;
            //encod URL useing hash_hmac
            $encodedUrl = base64_encode($urlWithSalt);

            $articleMongo = ArticleMongo::where('article_id', $original['id'])->first();
            if(isset($articleMongo) && !empty($articleMongo)) {

                if (isset($changedAttributes['image_orig'])) {
                    $updateData = [];

                    foreach (config('newsrecommendation.image_dimensions') as $imageDimension) {
                        $updateData[$imageDimension] = $changedAttributes[$imageDimension];
                    }

                    if (!empty($updateData)) {
                        $articleMongo->update($updateData);
                    }
                }

                $articleMongo->heading = $heading;
                $articleMongo->lead = $lead;
                $articleMongo->article_url = $encodedUrl;
                if(isset($answerArrayFinal) && !empty($answerArrayFinal)){
                    $articleMongo->tags = $answerArrayFinal;
                }
                $articleMongo->published = $published;
                $articleMongo->publish_at = $publishAt;
                $articleMongo->category = $categoryName;
                $articleMongo->subcategory = $subcategoryName;
                $articleMongo->category_url = $categoryUrl;
                $articleMongo->subcategory_url = $subcategoryUrl;

                $articleMongo->save();
            }
        }
        catch(Exception $e){
            if(is_array($original)){
                Log::info('OBSERVER_ERROR',$original);
                Log::info('OBSERVER_ERROR_MESSAGE',[$e->getMessage()]);
            }else{
                Log::info('OBSERVER_ERROR',$original->toArray());
                Log::info('OBSERVER_ERROR_MESSAGE',[$e->getMessage()]);
            }

            if(is_array($changedAttributes)){
                Log::info('OBSERVER_ERROR_CHANGED',$changedAttributes);
            }else{
                Log::info('OBSERVER_ERROR_CHANGED',$changedAttributes->toArray());
            }
        }
        
    }
}
