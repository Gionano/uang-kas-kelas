# Aplikasi Manajemen Uang Kas

Aplikasi web sederhana yang dibangun dengan PHP native untuk mengelola dan memantau keuangan uang kas untuk sebuah grup atau kelas. Aplikasi ini dirancang untuk memudahkan bendahara dalam mencatat pemasukan, mengelola pengeluaran, dan melacak status pembayaran dari setiap anggota (siswa).

## Fitur Utama

*   **Manajemen Pengguna & Hak Akses:**
    *   Sistem login dengan 3 level hak akses: **Administrator**, **Bendahara**, dan **Guru**.
    *   **Administrator**: Memiliki akses penuh ke semua fitur, termasuk manajemen pengguna dan data master.
    *   **Bendahara**: Dapat mengelola data pembayaran siswa dan mencatat pengeluaran kas.
    *   **Guru**: Hanya dapat melihat data dan laporan pembayaran tanpa bisa mengubahnya.

*   **Manajemen Pembayaran Fleksibel:**
    *   Pembayaran diorganisir per bulan dan tahun.
    *   Pencatatan pembayaran dilakukan per minggu (4 minggu dalam satu bulan).
    *   Kemampuan untuk mengatur nominal pembayaran per minggu yang berbeda untuk setiap bulannya.
    *   Fitur untuk menandai minggu tertentu sebagai **libur**, sehingga tidak ada tagihan pada minggu tersebut.

*   **Logika Bisnis Otomatis:**
    *   **Penyesuaian Otomatis**: Jika bendahara mengubah status sebuah minggu menjadi libur (atau sebaliknya), pembayaran yang sudah tercatat akan secara otomatis disesuaikan dan didistribusikan ulang ke minggu-minggu aktif.
    *   **Rollover Dana**: Kelebihan pembayaran pada satu bulan akan otomatis dialihkan sebagai pembayaran di awal bulan berikutnya.
    *   **Validasi Tunggakan**: Sistem secara otomatis mendeteksi dan menandai siswa yang memiliki tunggakan dari bulan sebelumnya untuk memudahkan penagihan.
    *   Pembayaran harus dilakukan secara berurutan dari minggu ke-1 hingga ke-4.

*   **Manajemen Siswa & Pengeluaran:**
    *   Fitur CRUD (Create, Read, Update, Delete) untuk data siswa.
    *   Pencatatan semua transaksi pengeluaran yang diambil dari uang kas beserta keterangannya.

*   **Riwayat & Pelaporan:**
    *   Setiap perubahan data pembayaran dan pengeluaran dicatat dalam **riwayat (log)** untuk transparansi dan audit.
    *   Tampilan detail pembayaran per bulan yang informatif, lengkap dengan status lunas/belum lunas/libur.

## Tumpukan Teknologi

*   **Backend**: PHP (Native)
*   **Database**: MySQL / MariaDB
*   **Frontend**:
    *   HTML, CSS, JavaScript
    *   Bootstrap 4
    *   jQuery
*   **Library**:
    *   [DataTables](https://datatables.net/): Untuk tabel yang interaktif dengan fitur pencarian dan paginasi.
    *   [SweetAlert2](https://sweetalert2.github.io/): Untuk notifikasi dan dialog yang modern.
    *   [FancyBox](https://fancyapps.com/fancybox/3/): Untuk menampilkan gambar atau konten modal.

## Struktur Database

Aplikasi ini menggunakan beberapa tabel utama untuk menyimpan data:

*   `siswa`: Menyimpan data profil siswa.
*   `user` & `jabatan`: Mengelola akun pengguna dan hak aksesnya.
*   `bulan_pembayaran`: Mengatur periode pembayaran, termasuk nominal per minggu dan status hari libur.
*   `uang_kas`: Tabel transaksi utama yang mencatat pembayaran setiap siswa untuk setiap bulan.
*   `pengeluaran`: Mencatat semua transaksi pengeluaran kas.
*   `riwayat` & `riwayat_pengeluaran`: Berfungsi sebagai log untuk mencatat semua aktivitas penting yang dilakukan pengguna.

## Cara Instalasi

1.  **Unduh Proyek**: Unduh atau kloning repositori ini ke komputer lokal Anda.
2.  **Pindahkan ke Web Server**: Letakkan semua file proyek ke dalam direktori root server web Anda (misalnya, `C:/xampp/htdocs/uang_kas`).
3.  **Buat Database**: Buka phpMyAdmin atau klien database lainnya, lalu buat database baru dengan nama `uang_kas`.
4.  **Impor Database**: Impor file `uang_kas.sql` yang ada di dalam folder proyek ke dalam database `uang_kas` yang baru saja Anda buat.
5.  **Konfigurasi Koneksi**: Buka file `connection.php` dan sesuaikan pengaturan koneksi database (`$host`, `$user`, `$pass`, `$db`) jika diperlukan.
6.  **Jalankan Aplikasi**: Buka browser dan akses aplikasi melalui URL `http://localhost/uang_kas`.

## Akun Default

Anda dapat login menggunakan akun default yang sudah tersedia di dalam database:

*   **Administrator**:
    *   **Username**: `Giovano`
    *   **Password**: `123456` (atau sesuai dengan hash di `uang_kas.sql`)
*   **Bendahara**:
    *   **Username**: `putri`
    *   **Password**: `123456`
*   **Guru**:
    *   **Username**: `hendrik`
    *   **Password**: `123456`

> **Catatan**: Kata sandi di-hash menggunakan BCRYPT. Jika kata sandi `password` tidak berfungsi, Anda dapat mengatur ulang kata sandi secara manual di tabel `user` pada database.

---

*Dibuat oleh Varel Giovano &copy; 2025*
