<?php
declare(strict_types=1);

if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
    die();
}

use Bitrix\Main\UI\Extension;
use Bitrix\Main\Web\Json;

Extension::load(['ui.vue3']);

if (!empty($arResult['IS_EMPTY'])) {
    ?>
    <div class="cm-checkout-empty">
        <p>Ваша корзина пуста.</p>
        <a href="<?= htmlspecialcharsbx($arResult['PATH_TO_BASKET']) ?>" class="cm-btn">Вернуться в корзину</a>
    </div>
    <?php
    return;
}
?>

<div id="cm-checkout-app" class="cm-checkout" v-cloak>
    <!-- Экран успешного оформления и оплаты -->
    <div v-if="createdOrder" class="cm-checkout__success">
        <h2>Заказ №{{ createdOrder.accountNumber }} успешно оформлен!</h2>
        <p>Номер заказа в системе: <strong>{{ createdOrder.orderId }}</strong>.</p>

        <!-- Блок онлайн-оплаты -->
        <div v-if="createdOrder.payment && !createdOrder.payment.isCash" class="cm-payment-box">
            <p v-if="redirectingToGateway" class="cm-payment-redirect-msg">
                Перенаправляем на платёжный шлюз «{{ createdOrder.payment.paySystemName }}»...
            </p>

            <div v-if="createdOrder.payment.error" class="cm-error">
                Не удалось автоматически открыть форму оплаты: {{ createdOrder.payment.error }}.
                Вы можете оплатить заказ из личного кабинета.
            </div>

            <div v-if="createdOrder.payment.paymentUrl" class="cm-payment-actions">
                <a :href="createdOrder.payment.paymentUrl" class="cm-btn cm-submit-btn">
                    Перейти к оплате ({{ createdOrder.payment.paySystemName }})
                </a>
            </div>

            <!-- Контейнер для HTML-формы или виджета платёжного шлюза -->
            <iframe
                v-if="createdOrder.payment.html"
                class="cm-payment-gateway-html"
                :srcdoc="createdOrder.payment.html"
                :sandbox="autoRedirectPayment
                    ? 'allow-forms allow-scripts allow-popups allow-top-navigation'
                    : 'allow-forms allow-scripts'"
                referrerpolicy="no-referrer"
                title="Переход к оплате"
            ></iframe>
        </div>

        <div class="cm-success-links">
            <a href="<?= htmlspecialcharsbx($arResult['PATH_TO_PERSONAL']) ?>" class="cm-btn cm-btn--secondary">Перейти к моим заказам</a>
        </div>
    </div>

    <!-- Основная сетка оформления -->
    <div v-else class="cm-checkout__grid">
        <div class="cm-checkout__main">
            <!-- 1. Тип плательщика -->
            <section class="cm-checkout__section" v-if="state.personTypes.length > 1">
                <h3>1. Тип плательщика</h3>
                <div class="cm-checkout__radio-group">
                    <label v-for="pt in state.personTypes" :key="pt.id" class="cm-radio-pill" :class="{ active: form.personTypeId === pt.id }">
                        <input type="radio" :value="pt.id" v-model="form.personTypeId" @change="onPersonTypeChange">
                        <span>{{ pt.name }}</span>
                    </label>
                </div>
            </section>

            <section class="cm-checkout__section" v-if="state.profiles && state.profiles.length">
                <h3>Профиль покупателя</h3>
                <select v-model="form.profileId" @change="selectBuyerProfile">
                    <option :value="0">Заполнить данные вручную</option>
                    <option v-for="profile in state.profiles" :key="profile.id" :value="profile.id">
                        {{ profile.name }}
                    </option>
                </select>
            </section>
            <section class="cm-checkout__section" v-if="saveProfileEnabled && userAuthorized">
                <label class="cm-checkbox">
                    <input type="checkbox" v-model="form.saveProfile">
                    Сохранить заполненные данные в профиль покупателя
                </label>
                <input v-if="form.saveProfile" type="text" v-model="form.profileName" maxlength="255" placeholder="Название профиля">
                <p class="cm-muted">Профиль будет сохранён после успешного создания заказа.</p>
            </section>

            <!-- 2. Данные покупателя -->
            <section class="cm-checkout__section">
                <h3>2. Данные покупателя и доставка</h3>

                <div v-for="prop in state.properties" :key="prop.id" class="cm-field">
                    <label :for="'prop_' + prop.code">
                        {{ prop.name }} <span v-if="prop.required" class="req">*</span>
                    </label>

                    <!-- Автокомплит местоположения -->
                    <div v-if="prop.isLocation" class="cm-autocomplete">
                        <input
                            :id="'prop_' + prop.code"
                            type="text"
                            v-model="locationQuery"
                            @input="onLocationInput(prop.code)"
                            placeholder="Начните вводить город..."
                            autocomplete="off"
                        >
                        <ul v-if="locationSuggestions.length" class="cm-autocomplete__list">
                            <li v-for="loc in locationSuggestions" :key="loc.code" @click="selectLocation(prop.code, loc)">
                                {{ loc.label }}
                            </li>
                        </ul>
                    </div>

                    <!-- Подсказки DaData для адреса -->
                    <div v-else-if="dadataEnabled && isAddressProp(prop.code)" class="cm-autocomplete">
                        <input
                            :id="'prop_' + prop.code"
                            type="text"
                            v-model="form.properties[prop.code]"
                            @input="onDadataInput('address', prop.code, $event.target.value)"
                            placeholder="Введите улицу, дом, квартиру..."
                            autocomplete="off"
                        >
                        <ul v-if="activeDadataField === prop.code && dadataSuggestions.length" class="cm-autocomplete__list">
                            <li v-for="(item, idx) in dadataSuggestions" :key="idx" @click="selectDadataAddress(prop.code, item)">
                                <strong>{{ item.value }}</strong>
                            </li>
                        </ul>
                    </div>

                    <!-- Подсказки DaData для юрлиц (ИНН / Название компании) -->
                    <div v-else-if="dadataEnabled && isPartyProp(prop.code)" class="cm-autocomplete">
                        <input
                            :id="'prop_' + prop.code"
                            type="text"
                            v-model="form.properties[prop.code]"
                            @input="onDadataInput('party', prop.code, $event.target.value)"
                            placeholder="Введите ИНН, ОГРН или название организации..."
                            autocomplete="off"
                        >
                        <ul v-if="activeDadataField === prop.code && dadataSuggestions.length" class="cm-autocomplete__list">
                            <li v-for="(item, idx) in dadataSuggestions" :key="idx" @click="selectDadataParty(item)">
                                <strong>{{ item.name }}</strong>
                                <small class="cm-suggestion-sub">ИНН {{ item.inn }}<span v-if="item.kpp"> / КПП {{ item.kpp }}</span> — {{ item.address }}</small>
                            </li>
                        </ul>
                    </div>

                    <!-- Многострочное поле -->
                    <textarea
                        v-else-if="prop.type === 'TEXTAREA'"
                        :id="'prop_' + prop.code"
                        v-model="form.properties[prop.code]"
                        rows="3"
                    ></textarea>

                    <!-- Обычное поле -->
                    <input
                        v-else
                        :id="'prop_' + prop.code"
                        :type="isPhoneProp(prop.code) ? 'tel' : 'text'"
                        :inputmode="isPhoneProp(prop.code) ? 'tel' : 'text'"
                        :placeholder="isPhoneProp(prop.code) ? '+7 (___) ___-__-__' : ''"
                        v-model="form.properties[prop.code]"
                        @input="onPhoneInput(prop.code, $event)"
                    >

                    <div v-if="fieldErrors['PROP_' + prop.code]" class="cm-error">
                        {{ fieldErrors['PROP_' + prop.code] }}
                    </div>
                </div>
            </section>

            <!-- 3. Доставка -->
            <section class="cm-checkout__section">
                <h3>3. Способ доставки</h3>
                <div v-if="!state.deliveries.length" class="cm-muted">
                    Для выбранного местоположения нет доступных служб доставки.
                </div>
                <label
                    v-for="del in state.deliveries"
                    :key="del.id"
                    class="cm-card-radio"
                    :class="{ active: form.deliveryId === del.id }"
                >
                    <input type="radio" :value="del.id" v-model="form.deliveryId" @change="recalculate">
                    <div class="cm-card-radio__body">
                        <div class="cm-card-radio__head">
                            <strong>{{ del.name }}</strong>
                            <span class="cm-price">{{ del.price > 0 ? formatPrice(del.price) : 'Бесплатно' }}</span>
                        </div>
                        <small v-if="del.period" class="cm-period">Срок доставки: {{ del.period }}</small>
                        <p v-if="del.description" v-html="del.description"></p>
                    </div>
                </label>
            </section>

            <!-- 4. Оплата -->
            <section class="cm-checkout__section">
                <h3>4. Способ оплаты</h3>
                <label
                    v-for="ps in state.paySystems"
                    :key="ps.id"
                    class="cm-card-radio"
                    :class="{ active: form.paySystemId === ps.id }"
                >
                    <input type="radio" :value="ps.id" v-model="form.paySystemId" @change="recalculate">
                    <div class="cm-card-radio__body">
                        <strong>{{ ps.name }}</strong>
                        <p v-if="ps.description" v-html="ps.description"></p>
                    </div>
                </label>
            </section>

            <!-- 5. Комментарий -->
            <section class="cm-checkout__section">
                <h3>5. Комментарий к заказу</h3>
                <textarea v-model="form.comment" rows="3" placeholder="Пожелания к заказу или доставке..."></textarea>
            </section>
        </div>

        <!-- Правая колонка -->
        <aside class="cm-checkout__sidebar">
            <div class="cm-summary" :class="{ 'is-loading': loading }">
                <h3>Состав заказа</h3>

                <ul class="cm-summary__items">
                    <li v-for="item in state.items" :key="item.id">
                        <span>{{ item.name }} <small>× {{ item.quantity }}</small></span>
                        <strong>{{ formatPrice(item.sum) }}</strong>
                    </li>
                </ul>

                <!-- Промокод -->
                <div class="cm-coupon">
                    <input type="text" v-model="form.coupon" placeholder="Промокод">
                    <button type="button" @click="recalculate" :disabled="loading">ОК</button>
                </div>
                <div v-if="state.appliedCoupons && state.appliedCoupons.length" class="cm-applied-coupons">
                    Применён промокод: <strong>{{ state.appliedCoupons.join(', ') }}</strong>
                    <button type="button" class="cm-link-btn" @click="clearCoupon">Сбросить</button>
                </div>

                <!-- Итоги -->
                <div class="cm-summary__totals">
                    <div><span>Товары:</span><span>{{ formatPrice(state.totals.basketBase) }}</span></div>
                    <div v-if="state.totals.discountPrice > 0" class="cm-discount">
                        <span>Скидка:</span><span>−{{ formatPrice(state.totals.discountPrice) }}</span>
                    </div>
                    <div><span>Доставка:</span><span>{{ formatPrice(state.totals.deliveryPrice) }}</span></div>
                    <div class="cm-total-row">
                        <span>Итого:</span>
                        <strong>{{ formatPrice(state.totals.orderPrice) }}</strong>
                    </div>
                </div>

                <div v-if="generalErrors.length" class="cm-errors-box">
                    <p v-for="(err, idx) in generalErrors" :key="idx">{{ err }}</p>
                </div>

                <button
                    type="button"
                    class="cm-btn cm-submit-btn"
                    @click="submitOrder"
                    :disabled="loading || submitting"
                >
                    {{ submitting ? 'Оформление...' : 'Оформить заказ' }}
                </button>
            </div>
        </aside>
    </div>
</div>

<script>
BX.ready(function () {
    const { createApp, ref, reactive } = BX.Vue3;
    const initialState = <?= Json::encode($arResult['INITIAL_STATE'], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
    const checkoutRequestId = '<?= bin2hex(random_bytes(16)) ?>';
    const signedParameters = '<?= CUtil::JSEscape($arResult['SIGNED_PARAMS']) ?>';
    const dadataEnabled = <?= $arResult['DADATA_ENABLED'] ? 'true' : 'false' ?>;
    const autoRedirectPayment = <?= $arResult['AUTO_REDIRECT_PAYMENT'] ? 'true' : 'false' ?>;
    const saveProfileEnabled = <?= $arResult['SAVE_USER_PROFILE_ENABLED'] ? 'true' : 'false' ?>;
    const userAuthorized = <?= $arResult['USER_AUTHORIZED'] ? 'true' : 'false' ?>;

    createApp({
        setup() {
            const state = ref(initialState);
            const loading = ref(false);
            const submitting = ref(false);
            const createdOrder = ref(null);
            const redirectingToGateway = ref(false);
            const fieldErrors = ref({});
            const generalErrors = ref([]);

            const locationQuery = ref(initialState.locationName || '');
            const locationSuggestions = ref([]);
            let locationTimer = null;

            const activeDadataField = ref('');
            const dadataSuggestions = ref([]);
            let dadataTimer = null;

            const form = reactive({
                personTypeId: initialState.personTypeId,
                profileId: initialState.selectedProfileId || 0,
                properties: { ...initialState.propValues },
                deliveryId: initialState.selectedDeliveryId,
                paySystemId: initialState.selectedPaySystemId,
                comment: '',
                coupon: '',
                clearCoupon: false,
                requestId: checkoutRequestId,
                saveProfile: false,
                profileName: 'Профиль покупателя'
            });

            const isAddressProp = (code) => ['ADDRESS', 'COMPANY_ADR', 'LEGAL_ADDRESS'].includes(code);
            const isPartyProp = (code) => ['INN', 'COMPANY'].includes(code);

            const isPhoneProp = (code) => ['PHONE', 'PERSONAL_PHONE', 'CONTACT_PHONE', 'MOBILE'].includes(
                String(code).toUpperCase()
            );

            const onPhoneInput = (code, event) => {
                if (!isPhoneProp(code)) {
                    return;
                }

                let digits = String(event.target.value || '').replace(/\D/g, '');
                if (digits.startsWith('8')) {
                    digits = '7' + digits.slice(1);
                } else if (!digits.startsWith('7') && digits.length > 0) {
                    digits = '7' + digits;
                }
                digits = digits.slice(0, 11);

                let formatted = digits.length === 0 ? '' : '+7';
                if (digits.length > 1) {
                    formatted += ' (' + digits.slice(1, 4);
                }
                if (digits.length >= 4) {
                    formatted += ') ' + digits.slice(4, 7);
                }
                if (digits.length >= 8) {
                    formatted += '-' + digits.slice(7, 9);
                }
                if (digits.length >= 10) {
                    formatted += '-' + digits.slice(9, 11);
                }

                form.properties[code] = formatted;
                event.target.value = formatted;
            };

            const syncFormWithState = (newState) => {
                state.value = newState;
                form.personTypeId = newState.personTypeId;
                form.profileId = newState.selectedProfileId || 0;
                form.deliveryId = newState.selectedDeliveryId;
                form.paySystemId = newState.selectedPaySystemId;

                const mergedProps = { ...newState.propValues };
                Object.keys(form.properties).forEach((k) => {
                    if (form.properties[k] !== '' && mergedProps[k] !== undefined) {
                        mergedProps[k] = form.properties[k];
                    }
                });
                form.properties = mergedProps;

                if (newState.locationName && !locationQuery.value) {
                    locationQuery.value = newState.locationName;
                }
            };

            const recalculate = () => {
                loading.value = true;
                generalErrors.value = [];

                BX.ajax.runComponentAction('codematrix:order.checkout', 'recalculate', {
                    mode: 'class',
                    signedParameters: signedParameters,
                    data: { payload: form }
                }).then((response) => {
                    form.clearCoupon = false;
                    syncFormWithState(response.data);
                    loading.value = false;
                }, (response) => {
                    form.clearCoupon = false;
                    generalErrors.value = (response.errors || []).map(e => e.message);
                    loading.value = false;
                });
            };

            const onPersonTypeChange = () => {
                form.profileId = 0;
                form.properties = {};
                recalculate();
            };

            const selectBuyerProfile = () => {
                form.properties = {};
                recalculate();
            };

            const clearCoupon = () => {
                form.coupon = '';
                form.clearCoupon = true;
                recalculate();
            };

            const onLocationInput = (propCode) => {
                clearTimeout(locationTimer);
                // Любое редактирование текста аннулирует ранее выбранный Bitrix location CODE.
                form.properties[propCode] = '';
                locationSuggestions.value = [];
                if (locationQuery.value.trim().length < 2) {
                    return;
                }
                locationTimer = setTimeout(() => {
                    BX.ajax.runComponentAction('codematrix:order.checkout', 'searchLocations', {
                        mode: 'class',
                        signedParameters: signedParameters,
                        data: { query: locationQuery.value }
                    }).then((response) => {
                        locationSuggestions.value = response.data.items || [];
                    });
                }, 300);
            };

            const selectLocation = (propCode, loc) => {
                locationQuery.value = loc.label;
                form.properties[propCode] = loc.code;
                locationSuggestions.value = [];
                recalculate();
            };

            const onDadataInput = (type, propCode, query) => {
                clearTimeout(dadataTimer);
                activeDadataField.value = propCode;
                if (!query || query.trim().length < 2) {
                    dadataSuggestions.value = [];
                    return;
                }
                dadataTimer = setTimeout(() => {
                    BX.ajax.runComponentAction('codematrix:order.checkout', 'suggestDadata', {
                        mode: 'class',
                        signedParameters: signedParameters,
                        data: { type: type, query: query }
                    }).then((response) => {
                        dadataSuggestions.value = response.data.items || [];
                    });
                }, 300);
            };

            const selectDadataAddress = (propCode, item) => {
                form.properties[propCode] = item.value;
                if (item.postalCode && form.properties.ZIP !== undefined) {
                    form.properties.ZIP = item.postalCode;
                }

                const locProp = state.value.properties.find(p => p.isLocation);
                if (locProp) {
                    // Не оставляем старый код, если DaData не сопоставилась с каталогом Bitrix.
                    form.properties[locProp.code] = item.locationCode || '';
                    if (item.city) {
                        locationQuery.value = item.city;
                    }
                    if (item.locationCode) {
                        recalculate();
                    }
                }

                dadataSuggestions.value = [];
                activeDadataField.value = '';
            };

            const selectDadataParty = (item) => {
                if (form.properties.COMPANY !== undefined && item.name) {
                    form.properties.COMPANY = item.name;
                }
                if (form.properties.INN !== undefined && item.inn) {
                    form.properties.INN = item.inn;
                }
                if (form.properties.KPP !== undefined && item.kpp) {
                    form.properties.KPP = item.kpp;
                }
                if (form.properties.COMPANY_ADR !== undefined && item.address) {
                    form.properties.COMPANY_ADR = item.address;
                }

                dadataSuggestions.value = [];
                activeDadataField.value = '';
            };

            const handlePaymentAfterCreate = (orderData) => {
                const payment = orderData.payment;
                if (!payment || payment.isCash) {
                    return;
                }

                if (payment.paymentUrl && autoRedirectPayment) {
                    redirectingToGateway.value = true;
                    window.location.href = payment.paymentUrl;
                }
            };

            const submitOrder = () => {
                submitting.value = true;
                fieldErrors.value = {};
                generalErrors.value = [];

                BX.ajax.runComponentAction('codematrix:order.checkout', 'createOrder', {
                    mode: 'class',
                    signedParameters: signedParameters,
                    data: { payload: form }
                }).then((response) => {
                    createdOrder.value = response.data;
                    submitting.value = false;
                    handlePaymentAfterCreate(response.data);
                }, (response) => {
                    (response.errors || []).forEach((err) => {
                        if (err.code && err.code.startsWith('PROP_')) {
                            fieldErrors.value[err.code] = err.message;
                        } else {
                            generalErrors.value.push(err.message);
                        }
                    });
                    submitting.value = false;
                });
            };

            const formatPrice = (val) => {
                return new Intl.NumberFormat('ru-RU', {
                    style: 'currency',
                    currency: 'RUB',
                    maximumFractionDigits: 0
                }).format(val || 0);
            };

            return {
                state,
                form,
                loading,
                submitting,
                createdOrder,
                redirectingToGateway,
                fieldErrors,
                generalErrors,
                locationQuery,
                locationSuggestions,
                dadataEnabled,
                saveProfileEnabled,
                userAuthorized,
                activeDadataField,
                dadataSuggestions,
                isAddressProp,
                isPartyProp,
                isPhoneProp,
                onPhoneInput,
                onPersonTypeChange,
                selectBuyerProfile,
                recalculate,
                clearCoupon,
                onLocationInput,
                selectLocation,
                onDadataInput,
                selectDadataAddress,
                selectDadataParty,
                submitOrder,
                formatPrice
            };
        }
    }).mount('#cm-checkout-app');
});
</script>