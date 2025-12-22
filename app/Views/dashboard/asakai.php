<h5>Asakai Dashboard</h5>

<div class="row mb-3">
    <div class="col-md-4">
        <div class="card bg-primary text-white text-center">
            <div class="card-body">
                <h6>Die Casting</h6>
                <h2><?= $asakai['die_casting']['qty_ok'] ?? 0 ?></h2>
                OK: <?= $asakai['die_casting']['qty_ok'] ?? 0 ?> |
                NG: <?= $asakai['die_casting']['qty_ng'] ?? 0 ?>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card bg-success text-white text-center">
            <div class="card-body">
                <h6>Shot Blast</h6>
                <h2><?= $asakai['shot_blast']['qty_ok'] ?? 0 ?></h2>
                OK: <?= $asakai['shot_blast']['qty_ok'] ?? 0 ?> |
                NG: <?= $asakai['shot_blast']['qty_ng'] ?? 0 ?>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card bg-warning text-white text-center">
            <div class="card-body">
                <h6>Machining</h6>
                <h2><?= $asakai['machining']['qty_ok'] ?? 0 ?></h2>
                OK: <?= $asakai['machining']['qty_ok'] ?? 0 ?> |
                NG: <?= $asakai['machining']['qty_ng'] ?? 0 ?>
            </div>
        </div>
    </div>
</div>
