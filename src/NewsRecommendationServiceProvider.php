<?php

namespace Hoks\NewsRecommendation;

use Illuminate\Support\ServiceProvider;
use Hoks\NewsRecommendation\OpenAI;
use Hoks\NewsRecommendation\Commands\ImportPublishedArticles;
use Hoks\NewsRecommendation\Commands\ImportPublishedArticlesPeriodicaly;
use Hoks\NewsRecommendation\Observers\ArticleMongoObserver;
use Hoks\NewsRecommendation\Commands\ProcessReaders;

class NewsRecommendationServiceProvider extends ServiceProvider{

    public function boot(){
        $this->publishes([
            __DIR__.'/config/newsrecommendation.php' => config_path('newsrecommendation.php')
        ],'config');
        if ($this->app->runningInConsole()) {
            $this->commands([
                ImportPublishedArticles::class,
                ImportPublishedArticlesPeriodicaly::class,
                ProcessReaders::class
            ]);
        }
        
        $className = config('newsrecommendation.article_model');
        if (class_exists($className)) { 
            $className::observe(ArticleMongoObserver::class); 
        }
    }

    public function register(){
        $this->app->bind('OpenAI',function(){
            return new OpenAI();
        });
        $this->mergeConfigFrom(__DIR__.'/config/newsrecommendation.php', 'newsrecommendation');
    }
}
