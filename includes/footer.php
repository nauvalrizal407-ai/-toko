    </main>
</div>
<script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.12.1/dist/JsBarcode.all.min.js"></script>
<script>
(function () {
    if (window.JsBarcode) {
        document.querySelectorAll('.barcode-label').forEach(function (element) {
            JsBarcode(element, element.getAttribute('jsbarcode-value') || '', {
                format: 'CODE128',
                width: 1,
                height: 35,
                displayValue: true,
                fontSize: 10,
                margin: 0
            });
        });
    }

    var barcodeInput = document.getElementById('barcode-input');
    if (barcodeInput) {
        barcodeInput.focus();
        var barcodeTimer = null;
        barcodeInput.addEventListener('input', function () {
            window.clearTimeout(barcodeTimer);
            barcodeTimer = window.setTimeout(function () {
                if (barcodeInput.value.trim() !== '') {
                    barcodeInput.closest('form').requestSubmit();
                }
            }, 180);
        });
        barcodeInput.addEventListener('keydown', function (event) {
            if (event.key === 'Enter') {
                window.clearTimeout(barcodeTimer);
            }
        });
        barcodeInput.closest('form').addEventListener('submit', function () {
            window.setTimeout(function () {
                barcodeInput.value = '';
                barcodeInput.focus();
            }, 0);
        });
    }

    var kodeBarang = document.getElementById('kode-barang');
    var generateBarcode = document.getElementById('generate-barcode');
    if (kodeBarang && generateBarcode) {
        if (kodeBarang.value.trim() === '') {
            kodeBarang.value = 'BRG-' + Date.now().toString().slice(-10);
        }
        generateBarcode.addEventListener('click', function () {
            if (kodeBarang.value.trim() !== '') return;
            var random = Math.floor(100000 + Math.random() * 900000);
            kodeBarang.value = 'BRG-' + Date.now().toString().slice(-6) + '-' + random;
        });
    }
})();
</script>
</body>
</html>
