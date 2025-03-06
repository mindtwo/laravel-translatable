<?php

arch('globals')
    ->expect('mindtwo\LaravelTranslatable')
    ->not->toUse(['dd', 'dump', 'ray']);
