<?php

namespace Hoks\NewsRecommendation\Observers;

use Exception;
use Hoks\NewsRecommendation\Models\ArticleMongo;
use Illuminate\Support\Facades\Log;

class PublishMongoObserver
{
    public function created($publish)
    {
        if(config('newsrecommendation.use_publish')){
            try{
                //retrieving values
                $changedAttributes = $publish->getDirty();
                //get article_id
                $articleId = $changedAttributes['article_id'];

                //check if site_id is changed
                if(isset($changedAttributes['site_id'])){
                    $siteId = $changedAttributes['site_id'];
                }else{
                    $siteId = false;
                }

                //get website and its domain
                if($siteId){
                    $website = \DB::table(config('newsrecommendation.websites_table_name'))->where('id', $siteId)->first();
                    $domain = $website->url;
                }else{
                    $domain = false;
                }

                //check if category is changed
                if(isset($changedAttributes['category_id'])){
                    $categoryId = $changedAttributes['category_id'];
                }else{
                    $categoryId = false;
                }
                if($categoryId){
                    $category = \DB::table(config('newsrecommendation.categories_table_name'))->where('id', $categoryId)->first();
                    $categoryName = $category->name;
                    $categoryUrl = $domain . '/' . \Str::slug($categoryName);
                }else{
                    $categoryName = false;
                    $categoryUrl = false;
                }
                

                //check if subcategory is changed
                if(isset($changedAttributes['subcategory_id'])){
                    $subcategoryId = $changedAttributes['subcategory_id'];
                }else{
                    $subcategoryId = false;
                }

                if($subcategoryId){
                    $subcategory = \DB::table(config('newsrecommendation.categories_table_name'))->where('id', $subcategoryId)->first();
                    $subcategoryName = $subcategory->name;
                    $subcategoryUrl = $domain . '/' . \Str::slug($categoryName) . '/' . \Str::slug($subcategoryName);
                }else{
                    $subcategoryName = false;
                    $subcategoryUrl = false;
                }
               
                
                //update article mongo
                $articleMongo =  $articleMongo = ArticleMongo::where('article_id', $articleId)->first();
                if(isset($articleMongo) && !empty($articleMongo)){
                    if($categoryName){
                        $articleMongo->category = $categoryName;
                    }
                    if($subcategoryName){
                        $articleMongo->subcategory = $subcategoryName;
                    }
                    if($categoryUrl){
                        $articleMongo->category_url = $categoryUrl;
                    }
                    if($subcategoryUrl){
                        $articleMongo->subcategory_url = $subcategoryUrl;
                    }
                    if($siteId){
                        $articleMongo->site_id = $siteId;
                    }

                    if($categoryId || $subcategoryId || $siteId){
                        $articleMongo->save();
                    }
                }

            }
            catch(Exception $e){
                if(is_array($changedAttributes)){
                    Log::info('OBSERVER_ERROR_PUBLISH',$changedAttributes);
                }else{
                    Log::info('OBSERVER_ERROR_PUBLISH',$changedAttributes->toArray());
                }
            }
        }
        
    }
}
