<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Invoice <?= esc($delivery['invoice_no'] ?? '') ?></title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap');
        
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; color: #1e293b; background: #f8fafc; padding: 20px; }
        
        .invoice-container {
            max-width: 800px; margin: 0 auto; background: #fff;
            border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,.08);
            padding: 48px; position: relative; overflow: hidden;
        }
        .invoice-container::before {
            content: ''; position: absolute; top: 0; left: 0; right: 0; height: 6px;
            background: linear-gradient(135deg, #6366f1, #4338ca, #7c3aed);
        }
        
        .header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 40px; }
        .company-info h1 { font-size: 1.5rem; font-weight: 900; color: #0f172a; }
        .company-info p { color: #64748b; font-size: .85rem; margin-top: 4px; }
        
        .invoice-label { text-align: right; }
        .invoice-label h2 { font-size: 2rem; font-weight: 900; color: #6366f1; letter-spacing: -1px; }
        .invoice-label .inv-no { font-size: 1rem; font-weight: 700; color: #475569; margin-top: 4px; }
        .invoice-label .inv-date { font-size: .85rem; color: #94a3b8; margin-top: 2px; }
        
        .meta-section { display: flex; gap: 40px; margin-bottom: 32px; padding: 20px; background: #f8fafc; border-radius: 8px; }
        .meta-block label { font-size: .7rem; text-transform: uppercase; letter-spacing: .05em; color: #94a3b8; font-weight: 700; display: block; margin-bottom: 4px; }
        .meta-block span { font-size: .9rem; font-weight: 600; color: #334155; }
        
        table { width: 100%; border-collapse: collapse; margin-bottom: 24px; }
        thead th { background: #f1f5f9; color: #64748b; font-weight: 700; text-transform: uppercase; font-size: .7rem; letter-spacing: .05em; padding: 12px 16px; text-align: left; border-bottom: 2px solid #e2e8f0; }
        tbody td { padding: 12px 16px; border-bottom: 1px solid #f1f5f9; font-size: .875rem; font-weight: 500; }
        tbody tr:hover { background: #f8fafc; }
        
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .fw-bold { font-weight: 700; }
        
        .total-row { background: #f1f5f9; }
        .total-row td { font-weight: 800; font-size: 1rem; border-top: 2px solid #e2e8f0; }
        
        .footer { margin-top: 48px; display: flex; justify-content: space-between; }
        .sign-block { width: 200px; text-align: center; }
        .sign-block .label { font-size: .75rem; color: #94a3b8; text-transform: uppercase; font-weight: 700; letter-spacing: .05em; }
        .sign-block .line { border-bottom: 1px solid #cbd5e1; margin: 60px 0 8px; }
        .sign-block .name { font-size: .85rem; font-weight: 600; color: #334155; }
        
        .print-actions { text-align: center; margin-bottom: 20px; }
        .print-actions button { padding: 10px 24px; font-weight: 700; font-size: .9rem; border: none; border-radius: 8px; cursor: pointer; margin: 0 4px; }
        .btn-print { background: #6366f1; color: #fff; }
        .btn-print:hover { background: #4f46e5; }
        .btn-back { background: #e2e8f0; color: #475569; }
        .btn-back:hover { background: #cbd5e1; }
        
        @media print {
            body { padding: 0; background: #fff; }
            .invoice-container { box-shadow: none; padding: 24px; border-radius: 0; }
            .invoice-container::before { display: none; }
            .print-actions { display: none; }
        }
    </style>
</head>
<body>

<div class="print-actions">
    <button class="btn-print" onclick="window.print()"><i>🖨</i> Print Invoice</button>
    <button class="btn-back" onclick="window.close()">✕ Tutup</button>
</div>

<div class="invoice-container">
    <div class="header">
        <div class="company-info">
            <h1>CPM Manufacturing</h1>
            <p>Production Management System</p>
        </div>
        <div class="invoice-label">
            <h2>INVOICE</h2>
            <div class="inv-no"><?= esc($delivery['invoice_no'] ?? '-') ?></div>
            <div class="inv-date"><?= date('d F Y', strtotime($delivery['delivery_date'] ?? 'now')) ?></div>
        </div>
    </div>
    
    <div class="meta-section">
        <div class="meta-block">
            <label>Tanggal Pengiriman</label>
            <span><?= date('d/m/Y', strtotime($delivery['delivery_date'] ?? 'now')) ?></span>
        </div>
        <div class="meta-block">
            <label>Dibuat Oleh</label>
            <span><?= esc($delivery['created_by'] ?? '-') ?></span>
        </div>
        <div class="meta-block">
            <label>Total Item</label>
            <span><?= (int)($delivery['total_items'] ?? 0) ?></span>
        </div>
        <div class="meta-block">
            <label>Total Qty</label>
            <span><?= number_format((int)($delivery['total_qty'] ?? 0)) ?> Pcs</span>
        </div>
    </div>
    
    <table>
        <thead>
            <tr>
                <th style="width:40px">No</th>
                <th>Part No</th>
                <th>Part Name</th>
                <th>Customer</th>
                <th>DO Number</th>
                <th class="text-right">Qty</th>
            </tr>
        </thead>
        <tbody>
            <?php $totalQty = 0; ?>
            <?php foreach ($items as $i => $item): ?>
                <?php $totalQty += (int)$item['qty']; ?>
                <tr>
                    <td class="text-center"><?= $i + 1 ?></td>
                    <td class="fw-bold"><?= esc($item['part_no'] ?? '-') ?></td>
                    <td><?= esc($item['part_name'] ?? '-') ?></td>
                    <td><?= esc($item['customer_name'] ?? '-') ?></td>
                    <td><?= esc($item['do_number'] ?? '-') ?></td>
                    <td class="text-right fw-bold"><?= number_format((int)$item['qty']) ?></td>
                </tr>
            <?php endforeach; ?>
            <tr class="total-row">
                <td colspan="5" class="text-right">TOTAL</td>
                <td class="text-right"><?= number_format($totalQty) ?> Pcs</td>
            </tr>
        </tbody>
    </table>
    
    <div class="footer">
        <div class="sign-block">
            <div class="label">Disiapkan Oleh</div>
            <div class="line"></div>
            <div class="name"><?= esc($delivery['created_by'] ?? '_______________') ?></div>
        </div>
        <div class="sign-block">
            <div class="label">Disetujui Oleh</div>
            <div class="line"></div>
            <div class="name">_______________</div>
        </div>
        <div class="sign-block">
            <div class="label">Diterima Oleh</div>
            <div class="line"></div>
            <div class="name">_______________</div>
        </div>
    </div>
</div>

</body>
</html>
