<?php

/*
| Architecture rules that keep the codebase consistent as it grows.
*/

arch('no debugging statements are left behind')
    ->expect(['dd', 'dump', 'ray', 'var_dump', 'die', 'print_r'])
    ->not->toBeUsed();

arch('controllers are thin and suffixed')
    ->expect('App\Http\Controllers')
    ->toHaveSuffix('Controller')
    ->not->toUse(['Illuminate\Support\Facades\Process']);

arch('models extend Eloquent')
    ->expect('App\Models')
    ->toExtend('Illuminate\Database\Eloquent\Model');

arch('policies are suffixed and only used for authorization')
    ->expect('App\Policies')
    ->toHaveSuffix('Policy')
    ->not->toUse(['Illuminate\Http\Request', 'Illuminate\Support\Facades\DB']);

arch('actions expose a handle method or are domain services')
    ->expect('App\Actions\Lessons')
    ->toHaveMethod('handle');

arch('enums are backed string enums')
    ->expect('App\Enums')
    ->toBeStringBackedEnums();

arch('form requests are suffixed')
    ->expect('App\Http\Requests')
    ->toHaveSuffix('Request')
    ->toExtend('Illuminate\Foundation\Http\FormRequest');

arch('jobs are queued')
    ->expect('App\Jobs')
    ->toImplement('Illuminate\Contracts\Queue\ShouldQueue');

arch('the domain does not depend on HTTP controllers')
    ->expect(['App\Models', 'App\Actions', 'App\Services', 'App\Jobs'])
    ->not->toUse('App\Http\Controllers');

arch('env() is only called from config files')
    ->expect('env')
    ->toOnlyBeUsedIn('config');

arch()->preset()->security()->ignoring(['md5']);
