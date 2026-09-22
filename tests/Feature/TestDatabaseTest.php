<?php

use Illuminate\Support\Facades\DB;

// SQLite has no SELECT ... FOR UPDATE, and the working database is what the Concurrency suite would truncate.
test('the suite runs against the dedicated MySQL test database', function () {
    expect(DB::connection()->getDriverName())->toBe('mysql')
        ->and(DB::connection()->getDatabaseName())->toBe('getid_test');
});
