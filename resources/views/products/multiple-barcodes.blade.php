<!DOCTYPE html>
<html>
<head>
    <title>Print Barcodes</title>
    <style>
        body { font-family: Arial, sans-serif; }

        .barcode-container {
            display: flex;
            flex-wrap: wrap;
            justify-content: flex-start;
        }

        .barcode-label {
            width: 180px;
            height: 140px;
            border: 1px solid #ccc;
            padding: 8px;
            margin: 8px;
            display: flex; /* flexbox layout */
            flex-direction: column; /* stack content vertically */
            justify-content: center; /* center vertically */
            align-items: center; /* center horizontally */
            text-align: center;
            vertical-align: top;
            page-break-inside: avoid;
            overflow: hidden;
        }

        .barcode-label img {
            max-width: 160px;
            height: 50px;
        }

        .barcode-label strong,
        .barcode-label small {
            display: block;
            word-wrap: break-word;
        }

        .no-print {
            text-align: center;
            margin-bottom: 20px;
        }

        @media print {
            .no-print {
                display: none;
            }
        }
    </style>
</head>
<body>

<div class="barcode-container">
    @foreach($barcodes as $barcode)
        <div class="barcode-label">
            <strong>{{ $barcode['product'] }}</strong>
            <small>{{ $barcode['variation'] }}</small>
            <img src="data:image/png;base64,{{ $barcode['barcodeImage'] }}" alt="barcode">
            <small>{{ $barcode['barcodeText'] }}</small>
            <strong>Rs. {{ $barcode['price'] }}</strong>
        </div>
    @endforeach
</div>


<div class="no-print">
    <button onclick="window.print()">Print</button>
</div>

</body>
</html>
