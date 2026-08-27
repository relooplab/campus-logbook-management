<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Faculty;
use App\Models\MahasiswaTa;
use App\Models\StudyProgram;
use App\Models\University;
use App\Models\User;
use App\Services\OrganizationalDirectoryService;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * Mekanisme kelompok KP:
 *  1. Dosen pembimbing dapat menggabungkan mahasiswa dari program KP terpisah
 *     (program lama dinonaktifkan, tanpa migrasi data).
 *  2. Kandidat yang sudah menjadi anggota kelompok KP lain ditolak.
 *  3. Pemilik kelompok dapat menambah/menghapus anggota (fitur "tambah teman").
 *  4. Anggota yang digabung otomatis melihat program yang sama.
 */
class KpGroupMemberTest extends TestCase
{
    use DatabaseTransactions;

    private User $dosen;
    private User $dosenLain;
    private User $owner;
    private User $teman;
    private MahasiswaTa $groupKp;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(ValidateCsrfToken::class);

        foreach (['dosen', 'mahasiswa'] as $role) {
            Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']);
        }

        $make = function (array $attr, string $role) {
            $u = User::create(array_merge([
                'password' => bcrypt('x'),
                'registration_status' => 'active',
                'whatsapp' => '628',
            ], $attr));
            $u->assignRole($role);

            return $u;
        };

        $this->dosen = $make(['name' => 'Pembimbing', 'email' => 'pemb-'.uniqid().'@t.test', 'nidn' => 'NIDN-'.substr(md5(uniqid()), 0, 12)], 'dosen');
        $this->dosenLain = $make(['name' => 'Dosen Lain', 'email' => 'dosen-'.uniqid().'@t.test', 'nidn' => 'NIDN-'.substr(md5(uniqid()), 0, 12)], 'dosen');
        $this->owner = $make(['name' => 'Owner KP', 'email' => 'owner-'.uniqid().'@t.test', 'nim' => 'NIM-'.uniqid()], 'mahasiswa');
        $this->teman = $make(['name' => 'Teman KP', 'email' => 'teman-'.uniqid().'@t.test', 'nim' => 'NIM-'.uniqid()], 'mahasiswa');

        $this->groupKp = MahasiswaTa::create([
            'user_id' => $this->owner->id,
            'jenis' => MahasiswaTa::JENIS_KP,
            'tempat_kp' => 'PT. Teknologi Nusantara',
            'pembimbing_1_id' => $this->dosen->id,
            'target_sesi' => 7,
            'status_ta' => MahasiswaTa::STATUS_AKTIF,
            'fase' => 'pelaksanaan',
        ]);
    }

    private function makeKp(User $user, ?User $pembimbing = null): MahasiswaTa
    {
        return MahasiswaTa::create([
            'user_id' => $user->id,
            'jenis' => MahasiswaTa::JENIS_KP,
            'tempat_kp' => 'PT. Lain',
            'pembimbing_1_id' => $pembimbing?->id ?? $this->dosen->id,
            'target_sesi' => 7,
            'status_ta' => MahasiswaTa::STATUS_AKTIF,
            'fase' => 'pelaksanaan',
        ]);
    }

    public function test_dosen_pembimbing_dapat_menggabungkan_mahasiswa_dengan_program_terpisah(): void
    {
        // Teman punya program KP terpisah (kasus produksi yang harus disatukan).
        $kpLama = $this->makeKp($this->teman);

        $this->actingAs($this->dosen)
            ->post(route('mahasiswa-kp.gabung', $this->groupKp), ['user_id' => $this->teman->id])
            ->assertRedirect();

        // Teman menjadi anggota kelompok ini.
        $this->assertTrue($this->groupKp->members()->whereKey($this->teman->id)->exists());

        // Program lama otomatis dinonaktifkan (status berubah), data tetap ada.
        $kpLama->refresh();
        $this->assertSame(MahasiswaTa::STATUS_NONAKTIF, $kpLama->status_ta);
        $this->assertDatabaseHas('mahasiswa_ta', ['id' => $kpLama->id]);

        // Teman melihat program yang sama via allPrograms.
        $programIds = $this->teman->allPrograms()->pluck('id');
        $this->assertTrue($programIds->contains($this->groupKp->id));
    }

    public function test_dosen_yang_bukan_pembimbing_tidak_bisa_menggabungkan(): void
    {
        $this->actingAs($this->dosenLain)
            ->post(route('mahasiswa-kp.gabung', $this->groupKp), ['user_id' => $this->teman->id])
            ->assertForbidden();

        $this->assertFalse($this->groupKp->members()->whereKey($this->teman->id)->exists());
    }

    public function test_kandidat_yang_sudah_anggota_kelompok_lain_ditolak(): void
    {
        $pemilikLain = User::create([
            'name' => 'Pemilik Lain', 'email' => 'pl-'.uniqid().'@t.test', 'password' => bcrypt('x'),
            'registration_status' => 'active', 'whatsapp' => '628', 'nim' => 'NIM-'.uniqid(),
        ]);
        $pemilikLain->assignRole('mahasiswa');
        $groupLain = $this->makeKp($pemilikLain);
        $groupLain->members()->attach($this->teman->id);

        // Coba jadikan teman anggota kelompok ini (oleh pemilik).
        $this->actingAs($this->owner)
            ->post(route('profile.kp.add-member', $this->groupKp), ['user_id' => $this->teman->id])
            ->assertSessionHas('error');

        $this->assertFalse($this->groupKp->members()->whereKey($this->teman->id)->exists());

        // Dan dosen pembimbing juga ditolak.
        $this->actingAs($this->dosen)
            ->post(route('mahasiswa-kp.gabung', $this->groupKp), ['user_id' => $this->teman->id])
            ->assertSessionHas('error');
    }

    public function test_pemilik_kelompok_dapat_menambah_dan_menghapus_anggota(): void
    {
        // Tambah teman.
        $this->actingAs($this->owner)
            ->post(route('profile.kp.add-member', $this->groupKp), ['user_id' => $this->teman->id])
            ->assertRedirect(route('profile.profil-akademik'));

        $this->assertTrue($this->groupKp->members()->whereKey($this->teman->id)->exists());

        // Anggota bukan pemilik tidak boleh menambah anggota lain.
        $orangLain = User::create([
            'name' => 'Orang Lain', 'email' => 'lain-'.uniqid().'@t.test', 'password' => bcrypt('x'),
            'registration_status' => 'active', 'whatsapp' => '628', 'nim' => 'NIM-'.uniqid(),
        ]);
        $orangLain->assignRole('mahasiswa');

        $this->actingAs($this->teman)
            ->post(route('profile.kp.add-member', $this->groupKp), ['user_id' => $orangLain->id])
            ->assertForbidden();

        // Pemilik menghapus anggota.
        $this->actingAs($this->owner)
            ->delete(route('profile.kp.remove-member', [$this->groupKp, $this->teman]))
            ->assertRedirect(route('profile.profil-akademik'));

        $this->assertFalse($this->groupKp->members()->whereKey($this->teman->id)->exists());
    }

    public function test_pemilik_yang_digabung_ke_kelompok_lain_program_lama_dinonaktifkan(): void
    {
        // Merge: pemilik program ini (owner) dijadikan anggota kelompok lain.
        // Diizinkan (aturan satu-program-KP lewat deaktivasi), program lamanya dinonaktifkan.
        $groupLain = $this->makeKp($this->teman);

        $this->actingAs($this->teman)
            ->post(route('profile.kp.add-member', $groupLain), ['user_id' => $this->owner->id])
            ->assertRedirect(route('profile.profil-akademik'));

        $this->assertTrue($groupLain->members()->whereKey($this->owner->id)->exists());

        $this->groupKp->refresh();
        $this->assertSame(MahasiswaTa::STATUS_NONAKTIF, $this->groupKp->status_ta);
    }

    public function test_select_dosen_menampilkan_pemilih_anggota_untuk_kp(): void
    {
        $pemula = $this->makeUser('pemula', 'NIM-PEMULA');
        $this->saveAffiliationSite([$pemula]);

        $this->actingAs($pemula)
            ->get(route('profile.select-dosen'))
            ->assertOk()
            ->assertSee('Anggota Kelompok')
            ->assertSee($this->teman->name);
    }

    public function test_halaman_dosen_dan_profil_akademik_terender_dengan_anggota(): void
    {
        // Halaman detail KP (view dosen) menampilkan form gabung — hanya untuk pembimbing.
        $this->actingAs($this->dosen)
            ->get(route('mahasiswa-kp.show', $this->groupKp))
            ->assertOk()
            ->assertSee('Gabung mahasiswa');

        // Halaman profil akademik (view pemilik) menampilkan seksi anggota + form tambah.
        $this->actingAs($this->owner)
            ->get(route('profile.profil-akademik'))
            ->assertOk()
            ->assertSee('Anggota Kelompok')
            ->assertSee('Tambah teman ke kelompok');
    }

    public function test_store_dosen_kp_membuat_anggota_kelompok_di_awal(): void
    {
        $pemula = $this->makeUser('pemula', 'NIM-PEMULA');
        $this->saveAffiliationSite([$pemula]);

        $this->actingAs($pemula)
            ->post(route('profile.store-dosen'), [
                'jenis' => MahasiswaTa::JENIS_KP,
                'fase' => 'pelaksanaan',
                'pembimbing_1_id' => $this->dosen->id,
                'member_ids' => [$this->teman->id],
            ])
            ->assertRedirect(route('dashboard'));

        $kpBaru = MahasiswaTa::where('user_id', $pemula->id)->where('jenis', MahasiswaTa::JENIS_KP)->first();
        $this->assertNotNull($kpBaru);
        $this->assertSame(MahasiswaTa::STATUS_PENDING_APPROVAL, $kpBaru->status_ta);
        $this->assertTrue($kpBaru->members()->whereKey($this->teman->id)->exists());

        // Teman langsung melihat program yang sama.
        $this->assertTrue($this->teman->allPrograms()->pluck('id')->contains($kpBaru->id));
    }

    private function makeUser(string $label, string $nim): User
    {
        $u = User::create([
            'name' => ucfirst($label).'-'.uniqid(),
            'email' => $label.'-'.uniqid().'@t.test',
            'password' => bcrypt('x'),
            'registration_status' => 'active',
            'whatsapp' => '628',
            'nim' => $nim.'-'.uniqid(),
        ]);
        $u->assignRole('mahasiswa');

        return $u;
    }

    /**
     * Siapkan afiliasi perguruan tinggi (sampai prodi) untuk dosen, teman,
     * dan daftar mahasiswa tambahan, agar alur "Pilih Dosen" dapat diakses.
     */
    private function saveAffiliationSite(array $extraStudents = []): void
    {
        $univ = University::create(['name' => 'Universitas Test KP']);
        $fac = Faculty::create(['university_id' => $univ->id, 'name' => 'FT']);
        $dept = Department::create(['faculty_id' => $fac->id, 'name' => 'Informatika']);
        $prodi = StudyProgram::create(['department_id' => $dept->id, 'name' => 'S1 IF', 'code' => '55201']);

        $svc = app(OrganizationalDirectoryService::class);
        $svc->attachUserToUniversity($this->dosen, $univ, $fac, $dept, $prodi, isPrimary: true);
        $svc->attachUserToUniversity($this->teman, $univ, $fac, $dept, $prodi, isPrimary: true);

        foreach ($extraStudents as $student) {
            $svc->attachUserToUniversity($student, $univ, $fac, $dept, $prodi, isPrimary: true);
        }
    }
}