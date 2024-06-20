<template>
    <div>
        <DefaultField
            v-for="locale in locales"
            :key="locale"
            :field="currentField"
            :show-help-text="showHelpText"
            :full-width-content="fullWidthContent"
        >

            <FormLabel
                :label-for="getLabelFor(locale)"
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
                <textarea
                    v-if="inputType === 'textarea'"
                    v-bind="extraAttributes"
                    class="block w-full form-control form-input form-control-bordered py-3 h-auto"
                    :id="getLabelFor(locale)"
                    :dusk="field.attribute"
                    :value="value[locale]"
                    @input="handleChange($event, locale)"
                    :maxlength="field.enforceMaxlength ? field.maxlength : -1"
                    :placeholder="placeholder"
                />
                <MarkdownEditor
                    v-else-if="inputType === 'markdown'"
                    :ref="`mdEditor-${locale}`"
                    v-show="currentlyIsVisible"
                    :class="{ 'form-control-bordered-error': hasError }"
                    :id="getLabelFor(locale)"
                    :previewer="previewer"
                    :readonly="currentlyIsReadonly"
                    @initialize="initialize(locale)"
                    @change="handleChange($event, locale)"
                />
                <input
                    v-else
                    :id="getLabelFor(locale)"
                    :type="inputType"
                    class="w-full form-control form-input form-control-bordered"
                    :class="errorClasses"
                    :placeholder="field.name"
                    v-model="value[locale]"
                />

                <HelpText class="help-text-error" v-if="localeErrors[locale]">
                    {{ localeErrors[locale][0] }}
                </HelpText>
            </template>
        </DefaultField>
    </div>
</template>

<script>
import isNil from 'lodash/isNil';
import { DependentFormField, HandlesValidationErrors } from 'laravel-nova';

export default {
    mixins: [DependentFormField, HandlesValidationErrors],

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
        inputType() {
            return this.field.inputType;
        },

        localeErrors() {
            const { errors } = this.errors;

            if (!errors || !errors[this.field.attribute]) {
                return {};
            }

            const fieldErrors = errors[this.field.attribute];

            return fieldErrors.reduce((acc, error) => {
                const obj = JSON.parse(error);

                const entries = Object.entries(obj);

                for (const [locale, message] of entries) {
                    if (!acc[locale]) {
                        acc[locale] = [];
                    }

                    acc[locale].push(message);
                }

                return acc;
            }, {});
        },

        // TODO preview?
        // previewer() {
        //     if (!this.isActionRequest) {
        //         return this.fetchPreviewContent
        //     }
        // },
    },

    methods: {
        getLabelFor(locale) {
            return `${this.field.uniqueKey}-${locale}`;
        },
        /*
        * Set the initial, internal value for the field.
        */
        setInitialValue() {
            this.value = {};

            for (const locale of this.locales) {
                this.value[locale] = this.field.value?.[locale] || '';
            }
        },

        getErrors(locale) {
            return this.localeErrors[locale] || [];
        },

        /**
         * Fill the given FormData object with the field's internal value.
         */
        fill(formData) {
            this.fillIfVisible(formData, this.field.attribute, JSON.stringify(this.value));
        },

        /**
         * Update the field's internal value
         */
        handleChangeMarkdown(value, locale) {
            this.value[locale] = value;

            if (this.field) {
                this.emitFieldValueChange(this.fieldAttribute, this.value)
            }
        },

        /**
         * Update the field's internal value
         */
        handleChangeTextarea(event, locale) {
            this.value[locale] = event.target.value;

            if (this.field) {
                this.emitFieldValueChange(this.fieldAttribute, this.value)
                this.$emit('field-changed')
            }
        },

        /**
         * Update the field's internal value
         */
        handleChange(event, locale) {
            if (this.inputType === 'markdown') {
                // event is only the value
                this.handleChangeMarkdown(event, locale);
                return;
            }

            if (this.inputType === 'textarea') {
                this.handleChangeTextarea(event, locale);
                return;
            }
        },

        listenToValueChanges(value) {
            if (this.currentlyIsVisible) {
                this.$refs.theMarkdownEditor.setValue(value)
            }

            this.handleChange(value)
        },

        /**
         * Initialize the Markdown Editor
         */
        initialize(locale) {
            const mdEditor = this.$refs[`mdEditor-${locale}`];

            if (!mdEditor?.length) {
                return;
            }

            mdEditor[0].setValue(this.value[locale] ?? this.currentField.value[locale]);

            Nova.$on(`${this.fieldAttributeValueEventName}:${locale}`, this.listenToValueChanges);
        },

        async fetchPreviewContent(value) {
            Nova.$progress.start()

            const {
                data: { preview },
            } = await Nova.request().post(
                `/nova-api/${this.resourceName}/field/${this.fieldAttribute}/preview`,
                { value },
                {
                    params: {
                        editing: true,
                        editMode: isNil(this.resourceId) ? 'create' : 'update',
                    },
                }
            )

            Nova.$progress.done()

            return preview
        },
    },

    beforeUnmount() {
        this.locales.forEach(locale => {
            Nova.$off(`${this.fieldAttributeValueEventName}:${locale}`, this.listenToValueChanges);
        });
    },
}
</script>
