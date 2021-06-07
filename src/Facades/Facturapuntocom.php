<?php

namespace Importaremx\Facturapuntocom\Facades;

use Illuminate\Support\Facades\Facade;

class Facturapuntocom extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor(): string
    {
        return 'facturapuntocom';
    }
}
