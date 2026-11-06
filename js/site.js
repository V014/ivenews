(() => {
    const body = document.body;
    const themeButton = document.querySelector('.theme-toggle');
    const themeSetting = localStorage.getItem('ivenews-theme');

    if (themeSetting === 'dark') body.classList.add('dark-mode');
    themeButton?.addEventListener('click', () => {
        body.classList.toggle('dark-mode');
        localStorage.setItem('ivenews-theme', body.classList.contains('dark-mode') ? 'dark' : 'light');
    });

    const menuButton = document.querySelector('.menu-toggle');
    const topicNav = document.querySelector('#topic-nav');
    menuButton?.addEventListener('click', () => {
        const isOpen = menuButton.getAttribute('aria-expanded') === 'true';
        menuButton.setAttribute('aria-expanded', String(!isOpen));
        topicNav?.classList.toggle('is-open', !isOpen);
    });

    const shareButton = document.querySelector('.share-button');
    shareButton?.addEventListener('click', async () => {
        const originalText = shareButton.textContent;
        try {
            if (navigator.share) {
                await navigator.share({ title: document.title, url: window.location.href });
            } else if (navigator.clipboard) {
                await navigator.clipboard.writeText(window.location.href);
                shareButton.textContent = 'Link copied ✓';
                window.setTimeout(() => { shareButton.textContent = originalText; }, 2200);
            } else {
                window.prompt('Copy this story link:', window.location.href);
            }
        } catch (error) {
            if (error.name !== 'AbortError') console.error('Unable to share this story.', error);
        }
    });

    const progress = document.querySelector('.reading-progress span');
    if (progress) {
        const updateProgress = () => {
            const page = document.documentElement;
            const available = page.scrollHeight - window.innerHeight;
            const percent = available > 0 ? Math.min(100, Math.max(0, (window.scrollY / available) * 100)) : 0;
            progress.style.width = `${percent}%`;
        };
        window.addEventListener('scroll', updateProgress, { passive: true });
        window.addEventListener('resize', updateProgress);
        updateProgress();
    }
})();
