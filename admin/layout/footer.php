</main>

<footer style="margin-top: 50px; text-align: center; color: var(--text-muted); font-size: 0.85rem;">
    &copy;
    <?php echo date('Y'); ?> URL Kısaltıcı. Tüm hakları saklıdır.
</footer>
</div>

<!-- QR Code Modal -->
<div id="qrModal" class="modal"
    style="display:none; position:fixed; z-index:1000; left:0; top:0; width:100%; height:100%; overflow:auto; background-color:rgba(0,0,0,0.5); align-items:center; justify-content:center;">
    <div class="modal-content"
        style="background-color:var(--card-bg); margin:5% auto; padding:20px; border:1px solid var(--border-color); width:90%; max-width:400px; border-radius:var(--radius); text-align:center; position:relative;">
        <span class="close" style="color:#aaa; float:right; font-size:28px; font-weight:bold; cursor:pointer;"
            onclick="closeQrModal()">&times;</span>
        <h3 style="margin-top:0;">QR Kod Özelleştir</h3>

        <div id="qrContainer" style="position: relative; margin: 20px auto; display: inline-block;">
            <div id="qrcode"
                style="display:flex; justify-content:center; align-items: center; min-height: 200px; background: white; padding: 10px; border-radius: 8px;">
            </div>
            <img id="qrLogo" src=""
                style="display:none; position:absolute; top:50%; left:50%; transform:translate(-50%, -50%); width:40px; height:40px; border-radius:8px; background:white; padding:4px; box-shadow: 0 4px 6px rgba(0,0,0,0.15);">
        </div>

        <div class="qr-options"
            style="text-align: left; margin-top: 15px; display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
            <div class="form-group" style="grid-column: span 2;">
                <label style="font-size: 0.8rem; display: block; margin-bottom: 4px;">Logo URL (Opsiyonel)</label>
                <input type="text" id="qrLogoInput" class="form-control" placeholder="https://.../logo.png"
                    style="font-size: 0.8rem; width: 100%;" oninput="updateQR()">
            </div>
            <div class="form-group">
                <label style="font-size: 0.8rem; display: block; margin-bottom: 4px;">Ön Plan</label>
                <input type="color" id="qrFgColor" value="#000000"
                    style="width: 100%; height: 30px; padding: 2px; cursor: pointer;" onchange="updateQR()">
            </div>
            <div class="form-group">
                <label style="font-size: 0.8rem; display: block; margin-bottom: 4px;">Arka Plan</label>
                <input type="color" id="qrBgColor" value="#ffffff"
                    style="width: 100%; height: 30px; padding: 2px; cursor: pointer;" onchange="updateQR()">
            </div>
            <div class="form-group">
                <label style="font-size: 0.8rem; display: block; margin-bottom: 4px;">Boyut</label>
                <select id="qrSize" class="form-control" style="font-size: 0.8rem; padding: 5px;" onchange="updateQR()">
                    <option value="150">Küçük</option>
                    <option value="200" selected>Normal</option>
                    <option value="300">Büyük</option>
                    <option value="400">Çok Büyük</option>
                </select>
            </div>
            <div class="form-group">
                <label style="font-size: 0.8rem; display: block; margin-bottom: 4px;">&nbsp;</label>
                <button type="button" class="btn btn-primary" style="width: 100%; padding: 5px; font-size: 0.8rem;"
                    onclick="downloadQR()">İndir (PNG)</button>
            </div>
        </div>

        <p id="qrLink" style="font-size:0.75rem; color:var(--text-muted); word-break:break-all; margin-top: 15px;"></p>
    </div>
</div>

<script>
    // Start QR Modal Logic
    const modal = document.getElementById("qrModal");
    let currentQrUrl = '';

    function openQrModal(url) {
        currentQrUrl = url;
        modal.style.display = "flex";
        document.getElementById("qrLink").textContent = url;
        updateQR();
    }

    function updateQR() {
        if (!currentQrUrl) return;

        const container = document.getElementById("qrcode");
        container.innerHTML = ""; // Clear existing

        const colorDark = document.getElementById("qrFgColor").value;
        const colorLight = document.getElementById("qrBgColor").value;
        const size = parseInt(document.getElementById("qrSize").value);
        const logoUrl = document.getElementById("qrLogoInput").value.trim();
        const logoImg = document.getElementById("qrLogo");

        setTimeout(() => {
            try {
                new QRCode(container, {
                    text: currentQrUrl,
                    width: size,
                    height: size,
                    colorDark: colorDark,
                    colorLight: colorLight,
                    correctLevel: QRCode.CorrectLevel.H
                });

                // Handle Logo Overlay
                if (logoUrl) {
                    logoImg.crossOrigin = "Anonymous";
                    logoImg.src = logoUrl;
                    logoImg.style.display = "block";
                    // Logo size proportional to QR size (approx 18% for better readability)
                    const logoSize = Math.floor(size * 0.18);
                    logoImg.style.width = logoSize + "px";
                    logoImg.style.height = logoSize + "px";
                } else {
                    logoImg.style.display = "none";
                }
            } catch (e) {
                console.error("QR Code Error:", e);
                container.innerText = "QR Kod oluşturulamadı.";
            }
        }, 50);
    }

    function downloadQR() {
        const canvas = document.querySelector("#qrcode canvas");
        if (!canvas) {
            alert("QR Kod hazır değil.");
            return;
        }

        // If there's a logo, we need to merge it into a new canvas before downloading
        const logoImg = document.getElementById("qrLogo");
        const finalCanvas = document.createElement("canvas");
        finalCanvas.width = canvas.width;
        finalCanvas.height = canvas.height;
        const ctx = finalCanvas.getContext("2d");

        // Draw QR
        ctx.drawImage(canvas, 0, 0);

        // Draw Logo if visible
        if (logoImg.style.display !== "none" && logoImg.complete && logoImg.naturalWidth !== 0) {
            const lSize = parseInt(logoImg.style.width);
            const x = (canvas.width - lSize) / 2;
            const y = (canvas.height - lSize) / 2;

            // Draw background for logo to make it pop
            ctx.fillStyle = "white";
            ctx.fillRect(x - 2, y - 2, lSize + 4, lSize + 4);
            ctx.drawImage(logoImg, x, y, lSize, lSize);
        }

        const link = document.createElement('a');
        link.download = 'qrcode.png';

        try {
            link.href = finalCanvas.toDataURL("image/png");
            link.click();
        } catch (e) {
            console.error(e);
            alert("Seçilen logo güvenlik nedeniyle (CORS) görselin indirilmesini engelliyor.\n\nQR Kod logosuz olarak indirilecek.");
            link.href = canvas.toDataURL("image/png"); // Fallback to raw QR
            link.click();
        }
    }

    function closeQrModal() {
        modal.style.display = "none";
    }

    window.onclick = function (event) {
        if (event.target == modal) {
            closeQrModal();
        }
    }
    // End QR Modal Logic

    // Dark Mode Toggle Logic
    const toggleBtn = document.getElementById('themeToggle');
    const html = document.documentElement;

    // Set initial icon
    if (html.classList.contains('dark-mode')) {
        toggleBtn.textContent = '☀️';
    } else {
        toggleBtn.textContent = '🌙';
    }

    toggleBtn.addEventListener('click', () => {
        html.classList.toggle('dark-mode');

        if (html.classList.contains('dark-mode')) {
            localStorage.setItem('theme', 'dark');
            html.setAttribute('data-theme', 'dark');
            toggleBtn.textContent = '☀️';
        } else {
            localStorage.setItem('theme', 'light');
            html.removeAttribute('data-theme');
            toggleBtn.textContent = '🌙';
        }
    });

    // Clipboard Copy Logic (Global)
    function copyUrl(text, btn) {
        if (navigator.clipboard) {
            navigator.clipboard.writeText(text).then(function () { showCopied(btn); });
        } else {
            var inp = document.createElement('input');
            inp.value = text;
            document.body.appendChild(inp);
            inp.select();
            try { document.execCommand('copy'); showCopied(btn); } catch (e) { }
            document.body.removeChild(inp);
        }
    }

    function showCopied(btn) {
        var orig = btn.textContent;
        btn.textContent = '✓';
        btn.classList.add('btn-success');
        setTimeout(function () {
            btn.textContent = orig;
            btn.classList.remove('btn-success');
        }, 1200);
    }
</script>
</body>

</html>