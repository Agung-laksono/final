<?php

namespace App\Http\Livewire;

use Livewire\Component;
use App\Events\InventoryUpdated;

class PusherTest extends Component
{
    public $testMessage = '';
    public $log = [];
    protected $listeners = ['addLog' => 'addLog'];

    public function mount()
    {
        // No extra initialization needed; $log already empty
    }

    public function sendTest()
    {
        $message = 'Tes Pusher dari Livewire pada ' . now()->toDateTimeString();
        InventoryUpdated::dispatch($message);
        $this->testMessage = $message;
    }

    // This method will be called via JS event
    public function addLog($msg)
    {
        $this->log[] = $msg;
    }

    public function render()
    {
        return view('livewire.pusher-test');
    }
}
