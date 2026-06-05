<?php

namespace App\Livewire;

use Livewire\Component;

class TestCropper extends Component
{
    public $photoBase64 = null;

    public function render()
    {
        return view('livewire.test-cropper');
    }
}
