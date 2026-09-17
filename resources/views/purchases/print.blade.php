<!DOCTYPE html>
<html lang="id">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>
        PO {{ $purchase->invoice_no }}
    </title>


    <style>

        /*
        ==========================================
        PAGE PRINT
        A3 LANDSCAPE

        A3:
        420mm x 297mm
        ==========================================
        */

        @page {
            size: A3 landscape;
            margin: 12mm;
        }


        * {
            box-sizing: border-box;
        }


        html,
        body {
            margin: 0;
            padding: 0;
        }


        body {
            font-family:
                Arial,
                Helvetica,
                sans-serif;

            color: #111827;

            font-size: 12px;

            background: white;
        }


        .page {
            width: 100%;
            min-height: 100%;
        }


        /*
        ==========================================
        HEADER
        ==========================================
        */

        .header {
            display: flex;

            justify-content: space-between;

            align-items: flex-start;

            border-bottom: 3px solid #111827;

            padding-bottom: 14px;

            margin-bottom: 16px;
        }


        .company {
            width: 60%;
        }


        .company-name {
            font-size: 26px;

            font-weight: 800;

            letter-spacing: 0.5px;

            margin-bottom: 6px;
        }


        .company-info {
            font-size: 11px;

            line-height: 1.6;

            color: #374151;
        }


        .document-title {
            text-align: right;

            width: 40%;
        }


        .document-title h1 {
            margin: 0;

            font-size: 30px;

            letter-spacing: 1px;

            font-weight: 800;
        }


        .document-title .subtitle {
            margin-top: 5px;

            font-size: 12px;

            color: #6b7280;

            text-transform: uppercase;

            letter-spacing: 1px;
        }


        /*
        ==========================================
        INFORMATION
        ==========================================
        */

        .info-wrapper {
            display: flex;

            justify-content: space-between;

            gap: 30px;

            margin-bottom: 18px;
        }


        .info-box {
            width: 50%;
        }


        .info-title {
            font-size: 11px;

            font-weight: 700;

            text-transform: uppercase;

            color: #6b7280;

            margin-bottom: 5px;
        }


        .info-table {
            width: 100%;

            border-collapse: collapse;
        }


        .info-table td {
            padding: 3px 0;

            vertical-align: top;
        }


        .info-table td:first-child {
            width: 130px;

            font-weight: 600;

            color: #374151;
        }


        /*
        ==========================================
        ITEMS TABLE
        ==========================================
        */

        .items-table {
            width: 100%;

            border-collapse: collapse;

            margin-top: 8px;
        }


        .items-table thead th {
            background: #111827;

            color: white;

            padding: 9px 10px;

            font-size: 11px;

            text-transform: uppercase;

            border: 1px solid #111827;
        }


        .items-table tbody td {
            padding: 8px 10px;

            border: 1px solid #d1d5db;

            vertical-align: middle;
        }


        .items-table tbody tr:nth-child(even) {
            background: #f9fafb;
        }


        .no {
            width: 50px;

            text-align: center;
        }


        .product {
            text-align: left;
        }


        .unit {
            width: 100px;

            text-align: center;
        }


        .qty {
            width: 110px;

            text-align: right;
        }


        .price {
            width: 170px;

            text-align: right;
        }


        .subtotal {
            width: 190px;

            text-align: right;
        }


        /*
        ==========================================
        TOTAL
        ==========================================
        */

        .summary-wrapper {
            display: flex;

            justify-content: flex-end;

            margin-top: 14px;
        }


        .summary-table {
            width: 380px;

            border-collapse: collapse;
        }


        .summary-table td {
            padding: 7px 10px;
        }


        .summary-table .label {
            text-align: right;

            font-weight: 600;

            color: #4b5563;
        }


        .summary-table .total {
            border-top: 2px solid #111827;

            font-size: 18px;

            font-weight: 800;
        }


        .summary-table .total-value {
            border-top: 2px solid #111827;

            font-size: 20px;

            font-weight: 800;

            text-align: right;
        }


        /*
        ==========================================
        NOTES
        ==========================================
        */

        .notes {
            margin-top: 25px;

            padding: 10px 12px;

            border: 1px solid #d1d5db;

            min-height: 55px;
        }


        .notes-title {
            font-weight: 700;

            margin-bottom: 5px;
        }


        /*
        ==========================================
        SIGNATURE
        ==========================================
        */

        .signature-wrapper {
            display: flex;

            justify-content: space-between;

            margin-top: 35px;

            page-break-inside: avoid;
        }


        .signature {
            width: 240px;

            text-align: center;
        }


        .signature-title {
            margin-bottom: 60px;

            font-weight: 600;
        }


        .signature-line {
            border-top: 1px solid #111827;

            padding-top: 6px;
        }


        /*
        ==========================================
        FOOTER
        ==========================================
        */

        .footer {
            margin-top: 25px;

            padding-top: 8px;

            border-top: 1px solid #d1d5db;

            display: flex;

            justify-content: space-between;

            font-size: 10px;

            color: #6b7280;
        }


        /*
        ==========================================
        PRINT CONTROL
        ==========================================
        */

        .print-button-wrapper {
            position: fixed;

            top: 20px;

            right: 20px;

            z-index: 999;
        }


        .print-button {
            background: #111827;

            color: white;

            border: none;

            padding: 10px 18px;

            border-radius: 6px;

            font-size: 13px;

            font-weight: 700;

            cursor: pointer;
        }


        .print-button:hover {
            background: #374151;
        }


        @media print {

            .print-button-wrapper {
                display: none !important;
            }

            body {
                background: white;
            }

            .page {
                width: 100%;
            }

            .items-table thead {
                display: table-header-group;
            }

            .items-table tr {
                page-break-inside: avoid;
            }

            .signature-wrapper {
                page-break-inside: avoid;
            }

        }


        @media screen {

            body {
                background: #e5e7eb;

                padding: 20px;
            }

            .page {
                background: white;

                max-width: 100%;

                padding: 20px;

                box-shadow:
                    0 4px 15px
                    rgba(0, 0, 0, 0.12);
            }

        }

    </style>

</head>


<body>


    <!-- ==========================================
         PRINT BUTTON
    =========================================== -->

    <div class="print-button-wrapper">

        <button
            type="button"
            class="print-button"
            onclick="window.print()"
        >
            🖨 Cetak PO
        </button>

    </div>


    <div class="page">


        <!-- ==========================================
             HEADER
        =========================================== -->

        <div class="header">

            <div class="company">

                <div class="company-name">
                    POS SYSTEM
                </div>

                <div class="company-info">
                    Dokumen Purchase Order / Barang Masuk
                    <br>
                    Sistem Manajemen Penjualan & Persediaan
                </div>

            </div>


            <div class="document-title">

                <h1>
                    PURCHASE ORDER
                </h1>

                <div class="subtitle">
                    Dokumen Pembelian
                </div>

            </div>

        </div>


        <!-- ==========================================
             INFORMATION
        =========================================== -->

        <div class="info-wrapper">


            <!-- PO -->

            <div class="info-box">

                <div class="info-title">
                    Informasi Purchase Order
                </div>

                <table class="info-table">

                    <tr>

                        <td>
                            Nomor PO
                        </td>

                        <td>
                            :
                            <strong>
                                {{ $purchase->invoice_no }}
                            </strong>
                        </td>

                    </tr>


                    <tr>

                        <td>
                            Tanggal
                        </td>

                        <td>
                            :
                            {{ $purchase->created_at?->format('d F Y H:i') }}
                        </td>

                    </tr>


                    <tr>

                        <td>
                            Dibuat Oleh
                        </td>

                        <td>
                            :
                            {{ $purchase->user?->name ?? '-' }}
                        </td>

                    </tr>

                </table>

            </div>


            <!-- SUPPLIER -->

            <div class="info-box">

                <div class="info-title">
                    Supplier / Agen
                </div>

                <table class="info-table">

                    <tr>

                        <td>
                            Nama Supplier
                        </td>

                        <td>
                            :
                            <strong>
                                {{ $purchase->supplier?->name ?? '-' }}
                            </strong>
                        </td>

                    </tr>


                    <tr>

                        <td>
                            Status
                        </td>

                        <td>
                            :
                            Barang Masuk
                        </td>

                    </tr>

                </table>

            </div>

        </div>


        <!-- ==========================================
             ITEM TABLE
        =========================================== -->

        <table class="items-table">

            <thead>

                <tr>

                    <th class="no">
                        No.
                    </th>

                    <th class="product">
                        Nama Produk
                    </th>

                    <th class="unit">
                        Satuan
                    </th>

                    <th class="qty">
                        Qty
                    </th>

                    <th class="price">
                        Harga Beli
                    </th>

                    <th class="subtotal">
                        Subtotal
                    </th>

                </tr>

            </thead>


            <tbody>

                @forelse(
                    $purchase->purchaseDetails
                    as $index => $detail
                )

                    <tr>

                        <td class="no">
                            {{ $index + 1 }}
                        </td>


                        <td class="product">

                            {{ $detail->product?->name ?? 'Produk Dihapus' }}

                        </td>


                        <td class="unit">

                            {{ $detail->product?->unit ?? '-' }}

                        </td>


                        <td class="qty">

                            {{ number_format(
                                (float) $detail->quantity,
                                3,
                                ',',
                                '.'
                            ) }}

                        </td>


                        <td class="price">

                            Rp
                            {{ number_format(
                                (float) $detail->price,
                                0,
                                ',',
                                '.'
                            ) }}

                        </td>


                        <td class="subtotal">

                            Rp
                            {{ number_format(
                                (float) $detail->subtotal,
                                0,
                                ',',
                                '.'
                            ) }}

                        </td>

                    </tr>

                @empty

                    <tr>

                        <td
                            colspan="6"
                            style="text-align:center;"
                        >
                            Tidak ada detail barang.

                        </td>

                    </tr>

                @endforelse

            </tbody>

        </table>


        <!-- ==========================================
             TOTAL
        =========================================== -->

        <div class="summary-wrapper">

            <table class="summary-table">

                <tr>

                    <td class="label">
                        Total Pembelian
                    </td>

                    <td class="total-value">

                        Rp
                        {{ number_format(
                            (float) $purchase->grand_total,
                            0,
                            ',',
                            '.'
                        ) }}

                    </td>

                </tr>

            </table>

        </div>


        <!-- ==========================================
             NOTES
        =========================================== -->

        <div class="notes">

            <div class="notes-title">
                Catatan
            </div>

            <div>
                Dokumen ini merupakan bukti pencatatan
                Purchase Order / barang masuk pada sistem.
            </div>

        </div>


        <!-- ==========================================
             SIGNATURE
        =========================================== -->

        <div class="signature-wrapper">


            <div class="signature">

                <div class="signature-title">
                    Supplier / Agen
                </div>

                <div class="signature-line">
                    Tanda Tangan
                </div>

            </div>


            <div class="signature">

                <div class="signature-title">
                    Penerima / Admin
                </div>

                <div class="signature-line">
                    {{ $purchase->user?->name ?? 'Tanda Tangan' }}
                </div>

            </div>


            <div class="signature">

                <div class="signature-title">
                    Mengetahui
                </div>

                <div class="signature-line">
                    Tanda Tangan
                </div>

            </div>

        </div>


        <!-- ==========================================
             FOOTER
        =========================================== -->

        <div class="footer">

            <span>
                Dicetak dari POS System
            </span>

            <span>
                PO: {{ $purchase->invoice_no }}
            </span>

            <span>
                {{ now()->format('d/m/Y H:i') }}
            </span>

        </div>


    </div>


    <script>

        /*
         * Saat halaman print dibuka,
         * user masih dapat melihat preview
         * terlebih dahulu.
         *
         * Tombol "Cetak PO" menggunakan
         * window.print().
         */

    </script>

</body>

</html>