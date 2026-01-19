<?php

namespace AltDesign\AltRedirect\Events;

use AltDesign\AltRedirect\Models\Redirect;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class RedirectCreated
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

	public Redirect $redirect;

	/**
     * Create a new event instance.
     */
    public function __construct(Redirect $redirect)
    {
        $this->redirect = $redirect;
    }
}
