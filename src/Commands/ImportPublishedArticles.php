<?php

namespace Hoks\NewsRecommendation\Commands;

use Illuminate\Console\Command;
use Hoks\NewsRecommendation\Models\ArticleMongo;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;


class ImportPublishedArticles extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'articles:import-in-mongodb';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import all published articles in mongodb databse';

    public function handle()
    {

        $all_articles = \DB::table(config('newsrecommendation.articles_table_name'))->where('published', 1)->where('deleted_at', null)->where('publish_at', '>=', Carbon::now()->subDays(config('newsrecommendation.days_ago')))->orderBy('publish_at', 'desc')->get();

        foreach($all_articles as $article) {
            try{
                $dataNormalization = $this->dataNormalization($article);
                if($dataNormalization){
                    $entity = new ArticleMongo();
                    $entity->fill($dataNormalization);
                    $entity->save();
                }
            }catch(\Exception $e){
                Log::info('FIRST_IMPORT',[$e->getMessage()]);
            }
            
        }

    }

    public function dataNormalization($data) {

        $finalData = [];

        if(!config('newsrecommendation.site_id')){
            if(config('newsrecommendation.site_id_from_public')){
                $publishSite = \DB::table(config('newsrecommendation.publish_table_name'))->where('article_id', $data->id)->first();
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
            $article = \DB::table(config('newsrecommendation.publish_table_name'))->where('article_id', $data->id)->first();
            if(isset($article) && !empty($article)) {
                $category = \DB::table(config('newsrecommendation.categories_table_name'))->where('id', $article->category_id)->first();
                $categoryName = $category->name;
                $categoryUrl = $domain . '/' . \Str::slug($categoryName);

                $subcategory = \DB::table(config('newsrecommendation.categories_table_name'))->where('id', $article->subcategory_id)->first();
                $subcategoryName = $subcategory->name;
                $subcategoryUrl = $domain . '/' . \Str::slug($categoryName) . '/' . \Str::slug($subcategoryName);
            }
        } else {
            $category = \DB::table(config('newsrecommendation.categories_table_name'))->where('id', $data->category_id)->first();
            if(isset($category) && !empty($category)) {
                $categoryName = $category->name;
                $categoryUrl = $domain . '/' . \Str::slug($categoryName);
            }

            $subcategory = \DB::table(config('newsrecommendation.categories_table_name'))->where('id', $data->subcategory_id)->first();
            if(isset($subcategory) && !empty($subcategory)) {
                $subcategoryName = $subcategory->name;
                $subcategoryUrl = $domain . '/' . \Str::slug($categoryName) . '/' . \Str::slug($subcategoryName);
            }
        }
        $articleUrl = $domain . '/' . \Str::slug($categoryName) . '/' . \Str::slug($subcategoryName) . '/' . $data->id . '/' . \Str::slug(!empty($data->og_title) ? $data->og_title : $data->heading) . '/' . config('newsrecommendation.article_trailing_string');
        //get salt from config
        $salt = config('newsrecommendation.url_salt');
        $urlWithSalt = $salt . '|' . $articleUrl;
        //encod URL useing hash_hmac
        $encodedUrl = base64_encode($urlWithSalt);

        foreach(config('newsrecommendation.required_fields') as $column) {

            if($column == 'tags') {
                $text = $data->text;
                $text = strip_tags($text);
                $heading = $data->heading;
                $lead = $data->lead;
                $AiModel = config('newsrecommendation.ai_model');
                $client = \OpenAI::client('chat/completions',30,$AiModel);
                $answerString = $client->ask('Analiziraj tekst, uvod, naslov i kategoriju novinskog artikla i predloži 10 ključnih reči koje se odnose na glavne teme i entitete. Pokaži ih samo kao string, odvojene sa "|" , bez navodnika, bez dodatnog objašnjenja. Ovo je naslov: '.$heading.'.Ovo je uvod: '.$lead.'. Ovo je kategorija: ' . $categoryName . '. Ovo je tekst: '.$text)['content'];
                $answerArray = explode('|',strtolower($answerString));
                $answerArrayFinal = [];
                foreach($answerArray as $key => $value){
                    $answerArrayFinal[$key] = str_replace('"','',trim($value));
                }
                $finalData[$column] = $answerArrayFinal;
            }elseif($column == 'article_id') {
                $finalData[$column] = $data->id;
            }elseif($column == 'category') {
                $finalData[$column] = $categoryName;
            }elseif($column == 'subcategory') {
                $finalData[$column] = $subcategoryName;
            }elseif($column == 'category_url') {
                $finalData[$column] = $categoryUrl;
            }elseif($column == 'subcategory_url') {
                $finalData[$column] = $subcategoryUrl;
            }elseif($column == 'article_url') {
                $finalData[$column] = $encodedUrl;
            }elseif($column == 'publish_at') {
                $finalData[$column] = Carbon::parse($data->publish_at)->format('Y-m-d H:i:s');
                if(strtotime(($finalData[$column])) === false){
                    Log::info('MRK_1',$data->toArray());
                }
            }else {
                if($column == 'site_id'){
                    if(config('newsrecommendation.site_id')){
                        $finalData[$column] = config('newsrecommendation.site_id');
                    }else{
                        if(config('newsrecommendation.site_id_from_public')){
                            $publishSite = \DB::table(config('newsrecommendation.publish_table_name'))->where('article_id', $data->id)->first();
                            if($publishSite){
                                $website = \DB::table(config('newsrecommendation.websites_table_name'))->where('id', $publishSite->site_id)->first();
                                $finalData[$column] = $website->id;
                            }
                        }else{
                            $website = \DB::table(config('newsrecommendation.websites_table_name'))->first();
                            $finalData[$column] = $website->id;

                        }
                    }
                }else{
                    $finalData[$column] = $data->$column;

                }
            }

        }

        return $finalData;

    }

}
