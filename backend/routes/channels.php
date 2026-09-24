<?php

use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
| Channel privat kasir - hanya user yang terhubung ke outlet tsb (via
| outlet_id di profilnya) yang boleh subscribe. Lihat 02-SDD.md §4.6.
| Channel publik (table.{qr_code_token}) TIDAK perlu didaftarkan di sini
| karena tidak butuh otorisasi - siapapun dengan link meja boleh listen.
*/
Broadcast::channel('outlet.{outletId}.cashier', function (User $user, string $outletId) {
    return (string) $user->outlet_id === $outletId;
});
