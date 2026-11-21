(() => {
  'use strict';

  const placeholder = document.currentScript;
  const currentPage = placeholder.dataset.page;
  const items = [
    {
      "id": "map",
      "href": "/",
      "label": "Карта",
      "icon": "<svg viewBox=\"0 0 24 24\" aria-hidden=\"true\"><path d=\"M19 10c0 5-7 10-7 10s-7-5-7-10a7 7 0 1 1 14 0Z\"/><circle cx=\"12\" cy=\"10\" r=\"2\"/></svg>"
    },
    {
      "id": "list",
      "href": "/?panel=list",
      "label": "Списком",
      "icon": "<svg viewBox=\"0 0 24 24\" aria-hidden=\"true\"><path d=\"M5 6h14M5 12h14M5 18h14\"/></svg>"
    },
    {
      "id": "filters",
      "href": "/?panel=filters",
      "label": "Фильтры",
      "icon": "<svg viewBox=\"0 0 24 24\" aria-hidden=\"true\"><path d=\"M4 8h4m4 0h8M4 16h10m4 0h2\"/><circle cx=\"10\" cy=\"8\" r=\"2\"/><circle cx=\"16\" cy=\"16\" r=\"2\"/></svg>"
    },
    {
      "id": "faq",
      "href": "/faq",
      "label": "FAQ",
      "icon": "<svg viewBox=\"0 0 24 24\" aria-hidden=\"true\"><circle cx=\"12\" cy=\"12\" r=\"9\"/><path d=\"M9.1 9a3 3 0 0 1 5.9.6c0 2-3 2.5-3 4.4m0 3h.01\"/></svg>"
    },
    {
      "id": "account",
      "href": "/account.html",
      "label": "Кабинет",
      "icon": "<svg viewBox=\"0 0 24 24\" aria-hidden=\"true\"><circle cx=\"12\" cy=\"8\" r=\"4\"/><path d=\"M4.5 21a7.5 7.5 0 0 1 15 0\"/></svg>"
    }
  ];

  function renderItem({ id, href, label, icon }) {
    const content = `${icon}<span>${label}</span>`;
    if (currentPage === 'map' && ['map', 'list', 'filters'].includes(id)) {
      const state = id === 'filters'
        ? 'aria-expanded="false" aria-controls="filterSheet"'
        : `aria-pressed="${id === 'map'}"`;
      return `<button class="mobile-tab" type="button" data-mobile-tab="${id}" ${state}>${content}</button>`;
    }

    const state = id === currentPage ? ' aria-current="page"' : '';
    return `<a class="mobile-tab" href="${href}"${state}>${content}</a>`;
  }

  const [map, list, ...links] = items.map(renderItem);
  const nav = document.createElement('nav');
  nav.className = 'mobile-nav';
  nav.setAttribute('aria-label', 'Основная навигация');
  nav.innerHTML = `
    <div class="mobile-view-switch" role="group" aria-label="Вид зарядных станций">
      ${map}
      ${list}
    </div>
    ${links.join('\n')}
  `;
  placeholder.replaceWith(nav);
})();
