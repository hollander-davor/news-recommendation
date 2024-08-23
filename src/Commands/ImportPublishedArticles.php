<?php

namespace Hoks\NewsRecommendations\Commands;

use Illuminate\Console\Command;
use Hoks\NewsRecommendations\Models\ArticleMongo;
use Carbon\Carbon;

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

        $all_articles = \DB::table(config('newsrecommendations.articles_table_name'))->where('published', 1)->where('deleted_at', null)->where('publish_at', '>=', Carbon::now()->subDays(config('newsrecommendations.days_ago')))->get();

        foreach($all_articles as $article) {
            $dataNormalization = $this->dataNormalization($article);
            $entity = new ArticleMongo();

            $entity->create($dataNormalization);
        }

    }

    public function dataNormalization($data) {

        $finalData = [];

        $website = \DB::table(config('newsrecommendations.websites_table_name'))->where('id', $data->site_id)->first();
        $domain = $website->url;

        if(config('newsrecommendations.use_publish')) {
            $article = \DB::table(config('newsrecommendations.publish_table_name'))->where('article_id', $data->id)->first();
            if(isset($article) && !empty($article)) {
                $category = \DB::table(config('newsrecommendations.categories_table_name'))->where('id', $article->category_id)->first();
                $categoryName = $category->name;
                $categoryUrl = $domain . '/' . \Str::slug($categoryName);

                $subcategory = \DB::table(config('newsrecommendations.categories_table_name'))->where('subcategory_id', $article->subcategory_id)->first();
                $subcategoryName = $subcategory->name;
                $subcategoryUrl = $domain . '/' . \Str::slug($categoryName) . '/' . \Str::slug($subcategoryName);
            }
        } else {
            $category = \DB::table(config('newsrecommendations.categories_table_name'))->where('id', $data->category_id)->first();
            if(isset($category) && !empty($category)) {
                $categoryName = $category->name;
                $categoryUrl = $domain . '/' . \Str::slug($categoryName);
            }

            $subcategory = \DB::table(config('newsrecommendations.categories_table_name'))->where('id', $data->subcategory_id)->first();
            if(isset($subcategory) && !empty($subcategory)) {
                $subcategoryName = $subcategory->name;
                $subcategoryUrl = $domain . '/' . \Str::slug($categoryName) . '/' . \Str::slug($subcategoryName);
            }
        }
        $articleUrl = $domain . '/' . \Str::slug($categoryName) . '/' . \Str::slug($subcategoryName) . '/' . $data->id . '/' . \Str::slug(!empty($data->og_title) ? $data->og_title : $data->heading) .'/vest';


        foreach(config('newsrecommendations.required_fields') as $column) {

            if($column == 'tags') {
                $text = $data->text;
                $text = strip_tags($text);
                $heading = $data->heading;
                $lead = $data->lead;
                $client = \OpenAI::client('chat/completions');
                $answerString = $client->ask('Analiziraj tekst, uvod, naslov i kategoriju novinskog artikla i predloži 10 ključnih reči koje se odnose na glavne teme i entitete. Pokaži ih samo kao string, odvojene sa "|" bez dodatnog objašnjenja. Ovo je naslov: '.$heading.'.Ovo je uvod: '.$lead.'. Ovo je kategorija: ' . $categoryName . '. Ovo je tekst: '.$text)['content'];
                $answerArray = explode('|',strtolower($answerString));
                $finalData[$column] = $answerArray;
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
                $finalData[$column] = $articleUrl;
            }else {
                $finalData[$column] = $data->$column;
            }

        }

        return $finalData;

    }

}
