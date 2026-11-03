        </main>
    </div>
</div>

<div id="toast-container"></div>

<!-- Scripts -->
<script src="<?= BASE_URL; ?>assets/js/countdown.js"></script>
<script src="<?= BASE_URL; ?>assets/js/app.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Start polling availability every 5 seconds
    if (typeof startSeatPolling === 'function') {
        startSeatPolling('<?= BASE_URL; ?>api/availability.php');
    }
});
</script>

</body>
</html>

