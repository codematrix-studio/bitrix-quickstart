<?php
declare(strict_types=1);

if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
    die();
}

use Bitrix\Main\UI\Extension;
use Bitrix\Main\Web\Json;

Extension::load(['ui.vue3']);

$basketUrl = htmlspecialcharsbx($arParams['PATH_TO_BASKET'] ?? '/personal/cart/');
$personalUrl = htmlspecialcharsbx($arParams['PATH_TO_PERSONAL'] ?? '/personal/orders/');

if (!empty($arResult['IS_EMPTY'])) {
    echo '<div class="cm-checkout-empty">Ваша корзина пуста. <a href="' . $basketUrl . '">Вернуться в корзину</a></div>';
    return;
}
?>

<div id="cm-checkout-app" class="cm-checkout">
    <div v-if="createdOrder" class="cm-checkout__success">
        <h2>Заказ №{{ createdOrder.accountNumber }} успешно оформлен!</h2>
        <p>Детали заказа отправлены на указанный E-Mail. Внутренний ID: {{ createdOrder.orderId }}</p>
        <a href="<?= $personalUrl ?>" class="cm-btn">Перейти в личный кабинет</a>
    </div>

    <div v-else class="cm-checkout__grid">
        <div class="cm-checkout__main">
            <section class="cm-checkout__section" v-if="state.personTypes.length > 1">
                <h3>1. Тип плательщика</h3>
                <div class="cm-radio-row">
                    <label v-for="pt in state.personTypes" :key="pt.id" class="cm-pill">
                        <input type="radio" :value="pt.id" v-model="form.personTypeId" @change="recalculate">
                        <span>{{ pt.name }}</span>
                    </label>
                </div>
            </section>

            <section class="cm-checkout__section">
                <h3>2. Данные покупателя и доставка</h3>
                <div v-for="prop in state.properties" :key="prop.id" class="cm-field">
                    <label>{{ prop.name }} <span v-if="prop.required" class="req">*</span></label>

                    <div v-if="prop.isLocation" class="cm-location-wrap">
                        <input
                            type="text"
                            v-model="locationQuery"
                            @input="onLocationInput"
                            placeholder="Начните вводить город..."
                            autocomplete="off"
                        >
                        <ul v-if="locationSuggestions.length" class="cm-location-dropdown">
                            <li
                                v-for="loc in locationSuggestions"
                                :key="loc.code"
                                @click="selectLocation(prop.code, loc)"
                            >
                                {{ loc.name }}
                            </li>
                        </ul>
                    </div>

                    <textarea
                        v-else-if="prop.type === 'TEXTAREA'"
                        v-model="form.properties[prop.code]"
                        rows="3"
                    ></textarea>

                    <input
                        v-else
                        type="text"
                        v-model="form.properties[prop.code]"
                    >

                    <div v-if="fieldErrors['PROP_' + prop.code]" class="cm-field-error">
                        {{ fieldErrors['PROP_' + prop.code] }}
                    </div>
                </div>
            </section>

            <section class="cm-checkout__section">
                <h3>3. Способ доставки</h3>
                <div v-if="!state.deliveries.length" class="cm-muted">Нет доступных служб доставки для выбранного города</div>
                <label v-for="del in state.deliveries" :key="del.id" class="cm-option-card">
                    <input type="radio" :value="del.id" v-model="form.deliveryId" @change="recalculate">
                    <div class="cm-option-card__body">
                        <div class="cm-option-card__top">
                            <strong>{{ del.name }}</strong>
                            <span class="cm-price">{{ del.price > 0 ? formatPrice(del.price) : 'Бесплатно' }}</span>
                        </div>
                        <div v-if="del.period" class="cm-muted">Срок доставки: {{ del.period }}</div>
                        <div v-if="del.description" class="cm-desc" v-html="del.description"></div>
                    </div>
                </label>
            </section>

            <section class="cm-checkout__section">
                <h3>4. Способ оплаты</h3>
                <label v-for="ps in state.paySystems" :key="ps.id" class="cm-option-card">
                    <input type="radio" :value="ps.id" v-model="form.paySystemId" @change="recalculate">
                    <div class="cm-option-card__body">
                        <strong>{{ ps.name }}</strong>
                        <div v-if="ps.description" class="cm-desc" v-html="ps.description"></div>
                    </div>
                </label>
            </section>

            <section class="cm-checkout__section">
                <h3>5. Комментарий к заказу</h3>
                <textarea v-model="form.comment" rows="3" placeholder="Дополнительная информация для менеджера или курьера"></textarea>
            </section>
        </div>

        <aside class="cm-checkout__sidebar">
            <div class="cm-summary" :class="{ 'is-loading': loading }">
                <h3>Состав заказа</h3>
                <ul class="cm-summary__list">
                    <li v-for="item in state.items" :key="item.id">
                        <span>{{ item.name }} × {{ item.quantity }}</span>
                        <strong>{{ formatPrice(item.sum) }}</strong>
                    </li>
                </ul>

                <div class="cm-coupon">
                    <input type="text" v-model="form.coupon" placeholder="Промокод">
                    <button type="button" @click="recalculate" :disabled="loading">ОК</button>
                </div>

                <div class="cm-totals">
                    <div class="cm-totals__row"><span>Товары:</span><span>{{ formatPrice(state.totals.basketBase) }}</span></div>
                    <div v-if="state.totals.discountPrice > 0" class="cm-totals__row is-discount">
                        <span>Скидка:</span><span>−{{ formatPrice(state.totals.discountPrice) }}</span>
                    </div>
                    <div class="cm-totals__row"><span>Доставка:</span><span>{{ formatPrice(state.totals.deliveryPrice) }}</span></div>
                    <div class="cm-totals__row is-final">
                        <span>Итого:</span><strong>{{ formatPrice(state.totals.orderPrice) }}</strong>
                    </div>
                </div>

                <div v-if="generalErrors.length" class="cm-alert-error">
                    <div v-for="(err, i) in generalErrors" :key="i">{{ err }}</div>
                </div>

                <button type="button" class="cm-btn cm-btn--full" @click="submitOrder" :disabled="loading || submitting">
                    {{ submitting ? 'Оформление...' : 'Оформить заказ' }}
                </button>
            </div>
        </aside>
    </div>
</div>

<script>
BX.ready(function () {
    const { createApp, ref, reactive } = BX.Vue3;
    const initialState = <?= Json::encode($arResult['INITIAL_STATE']) ?>;

    createApp({
        setup() {
            const state = ref(initialState);
            const loading = ref(false);
            const submitting = ref(false);
            const createdOrder = ref(null);
            const fieldErrors = ref({});
            const generalErrors = ref([]);
            const locationQuery = ref(initialState.locationName || '');
            const locationSuggestions = ref([]);
            let locationTimer = null;

            const form = reactive({
                personTypeId: initialState.personTypeId,
                properties: { ...initialState.propValues },
                deliveryId: initialState.selectedDeliveryId,
                paySystemId: initialState.selectedPaySystemId,
                comment: '',
                coupon: ''
            });

            const syncState = (newState) => {
                state.value = newState;
                form.personTypeId = newState.personTypeId;
                form.deliveryId = newState.selectedDeliveryId;
                form.paySystemId = newState.selectedPaySystemId;
                form.properties = { ...newState.propValues, ...form.properties };
                if (newState.locationName && !locationSuggestions.value.length) {
                    locationQuery.value = newState.locationName;
                }
            };

            const onLocationInput = () => {
                clearTimeout(locationTimer);
                if (locationQuery.value.trim().length < 2) {
                    locationSuggestions.value = [];
                    return;
                }
                locationTimer = setTimeout(() => {
                    BX.ajax.runComponentAction('codematrix:order.checkout', 'searchLocation', {
                        mode: 'class',
                        data: { query: locationQuery.value }
                    }).then((res) => {
                        locationSuggestions.value = res.data.locations || [];
                    });
                }, 300);
            };

            const selectLocation = (propCode, loc) => {
                form.properties[propCode] = loc.code;
                locationQuery.value = loc.name;
                locationSuggestions.value = [];
                recalculate();
            };

            const recalculate = () => {
                loading.value = true;
                generalErrors.value = [];

                BX.ajax.runComponentAction('codematrix:order.checkout', 'recalculate', {
                    mode: 'class',
                    data: { payload: form }
                }).then((res) => {
                    syncState(res.data);
                    loading.value = false;
                }, (res) => {
                    generalErrors.value = (res.errors || []).map(e => e.message);
                    loading.value = false;
                });
            };

            const submitOrder = () => {
                submitting.value = true;
                fieldErrors.value = {};
                generalErrors.value = [];

                BX.ajax.runComponentAction('codematrix:order.checkout', 'createOrder', {
                    mode: 'class',
                    data: { payload: form }
                }).then((res) => {
                    createdOrder.value = res.data;
                    submitting.value = false;
                }, (res) => {
                    (res.errors || []).forEach((err) => {
                        if (err.code && err.code.startsWith('PROP_')) {
                            fieldErrors.value[err.code] = err.message;
                        } else {
                            generalErrors.value.push(err.message);
                        }
                    });
                    submitting.value = false;
                });
            };

            const formatPrice = (v) => new Intl.NumberFormat('ru-RU', {
                style: 'currency',
                currency: 'RUB',
                maximumFractionDigits: 0
            }).format(v || 0);

            return {
                state, form, loading, submitting, createdOrder,
                fieldErrors, generalErrors, locationQuery, locationSuggestions,
                onLocationInput, selectLocation, recalculate, submitOrder, formatPrice
            };
        }
    }).mount('#cm-checkout-app');
});
</script>
