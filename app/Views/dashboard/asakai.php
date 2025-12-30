<h5>Asakai Dashboard</h5>

<div class="row mb-4">
<?php
$cards = [
    'Die Casting' => ['bg'=>'primary','d'=>$asakai['die_casting']],
    'Shot Blast'  => ['bg'=>'success','d'=>$asakai['shot_blast']],
    'Machining'   => ['bg'=>'warning','d'=>$asakai['machining']],
];
foreach ($cards as $title=>$c):
?>
<div class="col-md-4">
    <div class="card bg-<?= $c['bg'] ?> text-white text-center">
        <div class="card-body">
            <h6><?= $title ?></h6>
            <h2><?= $c['d']['qty_ok'] ?? 0 ?></h2>
            OK: <?= $c['d']['qty_ok'] ?? 0 ?> |
            NG: <?= $c['d']['qty_ng'] ?? 0 ?>
        </div>
    </div>
</div>
<?php endforeach ?>
</div>
