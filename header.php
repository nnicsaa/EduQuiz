<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? $page_title : 'EDUQuiz!'; ?></title>
    
    <!-- PWA Manifest & Theme -->
    <link rel="manifest" href="manifest.json">
    <meta name="theme-color" content="#7048e8">
    
    <!-- Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;600;800&display=swap" rel="stylesheet">
    
    <!-- Service Worker Registration -->
    <script>
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', () => {
                navigator.serviceWorker.register('sw.js')
                    .then(registration => console.log('PWA Service Worker registered successfully!'))
                    .catch(error => console.log('Service Worker registration failed:', error));
            });
        }
    </script>