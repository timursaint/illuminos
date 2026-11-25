/* =====================================================================
   Illuminos — страница оплаты подписки.
   Маска номера карты, проверка промокода через api/promo.php
   и пересчёт итоговой суммы без перезагрузки страницы.
   ===================================================================== */
'use strict';

document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('checkoutForm');
    if (!form) return;

    const numberInput = document.getElementById('card_number');
    const expiryInput = document.getElementById('card_expiry');
    const cardFields = document.getElementById('cardFields');
    const methods = form.querySelectorAll('input[name="method"]');
    const promoInput = document.getElementById('promo_code');
    const promoBtn = document.getElementById('applyPromo');
    const promoMsg = document.getElementById('promoMsg');
    const discountRow = document.getElementById('discountRow');
    const discountValue = document.getElementById('discountValue');
    const totalValue = document.getElementById('totalValue');
    const paySum = document.getElementById('payButtonSum');

    const baseSum = parseFloat(document.querySelector('[data-base-sum]').dataset.baseSum) || 0;
    const tariffId = form.dataset.tariffId;

    const formatMoney = (value) => `${value.toLocaleString('ru-RU', { maximumFractionDigits: 2 })} ₽`;

    /* --- Маска номера карты: 0000 0000 0000 0000 --- */
    numberInput.addEventListener('input', () => {
        const digits = numberInput.value.replace(/\D/g, '').slice(0, 16);
        numberInput.value = digits.replace(/(.{4})/g, '$1 ').trim();
    });

    /* --- Маска срока действия: ММ/ГГ --- */
    expiryInput.addEventListener('input', () => {
        const digits = expiryInput.value.replace(/\D/g, '').slice(0, 4);
        expiryInput.value = digits.length > 2 ? `${digits.slice(0, 2)}/${digits.slice(2)}` : digits;
        if (digits.length === 1 && Number(digits) > 1) {
            expiryInput.value = `0${digits}/`;
        }
    });

    /* --- Показ блока карты только для оплаты картой --- */
    const syncMethod = () => {
        const selected = form.querySelector('input[name="method"]:checked')?.value;
        cardFields.style.display = selected === 'card' ? '' : 'none';
        [numberInput, expiryInput, document.getElementById('card_cvv')].forEach((input) => {
            input.required = selected === 'card';
        });
    };
    methods.forEach((radio) => radio.addEventListener('change', syncMethod));
    syncMethod();

    /* --- Промокод --- */
    const resetPromo = () => {
        discountRow.hidden = true;
        totalValue.textContent = formatMoney(baseSum);
        paySum.textContent = formatMoney(baseSum);
        promoInput.dataset.applied = '';
    };

    promoBtn.addEventListener('click', async () => {
        const code = promoInput.value.trim();
        promoMsg.textContent = '';
        promoMsg.className = 'promo-msg';

        if (!code) {
            promoMsg.textContent = 'Введите промокод';
            promoMsg.classList.add('bad');
            return;
        }

        promoBtn.disabled = true;
        try {
            const response = await fetch(`${window.APP.api}/promo.php`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                body: JSON.stringify(Number(tariffId) > 0
                    ? { code, tariff_id: Number(tariffId) }
                    : { code, amount: baseSum }),
            });
            const data = await response.json();

            if (!data.ok) {
                resetPromo();
                promoMsg.textContent = data.message || 'Промокод не подошёл';
                promoMsg.classList.add('bad');
                return;
            }

            discountRow.hidden = false;
            discountValue.textContent = `−${formatMoney(data.discount)}`;
            totalValue.textContent = formatMoney(data.total);
            paySum.textContent = formatMoney(data.total);
            promoMsg.textContent = data.message;
            promoMsg.classList.add('ok');
            promoInput.dataset.applied = data.code;
        } catch (error) {
            promoMsg.textContent = 'Не удалось проверить промокод';
            promoMsg.classList.add('bad');
        } finally {
            promoBtn.disabled = false;
        }
    });

    promoInput.addEventListener('input', () => {
        if (promoInput.dataset.applied && promoInput.value.trim().toUpperCase() !== promoInput.dataset.applied) {
            resetPromo();
            promoMsg.textContent = '';
        }
    });

    /* --- Предотвращаем двойную отправку формы --- */
    form.addEventListener('submit', () => {
        const button = form.querySelector('button[type="submit"]');
        button.disabled = true;
        button.textContent = 'Обрабатываем платёж…';
    });
});
