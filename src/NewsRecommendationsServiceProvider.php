<?php

namespace Hoks\NewsRecommendations;

use Illuminate\Support\ServiceProvider;
use Hoks\NewsRecommendations\OpenAI;
use Hoks\NewsRecommendations\Commands\ImportPublishedArticles;
use Hoks\NewsRecommendations\Commands\ImportPublishedArticlesPeriodicaly;

class NewsRecommendationsServiceProvider extends ServiceProvider{

    public function boot(){
        $this->publishes([
            __DIR__.'/config/newsrecommendations.php' => config_path('newsrecommendations.php')
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
        $this->mergeConfigFrom(__DIR__.'/config/newsrecommendations.php', 'newsrecommendations');
    }
}
