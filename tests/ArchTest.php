<?php

arch('it will not use debugging functions dd')
    ->expect('dd')
    ->not->toBeUsed()
    ->ignoring('mindtwo\LaravelTranslatable\Nova\Fields');

arch('it will not use debugging functions dump')
    ->expect('dump')
    ->not->toBeUsed()
    ->ignoring('mindtwo\LaravelTranslatable\Nova\Fields');

arch('it will not use debugging functions ray')
    ->expect('ray')
    ->not->toBeUsed()
    ->ignoring('mindtwo\LaravelTranslatable\Nova\Fields');
