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
    const amount = settings.total_amount;
    const date = settings.total_updated_at;
    if (amount !== null && amount !== undefined && amount !== '' &&
        Number.isFinite(Number(amount)) &&
        typeof date === 'string' && /^\d{4}-\d{2}-\d{2}$/.test(date)) {
      const parsed = new Date(date + 'T12:00:00');
      if (!Number.isNaN(parsed.getTime())) {
        card.querySelector('#donationTotalAmount').textContent =
          new Intl.NumberFormat('ru-RU', { maximumFractionDigits: 2 })
            .format(Number(amount)) + ' сом';
        card.querySelector('#donationTotalDate').textContent =
          'Обновлено ' + new Intl.DateTimeFormat('ru-RU', {day:'numeric',month:'long',year:'numeric'}).format(parsed);
        card.querySelector('#donationTotals').hidden = false;
      }
    }
    card.hidden = false;
  })
  .catch(() => {});
