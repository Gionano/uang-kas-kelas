<?php 
require_once 'connection.php';
checkLogin();

// Handle form submissions for paying/unpaying fines
if (isset($_POST['btnBayarDenda'])) {
    if (bayarDenda($_POST) > 0) {
        setAlert("Berhasil", "Denda berhasil dibayar!", "success");
        header("Location: denda.php?id_bulan_pembayaran=" . $_POST['id_bulan_pembayaran']);
        exit;
    } else {
        setAlert("Gagal", "Gagal membayar denda!", "error");
        header("Location: denda.php?id_bulan_pembayaran=" . $_POST['id_bulan_pembayaran']);
        exit;
    }
}

if (isset($_GET['hapus_denda'])) {
    if (hapusDenda($_GET['id_uang_kas']) > 0) {
        setAlert("Berhasil", "Pembayaran denda berhasil dibatalkan!", "success");
        header("Location: denda.php?id_bulan_pembayaran=" . $_GET['id_bulan_pembayaran']);
        exit;
    } else {
        // checkJabatan() already sets an alert on failure
        header("Location: denda.php?id_bulan_pembayaran=" . $_GET['id_bulan_pembayaran']);
        exit;
    }
}

$bulan_pembayaran = mysqli_query($conn, "SELECT * FROM bulan_pembayaran ORDER BY tahun DESC, id_bulan_pembayaran DESC");
$data_denda = [];
$id_bulan_pembayaran = isset($_GET['id_bulan_pembayaran']) ? (int)$_GET['id_bulan_pembayaran'] : 0;
$is_month_passed = false;
$denda_per_bulan = 0;
$detail_bulan = null;

if ($id_bulan_pembayaran) {
    $detail_bulan = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM bulan_pembayaran WHERE id_bulan_pembayaran = '$id_bulan_pembayaran'"));
    if ($detail_bulan) {
        $denda_per_bulan = $detail_bulan['denda_per_bulan'];
        $nama_bulan = $detail_bulan['nama_bulan'];
        $tahun = $detail_bulan['tahun'];

        $month_map = [
            'januari' => 1, 'februari' => 2, 'maret' => 3, 'april' => 4, 'mei' => 5, 'juni' => 6,
            'juli' => 7, 'agustus' => 8, 'september' => 9, 'oktober' => 10, 'november' => 11, 'desember' => 12
        ];
        $month_number = $month_map[strtolower($nama_bulan)];

        // Check if the selected month has passed
        $end_of_month_timestamp = strtotime('last day of ' . $tahun . '-' . $month_number);
        if (time() > $end_of_month_timestamp) {
            $is_month_passed = true;
        }

        // Get students who are late (status_lunas = 0) and have not paid the fine yet
        $sql = "SELECT * FROM uang_kas 
                INNER JOIN siswa ON uang_kas.id_siswa = siswa.id_siswa 
                WHERE id_bulan_pembayaran = '$id_bulan_pembayaran' AND (status_lunas = 0 OR status_denda = 1)";
        $data_denda = mysqli_query($conn, $sql);
    }
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <?php include_once __DIR__ . '/include/head.php'; ?>
    <title>Telat Bayar</title>
</head>
<body id="page-top">
    <div id="wrapper">
        <?php include_once __DIR__ . '/include/sidebar.php'; ?>
        <div id="content-wrapper" class="d-flex flex-column">
            <div id="content">
                <?php include_once __DIR__ . '/include/topbar.php'; ?>
                <div class="container-fluid">
                    <div class="d-sm-flex align-items-center justify-content-between mb-4">
                        <h1 class="h3 mb-0 text-gray-800">Telat Bayar</h1>
                    </div>
                    <div class="card shadow mb-4">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-primary">Filter Bulan Pembayaran</h6>
                        </div>
                        <div class="card-body">
                            <form method="get">
                                <div class="form-group">
                                    <label for="id_bulan_pembayaran">Pilih Bulan Pembayaran</label>
                                    <select name="id_bulan_pembayaran" id="id_bulan_pembayaran" class="form-control" onchange="this.form.submit()">
                                        <option value="">--- Pilih Bulan ---</option>
                                        <?php foreach ($bulan_pembayaran as $dbp): ?>
                                            <option value="<?= $dbp['id_bulan_pembayaran']; ?>" <?= ($id_bulan_pembayaran == $dbp['id_bulan_pembayaran']) ? 'selected' : ''; ?>>
                                                <?= ucwords($dbp['nama_bulan']); ?> <?= $dbp['tahun']; ?>
                                            </option>
                                        <?php endforeach ?>
                                    </select>
                                </div>
                            </form>
                        </div>
                    </div>

                    <?php if ($id_bulan_pembayaran && $detail_bulan): ?>
                        <div class="card shadow mb-4">
                            <div class="card-header py-3">
                                <h6 class="m-0 font-weight-bold text-primary">Data Siswa Telat Bayar - <?= ucwords($detail_bulan['nama_bulan']); ?> <?= $detail_bulan['tahun']; ?></h6>
                            </div>
                            <div class="card-body">
                                <?php if (!$is_month_passed): ?>
                                    <div class="alert alert-warning">
                                        Bulan yang dipilih belum berakhir. Denda belum dapat diterapkan.
                                    </div>
                                <?php else: ?>
                                    <div class="table-responsive">
                                        <table class="table table-bordered" id="dataTable" width="100%" cellspacing="0">
                                            <thead>
                                                <tr>
                                                    <th>No.</th>
                                                    <th>Nama Siswa</th>
                                                    <th>Status Pembayaran</th>
                                                    <th>Status Denda</th>
                                                    <th>Aksi</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php $i = 1; ?>
                                                <?php foreach ($data_denda as $dd): ?>
                                                    <?php if ($dd['status_lunas'] == 0 || $dd['status_denda'] == 1): ?>
                                                        <tr>
                                                            <td><?= $i++; ?></td>
                                                            <td><?= htmlspecialchars($dd['nama_siswa']); ?></td>
                                                            <td><span class="badge badge-<?= $dd['status_lunas'] ? 'success' : 'danger'; ?>"><?= $dd['status_lunas'] ? 'Lunas' : 'Belum Lunas'; ?></span></td>
                                                            <td><span class="badge badge-<?= $dd['status_denda'] ? 'success' : 'danger'; ?>"><?= $dd['status_denda'] ? 'Sudah Bayar Denda' : 'Belum Bayar Denda'; ?></span></td>
                                                            <td>
                                                                <?php if ($dd['status_denda'] == 1): ?>
                                                                    <?php if (dataUser()['id_jabatan'] == '1'): ?>
                                                                        <a href="denda.php?hapus_denda=true&id_uang_kas=<?= $dd['id_uang_kas']; ?>&id_bulan_pembayaran=<?= $id_bulan_pembayaran; ?>" class="btn btn-danger btn-sm" onclick="return confirm('Apakah Anda yakin ingin membatalkan pembayaran denda untuk siswa ini?')"><i class="fas fa-fw fa-times"></i> Batalkan</a>
                                                                    <?php endif; ?>
                                                                <?php elseif($dd['status_lunas'] == 0): ?>
                                                                    <form method="post" class="d-inline">
                                                                        <input type="hidden" name="id_uang_kas" value="<?= $dd['id_uang_kas']; ?>">
                                                                        <input type="hidden" name="id_bulan_pembayaran" value="<?= $id_bulan_pembayaran; ?>">
                                                                        <button type="submit" name="btnBayarDenda" class="btn btn-success btn-sm"><i class="fas fa-fw fa-check"></i> Bayar Denda (Rp. <?= number_format($denda_per_bulan); ?>)</button>
                                                                    </form>
                                                                <?php endif ?>
                                                            </td>
                                                        </tr>
                                                    <?php endif; ?>
                                                <?php endforeach ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php include_once __DIR__ . '/include/footer.php'; ?>
        </div>
    </div>
    <a class="scroll-to-top rounded" href="#page-top"><i class="fas fa-angle-up"></i></a>
    <?php include_once __DIR__ . '/include/script.php'; ?>
</body>
</html>