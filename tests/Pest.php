<?php

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| Closures de testes Pest no diretório Feature herdam o TestCase da
| aplicação (Vite desligado, bootstrap Laravel). Classes PHPUnit
| existentes continuam independentes.
|
*/

uses(Tests\TestCase::class)->in('Feature');
