<?php
// includes/footer.php
?>
</main>

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
