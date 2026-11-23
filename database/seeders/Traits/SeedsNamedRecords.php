<?php

namespace Database\Seeders\Traits;

use Illuminate\Database\Eloquent\Model;

trait SeedsNamedRecords
{
    /**
     * @param  class-string<Model>  $modelClass
     * @param  array<int, string|null>  $records
     */
    protected function seedNamedRecords(string $modelClass, array $records): void
    {
        foreach ($records as $id => $name) {
            $modelClass::query()->updateOrCreate(['id' => $id], ['name' => $name]);
        }
    }
}
