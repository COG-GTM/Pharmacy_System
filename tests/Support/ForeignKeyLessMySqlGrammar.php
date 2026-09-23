<?php

namespace Tests\Support;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Grammars\MySqlGrammar;
use Illuminate\Support\Fluent;

/**
 * Schema grammar that skips foreign key creation.
 *
 * Several migrations declare foreign keys before the table they reference is
 * created, so the schema can only be built when the constraints are dropped.
 */
class ForeignKeyLessMySqlGrammar extends MySqlGrammar
{
    public function compileForeign(Blueprint $blueprint, Fluent $command)
    {
        return null;
    }

    public function compileDropForeign(Blueprint $blueprint, Fluent $command)
    {
        return null;
    }
}
