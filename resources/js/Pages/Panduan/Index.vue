<script setup>
import { computed } from "vue";
import { Head, Link, usePage } from "@inertiajs/vue3";
import AdminLayout from "@/Layouts/AdminLayout.vue";
import PageHeader from "@/Components/Admin/PageHeader.vue";

const page = usePage();
const roleName = computed(() => page.props.auth?.roleName || "Administrator");
const currentRoleLevel = computed(() => Number(page.props.auth?.user?.role ?? 5));

const quickNavigation = [
    { label: "Alur kerja", href: "#alur-kerja", icon: "pi pi-sitemap" },
    { label: "Hak akses", href: "#hak-akses", icon: "pi pi-shield" },
    { label: "Panduan menu", href: "#panduan-menu", icon: "pi pi-book" },
    { label: "Sebelum payroll", href: "#cek-payroll", icon: "pi pi-check-square" },
    { label: "Bantuan", href: "#bantuan", icon: "pi pi-question-circle" },
];

const dailyFlow = [
    {
        title: "Periksa Dashboard",
        description: "Lihat jumlah karyawan aktif, absensi hari ini, dan kondisi payroll terakhir.",
        icon: "pi pi-home",
        tone: "bg-sky-50 text-sky-700 dark:bg-sky-500/10 dark:text-sky-300",
    },
    {
        title: "Lengkapi data karyawan",
        description: "Pastikan status, organisasi, dan komponen gaji karyawan sudah benar.",
        icon: "pi pi-users",
        tone: "bg-red-50 text-red-700 dark:bg-red-500/10 dark:text-red-300",
    },
    {
        title: "Catat absensi",
        description: "Tambahkan absensi satu per satu atau import rekap Excel satu hari.",
        icon: "pi pi-clock",
        tone: "bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300",
    },
    {
        title: "Buat dan proses payroll",
        description: "Buat periode, periksa nominal pada detail, lalu proses setelah data final.",
        icon: "pi pi-wallet",
        tone: "bg-amber-50 text-amber-700 dark:bg-amber-500/10 dark:text-amber-300",
    },
    {
        title: "Bayar dan laporkan",
        description: "Tandai payroll sudah dibayar, lalu periksa laporan atau simpan hasil cetak.",
        icon: "pi pi-chart-bar",
        tone: "bg-violet-50 text-violet-700 dark:bg-violet-500/10 dark:text-violet-300",
    },
];

const allRoleAccess = [
    {
        level: 5,
        role: "Admin",
        summary: "Untuk pekerjaan operasional harian.",
        menus: ["Dashboard", "Panduan", "Employees", "Attendance", "Payroll", "Settings"],
        tone: "border-red-200 bg-red-50/70 text-red-800 dark:border-red-500/20 dark:bg-red-500/10 dark:text-red-200",
    },
    {
        level: 6,
        role: "Senior Admin",
        summary: "Admin dengan akses laporan dan export standar.",
        menus: ["Semua menu Admin", "Reports", "Export Excel Karyawan"],
        tone: "border-sky-200 bg-sky-50/70 text-sky-800 dark:border-sky-500/20 dark:bg-sky-500/10 dark:text-sky-200",
    },
    {
        level: 7,
        role: "Super Admin",
        summary: "Mengelola akun dan template export tingkat tinggi.",
        menus: ["Semua menu Senior Admin", "Users", "Super Admin Dashboard"],
        tone: "border-amber-200 bg-amber-50/70 text-amber-800 dark:border-amber-500/20 dark:bg-amber-500/10 dark:text-amber-200",
    },
    {
        level: 8,
        role: "Developer",
        summary: "Akses tertinggi untuk pemeliharaan teknis.",
        menus: ["Semua menu Super Admin", "Developer Settings"],
        tone: "border-violet-200 bg-violet-50/70 text-violet-800 dark:border-violet-500/20 dark:bg-violet-500/10 dark:text-violet-200",
    },
];

const allModuleGuides = [
    {
        minimumRole: 5,
        id: "dashboard",
        title: "Dashboard",
        audience: "Semua role admin",
        icon: "pi pi-home",
        tone: "bg-sky-50 text-sky-700 dark:bg-sky-500/10 dark:text-sky-300",
        description: "Halaman pertama untuk melihat kondisi singkat aplikasi.",
        routeName: "admin.dashboard",
        actionLabel: "Buka Dashboard",
        steps: [
            "Periksa angka Employees dan jumlah karyawan aktif.",
            "Lihat Attendance Today untuk mengetahui kehadiran dan keterlambatan hari ini.",
            "Periksa Payroll Net dan daftar aktivitas terbaru.",
            "Gunakan Akses cepat untuk membuka menu yang dibutuhkan.",
        ],
        note: "Dashboard hanya menampilkan ringkasan. Perubahan data dilakukan dari menu masing-masing.",
    },
    {
        minimumRole: 5,
        id: "employees",
        title: "Employees",
        audience: "Admin ke atas",
        icon: "pi pi-users",
        tone: "bg-red-50 text-red-700 dark:bg-red-500/10 dark:text-red-300",
        description: "Tempat menyimpan identitas, penempatan, status, dan komponen gaji karyawan.",
        routeName: "admin.employees.index",
        actionLabel: "Buka Employees",
        steps: [
            "Klik Tambah Karyawan untuk membuat data baru.",
            "Isi data Pribadi, Identitas, Kepegawaian, lalu Payroll. Kolom bertanda merah wajib diisi.",
            "Pastikan Company, Directorate, Department, Jabatan, status karyawan, dan gaji pokok sudah benar.",
            "Klik Simpan Karyawan. Gunakan ikon pensil untuk mengubah data atau ikon tempat sampah untuk menghapus.",
            "Gunakan pencarian dan filter untuk menemukan karyawan tanpa menggulir seluruh tabel.",
        ],
        note: "Status PKWT dan PKWTT dihitung sebagai karyawan aktif saat membuat payroll. Penghapusan data karyawan bersifat permanen.",
    },
    {
        minimumRole: 5,
        id: "attendance",
        title: "Attendance",
        audience: "Admin ke atas",
        icon: "pi pi-clock",
        tone: "bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300",
        description: "Mencatat kehadiran, keterlambatan, cuti, sakit, dan lembur.",
        routeName: "admin.attendance.index",
        actionLabel: "Buka Attendance",
        steps: [
            "Pilih tanggal yang ingin diperiksa, atau gunakan tombol Hari ini.",
            "Klik Tambah Absensi untuk mencatat satu karyawan secara manual.",
            "Untuk banyak karyawan, gunakan Import Excel dengan file .xlsx rekap Attendance Logs satu hari.",
            "Setelah import, periksa jumlah NIP yang tidak cocok dan punch yang belum lengkap.",
            "Gunakan filter untuk melihat karyawan terlambat atau status tertentu.",
            "Klik ikon pensil di samping NIP untuk mulai mengoreksi punch.",
            "Klik dan tarik pegangan di samping jam punch, lalu lepaskan pada slot tujuan. Drop ke slot kosong akan memindahkan punch; drop ke slot terisi akan menukar kedua punch.",
            "Klik ikon tempat sampah pada punch tertentu untuk menghapusnya dalam satu klik. Gunakan Undo pada notifikasi jika penghapusan keliru.",
        ],
        note: "File import maksimal 5 MB. NIP pada Excel harus sama dengan data karyawan. Drag hanya berlaku dalam baris yang sama. Tombol Hapus Harian menghapus seluruh data pada tanggal terpilih, jadi periksa tanggal sebelum menggunakannya.",
    },
    {
        minimumRole: 5,
        id: "payroll",
        title: "Payroll",
        audience: "Admin ke atas",
        icon: "pi pi-wallet",
        tone: "bg-amber-50 text-amber-700 dark:bg-amber-500/10 dark:text-amber-300",
        description: "Membuat periode penggajian, menghitung gaji, dan mencatat pembayaran.",
        routeName: "admin.payroll.index",
        actionLabel: "Buka Payroll",
        steps: [
            "Klik Buat periode setelah memastikan data karyawan aktif sudah lengkap.",
            "Isi nama periode, tanggal mulai, tanggal selesai, tanggal bayar, dan catatan bila diperlukan.",
            "Klik Buat dan hitung. Sistem akan menghitung gaji untuk seluruh karyawan aktif.",
            "Buka detail periode dan periksa Gross, Deductions, Net, serta nilai setiap karyawan.",
            "Jika sudah benar, klik Proses payroll. Setelah pembayaran dilakukan, klik Tandai dibayar.",
        ],
        note: "Periode berstatus draft dapat dihapus. Setelah diproses, gunakan status pembayaran dengan hati-hati karena tidak ada tombol pembatalan otomatis.",
    },
    {
        minimumRole: 6,
        id: "reports",
        title: "Reports",
        audience: "Senior Admin ke atas",
        icon: "pi pi-chart-bar",
        tone: "bg-sky-50 text-sky-700 dark:bg-sky-500/10 dark:text-sky-300",
        description: "Ringkasan headcount, kehadiran, payroll, department, dan directorate.",
        routeName: "admin.reports.index",
        actionLabel: "Buka Reports",
        steps: [
            "Buka Reports setelah data karyawan, absensi, atau payroll diperbarui.",
            "Baca kartu ringkasan untuk melihat headcount, attendance rate, payroll net, dan rata-rata gaji.",
            "Periksa komposisi status karyawan serta jumlah karyawan per department dan directorate.",
            "Gunakan halaman ini sebagai bahan pemeriksaan sebelum rapat atau pelaporan internal.",
        ],
        note: "Halaman Reports bersifat ringkasan dan tidak mengubah data. Admin biasa tidak memiliki akses ke menu ini.",
    },
    {
        minimumRole: 5,
        id: "settings",
        title: "Settings",
        audience: "Admin ke atas",
        icon: "pi pi-cog",
        tone: "bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-200",
        description: "Mengatur identitas organisasi serta aturan dasar payroll dan absensi.",
        routeName: "admin.settings.index",
        actionLabel: "Buka Settings",
        steps: [
            "Periksa Company name, bahasa, dan zona waktu.",
            "Periksa tanggal awal dan akhir cutoff payroll serta tanggal pembayaran default.",
            "Atur jam masuk dan toleransi keterlambatan absensi.",
            "Klik Simpan pengaturan dan pastikan muncul pesan berhasil.",
        ],
        note: "Ubah pengaturan bersama secara hati-hati karena nilai ini memengaruhi proses dan tampilan aplikasi.",
    },
    {
        minimumRole: 7,
        id: "users",
        title: "Users",
        audience: "Super Admin ke atas",
        icon: "pi pi-user-edit",
        tone: "bg-amber-50 text-amber-700 dark:bg-amber-500/10 dark:text-amber-300",
        description: "Membuat dan mengelola akun yang dapat login ke aplikasi.",
        routeName: "admin.users.index",
        actionLabel: "Buka Users",
        steps: [
            "Klik Tambah User.",
            "Isi nama lengkap, email, username opsional, role, bahasa, dan password.",
            "Pilih role minimal Admin agar akun memiliki akses aplikasi payroll.",
            "Klik Tambah user. Email akun baru otomatis dianggap terverifikasi.",
            "Gunakan ikon pensil untuk mengubah data atau mengatur ulang password.",
        ],
        note: "Role Supervisor dan Manager tidak dapat login ke aplikasi ini. Akun yang sedang digunakan tidak dapat menghapus dirinya sendiri, dan Developer terakhir tidak dapat dihapus.",
    },
    {
        minimumRole: 7,
        id: "super-admin",
        title: "Super Admin Dashboard",
        audience: "Super Admin ke atas",
        icon: "pi pi-shield",
        tone: "bg-violet-50 text-violet-700 dark:bg-violet-500/10 dark:text-violet-300",
        description: "Memeriksa kesiapan data dan mengunduh template salary adjustment.",
        routeName: "admin.super-admin-dashboard.index",
        actionLabel: "Buka Super Admin",
        steps: [
            "Periksa jumlah karyawan eligible dan data Directorate, Department, serta gaji pokok yang masih kosong.",
            "Lengkapi data yang ditandai belum lengkap melalui menu Employees.",
            "Pilih Template Department Excel atau Template Directorate Excel sesuai kebutuhan.",
            "Klik Download template. File berupa ZIP berisi workbook XLSX.",
        ],
        note: "Template hanya memasukkan karyawan berstatus PKWT dan PKWTT.",
    },
    {
        minimumRole: 8,
        id: "developer-settings",
        title: "Developer Settings",
        audience: "Developer only",
        icon: "pi pi-code",
        tone: "bg-violet-50 text-violet-700 dark:bg-violet-500/10 dark:text-violet-300",
        description: "Utilitas internal untuk migrasi, referensi master, kalender kerja, dan pembersihan data.",
        routeName: "admin.developer-settings.index",
        actionLabel: "Buka Developer Settings",
        steps: [
            "Periksa database source dan target sebelum menjalankan Copy Karyawan.",
            "Tinjau Field Mapping dan Data Quality, lalu centang Mapping sudah ditinjau sebelum menyalin.",
            "Gunakan Ganti 5 tabel hanya jika seluruh master data target memang akan diganti.",
            "Atur Batas Sensor Gaji Admin agar data payroll di atas batas tidak terlihat oleh Admin.",
            "Gunakan panduan Jadwal Kerja Developer dan Pengecualian Kalender di bawah untuk mengubah jadwal periode khusus.",
            "Gunakan Bersihkan Presensi hanya setelah memastikan bulan dan cakupan data yang akan dihapus.",
        ],
        note: "Menu ini hanya untuk Developer. Copy Karyawan dan Ganti 5 tabel dapat menghapus data target secara permanen, termasuk data terkait. Pastikan backup sebelum menjalankan proses.",
    },
    {
        minimumRole: 8,
        id: "developer-work-schedule",
        title: "Jadwal Kerja Developer",
        audience: "Developer only",
        icon: "pi pi-calendar-clock",
        tone: "bg-sky-50 text-sky-700 dark:bg-sky-500/10 dark:text-sky-300",
        description: "Mengubah jam kerja untuk periode tertentu seperti Ramadan, baik dimajukan maupun dimundurkan.",
        routeName: "admin.developer-settings.index",
        actionLabel: "Buka Jadwal Kerja",
        steps: [
            "Buka Developer Settings, lalu pilih menu Jadwal Kerja.",
            "Klik Atur jadwal serentak untuk mengubah beberapa profile dan shift dalam satu periode.",
            "Pilih periode yang sudah ada atau isi nama, tanggal mulai, dan tanggal selesai.",
            "Centang shift yang berubah, lalu sesuaikan jam masuk, jam keluar, akhir shift, jam reguler, dan jumlah punch.",
            "Klik Simpan dan hitung ulang. Seluruh presensi pada rentang tanggal tersebut akan dihitung ulang.",
            "Untuk menghapus override, hilangkan centang dari semua shift lalu simpan kembali.",
            "Gunakan Tambah jadwal jika perubahan hanya berlaku untuk profile, tipe hari, atau company tertentu.",
        ],
        note: "Jadwal serentak berlaku untuk semua hari dan seluruh organisasi. Isi jam reguler sesuai jam kerja bersih setelah istirahat. Perubahan pada periode seperti Ramadan dapat dimajukan atau dimundurkan tanpa mengubah jadwal normal.",
    },
    {
        minimumRole: 8,
        id: "developer-calendar-exceptions",
        title: "Pengecualian Kalender",
        audience: "Developer only",
        icon: "pi pi-calendar",
        tone: "bg-rose-50 text-rose-700 dark:bg-rose-500/10 dark:text-rose-300",
        description: "Mengatur hari libur nasional dan tanggal khusus yang berbeda untuk company tertentu.",
        routeName: "admin.developer-settings.index",
        actionLabel: "Buka Pengecualian Kalender",
        steps: [
            "Gunakan menu Hari Libur untuk menambahkan libur nasional yang berlaku global.",
            "Buka menu Pengecualian Kalender untuk tanggal yang berbeda pada company tertentu.",
            "Klik Tambah pengecualian, lalu pilih satu atau beberapa company.",
            "Isi tanggal dan pilih perlakuan Hari kerja biasa atau Libur dengan jam kerja x2.",
            "Pilih Dasar tipe hari bila tanggal tersebut harus mengikuti jadwal Jumat atau Sabtu tertentu.",
            "Klik Simpan. Presensi yang sudah ada pada tanggal dan company terkait akan dihitung ulang.",
            "Gunakan ikon pensil atau tempat sampah untuk memperbaiki atau menghapus aturan yang salah.",
        ],
        note: "Pengecualian company lebih spesifik daripada libur nasional. Jika pengecualian ditetapkan sebagai hari kerja, presensi tidak dihitung sebagai kerja libur meskipun tanggal tersebut libur nasional.",
    },
    {
        minimumRole: 5,
        id: "akun",
        title: "Profil & Password",
        audience: "Semua role admin",
        icon: "pi pi-key",
        tone: "bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300",
        description: "Mengubah identitas akun dan menjaga keamanan password.",
        routeName: "profile.edit",
        actionLabel: "Buka Profil",
        steps: [
            "Klik nama akun di pojok kanan atas, lalu pilih Profile.",
            "Ubah nama atau email pada Informasi akun, kemudian klik Simpan profil.",
            "Untuk mengganti password, isi password saat ini, password baru, dan konfirmasi password.",
            "Jika lupa password, keluar ke halaman login dan klik Forgot password.",
            "Masukkan email akun yang terdaftar, lalu buka tautan reset dari email.",
        ],
        note: "Tautan reset password berlaku selama 60 menit. Jika email tidak masuk, periksa folder Spam atau hubungi pengelola aplikasi.",
    },
];

const roleAccess = computed(() => allRoleAccess.filter(
    (role) => role.level <= currentRoleLevel.value,
));

const moduleGuides = computed(() => allModuleGuides.filter(
    (guide) => guide.minimumRole <= currentRoleLevel.value,
));

const roleGridClass = computed(() => ({
    1: "xl:grid-cols-1",
    2: "xl:grid-cols-2",
    3: "xl:grid-cols-3",
    4: "xl:grid-cols-4",
}[roleAccess.value.length] ?? "xl:grid-cols-4"));

const payrollChecklist = [
    "Data karyawan baru sudah disimpan dan statusnya PKWT atau PKWTT.",
    "Department, jabatan, dan rekening karyawan sudah benar.",
    "Gaji pokok, tunjangan, potongan, dan BPJS sudah diperiksa.",
    "Absensi periode terkait sudah diimport atau dicatat.",
    "NIP yang tidak cocok dan punch yang belum lengkap sudah diselesaikan.",
    "Periode, tanggal bayar, dan total payroll sudah sesuai sebelum diproses.",
];

const helpItems = [
    {
        title: "Tidak bisa masuk aplikasi",
        description: "Pastikan email dan password benar. Role Supervisor dan Manager memang tidak memiliki akses. Gunakan Forgot password bila lupa password.",
        icon: "pi pi-lock",
    },
    {
        title: "Tombol Buat periode tidak aktif",
        description: "Pastikan sudah ada karyawan aktif berstatus PKWT atau PKWTT. Lengkapi data karyawan terlebih dahulu.",
        icon: "pi pi-exclamation-circle",
    },
    {
        title: "NIP absensi tidak cocok",
        description: "Periksa kembali NIP pada file Excel dan samakan dengan NIP pada menu Employees. Data nama dan department diambil dari aplikasi.",
        icon: "pi pi-file-excel",
    },
    {
        title: "Data gaji tidak bisa diedit",
        description: "Admin hanya dapat mengubah data payroll sampai batas gaji yang diizinkan. Hubungi Super Admin atau Developer bila data perlu dibuka.",
        icon: "pi pi-wallet",
    },
];
</script>

<template>
    <AdminLayout>
        <Head title="Panduan" />

        <div class="space-y-6">
            <PageHeader
                title="Panduan"
                description="Petunjuk praktis yang disesuaikan dengan menu dan tanggung jawab role akun Anda."
                eyebrow="Manual pengguna"
            >
                <template #actions>
                    <Tag severity="info" class="h-10 px-3.5">
                        <i class="pi pi-user mr-2 text-xs" />
                        {{ roleName }}
                    </Tag>
                </template>
            </PageHeader>

            <section class="relative overflow-hidden rounded-[2rem] bg-slate-950 p-6 text-white shadow-xl shadow-slate-950/10 dark:border dark:border-slate-800 sm:p-8">
                <div class="pointer-events-none absolute -right-24 -top-32 h-80 w-80 rounded-full bg-red-500/20 blur-3xl" />
                <div class="pointer-events-none absolute -bottom-40 left-1/3 h-80 w-80 rounded-full bg-sky-500/10 blur-3xl" />
                <div class="relative grid gap-8 xl:grid-cols-[1.15fr_0.85fr] xl:items-end">
                    <div>
                        <span class="inline-flex items-center gap-2 rounded-full border border-white/10 bg-white/5 px-3 py-1 text-[10px] font-bold uppercase tracking-[0.16em] text-slate-300">
                            <i class="pi pi-compass text-[10px]" />
                            Mulai dari sini
                        </span>
                        <h2 class="mt-4 max-w-2xl text-2xl font-extrabold tracking-[-0.035em] sm:text-3xl">
                            Kerjakan payroll dengan urutan yang sama setiap periode.
                        </h2>
                        <p class="mt-3 max-w-2xl text-sm leading-6 text-slate-300">
                            Panduan ini ditampilkan untuk <strong class="font-semibold text-white">{{ roleName }}</strong>. Bagian role di atas level akun Anda tidak ditampilkan. Ikuti alur di bawah untuk pekerjaan rutin atau buka panduan menu yang tersedia.
                        </p>
                    </div>
                    <nav class="flex flex-wrap gap-2 xl:justify-end" aria-label="Navigasi cepat panduan">
                        <a
                            v-for="item in quickNavigation"
                            :key="item.href"
                            :href="item.href"
                            class="inline-flex items-center gap-2 rounded-xl border border-white/10 bg-white/5 px-3 py-2 text-xs font-semibold text-slate-200 transition hover:border-white/20 hover:bg-white/10 hover:text-white"
                        >
                            <i :class="item.icon" class="text-[10px]" />
                            {{ item.label }}
                        </a>
                    </nav>
                </div>
            </section>

            <section id="alur-kerja" class="scroll-mt-24">
                <div class="mb-4 flex items-end justify-between gap-4">
                    <div>
                        <h2 class="text-lg font-bold tracking-tight text-slate-950 dark:text-white">Alur kerja yang disarankan</h2>
                        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Ikuti urutan ini agar data absensi dan payroll tidak saling tertinggal.</p>
                    </div>
                    <Tag severity="secondary" class="hidden sm:inline-flex">5 langkah utama</Tag>
                </div>
                <ol class="grid gap-4 md:grid-cols-2 xl:grid-cols-5">
                    <li
                        v-for="(step, index) in dailyFlow"
                        :key="step.title"
                        class="relative overflow-hidden rounded-2xl border border-slate-200/80 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900"
                    >
                        <span class="absolute right-4 top-4 text-3xl font-black text-slate-100 dark:text-slate-800">{{ String(index + 1).padStart(2, "0") }}</span>
                        <span class="relative flex h-11 w-11 items-center justify-center rounded-xl" :class="step.tone">
                            <i :class="step.icon" />
                        </span>
                        <h3 class="relative mt-4 text-sm font-bold text-slate-950 dark:text-white">{{ step.title }}</h3>
                        <p class="relative mt-2 text-xs leading-5 text-slate-500 dark:text-slate-400">{{ step.description }}</p>
                    </li>
                </ol>
            </section>

            <section id="hak-akses" class="scroll-mt-24">
                <div class="mb-4">
                    <h2 class="text-lg font-bold tracking-tight text-slate-950 dark:text-white">Akses sesuai level {{ roleName }}</h2>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Role Anda dan role di bawahnya ditampilkan untuk membantu memahami alur kerja.</p>
                </div>
                <div class="grid gap-4 md:grid-cols-2" :class="roleGridClass">
                    <article
                        v-for="role in roleAccess"
                        :key="role.role"
                        class="rounded-2xl border p-5"
                        :class="role.tone"
                    >
                        <div class="flex items-center justify-between gap-3">
                            <h3 class="font-bold">{{ role.role }}</h3>
                            <i class="pi pi-shield text-sm opacity-60" />
                        </div>
                        <p class="mt-2 text-xs leading-5 opacity-80">{{ role.summary }}</p>
                        <ul class="mt-4 space-y-2">
                            <li v-for="menu in role.menus" :key="menu" class="flex items-start gap-2 text-xs font-medium">
                                <i class="pi pi-check-circle mt-0.5 text-[10px]" />
                                <span>{{ menu }}</span>
                            </li>
                        </ul>
                    </article>
                </div>
            </section>

            <section id="panduan-menu" class="scroll-mt-24">
                <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <h2 class="text-lg font-bold tracking-tight text-slate-950 dark:text-white">Panduan per menu</h2>
                        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Klik menu terkait jika ingin langsung membuka halamannya.</p>
                    </div>
                    <Message severity="info" :closable="false" class="max-w-xl">
                        Bagian ini hanya memuat menu yang dapat diakses sampai level {{ roleName }}. Panduan role di atasnya tidak ditampilkan.
                    </Message>
                </div>
                <div class="grid gap-5 xl:grid-cols-2">
                    <article
                        v-for="guide in moduleGuides"
                        :id="guide.id"
                        :key="guide.id"
                        class="scroll-mt-24 rounded-2xl border border-slate-200/80 bg-white p-5 shadow-sm dark:border-slate-800 dark:bg-slate-900 sm:p-6"
                    >
                        <div class="flex items-start justify-between gap-4">
                            <div class="flex min-w-0 items-start gap-3">
                                <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl" :class="guide.tone">
                                    <i :class="guide.icon" />
                                </span>
                                <div class="min-w-0">
                                    <h3 class="text-base font-bold text-slate-950 dark:text-white">{{ guide.title }}</h3>
                                    <p class="mt-1 text-xs font-medium text-slate-400">{{ guide.audience }}</p>
                                </div>
                            </div>
                            <Tag severity="secondary" class="hidden shrink-0 sm:inline-flex">Panduan</Tag>
                        </div>
                        <p class="mt-4 text-sm leading-6 text-slate-600 dark:text-slate-300">{{ guide.description }}</p>
                        <ol class="mt-5 space-y-3">
                            <li
                                v-for="(step, index) in guide.steps"
                                :key="step"
                                class="flex items-start gap-3 rounded-xl border border-slate-100 bg-slate-50/70 p-3 dark:border-slate-800 dark:bg-slate-950/30"
                            >
                                <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-white text-[10px] font-bold text-slate-500 shadow-sm dark:bg-slate-900 dark:text-slate-300">
                                    {{ index + 1 }}
                                </span>
                                <span class="text-xs leading-5 text-slate-600 dark:text-slate-300">{{ step }}</span>
                            </li>
                        </ol>
                        <Message v-if="guide.note" severity="warn" :closable="false" class="mt-4">
                            {{ guide.note }}
                        </Message>
                        <Link :href="route(guide.routeName)" class="mt-5 inline-flex">
                            <Button :label="guide.actionLabel" :icon="guide.icon" severity="secondary" outlined />
                        </Link>
                    </article>
                </div>
            </section>

            <section id="cek-payroll" class="scroll-mt-24">
                <div class="grid gap-6 xl:grid-cols-[1.1fr_0.9fr]">
                    <Card class="border border-slate-200/80 bg-white dark:border-slate-800 dark:bg-slate-900">
                        <template #title>
                            <div class="px-1">
                                <h2 class="text-base font-bold text-slate-950 dark:text-white">Periksa sebelum proses payroll</h2>
                                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Pastikan semua daftar berikut sudah benar.</p>
                            </div>
                        </template>
                        <template #content>
                            <ul class="grid gap-3 sm:grid-cols-2">
                                <li
                                    v-for="item in payrollChecklist"
                                    :key="item"
                                    class="flex items-start gap-3 rounded-xl border border-slate-200/80 p-3 dark:border-slate-800"
                                >
                                    <span class="mt-0.5 flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300">
                                        <i class="pi pi-check text-[10px]" />
                                    </span>
                                    <span class="text-xs leading-5 text-slate-600 dark:text-slate-300">{{ item }}</span>
                                </li>
                            </ul>
                        </template>
                    </Card>

                    <Card class="border border-amber-200 bg-amber-50/70 dark:border-amber-500/20 dark:bg-amber-500/10">
                        <template #title>
                            <div class="flex items-start gap-3 px-1">
                                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-amber-100 text-amber-700 dark:bg-amber-500/15 dark:text-amber-300">
                                    <i class="pi pi-exclamation-triangle" />
                                </span>
                                <div>
                                    <h2 class="text-base font-bold text-amber-950 dark:text-amber-100">Tindakan yang perlu hati-hati</h2>
                                    <p class="mt-1 text-xs text-amber-700 dark:text-amber-300">Beberapa tindakan langsung mengubah data.</p>
                                </div>
                            </div>
                        </template>
                        <template #content>
                            <div class="space-y-3 text-sm leading-6 text-amber-800 dark:text-amber-200">
                                <p><strong>Hapus Harian:</strong> menghapus absensi pada tanggal yang sedang dipilih.</p>
                                <p><strong>Hapus Karyawan:</strong> menghapus data karyawan secara permanen.</p>
                                <p><strong>Proses payroll:</strong> gunakan setelah nilai gaji dipastikan final.</p>
                                <p><strong>Tandai dibayar:</strong> gunakan setelah pembayaran benar-benar dilakukan.</p>
                            </div>
                        </template>
                    </Card>
                </div>
            </section>

            <section id="bantuan" class="scroll-mt-24">
                <div class="mb-4">
                    <h2 class="text-lg font-bold tracking-tight text-slate-950 dark:text-white">Bantuan cepat</h2>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Masalah yang paling sering terjadi dan langkah pertama untuk menyelesaikannya.</p>
                </div>
                <div class="grid gap-4 md:grid-cols-2">
                    <article
                        v-for="item in helpItems"
                        :key="item.title"
                        class="flex gap-4 rounded-2xl border border-slate-200/80 bg-white p-5 dark:border-slate-800 dark:bg-slate-900"
                    >
                        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300">
                            <i :class="item.icon" />
                        </span>
                        <div>
                            <h3 class="text-sm font-bold text-slate-950 dark:text-white">{{ item.title }}</h3>
                            <p class="mt-1 text-xs leading-5 text-slate-500 dark:text-slate-400">{{ item.description }}</p>
                        </div>
                    </article>
                </div>
            </section>
        </div>
    </AdminLayout>
</template>
