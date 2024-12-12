<?php

namespace Hoks\NewsRecommendation\Commands;

use Illuminate\Console\Command;
use Hoks\NewsRecommendation\Models\ArticleMongo;

class SyncArticles extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sync:mongo-articles';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync mongo and project articles database (if there was some error)';

   

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        // there was an issue with publish_at and published columns
        // there may be situation where some other fields were not updated
        // that is why we must update all mongo articles
        $count = 0;
        //get all mongo articles
       $mongoArticles = ArticleMongo::get();
       foreach($mongoArticles as $mongoArticle){
            $status = $this->checkData($mongoArticle);
            if($status){
                $count++;
            }
       }
       $this->info($count);
    }

    protected function checkData($mongoArticle){
        $mongoLead = $mongoArticle->lead;
        $mongoHeading = $mongoArticle->heading;
        $mongoCategory = $mongoArticle->category;
        $mongoSubcategory = $mongoArticle->subcategory;
        $mongoSiteId = $mongoArticle->site_id;
        $mongoPublished = $mongoArticle->published;
        $mongoPublishAt = $mongoArticle->publish_at;
        $articleId = $mongoArticle->article_id;
        //get article from articles table
        $projectArticle = \DB::table(config('newsrecommendation.articles_table_name'))->where('id',$articleId)->first();
        if(isset($projectArticle) && !empty($projectArticle)){
            //get project site_id----------------------------------------
            $publishArticle = \DB::table(config('newsrecommendation.publish_table_name'))->where('article_id', $articleId)->first();
            if(!config('newsrecommendation.site_id')){
                if(config('newsrecommendation.site_id_from_public')){
                    if($publishArticle){
                        $projectSiteId = $publishArticle->site_id;
                    }else{
                        return false;
                    }
                    
                }else{
                    $website = \DB::table(config('newsrecommendation.websites_table_name'))->first();
                    $projectSiteId = $website->id;
                }
            }else{
                $projectSiteId = config('newsrecommendation.site_id');
            }

            $domainRow = \DB::table(config('newsrecommendation.websites_table_name'))->where('id',$projectSiteId)->first();
            $domain = $domainRow->url;
            //get category and subcategory--------------------------------
            if(config('newsrecommendation.use_publish')){
                if($publishArticle){
                    $categoryId = $publishArticle->category_id;
                    $subcategoryId = $publishArticle->subcategory_id;
                }else{
                    return false;
                }
               
            }else{
                $categoryId = $projectArticle->category_id;
                $subcategoryId = $projectArticle->subcategory_id;
            }
            $projectCategory = \DB::table(config('newsrecommendation.categories_table_name'))->where('id', $categoryId)->first();
            $projectCategoryName = $projectCategory->name;
            $projectSubategory = \DB::table(config('newsrecommendation.categories_table_name'))->where('id', $subcategoryId)->first();
            $projectSubcategoryName = $projectSubategory->name;

            //get other values ------------------------------------
            $projectPublished = $projectArticle->published;
            $projectPublishAt = $projectArticle->publish_at;
            $projectLead = $projectArticle->lead;
            $projectHeading = $projectArticle->heading;

            //compare values ----------------------
            //lead
            if($mongoLead != $projectLead){
                $lead = $projectLead;
            }else{
                $lead = false;
            }
            //heading
            if($mongoHeading != $projectHeading){
                $heading = $projectHeading;
            }else{
                $heading = false;
            }
            //published
            if($mongoPublished != $projectPublished){
                $published = $projectPublished;
            }else{
                $published = false;
            }
            //publish_at
            if($mongoPublishAt != $projectPublishAt){
                $publishAt = $projectPublishAt;
            }else{
                $publishAt = false;
            }
            //category
            if($mongoCategory != $projectCategoryName){
                $category = $projectCategoryName;
            }else{
                $category = false;
            }
            //subcategory
            if($mongoSubcategory != $projectSubcategoryName){
                $subcategory = $projectSubcategoryName;
            }else{
                $subcategory = false;
            }
            //site_id
            if($mongoSiteId != $projectSiteId){
                $siteId = $projectSiteId;
            }else{
                $siteId = false;
            }

            //if value exists update table
            if($siteId){
                $mongoArticle->site_id = $siteId;
            }
            if($lead){
                $mongoArticle->lead = $lead;
            }
            if($heading){
                $mongoArticle->heading = $heading;
            }
            if($category){
                $mongoArticle->category = $category;
                $mongoArticle->category_url = $domain . '/' . \Str::slug($category);
            }
            if($subcategory){
                $mongoArticle->subcategory = $subcategory;
                $mongoArticle->subcategory_url = $domain . '/' . \Str::slug($mongoArticle->category) . '/' . \Str::slug($subcategory);
            }
            if($published!==false){
                $mongoArticle->published = (int) $published;
            }
            if($publishAt){
                $mongoArticle->publish_at = $publishAt;
            }

            if($lead || $heading){
                $text = $projectArticle->text;
                $AiModel = config('newsrecommendation.ai_model');
                $client = \OpenAI::client('chat/completions',30,$AiModel);
                $answerString = $client->ask('Analiziraj tekst, uvod, naslov i kategoriju novinskog artikla i predloži 10 ključnih reči koje se odnose na glavne teme i entitete. Pokaži ih samo kao string, odvojene sa "|" , bez navodnika, bez dodatnog objašnjenja. Ovo je naslov: '.$heading.'.Ovo je uvod: '.$lead.'. Ovo je kategorija: ' . $mongoArticle->category . '. Ovo je tekst: '.$text)['content'];
                $answerArray = explode('|',strtolower($answerString));
                $answerArrayFinal = [];
                foreach($answerArray as $key => $value){
                    $answerArrayFinal[$key] = str_replace('"','',trim($value));
                }
                $mongoArticle->tags = $answerArrayFinal;
            }
         
            if($lead!==false || $heading!==false || $siteId!==false || $publishAt!==false || $published!==false || $category!==false || $subcategory!==false){
                $mongoArticle->save();
                return true;
            }

        }
    }
}
