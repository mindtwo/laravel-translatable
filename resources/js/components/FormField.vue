<template>
    <div>
        <DefaultField
            v-for="locale in locales"
            :key="locale"
            :field="field"
            :errors="errors"
            :show-help-text="showHelpText"
            :full-width-content="fullWidthContent"
        >

            <FormLabel
                :label-for="labelFor || field.uniqueKey"
                class="space-x-1"
                :class="{ 'mb-2': shouldShowHelpText }"
            >
                <span>
                    {{ `${fieldName} (${locale.toUpperCase()})` }}
                </span>
                <span v-if="field.required" class="text-red-500 text-sm">
                    {{ __('*') }}
                </span>
            </FormLabel>

            <template #field>
                <input
                    :id="field.attribute"
                    type="text"
                    class="w-full form-control form-input form-control-bordered"
                    :class="errorClasses"
                    :placeholder="field.name"
                    v-model="value[locale]"
                />
            </template>
        </DefaultField>
    </div>
</template>

<script>
import { FormField, HandlesValidationErrors } from 'laravel-nova'

export default {
    mixins: [FormField, HandlesValidationErrors],

    props: ['resourceName', 'resourceId', 'field'],


    data() {
        return {
            values: {},
        }
    },

    computed: {
        locales() {
            return this.field.locales;
        },
        key() {
            return this.field.key;
        },
        fieldName() {
            return this.field.name;
        },
    },

    methods: {
        /*
        * Set the initial, internal value for the field.
        */
        setInitialValue() {
            this.value = {};

            for (const locale of this.locales) {
                this.value[locale] = this.field.value[locale] || '';
            }
        },

        /**
         * Fill the given FormData object with the field's internal value.
         */
        fill(formData) {
            for (const locale of this.locales) {
                formData.append(`${this.field.attribute}[${locale}]`, this.value[locale] || '');
            }
        },
    },
}
</script>
