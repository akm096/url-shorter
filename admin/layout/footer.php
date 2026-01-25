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
        style="background-color:var(--card-bg); margin:15% auto; padding:20px; border:1px solid var(--border-color); width:80%; max-width:300px; border-radius:var(--radius); text-align:center; position:relative;">
        <span class="close" style="color:#aaa; float:right; font-size:28px; font-weight:bold; cursor:pointer;"
            onclick="closeQrModal()">&times;</span>
        <h3 style="margin-top:0;">QR Kod</h3>
        <div id="qrcode"
            style="margin:20px auto; display:flex; justify-content:center; align-items: center; min-height: 200px;">
        </div>
        <p id="qrLink" style="font-size:0.85rem; color:var(--text-muted); word-break:break-all;"></p>
    </div>
</div>

<script>
    // Start QR Modal Logic
    const modal = document.getElementById("qrModal");

    function openQrModal(url) {
        modal.style.display = "flex";
        document.getElementById("qrcode").innerHTML = ""; // Clear
        document.getElementById("qrLink").textContent = url;

        setTimeout(() => {
            try {
                new QRCode(document.getElementById("qrcode"), {
                    text: url,
                    width: 200,
                    height: 200,
                    colorDark: "#000000",
                    colorLight: "#ffffff",
                    correctLevel: QRCode.CorrectLevel.H
                });
            } catch (e) {
                console.error("QR Code Error:", e);
                document.getElementById("qrcode").innerText = "QR Kod oluşturulamadı. Lütfen sayfayı yenileyin.";
            }
        }, 50);
    }

    function closeQrModal() {
        modal.style.display = "none";
    }

    window.onclick = function (event) {
        if (event.target == modal) {
            modal.style.display = "none";
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