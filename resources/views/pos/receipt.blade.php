@php
    $setting = \App\Models\Setting::first();
@endphp

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Struk #{{ $sale->invoice_no }}</title>
    <style>
        /* Reset Margin & Font Monospace */
        body { margin: 0; padding: 0; font-family: 'Courier New', Courier, monospace; font-size: 12px; color: #000; }
        
        /* Ukuran kertas thermal 58mm (sekitar 48mm area cetak) */
        .ticket { width: 48mm; max-width: 48mm; margin: 0 auto; }
        
        h1, p { margin: 0; text-align: center; }
        h1 { font-size: 16px; font-weight: bold; margin-bottom: 5px; }
        .info { text-align: left; margin: 10px 0; font-size: 10px; }
        
        table { width: 100%; border-collapse: collapse; font-size: 11px; }
        td { padding: 2px 0; }
        .right { text-align: right; }
        .center { text-align: center; }
        
        .border-top { border-top: 1px dashed #000; }
        .border-bottom { border-bottom: 1px dashed #000; }
        
        /* Sembunyikan elemen ini saat tidak di-print (opsional) */
        @media print {
            @page { margin: 0; }
            body { margin: 0; }
        }
    </style>
</head>
<body>
    <div class="ticket">
        <h1>{{ strtoupper($setting->store_name ?? 'TOKO SEMBAKO') }}</h1>
        <p>{{ $setting->store_address }}<br>Telp: {{ $setting->store_phone }}</p>
        
        <div class="info border-top border-bottom" style="padding: 5px 0;">
            No  : {{ $sale->invoice_no }}<br>
            Tgl : {{ $sale->created_at->format('d/m/Y H:i') }}<br>
            Ksr : {{ $sale->user->name }}
        </div>

        <table>
            <tbody>
                @foreach($sale->saleDetails as $detail)
                <tr>
                    <td colspan="2">{{ $detail->product->name }}</td>
                </tr>
                <tr>
                    <td>{{ $detail->quantity + 0 }} {{ $detail->product->unit }} x {{ number_format($detail->selling_price, 0, ',', '.') }}</td>
                    <td class="right">{{ number_format($detail->subtotal, 0, ',', '.') }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>

        <table class="border-top" style="margin-top: 5px; padding-top: 5px;">
            <tr>
                <td><b>Total</b></td>
                <td class="right"><b>Rp {{ number_format($sale->grand_total, 0, ',', '.') }}</b></td>
            </tr>
            <tr>
                <td>Pembayaran</td>
                <td class="right">{{ $sale->payment_method }}</td>
            </tr>
        </table>

        <p style="margin-top: 15px; font-size: 10px;">{{ $setting->receipt_note }}</p>
    </div>

    <!-- Script untuk otomatis cetak lalu tutup tab -->
    <script>
        window.onload = function() {
            window.print();
        }
        window.onafterprint = function() {
            window.close();
        }
    </script>
</body>
</html>