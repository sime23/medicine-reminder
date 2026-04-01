<?php
/**
 * Anti-FOUC Theme Initializer
 * 
 * Include this as the FIRST thing inside <head> on every authenticated page.
 * It reads the PHP session theme AND syncs with localStorage so the correct
 * data-theme is set before the browser paints anything.
 */
$pageTheme = $_SESSION['theme'] ?? 'light';
?>
<script>
(function() {
    // Read from localStorage first (instant, no server round-trip)
    // Fall back to the PHP-session value rendered server-side
    var stored  = null;
    try { stored = localStorage.getItem('medremind_theme'); } catch(e) {}
    var theme   = stored || '<?php echo addslashes($pageTheme); ?>';
    if (theme === 'dark') {
        document.documentElement.setAttribute('data-theme', 'dark');
    } else {
        document.documentElement.removeAttribute('data-theme');
    }
    // Keep localStorage in sync with whatever the server says is authoritative
    try { localStorage.setItem('medremind_theme', '<?php echo addslashes($pageTheme); ?>'); } catch(e) {}
})();
</script>
