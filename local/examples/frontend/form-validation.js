/**
 * Клиентская проверка обязательных полей средствами браузера.
 *
 * Подключайте файл после разметки или с атрибутом defer.
 * Серверная валидация обязательна: этот скрипт не является защитой данных.
 *
 * Пример:
 * <form data-validate>
 *   <label>
 *     E-mail
 *     <input type="email" name="email" required>
 *   </label>
 *   <button type="submit">Отправить</button>
 * </form>
 */

document.addEventListener('submit', (event) => {
    const form = event.target;

    if (!(form instanceof HTMLFormElement) || !form.matches('[data-validate]')) {
        return;
    }

    if (!form.checkValidity()) {
        event.preventDefault();
        form.reportValidity();
    }
});
