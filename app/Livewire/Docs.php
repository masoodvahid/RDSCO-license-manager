<?php

namespace App\Livewire;

use App\Services\Licensing\SigningKey;
use App\Services\Shahkar\ShahkarResponseCodes;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('مستندات API')]
class Docs extends Component
{
    public function render(SigningKey $signingKey, ShahkarResponseCodes $codes)
    {
        return view('livewire.docs', [
            'baseUrl' => rtrim((string) config('app.url'), '/'),
            'publicKey' => $signingKey->publicKey(),
            'shahkarCodes' => $codes->documentedResponses(),
            'graceHours' => (int) config('license.offline_grace_hours'),
        ]);
    }
}
