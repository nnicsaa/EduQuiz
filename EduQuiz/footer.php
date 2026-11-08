<script>
    document.addEventListener("DOMContentLoaded", function() {
        const alerts = document.querySelectorAll('.alert');
        alerts.forEach(alert => {
            setTimeout(() => {
                // Add a smooth fade out transition
                alert.style.transition = "opacity 0.5s ease";
                alert.style.opacity = "0";
                
                // Remove the element from the DOM after the fade completes
                setTimeout(() => {
                    alert.remove();
                }, 500);
            }, 3000); // 3000 milliseconds = 3 seconds
        });
    });
</script>
</body>
</html>