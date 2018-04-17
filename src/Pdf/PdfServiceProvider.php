<?php

namespace Sztyup\Pdf;

use Illuminate\Support\ServiceProvider;

class PdfServiceProvider extends ServiceProvider
{
    public function boot()
    {
    }

    public function register()
    {
        $this->mergeConfigFrom(__DIR__.'/../config/dompdf.php', 'dompdf');
        $this->mergeConfigFrom(__DIR__.'/../config/config.php', 'pdf');
    }
}
