<?php 
require 'connection.php';
checkLogin();

// Get id_bulan_pembayaran from URL
if (!isset($_GET['id_bulan_pembayaran'])) {
    header("Location: uang_kas.php");
    exit;
}
$id_bulan_pembayaran = $_GET['id_bulan_pembayaran'];

// Fetch month details
$detail_bulan_pembayaran = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM bulan_pembayaran WHERE id_bulan_pembayaran = '$id_bulan_pembayaran'"));

// If month not found, redirect
if (!$detail_bulan_pembayaran) {
    setAlert("Error!", "Bulan Pembayaran tidak ditemukan!", "error");
    header("Location: uang_kas.php");
    exit;
}

// Handle form submission
if (isset($_POST['btnEditBulanPembayaran'])) {
  if (editBulanPembayaran($_POST) > 0) {
    setAlert("Bulan Pembayaran has been changed", "Successfully changed", "success");
    header("Location: detail_bulan_pembayaran.php?id_bulan_pembayaran=$id_bulan_pembayaran");
    exit;
  } else {
    setAlert("Bulan Pembayaran failed to change", "Failed to change", "error");
    header("Location: detail_bulan_pembayaran.php?id_bulan_pembayaran=$id_bulan_pembayaran");
    exit;
  }
}
?>

<!DOCTYPE html>
<html>
<head>
  <?php include 'include/css.php'; ?>
  <title>Edit Bulan Pembayaran : <?= ucwords($detail_bulan_pembayaran['nama_bulan']); ?> <?= $detail_bulan_pembayaran['tahun']; ?></title>
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
            <h1 class="m-0 text-dark">Edit Bulan Pembayaran : <?= ucwords($detail_bulan_pembayaran['nama_bulan']); ?> <?= $detail_bulan_pembayaran['tahun']; ?></h1>
          </div><!-- /.col -->
        </div><!-- /.row -->
      </div><!-- /.container-fluid -->
    </div>
    <!-- /.content-header -->

    <!-- Main content -->
    <section class="content">
      <div class="container-fluid">
        <div class="row">
          <div class="col-lg-6">
            <div class="card">
              <div class="card-header">
                <h3 class="card-title">Form Edit Bulan Pembayaran</h3>
              </div>
              <div class="card-body">
                <form method="post">
                  <input type="hidden" name="id_bulan_pembayaran" value="<?= $detail_bulan_pembayaran['id_bulan_pembayaran']; ?>">
                  <div class="form-group">
                    <label for="nama_bulan">Nama Bulan</label>
                    <select name="nama_bulan" id="nama_bulan" class="form-control" required>
                      <option value="<?= $detail_bulan_pembayaran['nama_bulan']; ?>"><?= ucwords($detail_bulan_pembayaran['nama_bulan']); ?></option>
                      <option value="januari">Januari</option>
                      <option value="februari">Februari</option>
                      <option value="maret">Maret</option>
                      <option value="april">April</option>
                      <option value="mei">Mei</option>
                      <option value="juni">Juni</option>
                      <option value="juli">Juli</option>
                      <option value="agustus">Agustus</option>
                      <option value="september">September</option>
                      <option value="oktober">Oktober</option>
                      <option value="november">November</option>
                      <option value="desember">Desember</option>
                    </select>
                  </div>
                  <div class="form-group">
                    <label for="tahun">Tahun</label>
                    <input type="number" name="tahun" id="tahun" class="form-control" required value="<?= $detail_bulan_pembayaran['tahun']; ?>">
                  </div>
                  <div class="form-group">
                    <label for="pembayaran_perminggu">Pembayaran Perminggu</label>
                    <input type="number" name="pembayaran_perminggu" id="pembayaran_perminggu" class="form-control" required value="<?= $detail_bulan_pembayaran['pembayaran_perminggu']; ?>">
                  </div>
                  <div class="form-group">
                    <label>Minggu Libur</label><br>
                    <div class="form-check form-check-inline">
                      <input class="form-check-input" type="checkbox" id="libur_minggu_1" name="libur_minggu_1" value="1" <?= ($detail_bulan_pembayaran['libur_minggu_1'] == 1) ? 'checked' : ''; ?>>
                      <label class="form-check-label" for="libur_minggu_1">Minggu 1</label>
                    </div>
                    <div class="form-check form-check-inline">
                      <input class="form-check-input" type="checkbox" id="libur_minggu_2" name="libur_minggu_2" value="1" <?= ($detail_bulan_pembayaran['libur_minggu_2'] == 1) ? 'checked' : ''; ?>>
                      <label class="form-check-label" for="libur_minggu_2">Minggu 2</label>
                    </div>
                    <div class="form-check form-check-inline">
                      <input class="form-check-input" type="checkbox" id="libur_minggu_3" name="libur_minggu_3" value="1" <?= ($detail_bulan_pembayaran['libur_minggu_3'] == 1) ? 'checked' : ''; ?>>
                      <label class="form-check-label" for="libur_minggu_3">Minggu 3</label>
                    </div>
                    <div class="form-check form-check-inline">
                      <input class="form-check-input" type="checkbox" id="libur_minggu_4" name="libur_minggu_4" value="1" <?= ($detail_bulan_pembayaran['libur_minggu_4'] == 1) ? 'checked' : ''; ?>>
                      <label class="form-check-label" for="libur_minggu_4">Minggu 4</label>
                    </div>
                  </div>
                  <div class="card-footer">
                    <a href="detail_bulan_pembayaran.php?id_bulan_pembayaran=<?= $id_bulan_pembayaran; ?>" class="btn btn-danger"><i class="fas fa-fw fa-times"></i> Batal</a>
                    <button type="submit" name="btnEditBulanPembayaran" class="btn btn-primary"><i class="fas fa-fw fa-save"></i> Simpan</button>
                  </div>
                </form>
              </div>
            </div>
          </div>
        </div>
      </div>
    </section>
    <!-- /.content -->
  </div>

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