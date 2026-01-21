<?= $this->extend('layout/layout') ?>
<?= $this->section('content') ?>

<div class="container-fluid">

    <!-- =========================
         HEADER
    ========================== -->
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h3 class="mb-0">Daily Schedule</h3>

        <form method="get" class="d-flex gap-2">
            <input
                type="date"
                name="date"
                value="<?= esc($date) ?>"
                class="form-control"
            >
            <button class="btn btn-primary">
                Filter
            </button>
        </form>
    </div>

    <!-- =========================
         LOOP PER SHIFT
    ========================== -->
    <?php foreach ($grouped as $group): ?>

        <div class="card mb-4 shadow-sm">

            <!-- SHIFT HEADER -->
            <div class="card-header bg-secondary text-white">
                <strong><?= esc($group['shift']['shift_name']) ?></strong>
            </div>

            <div class="card-body p-0">

                <?php if (empty($group['schedules'])): ?>
                    <div class="p-3 text-muted fst-italic">
                        Tidak ada schedule untuk shift ini
                    </div>
                <?php else: ?>

                    <table class="table table-bordered table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th style="width: 40%">Section</th>
                                <th style="width: 30%">Status</th>
                                <th style="width: 30%" class="text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>

                        <?php foreach ($group['schedules'] as $row): ?>
                            <tr>
                                <td><?= esc($row['section']) ?></td>

                                <td>
                                    <?php if ($row['is_completed']): ?>
                                        <span class="badge bg-success">Completed</span>
                                    <?php else: ?>
                                        <span class="badge bg-warning text-dark">Open</span>
                                    <?php endif ?>
                                </td>

                                <td class="text-center">
                                    <a
                                        href="<?= site_url('production/daily-schedule/view/' . $row['id']) ?>"
                                        class="btn btn-sm btn-outline-primary"
                                    >
                                        Detail
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach ?>

                        </tbody>
                    </table>

                <?php endif ?>

            </div>
        </div>

    <?php endforeach ?>

</div>

<?= $this->endSection() ?>
