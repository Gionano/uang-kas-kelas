<?php 
  require 'connection.php';
  checkLogin();
  $id_bulan_pembayaran = $_GET['id_bulan_pembayaran'];
  $detail_bulan_pembayaran = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM bulan_pembayaran WHERE id_bulan_pembayaran = '$id_bulan_pembayaran'"));

  // --- LOGIKA PENYESUAIAN PEMBAYARAN OTOMATIS ---
  // Tujuan: Memperbaiki bug penumpukan data dan memastikan pembayaran terdistribusi dengan benar saat ada hari libur.
  // Metode:
  // 1. Mengambil semua pembayaran siswa untuk bulan ini.
  // 2. Mengumpulkan total pembayaran yang telah dilakukan setiap siswa.
  // 3. Mengidentifikasi slot minggu yang aktif (tidak libur).
  // 4. Mendistribusikan ulang total pembayaran ke slot-slot aktif secara berurutan.
  // 5. Jika ada kelebihan pembayaran, akan dipindahkan ke bulan berikutnya.
  $data_changed = false;

  // Ambil semua data pembayaran untuk bulan ini sekali saja untuk efisiensi.
  $all_payments_query = mysqli_query($conn, "SELECT * FROM uang_kas WHERE id_bulan_pembayaran = '$id_bulan_pembayaran'");
  $all_payments_data = [];
  while($row = mysqli_fetch_assoc($all_payments_query)) {
    $all_payments_data[] = $row;
  }

  // Jika ada data pembayaran, lanjutkan proses penyesuaian.
  if (!empty($all_payments_data)) {
    // Siapkan daftar minggu yang aktif (tidak libur).
    $active_weeks_cols = [];
    for ($i = 1; $i <= 4; $i++) {
      if (!$detail_bulan_pembayaran['libur_minggu_' . $i]) {
        $active_weeks_cols[] = 'minggu_ke_' . $i;
      }
    }
    $num_active_weeks = count($active_weeks_cols);
    $pembayaran_perminggu = $detail_bulan_pembayaran['pembayaran_perminggu'];

    // Proses setiap siswa.
    foreach ($all_payments_data as $payment_data) {
      // a. Kumpulkan semua pembayaran yang ada dan pecah jika ada penumpukan dari bug sebelumnya.
      $paid_amounts = [];
      $total_paid_this_month = 0;
      for ($i = 1; $i <= 4; $i++) {
        $total_paid_this_month += $payment_data['minggu_ke_' . $i];
      }

      if ($total_paid_this_month > 0 && $pembayaran_perminggu > 0) {
        $num_payments_made = floor($total_paid_this_month / $pembayaran_perminggu);
        for ($k = 0; $k < $num_payments_made; $k++) {
          $paid_amounts[] = $pembayaran_perminggu;
        }
      }

      // b. Siapkan data pembayaran baru yang sudah disesuaikan.
      $new_payments = ['minggu_ke_1' => 0, 'minggu_ke_2' => 0, 'minggu_ke_3' => 0, 'minggu_ke_4' => 0];
      $amount_to_next_month = 0;

      // c. Distribusikan ulang pembayaran ke slot minggu yang aktif.
      for ($i = 0; $i < count($paid_amounts); $i++) {
        if ($i < $num_active_weeks) {
          // Masukkan ke slot minggu aktif di bulan ini.
          $target_week_col = $active_weeks_cols[$i];
          $new_payments[$target_week_col] = $paid_amounts[$i];
        } else {
          // Jika slot di bulan ini habis, lebihnya akan dipindahkan ke bulan berikutnya.
          $amount_to_next_month += $paid_amounts[$i];
        }
      }

      // d. Cek apakah ada perubahan dari data asli.
      $is_different = false;
      foreach ($new_payments as $key => $value) {
        if ($payment_data[$key] != $value) { $is_different = true; break; }
      }

      // e. Jika ada perubahan atau ada dana ke bulan depan, lakukan update.
      if ($is_different || $amount_to_next_month > 0) {
        $data_changed = true;
        $id_uang_kas = $payment_data['id_uang_kas'];
        mysqli_query($conn, "UPDATE uang_kas SET minggu_ke_1 = '{$new_payments['minggu_ke_1']}', minggu_ke_2 = '{$new_payments['minggu_ke_2']}', minggu_ke_3 = '{$new_payments['minggu_ke_3']}', minggu_ke_4 = '{$new_payments['minggu_ke_4']}' WHERE id_uang_kas = '$id_uang_kas'");

        if ($amount_to_next_month > 0) {
            // Logika untuk memindahkan kelebihan dana ke bulan berikutnya
            $id_siswa = $payment_data['id_siswa'];
            $current_month_name = $detail_bulan_pembayaran['nama_bulan'];
            $current_year = $detail_bulan_pembayaran['tahun'];
            $months = ['januari', 'februari', 'maret', 'april', 'mei', 'juni', 'juli', 'agustus', 'september', 'oktober', 'november', 'desember'];
            $current_month_index = array_search($current_month_name, $months);

            $next_month_index = $current_month_index + 1;
            $next_year = $current_year;
            if ($next_month_index > 11) {
                $next_month_index = 0; // Januari
                $next_year = $current_year + 1;
            }
            $next_month_name = $months[$next_month_index];

            $check_next_month_result = mysqli_query($conn, "SELECT * FROM bulan_pembayaran WHERE nama_bulan = '$next_month_name' AND tahun = '$next_year'");
            if (mysqli_num_rows($check_next_month_result) > 0) {
              $id_bulan_pembayaran_next = mysqli_fetch_assoc($check_next_month_result)['id_bulan_pembayaran'];
            } else {
              mysqli_query($conn, "INSERT INTO bulan_pembayaran (nama_bulan, tahun, pembayaran_perminggu) VALUES ('$next_month_name', '$next_year', '$pembayaran_perminggu')");
              $id_bulan_pembayaran_next = mysqli_insert_id($conn);
            }
            $check_uang_kas_next_month = mysqli_query($conn, "SELECT id_uang_kas FROM uang_kas WHERE id_siswa = '$id_siswa' AND id_bulan_pembayaran = '$id_bulan_pembayaran_next'");
            if (mysqli_num_rows($check_uang_kas_next_month) == 0) {
                mysqli_query($conn, "INSERT INTO uang_kas (id_siswa, id_bulan_pembayaran) VALUES ('$id_siswa', '$id_bulan_pembayaran_next')");
            }
            mysqli_query($conn, "UPDATE uang_kas SET minggu_ke_1 = minggu_ke_1 + '$amount_to_next_month' WHERE id_siswa = '$id_siswa' AND id_bulan_pembayaran = '$id_bulan_pembayaran_next'");
        }
      }
    }
  }
  
  // Jika ada data yang berubah, refresh halaman untuk menampilkan data terbaru dan notifikasi
  if ($data_changed) {
    setAlert("Data Disesuaikan", "Status libur berubah, pembayaran telah disesuaikan secara otomatis.", "info");
    header("Location: detail_bulan_pembayaran.php?id_bulan_pembayaran=$id_bulan_pembayaran");
    exit();
  }
  
  $siswa = mysqli_query($conn, "SELECT * FROM siswa ORDER BY nama_siswa ASC");
  $siswa_baru = mysqli_query($conn, "SELECT * FROM siswa WHERE id_siswa NOT IN (SELECT id_siswa FROM uang_kas) ORDER BY nama_siswa ASC");
  $uang_kas = mysqli_query($conn, "SELECT * FROM uang_kas INNER JOIN siswa ON uang_kas.id_siswa = siswa.id_siswa INNER JOIN bulan_pembayaran ON uang_kas.id_bulan_pembayaran = bulan_pembayaran.id_bulan_pembayaran WHERE uang_kas.id_bulan_pembayaran = '$id_bulan_pembayaran' ORDER BY nama_siswa ASC");
  
  $bulan_pembayaran_pertama = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM bulan_pembayaran ORDER BY id_bulan_pembayaran ASC LIMIT 1")); 
  $id_bulan_pembayaran_pertama = $bulan_pembayaran_pertama['id_bulan_pembayaran'];  

  // --- LOGIKA PENGECEKAN TUNGGAKAN BULAN SEBELUMNYA ---
  $detail_bulan_sebelum = null;
  $uang_kas_bulan_sebelum_arr = [];
  if ($id_bulan_pembayaran != $id_bulan_pembayaran_pertama) {
    // Asumsi ID berurutan, konsisten dengan kode sebelumnya
    $id_bulan_pembayaran_sebelum = $id_bulan_pembayaran - 1; 
    
    $result_detail_sebelum = mysqli_query($conn, "SELECT * FROM bulan_pembayaran WHERE id_bulan_pembayaran = '$id_bulan_pembayaran_sebelum'");
    if (mysqli_num_rows($result_detail_sebelum) > 0) {
        $detail_bulan_sebelum = mysqli_fetch_assoc($result_detail_sebelum);
        
        $uang_kas_bulan_sebelum = mysqli_query($conn, "SELECT * FROM uang_kas WHERE id_bulan_pembayaran = '$id_bulan_pembayaran_sebelum'");
        while ($row = mysqli_fetch_assoc($uang_kas_bulan_sebelum)) {
            $uang_kas_bulan_sebelum_arr[$row['id_siswa']] = $row;
        }
    }
  }

  if (isset($_POST['btnEditPembayaranUangKas'])) {
    if (editPembayaranUangKas($_POST) > 0) {
      setAlert("Pembayaran has been changed", "Successfully changed", "success");
      header("Location: detail_bulan_pembayaran.php?id_bulan_pembayaran=$id_bulan_pembayaran");
    }
  }

  if (isset($_POST['btnTambahSiswa'])) {
    if (tambahSiswaUangKas($_POST) > 0) {
      setAlert("Siswa has been added", "Successfully added", "success");
      header("Location: detail_bulan_pembayaran.php?id_bulan_pembayaran=$id_bulan_pembayaran");
    }
  }

?>

<!DOCTYPE html>
<html>
<head>
  <?php include 'include/css.php'; ?>
  <title>Detail Bulan Pembayaran : <?= ucwords($detail_bulan_pembayaran['nama_bulan']); ?> <?= $detail_bulan_pembayaran['tahun']; ?></title>
</head>
<body class="hold-transition sidebar-mini layout-fixed">
<div class="wrapper">
  
  <?php include 'include/navbar.php'; ?>

  <?php include 'include/sidebar.php'; ?>

  <!-- Content Wrapper. Contains page content -->
  <div class="content-wrapper">
    <!-- Content Header (Page header) -->
    <div class="content-header">
      <div class="container-fluid">
        <div class="row mb-2">
          <div class="col-sm">
            <h1 class="m-0 text-dark">Detail Bulan Pembayaran : <?= ucwords($detail_bulan_pembayaran['nama_bulan']); ?> <?= $detail_bulan_pembayaran['tahun']; ?></h1>
            <h4>Rp. <?= number_format($detail_bulan_pembayaran['pembayaran_perminggu']); ?> / minggu</h4>
          </div><!-- /.col -->
          <div class="col-sm text-right">
            <?php if ($_SESSION['id_jabatan'] !== '3'): ?>
              <a href="edit_bulan_pembayaran.php?id_bulan_pembayaran=<?= $id_bulan_pembayaran; ?>" class="btn btn-success"><i class="fas fa-fw fa-edit"></i> Edit Bulan Pembayaran</a>
              <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#tambahSiswaModal"><i class="fas fa-fw fa-plus"></i> Tambah Siswa</button>
            <?php endif ?>
          </div>
        </div><!-- /.row -->
      </div><!-- /.container-fluid -->
    </div>
    <!-- /.content-header -->

    <!-- Main content -->
    <section class="content">
      <div class="container-fluid bg-white p-3 rounded">
        <div class="table-responsive">
          <table class="table table-hover table-striped table-bordered" id="table_id">
            <thead>
              <tr>
                <th>No.</th>
                <th>Nama Siswa</th>
                <th>Minggu ke 1</th>
                <th>Minggu ke 2</th>
                <th>Minggu ke 3</th>
                <th>Minggu ke 4</th>
              </tr>
            </thead>
            <tbody>
              <?php $i = 1; ?>
              <?php foreach ($uang_kas as $duk): ?>
                <?php
                  // --- LOGIKA PENGECEKAN TUNGGAKAN BULAN SEBELUMNYA ---
                  $tunggakan_bulan_sebelumnya = false;
                  // Cek jika kita tidak di bulan pertama dan data bulan sebelumnya ada
                  if ($id_bulan_pembayaran != $id_bulan_pembayaran_pertama && $detail_bulan_sebelum) {
                    // Cek jika ada data pembayaran siswa ini di bulan sebelumnya
                    if (isset($uang_kas_bulan_sebelum_arr[$duk['id_siswa']])) {
                      $data_sebelum = $uang_kas_bulan_sebelum_arr[$duk['id_siswa']];
                      
                      // Hitung jumlah minggu aktif di bulan sebelumnya (4 - jumlah minggu libur)
                      $minggu_aktif_sebelum = 4 - ($detail_bulan_sebelum['libur_minggu_1'] + $detail_bulan_sebelum['libur_minggu_2'] + $detail_bulan_sebelum['libur_minggu_3'] + $detail_bulan_sebelum['libur_minggu_4']);

                      // Hitung total yang harus dibayar dan yang sudah dibayar
                      $total_harus_bayar_sebelum = $detail_bulan_sebelum['pembayaran_perminggu'] * $minggu_aktif_sebelum;
                      $total_sudah_bayar_sebelum = $data_sebelum['minggu_ke_1'] + $data_sebelum['minggu_ke_2'] + $data_sebelum['minggu_ke_3'] + $data_sebelum['minggu_ke_4'];

                      // Jika pembayaran kurang, maka ada tunggakan
                      if ($total_sudah_bayar_sebelum < $total_harus_bayar_sebelum) {
                        $tunggakan_bulan_sebelumnya = true;
                      }
                    } else {
                      // Jika siswa tidak ada di bulan sebelumnya (misal siswa baru), anggap tidak ada tunggakan.
                      $tunggakan_bulan_sebelumnya = false;
                    }
                  }
                ?>
                <?php if ($_SESSION['id_jabatan'] == '3'): ?>
                  <tr>
                    <td><?= $i++; ?></td>
                    <td><?= ucwords(htmlspecialchars_decode($duk['nama_siswa'])); ?></td>
                    <?php if ($duk['libur_minggu_1']): ?>
                      <td class="text-info">Libur</td>
                    <?php elseif ($duk['minggu_ke_1'] == $duk['pembayaran_perminggu']): ?>
                      <td class="text-success"><?= number_format($duk['minggu_ke_1']); ?></td>
                    <?php else: ?>
                      <td class="text-danger"><?= number_format($duk['minggu_ke_1']); ?></td>
                    <?php endif ?>

                    <?php if ($duk['libur_minggu_2']): ?>
                      <td class="text-info">Libur</td>
                    <?php elseif ($duk['minggu_ke_2'] == $duk['pembayaran_perminggu']): ?>
                      <td class="text-success"><?= number_format($duk['minggu_ke_2']); ?></td>
                    <?php else: ?>
                      <td class="text-danger"><?= number_format($duk['minggu_ke_2']); ?></td>
                    <?php endif ?>

                    <?php if ($duk['libur_minggu_3']): ?>
                      <td class="text-info">Libur</td>
                    <?php elseif ($duk['minggu_ke_3'] == $duk['pembayaran_perminggu']): ?>
                      <td class="text-success"><?= number_format($duk['minggu_ke_3']); ?></td>
                    <?php else: ?>
                      <td class="text-danger"><?= number_format($duk['minggu_ke_3']); ?></td>
                    <?php endif ?>

                    <?php if ($duk['libur_minggu_4']): ?>
                      <td class="text-info">Libur</td>
                    <?php elseif ($duk['minggu_ke_4'] == $duk['pembayaran_perminggu']): ?>
                      <td class="text-success"><?= number_format($duk['minggu_ke_4']); ?></td>
                    <?php else: ?>
                      <td class="text-danger"><?= number_format($duk['minggu_ke_4']); ?></td>
                    <?php endif ?>
                  </tr>
                <?php else: ?>
                  <?php 
                    $can_pay_w2 = ($duk['minggu_ke_1'] == $duk['pembayaran_perminggu']) || $duk['libur_minggu_1'];
                    $can_pay_w3 = ($duk['minggu_ke_2'] == $duk['pembayaran_perminggu']) || ($duk['libur_minggu_2'] && $can_pay_w2);
                    $can_pay_w4 = ($duk['minggu_ke_3'] == $duk['pembayaran_perminggu']) || ($duk['libur_minggu_3'] && $can_pay_w3);
                  ?>
                  <?php if ($tunggakan_bulan_sebelumnya): ?>
                    <tr class="bg-danger">
                  <?php else: ?>
                    <tr>
                  <?php endif ?>
                    <td><?= $i++; ?></td>
                    <td><?= $duk['nama_siswa']; ?></td>
                    <?php if ($duk['libur_minggu_1']): ?>
                      <td><span class="badge badge-info">Libur</span></td>
                    <?php elseif ($duk['minggu_ke_1'] == $duk['pembayaran_perminggu']): ?>
                      <?php if ($duk['minggu_ke_2'] !== "0"): ?>
                        <td>
                          <button type="button" class="badge badge-success" data-container="body" data-toggle="popover" data-placement="top" data-content="Tidak bisa mengubah minggu ke 1, kalau minggu ke 2 dan seterusnya sudah lunas, jika ingin mengubah, ubahlah minggu ke 2 atau ke 3 atau ke 4 terlebih dahulu menjadi 0.">
                            <i class="fas fa-fw fa-check"></i> Sudah bayar
                          </button>
                        </td>
                      <?php else: ?>
                        <td><a href="" data-toggle="modal" data-target="#editMingguKe1<?= $duk['id_uang_kas']; ?>" class="badge badge-success"><i class="fas fa-fw fa-check"></i> Sudah bayar</a></td>
                      <?php endif ?>
                    <?php else: ?>
                      <td>
                        <?php if ($tunggakan_bulan_sebelumnya): ?>
                          <button type="button" class="badge badge-danger" data-container="body" data-toggle="popover" data-placement="top" data-content="Tidak bisa melakukan pembayaran, jika bulan pembayaran sebelumnya belum lunas.">
                            <i class="fas fa-fw fa-times"></i> 
                          </button>
                        <?php else: ?>
                          <a href="" data-toggle="modal" data-target="#editMingguKe1<?= $duk['id_uang_kas']; ?>" class="badge badge-danger"><?= number_format($duk['minggu_ke_1']); ?></a>
                        <?php endif ?>
                      </td>
                    <?php endif ?>
                    <!-- MINGGU 2 -->
                    <?php if ($duk['libur_minggu_2']): ?>
                      <td><span class="badge badge-info">Libur</span></td>
                    <?php elseif (!$can_pay_w2): ?>
                      <td><---</td>
                    <?php elseif ($duk['minggu_ke_2'] == $duk['pembayaran_perminggu']): ?>
                      <?php if ($duk['minggu_ke_3'] !== "0"): ?>
                        <td><button type="button" class="badge badge-success" data-container="body" data-toggle="popover" data-placement="top" data-content="Tidak bisa mengubah minggu ke 2, jika minggu ke 3 dan seterusnya sudah lunas, jika ingin mengubah, ubahlah minggu ke 3 atau ke 4 terlebih dahulu menjadi 0."><i class="fas fa-fw fa-check"></i> Sudah bayar</button></td>
                      <?php else: ?>
                        <td><a href="" data-toggle="modal" data-target="#editMingguKe2<?= $duk['id_uang_kas']; ?>" class="badge badge-success"><i class="fas fa-fw fa-check"></i> Sudah bayar</a></td>
                      <?php endif ?>
                    <?php else: ?>
                      <td><a href="" data-toggle="modal" data-target="#editMingguKe2<?= $duk['id_uang_kas']; ?>" class="badge badge-danger"><?= number_format($duk['minggu_ke_2']); ?></a></td>
                    <?php endif ?>

                    <!-- MINGGU 3 -->
                    <?php if ($duk['libur_minggu_3']): ?>
                      <td><span class="badge badge-info">Libur</span></td>
                    <?php elseif (!$can_pay_w3): ?>
                      <td><---</td>
                    <?php elseif ($duk['minggu_ke_3'] == $duk['pembayaran_perminggu']): ?>
                      <?php if ($duk['minggu_ke_4'] !== "0"): ?>
                        <td><button type="button" class="badge badge-success" data-container="body" data-toggle="popover" data-placement="top" data-content="Tidak bisa mengubah minggu ke 3, jika minggu ke 4 sudah lunas, jika ingin mengubah, ubahlah minggu ke 4 terlebih dahulu menjadi 0."><i class="fas fa-fw fa-check"></i> Sudah bayar</button></td>
                      <?php else: ?>
                        <td><a href="" data-toggle="modal" data-target="#editMingguKe3<?= $duk['id_uang_kas']; ?>" class="badge badge-success"><i class="fas fa-fw fa-check"></i> Sudah bayar</a></td>
                      <?php endif ?>
                    <?php else: ?>
                      <td><a href="" data-toggle="modal" data-target="#editMingguKe3<?= $duk['id_uang_kas']; ?>" class="badge badge-danger"><?= number_format($duk['minggu_ke_3']); ?></a></td>
                    <?php endif ?>

                    <!-- MINGGU 4 -->
                    <?php if ($duk['libur_minggu_4']): ?>
                      <td><span class="badge badge-info">Libur</span></td>
                    <?php elseif (!$can_pay_w4): ?>
                      <td><---</td>
                    <?php elseif ($duk['minggu_ke_4'] == $duk['pembayaran_perminggu']): ?>
                      <td><a href="" data-toggle="modal" data-target="#editMingguKe4<?= $duk['id_uang_kas']; ?>" class="badge badge-success"><i class="fas fa-fw fa-check"></i> Sudah bayar</a></td>
                    <?php else: ?>
                      <td><a href="" data-toggle="modal" data-target="#editMingguKe4<?= $duk['id_uang_kas']; ?>" class="badge badge-danger"><?= number_format($duk['minggu_ke_4']); ?></a></td>
                    <?php endif ?>
                  </tr>
                    
                  <div class="modal fade" id="editMingguKe1<?= $duk['id_uang_kas']; ?>" tabindex="-1" role="dialog" aria-labelledby="editMingguKe1Label<?= $duk['id_uang_kas']; ?>" aria-hidden="true">
                    <div class="modal-dialog" role="document">
                      <form method="post">
                        <input type="hidden" name="id_uang_kas" value="<?= $duk['id_uang_kas']; ?>">
                        <div class="modal-content">
                          <div class="modal-header">
                            <h5 class="modal-title" id="editMingguKe1Label<?= $dbp['id_bulan_pembayaran']; ?>">Ubah Minggu Ke-1 : <?= $duk['nama_siswa']; ?></h5>
                            <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                              <span aria-hidden="true">&times;</span>
                            </button>
                          </div>
                          <div class="modal-body">
                            <div class="form-group">
                              <label for="minggu_ke_1">Minggu Ke-1</label>
                              <input type="hidden" name="uang_sebelum" value="<?= $duk['minggu_ke_1']; ?>">
                              <input max="<?= $duk['pembayaran_perminggu']; ?>" type="number" name="minggu_ke_1" class="form-control" value="<?= $duk['minggu_ke_1']; ?>">
                            </div>
                          </div>
                          <div class="modal-footer">
                            <button type="button" class="btn btn-danger" data-dismiss="modal"><i class="fas fa-fw fa-times"></i> Close</button>
                            <button type="submit" name="btnEditPembayaranUangKas" class="btn btn-primary"><i class="fas fa-fw fa-save"></i> Save</button>
                          </div>
                        </div>
                      </form>
                    </div>
                  </div>

                  <div class="modal fade" id="editMingguKe2<?= $duk['id_uang_kas']; ?>" tabindex="-1" role="dialog" aria-labelledby="editMingguKe2Label<?= $duk['id_uang_kas']; ?>" aria-hidden="true">
                    <div class="modal-dialog" role="document">
                      <form method="post">
                        <input type="hidden" name="id_uang_kas" value="<?= $duk['id_uang_kas']; ?>">
                        <div class="modal-content">
                          <div class="modal-header">
                            <h5 class="modal-title" id="editMingguKe2Label<?= $dbp['id_bulan_pembayaran']; ?>">Ubah Minggu Ke-2 : <?= $duk['nama_siswa']; ?></h5>
                            <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                              <span aria-hidden="true">&times;</span>
                            </button>
                          </div>
                          <div class="modal-body">
                            <div class="form-group">
                              <label for="minggu_ke_2">Minggu Ke-2</label>
                              <input type="hidden" name="uang_sebelum" value="<?= $duk['minggu_ke_2']; ?>">
                              <input max="<?= $duk['pembayaran_perminggu']; ?>" type="number" name="minggu_ke_2" class="form-control" value="<?= $duk['minggu_ke_2']; ?>">
                            </div>
                          </div>
                          <div class="modal-footer">
                            <button type="button" class="btn btn-danger" data-dismiss="modal"><i class="fas fa-fw fa-times"></i> Close</button>
                            <button type="submit" name="btnEditPembayaranUangKas" class="btn btn-primary"><i class="fas fa-fw fa-save"></i> Save</button>
                          </div>
                        </div>
                      </form>
                    </div>
                  </div>

                  <div class="modal fade" id="editMingguKe3<?= $duk['id_uang_kas']; ?>" tabindex="-1" role="dialog" aria-labelledby="editMingguKe3Label<?= $duk['id_uang_kas']; ?>" aria-hidden="true">
                    <div class="modal-dialog" role="document">
                      <form method="post">
                        <input type="hidden" name="id_uang_kas" value="<?= $duk['id_uang_kas']; ?>">
                        <div class="modal-content">
                          <div class="modal-header">
                            <h5 class="modal-title" id="editMingguKe3Label<?= $dbp['id_bulan_pembayaran']; ?>">Ubah Minggu Ke-3 : <?= $duk['nama_siswa']; ?></h5>
                            <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                              <span aria-hidden="true">&times;</span>
                            </button>
                          </div>
                          <div class="modal-body">
                            <div class="form-group">
                              <label for="minggu_ke_3">Minggu Ke-3</label>
                              <input type="hidden" name="uang_sebelum" value="<?= $duk['minggu_ke_3']; ?>">
                              <input max="<?= $duk['pembayaran_perminggu']; ?>" type="number" name="minggu_ke_3" class="form-control" value="<?= $duk['minggu_ke_3']; ?>">
                            </div>
                          </div>
                          <div class="modal-footer">
                            <button type="button" class="btn btn-danger" data-dismiss="modal"><i class="fas fa-fw fa-times"></i> Close</button>
                            <button type="submit" name="btnEditPembayaranUangKas" class="btn btn-primary"><i class="fas fa-fw fa-save"></i> Save</button>
                          </div>
                        </div>
                      </form>
                    </div>
                  </div>

                  <div class="modal fade" id="editMingguKe4<?= $duk['id_uang_kas']; ?>" tabindex="-1" role="dialog" aria-labelledby="editMingguKe4Label<?= $duk['id_uang_kas']; ?>" aria-hidden="true">
                    <div class="modal-dialog" role="document">
                      <form method="post">
                        <input type="hidden" name="id_uang_kas" value="<?= $duk['id_uang_kas']; ?>">
                        <input type="hidden" name="pembayaran_perminggu" value="<?= $duk['pembayaran_perminggu']; ?>">
                        <div class="modal-content">
                          <div class="modal-header">
                            <h5 class="modal-title" id="editMingguKe4Label<?= $dbp['id_bulan_pembayaran']; ?>">Ubah Minggu Ke-4 : <?= $duk['nama_siswa']; ?></h5>
                            <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                              <span aria-hidden="true">&times;</span>
                            </button>
                          </div>
                          <div class="modal-body">
                            <div class="form-group">
                              <label for="minggu_ke_4">Minggu Ke-4</label>
                              <input type="hidden" name="uang_sebelum" value="<?= $duk['minggu_ke_4']; ?>">
                              <input max="<?= $duk['pembayaran_perminggu']; ?>" type="number" name="minggu_ke_4" class="form-control" value="<?= $duk['minggu_ke_4']; ?>">
                            </div>
                          </div>
                          <div class="modal-footer">
                            <button type="button" class="btn btn-danger" data-dismiss="modal"><i class="fas fa-fw fa-times"></i> Close</button>
                            <button type="submit" name="btnEditPembayaranUangKas" class="btn btn-primary"><i class="fas fa-fw fa-save"></i> Save</button>
                          </div>
                        </div>
                      </form>
                    </div>
                  </div>
                <?php endif ?>
              <?php endforeach ?>
            </tbody>
          </table>
        </div>
      </div>
    </section>
    <!-- /.content -->
  </div>
<?php if ($_SESSION['id_jabatan'] !== '3'): ?>
  <div class="modal fade" id="tambahSiswaModal" tabindex="-1" role="dialog" aria-labelledby="tambahSiswaModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
      <form method="post">
        <input type="hidden" name="id_bulan_pembayaran" value="<?= $id_bulan_pembayaran; ?>">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title" id="tambahSiswaModalLabel">Tambah Siswa</h5>
            <button type="button" class="close" data-dismiss="modal" aria-label="Close">
              <span aria-hidden="true">&times;</span>
            </button>
          </div>
          <div class="modal-body">
            <div class="form-group">
              <label for="id_siswa">Nama Siswa</label>
              <select name="id_siswa" id="id_siswa" class="form-control">
                <?php foreach ($siswa_baru as $dsb): ?>
                  <option value="<?= $dsb['id_siswa']; ?>"><?= $dsb['nama_siswa']; ?></option>
                <?php endforeach ?>
              </select>
              <a href="siswa.php?toggle_modal=tambahSiswaModal">Tidak ada nama siswa diatas? Tambahkan siswa disini!</a>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-danger" data-dismiss="modal"><i class="fas fa-fw fa-times"></i> Close</button>
            <button type="submit" name="btnTambahSiswa" class="btn btn-primary"><i class="fas fa-fw fa-save"></i> Save</button>
          </div>
        </div>
      </form>
    </div>
  </div>
<?php endif ?>

  <!-- /.content-wrapper -->
  <footer class="main-footer">
    <strong>Copyright &copy; 2025 By Varel Giovano.</strong>
    All rights reserved.
    <div class="float-right d-none d-sm-inline-block">
      <b>Version</b> 1.0.0
    </div>
  </footer>

</div>
</body>
</html>
