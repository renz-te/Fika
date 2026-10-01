document.addEventListener('DOMContentLoaded', () => {
    const themeToggle = document.getElementById('themeToggle');
    if (themeToggle) {
        const currentTheme = localStorage.getItem('hrmsTheme') || 'light';
        document.body.dataset.theme = currentTheme;
        themeToggle.addEventListener('click', () => {
            const nextTheme = document.body.dataset.theme === 'dark' ? 'light' : 'dark';
            document.body.dataset.theme = nextTheme;
            localStorage.setItem('hrmsTheme', nextTheme);
        });
    }
    const clock = document.getElementById('digitalClock');
    if (clock) {
        setInterval(() => {
            const now = new Date();
            clock.textContent = now.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', second: '2-digit' });
        }, 1000);
    }
});
