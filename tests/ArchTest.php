<?php

arch('it will not use debugging functions')
    ->excludePaths('config', 'routes')
    ->ignoring('mindtwo\LaravelTranslatable\Nova\Fields')
    ->each->not->toBeUsed();
