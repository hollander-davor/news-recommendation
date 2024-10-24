<?php

namespace Hoks\NewsRecommendation\Observers;

use Exception;
use Hoks\NewsRecommendation\Models\ArticleMongo;
use Illuminate\Support\Facades\Log;

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

            //article site
            if(!config('newsrecommendation.site_id')){
                if(config('newsrecommendation.site_id_from_public')){
                    $publishSite = \DB::table(config('newsrecommendation.publish_table_name'))->where('article_id', $original['id'])->first();
                    if($publishSite){
                        $website = \DB::table(config('newsrecommendation.websites_table_name'))->where('id', $publishSite->site_id)->first();
                        $domain = $website->url;
                    }else{
                        return false;
                    }
                }else{
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
                //article subcategory
                $subcategory = \DB::table(config('newsrecommendation.categories_table_name'))->where('id', $publish->subcategory_id)->first();
                $subcategoryName = $subcategory->name;
            }else{
                //article category
                $category = \DB::table(config('newsrecommendation.categories_table_name'))->where('id', $original['category_id'])->first();
                $categoryName = $category->name;
                //article subcategory
                $subcategory = \DB::table(config('newsrecommendation.categories_table_name'))->where('id', $original['subcategory_id'])->first();
                $subcategoryName = $subcategory->name;
            }

            //retrieving changed values
            $changedAttributes = $article->getDirty();

            if($original['published'] == 1) {

                if(isset($changedAttributes['heading']) || isset($changedAttributes['text']) || isset($changedAttributes['lead']) || isset($changedAttributes['image_orig'])) {

            

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


                    $AiModel = config('newsrecommendation.ai_model');
                    $client = \OpenAI::client('chat/completions',30,$AiModel);
                    $answerString = $client->ask('Analiziraj tekst, uvod, naslov i kategoriju novinskog artikla i predloži 10 ključnih reči koje se odnose na glavne teme i entitete. Pokaži ih samo kao string, odvojene sa "|" bez dodatnog objašnjenja. Ovo je naslov: '.$heading.'.Ovo je uvod: '.$lead.'. Ovo je kategorija: ' . $categoryName . '. Ovo je tekst: '.$text)['content'];
                    $answerArray = explode('|',strtolower($answerString));

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
                        $articleMongo->tags = $answerArray;
                        $articleMongo->save();
                    }
                }
            }
        }
        catch(Exception $e){
            if(is_array($original)){
                Log::info('OBSERVER_ERROR',$original);
            }else{
                Log::info('OBSERVER_ERROR',$original->toArray());
            }
        }
        
    }
}
