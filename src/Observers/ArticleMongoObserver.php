<?php

namespace Hoks\NewsRecommendation\Observers;

use Hoks\NewsRecommendation\Models\ArticleMongo;

class ArticleMongoObserver
{

    public function updated($article)
    {
        //check if $article is an instance of the evaluated class
        $expectedClass = config('newsrecommendation.article_model');
        if (!($article instanceof $expectedClass)) {
            return;
        }

        //retrieving the original values ​​before the update occurs
        $original = $article->getOriginal();

        //article site
        if(!config('newsrecommendation.site_id')){
            $website = \DB::table(config('newsrecommendation.websites_table_name'))->where('id', $original['site_id'])->first();
            $domain = $website->url;
        }else{
            $website = \DB::table(config('newsrecommendation.websites_table_name'))->where('id', config('newsrecommendation.site_id'))->first();
            $domain = $website->url;
        }
        //article category
        $category = \DB::table(config('newsrecommendation.categories_table_name'))->where('id', $original['category_id'])->first();
        $categoryName = $category->name;
        //article subcategory
        $subcategory = \DB::table(config('newsrecommendation.categories_table_name'))->where('id', $original['subcategory_id'])->first();
        $subcategoryName = $subcategory->name;

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
                    $articleMongo->article_url = $articleUrl;
                    $articleMongo->tags = $answerArray;
                    $articleMongo->save();
                }
            }
        }
    }
}
