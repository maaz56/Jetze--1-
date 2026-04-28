```vue
<template>
  <div class="social-bar">
    <!-- <a href="https://www.youtube.com/@Jetze" target="_blank" aria-label="YouTube" class="social-link">
      <img src="/public/youtube.png" alt="YouTube" class="social-img">
    </a>
    <a href="https://www.tiktok.com/@globe_trips_fzco" target="_blank" aria-label="TikTok" class="social-link">
      <img src="/public/tiktok.png" alt="TikTok" class="social-img">
    </a>
    <a href="https://x.com/Jetze" target="_blank" aria-label="X (Twitter)" class="social-link">
      <img src="/public/twitter.png" alt="X (Twitter)" class="social-img">
    </a>
    <a href="https://www.instagram.com/azr.skyline" target="_blank" aria-label="Instagram" class="social-link">
      <img src="/public/insta.png" alt="Instagram" class="social-img">
    </a>
    <a href="https://www.facebook.com/Jetze" target="_blank" aria-label="Facebook" class="social-link">
      <img src="/public/fb.png" alt="Facebook" class="social-img">
    </a>
    <a href="https://www.linkedin.com/in/globe-tours-and-trips-fzco-a1641537a" target="_blank" aria-label="LinkedIn" class="social-link">
      <img src="/public/link.png" alt="LinkedIn" class="social-img">
    </a> -->
  </div>
</template>

<style scoped>
.social-bar {
  display: flex;
  flex-direction: row;
  gap: 8px;
  justify-content: center;
  align-items: center;
  padding: 8px 0;
  width: 100%;
  box-sizing: border-box;
}
.social-link {
  display: flex;
  align-items: center;
  justify-content: center;
  text-decoration: none;
  transition: transform 0.2s;
}
.social-img {
  width: 34px;
  height: 34px;
  object-fit: cover;
}
.social-link:hover {
  transform: scale(1.2);
}
</style>
```