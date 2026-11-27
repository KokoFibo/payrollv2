<?php

use App\Enums\KaryawanStatus;
use App\Enums\PayrollMethod;
use App\Models\Company;
use App\Models\Department;
use App\Models\Directorate;
use App\Models\Etnis;
use App\Models\Jabatan;
use App\Models\JobGrade;
use App\Models\Karyawan;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

function validEmployeePayload(array $overrides = []): array
{
    return array_merge([
        'nama' => 'Budi Santoso',
        'email' => 'budi@example.com',
        'hp' => '081234567890',
        'telepon' => '021555000',
        'tempat_lahir' => 'Jakarta',
        'tanggal_lahir' => '1995-05-05',
        'gender' => 'Laki-laki',
        'status_pernikahan' => 'Menikah',
        'golongan_darah' => 'O',
        'agama' => 'Islam',
        'etnis_id' => Etnis::factory()->create(['name' => 'Jawa'])->id,
        'jenis_identitas' => 'KTP',
        'no_identitas' => '3273010505950001',
        'alamat_identitas' => 'Jl. Merdeka No. 1',
        'alamat_tinggal_sekarang' => 'Jl. Sudirman No. 10',
        'status_karyawan' => KaryawanStatus::Pkwt->value,
        'tanggal_bergabung' => '2024-01-10',
        'tanggal_resigned' => null,
        'tanggal_blacklist' => null,
        'company_id' => Company::factory()->create(['name' => 'PT Contoh'])->id,
        'directorate_id' => Directorate::factory()->create(['name' => 'Finance Center'])->id,
        'department_id' => Department::factory()->create(['name' => 'Payroll'])->id,
        'jabatan_id' => Jabatan::factory()->create(['name' => 'Payroll Officer'])->id,
        'job_grade_id' => JobGrade::factory()->create(['grade' => 'M5', 'grade_name' => 'Manager'])->id,
        'nama_bank' => 'BCA',
        'nomor_rekening' => '1234567890',
        'metode_penggajian' => PayrollMethod::Monthly->value,
        'gaji_pokok' => 5_000_000,
        'gaji_tetap' => 750_000,
        'gaji_overtime' => 500_000,
        'gaji_shift_malam_satpam' => 0,
        'bonus' => 0,
        'tunjangan_jabatan' => 0,
        'tunjangan_bahasa' => 0,
        'tunjangan_skill' => 0,
        'tunjangan_lembur_sabtu' => 0,
        'tunjangan_lama_kerja' => 0,
        'tunjangan_housing' => 400_000,
        'iuran_air' => 50_000,
        'denda' => 0,
        'iuran_locker' => 25_000,
        'gaji_bpjs' => 250_000,
        'potongan_jht' => true,
        'potongan_jp' => true,
        'potongan_jkk' => false,
        'potongan_jkm' => false,
        'potongan_kesehatan' => true,
        'no_npwp' => '123456789',
        'ptkp' => 'K1',
        'nama_contact_darurat' => 'Ibu Budi',
        'nama_contact_darurat_2' => 'Saudara Budi',
        'contact_darurat_1' => '081111111111',
        'contact_darurat_2' => '082222222222',
        'hubungan_1' => 'Orang tua',
        'hubungan_2' => 'Saudara',
        'tanggungan' => 1,
        'pendidikan' => 'S1',
        'jurusan' => 'Akuntansi',
        'nama_kampus' => 'Universitas Indonesia',
    ], $overrides);
}

test('renders the employee table for an admin', function () {
    $admin = User::factory()->create(['role' => 5]);
    Karyawan::factory()->create([
        'nama' => 'Special Employee',
        'status_karyawan' => KaryawanStatus::Pkwt->value,
        'gaji_pokok' => 4_500_000,
    ]);

    $this->actingAs($admin)
        ->get('/admin/employees')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Employees/Index')
            ->has('karyawans.data', 1)
            ->where('karyawans.data.0.nama', 'Special Employee')
            ->where('karyawans.data.0.metode_penggajian_label', 'Monthly')
            ->where('filters.status', 'active'));
});

test('employee pages reject users below admin', function () {
    $user = User::factory()->create(['role' => 4]);

    $this->actingAs($user)->get('/admin/employees')->assertForbidden();
});

test('employee routes resolve by public uuid instead of primary id', function () {
    $admin = User::factory()->create(['role' => 5]);
    $employee = Karyawan::factory()->create([
        'gaji_pokok' => 4_500_000,
        'status_pernikahan' => 'Kawin',
    ]);

    $this->actingAs($admin)
        ->get('/admin/employees/'.$employee->id.'/edit')
        ->assertNotFound();

    $this->actingAs($admin)
        ->get('/admin/employees/'.$employee->uuid.'/edit')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Employees/Form')
            ->where('employee.id', $employee->id)
            ->where('employee.uuid', $employee->uuid)
            ->where('employee.status_pernikahan', 'Kawin')
            ->where('employee.metode_penggajian', PayrollMethod::Monthly->value)
            ->where('options.maritalStatuses.3.value', 'Kawin')
            ->where('options.payrollMethods.0.value', PayrollMethod::Hourly->value)
            ->where('options.payrollMethods.0.label', 'Hourly')
            ->where('options.payrollMethods.1.value', PayrollMethod::Monthly->value)
            ->where('options.payrollMethods.1.label', 'Monthly'));
});

test('an admin can create and update an employee', function () {
    $admin = User::factory()->create(['role' => 6]);
    $payload = validEmployeePayload();

    $this->actingAs($admin)
        ->post('/admin/employees', $payload)
        ->assertRedirect(route('admin.employees.index'));

    $karyawan = Karyawan::query()->firstOrFail();

    expect($karyawan->nip)->toBe(1)
        ->and(Str::isUuid($karyawan->uuid))->toBeTrue()
        ->and($karyawan->outsource->value)->toBe('OS')
        ->and($karyawan->metode_penggajian)->toBe(PayrollMethod::Monthly)
        ->and($karyawan->gaji_pokok)->toBe(5_000_000)
        ->and($karyawan->tunjangan_housing)->toBe(400_000);

    expect($karyawan->only([
        'alamat_tinggal_sekarang',
        'nama_contact_darurat',
        'nama_contact_darurat_2',
        'contact_darurat_1',
        'contact_darurat_2',
        'hubungan_1',
        'hubungan_2',
    ]))->toBe([
        'alamat_tinggal_sekarang' => 'Jl. Sudirman No. 10',
        'nama_contact_darurat' => 'Ibu Budi',
        'nama_contact_darurat_2' => 'Saudara Budi',
        'contact_darurat_1' => '081111111111',
        'contact_darurat_2' => '082222222222',
        'hubungan_1' => 'Orang tua',
        'hubungan_2' => 'Saudara',
    ]);

    $rawKaryawan = DB::table('karyawans')->where('id', $karyawan->id)->first();

    expect($rawKaryawan->no_identitas)->not->toBe('3273010505950001')
        ->and($rawKaryawan->nomor_rekening)->not->toBe('1234567890')
        ->and($rawKaryawan->no_npwp)->not->toBe('123456789')
        ->and($rawKaryawan->nomor_rekening_hash)->toBe(karyawan_blind_index('1234567890'));

    expect((int) $rawKaryawan->nip)->toBe(1)
        ->and((string) $rawKaryawan->nip)->toBe('1');

    expect($karyawan->getRouteKeyName())->toBe('uuid')
        ->and(route('admin.employees.edit', $karyawan))
        ->toContain($karyawan->uuid);

    $payload['nama'] = 'Budi Santoso Updated';
    $payload['gaji_pokok'] = 6_000_000;
    $payload['status_pernikahan'] = 'Kawin';
    $payload['metode_penggajian'] = PayrollMethod::Hourly->value;

    $this->actingAs($admin)
        ->put(route('admin.employees.update', $karyawan), $payload)
        ->assertRedirect(route('admin.employees.index'));

    expect($karyawan->refresh()->nama)->toBe('Budi Santoso Updated')
        ->and($karyawan->gaji_pokok)->toBe(6_000_000)
        ->and($karyawan->metode_penggajian)->toBe(PayrollMethod::Hourly)
        ->and($karyawan->status_pernikahan)->toBe('Kawin');
});

test('an admin can delete an employee', function () {
    $admin = User::factory()->create(['role' => 6]);
    $karyawan = Karyawan::factory()->create();

    $this->actingAs($admin)
        ->from(route('admin.employees.index'))
        ->delete(route('admin.employees.destroy', $karyawan))
        ->assertRedirect(route('admin.employees.index'));

    $this->assertModelMissing($karyawan);
});

test('employee export downloads the filtered rows', function () {
    $admin = User::factory()->create(['role' => 6]);
    Karyawan::factory()->create(['nama' => 'Filtered Export Employee']);

    $response = $this->actingAs($admin)
        ->get(route('admin.employees.export', ['search' => 'Filtered Export']));

    $response->assertOk();
    expect($response->headers->get('content-type'))
        ->toContain('spreadsheetml.sheet');
});

test('employee export writes readable payroll method labels', function () {
    $admin = User::factory()->create(['role' => 6]);
    Karyawan::factory()->create([
        'nama' => 'Hourly Export Employee',
        'status_karyawan' => KaryawanStatus::Pkwt->value,
        'metode_penggajian' => PayrollMethod::Hourly->value,
    ]);

    $response = $this->actingAs($admin)
        ->get(route('admin.employees.export', ['search' => 'Hourly Export']));

    $response->assertOk();

    $path = $response->baseResponse->getFile()->getPathname();
    $archive = new ZipArchive;

    expect($archive->open($path))->toBeTrue();

    $worksheet = $archive->getFromName('xl/worksheets/sheet1.xml');
    $archive->close();
    File::delete($path);

    expect($worksheet)
        ->toContain('Hourly Export Employee')
        ->toContain('Hourly');
});

test('employee validation rejects unavailable reference values', function () {
    $admin = User::factory()->create(['role' => 5]);
    $payload = validEmployeePayload([
        'etnis_id' => 999999,
        'pendidikan' => 'SMA',
        'ptkp' => 'INVALID',
        'metode_penggajian' => 'Perbulan',
    ]);

    $this->actingAs($admin)
        ->from(route('admin.employees.create'))
        ->post('/admin/employees', $payload)
        ->assertSessionHasErrors(['etnis_id', 'pendidikan', 'ptkp', 'metode_penggajian']);
});

test('employee bank account must remain unique through the blind index', function () {
    $admin = User::factory()->create(['role' => 5]);
    Karyawan::factory()->create(['nomor_rekening' => '1234567890']);

    $this->actingAs($admin)
        ->from(route('admin.employees.create'))
        ->post('/admin/employees', validEmployeePayload([
            'email' => 'different@example.com',
            'nomor_rekening' => '1234567890',
        ]))
        ->assertSessionHasErrors('nomor_rekening');
});

test('employee email cannot collide with an application user', function () {
    $admin = User::factory()->create(['role' => 5]);
    User::factory()->create(['email' => 'application@example.com']);

    $this->actingAs($admin)
        ->from(route('admin.employees.create'))
        ->post('/admin/employees', validEmployeePayload([
            'email' => 'application@example.com',
        ]))
        ->assertSessionHasErrors('email');
});
