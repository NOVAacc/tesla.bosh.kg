if ('serviceWorker' in navigator) {
  navigator.serviceWorker.register('/sw.js').catch(error => console.warn('PWA:', error));
}

const installButton = document.getElementById('pwaInstall');
const installHelp = document.getElementById('pwaInstallHelp');
if (installButton && installHelp) {
  const standalone = matchMedia('(display-mode: standalone)');
  let pendingInstall;

  function syncButton() {
    installButton.hidden = standalone.matches || navigator.standalone === true;
  }
  syncButton();
  standalone.addEventListener('change', syncButton);

  window.addEventListener('beforeinstallprompt', event => {
    event.preventDefault();
    pendingInstall = event;
  });
  window.addEventListener('appinstalled', () => {
    pendingInstall = undefined;
    installButton.hidden = true;
  });

  installButton.addEventListener('click', async () => {
    if (pendingInstall) {
      const prompt = pendingInstall;
      pendingInstall = undefined;
      try {
        await prompt.prompt();
        const choice = await prompt.userChoice;
        if (choice.outcome === 'accepted') installButton.hidden = true;
        return;
      } catch {
        // Show the browser's manual installation steps below.
      }
    }
    installHelp.showModal();
  });
}
