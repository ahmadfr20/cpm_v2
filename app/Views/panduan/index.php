<?= $this->extend('layout/layout'); ?>

<?= $this->section('content'); ?>
<div class="page-content">
    <div class="container-fluid">

        <!-- start page title -->
        <div class="row">
            <div class="col-12">
                <div class="page-title-box d-sm-flex align-items-center justify-content-between">
                    <h4 class="mb-sm-0"><?= $title ?? 'Panduan Aplikasi' ?></h4>
                    <div class="page-title-right">
                        <ol class="breadcrumb m-0">
                            <li class="breadcrumb-item"><a href="javascript: void(0);">Dashboard</a></li>
                            <li class="breadcrumb-item active">Panduan Aplikasi</li>
                        </ol>
                    </div>
                </div>
            </div>
        </div>
        <!-- end page title -->

        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header border-bottom-dashed d-flex align-items-center">
                        <h4 class="card-title mb-0 flex-grow-1">Buku Panduan Penggunaan</h4>
                        <div>
                            <!-- Optional: Tombol untuk buka PDF di tab baru -->
                            <a href="<?= $pdf_url ?>" target="_blank" class="btn btn-sm btn-primary">
                                <i class="bi bi-box-arrow-up-right me-1"></i> Buka di Tab Baru
                            </a>
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <!-- Wrapper PDF Reader -->
                        <!-- Object tag lebih kompatibel untuk berbagai browser membaca PDF tanpa force download -->
                        <div class="pdf-container" style="width: 100%; height: 80vh; overflow: hidden;">
                            <object data="<?= $pdf_url ?>" type="application/pdf" width="100%" height="100%">
                                <iframe src="<?= $pdf_url ?>" width="100%" height="100%" style="border: none;">
                                    <p>Browser Anda tidak mendukung fitur untuk menampilkan PDF secara langsung. 
                                    Silakan <a href="<?= $pdf_url ?>" target="_blank">klik di sini untuk mengunduh / melihat PDF</a>.</p>
                                </iframe>
                            </object>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>
<?= $this->endSection(); ?>
