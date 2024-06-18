<?php

arch('globals dd')
    ->expect('mindtwo\LaravelTranslatable\Traits')
    ->not->toUse('dd');

arch('globals dump')
    ->expect('mindtwo\LaravelTranslatable\Traits')
    ->not->toUse('dump');

arch('globals ray')
    ->expect('mindtwo\LaravelTranslatable\Traits')
    ->not->toUse('ray');

arch('globals dd 2')
    ->expect('mindtwo\LaravelTranslatable\Models')
    ->not->toUse('dd');

arch('globals dump 2')
    ->expect('mindtwo\LaravelTranslatable\Models')
    ->not->toUse('dump');

arch('globals ray 2')
    ->expect('mindtwo\LaravelTranslatable\Models')
    ->not->toUse('ray');
