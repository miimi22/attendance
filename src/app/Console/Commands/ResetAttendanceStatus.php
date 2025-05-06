<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class ResetAttendanceStatus extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'user:reset-status';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Reset attendance_status for all users to OFF_WORK (0) at midnight';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
       Log::info('Batch process started: Resetting attendance status for all users.');

        $updatedCount = User::where('attendance_status', '!=', User::STATUS_OFF_WORK)
                            ->orWhereNull('attendance_status')
                            ->update(['attendance_status' => User::STATUS_OFF_WORK]);

        /*
        $updatedCount = 0;
        User::where('attendance_status', '!=', User::STATUS_OFF_WORK)
            ->orWhereNull('attendance_status')
            ->chunkById(200, function ($users) use (&$updatedCount) {
                foreach ($users as $user) {
                    $user->attendance_status = User::STATUS_OFF_WORK;
                    $user->save();
                    $updatedCount++;
                }
            });
        */

        $message = "Batch process finished: {$updatedCount} users' attendance status have been reset to OFF_WORK(0).";
        Log::info($message);
        $this->info($message);

        return Command::SUCCESS; 
    }
}
