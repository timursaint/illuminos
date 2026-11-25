/* =====================================================================
   Illuminos — валидация форм входа и регистрации.
   Работает и на отдельных страницах, и в модальных окнах главной:
   ошибки показываются под полями без перезагрузки, данные уходят fetch'ем
   на login.php / register.php (серверная валидация обязательна).
   ===================================================================== */
'use strict';

document.addEventListener('DOMContentLoaded', () => {
    initAuthModals();
    document.querySelectorAll('form.js-auth-form').forEach(initAuthForm);
});

/* ---------------------- Модальные окна ---------------------- */
function initAuthModals() {
    const modals = document.querySelectorAll('[data-auth-modal]');
    if (!modals.length) return;

    const open = (mode) => {
        modals.forEach((modal) => { modal.hidden = modal.dataset.authModal !== mode; });
        document.body.classList.add('is-locked');
        const first = document.querySelector(`[data-auth-modal="${mode}"] input`);
        if (first) setTimeout(() => first.focus(), 50);
    };

    const closeAll = () => {
        modals.forEach((modal) => { modal.hidden = true; });
        document.body.classList.remove('is-locked');
    };

    document.addEventListener('click', (event) => {
        const opener = event.target.closest('[data-auth-open]');
        if (opener) {
            event.preventDefault();
            open(opener.dataset.authOpen);
            return;
        }
        if (event.target.closest('[data-auth-close]')) {
            closeAll();
        }
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') closeAll();
    });
}

/* ------------------------- Правила проверки ------------------------- */
const rules = {
    last_name: (v) => (v.trim().length >= 2 ? '' : 'Укажите фамилию (минимум 2 символа).'),
    first_name: (v) => (v.trim().length >= 2 ? '' : 'Укажите имя (минимум 2 символа).'),
    login: (v) => (/^[a-zA-Z0-9_]{3,32}$/.test(v.trim())
        ? '' : 'Логин: 3–32 символа, латиница, цифры и «_».'),
    email: (v) => (/^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(v.trim()) ? '' : 'Введите корректный e-mail.'),
    password: (v) => {
        if (v.length < 8) return 'Пароль должен содержать минимум 8 символов.';
        if (!/[A-Za-zА-Яа-я]/.test(v) || !/\d/.test(v)) return 'Пароль должен содержать буквы и цифру.';
        return '';
    },
    password_repeat: (v) => (v === '' ? 'Повторите пароль.' : ''),
};

function setFieldError(form, field, message) {
    const box = form.querySelector(`[data-error="${field}"]`);
    const input = form.querySelector(`[name="${field}"]`);
    if (box) {
        box.textContent = message || '';
        box.classList.toggle('is-visible', Boolean(message));
    }
    if (input && input.classList.contains('field__input')) {
        input.classList.toggle('is-invalid', Boolean(message));
    }
}

function clearErrors(form) {
    form.querySelectorAll('.field__error').forEach((el) => {
        el.textContent = '';
        el.classList.remove('is-visible');
    });
    form.querySelectorAll('.field__input').forEach((el) => el.classList.remove('is-invalid'));
    const box = form.querySelector('.js-form-error');
    if (box) box.style.display = 'none';
}

function showFormError(form, message) {
    const box = form.querySelector('.js-form-error');
    if (!box) return;
    box.textContent = message;
    box.style.display = 'block';
}

/* --------------------------- Инициализация --------------------------- */
function initAuthForm(form) {
    const mode = form.dataset.mode === 'register' ? 'register' : 'login';
    const fields = mode === 'register'
        ? ['last_name', 'first_name', 'login', 'email', 'password', 'password_repeat']
        : ['login', 'password'];

    const validateField = (field) => {
        const input = form.querySelector(`[name="${field}"]`);
        if (!input) return true;

        let message = field === 'login' && mode === 'login'
            ? (input.value.trim() ? '' : 'Введите логин или e-mail.')
            : rules[field](input.value);

        // В режиме входа пароль проверяем только на заполненность
        if (field === 'password' && mode === 'login') {
            message = input.value ? '' : 'Введите пароль.';
        }

        if (field === 'password_repeat' && message === '') {
            const main = form.querySelector('[name="password"]');
            if (main && main.value !== input.value) message = 'Пароли не совпадают.';
        }

        setFieldError(form, field, message);
        return message === '';
    };

    fields.forEach((field) => {
        const input = form.querySelector(`[name="${field}"]`);
        if (!input) return;
        input.addEventListener('blur', () => validateField(field));
        input.addEventListener('input', () => {
            if (input.classList.contains('is-invalid')) validateField(field);
        });
    });

    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        clearErrors(form);

        let valid = true;
        fields.forEach((field) => { if (!validateField(field)) valid = false; });

        if (mode === 'register') {
            const consent = form.querySelector('[name="consent"]');
            if (consent && !consent.checked) {
                setFieldError(form, 'consent', 'Подтвердите согласие с правилами регистрации.');
                valid = false;
            }
        }

        if (!valid) return;

        const button = form.querySelector('button[type="submit"]');
        const initialText = button.textContent;
        button.disabled = true;
        button.textContent = mode === 'register' ? 'Создаём аккаунт…' : 'Входим…';

        try {
            const response = await fetch(form.action, {
                method: 'POST',
                body: new FormData(form),
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
            });
            const data = await response.json();

            if (data.ok) {
                window.location.href = data.redirect;
                return;
            }

            if (data.errors) {
                Object.entries(data.errors).forEach(([field, message]) => setFieldError(form, field, message));
            }
            showFormError(form, data.message || 'Проверьте заполнение полей.');
        } catch (error) {
            showFormError(form, 'Ошибка соединения. Попробуйте ещё раз.');
        } finally {
            button.disabled = false;
            button.textContent = initialText;
        }
    });
}
