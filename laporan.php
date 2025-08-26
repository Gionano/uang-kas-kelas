<?php 
  require 'connection.php';
  checkLogin();
  $bulan_pembayaran = mysqli_query($conn, "SELECT * FROM bulan_pembayaran ORDER BY id_bulan_pembayaran DESC");
  $pemasukkan_rows = [];
  $pengeluaran_rows = [];
  $fetch_sql = null;

  if (isset($_POST['btnLaporanPemasukkan'])) {
    $id_bulan_pembayaran = $_POST['id_bulan_pembayaran'];
    $stmt = mysqli_prepare($conn, "SELECT * FROM bulan_pembayaran 
        INNER JOIN uang_kas ON bulan_pembayaran.id_bulan_pembayaran = uang_kas.id_bulan_pembayaran 
        INNER JOIN siswa ON uang_kas.id_siswa = siswa.id_siswa 
        WHERE bulan_pembayaran.id_bulan_pembayaran = ? ORDER BY siswa.nama_siswa ASC");
    mysqli_stmt_bind_param($stmt, 'i', $id_bulan_pembayaran);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $pemasukkan_rows = mysqli_fetch_all($result, MYSQLI_ASSOC);
    if (!empty($pemasukkan_rows)) {
        $fetch_sql = $pemasukkan_rows[0];
    }
  }

  if (isset($_POST['btnLaporanPengeluaran'])) {
    $dari_tanggal_date = htmlspecialchars($_POST['dari_tanggal']);
    $sampai_tanggal_date = htmlspecialchars($_POST['sampai_tanggal']);
    $dari_tanggal = strtotime($dari_tanggal_date . " 00:00:00");
    $sampai_tanggal = strtotime($sampai_tanggal_date . " 23:59:59");
    
    $stmt = mysqli_prepare($conn, "SELECT * FROM pengeluaran INNER JOIN user ON pengeluaran.id_user = user.id_user WHERE tanggal_pengeluaran BETWEEN ? AND ? ORDER BY tanggal_pengeluaran DESC");
    mysqli_stmt_bind_param($stmt, 'ii', $dari_tanggal, $sampai_tanggal);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $pengeluaran_rows = mysqli_fetch_all($result, MYSQLI_ASSOC);
  }
?>

<!DOCTYPE html>
<html>
<head>
  <?php include 'include/css.php'; ?>
  <title>Laporan</title>
  <style>
  	@media print {
	  	.not-printed {
	  		display: none;
	  	}
	  	.total {
	  		color: black !important;
	  	}
  	}
  </style>
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
            <h1 class="m-0 text-dark">Laporan</h1>
          </div><!-- /.col -->
        </div><!-- /.row -->
      </div><!-- /.container-fluid -->
    </div>
    <!-- /.content-header -->

    <!-- Main content -->
    <section class="content">
      <div class="container-fluid">
        <div class="not-printed row justify-content-center">
        	<div class="col-lg-5 mr-4">
        		<h3>Pemasukkan</h3>
        		<form method="post">
        			<div class="form-group">
        				<label for="id_bulan_pembayaran">Pilih Bulan Pembayaran</label>
	        			<select name="id_bulan_pembayaran" id="id_bulan_pembayaran" class="form-control">
	        				<?php if (isset($_POST['btnLaporanPemasukkan'])): ?>
	        					<option value="<?= $fetch_sql['id_bulan_pembayaran']; ?>"><?= ucwords($fetch_sql['nama_bulan']); ?> | <?= $fetch_sql['tahun']; ?> | Rp. <?= number_format($fetch_sql['pembayaran_perminggu']); ?></option>
		        				<option disabled>----</option>
	        				<?php endif ?>
		        			<?php foreach ($bulan_pembayaran as $dbp): ?>
		        				<option value="<?= $dbp['id_bulan_pembayaran']; ?>"><?= ucwords($dbp['nama_bulan']); ?> | <?= $dbp['tahun']; ?> | Rp. <?= number_format($dbp['pembayaran_perminggu']); ?></option>
		        			<?php endforeach ?>
	        			</select>
        			</div>
        			<div class="form-group">
        				<button type="submit" name="btnLaporanPemasukkan" class="btn btn-primary">Laporan Pemasukkan</button>
        			</div>
        		</form>
        	</div>
        	<div class="col-lg-5 ml-4">
        		<h3>Pengeluaran</h3>
        		<form method="post">
        			<div class="row">
        				<div class="col-lg">
        					<div class="form-group">
		        				<label for="dari_tanggal">Dari Tanggal</label>
		        				<?php if (isset($_POST['btnLaporanPengeluaran'])): ?>
			        				<input type="date" name="dari_tanggal" class="form-control" id="dari_tanggal" value="<?= $_POST['dari_tanggal']; ?>">
	        					<?php else: ?>
			        				<input type="date" name="dari_tanggal" class="form-control" id="dari_tanggal" value="<?= date('Y-m-01'); ?>">
		        				<?php endif ?>
		        			</div>
        				</div>
        				<div class="col-lg">
        					<div class="form-group">
		        				<label for="sampai_tanggal">Sampai Tanggal</label>
		        				<?php if (isset($_POST['btnLaporanPengeluaran'])): ?>
			        				<input type="date" name="sampai_tanggal" class="form-control" id="sampai_tanggal" value="<?= $_POST['sampai_tanggal']; ?>">
	        					<?php else: ?>
			        				<input type="date" name="sampai_tanggal" class="form-control" id="sampai_tanggal" value="<?= date('Y-m-d'); ?>">
		        				<?php endif ?>
		        			</div>
        				</div>
        			</div>
        			<div class="form-group">
        				<button type="submit" name="btnLaporanPengeluaran" class="btn btn-primary">Laporan Pengeluaran</button>
        			</div>
        		</form>
        	</div>
        </div>
        <?php if (isset($_POST['btnLaporanPemasukkan']) && $fetch_sql): ?>
        	<hr class="not-printed">
        	<button id="btnDownload" class="not-printed btn btn-primary"><i class="fas fa-fw fa-download"></i> Download PDF</button>
        	<div id="laporan-content">
	        	<div class="row m-1">
	        	<div class="col-lg m-1">
	        		<h2 class="text-center mb-3 mt-2">Laporan Pemasukkan</h2>
	        		<h3 class="text-left mb-3">Laporan Pada Bulan: <?= ucwords($fetch_sql['nama_bulan']); ?>, Tahun: <?= $fetch_sql['tahun']; ?>, Pembayaran Perminggu: Rp. <?= number_format($fetch_sql['pembayaran_perminggu']); ?></h3>
	        		<div class="table-responsive">
	        			<table class="table table-bordered table-hover">
	        				<thead>
	        					<tr>
	        						<th>No.</th>
	        						<th>Nama Siswa</th>
	        						<th>Minggu Ke-1</th>
	        						<th>Minggu Ke-2</th>
	        						<th>Minggu Ke-3</th>
	        						<th>Minggu Ke-4</th>
	        					</tr>
	        				</thead>
	        				<tbody>
	        					<?php $i = 1; ?>
	        					<?php foreach ($pemasukkan_rows as $ds): ?>
	        						<tr>
	        							<td><?= $i++; ?></td>
	        							<td><?= ucwords(htmlspecialchars_decode($ds['nama_siswa'])); ?></td>
	        							<!-- Minggu 1 -->
	        							<?php if ($ds['libur_minggu_1']): ?>
	        								<td class="text-info">Libur</td>
	        							<?php else: ?>
		        							<?php if ($ds['minggu_ke_1'] == $ds['pembayaran_perminggu']): ?>
						                      <td class="text-success">Rp. <?= number_format($ds['minggu_ke_1']); ?></td>
						                    <?php else: ?>
						                      <td class="text-danger">Rp. <?= number_format($ds['minggu_ke_1']); ?></td>
						                    <?php endif ?>
	        							<?php endif ?>
	        							<!-- Minggu 2 -->
	        							<?php if ($ds['libur_minggu_2']): ?>
	        								<td class="text-info">Libur</td>
	        							<?php else: ?>
		        							<?php if ($ds['minggu_ke_2'] == $ds['pembayaran_perminggu']): ?>
						                      <td class="text-success">Rp. <?= number_format($ds['minggu_ke_2']); ?></td>
						                    <?php else: ?>
						                      <td class="text-danger">Rp. <?= number_format($ds['minggu_ke_2']); ?></td>
						                    <?php endif ?>
	        							<?php endif ?>
	        							<!-- Minggu 3 -->
	        							<?php if ($ds['libur_minggu_3']): ?>
	        								<td class="text-info">Libur</td>
	        							<?php else: ?>
		        							<?php if ($ds['minggu_ke_3'] == $ds['pembayaran_perminggu']): ?>
						                      <td class="text-success">Rp. <?= number_format($ds['minggu_ke_3']); ?></td>
						                    <?php else: ?>
						                      <td class="text-danger">Rp. <?= number_format($ds['minggu_ke_3']); ?></td>
						                    <?php endif ?>
	        							<?php endif ?>
	        							<!-- Minggu 4 -->
	        							<?php if ($ds['libur_minggu_4']): ?>
	        								<td class="text-info">Libur</td>
	        							<?php else: ?>
		        							<?php if ($ds['minggu_ke_4'] == $ds['pembayaran_perminggu']): ?>
						                      <td class="text-success">Rp. <?= number_format($ds['minggu_ke_4']); ?></td>
						                    <?php else: ?>
						                      <td class="text-danger">Rp. <?= number_format($ds['minggu_ke_4']); ?></td>
						                    <?php endif ?>
	        							<?php endif ?>
	        						</tr>
	        					<?php endforeach ?>
	        				</tbody>
	        			</table>
	        		</div>
	        	</div>
		        </div>
		        <div class="row mx-1 mb-1 mt-0">
	    			<div class="col-lg-4">
	    				<?php 
	                        $stmt = mysqli_prepare($conn, "SELECT sum(minggu_ke_1 + minggu_ke_2 + minggu_ke_3 + minggu_ke_4) as jml_uang_kas 
	                            FROM uang_kas 
	                            INNER JOIN bulan_pembayaran ON bulan_pembayaran.id_bulan_pembayaran = uang_kas.id_bulan_pembayaran 
	                            WHERE bulan_pembayaran.id_bulan_pembayaran = ?");
	                        mysqli_stmt_bind_param($stmt, 'i', $id_bulan_pembayaran);
	                        mysqli_stmt_execute($stmt);
	    					$jml_uang_kas = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
	    				?>
			    		<div class="p-3 rounded bg-success total">Total Pemasukkan: Rp. <?= number_format($jml_uang_kas['jml_uang_kas']); ?></div>
	    			</div>
	    		</div>
        	</div>
        <?php elseif (isset($_POST['btnLaporanPengeluaran']) && !empty($pengeluaran_rows)): ?>
        	<hr class="not-printed">
        	<button id="btnDownload" class="not-printed btn btn-primary"><i class="fas fa-fw fa-download"></i> Download PDF</button>
        	<div id="laporan-content">
	        	<div class="row m-1 mb-0">
	        	<div class="col-lg m-1">
	        		<h2 class="text-center mb-3 mt-2">Laporan Pengeluaran</h2>
	        		<h3 class="text-left mb-3">Laporan Dari Tanggal: <?= $dari_tanggal_date; ?> Sampai Tanggal: <?= $sampai_tanggal_date; ?></h3>
	        		<div class="table-responsive">
	        			<table class="table table-bordered table-hover">
	        				<thead>
	        					<tr>
	        						<th>No.</th>
	        						<th>Pengeluaran</th>
	        						<th>Keterangan</th>
	        						<th>Tanggal Pengeluaran</th>
	        						<th>Username</th>
	        					</tr>
	        				</thead>
	        				<tbody>
	        					<?php $i = 1; ?>
	        					<?php $total_pengeluaran = '0'; ?>
	        					<?php foreach ($pengeluaran_rows as $ds): ?>
	        						<tr>
	        							<td><?= $i++; ?></td>
	        							<td>Rp. <?= number_format($ds['jumlah_pengeluaran']); ?></td>
	        							<td><?= $ds['keterangan']; ?></td>
	        							<td><?= date('d-m-Y, H:i:s', $ds['tanggal_pengeluaran']); ?></td>
	        							<td><?= $ds['username']; ?></td>
	        						</tr>
	        						<?php 
	        							$total_pengeluaran += $ds['jumlah_pengeluaran'];
	        						?>
	        					<?php endforeach ?>
	        				</tbody>
	        			</table>
	        		</div>
	        	</div>
		        </div>
	    		<div class="row mx-1 mb-1 mt-0">
	    			<div class="col-lg-4">
			    		<div class="p-3 rounded bg-success total">Total Pengeluaran: Rp. <?= number_format($total_pengeluaran); ?></div>
	    			</div>
	    		</div>
        	</div>
        <?php endif ?>
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
<?php include 'include/js.php'; ?>
<!-- Ganti dari CDN ke file lokal untuk menghindari masalah koneksi -->
<script src="vendor/html2pdf/html2pdf.bundle.min.js"></script>
<script>
  // Pastikan DOM sudah siap sebelum menjalankan script
  document.addEventListener('DOMContentLoaded', function() {
    const btnDownload = document.getElementById('btnDownload');
    if (btnDownload) {
      btnDownload.addEventListener('click', function() {
        // Menonaktifkan tombol dan menampilkan status loading
        btnDownload.disabled = true;
        btnDownload.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Membuat PDF...';

        const element = document.getElementById('laporan-content');
        
        let filename = 'laporan.pdf';
        
        <?php if (isset($_POST['btnLaporanPemasukkan']) && $fetch_sql): ?>
          filename = `Laporan Pemasukkan - <?= ucwords($fetch_sql['nama_bulan']); ?> - <?= $fetch_sql['tahun']; ?>.pdf`;
        <?php elseif (isset($_POST['btnLaporanPengeluaran'])): ?>
          filename = `Laporan Pengeluaran - <?= $dari_tanggal_date; ?> - <?= $sampai_tanggal_date; ?>.pdf`;
        <?php endif; ?>

        const opt = {
          margin:       0.5,
          filename:     filename,
          image:        { type: 'jpeg', quality: 0.98 },
          html2canvas:  { scale: 2, useCORS: true },
          jsPDF:        { unit: 'in', format: 'letter', orientation: 'portrait' }
        };

        // Menggunakan html2pdf untuk membuat dan menyimpan PDF
        // then() akan dijalankan setelah PDF selesai dibuat
        html2pdf().set(opt).from(element).save().then(function() {
          // Mengaktifkan kembali tombol setelah selesai
          btnDownload.disabled = false;
          btnDownload.innerHTML = '<i class="fas fa-fw fa-download"></i> Download PDF';
        }).catch(function(error) {
          console.error('Gagal membuat PDF:', error);
          alert('Gagal membuat PDF. Silakan cek konsol browser untuk detailnya.');
          btnDownload.disabled = false;
          btnDownload.innerHTML = '<i class="fas fa-fw fa-download"></i> Download PDF';
        });
      });
    }
  });
</script>
</body>
</html>
