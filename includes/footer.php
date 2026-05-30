<?php
// ============================================================
// includes/footer.php  —  Closing tags & footer bar
// ============================================================
declare(strict_types=1);

$is_logged_in = !empty($_SESSION['user_id']);
?>

<?php if ($is_logged_in): ?>
        </main><!-- /main page body -->

        <!-- Footer bar inside authenticated layout -->
        <footer class="border-t border-slate-100 bg-white px-6 py-3 flex items-center justify-between">
            <p class="text-xs text-slate-400">
                &copy; <?= date('Y') ?> CareSync EHR &mdash; Secure Health Records
            </p>
            <p class="text-xs text-slate-400 font-mono">
                PHP <?= PHP_MAJOR_VERSION ?>.<?= PHP_MINOR_VERSION ?> &nbsp;|&nbsp; PDO/MySQL
            </p>
        </footer>

    </div><!-- /main content area -->
</div><!-- /flex wrapper -->

<?php else: ?>

</div><!-- /unauthenticated centered wrapper -->

<?php endif; ?>
</body>
</html>
