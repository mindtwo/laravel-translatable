<?php

arch('it will not use debugging functions')
    ->ignoring('mindtwo\LaravelTranslatable\Nova\Fields')
    ->expect(['dd', 'dump', 'ray'])
    ->each->not->toBeUsed();
