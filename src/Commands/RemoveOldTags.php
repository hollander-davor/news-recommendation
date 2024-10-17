<?php

namespace Hoks\NewsRecommendation\Commands;

use Illuminate\Console\Command;
use Hoks\NewsRecommendation\Models\UserMongo;
use Illuminate\Support\Carbon;

class RemoveOldTags extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'old-tags:remove';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Remove old tags';


    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $daysKeepData = config('newsrecommendation.days_keep_data');
        $dayToDeleteTag = now()->subDays($daysKeepData)->format('d-m-Y');
        $parsedDateToDelete = Carbon::parse($dayToDeleteTag);
        
        $allUsers = UserMongo::get();
        foreach($allUsers as $user){
            $userTags = $user->tags;
            $userTagsNew = $user->tags;

            foreach($userTags as $key => $userTag){
                $parsedKeyDate = Carbon::parse($key);
                if($parsedKeyDate <= $parsedDateToDelete){
                    unset($userTagsNew[$key]);
                }
            }
            $user->tags = $userTagsNew;
            $user->save();
        }
    }
}
