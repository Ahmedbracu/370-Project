  </div><!-- /content -->
</div><!-- /main -->

<script>
// Live clock
function updateClock() {
    const now = new Date();
    const el = document.getElementById('clock');
    if (el) {
        el.textContent = now.toLocaleTimeString('en-US', {
            hour: '2-digit', minute: '2-digit',
            hour12: true
        });
    }
}
updateClock();
setInterval(updateClock, 1000);

// Smooth hover ripple on stat cards
document.querySelectorAll('.stat-card, .card').forEach(card => {
    card.addEventListener('mouseenter', function() {
        this.style.transition = 'all .3s cubic-bezier(.4,0,.2,1)';
    });
});
</script>
</body>
</html>
