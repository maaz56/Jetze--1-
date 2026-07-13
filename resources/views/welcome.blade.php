<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>Jetze</title>
    <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="any">
    @include('partials.google-tag')
    
    <!-- Styles -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <!-- Font Awesome kit -->
    <script src="https://kit.fontawesome.com/8a3b522169.js" crossorigin="anonymous"></script>
{{--    <script src="https://assets.duffel.com/components/3.3.1/duffel-payments.js"></script>--}}
    
    <!-- Favicon Animation Script -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Create animated favicon functionality
            const AnimatedFavicon = {
                // Animation properties
                isAnimating: false,
                animationInterval: null,
                
                // Initialize the favicon
                init: function() {
                    // Create link elements if they don't exist
                    if (!document.querySelector('link[rel="icon"]')) {
                        const iconLink = document.createElement('link');
                        iconLink.rel = 'icon';
                        iconLink.type = 'image/png';
                        iconLink.id = 'favicon';
                        document.head.appendChild(iconLink);
                    }
                    
                    if (!document.querySelector('link[rel="apple-touch-icon"]')) {
                        const appleLink = document.createElement('link');
                        appleLink.rel = 'apple-touch-icon';
                        appleLink.id = 'apple-touch-icon';
                        document.head.appendChild(appleLink);
                    }
                    
                    // Load the image
                    const img = new Image();
                    img.crossOrigin = "anonymous";
                    img.onload = () => {
                        this.originalImage = img;
                        this.start();
                    };
                    img.onerror = () => {
                        console.error("Failed to load favicon image");
                    };
                    
                    img.src = '/favicon.ico';
                },
                
                // Start animation
                start: function() {
                    if (this.isAnimating || !this.originalImage) return;
                    this.isAnimating = true;
                    
                    const favicon = document.querySelector('link[rel="icon"]');
                    const appleIcon = document.querySelector('link[rel="apple-touch-icon"]');
                    let rotation = 0;
                    let scale = 1.0;
                    let scaleDirection = -0.01;
                    
                    this.animationInterval = setInterval(() => {
                        // Create a canvas to manipulate the image
                        const canvas = document.createElement('canvas');
                        canvas.width = 32;
                        canvas.height = 32;
                        const ctx = canvas.getContext('2d');
                        
                        // Clear canvas
                        ctx.clearRect(0, 0, canvas.width, canvas.height);
                        
                        // Save context state
                        ctx.save();
                        
                        // Move to center of canvas
                        ctx.translate(canvas.width/2, canvas.height/2);
                        
                        // Rotate
                        ctx.rotate(rotation * Math.PI / 180);
                        
                        // Scale (pulsing effect)
                        ctx.scale(scale, scale);
                        
                        // Draw image centered
                        ctx.drawImage(
                            this.originalImage, 
                            -canvas.width/2, 
                            -canvas.height/2, 
                            canvas.width, 
                            canvas.height
                        );
                        
                        // Restore context
                        ctx.restore();
                        
                        // Update favicon
                        const faviconUrl = canvas.toDataURL('image/png');
                        favicon.href = faviconUrl;
                        
                        // Create larger version for apple-touch-icon
                        const largeCanvas = document.createElement('canvas');
                        largeCanvas.width = 180;
                        largeCanvas.height = 180;
                        const largeCtx = largeCanvas.getContext('2d');
                        
                        largeCtx.clearRect(0, 0, 180, 180);
                        largeCtx.save();
                        largeCtx.translate(90, 90);
                        largeCtx.rotate(rotation * Math.PI / 180);
                        largeCtx.scale(scale, scale);
                        largeCtx.drawImage(this.originalImage, -90, -90, 180, 180);
                        largeCtx.restore();
                        
                        appleIcon.href = largeCanvas.toDataURL('image/png');
                        
                        // Increment rotation for next frame
                        rotation = (rotation + 1) % 360;
                        
                        // Update scale for pulsing effect
                        scale += scaleDirection;
                        if (scale <= 0.95) {
                            scaleDirection = 0.01;
                        } else if (scale >= 1.05) {
                            scaleDirection = -0.01;
                        }
                    }, 50);
                },
                
                // Stop animation
                stop: function() {
                    if (!this.isAnimating) return;
                    clearInterval(this.animationInterval);
                    this.isAnimating = false;
                }
            };
            
            // Initialize the animated favicon
            AnimatedFavicon.init();
            
            // Optional: pause animation when tab is not visible to save resources
            document.addEventListener('visibilitychange', function() {
                if (document.hidden) {
                    AnimatedFavicon.stop();
                } else {
                    AnimatedFavicon.start();
                }
            });
        });
    </script>
</head>

<body>
    <div id="app"></div>
</body>

</html>
