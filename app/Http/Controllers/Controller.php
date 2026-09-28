<?php

namespace App\Http\Controllers;

use Illuminate\Support\MessageBag;
use Illuminate\Support\ViewErrorBag;

abstract class Controller
{
    /**
     * Bug gefunden (Ralf-Feature-Arbeit, 2026-09-28): eine per
     * response()->view(..., ['errors' => $validator->errors()]) übergebene
     * rohe MessageBag lässt Blades @error()-Direktive mit einem Fatal Error
     * abstürzen ("Call to undefined method MessageBag::getBag()") - @error
     * braucht zwingend eine ViewErrorBag (die normalerweise automatisch per
     * Session-Middleware bereitsteht, hier aber durch die eigene 'errors'-
     * View-Variable überschrieben wird). Dieser Helfer verpackt die
     * MessageBag korrekt, statt sie roh durchzureichen.
     */
    protected function viewErrors(MessageBag $errors): ViewErrorBag
    {
        return (new ViewErrorBag)->put('default', $errors);
    }
}
