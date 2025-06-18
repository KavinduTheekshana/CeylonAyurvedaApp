<?php

namespace App\Filament\Resources\ContactMessageResource\Widgets;

use Filament\Widgets\ChartWidget;

class ContactMessagesChart extends ChartWidget
{
    protected static ?string $heading = 'Chart';

    protected function getData(): array
    {
        return [
            //
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
