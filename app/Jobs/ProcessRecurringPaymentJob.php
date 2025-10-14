<?php
namespace App\Jobs;
use App\Models\RecurringInformation;

class ProcessRecurringPaymentJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $recurringPayment;

    public function __construct(RecurringInformation $recurringPayment)
    {
        $this->recurringPayment = $recurringPayment;
    }

    public function handle()
    {
        // Background job logic e.g., schedule next payment, notify user, etc.
        Log::info("Processing recurring schedule ID: " . $this->recurringPayment->id);
    }
}
