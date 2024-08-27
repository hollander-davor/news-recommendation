<?php

return [
    //api key for openai
    'openai-api-key' => '',
    //required fields for saveing articles
    'required_fields' => [
        'article_id',
        'site_id',
        'preheading',
        'heading',
        'og_title',
        'lead',
        'category',
        'subcategory',
        'article_url',
        'category_url',
        'subcategory_url',
        'tags',
        'published',
        'publish_at',
        'time_created_real',
        'time_updated_real',
        'views',
        'image_orig',
        'image_t',
        'image_f',
        'image_l',
        'image_ig',
        'image_s',
        'image_iff',
        'image_xs',
        'image_kf',
        'image_kfl',
        'image_m',
        'image_pl'
    ],
    //image dimensions
    'image_dimensions' => [
        'image_orig',
        'image_t',
        'image_f',
        'image_l',
        'image_ig',
        'image_s',
        'image_iff',
        'image_xs',
        'image_kf',
        'image_kfl',
        'image_m',
        'image_pl'
    ],
    //how many days ago do we take the articles
    'days_ago' => 10,
    //table name for articles
    'articles_table_name' => 'articles',
    //table name for categories
    'categories_table_name' => 'categories',
    //table name for websites
    'websites_table_name' => 'websites',
    //table name for publish
    'publish_table_name' => 'publish',
   //article model
   'article_model' => \App\Models\Article::class,
   //for how many days we wait for user to visit website again before we delete it
   'days_delete_user' => 7,
   //for how many days we keep data
   'days_keep_data' => 7
];
