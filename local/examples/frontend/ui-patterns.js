/**
 * Небольшие UI-паттерны без jQuery и сторонних плагинов.
 *
 * Разметка вкладок:
 * <div data-tabs>
 *   <button type="button" data-tab-target="tab-one" aria-selected="true">Первая</button>
 *   <button type="button" data-tab-target="tab-two" aria-selected="false">Вторая</button>
 *   <section id="tab-one" data-tab-panel>Содержимое первой</section>
 *   <section id="tab-two" data-tab-panel hidden>Содержимое второй</section>
 * </div>
 *
 * Разметка переключателя:
 * <button type="button" data-toggle-target="details" aria-expanded="false">Подробнее</button>
 * <div id="details" hidden>Дополнительное содержимое</div>
 */

document.addEventListener('click', (event) => {
    const tabButton = event.target.closest('[data-tab-target]');

    if (tabButton) {
        const tabs = tabButton.closest('[data-tabs]');
        const targetId = tabButton.dataset.tabTarget;
        const targetPanel = targetId ? document.getElementById(targetId) : null;

        if (!tabs || !targetPanel || !tabs.contains(targetPanel)) {
            return;
        }

        tabs.querySelectorAll('[data-tab-target]').forEach((button) => {
            const selected = button === tabButton;
            button.setAttribute('aria-selected', String(selected));
            button.classList.toggle('is-active', selected);
        });

        tabs.querySelectorAll('[data-tab-panel]').forEach((panel) => {
            panel.hidden = panel !== targetPanel;
        });

        return;
    }

    const toggleButton = event.target.closest('[data-toggle-target]');

    if (!toggleButton) {
        return;
    }

    const target = document.getElementById(toggleButton.dataset.toggleTarget);

    if (!target) {
        return;
    }

    const expanded = toggleButton.getAttribute('aria-expanded') === 'true';
    target.hidden = expanded;
    toggleButton.setAttribute('aria-expanded', String(!expanded));
    toggleButton.classList.toggle('is-active', !expanded);
});
