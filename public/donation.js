const card = document.getElementById('donationCard');
const toggle = card.querySelector('.donation-toggle');
const image = card.querySelector('img');
const endpoint = '/api/donation.php';

toggle.addEventListener('click', () => {
  const expanded = toggle.getAttribute('aria-expanded') === 'true';
  toggle.setAttribute('aria-expanded', String(!expanded));
  image.hidden = expanded;
  if (!expanded && !image.hasAttribute('src')) image.src = endpoint + '?qr=1';
});

card.querySelector('.donation-close').addEventListener('click', () => {
  card.hidden = true;
});

fetch(endpoint)
  .then(response => response.ok ? response.json() : null)
  .then(settings => {
    if (!settings?.enabled) return;
    card.querySelector('p').textContent = settings.message;
    toggle.textContent = settings.button;
    card.hidden = false;
  })
  .catch(() => {});
