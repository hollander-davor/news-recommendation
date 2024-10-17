<?php

namespace Hoks\NewsRecommendation\Commands;

use Illuminate\Console\Command;
use Hoks\NewsRecommendation\Models\UserMongo;

class RemoveOldUsers extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'old-users:remove';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Remove old users';

   

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $allUsers = UserMongo::get();
        $daysDeleteUser = config('newsrecommendation.days_delete_user');
        $dayToDeleteUser = now()->subDays($daysDeleteUser)->format('d-m-Y');
        foreach($allUsers as $user){
            if($user->latest_update <= $dayToDeleteUser){
                $user->delete();
            }
        }
    }
}
