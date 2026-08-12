<?php

namespace Tests\Feature;

use App\Models\Plan;
use App\Models\User;
use App\Models\UserPlanOverride;
use App\Support\Feature;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Semantik override kuota admin:
 *  - null / 0  -> ikuti kuota default (free plan / pool).
 *  - > 0       -> override nyata (menang).
 */
class StorageLimitOverrideTest extends TestCase
{
    use DatabaseTransactions;

    private function dosen(): User
    {
        Role::firstOrCreate(['name' => 'dosen', 'guard_name' => 'web']);
        $u = User::create([
            'name' => 'Dosen Quota',
            'email' => 'quota-'.uniqid().'@t.test',
            'password' => bcrypt('x'),
            'registration_status' => 'active',
            'nidn' => 'NIDN-'.substr(md5(uniqid()), 0, 10),
        ]);
        $u->assignRole('dosen');

        return $u;
    }

    private function defaultLimit(): int
    {
        return (int) (Plan::where('name', 'free')->first()?->storageLimitMb() ?? 0);
    }

    private function setOverride(User $user, ?int $mb): void
    {
        $ov = UserPlanOverride::where('user_id', $user->id)->first() ?? new UserPlanOverride();
        $ov->user_id = $user->id;
        $ov->storage_limit_mb = $mb;
        $ov->save();
    }

    public function test_no_override_follows_default(): void
    {
        $u = $this->dosen();
        $this->assertSame($this->defaultLimit(), Feature::storageLimitMb($u));
    }

    public function test_override_zero_follows_default(): void
    {
        $u = $this->dosen();
        $this->setOverride($u, 0);
        $this->assertSame($this->defaultLimit(), Feature::storageLimitMb($u), 'Override 0 harus mengikuti kuota default.');
    }

    public function test_override_positive_wins(): void
    {
        $u = $this->dosen();
        $this->setOverride($u, 500);
        $this->assertSame(500, Feature::storageLimitMb($u));
    }
}