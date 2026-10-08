<?php
// includes/footer.php
?>
</main>

<style>
.site-footer{min-height:64px;padding:15px 28px;background:rgba(7,11,18,.94);border-top:1px solid rgba(59,130,246,.14);color:rgba(255,255,255,.75);font-size:12px}
.footer-flex{display:flex;justify-content:space-between;align-items:center;gap:18px;flex-wrap:wrap}.footer-brand,.footer-right-info{display:flex;align-items:center;gap:12px;flex-wrap:wrap}.hospital-tag-white{background:rgba(37,99,235,.16);color:#93c5fd;padding:5px 10px;border-radius:6px;font-weight:800;text-transform:uppercase;letter-spacing:1px;font-size:10px;border:1px solid rgba(59,130,246,.24)}.copyright-span{font-weight:500;color:#94a3b8}.footer-right-info{gap:18px}.support-pill-dark{background:rgba(0,0,0,.22);border:1px solid rgba(148,163,184,.16);padding:6px 12px;border-radius:50px;color:#e2e8f0;font-weight:700;font-size:11px}.support-pill-dark i{color:#60a5fa;margin-right:6px}.credit-text{color:#64748b}.dev-link-white{color:#fff;font-weight:700;text-decoration:none;border-bottom:1px solid rgba(255,255,255,.35)}.dev-link-white:hover{color:#93c5fd;border-bottom-color:#60a5fa}
@media(max-width:700px){.site-footer{padding:18px;text-align:center}.footer-flex,.footer-brand,.footer-right-info{justify-content:center;flex-direction:column;gap:9px}}
</style>

<footer class="footer site-footer">
    <div class="footer-flex">
        <div class="footer-brand">
            <div class="hospital-tag-white"><?= e(getCompanyName($conn) ?: 'FLEXIHUB') ?></div>
            <span class="copyright-span">&copy; <?= date('Y') ?> All Rights Reserved</span>
        </div>
        <div class="footer-right-info">
            <div class="support-pill-dark"><i class="fa-solid fa-headset"></i> Support: 0705259931</div>
            <div class="credit-text">Powered by <a href="https://www.flexiscriptlab.africa" class="dev-link-white" target="_blank" rel="noopener">FlexiScript Labs.</a></div>
        </div>
    </div>
</footer>
</div>
</div>
<script src="../assets/js/script.js"></script>
</body>
</html>
