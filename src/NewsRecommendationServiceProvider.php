<?php

namespace Hoks\NewsRecommendation;

use Illuminate\Support\ServiceProvider;
use Hoks\NewsRecommendation\OpenAI;
use Hoks\NewsRecommendation\Commands\ImportPublishedArticles;
use Hoks\NewsRecommendation\Commands\ImportPublishedArticlesPeriodicaly;

class NewsRecommendationServiceProvider extends ServiceProvider{

    public function boot(){
        $this->publishes([
            __DIR__.'/config/newsrecommendation.php' => config_path('newsrecommendation.php')
        ],'config');
        if ($this->app->runningInConsole()) {
            $this->commands([
                ImportPublishedArticles::class,
                ImportPublishedArticlesPeriodicaly::class
            ]);
        }
    }

    public function register(){
        $this->app->bind('OpenAI',function(){
            return new OpenAI();
        });
        $this->mergeConfigFrom(__DIR__.'/config/newsrecommendation.php', 'newsrecommendation');
    }
}
